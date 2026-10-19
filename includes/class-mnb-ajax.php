<?php
defined( 'ABSPATH' ) || exit;

class MNB_Ajax {

    public static function init() {
        add_action( 'wp_ajax_mnb_get_font_urls',        [ __CLASS__, 'get_font_urls' ] );
        add_action( 'wp_ajax_nopriv_mnb_get_font_urls', [ __CLASS__, 'get_font_urls' ] );
        add_action( 'wp_ajax_mnb_get_monument',         [ __CLASS__, 'get_monument' ] );
        add_action( 'wp_ajax_nopriv_mnb_get_monument',  [ __CLASS__, 'get_monument' ] );
        add_action( 'wp_ajax_mnb_save_proof',           [ __CLASS__, 'save_proof_proxy' ] );
        add_action( 'wp_ajax_nopriv_mnb_save_proof',    [ __CLASS__, 'save_proof_proxy' ] );
    }

    public static function get_monument() {
        check_ajax_referer( 'mnb_nonce', 'nonce' );

        $monument_id = absint( $_POST['monument_id'] ?? 0 );
        if ( ! $monument_id ) {
            wp_send_json_error( 'Invalid monument ID' );
        }

        $monument = get_post( $monument_id );
        if ( ! $monument || $monument->post_type !== 'mnb_monument' ) {
            wp_send_json_error( 'Monument not found' );
        }

        wp_send_json_success( [
            'id'          => $monument->ID,
            'title'       => $monument->post_title,
            'sku_pattern' => get_post_meta( $monument->ID, '_mnb_sku_pattern', true ),
            'design_code' => get_post_meta( $monument->ID, '_mnb_design_code', true ),
            'sizes'       => get_post_meta( $monument->ID, '_mnb_sizes', true ),
            'colors'      => get_post_meta( $monument->ID, '_mnb_colors', true ),
            'type'        => get_post_meta( $monument->ID, '_mnb_monument_type', true ),
            'thumbnail'   => get_the_post_thumbnail_url( $monument->ID, 'medium' ),
        ] );
    }

    /**
     * Return font file URLs for the Fabric.js FontFace loader.
     */
    public static function get_font_urls() {
        check_ajax_referer( 'mnb_nonce', 'nonce' );

        $fonts     = MNB_Product_Fields::get_fonts();
        $font_data = [];

        foreach ( $fonts as $key => $label ) {
            $att_id = get_option( 'mnb_font_file_' . $key );
            if ( $att_id ) {
                $url = wp_get_attachment_url( $att_id );
                if ( $url ) {
                    $font_data[ $key ] = [
                        'label' => $label,
                        'url'   => $url,
                    ];
                }
            }
        }

        wp_send_json_success( $font_data );
    }

    /**
     * Proxy to MNB_Proof::ajax_save_proof() — keeps Ajax routing in one place.
     */
    public static function save_proof_proxy() {
        MNB_Proof::ajax_save_proof();
    }
}
