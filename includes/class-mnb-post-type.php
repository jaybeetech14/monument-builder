<?php
defined( 'ABSPATH' ) || exit;

class MNB_Post_Type {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_action( 'init', [ __CLASS__, 'register_taxonomies' ] );
        add_filter( 'manage_mnb_monument_posts_columns',       [ __CLASS__, 'admin_columns' ] );
        add_action( 'manage_mnb_monument_posts_custom_column', [ __CLASS__, 'admin_column_data' ], 10, 2 );
    }

    public static function register() {
        register_post_type( 'mnb_monument', [
            'label'               => 'Monuments',
            'labels'              => [
                'name'               => 'Monuments',
                'singular_name'      => 'Monument',
                'add_new'            => 'Add New',
                'add_new_item'       => 'Add New Monument',
                'edit_item'          => 'Edit Monument',
                'new_item'           => 'New Monument',
                'view_item'          => 'View Monument',
                'search_items'       => 'Search Monuments',
                'not_found'          => 'No monuments found',
                'not_found_in_trash' => 'No monuments in trash',
                'menu_name'          => 'Monuments',
            ],
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'monument-builder',
            'supports'            => [ 'title', 'thumbnail', 'editor' ],
            'has_archive'         => false,
            'rewrite'             => false,
            'capability_type'     => 'post',
        ] );
    }

    public static function register_taxonomies() {
        // Monument Category taxonomy
        register_taxonomy( 'monument_category', 'mnb_monument', [
            'label'             => 'Monument Categories',
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_admin_column' => true,
            'rewrite'           => false,
            'labels'            => [
                'name'              => 'Categories',
                'singular_name'     => 'Category',
                'add_new_item'      => 'Add New Category',
                'edit_item'         => 'Edit Category',
                'search_items'      => 'Search Categories',
            ],
        ] );
    }

    public static function admin_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['sku']             = 'SKU Pattern';
                $new['wc_product']      = 'WC Product';
                $new['monument_type']   = 'Type';
            }
        }
        return $new;
    }

    public static function admin_column_data( $column, $post_id ) {
        switch ( $column ) {
            case 'sku':
                echo esc_html( get_post_meta( $post_id, '_mnb_sku_pattern', true ) ?: '—' );
                break;
            case 'wc_product':
                $pid = get_post_meta( $post_id, '_mnb_wc_product_id', true );
                if ( $pid ) {
                    $product = wc_get_product( $pid );
                    if ( $product ) {
                        printf(
                            '<a href="%s">%s</a>',
                            get_edit_post_link( $pid ),
                            esc_html( $product->get_name() )
                        );
                    } else {
                        echo '<span style="color:#999;">Unlinked</span>';
                    }
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;
            case 'monument_type':
                $type = get_post_meta( $post_id, '_mnb_monument_type', true );
                $label = $type === 'blank' ? '<span style="background:#e8f4e8;color:#2d6a2d;padding:2px 8px;border-radius:3px;font-size:11px;">Blank</span>'
                                           : '<span style="background:#e8eef8;color:#2d4a8a;padding:2px 8px;border-radius:3px;font-size:11px;">Engraved</span>';
                echo wp_kses_post( $label );
                break;
        }
    }
}
