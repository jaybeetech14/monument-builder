<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Template v16
 *
 * Enqueues plugin CSS/JS on monument product pages only.
 * No PHP template overrides. No body classes. No broad CSS selectors.
 * Header and footer are entirely Elementor's responsibility.
 */
class MNB_Template {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'wp_head',            [ __CLASS__, 'hide_payment_buttons' ] );
        add_action( 'wp_footer',          [ __CLASS__, 'remove_payment_buttons_js' ] );

        // Monument details confirmation checkbox on checkout
        // Classic shortcode checkout
        add_action( 'woocommerce_review_order_before_submit',  [ __CLASS__, 'checkout_confirmation_checkbox' ] );
        add_action( 'woocommerce_checkout_process',            [ __CLASS__, 'validate_checkout_confirmation' ] );
        // Block-based checkout (WooCommerce Blocks / WooPay)
        add_action( 'woocommerce_blocks_checkout_order_processed', [ __CLASS__, 'validate_checkout_confirmation_blocks' ], 10, 1 );
        add_filter( 'woocommerce_store_api_checkout_order_processed', [ __CLASS__, 'validate_checkout_confirmation_blocks_api' ], 10, 1 );
        // Inject checkbox into block checkout via wp_footer JS
        add_action( 'wp_footer', [ __CLASS__, 'inject_block_checkout_checkbox' ] );
    }


    /**
     * Inject inline CSS to hide express payment buttons on single product pages.
     * Fires on ALL single product pages, not just monument products,
     * since express checkout is never appropriate for custom engraved items.
     */
    public static function hide_payment_buttons() {
        if ( ! is_singular( 'product' ) ) return;
        ?>
        <style id="mnb-hide-payments">
        /* ── Express payment buttons ── */
        .single-product .wc-ppec-cart-buttons-wrap,
        .single-product #woo_pp_ec_button_product,
        .single-product .ppcpcc-container,
        .single-product .woopay-express-button-wrapper,
        .single-product .wc-block-components-express-payment,
        .single-product .wc-block-components-express-payment-continue-rule,
        .single-product #wc-stripe-payment-request-wrapper,
        .single-product [id*="payment-request-button"],
        .single-product [id*="paypal-button"],
        .single-product [id*="smart-button"],
        .single-product [id*="woo_pp"],
        .single-product [id*="ppcp"],
        .single-product [class*="ppcp-button"],
        .single-product [class*="ppcp-container"],
        .single-product [class*="woopay-button"],
        .single-product [class*="gpay-button"],
        .single-product [class*="apple-pay-button"],
        .single-product [class*="venmo"],
        .single-product [class*="paypal-button"],
        .single-product .wc-paypal-pay-later-message,
        .single-product .ppc-button-wrapper,

        /* PayPal "Starting at / pay later" message is intentionally kept visible */

        /* ── Klarna / Affirm / Afterpay banners ── */
        .single-product .klarna-payments-osm,
        .single-product .affirm-as-low-as,
        .single-product [id*="afterpay"],
        .single-product [class*="afterpay"],
        .single-product [class*="klarna"],
        .single-product [class*="affirm"],

        /* ── WooCommerce product rating (stars) ── */
        .single-product .woocommerce-product-rating,

        /* ── "Or" divider between button and express payments ── */
        .single-product .wc-block-components-express-payment__title-container { 
            display: none !important; 
        }
        </style>
        <?php
    }
    public static function enqueue_assets() {
        if ( ! is_singular( 'product' ) ) return;

        $product_id = get_queried_object_id();
        if ( ! MNB_Product_Fields::product_is_monument( $product_id ) ) return;

        wp_enqueue_style(
            'mnb-layout',
            MNB_PLUGIN_URL . 'assets/css/mnb-layout.css',
            [ 'mnb-frontend' ],
            MNB_VERSION
        );

        wp_enqueue_script(
            'mnb-gallery',
            MNB_PLUGIN_URL . 'assets/js/mnb-gallery.js',
            [ 'jquery' ],
            MNB_VERSION,
            true
        );
    }

    /**
     * Inject JS to remove express payment buttons that are injected
     * dynamically by payment plugins after page load (e.g. Google Pay,
     * PayPal express). CSS alone cannot catch these.
     */
    public static function remove_payment_buttons_js() {
        if ( ! is_singular( 'product' ) ) return;
        ?>
        <script id="mnb-remove-payments">
        ( function() {
            var selectors = [
                '[class*="gpay"]',
                '[class*="google-pay"]',
                '[id*="google-pay"]',
                '[class*="ppcp-button"]',
                '[id*="ppcp"]',
                '[class*="woopay-express"]',
                '[id*="woopay"]',
                '#wc-ppcp-product-button-container',
                '#woo_pp_product_button',
                '.wc-ppec-cart-buttons-wrap',
                '.wc-stripe-product-payment-request-wrapper',
                '[id*="payment-request-button"]',
                '[class*="venmo"]',
                '[class*="apple-pay"]',
                '.wc-block-components-express-payment',
                '.wc-block-components-express-payment-continue-rule'
            ];

            function removeButtons() {
                selectors.forEach( function( sel ) {
                    try {
                        document.querySelectorAll( sel ).forEach( function( el ) {
                            el.style.setProperty( 'display', 'none', 'important' );
                        } );
                    } catch(e) {}
                } );
            }

            // Run immediately
            removeButtons();

            // Run after DOM ready
            document.addEventListener( 'DOMContentLoaded', removeButtons );

            // Run after scripts inject buttons (observe DOM changes)
            if ( window.MutationObserver ) {
                var observer = new MutationObserver( function( mutations ) {
                    var shouldRun = mutations.some( function( m ) {
                        return m.addedNodes.length > 0;
                    } );
                    if ( shouldRun ) removeButtons();
                } );
                document.addEventListener( 'DOMContentLoaded', function() {
                    observer.observe( document.body, { childList: true, subtree: true } );
                    // Stop observing after 10s to avoid performance impact
                    setTimeout( function() { observer.disconnect(); }, 10000 );
                } );
            }
        } )();
        </script>
        <?php
    }

    /**
     * Render a required "I confirm my monument details" checkbox before
     * the Place Order button on the checkout page.
     */
    public static function checkout_confirmation_checkbox() {
        // Only show if cart contains a monument product
        $has_monument = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( MNB_Product_Fields::product_is_monument( $item['product_id'] ) ) {
                $has_monument = true;
                break;
            }
        }
        if ( ! $has_monument ) return;
        ?>
        <div class="mnb-checkout-confirmation" style="
            background: #fff8e1;
            border: 2px solid #c8a84b;
            border-radius: 6px;
            padding: 16px 20px;
            margin: 20px 0;
        ">
            <label for="mnb_monument_confirm" style="
                display: flex;
                align-items: flex-start;
                gap: 12px;
                cursor: pointer;
                font-weight: 600;
                font-size: 15px;
                line-height: 1.5;
                color: #2f2f2f;
            ">
                <input type="checkbox"
                       name="mnb_monument_confirm"
                       id="mnb_monument_confirm"
                       value="1"
                       style="
                           width: 22px;
                           height: 22px;
                           min-width: 22px;
                           margin-top: 2px;
                           accent-color: #c8a84b;
                           cursor: pointer;
                       ">
                <span>
                    &#9888; <span style="color:#c8a84b;">REQUIRED:</span>
                    I have reviewed my monument engraving details and confirm they are correct.
                    I understand that once production begins, changes may not be possible.
                    <span style="display:block;font-weight:400;font-size:13px;color:#666;margin-top:4px;">
                        Please double-check names, dates, and spelling before placing your order.
                    </span>
                </span>
            </label>
        </div>
        <?php
    }

    /**
     * Block checkout submission if the monument confirmation checkbox is not checked.
     */
    public static function validate_checkout_confirmation() {
        // Check if cart has a monument
        $has_monument = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( MNB_Product_Fields::product_is_monument( $item['product_id'] ) ) {
                $has_monument = true;
                break;
            }
        }
        if ( ! $has_monument ) return;

        if ( empty( $_POST['mnb_monument_confirm'] ) ) {
            wc_add_notice(
                '&#9888; Please confirm your monument engraving details are correct before placing your order.',
                'error'
            );
        }
    }


    /**
     * Inject the confirmation checkbox into block-based checkout via JS.
     * WooCommerce Blocks renders in React — we inject after page load.
     */
    public static function inject_block_checkout_checkbox() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
        if ( ! self::cart_has_monument() ) return;
        ?>
        <style>
        .mnb-checkout-confirmation { display:none; }
        .mnb-checkout-confirmation.mnb-visible {
            display:block;
            background:#fff8e1;
            border:2px solid #c8a84b;
            border-radius:6px;
            padding:16px 20px;
            margin:16px 0;
        }
        .mnb-checkout-confirmation label {
            display:flex;
            align-items:flex-start;
            gap:12px;
            cursor:pointer;
            font-weight:600;
            font-size:15px;
            line-height:1.5;
            color:#2f2f2f;
        }
        .mnb-checkout-confirmation input[type="checkbox"] {
            width:22px;height:22px;min-width:22px;
            margin-top:2px;accent-color:#c8a84b;cursor:pointer;flex-shrink:0;
        }
        .mnb-checkout-confirmation .mnb-confirm-sub {
            display:block;font-weight:400;font-size:13px;color:#666;margin-top:4px;
        }
        .mnb-confirm-error {
            color:#c0392b;font-weight:600;font-size:13px;
            padding:8px 12px;background:#fff0f0;border:1px solid #fcc;
            border-radius:4px;margin-top:8px;display:none;
        }
        </style>
        <div class="mnb-checkout-confirmation" id="mnb-checkout-confirmation">
            <label for="mnb_monument_confirm_block">
                <input type="checkbox" id="mnb_monument_confirm_block" name="mnb_monument_confirm_block">
                <span>
                    &#9888; <span style="color:#c8a84b;">REQUIRED:</span>
                    I have reviewed my monument engraving details and confirm they are correct.
                    I understand that once production begins, changes may not be possible.
                    <span class="mnb-confirm-sub">
                        Please double-check names, dates, and spelling before placing your order.
                    </span>
                </span>
            </label>
            <div class="mnb-confirm-error" id="mnb-confirm-error">
                &#9888; Please confirm your monument details are correct before placing your order.
            </div>
        </div>
        <script>
        ( function() {
            function injectCheckbox() {
                var box = document.getElementById( 'mnb-checkout-confirmation' );
                if ( ! box ) return;

                // Try multiple known block checkout button selectors
                var btn = document.querySelector( '.wc-block-components-checkout-place-order-button' )
                       || document.querySelector( 'button.wc-block-checkout__submit-button' )
                       || document.querySelector( '[class*="place-order"] button' )
                       || document.querySelector( 'button[type="submit"][class*="checkout"]' );

                if ( btn && ! box.classList.contains( 'mnb-visible' ) ) {
                    btn.parentNode.insertBefore( box, btn );
                    box.classList.add( 'mnb-visible' );

                    // Intercept button click
                    btn.addEventListener( 'click', function( e ) {
                        var cb    = document.getElementById( 'mnb_monument_confirm_block' );
                        var errEl = document.getElementById( 'mnb-confirm-error' );
                        if ( cb && ! cb.checked ) {
                            e.preventDefault();
                            e.stopImmediatePropagation();
                            errEl.style.display = 'block';
                            box.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                            return false;
                        }
                        if ( errEl ) errEl.style.display = 'none';
                    }, true );
                }
            }

            // Try on DOM ready and repeatedly for React-rendered content
            document.addEventListener( 'DOMContentLoaded', injectCheckbox );
            setTimeout( injectCheckbox, 500 );
            setTimeout( injectCheckbox, 1500 );
            setTimeout( injectCheckbox, 3000 );

            // Also watch for DOM changes (React re-renders)
            if ( window.MutationObserver ) {
                var obs = new MutationObserver( function( muts ) {
                    var added = muts.some( function(m){ return m.addedNodes.length > 0; } );
                    if ( added ) injectCheckbox();
                } );
                document.addEventListener( 'DOMContentLoaded', function() {
                    obs.observe( document.body, { childList: true, subtree: true } );
                    setTimeout( function(){ obs.disconnect(); }, 15000 );
                } );
            }
        } )();
        </script>
        <?php
    }

    private static function cart_has_monument() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return false;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( MNB_Product_Fields::product_is_monument( $item['product_id'] ) ) return true;
        }
        return false;
    }

    /**
     * Server-side validation for block checkout (fires via REST API order processing).
     */
    public static function validate_checkout_confirmation_blocks( $order ) {
        // Block checkout doesn't POST the checkbox — we rely on JS interception.
        // This is a secondary check using order meta if we ever store it.
    }

    public static function validate_checkout_confirmation_blocks_api( $order ) {
        return $order;
    }

}