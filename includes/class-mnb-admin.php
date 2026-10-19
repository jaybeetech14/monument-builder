<?php
defined( 'ABSPATH' ) || exit;

class MNB_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_post_mnb_save_settings', [ __CLASS__, 'save_settings' ] );
        add_action( 'admin_post_mnb_remove_font',     [ __CLASS__, 'remove_font' ] );
        add_action( 'admin_post_mnb_save_lines',    [ __CLASS__, 'save_text_lines' ] );
        add_action( 'admin_notices',         [ __CLASS__, 'admin_notices' ] );

        // Monument design meta boxes
        add_action( 'add_meta_boxes', [ __CLASS__, 'monument_meta_boxes' ] );
        add_action( 'save_post_mnb_monument', [ __CLASS__, 'save_monument_meta' ] );
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public static function register_menu() {
        add_menu_page(
            'MNB Monuments',
            'MNB Monuments',
            'manage_woocommerce',
            'monument-builder',
            [ __CLASS__, 'page_dashboard' ],
            'dashicons-store',
            56
        );

        add_submenu_page( 'monument-builder', 'Dashboard',      'Dashboard',      'manage_woocommerce', 'monument-builder',       [ __CLASS__, 'page_dashboard' ] );
        add_submenu_page( 'monument-builder', 'Monument Designs','Monument Designs','manage_woocommerce', 'edit.php?post_type=mnb_monument', null );
        add_submenu_page( 'monument-builder', 'Add New Design', 'Add New Design', 'manage_woocommerce', 'post-new.php?post_type=mnb_monument', null );
        add_submenu_page( 'monument-builder', 'Text Line Config','Text Fields',   'manage_woocommerce', 'mnb-text-lines',      [ __CLASS__, 'page_text_lines' ] );
        add_submenu_page( 'monument-builder', 'Settings',       'Settings',       'manage_woocommerce', 'mnb-settings',        [ __CLASS__, 'page_settings' ] );
        add_submenu_page( 'monument-builder', 'Order Workflow',  'Order Workflow', 'manage_woocommerce', 'mnb-order-workflow',  [ __CLASS__, 'page_order_workflow' ] );
        add_submenu_page( 'monument-builder', 'Epitaph Presets', 'Epitaph Presets', 'manage_woocommerce', 'mnb-epitaphs', [ 'MNB_Epitaph', 'render_admin_page' ] );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'mnb' ) === false && get_post_type() !== 'mnb_monument' ) return;

        wp_enqueue_style(
            'mnb-admin',
            MNB_PLUGIN_URL . 'assets/css/mnb-admin.css',
            [],
            MNB_VERSION
        );

        // Load WP media uploader for font image and font file upload buttons
        wp_enqueue_media();

        // Register a minimal script so we can attach our inline JS to a known handle
        wp_register_script( 'mnb-admin-media', false, [ 'jquery', 'media-upload' ], false, true );
        wp_enqueue_script( 'mnb-admin-media' );
        wp_add_inline_script( 'mnb-admin-media', self::media_uploader_js() );
    }

    private static function media_uploader_js() {
        return <<<'JS'
jQuery(function($){
    // Font IMAGE upload buttons
    $(document).on('click', '.mnb-upload-font-image', function(e){
        e.preventDefault();
        var btn    = $(this);
        var key    = btn.data('key');
        var input  = $('input[name="mnb_font_image[' + key + ']"]');
        var frame  = wp.media({
            title:    'Select Font Preview Image',
            button:   { text: 'Use this image' },
            multiple: false,
            library:  { type: 'image' }
        });
        frame.on('select', function(){
            var att = frame.state().get('selection').first().toJSON();
            input.val(att.id);
            var wrap = btn.closest('td');
            wrap.find('.mnb-font-image-preview').remove();
            var thumb = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            btn.before('<img class="mnb-font-image-preview" src="'+thumb+'" style="max-height:50px;display:block;margin-bottom:8px;">');
            btn.text('Change Image');
            wrap.find('.mnb-font-image-remove').show();
        });
        frame.open();
    });

    // Font IMAGE remove buttons
    $(document).on('click', '.mnb-font-image-remove', function(e){
        e.preventDefault();
        var btn   = $(this);
        var key   = btn.data('key');
        $('input[name="mnb_font_image[' + key + ']"]').val('');
        btn.closest('td').find('.mnb-font-image-preview').remove();
        btn.hide();
        btn.closest('td').find('.mnb-upload-font-image').text('Upload Image');
    });

    // Font FILE upload buttons
    $(document).on('click', '.mnb-upload-font-file', function(e){
        e.preventDefault();
        var btn   = $(this);
        var key   = btn.data('key');
        var input = $('input[name="mnb_font_file[' + key + ']"]');
        var frame = wp.media({
            title:    'Select Font File (.ttf / .woff)',
            button:   { text: 'Use this font file' },
            multiple: false,
            library:  { type: [ 'application/x-font-ttf', 'application/font-woff',
                                  'application/font-woff2', 'font/ttf', 'font/woff',
                                  'font/woff2', 'application/octet-stream' ] }
        });
        frame.on('select', function(){
            var att   = frame.state().get('selection').first().toJSON();
            var fname = att.filename || att.title || ('Font ID ' + att.id);
            input.val(att.id);
            btn.closest('td').find('.mnb-font-file-name').text('✅ ' + fname).show();
            btn.text('Change File');
            btn.closest('td').find('.mnb-font-file-remove').show();
        });
        // Allow all file types since font MIME types vary by browser
        frame.on('ready', function(){
            frame.$el.find('.media-toolbar-secondary').prepend(
                '<p style="padding:8px 0;color:#666;font-size:12px;">Select .ttf, .woff, or .woff2 file</p>'
            );
        });
        frame.open();
    });

    // Font FILE remove buttons
    $(document).on('click', '.mnb-font-file-remove', function(e){
        e.preventDefault();
        var btn = $(this);
        var key = btn.data('key');
        $('input[name="mnb_font_file[' + key + ']"]').val('');
        btn.closest('td').find('.mnb-font-file-name').text('').hide();
        btn.hide();
        btn.closest('td').find('.mnb-upload-font-file').text('Upload Font File');
    });
    // Delete font row
    $(document).on('click', '.mnb-delete-font-row', function() {
        var btn = $(this);
        var key = btn.data('key');
        if ( ! confirm('Delete font "' + key + '"? This will also remove its uploaded file and preview image. This cannot be undone.') ) return;
        var row = btn.closest('tr');
        // Set the delete flag hidden input
        row.find('.mnb-delete-flag').val(key);
        // Grey out the row visually
        row.css({ opacity: '0.4', textDecoration: 'line-through' });
        btn.text('Marked for deletion').prop('disabled', true);
        row.find('.mnb-font-name-input').prop('disabled', true);
    });
});
JS;
    }

    // ── Dashboard Page ────────────────────────────────────────────────────────

    public static function page_dashboard() {
        $monument_count  = wp_count_posts( 'mnb_monument' )->publish ?? 0;

        // Monument products (WC products with _mnb_is_monument = 1)
        $monument_products = get_posts( [
            'post_type'   => 'product',
            'meta_key'    => '_mnb_is_monument',
            'meta_value'  => '1',
            'numberposts' => -1,
            'fields'      => 'ids',
        ] );

        // Orders with engraving
        $engraving_orders = wc_get_orders( [
            'limit'      => -1,
            'meta_key'   => '',
            'return'     => 'ids',
        ] );

        // Orders by custom status
        $in_production = count( wc_get_orders( [ 'status' => 'in-production', 'limit' => -1 ] ) );
        $engraving     = count( wc_get_orders( [ 'status' => 'engraving',     'limit' => -1 ] ) );
        $ready_ship    = count( wc_get_orders( [ 'status' => 'ready-ship',    'limit' => -1 ] ) );

        ?>
        <div class="wrap mnb-admin-wrap">
            <h1>🪨 Monument Builder — Dashboard</h1>

            <div class="mnb-stats-grid">
                <div class="mnb-stat-card">
                    <span class="mnb-stat-number"><?php echo esc_html( $monument_count ); ?></span>
                    <span class="mnb-stat-label">Monument Designs</span>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=mnb_monument' ) ); ?>" class="mnb-stat-link">Manage →</a>
                </div>
                <div class="mnb-stat-card">
                    <span class="mnb-stat-number"><?php echo esc_html( count( $monument_products ) ); ?></span>
                    <span class="mnb-stat-label">Monument Products</span>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="mnb-stat-link">View Products →</a>
                </div>
                <div class="mnb-stat-card mnb-stat-warning">
                    <span class="mnb-stat-number"><?php echo esc_html( $in_production ); ?></span>
                    <span class="mnb-stat-label">In Production</span>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order&post_status=wc-in-production' ) ); ?>" class="mnb-stat-link">View →</a>
                </div>
                <div class="mnb-stat-card mnb-stat-active">
                    <span class="mnb-stat-number"><?php echo esc_html( $engraving ); ?></span>
                    <span class="mnb-stat-label">Engraving</span>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order&post_status=wc-engraving' ) ); ?>" class="mnb-stat-link">View →</a>
                </div>
                <div class="mnb-stat-card mnb-stat-success">
                    <span class="mnb-stat-number"><?php echo esc_html( $ready_ship ); ?></span>
                    <span class="mnb-stat-label">Ready to Ship</span>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order&post_status=wc-ready-ship' ) ); ?>" class="mnb-stat-link">View →</a>
                </div>
            </div>

            <div class="mnb-quick-links">
                <h2>Quick Actions</h2>
                <div class="mnb-action-grid">
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=mnb_monument' ) ); ?>" class="mnb-action-btn">
                        ➕ Add Monument Design
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="mnb-action-btn">
                        ➕ Add WC Product
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mnb-text-lines' ) ); ?>" class="mnb-action-btn">
                        ✏️ Configure Text Fields
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mnb-settings' ) ); ?>" class="mnb-action-btn">
                        ⚙️ Plugin Settings
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>" class="mnb-action-btn">
                        📦 All Orders
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=mnb-order-workflow' ) ); ?>" class="mnb-action-btn">
                        🔄 Order Workflow
                    </a>
                </div>
            </div>

            <div class="mnb-setup-checklist">
                <h2>Setup Checklist</h2>
                <?php self::render_setup_checklist( $monument_count, count( $monument_products ) ); ?>
            </div>

            <?php MNB_Elementor_Import::render_import_button(); ?>

        </div>
        <?php
    }

    private static function render_setup_checklist( $design_count, $product_count ) {
        $fonts   = get_option( 'mnb_fonts', [] );
        $lines   = get_option( 'mnb_text_lines', [] );

        $checks = [
            [ 'done' => $design_count > 0,   'label' => 'At least one Monument Design added',              'link' => admin_url( 'post-new.php?post_type=mnb_monument' ) ],
            [ 'done' => $product_count > 0,  'label' => 'At least one WooCommerce Monument Product created', 'link' => admin_url( 'post-new.php?post_type=product' ) ],
            [ 'done' => count( $fonts ) >= 2, 'label' => 'Fonts configured (Sunk Roman · Vermarco Script · Spacerite Vermarco)', 'link' => admin_url( 'admin.php?page=mnb-settings' ) ],
            [ 'done' => count( $lines ) >= 5, 'label' => 'All 5 text lines configured',                       'link' => admin_url( 'admin.php?page=mnb-text-lines' ) ],
        ];

        echo '<ul class="mnb-checklist">';
        foreach ( $checks as $check ) {
            $icon  = $check['done'] ? '✅' : '⬜';
            $class = $check['done'] ? 'mnb-check-done' : 'mnb-check-todo';
            printf(
                '<li class="mnb-check-item %s">%s <a href="%s">%s</a></li>',
                esc_attr( $class ),
                esc_html( $icon ),
                esc_url( $check['link'] ),
                esc_html( $check['label'] )
            );
        }
        echo '</ul>';
    }

    // ── Text Lines Config Page ────────────────────────────────────────────────

    public static function page_text_lines() {
        $lines = MNB_Product_Fields::get_text_lines();
        ?>
        <div class="wrap mnb-admin-wrap">
            <h1>Text Field Configuration</h1>
            <p>These fields appear on every monument product page. Changes apply globally to all monument products.</p>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'mnb_save_lines', 'mnb_lines_nonce' ); ?>
                <input type="hidden" name="action" value="mnb_save_lines">

                <table class="wp-list-table widefat fixed striped mnb-lines-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Field Key</th>
                            <th>Label</th>
                            <th>Required?</th>
                            <th>Max Characters</th>
                            <th>Textarea?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $lines as $i => $line ) : ?>
                        <tr>
                            <td><?php echo esc_html( $i + 1 ); ?></td>
                            <td><code><?php echo esc_html( $line['key'] ); ?></code></td>
                            <td>
                                <input type="text" name="mnb_lines[<?php echo $i; ?>][label]"
                                       value="<?php echo esc_attr( $line['label'] ); ?>" class="regular-text">
                                <input type="hidden" name="mnb_lines[<?php echo $i; ?>][key]" value="<?php echo esc_attr( $line['key'] ); ?>">
                            </td>
                            <td>
                                <input type="checkbox" name="mnb_lines[<?php echo $i; ?>][required]" value="1"
                                    <?php checked( ! empty( $line['required'] ) ); ?>>
                            </td>
                            <td>
                                <input type="number" name="mnb_lines[<?php echo $i; ?>][max]"
                                       value="<?php echo esc_attr( $line['max'] ?? 80 ); ?>" min="10" max="200" style="width:80px">
                            </td>
                            <td>
                                <input type="checkbox" name="mnb_lines[<?php echo $i; ?>][textarea]" value="1"
                                    <?php checked( ! empty( $line['textarea'] ) ); ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button( 'Save Text Field Configuration' ); ?>
            </form>
        </div>
        <?php
    }

    // ── Settings Page ─────────────────────────────────────────────────────────

    public static function page_settings() {
        $fonts = MNB_Product_Fields::get_fonts();
        ?>
        <div class="wrap mnb-admin-wrap">
            <h1>Monument Settings</h1>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'mnb_save_settings', 'mnb_settings_nonce' ); ?>
                <input type="hidden" name="action" value="mnb_save_settings">

                <h2>Engraving Fonts</h2>
                <p>Manage the fonts available for customers. You can rename existing fonts, delete them, or add new ones. The <strong>Font Key</strong> is used internally — use lowercase letters and underscores only (e.g. <code>my_font</code>).</p>

                <table class="form-table" id="mnb-fonts-table">
                    <thead>
                        <tr>
                            <th style="width:200px;">Font Key <span style="font-weight:400;color:#999;">(internal, cannot change)</span></th>
                            <th>Display Name <span style="font-weight:400;color:#999;">(shown to customers)</span></th>
                            <th style="width:80px;">Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $fonts as $key => $name ) : ?>
                    <tr class="mnb-font-row" data-key="<?php echo esc_attr( $key ); ?>">
                        <td><code style="font-size:13px;"><?php echo esc_html( $key ); ?></code></td>
                        <td>
                            <input type="text"
                                   name="mnb_fonts[<?php echo esc_attr( $key ); ?>]"
                                   value="<?php echo esc_attr( $name ); ?>"
                                   class="regular-text mnb-font-name-input"
                                   placeholder="Display name">
                        </td>
                        <td>
                            <button type="button" class="button mnb-delete-font-row" title="Remove this font"
                                    style="color:#c0392b;border-color:#c0392b;"
                                    data-key="<?php echo esc_attr( $key ); ?>">
                                &#10005; Delete
                            </button>
                            <input type="hidden" name="mnb_fonts_delete[]" class="mnb-delete-flag" value="">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin:16px 0 24px;padding:16px;background:#f8f8f8;border:1px solid #ddd;border-radius:4px;">
                    <h3 style="margin:0 0 12px;font-size:14px;">&#43; Add New Font</h3>
                    <table style="width:100%;border-collapse:collapse;">
                        <tr>
                            <td style="padding:0 12px 0 0;width:220px;">
                                <label style="display:block;font-size:12px;color:#555;margin-bottom:3px;">Font Key <em>(lowercase, underscores)</em></label>
                                <input type="text" name="mnb_new_font_key" id="mnb-new-font-key"
                                       class="regular-text" placeholder="e.g. spacerite_roman"
                                       pattern="[a-z0-9_]+" style="width:100%;">
                            </td>
                            <td style="padding:0 12px 0 0;">
                                <label style="display:block;font-size:12px;color:#555;margin-bottom:3px;">Display Name</label>
                                <input type="text" name="mnb_new_font_name" id="mnb-new-font-name"
                                       class="regular-text" placeholder="e.g. Spacerite Roman"
                                       style="width:100%;">
                            </td>
                        </tr>
                    </table>
                    <p style="margin:8px 0 0;font-size:12px;color:#888;">
                        After adding, scroll down to upload the font file and preview image for the new font.
                    </p>
                </div>

                <h2>Font Preview Images</h2>
                <p>Upload a sample image for each font. These appear as radio button labels on the product page.</p>
                <table class="form-table">
                    <?php foreach ( $fonts as $key => $name ) : ?>
                    <?php $img_id = get_option( 'mnb_font_image_' . $key ); ?>
                    <tr>
                        <th><label><?php echo esc_html( $name ); ?></label></th>
                        <td>
                            <?php if ( $img_id ) : ?>
                                <?php echo wp_get_attachment_image( $img_id, 'thumbnail', false, [ 'class' => 'mnb-font-image-preview', 'style' => 'max-height:50px;display:block;margin-bottom:8px;' ] ); ?>
                            <?php endif; ?>
                            <input type="hidden" name="mnb_font_image[<?php echo esc_attr( $key ); ?>]"
                                   value="<?php echo esc_attr( $img_id ); ?>" class="mnb-font-image-id">
                            <button type="button" class="button mnb-upload-font-image" data-key="<?php echo esc_attr( $key ); ?>">
                                <?php echo $img_id ? 'Change Image' : 'Upload Image'; ?>
                            </button>
                            <button type="button" class="button mnb-font-image-remove" data-key="<?php echo esc_attr( $key ); ?>"
                                    style="<?php echo $img_id ? '' : 'display:none;'; ?>margin-left:6px;">
                                Remove
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>


                <h2>Font Files (for Canvas)</h2>
                <p>Upload the .ttf or .woff font files. These are used in the live canvas preview so customers see the actual engraving font.</p>
                <table class="form-table">
                    <?php foreach ( $fonts as $key => $name ) :
                        $file_url  = get_option( 'mnb_font_url_' . $key, '' );
                        $file_name = get_option( 'mnb_font_filename_' . $key, '' );
                    ?>
                    <tr>
                        <th><label for="mnb_font_upload_<?php echo esc_attr( $key ); ?>">
                            <?php echo esc_html( $name ); ?>
                        </label></th>
                        <td>
                            <?php if ( $file_url && $file_name ) : ?>
                                <p class="mnb-font-current">
                                    ✅ <strong><?php echo esc_html( $file_name ); ?></strong>
                                    &nbsp;
                                    <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=mnb_remove_font&key=' . esc_attr( $key ) . '&nonce=' . wp_create_nonce( 'mnb_remove_font_' . $key ) ) ); ?>"
                                       style="color:#c0392b;font-size:12px;">Remove</a>
                                </p>
                            <?php endif; ?>
                            <input type="file"
                                   id="mnb_font_upload_<?php echo esc_attr( $key ); ?>"
                                   name="mnb_font_upload[<?php echo esc_attr( $key ); ?>]"
                                   accept=".ttf,.woff,.woff2,font/ttf,font/woff,font/woff2,application/font-woff,application/x-font-ttf"
                                   style="margin-top:4px;">
                            <p class="description">Accepted: .ttf, .woff, .woff2 &nbsp;&middot;&nbsp; Max 2MB</p>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>


                <h2>Epitaph Presets</h2>
                <p>These appear as selectable options in the Epitaph dropdown on every monument product page. One per line.</p>
                <table class="form-table">
                    <tr>
                        <th><label for="mnb_epitaph_presets">Preset Epitaphs</label></th>
                        <td>
                            <?php
                            $presets = MNB_Epitaph::get_presets();
                            ?>
                            <textarea name="mnb_epitaph_presets" id="mnb_epitaph_presets"
                                      rows="12" style="width:100%;font-size:13px;"><?php echo esc_textarea( implode( "\n", $presets ) ); ?></textarea>
                            <p class="description">One epitaph per line. Customers can also choose "Write Your Own" to enter custom text.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>
        </div>
        <?php
    }

    // ── Order Workflow Page ───────────────────────────────────────────────────

    public static function page_order_workflow() {
        $statuses = [
            'processing'    => [ 'label' => 'Processing',     'color' => '#2271b1' ],
            'in-production' => [ 'label' => 'In Production',  'color' => '#1a73e8' ],
            'engraving'     => [ 'label' => 'Engraving',      'color' => '#b06000' ],
            'ready-ship'    => [ 'label' => 'Ready to Ship',  'color' => '#1e7e34' ],
            'completed'     => [ 'label' => 'Completed',      'color' => '#388e3c' ],
        ];

        echo '<div class="wrap mnb-admin-wrap">';
        echo '<h1>Order Workflow</h1>';
        echo '<p>Use these status stages to track monument orders through production.</p>';

        echo '<div class="mnb-workflow-diagram">';
        foreach ( $statuses as $slug => $info ) {
            $count = count( wc_get_orders( [ 'status' => $slug, 'limit' => -1 ] ) );
            $url   = admin_url( 'edit.php?post_type=shop_order&post_status=wc-' . $slug );
            printf(
                '<div class="mnb-workflow-step" style="border-color:%s">
                    <span class="mnb-wf-label" style="color:%s">%s</span>
                    <span class="mnb-wf-count">%d orders</span>
                    <a href="%s" class="mnb-wf-link">View →</a>
                 </div>',
                esc_attr( $info['color'] ),
                esc_attr( $info['color'] ),
                esc_html( $info['label'] ),
                $count,
                esc_url( $url )
            );
        }
        echo '</div>'; // .mnb-workflow-diagram

        echo '<h2 style="margin-top:32px;">Recent Engraving Orders</h2>';

        $orders = wc_get_orders( [
            'status' => [ 'in-production', 'engraving', 'ready-ship' ],
            'limit'  => 20,
        ] );

        if ( empty( $orders ) ) {
            echo '<p>No active engraving orders.</p>';
        } else {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
            foreach ( $orders as $order ) {
                printf(
                    '<tr>
                        <td><a href="%s">#%s</a></td>
                        <td>%s</td>
                        <td>%s</td>
                        <td>%s</td>
                        <td><a href="%s" class="button button-small">View Order</a></td>
                     </tr>',
                    esc_url( get_edit_post_link( $order->get_id() ) ),
                    esc_html( $order->get_order_number() ),
                    esc_html( $order->get_formatted_billing_full_name() ),
                    esc_html( wc_get_order_status_name( $order->get_status() ) ),
                    esc_html( $order->get_date_created()->date( 'M j, Y' ) ),
                    esc_url( get_edit_post_link( $order->get_id() ) )
                );
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    // ── Monument Design Meta Boxes ────────────────────────────────────────────

    public static function monument_meta_boxes() {
        add_meta_box(
            'mnb_monument_details',
            'Monument Design Details',
            [ __CLASS__, 'render_monument_meta_box' ],
            'mnb_monument',
            'normal',
            'high'
        );
    }

    public static function render_monument_meta_box( $post ) {
        wp_nonce_field( 'mnb_monument_meta', 'mnb_monument_meta_nonce' );

        $sku_pattern   = get_post_meta( $post->ID, '_mnb_sku_pattern', true );
        $monument_type = get_post_meta( $post->ID, '_mnb_monument_type', true ) ?: 'engraved';
        $sizes         = get_post_meta( $post->ID, '_mnb_sizes', true );
        $colors        = get_post_meta( $post->ID, '_mnb_colors', true );
        $styles        = get_post_meta( $post->ID, '_mnb_styles', true );
        $wc_product_id = get_post_meta( $post->ID, '_mnb_wc_product_id', true );
        $design_code   = get_post_meta( $post->ID, '_mnb_design_code', true );
        $notes         = get_post_meta( $post->ID, '_mnb_notes', true );
        ?>
        <table class="form-table mnb-monument-meta">
            <tr>
                <th><label for="mnb_monument_type">Monument Type</label></th>
                <td>
                    <select name="_mnb_monument_type" id="mnb_monument_type">
                        <option value="engraved" <?php selected( $monument_type, 'engraved' ); ?>>Engraved Design</option>
                        <option value="blank"    <?php selected( $monument_type, 'blank' ); ?>>Blank / Text Only</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="mnb_design_code">Design Code</label></th>
                <td>
                    <input type="text" id="mnb_design_code" name="_mnb_design_code"
                           value="<?php echo esc_attr( $design_code ); ?>" placeholder="e.g. PH" style="width:100px">
                    <p class="description">2-4 letter prefix used to build SKUs (e.g. PH → PH-2412-BLK)</p>
                </td>
            </tr>
            <tr>
                <th><label for="mnb_sku_pattern">SKU Pattern</label></th>
                <td>
                    <input type="text" id="mnb_sku_pattern" name="_mnb_sku_pattern"
                           value="<?php echo esc_attr( $sku_pattern ); ?>" class="regular-text"
                           placeholder="e.g. PH-{SIZE}-{CLR}">
                    <p class="description">Use {SIZE} and {CLR} as placeholders.</p>
                </td>
            </tr>
            <tr>
                <th><label for="mnb_sizes">Available Sizes</label></th>
                <td>
                    <input type="text" id="mnb_sizes" name="_mnb_sizes"
                           value="<?php echo esc_attr( $sizes ); ?>" class="regular-text"
                           placeholder="e.g. 24x12, 28x16, 36x18">
                </td>
            </tr>
            <tr>
                <th><label for="mnb_colors">Available Colors</label></th>
                <td>
                    <input type="text" id="mnb_colors" name="_mnb_colors"
                           value="<?php echo esc_attr( $colors ); ?>" class="regular-text"
                           placeholder="e.g. BLK, GRY, PNK">
                </td>
            </tr>
            <tr>
                <th><label for="mnb_styles">Styles</label></th>
                <td>
                    <input type="text" id="mnb_styles" name="_mnb_styles"
                           value="<?php echo esc_attr( $styles ); ?>" class="regular-text"
                           placeholder="e.g. Flat Marker, Upright">
                </td>
            </tr>
            <tr>
                <th><label for="mnb_wc_product_id">Linked WC Product ID</label></th>
                <td>
                    <input type="number" id="mnb_wc_product_id" name="_mnb_wc_product_id"
                           value="<?php echo esc_attr( $wc_product_id ); ?>" style="width:120px">
                    <?php if ( $wc_product_id ) : ?>
                        <a href="<?php echo esc_url( get_edit_post_link( $wc_product_id ) ); ?>" target="_blank" class="button button-small">View Product</a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="mnb_notes">Internal Notes</label></th>
                <td>
                    <textarea id="mnb_notes" name="_mnb_notes" rows="3" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_monument_meta( $post_id ) {
        if (
            ! isset( $_POST['mnb_monument_meta_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mnb_monument_meta_nonce'] ) ), 'mnb_monument_meta' ) ||
            ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
            ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        $fields = [
            '_mnb_monument_type' => 'sanitize_text_field',
            '_mnb_design_code'   => 'sanitize_text_field',
            '_mnb_sku_pattern'   => 'sanitize_text_field',
            '_mnb_sizes'         => 'sanitize_text_field',
            '_mnb_colors'        => 'sanitize_text_field',
            '_mnb_styles'        => 'sanitize_text_field',
            '_mnb_wc_product_id' => 'absint',
            '_mnb_notes'         => 'sanitize_textarea_field',
        ];

        foreach ( $fields as $meta_key => $sanitizer ) {
            if ( isset( $_POST[ $meta_key ] ) ) {
                update_post_meta( $post_id, $meta_key, $sanitizer( wp_unslash( $_POST[ $meta_key ] ) ) );
            }
        }
    }

    // ── Form Handlers ─────────────────────────────────────────────────────────


    public static function remove_font() {
        $key = sanitize_key( $_GET['key'] ?? '' );
        if ( ! $key ) wp_die( 'Invalid key' );
        if ( ! wp_verify_nonce( $_GET['nonce'] ?? '', 'mnb_remove_font_' . $key ) ) wp_die( 'Security check failed' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        // Delete the file from disk
        $upload_dir = wp_upload_dir();
        $font_dir   = trailingslashit( $upload_dir['basedir'] ) . 'mnb-fonts/';
        $filename   = get_option( 'mnb_font_filename_' . $key );
        if ( $filename && file_exists( $font_dir . $filename ) ) {
            unlink( $font_dir . $filename );
        }

        delete_option( 'mnb_font_url_'      . $key );
        delete_option( 'mnb_font_filename_' . $key );

        wp_redirect( admin_url( 'admin.php?page=mnb-settings&font_removed=1' ) );
        exit;
    }

    public static function save_settings() {
        check_admin_referer( 'mnb_save_settings', 'mnb_settings_nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        if ( isset( $_POST['mnb_fonts'] ) && is_array( $_POST['mnb_fonts'] ) ) {
            $new_fonts = [];
            foreach ( $_POST['mnb_fonts'] as $key => $label ) {
                $k = sanitize_key( $key );
                $v = sanitize_text_field( wp_unslash( $label ) );
                if ( $k && $v ) $new_fonts[ $k ] = $v;
            }

            // Delete fonts flagged for removal
            if ( ! empty( $_POST['mnb_fonts_delete'] ) && is_array( $_POST['mnb_fonts_delete'] ) ) {
                foreach ( $_POST['mnb_fonts_delete'] as $del_key ) {
                    $del_key = sanitize_key( $del_key );
                    if ( ! $del_key ) continue;
                    unset( $new_fonts[ $del_key ] );
                    delete_option( 'mnb_font_image_'    . $del_key );
                    delete_option( 'mnb_font_file_'     . $del_key );
                    delete_option( 'mnb_font_url_'      . $del_key );
                    delete_option( 'mnb_font_filename_' . $del_key );
                }
            }

            // Add new font
            $new_key  = sanitize_key( wp_unslash( $_POST['mnb_new_font_key']  ?? '' ) );
            $new_name = sanitize_text_field( wp_unslash( $_POST['mnb_new_font_name'] ?? '' ) );
            if ( $new_key && $new_name && ! isset( $new_fonts[ $new_key ] ) ) {
                $new_fonts[ $new_key ] = $new_name;
            }

            if ( ! empty( $new_fonts ) ) update_option( 'mnb_fonts', $new_fonts );
        }

        // Save epitaph presets
        if ( isset( $_POST['mnb_epitaph_presets'] ) ) {
            $raw     = sanitize_textarea_field( wp_unslash( $_POST['mnb_epitaph_presets'] ) );
            $presets = array_filter( array_map( 'trim', explode( "\n", $raw ) ) );
            update_option( 'mnb_epitaph_presets', array_values( $presets ) );
        }

        // Handle direct font file uploads
        if ( ! empty( $_FILES['mnb_font_upload'] ) && is_array( $_FILES['mnb_font_upload']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $allowed_types = [ 'ttf', 'woff', 'woff2' ];
            $upload_dir    = wp_upload_dir();
            $font_dir      = trailingslashit( $upload_dir['basedir'] ) . 'mnb-fonts/';
            $font_url_base = trailingslashit( $upload_dir['baseurl'] ) . 'mnb-fonts/';

            // Create font directory if it doesn't exist
            if ( ! file_exists( $font_dir ) ) {
                wp_mkdir_p( $font_dir );
                // Protect with .htaccess
                file_put_contents( $font_dir . '.htaccess', 'Options -Indexes' );
            }

            foreach ( $_FILES['mnb_font_upload']['name'] as $key => $filename ) {
                $key = sanitize_key( $key );
                if ( empty( $filename ) || $_FILES['mnb_font_upload']['error'][ $key ] !== UPLOAD_ERR_OK ) continue;

                $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
                if ( ! in_array( $ext, $allowed_types, true ) ) {
                    // phpcs:ignore -- just skip bad types
                    continue;
                }

                $size = $_FILES['mnb_font_upload']['size'][ $key ];
                if ( $size > 2 * 1024 * 1024 ) continue; // max 2MB

                $safe_name  = sanitize_file_name( $filename );
                $dest_path  = $font_dir . $safe_name;
                $dest_url   = $font_url_base . $safe_name;
                $tmp_path   = $_FILES['mnb_font_upload']['tmp_name'][ $key ];

                if ( move_uploaded_file( $tmp_path, $dest_path ) ) {
                    update_option( 'mnb_font_url_'      . $key, $dest_url );
                    update_option( 'mnb_font_filename_' . $key, $safe_name );
                }
            }
        }

        if ( isset( $_POST['mnb_font_image'] ) && is_array( $_POST['mnb_font_image'] ) ) {
            foreach ( $_POST['mnb_font_image'] as $key => $img_id ) {
                update_option( 'mnb_font_image_' . sanitize_key( $key ), absint( $img_id ) );
            }
        }

        wp_redirect( admin_url( 'admin.php?page=mnb-settings&saved=1' ) );
        exit;
    }

    public static function save_text_lines() {
        check_admin_referer( 'mnb_save_lines', 'mnb_lines_nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        if ( isset( $_POST['mnb_lines'] ) && is_array( $_POST['mnb_lines'] ) ) {
            $lines = [];
            foreach ( $_POST['mnb_lines'] as $line ) {
                $lines[] = [
                    'key'      => sanitize_key( $line['key'] ),
                    'label'    => sanitize_text_field( wp_unslash( $line['label'] ) ),
                    'required' => ! empty( $line['required'] ),
                    'max'      => absint( $line['max'] ?? 80 ),
                    'textarea' => ! empty( $line['textarea'] ),
                ];
            }
            update_option( 'mnb_text_lines', $lines );
        }

        wp_redirect( admin_url( 'admin.php?page=mnb-text-lines&saved=1' ) );
        exit;
    }

    // ── Admin Notices ─────────────────────────────────────────────────────────

    public static function admin_notices() {
        $page = $_GET['page'] ?? '';
        if ( isset( $_GET['saved'] ) && in_array( $page, [ 'mnb-settings', 'mnb-text-lines', 'mnb-epitaphs' ], true ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>MNB settings saved successfully.</p></div>';
        }
    }
}
