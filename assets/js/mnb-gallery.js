/* global jQuery */
( function ( $ ) {
    'use strict';

    const MNBGallery = {

        init() {
            this.syncMnbSelectsToFormCart();
            this.syncVariationForms();
            this.bindThumbs();
            this.bindVariationSwap();
        },

        // ── Mirror MNB Step 1 selects → form.cart ────────────────────────────
        //
        // The MNB shortcode renders variation dropdowns inside .mnb-variations-table
        // which is OUTSIDE form.cart. WooCommerce's wc-add-to-cart-variation.js only
        // watches selects inside form.cart, so it never fires found_variation.
        //
        // Fix: whenever a .mnb-variations-table select changes, copy the value into
        // the matching select inside form.cart and trigger WC's change detection.
        // This makes found_variation fire → canvas image updates.

        syncMnbSelectsToFormCart() {
            const mnbTable = document.querySelector( '.mnb-variations-table' );
            if ( ! mnbTable ) return;

            // Find the form.cart that has the variation selects
            // (the mnb-variations-only widget or the first form.cart with selects)
            const getTargetForm = () => {
                const forms = document.querySelectorAll( 'form.cart' );
                for ( const form of forms ) {
                    if ( form.querySelector( 'select[name^="attribute_"]' ) ) return form;
                }
                // If no form has attribute selects yet, return button form as fallback
                return Array.from( forms ).find( f =>
                    f.querySelector( '.single_add_to_cart_button' )
                ) || forms[0];
            };

            $( mnbTable ).on( 'change', 'select', function () {
                const name = this.name;
                const val  = this.value;

                const targetForm = getTargetForm();
                if ( ! targetForm ) return;

                // Find or create the matching select in form.cart
                let formSelect = targetForm.querySelector( `select[name="${name}"]` );

                if ( ! formSelect ) {
                    // Create a hidden select so WC variation JS can read it
                    formSelect = document.createElement( 'select' );
                    formSelect.name  = name;
                    formSelect.style.display = 'none';
                    const opt = document.createElement( 'option' );
                    opt.value    = val;
                    opt.selected = true;
                    formSelect.appendChild( opt );
                    targetForm.appendChild( formSelect );
                } else {
                    formSelect.value = val;
                }

                // Trigger WooCommerce's native variation detection
                $( formSelect ).trigger( 'change' );
                $( targetForm ).trigger( 'woocommerce_variation_select_change' );
                $( targetForm ).find( '.variations_form' ).trigger( 'wc_variation_form' );

                // Also sync to ALL other form.cart elements (button form)
                document.querySelectorAll( 'form.cart' ).forEach( form => {
                    if ( form === targetForm ) return;
                    let sel = form.querySelector( `select[name="${name}"]` );
                    if ( sel ) {
                        sel.value = val;
                        $( sel ).trigger( 'change' );
                    }
                } );
            } );

            // When WC finds a variation, update the canvas background
            $( document ).on( 'found_variation', '.variations_form, form.cart', function( e, variation ) {
                if ( variation && variation.image && variation.image.url ) {
                    MNBGallery.updateCanvasBg( variation.image.url );
                }
            } );

            // Handle reset — restore original canvas image
            $( mnbTable ).on( 'click', '.reset_variations', function() {
                if ( window.mnbData && window.mnbData.canvasImg ) {
                    const container = document.getElementById( 'mnb-canvas-container' );
                    if ( container && window.MNBCanvas && window.MNBCanvas.loadBackground ) {
                        window.MNBCanvas.loadBackground( window.mnbData.canvasImg, container );
                    }
                }
            } );

            // Trigger sync on page load for any pre-selected values
            // Delay to allow WooCommerce variation JS to initialise first
            setTimeout( () => {
                mnbTable.querySelectorAll( 'select[name^="attribute_"]' ).forEach( sel => {
                    if ( sel.value ) {
                        $( sel ).trigger( 'change' );
                    }
                } );
            }, 600 );
        },

        // ── Sync two Add to Cart forms (button form ↔ variations form) ───────

        syncVariationForms() {
            const forms = document.querySelectorAll( 'form.cart' );
            if ( forms.length < 2 ) return;

            let leftForm  = null;
            let rightForm = null;

            forms.forEach( form => {
                if ( form.querySelector( 'select[name^="attribute_"]' ) ) {
                    leftForm = form;
                } else if ( form.querySelector( '.single_add_to_cart_button' ) ) {
                    rightForm = form;
                }
            } );

            if ( ! leftForm ) leftForm  = forms[0];
            if ( ! rightForm ) rightForm = forms[1];
            if ( ! leftForm || ! rightForm || leftForm === rightForm ) return;

            // Mirror left → right on select change
            $( leftForm ).on( 'change', 'select[name^="attribute_"]', function () {
                const name = this.name;
                const val  = this.value;
                const rightSel = rightForm.querySelector( `select[name="${name}"]` );
                if ( rightSel && rightSel.value !== val ) {
                    rightSel.value = val;
                    $( rightSel ).trigger( 'change' );
                }
            } );

            // On submit: copy variation_id + attributes from left to right
            $( rightForm ).on( 'submit', function () {
                const leftVarId  = leftForm.querySelector( 'input[name="variation_id"]' );
                const rightVarId = rightForm.querySelector( 'input[name="variation_id"]' );
                if ( leftVarId && rightVarId && leftVarId.value && leftVarId.value !== '0' ) {
                    rightVarId.value = leftVarId.value;
                }
                leftForm.querySelectorAll( 'input[name^="attribute_"]' ).forEach( inp => {
                    let rightInp = rightForm.querySelector( `input[name="${inp.name}"]` );
                    if ( ! rightInp ) {
                        rightInp = document.createElement( 'input' );
                        rightInp.type = 'hidden';
                        rightInp.name = inp.name;
                        rightForm.appendChild( rightInp );
                    }
                    rightInp.value = inp.value;
                } );
            } );

            this._leftForm  = leftForm;
            this._rightForm = rightForm;
        },

        // ── Gallery thumbnail clicks ──────────────────────────────────────────

        bindThumbs() {
            $( document ).on( 'click', '.mnb-thumb', function () {
                const fullUrl = $( this ).data( 'full' );
                if ( ! fullUrl ) return;

                $( '.mnb-thumb' ).removeClass( 'mnb-thumb-active' );
                $( this ).addClass( 'mnb-thumb-active' );

                const img  = new Image();
                img.onload = () => MNBGallery.updateCanvasBg( fullUrl );
                img.src = fullUrl;
            } );
        },

        // ── WooCommerce found_variation → update canvas bg ────────────────────

        bindVariationSwap() {
            // Listen on document so it catches events from any form.cart
            $( document ).on( 'found_variation', function ( e, variation ) {
                if ( variation && variation.image && variation.image.url ) {
                    MNBGallery.updateCanvasBg( variation.image.url );
                }
                // Collapse the large gallery image once a variation is chosen
                MNBGallery.collapseGallery( true );
            } );

            $( document ).on( 'reset_data', function () {
                if ( window.mnbData && window.mnbData.canvasImg ) {
                    const container = document.getElementById( 'mnb-canvas-container' );
                    if ( container && window.MNBCanvas && window.MNBCanvas.loadBackground ) {
                        window.MNBCanvas.loadBackground( window.mnbData.canvasImg, container );
                    }
                }
                MNBGallery.collapseGallery( false );
            } );

            // When WooCommerce variation JS inits on any form, re-trigger
            // change on MNB selects in case they were already filled
            $( document ).on( 'wc_variation_form', function() {
                const mnbTable = document.querySelector( '.mnb-variations-table' );
                if ( ! mnbTable ) return;
                setTimeout( () => {
                    mnbTable.querySelectorAll( 'select[name^="attribute_"]' ).forEach( sel => {
                        if ( sel.value ) $( sel ).trigger( 'change' );
                    } );
                }, 300 );
            } );
        },

        updateCanvasBg( imgUrl ) {
            if ( ! imgUrl ) return;
            const container = document.getElementById( 'mnb-canvas-container' );
            if ( ! container ) return;
            if ( window.MNBCanvas && window.MNBCanvas.loadBackground ) {
                window.MNBCanvas.loadBackground( imgUrl, container );
            }
        },

        /**
         * Collapse the main product gallery image to 300px once the customer
         * has selected a variation in Step 1. The canvas preview becomes the
         * primary visual reference at that point, so the large gallery image
         * is no longer needed at full size.
         * Thumbnails are unaffected. Zoom icon remains functional.
         */
        collapseGallery( collapsed ) {
            const gallery = document.querySelector(
                '.mnb-product-gallery-right .woocommerce-product-gallery, ' +
                '.mnb-product-gallery-right .woocommerce-product-gallery__wrapper'
            );
            if ( ! gallery ) return;

            const mainImg = gallery.querySelector(
                '.woocommerce-product-gallery__image:first-child, .flex-viewport'
            );
            if ( ! mainImg ) return;

            if ( collapsed ) {
                mainImg.style.maxHeight = '300px';
                mainImg.style.overflow  = 'hidden';
                mainImg.style.transition = 'max-height 0.3s ease';
                mainImg.style.cursor     = 'pointer';
                // Allow click to temporarily expand
                mainImg.addEventListener( 'click', function toggleExpand() {
                    const isCollapsed = mainImg.style.maxHeight === '300px';
                    mainImg.style.maxHeight = isCollapsed ? '100%' : '300px';
                }, { once: false } );
            } else {
                mainImg.style.maxHeight = '';
                mainImg.style.overflow  = '';
            }
        },
    };

    $( document ).ready( () => MNBGallery.init() );

} )( jQuery );
