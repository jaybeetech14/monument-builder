<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Production_Sheet
 *
 * Generates a print-ready HTML production sheet for each order
 * containing monument engraving details.
 *
 * Accessible at: /wp-admin/admin.php?page=mnb-production-sheet&order_id=XXX
 * Also adds a "Print Engraving Sheet" button to the admin order view.
 */
class MNB_Production_Sheet {

    public static function init() {
        add_action( 'admin_menu',                                        [ __CLASS__, 'register_page' ] );
        add_action( 'woocommerce_order_actions',                         [ __CLASS__, 'add_order_action' ] );
        add_action( 'woocommerce_order_action_mnb_print_sheet',          [ __CLASS__, 'handle_order_action' ] );
        add_action( 'woocommerce_admin_order_data_after_order_details',  [ __CLASS__, 'add_print_button' ] );
        add_action( 'admin_init',                                        [ __CLASS__, 'maybe_render_sheet' ] );
    }

    // ── Register hidden admin page (no menu entry) ─────────────────────────────

    public static function register_page() {
        add_submenu_page(
            null,                          // no parent — hidden page
            'MNB Production Sheet',
            'Production Sheet',
            'manage_woocommerce',
            'mnb-production-sheet',
            [ __CLASS__, 'render_sheet_page' ]
        );
    }

    // ── Intercept early to output clean HTML for printing ─────────────────────

    public static function maybe_render_sheet() {
        if (
            ! is_admin() ||
            ( $_GET['page'] ?? '' ) !== 'mnb-production-sheet' ||
            empty( $_GET['order_id'] )
        ) {
            return;
        }

        $order_id = absint( $_GET['order_id'] );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized' );
        }

