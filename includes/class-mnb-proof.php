<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Proof
 *
 * Handles:
 *  1. Receiving a base64 canvas snapshot via Ajax and saving it as
 *     a WP media attachment linked to the order.
 *  2. Displaying the proof thumbnail in the admin order view.
 *  3. Including a proof link in the customer order confirmation email.
 */
class MNB_Proof {

    public static function init() {
        // Ajax endpoint — called from JS after canvas renders
        add_action( 'wp_ajax_mnb_save_proof',        [ __CLASS__, 'ajax_save_proof' ] );
        add_action( 'wp_ajax_nopriv_mnb_save_proof', [ __CLASS__, 'ajax_save_proof' ] );

        // Show proof in admin order
        add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'display_proof_in_admin' ] );

        // Attach proof link to customer order email
        add_action( 'woocommerce_email_order_details', [ __CLASS__, 'email_proof_link' ], 20, 4 );

        // Add proof column to orders list
        add_filter( 'manage_woocommerce_page_wc-orders_columns',  [ __CLASS__, 'add_proof_column' ] );
        add_filter( 'manage_edit-shop_order_columns',             [ __CLASS__, 'add_proof_column' ] );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ __CLASS__, 'render_proof_column' ], 10, 2 );
        add_action( 'manage_shop_order_posts_custom_column',           [ __CLASS__, 'render_proof_column' ], 10, 2 );
    }

    // ── Ajax: receive base64 PNG from JS, save as WP media ────────────────────

    public static function ajax_save_proof() {
        check_ajax_referer( 'mnb_nonce', 'nonce' );

        $order_id  = absint( $_POST['order_id']   ?? 0 );
        $image_b64 = sanitize_text_field( wp_unslash( $_POST['image_data'] ?? '' ) );

        if ( ! $order_id || ! $image_b64 ) {
            wp_send_json_error( 'Missing data' );
        }

        // Strip data URI prefix
        $image_b64 = preg_replace( '/^data:image\/\w+;base64,/', '', $image_b64 );
        $image_data = base64_decode( $image_b64 );

        if ( ! $image_data ) {
            wp_send_json_error( 'Invalid image data' );
        }

        // Save to uploads
        $upload_dir = wp_upload_dir();
        $folder     = trailingslashit( $upload_dir['basedir'] ) . 'mnb-proofs';
        wp_mkdir_p( $folder );

        $filename = 'proof-order-' . $order_id . '-' . time() . '.png';
        $filepath = $folder . '/' . $filename;
        $fileurl  = trailingslashit( $upload_dir['baseurl'] ) . 'mnb-proofs/' . $filename;

        file_put_contents( $filepath, $image_data );

        // Register as WP media attachment
        $attachment_id = wp_insert_attachment( [
            'post_mime_type' => 'image/png',
            'post_title'     => 'Monument Proof — Order #' . $order_id,
            'post_status'    => 'inherit',
            'post_parent'    => $order_id,
        ], $filepath );

        if ( ! is_wp_error( $attachment_id ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $filepath ) );
        }

        // Store proof URL and attachment ID on the order
        update_post_meta( $order_id, '_mnb_proof_url',  $fileurl );
        update_post_meta( $order_id, '_mnb_proof_att',  $attachment_id );
        update_post_meta( $order_id, '_mnb_proof_file', $filepath );

        wp_send_json_success( [ 'proof_url' => $fileurl ] );
    }

    // ── Admin order view — proof thumbnail ────────────────────────────────────

    public static function display_proof_in_admin( $order ) {
        $proof_url = get_post_meta( $order->get_id(), '_mnb_proof_url', true );
        if ( ! $proof_url ) return;

        echo '<div class="mnb-proof-panel" style="margin-top:20px;padding:12px;background:#f9f6ee;border:1px solid #c8a84b;border-radius:6px;">';
        echo '<h4 style="margin:0 0 10px;color:#5d4037;font-size:14px;">🖼️ Monument Engraving Proof</h4>';
        printf(
            '<a href="%s" target="_blank"><img src="%s" style="max-width:100%%;border:1px solid #ddd;border-radius:4px;display:block;" alt="Monument proof"></a>',
            esc_url( $proof_url ),
            esc_url( $proof_url )
        );
        printf(
            '<p style="margin:8px 0 0;font-size:12px;"><a href="%s" target="_blank" download>⬇ Download Proof Image</a></p>',
            esc_url( $proof_url )
        );
        echo '</div>';
    }

    // ── Customer email — proof link ────────────────────────────────────────────

    public static function email_proof_link( $order, $sent_to_admin, $plain_text, $email ) {
        // Only on customer order confirmation
        if ( $sent_to_admin ) return;
        if ( ! in_array( $email->id, [ 'customer_processing_order', 'customer_completed_order' ], true ) ) return;

        $proof_url = get_post_meta( $order->get_id(), '_mnb_proof_url', true );
        if ( ! $proof_url ) return;

        echo '<div style="margin:20px 0;padding:14px;background:#f9f6ee;border-left:4px solid #c8a84b;">';
        echo '<h3 style="margin:0 0 8px;color:#2f2f2f;">Your Monument Proof</h3>';
        echo '<p style="margin:0 0 10px;font-size:14px;color:#555;">Below is a preview of how your monument engraving will be positioned. Our team will contact you if any adjustments are needed before production begins.</p>';
        printf(
            '<img src="%s" style="max-width:100%%;border:1px solid #ddd;display:block;margin-bottom:10px;" alt="Your monument proof">',
            esc_url( $proof_url )
        );
        printf(
            '<a href="%s" style="font-size:13px;color:#c8a84b;" target="_blank">View full size proof →</a>',
            esc_url( $proof_url )
        );
        echo '</div>';
    }

    // ── Orders list — proof column ─────────────────────────────────────────────

    public static function add_proof_column( $columns ) {
        $new = [];
        foreach ( $columns as $key => $val ) {
            $new[ $key ] = $val;
            if ( $key === 'order_status' ) {
                $new['mnb_proof'] = 'Proof';
            }
        }
        return $new;
    }

    public static function render_proof_column( $column, $order_id ) {
        if ( $column !== 'mnb_proof' ) return;

        $proof_url = get_post_meta( $order_id, '_mnb_proof_url', true );
        if ( $proof_url ) {
            printf(
                '<a href="%s" target="_blank" title="View proof"><span style="font-size:18px;">🖼️</span></a>',
                esc_url( $proof_url )
            );
        } else {
            echo '<span style="color:#ccc;font-size:12px;">—</span>';
        }
    }
}
