<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers custom WooCommerce order statuses for
 * the monument production workflow.
 */
class MNB_Order_Status {

    public static function init() {
        add_action( 'init',                              [ __CLASS__, 'register_statuses' ] );
        add_filter( 'wc_order_statuses',                 [ __CLASS__, 'add_to_wc_statuses' ] );
        add_filter( 'woocommerce_valid_order_statuses_for_payment', [ __CLASS__, 'payment_statuses' ] );
        add_action( 'admin_head',                        [ __CLASS__, 'status_colors' ] );
        add_filter( 'bulk_actions-edit-shop_order',      [ __CLASS__, 'bulk_actions' ] );
        add_filter( 'woocommerce_email_order_statuses',  [ __CLASS__, 'email_statuses' ] );
    }

    public static function register_statuses() {
        $statuses = [
            'wc-in-production' => [
                'label'                     => 'In Production',
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'In Production <span class="count">(%s)</span>', 'In Production <span class="count">(%s)</span>' ),
            ],
            'wc-engraving' => [
                'label'                     => 'Engraving',
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'Engraving <span class="count">(%s)</span>', 'Engraving <span class="count">(%s)</span>' ),
            ],
            'wc-ready-ship' => [
                'label'                     => 'Ready to Ship',
                'public'                    => false,
                'exclude_from_search'       => false,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'Ready to Ship <span class="count">(%s)</span>', 'Ready to Ship <span class="count">(%s)</span>' ),
            ],
        ];

        foreach ( $statuses as $status => $args ) {
            register_post_status( $status, $args );
        }
    }

    public static function add_to_wc_statuses( $order_statuses ) {
        $new_statuses = [];

        foreach ( $order_statuses as $key => $status ) {
            $new_statuses[ $key ] = $status;
            if ( 'wc-processing' === $key ) {
                $new_statuses['wc-in-production'] = 'In Production';
                $new_statuses['wc-engraving']     = 'Engraving';
                $new_statuses['wc-ready-ship']    = 'Ready to Ship';
            }
        }

        return $new_statuses;
    }

    public static function payment_statuses( $statuses ) {
        return array_merge( $statuses, [ 'in-production', 'engraving', 'ready-ship' ] );
    }

    public static function status_colors() {
        ?>
        <style>
            .order-status.status-in-production { background: #e8f0fe; color: #1a73e8; }
            .order-status.status-engraving     { background: #fce8b2; color: #b06000; }
            .order-status.status-ready-ship    { background: #e6f4ea; color: #1e7e34; }
        </style>
        <?php
    }

    public static function bulk_actions( $actions ) {
        $actions['mark_in-production'] = 'Mark: In Production';
        $actions['mark_engraving']     = 'Mark: Engraving';
        $actions['mark_ready-ship']    = 'Mark: Ready to Ship';
        return $actions;
    }

    public static function email_statuses( $statuses ) {
        return array_merge( $statuses, [ 'in-production', 'engraving', 'ready-ship' ] );
    }
}
