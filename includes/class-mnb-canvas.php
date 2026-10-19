<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Canvas
 *
 * Manages monument layout type (single vs double),
 * field definitions per layout, and the canvas JSON
 * data that stores text positions/sizes from Fabric.js.
 */
class MNB_Canvas {

    public static function init() {
        // Nothing to hook here — fields & assets are handled by
        // MNB_Product_Fields and MNB_Shortcode, which call
        // static methods on this class.
    }

    // ── Layout definitions ────────────────────────────────────────────────────

    /**
     * Return field groups for single monument layout.
     */
    public static function single_fields() {
        return [
            [
                'key'      => 'full_name',
                'label'    => 'Full Name',
                'required' => true,
                'max'      => 40,
                'group'    => 'single',
                'default_y_pct' => 30,   // % from top of canvas
                'default_x_pct' => 50,   // % from left
                'align'    => 'center',
            ],
            [
                'key'      => 'date_birth',
                'label'    => 'Date of Birth',
                'required' => false,
                'max'      => 30,
                'group'    => 'single',
                'default_y_pct' => 50,
                'default_x_pct' => 50,
                'align'    => 'center',
            ],
            [
                'key'      => 'date_passing',
                'label'    => 'Date of Passing',
                'required' => false,
                'max'      => 30,
                'group'    => 'single',
                'default_y_pct' => 60,
                'default_x_pct' => 50,
                'align'    => 'center',
            ],
            [
                'key'      => 'epitaph',
                'label'    => 'Epitaph / Message',
                'required' => false,
                'max'      => 80,
                'textarea' => true,
                'group'    => 'single',
                'default_y_pct' => 75,
                'default_x_pct' => 50,
                'align'    => 'center',
            ],
        ];
    }

    /**
     * Return field groups for double monument layout.
     * Shared fields span full width; person fields are left/right.
     */
    public static function double_fields() {
        return [
            // ── Shared ────────────────────────────────────────────────
            [
                'key'      => 'last_name',
                'label'    => 'Last Name (Shared)',
                'required' => true,
                'max'      => 40,
                'group'    => 'shared',
                'default_y_pct' => 18,
                'default_x_pct' => 50,
                'align'    => 'center',
            ],
            // ── Person 1 (Left) ───────────────────────────────────────
            [
                'key'      => 'p1_first_name',
                'label'    => 'Person 1 – First Name',
                'required' => true,
                'max'      => 30,
                'group'    => 'person1',
                'side'     => 'left',
                'default_y_pct' => 35,
                'default_x_pct' => 25,
                'align'    => 'center',
            ],
            [
                'key'      => 'p1_date_birth',
                'label'    => 'Person 1 – Date of Birth',
                'required' => false,
                'max'      => 30,
                'group'    => 'person1',
                'side'     => 'left',
                'default_y_pct' => 50,
                'default_x_pct' => 25,
                'align'    => 'center',
            ],
            [
                'key'      => 'p1_date_passing',
                'label'    => 'Person 1 – Date of Passing',
                'required' => false,
                'max'      => 30,
                'group'    => 'person1',
                'side'     => 'left',
                'default_y_pct' => 60,
                'default_x_pct' => 25,
                'align'    => 'center',
            ],
            // ── Person 2 (Right) ──────────────────────────────────────
            [
                'key'      => 'p2_first_name',
                'label'    => 'Person 2 – First Name',
                'required' => true,
                'max'      => 30,
                'group'    => 'person2',
                'side'     => 'right',
                'default_y_pct' => 35,
                'default_x_pct' => 75,
                'align'    => 'center',
            ],
            [
                'key'      => 'p2_date_birth',
                'label'    => 'Person 2 – Date of Birth',
                'required' => false,
                'max'      => 30,
                'group'    => 'person2',
                'side'     => 'right',
                'default_y_pct' => 50,
                'default_x_pct' => 75,
                'align'    => 'center',
            ],
            [
                'key'      => 'p2_date_passing',
                'label'    => 'Person 2 – Date of Passing',
                'required' => false,
                'max'      => 30,
                'group'    => 'person2',
                'side'     => 'right',
                'default_y_pct' => 60,
                'default_x_pct' => 75,
                'align'    => 'center',
            ],
            // ── Shared epitaph ────────────────────────────────────────
            [
                'key'      => 'epitaph',
                'label'    => 'Epitaph / Message (Shared)',
                'required' => false,
                'max'      => 80,
                'textarea' => true,
                'group'    => 'shared',
                'default_y_pct' => 78,
                'default_x_pct' => 50,
                'align'    => 'center',
            ],
        ];
    }

    /**
     * Get fields for a given layout type.
     */
    public static function get_fields( $layout = 'single' ) {
        return $layout === 'double' ? self::double_fields() : self::single_fields();
    }

    /**
     * Get the monument layout type for a WC product.
     */
    public static function get_layout( $product_id ) {
        return get_post_meta( $product_id, '_mnb_layout', true ) ?: 'single';
    }

    /**
     * Get the product image URL to use as canvas background.
     */
    public static function get_canvas_image( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) return '';

        $img_id = $product->get_image_id();
        if ( ! $img_id ) return '';

        $src = wp_get_attachment_image_src( $img_id, 'large' );
        return $src ? $src[0] : '';
    }
}
