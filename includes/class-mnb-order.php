<?php
defined( 'ABSPATH' ) || exit;

/**
 * Saves monument engraving data to WooCommerce orders
 * and displays it throughout the order lifecycle.
 */
class MNB_Order {

    public static function init() {
        add_action( 'woocommerce_checkout_create_order_line_item',      [ __CLASS__, 'save_to_order_item' ],       10, 4 );
        add_action( 'woocommerce_after_order_itemmeta',                 [ __CLASS__, 'display_in_admin_order' ],   10, 3 );
        add_filter( 'woocommerce_order_item_get_formatted_meta_data',   [ __CLASS__, 'format_order_meta' ],        10, 2 );
        add_action( 'woocommerce_admin_order_data_after_order_details', [ __CLASS__, 'admin_order_engraving_panel' ] );
    }

    /**
     * Copy all engraving data from cart item to order line item meta.
     * Handles standard fields (v1/v2) and canvas JSON (v3+).
     */
    public static function save_to_order_item( $item, $cart_item_key, $values, $order ) {

        // ── Standard text/font fields ─────────────────────────────────────────
        if ( ! empty( $values['mnb_engraving'] ) ) {
            foreach ( $values['mnb_engraving'] as $line ) {
                $item->add_meta_data(
                    $line['label'],
                    sprintf( '%s (Font: %s)', $line['text'], $line['font_label'] )
                );
            }
            $item->add_meta_data( '_mnb_engraving_raw', wp_json_encode( $values['mnb_engraving'] ), true );
        }

        // ── Design notes (v6) ────────────────────────────────────────────────────
        // phpcs:ignore WordPress.Security.NonceVerification
        $design_notes = sanitize_textarea_field( wp_unslash( $_POST['mnb_design_notes'] ?? '' ) );
        if ( $design_notes ) {
            $item->add_meta_data( 'Design Notes', $design_notes );
        }

        // ── Design notes (v6) ────────────────────────────────────────────────
        if ( ! empty( $values['mnb_design_notes'] ) ) {
            $item->add_meta_data( 'Design Notes', sanitize_textarea_field( $values['mnb_design_notes'] ) );
        }

        // ── Canvas JSON (v3+) ─────────────────────────────────────────────────
        if ( ! empty( $values['mnb_canvas_json'] ) ) {
            $item->add_meta_data( '_mnb_canvas_json', $values['mnb_canvas_json'], true );
            $item->add_meta_data( '_mnb_layout',      $values['mnb_layout'] ?? 'single', true );

            // Parse text layers and store position notes for the engraver
            $canvas = json_decode( $values['mnb_canvas_json'], true );
            if ( ! empty( $canvas['textLayers'] ) ) {
                foreach ( $canvas['textLayers'] as $layer ) {
                    $pos_note = sprintf(
                        '%s | Font: %s | Position: %.1f%% left, %.1f%% top | Scale: %.2f',
                        $layer['text'],
                        $layer['fontLabel'] ?? '',
                        $layer['left_pct']  ?? 0,
                        $layer['top_pct']   ?? 0,
                        $layer['scaleX']    ?? 1
                    );
                    $item->add_meta_data( sprintf( '📍 %s', $layer['key'] ), $pos_note );
                }
            }
        }
    }

    /**
     * Render the engraving table in the WP Admin order item rows.
     */
    public static function display_in_admin_order( $item_id, $item, $product ) {
        $raw = $item->get_meta( '_mnb_engraving_raw' );
        if ( ! $raw ) return;

        $lines = json_decode( $raw, true );
        if ( empty( $lines ) ) return;

        echo '<div class="mnb-admin-engraving">';
        echo '<strong style="display:block;margin:8px 0 4px;color:#2271b1;">🪨 Monument Engraving Details</strong>';
        echo '<table style="width:100%;border-collapse:collapse;font-size:13px;">';
        echo '<thead><tr>
                <th style="text-align:left;padding:4px 8px;background:#f0f6ff;border:1px solid #ddd;">Field</th>
                <th style="text-align:left;padding:4px 8px;background:#f0f6ff;border:1px solid #ddd;">Text</th>
                <th style="text-align:left;padding:4px 8px;background:#f0f6ff;border:1px solid #ddd;">Font</th>
              </tr></thead><tbody>';

        foreach ( $lines as $line ) {
            printf(
                '<tr>
                    <td style="padding:4px 8px;border:1px solid #ddd;font-weight:600;">%s</td>
                    <td style="padding:4px 8px;border:1px solid #ddd;">%s</td>
                    <td style="padding:4px 8px;border:1px solid #ddd;font-style:italic;">%s</td>
                 </tr>',
                esc_html( $line['label'] ),
                esc_html( $line['text'] ),
                esc_html( $line['font_label'] )
            );
        }

        echo '</tbody></table></div>';
    }

    /**
     * Hide internal meta keys from customer-facing views.
     */
    public static function format_order_meta( $formatted_meta, $item ) {
        foreach ( $formatted_meta as $key => $meta ) {
            if ( ! empty( $meta->key ) && str_starts_with( (string) $meta->key, '_mnb_' ) ) {
                unset( $formatted_meta[ $key ] );
            }
        }
        return $formatted_meta;
    }

    /**
     * Gold banner at top of admin order reminding staff to review engraving.
     */
    public static function admin_order_engraving_panel( $order ) {
        $has_engraving = false;
        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( '_mnb_engraving_raw' ) || $item->get_meta( '_mnb_canvas_json' ) ) {
                $has_engraving = true;
                break;
            }
        }

        if ( ! $has_engraving ) return;

        echo '<div style="background:#fff8e1;border-left:4px solid #c8a84b;padding:12px 16px;margin-top:16px;border-radius:0 4px 4px 0;">';
        echo '<h4 style="margin:0 0 8px;color:#5d4037;">🪨 Monument Engraving Order — Production Notes</h4>';
        echo '<p style="margin:0;font-size:13px;color:#666;">This order contains custom engraved monuments. Review engraving details in each line item above before moving to <strong>In Production</strong> status.</p>';
        echo '</div>';
    }
}