        self::output_printable_sheet( $order_id );
        exit;
    }

    // ── Button on admin order page ─────────────────────────────────────────────

    public static function add_print_button( $order ) {
        $has_engraving = false;
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( '_mnb_engraving_raw' ) || $item->get_meta( '_mnb_canvas_json' ) ) {
                $has_engraving = true;
                break;
            }
        }

        if ( ! $has_engraving ) return;

        $url = admin_url( 'admin.php?page=mnb-production-sheet&order_id=' . $order->get_id() );
        printf(
            '<p class="form-field"><a href="%s" target="_blank" class="button button-primary" style="background:#c8a84b;border-color:#b8943b;color:#fff;">
                🖨️ Print Engraving Sheet
             </a></p>',
            esc_url( $url )
        );
    }

    // ── WC order action (from order actions dropdown) ──────────────────────────

    public static function add_order_action( $actions ) {
        $actions['mnb_print_sheet'] = 'MNB: Open Engraving Sheet';
        return $actions;
    }

    public static function handle_order_action( $order ) {
        $url = admin_url( 'admin.php?page=mnb-production-sheet&order_id=' . $order->get_id() );
        wp_redirect( $url );
        exit;
    }

    // ── Render the hidden admin page (redirected above, but fallback) ─────────

    public static function render_sheet_page() {
        $order_id = absint( $_GET['order_id'] ?? 0 );
        if ( $order_id ) {
            self::output_printable_sheet( $order_id );
        }
    }

    // ── Main output ────────────────────────────────────────────────────────────

    private static function output_printable_sheet( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_die( 'Order not found.' );
        }

        $proof_url = get_post_meta( $order_id, '_mnb_proof_url', true );
        $date      = $order->get_date_created() ? $order->get_date_created()->date( 'F j, Y' ) : '';
        $customer  = $order->get_formatted_billing_full_name();
        $phone     = $order->get_billing_phone();
        $email     = $order->get_billing_email();
        $status    = wc_get_order_status_name( $order->get_status() );

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Engraving Production Sheet — Order #<?php echo esc_html( $order->get_order_number() ); ?></title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: Arial, sans-serif; font-size: 13px; color: #111; background: #fff; padding: 24px; }

                .sheet-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #2f2f2f; padding-bottom: 14px; margin-bottom: 20px; }
                .sheet-title  { font-size: 22px; font-weight: 700; color: #2f2f2f; }
                .sheet-sub    { font-size: 13px; color: #666; margin-top: 4px; }
                .shop-name    { font-size: 16px; font-weight: 700; text-align: right; color: #c8a84b; }
                .shop-meta    { font-size: 12px; color: #666; text-align: right; }

                .order-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 20px; background: #f9f9f9; padding: 12px; border-radius: 4px; border: 1px solid #ddd; }
                .meta-item label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #999; letter-spacing: 0.5px; margin-bottom: 3px; }
                .meta-item span  { font-size: 14px; font-weight: 600; }

                .section-title { font-size: 14px; font-weight: 700; background: #2f2f2f; color: #fff; padding: 6px 12px; border-radius: 4px 4px 0 0; margin-bottom: 0; }
                .section-body  { border: 1px solid #ddd; border-top: none; border-radius: 0 0 4px 4px; padding: 12px; margin-bottom: 20px; }

                .engraving-table { width: 100%; border-collapse: collapse; }
                .engraving-table th { text-align: left; padding: 6px 10px; background: #f5edd6; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #666; letter-spacing: 0.5px; border: 1px solid #ddd; }
                .engraving-table td { padding: 8px 10px; border: 1px solid #ddd; vertical-align: top; }
                .engraving-table tr:nth-child(even) td { background: #fafafa; }
                .text-val  { font-size: 16px; font-weight: 700; }
                .font-val  { font-size: 12px; color: #666; font-style: italic; }
                .pos-val   { font-size: 11px; color: #999; font-family: monospace; }

                .double-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                .col-title   { font-size: 13px; font-weight: 700; color: #c8a84b; padding-bottom: 6px; border-bottom: 2px solid #c8a84b; margin-bottom: 10px; }

                .proof-section { margin-bottom: 20px; }
                .proof-section img { max-width: 100%; border: 1px solid #ddd; border-radius: 4px; display: block; }

                .work-notes { border: 2px dashed #ddd; border-radius: 4px; padding: 12px; min-height: 80px; }
                .work-notes label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #999; display: block; margin-bottom: 6px; }

                .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #e8f0fe; color: #1a73e8; }

                .signature-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-top: 24px; padding-top: 16px; border-top: 1px solid #ddd; }
                .sig-field { border-bottom: 1px solid #aaa; padding-bottom: 4px; min-height: 36px; }
                .sig-label { font-size: 10px; color: #999; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

                .footer { margin-top: 24px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 11px; color: #999; display: flex; justify-content: space-between; }

                @media print {
                    body { padding: 12px; }
                    .no-print { display: none !important; }
                    @page { margin: 0.5in; size: letter; }
                }
            </style>
        </head>
        <body>

        <!-- Print button (screen only) -->
        <div class="no-print" style="margin-bottom:16px;">
            <button onclick="window.print()" style="background:#c8a84b;color:#fff;border:none;padding:10px 20px;border-radius:4px;font-size:14px;font-weight:700;cursor:pointer;">🖨️ Print This Sheet</button>
            <button onclick="window.close()" style="background:#eee;border:1px solid #ddd;padding:10px 16px;border-radius:4px;font-size:13px;cursor:pointer;margin-left:8px;">✕ Close</button>
        </div>

        <!-- Header -->
        <div class="sheet-header">
            <div>
                <div class="sheet-title">🪨 Monument Engraving Production Sheet</div>
                <div class="sheet-sub">Order #<?php echo esc_html( $order->get_order_number() ); ?> &nbsp;·&nbsp; <?php echo esc_html( $date ); ?> &nbsp;·&nbsp; <span class="status-badge"><?php echo esc_html( $status ); ?></span></div>
            </div>
            <div>
                <div class="shop-name"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
                <div class="shop-meta">Engraving Production Copy</div>
            </div>
        </div>

        <!-- Customer meta -->
        <div class="order-meta">
            <div class="meta-item"><label>Customer</label><span><?php echo esc_html( $customer ); ?></span></div>
            <div class="meta-item"><label>Phone</label><span><?php echo esc_html( $phone ?: '—' ); ?></span></div>
            <div class="meta-item"><label>Email</label><span><?php echo esc_html( $email ); ?></span></div>
        </div>

        <?php foreach ( $order->get_items() as $item ) :
            $raw_engraving = $item->get_meta( '_mnb_engraving_raw' );
            $canvas_json   = $item->get_meta( '_mnb_canvas_json' );
            $layout        = $item->get_meta( '_mnb_layout' ) ?: 'single';

            if ( ! $raw_engraving && ! $canvas_json ) continue;

            $engraving = $raw_engraving ? json_decode( $raw_engraving, true ) : [];
            $canvas    = $canvas_json   ? json_decode( $canvas_json, true )   : [];

            $product   = $item->get_product();
            $prod_name = $item->get_name();

            // Build variation label
            $variation_label = '';
            if ( $product && $product->is_type( 'variation' ) ) {
                $attrs = $product->get_variation_attributes();
                foreach ( $attrs as $key => $val ) {
                    $variation_label .= ucfirst( str_replace( 'attribute_pa_', '', $key ) ) . ': ' . ucfirst( $val ) . '  ';
                }
            }
        ?>

        <!-- Product section -->
        <div class="section-title">
            📦 <?php echo esc_html( $prod_name ); ?>
            <?php if ( $variation_label ) echo ' &nbsp;·&nbsp; <span style="font-weight:400;font-size:12px;">' . esc_html( $variation_label ) . '</span>'; ?>
            &nbsp;·&nbsp; <span style="font-weight:400;font-size:12px;">Layout: <?php echo esc_html( ucfirst( $layout ) ); ?> Monument</span>
        </div>
        <div class="section-body">

            <?php
            // Use canvas textLayers if available (v3+), fallback to raw engraving (v1/v2)
            $layers = ! empty( $canvas['textLayers'] ) ? $canvas['textLayers'] : [];
            if ( empty( $layers ) && ! empty( $engraving ) ) {
                foreach ( $engraving as $e ) {
                    $layers[] = [
                        'key'       => $e['key'],
                        'text'      => $e['text'],
                        'fontLabel' => $e['font_label'],
                        'left_pct'  => null,
                        'top_pct'   => null,
                        'scaleX'    => 1,
                    ];
                }
            }

            if ( $layout === 'double' ) :
                // Group layers by side
                $shared  = [];
                $person1 = [];
                $person2 = [];
                foreach ( $layers as $layer ) {
                    $k = $layer['key'];
                    if ( str_starts_with( $k, 'p1_' ) )     $person1[] = $layer;
                    elseif ( str_starts_with( $k, 'p2_' ) ) $person2[] = $layer;
                    else                                     $shared[]  = $layer;
                }
            ?>
            <!-- Shared fields -->
            <?php if ( ! empty( $shared ) ) : ?>
            <p style="font-weight:700;margin-bottom:6px;color:#666;">Shared</p>
            <?php self::render_engraving_table( $shared ); ?>
            <?php endif; ?>

            <div class="double-grid" style="margin-top:16px;">
                <div>
                    <div class="col-title">◀ Person 1 (Left Side)</div>
                    <?php self::render_engraving_table( $person1 ); ?>
                </div>
                <div>
                    <div class="col-title">Person 2 ▶ (Right Side)</div>
                    <?php self::render_engraving_table( $person2 ); ?>
                </div>
            </div>

            <?php else : ?>
            <?php self::render_engraving_table( $layers ); ?>
            <?php endif; ?>

        </div><!-- .section-body -->

        <?php endforeach; ?>

        <!-- Proof image -->
        <?php if ( $proof_url ) : ?>
        <div class="section-title">🖼️ Canvas Proof</div>
        <div class="section-body proof-section">
            <img src="<?php echo esc_url( $proof_url ); ?>" alt="Monument proof">
        </div>
        <?php endif; ?>

        <!-- Work notes -->
        <div class="section-title">✏️ Production Notes</div>
        <div class="section-body">
            <div class="work-notes">
                <label>Engraver Notes / Adjustments</label>
            </div>
        </div>

        <!-- Sign-off -->
        <div class="signature-row">
            <div>
                <div class="sig-field"></div>
                <div class="sig-label">Engraved By</div>
            </div>
            <div>
                <div class="sig-field"></div>
                <div class="sig-label">QC Checked By</div>
            </div>
            <div>
                <div class="sig-field"></div>
                <div class="sig-label">Date Completed</div>
            </div>
        </div>

        <div class="footer">
            <span>Order #<?php echo esc_html( $order->get_order_number() ); ?> &nbsp;·&nbsp; Generated <?php echo esc_html( current_time( 'F j, Y g:i a' ) ); ?></span>
            <span>Monument Builder v4.0</span>
        </div>

        <script>
            // Auto-open print dialog when accessed directly
            if ( window.location.search.indexOf('autoprint=1') !== -1 ) {
                window.addEventListener('load', function() { window.print(); });
            }
        </script>

        </body>
        </html>
        <?php
    }

    // ── Helper: render the engraving table rows ────────────────────────────────

    private static function render_engraving_table( $layers ) {
        if ( empty( $layers ) ) {
            echo '<p style="color:#999;font-style:italic;font-size:12px;">No engraving data.</p>';
            return;
        }
        ?>
        <table class="engraving-table">
            <thead>
                <tr>
                    <th style="width:24%">Field</th>
                    <th style="width:36%">Text</th>
                    <th style="width:20%">Font</th>
                    <th style="width:20%">Position</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $layers as $layer ) :
                $label = ucwords( str_replace( [ '_', 'p1 ', 'p2 ' ], [ ' ', '', '' ], $layer['key'] ) );
                $pos   = ( isset( $layer['left_pct'] ) && $layer['left_pct'] !== null )
                    ? sprintf( 'L: %.1f%%  T: %.1f%%  ×%.2f', $layer['left_pct'], $layer['top_pct'], $layer['scaleX'] )
                    : '—';
            ?>
                <tr>
                    <td><?php echo esc_html( $label ); ?></td>
                    <td><span class="text-val"><?php echo esc_html( $layer['text'] ); ?></span></td>
                    <td><span class="font-val"><?php echo esc_html( $layer['fontLabel'] ?? '—' ); ?></span></td>
                    <td><span class="pos-val"><?php echo esc_html( $pos ); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
