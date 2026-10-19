<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Cart v19
 *
 * Saves monument engraving data to WooCommerce cart items.
 * Uses MNB_Canvas::get_fields() as the single source of truth for field keys,
 * matching exactly what the shortcode renders on the product page.
 */
class MNB_Cart {

    public static function init() {
        add_filter( 'woocommerce_add_cart_item_data',      [ __CLASS__, 'add_cart_item_data' ],    10, 2 );
        add_filter( 'woocommerce_get_item_data',           [ __CLASS__, 'display_cart_item_data' ], 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'recalculate_item_price' ], 10, 1 );
        add_filter( 'woocommerce_cart_item_name',          [ __CLASS__, 'cart_item_name_suffix' ],  10, 3 );
    }

    public static function add_cart_item_data( $cart_item_data, $product_id ) {
        if ( ! MNB_Product_Fields::product_is_monument( $product_id ) ) {
            return $cart_item_data;
        }

        $layout    = MNB_Canvas::get_layout( $product_id );
        $fields    = MNB_Canvas::get_fields( $layout );
        $fonts     = MNB_Product_Fields::get_fonts();
        $engraving = [];

        // phpcs:disable WordPress.Security.NonceVerification
        foreach ( $fields as $field ) {
            $key  = $field['key'];
            $text = isset( $_POST[ 'mnb_' . $key ] )
                ? sanitize_textarea_field( wp_unslash( $_POST[ 'mnb_' . $key ] ) )
                : '';

            // Epitaph comes from the hidden final field
            if ( $key === 'epitaph' ) {
                $text = isset( $_POST['mnb_epitaph'] )
                    ? sanitize_textarea_field( wp_unslash( $_POST['mnb_epitaph'] ) )
                    : $text;
            }

            if ( '' === $text ) continue;

            $font_key   = isset( $_POST[ 'mnb_font_' . $key ] )
                ? sanitize_text_field( wp_unslash( $_POST[ 'mnb_font_' . $key ] ) )
                : array_key_first( $fonts );
            $font_label = $fonts[ $font_key ] ?? reset( $fonts );

            $engraving[] = [
                'key'        => $key,
                'label'      => $field['label'],
                'text'       => $text,
                'font_key'   => $font_key,
                'font_label' => $font_label,
            ];
        }
        // phpcs:enable

        if ( ! empty( $engraving ) ) {
            $cart_item_data['mnb_engraving']  = $engraving;
            $cart_item_data['mnb_unique_key'] = md5( serialize( $engraving ) . time() );
        }

        // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! empty( $_POST['mnb_canvas_json'] ) ) {
            $cart_item_data['mnb_canvas_json'] = sanitize_textarea_field(
                wp_unslash( $_POST['mnb_canvas_json'] ) // phpcs:ignore
            );
        }

        // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! empty( $_POST['mnb_layout'] ) ) {
            $cart_item_data['mnb_layout'] = sanitize_text_field(
                wp_unslash( $_POST['mnb_layout'] ) // phpcs:ignore
            );
        }

        // Custom uploaded image (base64, PNG only)
        // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! empty( $_POST['mnb_custom_image_data'] ) ) {
            // Validate it looks like a PNG data URI before storing
            $img_data = $_POST['mnb_custom_image_data']; // phpcs:ignore
            if ( strpos( $img_data, 'data:image/png;base64,' ) === 0 ) {
                $cart_item_data['mnb_custom_image_data'] = sanitize_text_field( wp_unslash( $img_data ) );
                $cart_item_data['mnb_custom_image_name'] = sanitize_file_name(
                    wp_unslash( $_POST['mnb_custom_image_name'] ?? 'custom.png' ) // phpcs:ignore
                );
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification
        if ( ! empty( $_POST['mnb_design_notes'] ) ) {
            $cart_item_data['mnb_design_notes'] = sanitize_textarea_field(
                wp_unslash( $_POST['mnb_design_notes'] ) // phpcs:ignore
            );
        }

        return $cart_item_data;
    }

    public static function display_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['mnb_engraving'] ) ) return $item_data;

        foreach ( $cart_item['mnb_engraving'] as $line ) {
            $item_data[] = [
                'key'   => esc_html( $line['label'] ),
                'value' => esc_html( $line['text'] ) . ' <em>(' . esc_html( $line['font_label'] ) . ')</em>',
            ];
        }

        if ( ! empty( $cart_item['mnb_design_notes'] ) ) {
            $item_data[] = [
                'key'   => 'Design Notes',
                'value' => esc_html( $cart_item['mnb_design_notes'] ),
            ];
        }

        return $item_data;
    }

    public static function recalculate_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    }

    public static function cart_item_name_suffix( $name, $cart_item, $cart_item_key ) {
        if ( ! empty( $cart_item['mnb_engraving'] ) ) {
            $name .= ' <span class="mnb-cart-badge">Custom Engraving</span>';
        }
        return $name;
    }
}
