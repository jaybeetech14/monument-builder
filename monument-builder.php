<?php
/**
 * Plugin Name: Monument Builder
 * Description: Complete monument product configurator for WooCommerce. Manage engraved monument products, per-line text and font customization, and order workflow.
 * Version:     50.0.0
 * Author:      Jason Bearthes
 * Text Domain: monument-builder
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MNB_VERSION',     '50.0.0' );
define( 'MNB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'MNB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'MNB_PLUGIN_FILE', __FILE__ );

// ── Autoload includes ────────────────────────────────────────────────────────
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-post-type.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-product-fields.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-canvas.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-shortcode.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-cart.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-order.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-order-status.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-admin.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-ajax.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-epitaph.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-template.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-elementor-import.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-bulk-import.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-proof.php';
require_once MNB_PLUGIN_DIR . 'includes/class-mnb-production-sheet.php';

// ── Boot ─────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'mnb_init' );
function mnb_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Monument Builder</strong> requires WooCommerce to be active.</p></div>';
        } );
        return;
    }

    MNB_Post_Type::init();
    MNB_Product_Fields::init();
    MNB_Canvas::init();
    MNB_Shortcode::init();
    MNB_Cart::init();
    MNB_Order::init();
    MNB_Order_Status::init();
    MNB_Admin::init();
    MNB_Ajax::init();
    MNB_Epitaph::init();
    MNB_Template::init();
    MNB_Elementor_Import::init();
    MNB_Bulk_Import::init();
    MNB_Proof::init();
    MNB_Production_Sheet::init();
}

// ── Activation / Deactivation ────────────────────────────────────────────────
register_activation_hook( __FILE__, 'mnb_activate' );
function mnb_activate() {
    MNB_Post_Type::register();
    flush_rewrite_rules();

    // Default options
    add_option( 'mnb_fonts', [
        'sunk_roman'      => 'Sunk Roman',
        'vermarco_script'   => 'Vermarco Script',
        'spacerite_vermarco' => 'Spacerite Vermarco',
    ] );

    add_option( 'mnb_text_lines', [
        [ 'key' => 'line1_name',    'label' => 'Line 1 – Full Name',         'required' => true,  'max' => 40 ],
        [ 'key' => 'line2_name',    'label' => 'Line 2 – Name (2nd person)', 'required' => false, 'max' => 40 ],
        [ 'key' => 'date_birth',    'label' => 'Date of Birth',              'required' => false, 'max' => 30 ],
        [ 'key' => 'date_passing',  'label' => 'Date of Passing',            'required' => false, 'max' => 30 ],
        [ 'key' => 'epitaph',       'label' => 'Epitaph / Message',          'required' => false, 'max' => 80, 'textarea' => true ],
    ] );

    add_option( 'mnb_custom_statuses', [
        'wc-in-production' => 'In Production',
        'wc-engraving'     => 'Engraving',
        'wc-ready-ship'    => 'Ready to Ship',
    ] );
}

register_deactivation_hook( __FILE__, 'mnb_deactivate' );
function mnb_deactivate() {
    flush_rewrite_rules();
}
