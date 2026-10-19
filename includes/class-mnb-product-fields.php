<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Product_Fields v19
 *
 * Handles cart validation and admin meta box.
 * Field keys match MNB_Canvas::get_fields() exactly.
 */
class MNB_Product_Fields {

    public static function init() {
        add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'validate_fields' ], 10, 3 );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_box' ] );
        add_action( 'save_post',      [ __CLASS__, 'save_meta_box' ] );
    }

    // ── Cart validation ───────────────────────────────────────────────────────

    public static function validate_fields( $passed, $product_id, $qty ) {
        if ( ! self::product_is_monument( $product_id ) ) return $passed;

        $layout = MNB_Canvas::get_layout( $product_id );
        $fields = MNB_Canvas::get_fields( $layout );

        // Build map of all submitted mnb_* values
        // phpcs:disable WordPress.Security.NonceVerification
        $posted = [];
        foreach ( $_POST as $k => $v ) {
            if ( strpos( $k, 'mnb_' ) === 0 ) {
                $posted[ $k ] = sanitize_text_field( wp_unslash( $v ) );
            }
        }

        // Also parse canvas JSON as fallback
        $canvas_layers = [];
        if ( ! empty( $_POST['mnb_canvas_json'] ) ) {
            $canvas = json_decode( wp_unslash( $_POST['mnb_canvas_json'] ), true );
            if ( ! empty( $canvas['textLayers'] ) ) {
                foreach ( $canvas['textLayers'] as $layer ) {
                    if ( ! empty( $layer['key'] ) && ! empty( $layer['text'] ) ) {
                        $canvas_layers[ $layer['key'] ] = $layer['text'];
                    }
                }
            }
        }
        // phpcs:enable

        foreach ( $fields as $field ) {
            if ( empty( $field['required'] ) ) continue;

            $key = $field['key'];
            $val = $posted[ 'mnb_' . $key ] ?? $canvas_layers[ $key ] ?? '';

            if ( '' === $val ) {
                wc_add_notice(
                    sprintf(
                        __( '"%s" is required for monument engraving.', 'monument-builder' ),
                        $field['label']
                    ),
                    'error'
                );
                $passed = false;
            }
        }

        return $passed;
    }

    // ── Asset enqueue ─────────────────────────────────────────────────────────

    public static function enqueue_assets() {
        if ( ! is_singular( 'product' ) ) return;
        $product_id = get_queried_object_id();
        if ( ! self::product_is_monument( $product_id ) ) return;

        wp_enqueue_style(  'mnb-frontend', MNB_PLUGIN_URL . 'assets/css/mnb-frontend.css', [], MNB_VERSION );
        wp_enqueue_script( 'fabricjs', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js', [], '5.3.1', true );
        wp_enqueue_script( 'mnb-canvas', MNB_PLUGIN_URL . 'assets/js/mnb-canvas.js', [ 'jquery', 'fabricjs' ], MNB_VERSION, true );

        $layout = MNB_Canvas::get_layout( $product_id );
        wp_localize_script( 'mnb-canvas', 'mnbData', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'mnb_nonce' ),
            'fonts'     => self::get_fonts(),
            'fields'    => MNB_Canvas::get_fields( $layout ),
            'layout'    => $layout,
            'productId' => $product_id,
            'canvasImg' => MNB_Canvas::get_canvas_image( $product_id ),
            'fontUrls'  => self::get_font_urls(),
            'fontMap'   => [
                'sunk_roman'         => 'Times New Roman, Times, serif',
                'vermarco_script'   => 'Georgia, serif',
                'spacerite_vermarco' => 'Georgia, serif',
            ],
        ] );
    }

    // ── WC Product admin meta box ─────────────────────────────────────────────

    public static function add_meta_box() {
        add_meta_box(
            'mnb_product_settings',
            '🪨 Monument Settings',
            [ __CLASS__, 'render_meta_box' ],
            'product',
            'side',
            'high'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'mnb_product_meta', 'mnb_product_nonce' );

        $is_monument = get_post_meta( $post->ID, '_mnb_is_monument',       true );
        $is_blank    = get_post_meta( $post->ID, '_mnb_is_blank_monument',  true );
        $layout      = get_post_meta( $post->ID, '_mnb_layout',             true ) ?: 'single';
        $monument_id = get_post_meta( $post->ID, '_mnb_monument_id',        true );
        $sku_code    = get_post_meta( $post->ID, '_mnb_sku_code',            true );

        $monuments = get_posts( [
            'post_type'      => 'mnb_monument',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        ?>
        <div class="mnb-meta-box">
            <p>
                <label>
                    <input type="checkbox" name="_mnb_is_monument" value="1" <?php checked( $is_monument, '1' ); ?>>
                    <strong>This is a monument product</strong>
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="_mnb_is_blank_monument" value="1" <?php checked( $is_blank, '1' ); ?>>
                    Blank / Text Only (no engraving)
                </label>
            </p>
            <hr style="margin:10px 0;border:none;border-top:1px solid #ddd;">
            <p>
                <label for="mnb_layout"><strong>Monument Layout</strong></label><br>
                <select name="_mnb_layout" id="mnb_layout" style="width:100%;margin-top:4px;">
                    <option value="single" <?php selected( $layout, 'single' ); ?>>&#9632; Single Monument</option>
                    <option value="double" <?php selected( $layout, 'double' ); ?>>&#9632;&#9632; Double Monument</option>
                </select>
            </p>
            <hr style="margin:10px 0;border:none;border-top:1px solid #ddd;">
            <p>
                <label for="mnb_monument_id"><strong>Linked Monument Design</strong></label><br>
                <select name="_mnb_monument_id" id="mnb_monument_id" style="width:100%;margin-top:4px;">
                    <option value="">— None —</option>
                    <?php foreach ( $monuments as $m ) : ?>
                        <option value="<?php echo esc_attr( $m->ID ); ?>" <?php selected( (string) $monument_id, (string) $m->ID ); ?>>
                            <?php echo esc_html( $m->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="mnb_sku_code"><strong>Design Code (SKU prefix)</strong></label><br>
                <input type="text" name="_mnb_sku_code" id="mnb_sku_code"
                       value="<?php echo esc_attr( (string) $sku_code ); ?>"
                       style="width:100%;margin-top:4px;" placeholder="e.g. PH">
            </p>
        </div>
        <?php
    }

    public static function save_meta_box( $post_id ) {
        if (
            empty( $_POST['mnb_product_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mnb_product_nonce'] ) ), 'mnb_product_meta' ) ||
            ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
            ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        update_post_meta( $post_id, '_mnb_is_monument',       isset( $_POST['_mnb_is_monument'] )       ? '1' : '0' );
        update_post_meta( $post_id, '_mnb_is_blank_monument', isset( $_POST['_mnb_is_blank_monument'] ) ? '1' : '0' );

        $layout = ( isset( $_POST['_mnb_layout'] ) && in_array( $_POST['_mnb_layout'], [ 'single', 'double' ], true ) )
            ? sanitize_text_field( wp_unslash( $_POST['_mnb_layout'] ) )
            : 'single';
        update_post_meta( $post_id, '_mnb_layout', $layout );
        update_post_meta( $post_id, '_mnb_monument_id', absint( $_POST['_mnb_monument_id'] ?? 0 ) );
        update_post_meta( $post_id, '_mnb_sku_code',    sanitize_text_field( wp_unslash( $_POST['_mnb_sku_code'] ?? '' ) ) );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function product_is_monument( $product_id ) {
        $product_id = absint( $product_id );
        if ( ! $product_id ) return false;
        return '1' === get_post_meta( $product_id, '_mnb_is_monument', true );
    }


    public static function get_font_urls() {
        $fonts = self::get_fonts();
        $urls  = [];
        foreach ( $fonts as $key => $label ) {
            // First try direct upload URL (new method)
            $url = get_option( 'mnb_font_url_' . $key, '' );
            // Fall back to attachment URL (old method)
            if ( ! $url ) {
                $att_id = get_option( 'mnb_font_file_' . $key );
                if ( $att_id ) $url = wp_get_attachment_url( $att_id );
            }
            if ( $url ) $urls[ $key ] = $url;
        }
        return $urls;
    }

    public static function get_text_lines() {
        return get_option( 'mnb_text_lines', [
            [ 'key' => 'line1_name',   'label' => 'Line 1 – Full Name',         'required' => true,  'max' => 40 ],
            [ 'key' => 'line2_name',   'label' => 'Line 2 – Name (2nd person)', 'required' => false, 'max' => 40 ],
            [ 'key' => 'date_birth',   'label' => 'Date of Birth',              'required' => false, 'max' => 30 ],
            [ 'key' => 'date_passing', 'label' => 'Date of Passing',            'required' => false, 'max' => 30 ],
            [ 'key' => 'epitaph',      'label' => 'Epitaph / Message',          'required' => false, 'max' => 80, 'textarea' => true ],
        ] );
    }

    public static function get_fonts() {
        return get_option( 'mnb_fonts', [
            'sunk_roman'         => 'Sunk Roman',
            'vermarco_script'   => 'Vermarco Script',
            'spacerite_vermarco' => 'Spacerite Vermarco',
        ] );
    }
}
