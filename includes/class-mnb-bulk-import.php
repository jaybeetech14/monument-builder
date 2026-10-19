<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Bulk_Import
 *
 * Handles bulk upload of product/variation images via ZIP + CSV.
 *
 * CSV format (with header row):
 *   product_sku, color, image_file
 *
 *   product_sku  → WooCommerce product SKU  (or product name if no SKU set)
 *   color        → Variation attribute value (e.g. "Jet Black", "India Red")
 *   image_file   → Filename inside the uploaded ZIP
 *
 * If color is blank the image is assigned as the main product image.
 * If color is provided the image is assigned to that variation image.
 */
class MNB_Bulk_Import {

    const NONCE_UPLOAD = 'mnb_bulk_import_upload';
    const NONCE_PROCESS = 'mnb_bulk_import_process';
    const TRANSIENT_KEY = 'mnb_bulk_import_data';

    // ── Hooks ──────────────────────────────────────────────────────────────

    public static function init() {
        add_action( 'admin_menu',                [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_post_mnb_bulk_upload',  [ __CLASS__, 'handle_upload' ] );
        add_action( 'wp_ajax_mnb_bulk_process',    [ __CLASS__, 'ajax_process_row' ] );
        add_action( 'admin_enqueue_scripts',       [ __CLASS__, 'enqueue' ] );
    }

    public static function add_menu() {
        add_submenu_page(
            'monument-builder',
            'Bulk Image Import',
            'Bulk Image Import',
            'manage_woocommerce',
            'mnb-bulk-import',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'mnb-bulk-import' ) === false ) return;
        wp_add_inline_script( 'jquery', self::progress_js() );
    }

    // ── Step 1: Upload ZIP + CSV ───────────────────────────────────────────

    public static function handle_upload() {
        check_admin_referer( self::NONCE_UPLOAD );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        $errors = [];

        // ── Validate ZIP ──────────────────────────────────────────────────
        if ( empty( $_FILES['mnb_zip']['name'] ) || $_FILES['mnb_zip']['error'] !== UPLOAD_ERR_OK ) {
            wp_die( 'ZIP upload failed or missing.' );
        }

        $zip_tmp = $_FILES['mnb_zip']['tmp_name'];
        $zip_name = sanitize_file_name( $_FILES['mnb_zip']['name'] );

        if ( strtolower( pathinfo( $zip_name, PATHINFO_EXTENSION ) ) !== 'zip' ) {
            wp_die( 'Please upload a .zip file.' );
        }

        // ── Validate CSV ──────────────────────────────────────────────────
        if ( empty( $_FILES['mnb_csv']['name'] ) || $_FILES['mnb_csv']['error'] !== UPLOAD_ERR_OK ) {
            wp_die( 'CSV upload failed or missing.' );
        }
        $csv_content = file_get_contents( $_FILES['mnb_csv']['tmp_name'] );

        // ── Extract ZIP to temp dir ───────────────────────────────────────
        $upload_dir = wp_upload_dir();
        $extract_dir = trailingslashit( $upload_dir['basedir'] ) . 'mnb-bulk-import/';
        if ( file_exists( $extract_dir ) ) {
            self::rrmdir( $extract_dir );
        }
        wp_mkdir_p( $extract_dir );

        $zip = new ZipArchive();
        if ( $zip->open( $zip_tmp ) !== true ) {
            wp_die( 'Could not open ZIP file.' );
        }
        $zip->extractTo( $extract_dir );
        $zip->close();

        // ── Index extracted files (filename → full path) ──────────────────
        $extracted = [];
        $iterator  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $extract_dir ) );
        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $extracted[ strtolower( $file->getFilename() ) ] = $file->getPathname();
            }
        }

        // ── Parse CSV ─────────────────────────────────────────────────────
        $rows = self::parse_csv( $csv_content );

        // ── Build preview rows ────────────────────────────────────────────
        $preview = [];
        $header_skipped = false;

        foreach ( $rows as $row ) {
            if ( ! $header_skipped ) {
                // Skip header row if it contains column names
                $lower = array_map( 'strtolower', array_map( 'trim', $row ) );
                if ( in_array( 'product_sku', $lower ) || in_array( 'sku', $lower ) ) {
                    $header_skipped = true;
                    continue;
                }
                $header_skipped = true; // always skip first row
            }

            if ( count( $row ) < 2 ) continue;

            $sku_or_name = trim( $row[0] );
            $color       = isset( $row[1] ) ? trim( $row[1] ) : '';
            $img_file    = isset( $row[2] ) ? trim( $row[2] ) : '';

            if ( $sku_or_name === '' && $img_file === '' ) continue;

            // Auto-detect image filename from SKU+color if not provided
            if ( $img_file === '' && $color !== '' ) {
                $img_file = strtolower( str_replace( [ ' ', '/' ], [ '_', '_' ], $sku_or_name ) )
                          . '-' . strtolower( str_replace( ' ', '_', $color ) ) . '.jpeg';
            }

            // Resolve file
            $file_lower = strtolower( $img_file );
            $file_path  = $extracted[ $file_lower ] ?? null;

            // Try .jpg if .jpeg not found
            if ( ! $file_path ) {
                $try = preg_replace( '/\.jpeg$/i', '.jpg', $file_lower );
                $file_path = $extracted[ $try ] ?? null;
            }

            // Look up product
            $product = wc_get_product( wc_get_product_id_by_sku( $sku_or_name ) );
            if ( ! $product ) {
                // Try by name
                $products = wc_get_products( [ 'name' => $sku_or_name, 'limit' => 1 ] );
                $product  = ! empty( $products ) ? $products[0] : null;
            }

            $status = 'pending';
            $note   = '';

            if ( ! $product ) {
                $status = 'error';
                $note   = 'Product not found: ' . esc_html( $sku_or_name );
            } elseif ( ! $file_path ) {
                $status = 'error';
                $note   = 'Image file not found in ZIP: ' . esc_html( $img_file );
            }

            $preview[] = [
                'sku'       => $sku_or_name,
                'color'     => $color,
                'img_file'  => $img_file,
                'file_path' => $file_path,
                'product_id'=> $product ? $product->get_id() : 0,
                'status'    => $status,
                'note'      => $note,
            ];
        }

        // Store in transient for the AJAX processor
        set_transient( self::TRANSIENT_KEY, [
            'rows'        => $preview,
            'extract_dir' => $extract_dir,
        ], HOUR_IN_SECONDS );

        wp_redirect( admin_url( 'admin.php?page=mnb-bulk-import&step=preview' ) );
        exit;
    }

    // ── Step 2 (AJAX): Process one row at a time ───────────────────────────

    public static function ajax_process_row() {
        check_ajax_referer( self::NONCE_PROCESS, 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $index  = absint( $_POST['index'] ?? 0 );
        $data   = get_transient( self::TRANSIENT_KEY );

        if ( ! $data || ! isset( $data['rows'][ $index ] ) ) {
            wp_send_json_error( 'Session expired. Please re-upload.' );
        }

        $row = $data['rows'][ $index ];

        if ( $row['status'] === 'error' ) {
            wp_send_json_success( [
                'index'  => $index,
                'status' => 'error',
                'note'   => $row['note'],
            ] );
        }

        if ( $row['status'] === 'done' ) {
            wp_send_json_success( [ 'index' => $index, 'status' => 'done', 'note' => 'Already processed' ] );
        }

        // ── Upload image to Media Library ─────────────────────────────────
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $file_path = $row['file_path'];
        $file_name = basename( $file_path );
        $upload    = wp_upload_bits( $file_name, null, file_get_contents( $file_path ) );

        if ( ! empty( $upload['error'] ) ) {
            wp_send_json_success( [
                'index'  => $index,
                'status' => 'error',
                'note'   => 'Upload failed: ' . $upload['error'],
            ] );
        }

        $attach_id = wp_insert_attachment( [
            'post_title'     => pathinfo( $file_name, PATHINFO_FILENAME ),
            'post_mime_type' => wp_check_filetype( $upload['file'] )['type'],
            'post_status'    => 'inherit',
        ], $upload['file'] );

        wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $upload['file'] ) );

        // ── Assign to product or variation ────────────────────────────────
        $product_id = $row['product_id'];
        $color      = $row['color'];
        $assigned   = false;
        $note       = '';

        if ( $color !== '' ) {
            // Find matching variation
            $product = wc_get_product( $product_id );
            if ( $product && $product->is_type( 'variable' ) ) {
                foreach ( $product->get_children() as $var_id ) {
                    $variation = wc_get_product( $var_id );
                    $attrs     = $variation->get_variation_attributes();
                    foreach ( $attrs as $attr_val ) {
                        if ( strtolower( $attr_val ) === strtolower( $color ) ) {
                            update_post_meta( $var_id, '_thumbnail_id', $attach_id );
                            $assigned = true;
                            $note     = "Set variation image (ID $var_id)";
                            break 2;
                        }
                    }
                }
                if ( ! $assigned ) {
                    $note = "Variation '$color' not found — set as product gallery image instead";
                    self::add_to_gallery( $product_id, $attach_id );
                    $assigned = true;
                }
            }
        }

        // Assign as main product image if no color / not a variable product
        if ( ! $assigned ) {
            $existing = get_post_thumbnail_id( $product_id );
            if ( ! $existing ) {
                set_post_thumbnail( $product_id, $attach_id );
                $note = 'Set as main product image';
            } else {
                self::add_to_gallery( $product_id, $attach_id );
                $note = 'Added to product gallery';
            }
        }

        // Mark row as done in transient
        $data['rows'][ $index ]['status'] = 'done';
        $data['rows'][ $index ]['note']   = $note;
        set_transient( self::TRANSIENT_KEY, $data, HOUR_IN_SECONDS );

        wp_send_json_success( [
            'index'      => $index,
            'status'     => 'done',
            'note'       => $note,
            'attach_id'  => $attach_id,
        ] );
    }

    // ── Render admin page ──────────────────────────────────────────────────

    public static function render_page() {
        $step = sanitize_key( $_GET['step'] ?? 'upload' );
        $data = get_transient( self::TRANSIENT_KEY );
        ?>
        <div class="wrap">
            <h1>🗂️ Monument Bulk Image Import</h1>

            <?php if ( $step === 'upload' || ! $data ) : ?>
            <!-- ── STEP 1: Upload form ─────────────────────────────── -->
            <div style="max-width:680px;">
                <div style="background:#fff8e1;border:1px solid #c8a84b;border-radius:6px;padding:16px 20px;margin-bottom:20px;">
                    <h3 style="margin:0 0 8px;">CSV Format</h3>
                    <p style="margin:0 0 6px;">One row per image. Column order: <code>product_sku, color, image_file</code></p>
                    <table style="font-size:13px;border-collapse:collapse;width:100%;">
                        <tr style="background:#f5f0e8;">
                            <th style="padding:4px 8px;text-align:left;border:1px solid #ddd;">product_sku</th>
                            <th style="padding:4px 8px;text-align:left;border:1px solid #ddd;">color</th>
                            <th style="padding:4px 8px;text-align:left;border:1px solid #ddd;">image_file</th>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px;border:1px solid #ddd;">flat-24x12</td>
                            <td style="padding:4px 8px;border:1px solid #ddd;">Jet Black</td>
                            <td style="padding:4px 8px;border:1px solid #ddd;">24x12_bass-Jet_Black.jpeg</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px;border:1px solid #ddd;">flat-24x12</td>
                            <td style="padding:4px 8px;border:1px solid #ddd;">India Red</td>
                            <td style="padding:4px 8px;border:1px solid #ddd;">24x12_bass-India_Red.jpeg</td>
                        </tr>
                        <tr>
                            <td style="padding:4px 8px;border:1px solid #ddd;">slant-18x10</td>
                            <td style="padding:4px 8px;border:1px solid #ddd;"></td>
                            <td style="padding:4px 8px;border:1px solid #ddd;">slant-Bahama_Blue.jpeg</td>
                        </tr>
                    </table>
                    <p style="margin:8px 0 0;font-size:12px;color:#777;">
                        <strong>product_sku</strong> — WooCommerce SKU or exact product name.<br>
                        <strong>color</strong> — Variation attribute value. Leave blank to set as main product image.<br>
                        <strong>image_file</strong> — Filename inside your ZIP. Leave blank to auto-detect from SKU + color.
                    </p>
                </div>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                      enctype="multipart/form-data">
                    <?php wp_nonce_field( self::NONCE_UPLOAD ); ?>
                    <input type="hidden" name="action" value="mnb_bulk_upload">

                    <table class="form-table">
                        <tr>
                            <th><label for="mnb_zip">Images ZIP</label></th>
                            <td>
                                <input type="file" name="mnb_zip" id="mnb_zip" accept=".zip" required>
                                <p class="description">ZIP file containing all your monument images. Max PHP upload limit.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="mnb_csv">Mapping CSV</label></th>
                            <td>
                                <input type="file" name="mnb_csv" id="mnb_csv" accept=".csv,.txt" required>
                                <p class="description">CSV with columns: product_sku, color, image_file</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button( '⬆ Upload &amp; Preview', 'primary large' ); ?>
                </form>
            </div>

            <?php elseif ( $step === 'preview' && $data ) : ?>
            <!-- ── STEP 2: Preview + Process ──────────────────────── -->
            <?php
                $rows   = $data['rows'];
                $total  = count( $rows );
                $errors = array_filter( $rows, fn($r) => $r['status'] === 'error' );
                $good   = $total - count( $errors );
            ?>
            <p>
                Found <strong><?php echo $total; ?></strong> rows —
                <strong style="color:#2e7d32;"><?php echo $good; ?> ready</strong>
                <?php if ( $errors ) : ?>,
                <strong style="color:#c62828;"><?php echo count( $errors ); ?> errors</strong>
                <?php endif; ?>.
            </p>

            <div id="mnb-progress-wrap" style="display:none;margin-bottom:16px;">
                <div style="background:#f0f0f0;border-radius:4px;height:24px;overflow:hidden;width:500px;">
                    <div id="mnb-progress-bar"
                         style="height:100%;background:#c8a84b;width:0%;transition:width 0.3s ease;"></div>
                </div>
                <p id="mnb-progress-text" style="margin:4px 0;font-size:13px;color:#555;"></p>
            </div>

            <button id="mnb-start-import" class="button button-primary button-large">
                ▶ Start Import (<?php echo $good; ?> images)
            </button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=mnb-bulk-import' ) ); ?>"
               class="button" style="margin-left:8px;">↩ Start Over</a>

            <table class="widefat striped" style="margin-top:16px;" id="mnb-results-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product SKU / Name</th>
                        <th>Color</th>
                        <th>Image File</th>
                        <th>Status</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $rows as $i => $row ) : ?>
                    <tr id="mnb-row-<?php echo $i; ?>"
                        style="<?php echo $row['status'] === 'error' ? 'background:#fff3f3;' : ''; ?>">
                        <td><?php echo $i + 1; ?></td>
                        <td><?php echo esc_html( $row['sku'] ); ?></td>
                        <td><?php echo esc_html( $row['color'] ); ?></td>
                        <td style="font-size:12px;"><?php echo esc_html( $row['img_file'] ); ?></td>
                        <td class="mnb-row-status">
                            <?php if ( $row['status'] === 'error' ) : ?>
                                <span style="color:#c62828;">✗ Error</span>
                            <?php else : ?>
                                <span style="color:#999;">⏳ Pending</span>
                            <?php endif; ?>
                        </td>
                        <td class="mnb-row-note" style="font-size:12px;color:#666;">
                            <?php echo esc_html( $row['note'] ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <script>
            jQuery(function($){
                var nonce  = <?php echo wp_json_encode( wp_create_nonce( self::NONCE_PROCESS ) ); ?>;
                var rows   = <?php echo wp_json_encode( array_keys( array_filter( $rows, fn($r) => $r['status'] !== 'error' ) ) ); ?>;
                var total  = rows.length;
                var done   = 0;

                $('#mnb-start-import').on('click', function(){
                    $(this).prop('disabled', true).text('Processing…');
                    $('#mnb-progress-wrap').show();
                    processNext(0);
                });

                function processNext(idx) {
                    if (idx >= rows.length) {
                        $('#mnb-progress-text').text('✅ Import complete! ' + done + ' images processed.');
                        $('#mnb-start-import').text('✅ Done');
                        return;
                    }
                    var rowIndex = rows[idx];
                    $.post(ajaxurl, {
                        action: 'mnb_bulk_process',
                        nonce:  nonce,
                        index:  rowIndex
                    }, function(res){
                        done++;
                        var pct = Math.round((done / total) * 100);
                        $('#mnb-progress-bar').css('width', pct + '%');
                        $('#mnb-progress-text').text('Processing ' + done + ' of ' + total + '…');

                        var $row = $('#mnb-row-' + rowIndex);
                        if (res.success) {
                            var d = res.data;
                            $row.find('.mnb-row-status').html(
                                d.status === 'done'
                                    ? '<span style="color:#2e7d32;">✓ Done</span>'
                                    : '<span style="color:#c62828;">✗ Error</span>'
                            );
                            $row.find('.mnb-row-note').text(d.note || '');
                        } else {
                            $row.find('.mnb-row-status').html('<span style="color:#c62828;">✗ Failed</span>');
                        }
                        processNext(idx + 1);
                    }).fail(function(){
                        done++;
                        processNext(idx + 1);
                    });
                }
            });
            </script>

            <?php endif; ?>
        </div>
        <?php
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private static function parse_csv( $content ) {
        $content = str_replace( "\r\n", "\n", $content );
        $content = str_replace( "\r", "\n", $content );
        $rows    = [];
        foreach ( explode( "\n", $content ) as $line ) {
            $line = trim( $line );
            if ( $line === '' ) continue;
            $rows[] = str_getcsv( $line );
        }
        return $rows;
    }

    private static function add_to_gallery( $product_id, $attach_id ) {
        $gallery = get_post_meta( $product_id, '_product_image_gallery', true );
        $ids     = $gallery ? explode( ',', $gallery ) : [];
        if ( ! in_array( (string) $attach_id, $ids ) ) {
            $ids[]   = $attach_id;
            update_post_meta( $product_id, '_product_image_gallery', implode( ',', $ids ) );
        }
    }

    private static function rrmdir( $dir ) {
        if ( ! is_dir( $dir ) ) return;
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $items as $item ) {
            $item->isDir() ? rmdir( $item->getRealPath() ) : unlink( $item->getRealPath() );
        }
        rmdir( $dir );
    }

    private static function progress_js() { return ''; } // placeholder
}
