/* global mnbData, fabric, jQuery */
( function ( $, fabric ) {
    'use strict';

    if ( typeof fabric === 'undefined' || ! mnbData ) return;

    const MNB = {

        canvas:          null,
        textObjects:     {},
        canvasW:         0,
        canvasH:         0,
        defaults:        {},
        proofTimer:      null,
        loadedFonts:     {},   // fontKey → exact CSS name usable by Fabric
        lineOverrides:   {},   // lineKey → fontKey set explicitly by user in Step 3
        uploadedImages:  [],   // array of { obj, dark } for multiple uploaded images

        // ══════════════════════════════════════════════════════════════════
        // INIT
        // ══════════════════════════════════════════════════════════════════

        init() {
            const container = document.getElementById( 'mnb-canvas-container' );
            if ( ! container ) return;

            this.buildCanvas( container );
            this.loadFonts().then( () => {
                this.loadBackground( mnbData.canvasImg, container );
                this.applyDefaultFont();
            } );

            this.buildDefaultPositions();
            // Skip font binding entirely for bronze monuments
            const hideFonts = document.getElementById( 'mnb-configurator' )
                ?.dataset?.hideFont === '1';
            if ( ! hideFonts ) {
                this.bindGlobalFont();
                this.bindPerLineOverrides();
            } else {
                // Bronze: apply the first available font as a fixed default
                const defaultRadio = document.querySelector(
                    'input[name="mnb_global_font"]:first-of-type'
                );
                if ( defaultRadio ) {
                    const cssFont = this.getFontFamily( defaultRadio.value );
                    this.applyFontToAllLines( defaultRadio.value, cssFont );
                }
            }
            this.bindInputs();
            this.bindEpitaphSelect();
            this.bindResetButton();
            this.bindDownloadButton();
            this.bindUploadToggle(); // uses correct IDs matching shortcode HTML
            this.bindVariationSwap();
            this.bindFormSubmit();
        },

        // ══════════════════════════════════════════════════════════════════
        // CANVAS SETUP
        // ══════════════════════════════════════════════════════════════════

        buildCanvas( container ) {
            const el     = document.getElementById( 'mnb-canvas' );
            this.canvasW = container.offsetWidth || 600;
            this.canvasH = Math.round( this.canvasW * 0.55 );

            el.width  = this.canvasW;
            el.height = this.canvasH;

            this.canvas = new fabric.Canvas( 'mnb-canvas', {
                selection:              false,
                preserveObjectStacking: true,
            } );

            window.addEventListener( 'resize', () => this.handleResize( container ) );
        },

        loadBackground( imgUrl, container ) {
            if ( ! imgUrl ) return;

            const doLoad = ( src ) => {
                fabric.Image.fromURL( src, ( fabricImg ) => {
                    const imgNatW = fabricImg.width;
                    const imgNatH = fabricImg.height;
                    const aspect  = imgNatH / imgNatW;

                    this.canvasW = container.offsetWidth || 600;
                    this.canvasH = Math.round( this.canvasW * aspect );

                    this.canvas.setDimensions( { width: this.canvasW, height: this.canvasH } );
                    container.style.aspectRatio = imgNatW + ' / ' + imgNatH;
                    container.classList.add( 'mnb-canvas-loaded' );

                    fabricImg.set( {
                        scaleX: this.canvasW / imgNatW,
                        scaleY: this.canvasH / imgNatH,
                        originX: 'left', originY: 'top',
                        selectable: false, evented: false,
                    } );

                    this.canvas.setBackgroundImage( fabricImg, () => {
                        this.canvas.renderAll();
                        if ( mnbData.layout === 'double' ) this.drawDivider();
                        this.repositionTextObjects();
                        // Update engraving image filter for new stone colour
                        this.updateUploadedImageFilter();
                    } );
                }, { crossOrigin: 'anonymous' } );
            };

            const img = document.getElementById( 'mnb-bg-img' );
            if ( img ) {
                img.src = imgUrl;
                if ( img.complete && img.naturalWidth ) doLoad( imgUrl );
                else img.onload = () => doLoad( imgUrl );
            } else {
                doLoad( imgUrl );
            }
        },

        drawDivider() {
            this.canvas.getObjects().filter( o => o.name === 'divider' ).forEach( o => this.canvas.remove( o ) );
            const mid  = this.canvasW / 2;
            const line = new fabric.Line( [ mid, 20, mid, this.canvasH - 20 ], {
                stroke: 'rgba(255,255,255,0.35)', strokeWidth: 2,
                strokeDashArray: [ 6, 4 ], selectable: false, evented: false, name: 'divider',
            } );
            this.canvas.add( line );
            this.canvas.sendToBack( line );
        },

        buildDefaultPositions() {
            ( mnbData.fields || [] ).forEach( f => {
                this.defaults[ f.key ] = { x_pct: f.default_x_pct || 50, y_pct: f.default_y_pct || 50 };
            } );
        },

        repositionTextObjects() {
            Object.keys( this.textObjects ).forEach( key => {
                const obj = this.textObjects[ key ];
                const def = this.defaults[ key ];
                if ( ! def ) return;
                obj.set( {
                    left: ( def.x_pct / 100 ) * this.canvasW,
                    top:  ( def.y_pct / 100 ) * this.canvasH,
                } );
                obj.setCoords();
            } );
            this.canvas.renderAll();
        },

        // ══════════════════════════════════════════════════════════════════
        // FONT LOADING
        // Tries uploaded font files first, falls back to Google Fonts
        // for Sunk Roman (IM Fell English) and Vermarco Script (Great Vibes).
        // Registers each font so Fabric.js can render it on the canvas.
        // ══════════════════════════════════════════════════════════════════

        loadFonts() {
            const fonts    = mnbData.fonts    || {};
            const fontUrls = mnbData.fontUrls || {};
            const promises = [];

            // Google Fonts fallback map for known MNB font keys
            const googleFallback = {
                sunk_roman:      { family: 'IM Fell English', url: 'https://fonts.googleapis.com/css2?family=IM+Fell+English&display=swap' },
                vermarco_script:    { family: 'Great Vibes',     url: 'https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap' },
                spacerite_vermarco: { family: 'Great Vibes',     url: 'https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap' },
            };

            Object.keys( fonts ).forEach( key => {
                const displayName = fonts[ key ];

                if ( fontUrls[ key ] ) {
                    // Uploaded font file — load via FontFace API
                    const face = new FontFace( displayName, `url(${fontUrls[ key ]})` );
                    promises.push(
                        face.load().then( f => {
                            document.fonts.add( f );
                            this.loadedFonts[ key ] = displayName;
                        } ).catch( () => {
                            // Fallback to system font
                            this.loadedFonts[ key ] = mnbData.fontMap?.[ key ] || 'serif';
                        } )
                    );
                } else if ( googleFallback[ key ] ) {
                    // Load Google Font as stylesheet, then register name
                    const gf = googleFallback[ key ];
                    if ( ! document.querySelector( `link[href*="${encodeURIComponent(gf.family)}"]` ) ) {
                        const link  = document.createElement( 'link' );
                        link.rel    = 'stylesheet';
                        link.href   = gf.url;
                        document.head.appendChild( link );
                    }
                    // Wait for font to be ready then register
                    promises.push(
                        document.fonts.ready.then( () => {
                            this.loadedFonts[ key ] = gf.family;
                        } )
                    );
                } else {
                    // System font fallback
                    this.loadedFonts[ key ] = mnbData.fontMap?.[ key ] || 'serif';
                }
            } );

            return Promise.all( promises );
        },

        getFontFamily( fontKey ) {
            return this.loadedFonts[ fontKey ]
                || mnbData.fontMap?.[ fontKey ]
                || 'Times New Roman, Times, serif';
        },

        getGlobalFontKey() {
            const checked = document.querySelector( 'input[name="mnb_global_font"]:checked' );
            return checked?.value || Object.keys( mnbData.fonts || {} )[0] || 'sunk_roman';
        },

        getEffectiveFontFamily( lineKey ) {
            const radio = document.querySelector( `input[name="mnb_font_${lineKey}"]:checked` );
            const fk    = radio?.value || this.getGlobalFontKey();
            return this.getFontFamily( fk );
        },

        applyDefaultFont() {
            const defaultKey = this.getGlobalFontKey();
            const cssFont    = this.getFontFamily( defaultKey );
            this.applyFontToAllLines( defaultKey, cssFont );
        },

        applyFontToAllLines( fontKey, cssFont ) {
            // Update per-line radio buttons — skip lines the user has explicitly overridden
            // Per-line radios have name="mnb_font_{lineKey}" with value=fontKey
            document.querySelectorAll( 'input[type="radio"][name^="mnb_font_"]' ).forEach( radio => {
                if ( radio.name === 'mnb_global_font' ) return;
                const key = radio.name.replace( /^mnb_font_/, '' );
                if ( ! key || this.lineOverrides[ key ] ) return;
                if ( radio.value === fontKey ) {
                    radio.checked = true;
                    const container = radio.closest( '.mnb-font-options-inline' );
                    if ( container ) {
                        container.querySelectorAll( '.mnb-font-option-sm' ).forEach( l =>
                            l.classList.remove( 'mnb-font-option-sm--active', 'mnb-selected' )
                        );
                        radio.closest( '.mnb-font-option-sm' )
                            ?.classList.add( 'mnb-font-option-sm--active', 'mnb-selected' );
                    }
                }
            } );
            // Update canvas objects — skip lines the user has explicitly overridden
            Object.keys( this.textObjects ).forEach( key => {
                if ( this.lineOverrides[ key ] ) return;
                this.textObjects[ key ].set( 'fontFamily', cssFont );
            } );
            if ( this.canvas ) {
                this.canvas.renderAll();
                this.serializeCanvas();
            }
        },

        // ══════════════════════════════════════════════════════════════════
        // GLOBAL FONT (Step 1 cards)
        // ══════════════════════════════════════════════════════════════════


        // ── Monument type buttons (Single / Double) ───────────────────────────

        bindTypeButtons() {
            document.querySelectorAll( '.mnb-type-btn' ).forEach( btn => {
                btn.addEventListener( 'click', () => {
                    document.querySelectorAll( '.mnb-type-btn' ).forEach( b => b.classList.remove( 'mnb-type-btn--active' ) );
                    btn.classList.add( 'mnb-type-btn--active' );

                    const type  = btn.dataset.type;
                    const input = document.getElementById( 'mnb-layout-input' );
                    if ( input ) input.value = type;

                    // Update badge
                    const badge = document.querySelector( '.mnb-badge-double' );
                    if ( badge ) {
                        badge.closest( '.mnb-layout-badge' ).style.display = type === 'double' ? '' : 'none';
                    }
                } );
            } );
        },

        // ── Upload toggle accordion ───────────────────────────────────────────

        bindUploadToggle() {
            const toggle   = document.getElementById( 'mnb-upload-toggle' );
            const panel    = document.getElementById( 'mnb-upload-panel' );
            const input    = document.getElementById( 'mnb-upload-input' );
            const dropzone = document.getElementById( 'mnb-upload-dropzone' );
            const preview  = document.getElementById( 'mnb-upload-preview' );
            const thumb    = document.getElementById( 'mnb-upload-thumb' );
            const removeBtn= document.getElementById( 'mnb-upload-remove' );
            const dropUI   = document.getElementById( 'mnb-upload-dropzone-ui' );
            const note     = document.getElementById( 'mnb-upload-note' );
            const controls = document.getElementById( 'mnb-upload-controls' );
            const sizeRange= document.getElementById( 'mnb-upload-size' );
            const sizeVal  = document.getElementById( 'mnb-upload-size-val' );

            if ( ! toggle || ! panel ) return;

            // Toggle accordion
            toggle.addEventListener( 'click', () => {
                const open = toggle.getAttribute( 'aria-expanded' ) === 'true';
                toggle.setAttribute( 'aria-expanded', String( ! open ) );
                panel.classList.toggle( 'mnb-open', ! open );
            } );

            if ( ! input ) return;

            // Handle file selection
            const handleFile = ( file ) => {
                if ( ! file ) return;

                if ( file.type !== 'image/png' ) {
                    if ( note ) { note.textContent = 'Only PNG files are accepted.'; note.className = 'mnb-upload-note mnb-error'; }
                    return;
                }

                if ( file.size > 5 * 1024 * 1024 ) {
                    if ( note ) { note.textContent = 'File exceeds 5 MB limit.'; note.className = 'mnb-upload-note mnb-error'; }
                    return;
                }

                if ( note ) { note.textContent = ''; note.className = 'mnb-upload-note'; }

                const reader = new FileReader();
                reader.onload = ( ev ) => {
                    const dataURL = ev.target.result;

                    // Show preview thumbnail
                    if ( thumb )    thumb.src = dataURL;
                    if ( preview )  preview.style.display = 'flex';
                    if ( dropUI )   dropUI.style.display  = 'none';
                    if ( controls ) controls.style.display = 'block';

                    // Show filename
                    const fnEl = document.getElementById( 'mnb-upload-filename' );
                    if ( fnEl ) fnEl.textContent = file.name;

                    // Place image on canvas as draggable/resizable object
                    this.placeUploadedImage( dataURL, sizeRange ? parseInt( sizeRange.value ) : 30 );
                    // update count display immediately
                    setTimeout( () => this.updateUploadSlotCount(), 100 );
                };
                reader.readAsDataURL( file );
            };

            input.addEventListener( 'change', () => {
                if ( input.files && input.files[0] ) handleFile( input.files[0] );
            } );

            // Drag and drop
            if ( dropzone ) {
                dropzone.addEventListener( 'dragover',  ( e ) => { e.preventDefault(); dropzone.classList.add( 'mnb-drag-over' ); } );
                dropzone.addEventListener( 'dragleave', ()  => { dropzone.classList.remove( 'mnb-drag-over' ); } );
                dropzone.addEventListener( 'drop', ( e ) => {
                    e.preventDefault();
                    dropzone.classList.remove( 'mnb-drag-over' );
                    const file = e.dataTransfer.files?.[0];
                    if ( file ) handleFile( file );
                } );
            }

            // Remove uploaded image
            if ( removeBtn ) {
                removeBtn.addEventListener( 'click', () => {
                    if ( preview )  preview.style.display = 'none';
                    if ( dropUI )   dropUI.style.display  = 'flex';
                    if ( controls ) controls.style.display = 'none';
                    if ( thumb )    thumb.src = '';
                    if ( input )    input.value = '';
                    const fnEl = document.getElementById( 'mnb-upload-filename' );
                    if ( fnEl ) fnEl.textContent = '';
                    this.removeUploadedImage();
                } );
            }

            // Size range slider
            if ( sizeRange ) {
                sizeRange.addEventListener( 'input', () => {
                    if ( sizeVal ) sizeVal.textContent = sizeRange.value + '%';
                    if ( this._uploadedImageObj ) {
                        this.resizeUploadedImage( parseInt( sizeRange.value ) );
                    }
                } );
            }

            // Manual invert toggle — applies to ALL uploaded images
            const invertToggle = document.getElementById( 'mnb-upload-invert' );
            if ( invertToggle ) {
                invertToggle.addEventListener( 'change', () => {
                    const isDark = invertToggle.checked;
                    this.uploadedImages.forEach( entry => {
                        entry.dark = isDark;
                        entry.obj.filters = isDark ? [ new fabric.Image.filters.Invert() ] : [];
                        entry.obj.applyFilters();
                    } );
                    this._uploadedDark = isDark;
                    this.canvas.renderAll();
                    this.serializeCanvas();
                } );
            }

            // Add Another Image button — re-opens the file picker
            const addAnotherBtn = document.getElementById( 'mnb-upload-add-another' );
            if ( addAnotherBtn && input ) {
                addAnotherBtn.addEventListener( 'click', () => {
                    input.value = '';
                    input.click();
                } );
            }

            // Remove Last Image button
            const removeLastBtn = document.getElementById( 'mnb-upload-remove-last' );
            if ( removeLastBtn ) {
                removeLastBtn.addEventListener( 'click', () => {
                    this.removeLastUploadedImage();
                    if ( this.uploadedImages.length === 0 ) {
                        if ( preview )  preview.style.display = 'none';
                        if ( dropUI )   dropUI.style.display  = 'flex';
                        if ( controls ) controls.style.display = 'none';
                        if ( thumb )    thumb.src = '';
                        if ( input )    input.value = '';
                    }
                } );
            }
        },

        placeUploadedImage( dataURL, sizePct ) {
            if ( ! this.canvas ) return;

            // Note: does NOT remove existing images — supports multiple uploads

            fabric.Image.fromURL( dataURL, ( img ) => {
                const maxW  = ( sizePct / 100 ) * this.canvasW;
                const scale = maxW / img.width;

                // Detect background brightness and apply appropriate filter
                // so the engraving is always visible regardless of stone colour.
                const isDark = this.getBackgroundBrightness() < 140;

                if ( isDark ) {
                    // Dark stone (black, blue, paradiso): invert black → white
                    // simulating sandblasted/carved engraving appearance
                    img.filters = [ new fabric.Image.filters.Invert() ];
                    img.applyFilters();
                }

                img.set( {
                    left:         this.canvasW * 0.5,
                    top:          this.canvasH * 0.5,
                    originX:      'center',
                    originY:      'center',
                    scaleX:       scale,
                    scaleY:       scale,
                    name:         '_mnb_upload',
                    lockRotation: false,
                } );

                this.canvas.add( img );
                // Send image BEHIND text objects (but above stone background).
                // Without this, images added after typing partially hide the text.
                this.canvas.sendToBack( img );
                this.canvas.setActiveObject( img );

                // Track in multi-image array
                const entry = { obj: img, dark: isDark };
                this.uploadedImages.push( entry );
                // Keep legacy single ref for backwards compat
                this._uploadedImageObj = img;
                this._uploadedDark = isDark;

                // Sync manual toggle to auto-detection result
                const invertToggleEl = document.getElementById( 'mnb-upload-invert' );
                if ( invertToggleEl ) invertToggleEl.checked = isDark;

                this.canvas.renderAll();
                this.serializeCanvas();
                this.updateUploadSlotCount();
            }, { crossOrigin: 'anonymous' } );
        },

        /**
         * Sample the average pixel brightness of the canvas background image.
         * Returns 0 (black) – 255 (white). Falls back to 128 (neutral) if unavailable.
         */
        getBackgroundBrightness() {
            try {
                const bgImg = this.canvas.backgroundImage;
                if ( ! bgImg ) return 128;

                // Use a small offscreen canvas to sample colours
                const sampleW = 40;
                const sampleH = 40;
                const off  = document.createElement( 'canvas' );
                off.width  = sampleW;
                off.height = sampleH;
                const ctx  = off.getContext( '2d' );

                // Draw the fabric background image element into the sample canvas
                const el = bgImg.getElement ? bgImg.getElement() : bgImg._element;
                if ( ! el ) return 128;

                ctx.drawImage( el, 0, 0, sampleW, sampleH );
                const data = ctx.getImageData( 0, 0, sampleW, sampleH ).data;

                let total = 0, count = 0;
                for ( let i = 0; i < data.length; i += 4 ) {
                    const r = data[ i ], g = data[ i + 1 ], b = data[ i + 2 ], a = data[ i + 3 ];
                    if ( a < 128 ) continue; // skip transparent pixels
                    // Perceived brightness (ITU-R BT.601 luma)
                    total += 0.299 * r + 0.587 * g + 0.114 * b;
                    count++;
                }
                return count > 0 ? total / count : 128;
            } catch ( e ) {
                return 128; // cross-origin or other error — assume neutral
            }
        },

        /**
         * Re-apply the correct invert filter when the background changes
         * (e.g. customer picks a different stone colour).
         */
        updateUploadedImageFilter() {
            if ( this.uploadedImages.length === 0 ) return;
            const isDark = this.getBackgroundBrightness() < 140;
            this._uploadedDark = isDark;
            this.uploadedImages.forEach( entry => {
                if ( isDark === entry.dark ) return;
                entry.dark = isDark;
                entry.obj.filters = isDark ? [ new fabric.Image.filters.Invert() ] : [];
                entry.obj.applyFilters();
            } );
            const invertToggleEl = document.getElementById( 'mnb-upload-invert' );
            if ( invertToggleEl ) invertToggleEl.checked = isDark;
            this.canvas.renderAll();
            this.serializeCanvas();
        },

        resizeUploadedImage( sizePct ) {
            if ( ! this._uploadedImageObj ) return;
            const maxW  = ( sizePct / 100 ) * this.canvasW;
            const scale = maxW / this._uploadedImageObj.width;
            this._uploadedImageObj.set( { scaleX: scale, scaleY: scale } );
            this.canvas.renderAll();
            this.serializeCanvas();
        },

        removeUploadedImage() {
            // Remove all uploaded images (used by Reset button)
            this.uploadedImages.forEach( entry => {
                if ( entry.obj ) this.canvas.remove( entry.obj );
            } );
            this.uploadedImages = [];
            this._uploadedImageObj = null;
            this._uploadedDark = false;
            this.canvas.renderAll();
        },

        removeLastUploadedImage() {
            if ( this.uploadedImages.length === 0 ) return;
            const last = this.uploadedImages.pop();
            if ( last && last.obj ) this.canvas.remove( last.obj );
            this._uploadedImageObj = this.uploadedImages.length > 0
                ? this.uploadedImages[ this.uploadedImages.length - 1 ].obj : null;
            this.canvas.renderAll();
            this.serializeCanvas();
            this.updateUploadSlotCount();
        },

        updateUploadSlotCount() {
            const countEl = document.getElementById( 'mnb-upload-count' );
            const removeLastBtn = document.getElementById( 'mnb-upload-remove-last' );
            const addAnotherBtn = document.getElementById( 'mnb-upload-add-another' );
            const n = this.uploadedImages.length;
            if ( countEl ) countEl.textContent = n + ' image' + ( n !== 1 ? 's' : '' ) + ' on canvas';
            if ( removeLastBtn ) removeLastBtn.style.display = n > 0 ? 'inline-block' : 'none';
            if ( addAnotherBtn ) addAnotherBtn.style.display = n < 5 ? 'inline-block' : 'none';
        },

        bindGlobalFont() {
            const apply = ( fontKey ) => {
                const cssFont = this.getFontFamily( fontKey );

                // Update active state on all font card variants
                document.querySelectorAll( '[data-font-key]' ).forEach( c => {
                    c.classList.remove( 'mnb-font-swatch--active', 'mnb-font-tile--active', 'mnb-font-card-active' );
                } );
                document.querySelectorAll( `[data-font-key="${fontKey}"]` ).forEach( c => {
                    c.classList.add( 'mnb-font-swatch--active', 'mnb-font-tile--active' );
                } );

                this.applyFontToAllLines( fontKey, cssFont );
                this.scheduleProof();
            };

            // Works for .mnb-font-swatch, .mnb-font-tile, .mnb-font-card
            document.querySelectorAll( '[data-font-key]' ).forEach( card => {
                card.addEventListener( 'click', ( e ) => {
                    e.preventDefault();
                    const fontKey = card.dataset.fontKey;
                    const radio   = card.querySelector( 'input[name="mnb_global_font"]' );
                    if ( radio ) radio.checked = true;
                    apply( fontKey );
                } );
            } );

            // Radio change fallback
            document.querySelectorAll( 'input[name="mnb_global_font"]' ).forEach( radio => {
                radio.addEventListener( 'change', () => apply( radio.value ) );
            } );

            // Apply default on init
            const defaultRadio = document.querySelector( 'input[name="mnb_global_font"]:checked' );
            if ( defaultRadio ) apply( defaultRadio.value );
        },

        // ══════════════════════════════════════════════════════════════════
        // PER-LINE FONT OVERRIDES (Step 2 radio buttons)
        // ══════════════════════════════════════════════════════════════════

        bindPerLineOverrides() {
            // Per-line radios have name="mnb_font_{lineKey}" — extract key from name
            // The radio lives inside .mnb-font-inline[data-for="{lineKey}"], NOT .mnb-line-group
            document.querySelectorAll( 'input[type="radio"][name^="mnb_font_"]' ).forEach( radio => {
                // Skip the global font radio (name="mnb_global_font")
                if ( radio.name === 'mnb_global_font' ) return;

                radio.addEventListener( 'change', () => {
                    // Extract lineKey from name: mnb_font_{lineKey}_{fontKey} OR mnb_font_{lineKey}
                    // Name format is: mnb_font_{lineKey}  (value is fontKey)
                    const key = radio.name.replace( /^mnb_font_/, '' );
                    if ( ! key ) return;

                    // Record explicit user override so global font won't overwrite it
                    this.lineOverrides[ key ] = radio.value;

                    const cssFont = this.getFontFamily( radio.value );
                    const obj = this.textObjects[ key ];
                    if ( obj ) {
                        obj.set( 'fontFamily', cssFont );
                        this.canvas.renderAll();
                        this.serializeCanvas();
                        this.scheduleProof();
                    }

                    // Update active class on the label
                    const container = radio.closest( '.mnb-font-options-inline' );
                    if ( container ) {
                        container.querySelectorAll( '.mnb-font-option-sm' ).forEach( l =>
                            l.classList.remove( 'mnb-font-option-sm--active', 'mnb-selected' )
                        );
                        radio.closest( '.mnb-font-option-sm' )
                            ?.classList.add( 'mnb-font-option-sm--active', 'mnb-selected' );
                    }
                } );
            } );
        },

        // ══════════════════════════════════════════════════════════════════
        // TEXT INPUT BINDING
        // ══════════════════════════════════════════════════════════════════

        bindInputs() {
            document.querySelectorAll( '.mnb-text-input, .mnb-input:not(select):not(.mnb-epitaph-select):not(.mnb-textarea[id="mnb_design_notes"])' ).forEach( input => {
                const group = input.closest( '.mnb-line-group, .mnb-field-group' );
                if ( ! group ) return;
                const key = group.dataset.line;
                if ( key === 'epitaph' || key === 'design_notes' ) return;

                input.addEventListener( 'input', () => {
                    this.updateTextObject( key, input.value );
                    this.updateCharCount( input );
                    this.toggleFontSelector( group, input.value );
                } );
                this.updateCharCount( input );
            } );
        },

        getOrCreateTextObject( key ) {
            if ( this.textObjects[ key ] ) return this.textObjects[ key ];

            const def  = this.defaults[ key ] || { x_pct: 50, y_pct: 50 };
            const font = this.getEffectiveFontFamily( key );

            const itext = new fabric.IText( '', {
                left:         ( def.x_pct / 100 ) * this.canvasW,
                top:          ( def.y_pct / 100 ) * this.canvasH,
                originX:      'center',
                originY:      'center',
                fontFamily:   font,
                fontSize:     this.defaultFontSize(),
                fill:         '#ffffff',
                // Dark stroke gives contrast on light backgrounds (gray granite)
                // Shadow gives contrast on all backgrounds
                stroke:       'rgba(0,0,0,0.85)',
                strokeWidth:  3,
                paintFirst:   'stroke',   // paint stroke behind fill so letters stay clean
                shadow:       new fabric.Shadow( {
                    color:   'rgba(0,0,0,0.75)',
                    blur:    6,
                    offsetX: 0,
                    offsetY: 0,
                } ),
                textAlign:    'center',
                editable:     false,
                lockRotation: true,
                padding:      6,
                name:         key,
                _controlsVisibility: { mtr: false, ml: false, mr: false, mt: false, mb: false },
            } );

            itext.on( 'moving',   () => this.constrainObject( itext ) );
            itext.on( 'modified', () => { this.serializeCanvas(); this.scheduleProof(); } );

            this.canvas.add( itext );
            this.textObjects[ key ] = itext;
            return itext;
        },

        updateTextObject( key, text ) {
            if ( ! text || ! text.trim() ) {
                this.removeTextObject( key );
                return;
            }
            const obj = this.getOrCreateTextObject( key );
            obj.set( { text: text.trim(), fontFamily: this.getEffectiveFontFamily( key ) } );
            this.canvas.renderAll();
            this.serializeCanvas();
            this.scheduleProof();
        },

        removeTextObject( key ) {
            if ( ! this.textObjects[ key ] ) return;
            this.canvas.remove( this.textObjects[ key ] );
            delete this.textObjects[ key ];
            this.canvas.renderAll();
            this.serializeCanvas();
        },

        constrainObject( obj ) {
            const pad = 8;
            obj.left = Math.min( Math.max( obj.left, pad ), this.canvasW - pad );
            obj.top  = Math.min( Math.max( obj.top,  pad ), this.canvasH - pad );
        },

        defaultFontSize() {
            return Math.max( 14, Math.round( this.canvasW * 0.028 ) );
        },

        // ══════════════════════════════════════════════════════════════════
        // EPITAPH SELECT
        // ══════════════════════════════════════════════════════════════════

        bindEpitaphSelect() {
            const select     = document.getElementById( 'mnb_epitaph_select' );
            const customWrap = document.getElementById( 'mnb-epitaph-custom' );
            const textarea   = document.querySelector( '.mnb-epitaph-text' );
            const finalField = document.querySelector( '.mnb-epitaph-final' );
            const fontSel    = document.querySelector( '.mnb-epitaph-group .mnb-font-selector' );

            if ( ! select ) return;

            const updateEpitaph = ( text ) => {
                if ( finalField ) finalField.value = text;
                this.updateTextObject( 'epitaph', text );
                if ( fontSel ) fontSel.classList.toggle( 'mnb-font-visible', text.length > 0 );
            };

            select.addEventListener( 'change', () => {
                const val = select.value;
                if ( val === '__custom__' ) {
                    if ( customWrap ) customWrap.style.display = 'block';
                    if ( textarea ) { textarea.focus(); updateEpitaph( textarea.value ); }
                } else if ( val === '' ) {
                    if ( customWrap ) customWrap.style.display = 'none';
                    updateEpitaph( '' );
                } else {
                    if ( customWrap ) customWrap.style.display = 'none';
                    updateEpitaph( val );
                }
            } );

            if ( textarea ) {
                textarea.addEventListener( 'input', () => {
                    this.updateCharCount( textarea );
                    updateEpitaph( textarea.value );
                } );
            }
        },

        // ══════════════════════════════════════════════════════════════════
        // DOWNLOAD BUTTON
        // ══════════════════════════════════════════════════════════════════

        bindDownloadButton() {
            // Shortcode renders id="mnb-btn-download" — listen for that ID
            // Use event delegation so it works whether element is in left or right col
            document.addEventListener( 'click', ( e ) => {
                const btn = e.target.closest( '#mnb-btn-download, .mnb-btn-download' );
                if ( ! btn ) return;
                e.preventDefault();
                this.openPreview( btn );
            } );
        },

        openPreview( btn ) {
            if ( ! this.canvas ) return;

            const origHTML  = btn.innerHTML;
            btn.textContent = 'Generating…';
            btn.disabled    = true;

            // Tiny delay so canvas finishes any pending render
            setTimeout( () => {
                const dataURL = this.canvas.toDataURL( { format: 'png', multiplier: 2, quality: 1 } );

                // Open a new tab with the preview image and a download button
                const win = window.open( '', '_blank' );
                if ( win ) {
                    win.document.write( `<!DOCTYPE html>
<html><head><title>Monument Preview</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { background: #1a1a1a; font-family: sans-serif; }
  .page { display: flex; flex-direction: column; align-items: center;
          padding: 24px 16px 40px; gap: 20px; min-height: 100vh; }
  .toolbar { position: sticky; top: 0; z-index: 10; background: rgba(26,26,26,0.95);
             width: 100%; display: flex; align-items: center; justify-content: center;
             gap: 12px; padding: 12px 16px; border-bottom: 1px solid #333; }
  a.btn { display: inline-block; padding: 9px 22px; border: 1px solid #c8a84b;
          color: #c8a84b; border-radius: 4px; text-decoration: none;
          font-size: 14px; font-weight: 600; white-space: nowrap; }
  a.btn:hover { background: #c8a84b; color: #fff; }
  .img-wrap { width: 100%; max-width: 900px; }
  img { width: 100%; height: auto; display: block; border-radius: 4px; }
  p { color: #555; font-size: 12px; text-align: center; }
</style></head>
<body>
  <div class="toolbar">
    <a class="btn" href="${dataURL}" download="monument-preview.png">&#11123; Download PNG</a>
    <a class="btn" href="javascript:window.print()">&#128438; Print</a>
  </div>
  <div class="page">
    <div class="img-wrap">
      <img src="${dataURL}" alt="Monument Preview">
    </div>
    <p>Right-click the image above to save, or use the Download button.</p>
  </div>
</body></html>` );
                    win.document.close();
                } else {
                    // Popup blocked — fallback: direct download
                    const link = document.createElement( 'a' );
                    link.href  = dataURL;
                    link.download = 'monument-preview.png';
                    document.body.appendChild( link );
                    link.click();
                    document.body.removeChild( link );
                }

                btn.innerHTML = origHTML;
                btn.disabled  = false;
            }, 200 );
        },

        // ══════════════════════════════════════════════════════════════════
        // RESET BUTTON
        // ══════════════════════════════════════════════════════════════════

        bindResetButton() {
            document.getElementById( 'mnb-reset-positions' )?.addEventListener( 'click', () => {
                // Reset text object positions
                Object.keys( this.textObjects ).forEach( key => {
                    const obj = this.textObjects[ key ];
                    const def = this.defaults[ key ] || { x_pct: 50, y_pct: 50 };
                    obj.set( {
                        left: ( def.x_pct / 100 ) * this.canvasW,
                        top:  ( def.y_pct / 100 ) * this.canvasH,
                        scaleX: 1, scaleY: 1,
                    } );
                    obj.setCoords();
                } );
                // Also reset uploaded image position to center-left
                if ( this._uploadedImageObj ) {
                    this._uploadedImageObj.set( {
                        left: this.canvasW * 0.25,
                        top:  this.canvasH * 0.5,
                    } );
                    this._uploadedImageObj.setCoords();
                }
                this.canvas.renderAll();
                this.serializeCanvas();
                this.scheduleProof();
            } );
        },

        // ══════════════════════════════════════════════════════════════════
        // VARIATION IMAGE SWAP
        // ══════════════════════════════════════════════════════════════════

        bindVariationSwap() {
            $( 'form.cart' ).on( 'found_variation', ( e, variation ) => {
                if ( ! variation.image || ! variation.image.url ) return;
                const container = document.getElementById( 'mnb-canvas-container' );
                if ( container ) this.loadBackground( variation.image.url, container );
                const $hero = $( '#mnb-hero-img' );
                if ( $hero.length ) $hero.attr( 'src', variation.image.url );
            } );
            $( 'form.cart' ).on( 'reset_data', () => {
                if ( mnbData.canvasImg ) {
                    const container = document.getElementById( 'mnb-canvas-container' );
                    if ( container ) this.loadBackground( mnbData.canvasImg, container );
                }
            } );
        },


        // ── Image Upload Panel (Step 2) ──────────────────────────────────────

        bindUploadPanel() {
            const toggle   = document.getElementById( 'mnb-upload-toggle' );
            const panel    = document.getElementById( 'mnb-upload-panel' );
            const dropzone = document.getElementById( 'mnb-upload-dropzone' );
            const fileInput = document.getElementById( 'mnb-image-upload' );
            const preview  = document.getElementById( 'mnb-upload-preview' );
            const previewImg = document.getElementById( 'mnb-upload-img-preview' );
            const removeBtn = document.getElementById( 'mnb-upload-remove' );
            const errorEl  = document.getElementById( 'mnb-upload-error' );
            const dataField = document.getElementById( 'mnb-custom-image-data' );
            const nameField = document.getElementById( 'mnb-custom-image-name' );

            if ( ! toggle || ! panel ) return;

            // Support both button toggle (shortcode v21+) and checkbox toggle (older)
            const isButton = toggle.tagName === 'BUTTON';
            let panelOpen  = false;

            const openPanel = () => {
                panelOpen = true;
                panel.style.display = 'block';
                panel.setAttribute( 'aria-hidden', 'false' );
                toggle.classList.add( 'mnb-upload-toggle--open' );
            };

            const closePanel = () => {
                panelOpen = false;
                panel.style.display = 'none';
                panel.setAttribute( 'aria-hidden', 'true' );
                toggle.classList.remove( 'mnb-upload-toggle--open' );
                if ( dataField ) dataField.value = '';
                if ( nameField ) nameField.value = '';
                if ( preview )  preview.style.display  = 'none';
                if ( dropzone ) dropzone.style.display = '';
                this.customImage = null;
                this.loadBackground( mnbData.canvasImg,
                    document.getElementById( 'mnb-canvas-container' ) );
            };

            if ( isButton ) {
                toggle.addEventListener( 'click', () => {
                    panelOpen ? closePanel() : openPanel();
                } );
            } else {
                // Checkbox
                toggle.addEventListener( 'change', () => {
                    toggle.checked ? openPanel() : closePanel();
                } );
            }

            if ( ! dropzone ) return;

            // Click dropzone → open file picker
            dropzone.addEventListener( 'click', () => fileInput?.click() );

            // Drag and drop
            dropzone.addEventListener( 'dragover', ( e ) => {
                e.preventDefault();
                dropzone.classList.add( 'mnb-drag-over' );
            } );
            dropzone.addEventListener( 'dragleave', () => dropzone.classList.remove( 'mnb-drag-over' ) );
            dropzone.addEventListener( 'drop', ( e ) => {
                e.preventDefault();
                dropzone.classList.remove( 'mnb-drag-over' );
                const file = e.dataTransfer.files[0];
                if ( file ) this.handleImageFile( file, { dropzone, preview, previewImg, errorEl, dataField, nameField } );
            } );

            // File input change
            fileInput?.addEventListener( 'change', () => {
                const file = fileInput.files[0];
                if ( file ) this.handleImageFile( file, { dropzone, preview, previewImg, errorEl, dataField, nameField } );
            } );

            // Remove button
            removeBtn?.addEventListener( 'click', () => {
                if ( fileInput ) fileInput.value = '';
                if ( dataField ) dataField.value = '';
                if ( nameField ) nameField.value = '';
                if ( preview )  preview.style.display  = 'none';
                if ( dropzone ) dropzone.style.display = '';
                if ( errorEl )  errorEl.style.display  = 'none';
                this.customImage = null;
                this.loadBackground( mnbData.canvasImg,
                    document.getElementById( 'mnb-canvas-container' ) );
            } );
        },

        handleImageFile( file, els ) {
            const { dropzone, preview, previewImg, errorEl, dataField, nameField } = els;

            // Validate PNG
            if ( file.type !== 'image/png' ) {
                if ( errorEl ) {
                    errorEl.textContent = 'Only PNG files are accepted. Please choose a .png file.';
                    errorEl.style.display = 'block';
                }
                return;
            }

            // Max 5MB
            if ( file.size > 5 * 1024 * 1024 ) {
                if ( errorEl ) {
                    errorEl.textContent = 'File is too large. Maximum size is 5MB.';
                    errorEl.style.display = 'block';
                }
                return;
            }

            if ( errorEl ) errorEl.style.display = 'none';

            const reader = new FileReader();
            reader.onload = ( e ) => {
                const dataURL = e.target.result;

                // Show preview
                if ( previewImg ) previewImg.src = dataURL;
                if ( preview )  preview.style.display  = 'flex';
                if ( dropzone ) dropzone.style.display  = 'none';

                // Store in hidden field
                if ( dataField ) dataField.value = dataURL;
                if ( nameField ) nameField.value = file.name;

                // Place image as draggable/resizable object ON the monument preview
                // Do NOT replace the monument background image
                this.customImage = dataURL;
                this.placeUploadedImage( dataURL, 40 );
            };
            reader.readAsDataURL( file );
        },

        // ══════════════════════════════════════════════════════════════════
        // FORM SUBMIT
        // ══════════════════════════════════════════════════════════════════

        bindFormSubmit() {
            // Find the button form (right column) specifically.
            // With two form.cart elements, querySelector returns the first (left/variations).
            // We need to submit from the button form (mnb-cart-button-only).
            const buttonForm = (
                document.querySelector( '.mnb-cart-button-only form.cart' ) ||
                document.querySelector( '.mnb-cart-button-only' )?.closest( 'form' ) ||
                // Fallback: find the form containing the add-to-cart button
                Array.from( document.querySelectorAll( 'form.cart' ) ).find( f =>
                    f.querySelector( '.single_add_to_cart_button' )
                ) ||
                document.querySelector( 'form.cart' )
            );

            if ( ! buttonForm ) return;

            buttonForm.addEventListener( 'submit', ( e ) => {

                // ── Step 1: Validate required MNB fields ─────────────────
                let valid = true;
                document.querySelectorAll( '.mnb-line-group, .mnb-field-group' ).forEach( group => {
                    group.classList.remove( 'mnb-error' );
                    group.querySelector( '.mnb-inline-error' )?.remove();
                    const input = group.querySelector( '.mnb-text-input' );
                    const req   = group.querySelector( '.mnb-required' );
                    if ( req && input && ! input.value.trim() ) {
                        group.classList.add( 'mnb-error' );
                        const err = document.createElement( 'p' );
                        err.className   = 'mnb-inline-error';
                        err.textContent = 'This field is required.';
                        group.appendChild( err );
                        valid = false;
                    }
                } );

                if ( ! valid ) {
                    e.preventDefault();
                    document.querySelector( '.mnb-error' )?.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                    return;
                }

                // ── Step 2: Serialize canvas ──────────────────────────────
                this.serializeCanvas();

                // ── Step 3: Copy ALL MNB fields into form.cart ───────────
                // In Elementor the configurator is outside form.cart,
                // so WooCommerce never receives the field values.
                // We copy every mnb_* input/select/textarea into the form
                // as hidden inputs right before submission.
                this.injectFieldsIntoForm( buttonForm );
            } );
        },

        injectFieldsIntoForm( form ) {
            // Remove any previously injected MNB hidden fields
            form.querySelectorAll( '.mnb-injected' ).forEach( el => el.remove() );

            // Find all MNB inputs outside the form
            const mnbFields = document.querySelectorAll(
                '.mnb-configurator input, .mnb-configurator select, ' +
                '.mnb-configurator textarea, .mnb-canvas-only-wrap input, ' +
                '#mnb-canvas-json, input[name="mnb_layout"], ' +
                'input[name="mnb_global_font"]'
            );

            const seen = new Set();

            mnbFields.forEach( field => {
                const name = field.name;
                if ( ! name || name === '' ) return;
                if ( form.contains( field ) ) return; // already inside form

                // For radio buttons, only inject the checked one
                if ( field.type === 'radio' ) {
                    if ( ! field.checked ) return;
                    if ( seen.has( name ) ) return;
                }

                // Skip the epitaph selector — use the hidden final value instead
                if ( name === 'mnb_epitaph_select' ) return;

                // Skip unchecked checkboxes
                if ( field.type === 'checkbox' && ! field.checked ) return;

                const val = field.value;
                if ( val === '' && field.type !== 'hidden' ) return;

                if ( seen.has( name ) && field.type !== 'radio' ) return;
                seen.add( name );

                const hidden = document.createElement( 'input' );
                hidden.type       = 'hidden';
                hidden.name       = name;
                hidden.value      = val;
                hidden.className  = 'mnb-injected';
                form.appendChild( hidden );
            } );
        },

        // ══════════════════════════════════════════════════════════════════
        // SERIALIZE CANVAS → hidden field
        // ══════════════════════════════════════════════════════════════════

        serializeCanvas() {
            const field = document.getElementById( 'mnb-canvas-json' );
            if ( ! field ) return;

            const data = { layout: mnbData.layout, canvasW: this.canvasW, canvasH: this.canvasH, textLayers: [] };

            document.querySelectorAll( '.mnb-line-group, .mnb-field-group' ).forEach( group => {
                const key   = group.dataset.line;
                const input = document.getElementById( 'mnb_' + key )
                           || document.querySelector( `.mnb-epitaph-final` );
                const text  = key === 'epitaph'
                    ? ( document.querySelector( '.mnb-epitaph-final' )?.value || '' ).trim()
                    : ( input?.value || '' ).trim();
                if ( ! text ) return;

                const radio     = group.querySelector( `input[name="mnb_font_${key}"]:checked` );
                const fontKey   = radio?.value   || this.getGlobalFontKey();
                const fontLabel = mnbData.fonts?.[ fontKey ] || fontKey;

                const obj = this.textObjects[ key ];
                data.textLayers.push( {
                    key, text, fontKey, fontLabel,
                    left_pct: obj ? +( ( obj.left  / this.canvasW ) * 100 ).toFixed(2) : null,
                    top_pct:  obj ? +( ( obj.top   / this.canvasH ) * 100 ).toFixed(2) : null,
                    scaleX:   obj ? +obj.scaleX.toFixed(4) : 1,
                    scaleY:   obj ? +obj.scaleY.toFixed(4) : 1,
                    fontSize: obj ? obj.fontSize : this.defaultFontSize(),
                } );
            } );

            const notesEl = document.getElementById( 'mnb_design_notes' );
            if ( notesEl?.value.trim() ) data.designNotes = notesEl.value.trim();

            field.value = JSON.stringify( data );
        },

        // ══════════════════════════════════════════════════════════════════
        // PROOF (save canvas snapshot to order)
        // ══════════════════════════════════════════════════════════════════

        scheduleProof() {
            clearTimeout( this.proofTimer );
            this.proofTimer = setTimeout( () => this.saveProof(), 1500 );
        },

        saveProof() {
            const orderId = parseInt( document.getElementById( 'mnb-order-id' )?.value || '0', 10 );
            if ( ! orderId ) return;
            const dataURL = this.canvas.toDataURL( { format: 'png', multiplier: 2 } );
            fetch( mnbData.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams( { action: 'mnb_save_proof', nonce: mnbData.nonce, order_id: orderId, image_data: dataURL } ),
            } ).then( r => r.json() ).then( d => {
                if ( d.success ) {
                    const ind = document.getElementById( 'mnb-proof-indicator' );
                    if ( ind ) { ind.textContent = 'Preview saved'; ind.style.color = '#27ae60'; }
                }
            } ).catch( () => {} );
        },

        // ══════════════════════════════════════════════════════════════════
        // HELPERS
        // ══════════════════════════════════════════════════════════════════

        toggleFontSelector( group, text ) {
            // Show per-line font inline when field has content
            const fs  = group.querySelector( '.mnb-font-inline' );
            const req = group.querySelector( '.mnb-required' );
            if ( ! fs ) return;
            if ( req ) return; // always visible for required fields
            fs.classList.toggle( 'mnb-font-inline--visible', text.trim().length > 0 );
        },

        updateCharCount( input ) {
            const group   = input.closest( '.mnb-line-group, .mnb-field-group' );
            const counter = group?.querySelector( '.mnb-char-count' );
            if ( ! counter ) return;
            const max = parseInt( counter.dataset.max, 10 );
            const cur = input.value.length;
            counter.textContent = cur + '/' + max;
            counter.classList.toggle( 'mnb-at-limit',   cur >= max );
            counter.classList.toggle( 'mnb-near-limit', cur >= max * 0.85 && cur < max );
        },

        handleResize( container ) {
            clearTimeout( this._resizeTimer );
            this._resizeTimer = setTimeout( () => {
                const newW = container.offsetWidth;
                if ( ! newW || newW === this.canvasW ) return;
                const aspect = this.canvasH / ( this.canvasW || 1 );
                const newH   = Math.round( newW * aspect );
                const ratio  = newW / this.canvasW;

                this.canvas.setDimensions( { width: newW, height: newH } );
                this.canvas.getObjects().forEach( obj => {
                    if ( obj.name && obj.name !== 'divider' ) {
                        obj.set( { left: obj.left * ratio, top: obj.top * ( newH / this.canvasH ) } );
                        obj.setCoords();
                    }
                } );
                this.canvasW = newW;
                this.canvasH = newH;
                if ( mnbData.layout === 'double' ) this.drawDivider();
                this.canvas.renderAll();
                this.serializeCanvas();
            }, 200 );
        },
    };

    $( document ).ready( () => {
        MNB.init();
        window.MNBCanvas = MNB;
    } );

} )( jQuery, typeof fabric !== 'undefined' ? fabric : null );
