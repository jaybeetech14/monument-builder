<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Shortcode v21
 *
 * Two shortcodes:
 *   [mnb_canvas_preview]    — canvas preview only (left column)
 *   [mnb_engraving_fields]  — all configuration steps (right column)
 *
 * Layout mirrors Signature Headstones reference:
 *   STEP 1 — Monument type + Font selection (visual grid)
 *   STEP 2 — Text fields + Upload your own image (accordion)
 *   STEP 3 — Design Adjustments
 */
class MNB_Shortcode {

    public static function init() {
        add_shortcode( 'mnb_engraving_fields', [ __CLASS__, 'render' ] );
        add_shortcode( 'mnb_canvas_preview',   [ __CLASS__, 'render_canvas_only' ] );
        add_action( 'elementor/widgets/register', [ __CLASS__, 'register_elementor_widget' ] );
    }

    // ══════════════════════════════════════════════════════════════════════
    // [mnb_canvas_preview] — left column, preview only
    // ══════════════════════════════════════════════════════════════════════

    public static function render_canvas_only( $atts ) {
        $atts       = shortcode_atts( [ 'product_id' => 0 ], $atts, 'mnb_canvas_preview' );
        $product_id = absint( $atts['product_id'] ) ?: get_the_ID();
        if ( ! $product_id || ! MNB_Product_Fields::product_is_monument( $product_id ) ) return '';

        self::enqueue_assets( $product_id );

        $canvas_img = MNB_Canvas::get_canvas_image( $product_id );
        $layout     = MNB_Canvas::get_layout( $product_id );
        $is_double  = ( $layout === 'double' );

        ob_start(); ?>
        <div class="mnb-canvas-only-wrap"
             data-product-id="<?php echo esc_attr( $product_id ); ?>"
             data-layout="<?php echo esc_attr( $layout ); ?>">

            <?php if ( $is_double ) : ?>
                <p class="mnb-layout-badge">
                    <span class="mnb-badge mnb-badge-double">&#9632;&#9632; Double Monument</span>
                </p>
            <?php endif; ?>

            <div class="mnb-canvas-wrap">
                <div class="mnb-canvas-container" id="mnb-canvas-container">
                    <?php if ( $canvas_img ) : ?>
                        <img src="<?php echo esc_url( $canvas_img ); ?>"
                             id="mnb-bg-img" class="mnb-canvas-bg"
                             alt="Monument preview" crossorigin="anonymous">
                    <?php else : ?>
                        <div class="mnb-canvas-placeholder">Monument image will appear here</div>
                    <?php endif; ?>
                    <canvas id="mnb-canvas"></canvas>
                </div>
                <div class="mnb-canvas-toolbar">
                    <button type="button" class="mnb-tool-btn" id="mnb-reset-positions">
                        &#8634; Reset
                    </button>
                    <button type="button" class="mnb-btn-download" id="mnb-btn-download">
                        &#11123; View / Download
                    </button>
                    <?php if ( $is_double ) : ?>
                        <span class="mnb-toolbar-hint">Left = Person 1 &nbsp;|&nbsp; Right = Person 2</span>
                    <?php endif; ?>
                    <span id="mnb-proof-indicator"></span>
                </div>
                <p class="mnb-canvas-hint">
                    &#128161; <strong>Drag</strong> to reposition &nbsp;&middot;&nbsp;
                    <strong>Corner handles</strong> to resize
                </p>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    // ══════════════════════════════════════════════════════════════════════
    // [mnb_engraving_fields] — right column, all steps
    // ══════════════════════════════════════════════════════════════════════

    public static function render( $atts ) {
        $atts = shortcode_atts( [
            'product_id'  => 0,
            'show_title'  => 'no',
            'title'       => 'Personalize Your Monument',
            'hide_canvas' => 'yes',
        ], $atts, 'mnb_engraving_fields' );

        $product_id = absint( $atts['product_id'] ) ?: get_the_ID();
        if ( ! $product_id || ! MNB_Product_Fields::product_is_monument( $product_id ) ) return '';

        self::enqueue_assets( $product_id );

        $layout     = MNB_Canvas::get_layout( $product_id );
        $fields     = MNB_Canvas::get_fields( $layout );
        $fonts      = MNB_Product_Fields::get_fonts();
        $is_double  = ( $layout === 'double' );
        $presets    = MNB_Epitaph::get_presets();

        // Detect bronze products — font selection is not applicable for bronze
        // Checks product category names and the product's own name/type
        $is_bronze  = false;
        $cats       = get_the_terms( $product_id, 'product_cat' );
        if ( $cats && ! is_wp_error( $cats ) ) {
            foreach ( $cats as $cat ) {
                if ( stripos( $cat->name, 'bronze' ) !== false || stripos( $cat->slug, 'bronze' ) !== false ) {
                    $is_bronze = true;
                    break;
                }
            }
        }

        ob_start(); ?>
        <div class="mnb-configurator mnb-layout-<?php echo esc_attr( $layout ); ?>"
             id="mnb-configurator"
             data-product-id="<?php echo esc_attr( $product_id ); ?>"
             data-layout="<?php echo esc_attr( $layout ); ?>"
             <?php if ( $is_bronze ) echo 'data-hide-font="1"'; ?>>

            <?php if ( $is_double ) : ?>
                <p class="mnb-layout-badge" style="margin:0 0 12px;">
                    <span class="mnb-badge mnb-badge-double">&#9632;&#9632; Double Monument</span>
                </p>
            <?php endif; ?>

            <?php if ( 'yes' === $atts['show_title'] ) : ?>
                <h3 class="mnb-config-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <?php endif; ?>

            <?php if ( 'yes' !== $atts['hide_canvas'] ) : ?>
            <div class="mnb-canvas-wrap mnb-canvas-inline">
                <div class="mnb-canvas-container" id="mnb-canvas-container">
                    <?php
                    $canvas_img = MNB_Canvas::get_canvas_image( $product_id );
                    if ( $canvas_img ) : ?>
                        <img src="<?php echo esc_url( $canvas_img ); ?>"
                             id="mnb-bg-img" class="mnb-canvas-bg"
                             alt="Monument preview" crossorigin="anonymous">
                    <?php else : ?>
                        <div class="mnb-canvas-placeholder">Monument image will appear here</div>
                    <?php endif; ?>
                    <canvas id="mnb-canvas"></canvas>
                </div>
                <div class="mnb-canvas-toolbar">
                    <button type="button" class="mnb-tool-btn" id="mnb-reset-positions">&#8634; Reset</button>
                    <button type="button" class="mnb-btn-download" id="mnb-btn-download">&#11123; View / Download</button>
                    <span id="mnb-proof-indicator"></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- ══════════════════════════════════════════
                 STEP 1 — Select Engraving Style
            ══════════════════════════════════════════ -->
            <?php
            // Render variation selectors as Step 1 only if this is a variable product
            $wc_product = wc_get_product( $product_id );
            if ( $wc_product && $wc_product->is_type( 'variable' ) ) :
            ?>
            <div class="mnb-step-card" id="mnb-step-engravings">
                <div class="mnb-step-card-header">
                    <span class="mnb-step-num-tag">STEP 1</span>
                    <span class="mnb-step-card-title">Select Your Options</span>
                </div>
                <div class="mnb-step-card-body mnb-engravings-body">
                    <div class="mnb-engravings-slot" id="mnb-engravings-slot">
                        <?php
                        // Render the WC variation attributes table directly
                        $attributes = $wc_product->get_variation_attributes();
                        if ( ! empty( $attributes ) ) :
                        ?>
                        <table class="variations mnb-variations-table" cellspacing="0">
                            <tbody>
                            <?php foreach ( $attributes as $attribute_name => $options ) :
                                $attr_label = wc_attribute_label( $attribute_name );
                            ?>
                                <tr>
                                    <td class="label">
                                        <label for="<?php echo esc_attr( sanitize_title( $attribute_name ) ); ?>">
                                            <?php echo esc_html( $attr_label ); ?>
                                        </label>
                                    </td>
                                    <td class="value">
                                        <?php
                                        wc_dropdown_variation_attribute_options( [
                                            'options'   => $options,
                                            'attribute' => $attribute_name,
                                            'product'   => $wc_product,
                                        ] );
                                        ?>
                                        <a class="reset_variations" href="#"><?php esc_html_e( 'Clear', 'woocommerce' ); ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Monument type hidden field — always rendered regardless of bronze -->
            <input type="hidden" name="mnb_monument_type"
                   id="mnb-monument-type-input"
                   value="<?php echo esc_attr( $layout ); ?>">

            <?php if ( ! $is_bronze ) : ?>
            <!-- ══════════════════════════════════════════
                 STEP 2 — Font Selection (hidden for bronze)
            ══════════════════════════════════════════ -->
            <div class="mnb-step-card">
                <div class="mnb-step-card-header">
                    <span class="mnb-step-num-tag">STEP 2</span>
                </div>
                <div class="mnb-step-card-body">

                    <!-- Font selection — visual grid -->
                    <div class="mnb-field-block">
                        <label class="mnb-field-label">Select Font</label>
                        <div class="mnb-font-grid" id="mnb-font-grid">
                            <?php $first = true; foreach ( $fonts as $font_key => $font_name ) :
                                $img_id   = get_option( 'mnb_font_image_' . $font_key );
                                $css_font = self::css_font( $font_key );
                            ?>
                            <label class="mnb-font-tile<?php echo $first ? ' mnb-font-tile--active' : ''; ?>"
                                   data-font-key="<?php echo esc_attr( $font_key ); ?>"
                                   data-css-font="<?php echo esc_attr( $css_font ); ?>">
                                <input type="radio"
                                       name="mnb_global_font"
                                       value="<?php echo esc_attr( $font_key ); ?>"
                                       data-css-font="<?php echo esc_attr( $css_font ); ?>"
                                       data-font-label="<?php echo esc_attr( $font_name ); ?>"
                                       <?php echo $first ? 'checked' : ''; ?>
                                       style="position:absolute;opacity:0;width:0;height:0;">
                                <?php if ( $img_id ) : ?>
                                    <?php echo wp_get_attachment_image( $img_id, [ 120, 50 ], false, [ 'class' => 'mnb-font-tile-img' ] ); ?>
                                <?php else : ?>
                                    <span class="mnb-font-tile-sample"
                                          style="font-family:<?php echo esc_attr( $css_font ); ?>">Abc</span>
                                <?php endif; ?>
                                <span class="mnb-font-tile-name"><?php echo esc_html( $font_name ); ?></span>
                            </label>
                            <?php $first = false; endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
            <?php endif; // ! $is_bronze — font step ?>

            <!-- ══════════════════════════════════════════
                 STEP 3 — Text Fields + Upload
            ══════════════════════════════════════════ -->
            <div class="mnb-step-card">
                <div class="mnb-step-card-header">
                    <span class="mnb-step-num-tag"><?php echo $is_bronze ? 'STEP 2' : 'STEP 3'; ?></span>
                    <span class="mnb-step-card-title">Enter Your Text</span>
                </div>
                <div class="mnb-step-card-body">

                    <?php if ( $is_double ) : ?>
                    <!-- Double: three-column grid -->
                    <div class="mnb-double-grid">
                        <div class="mnb-col mnb-col-shared">
                            <h4 class="mnb-col-heading">Shared</h4>
                            <?php foreach ( $fields as $f ) {
                                if ( $f['group'] === 'shared' && $f['key'] !== 'epitaph' ) {
                                    self::render_field( $f, $fonts );
                                }
                            } ?>
                        </div>
                        <div class="mnb-col mnb-col-left">
                            <h4 class="mnb-col-heading">&#9664; Person 1</h4>
                            <?php foreach ( $fields as $f ) {
                                if ( $f['group'] === 'person1' ) self::render_field( $f, $fonts );
                            } ?>
                        </div>
                        <div class="mnb-col mnb-col-right">
                            <h4 class="mnb-col-heading">Person 2 &#9654;</h4>
                            <?php foreach ( $fields as $f ) {
                                if ( $f['group'] === 'person2' ) self::render_field( $f, $fonts );
                            } ?>
                        </div>
                    </div>
                    <?php else : ?>
                    <!-- Single: stacked fields -->
                    <?php foreach ( $fields as $f ) :
                        if ( $f['key'] === 'epitaph' ) continue;
                        self::render_field( $f, $fonts );
                    endforeach; ?>
                    <?php endif; ?>

                    <!-- Epitaph -->
                    <div class="mnb-epitaph-section">
                        <?php self::render_epitaph_field( $fonts, $presets, 'epitaph' ); ?>
                    </div>

                    <!-- Upload Your Own Image — accordion toggle -->
                    <div class="mnb-upload-block" id="mnb-upload-block">
                        <button type="button" class="mnb-upload-toggle" id="mnb-upload-toggle"
                                aria-expanded="false">
                            <span class="mnb-upload-toggle-icon">&#43;</span>
                            <span>Upload Your Own Image</span>
                            <span class="mnb-upload-toggle-sub">PNG only &middot; Optional</span>
                        </button>
                        <div class="mnb-upload-panel" id="mnb-upload-panel" aria-hidden="true">
                            <div class="mnb-upload-inner">
                                <p class="mnb-upload-desc">
                                    Upload a PNG image to appear on the monument preview.
                                    Only <strong>PNG</strong> files are accepted.
                                </p>
                                <div class="mnb-upload-dropzone" id="mnb-upload-dropzone">
                                    <input type="file"
                                           id="mnb-upload-input"
                                           name="mnb_upload_image"
                                           accept="image/png"
                                           class="mnb-upload-file-input">
                                    <div class="mnb-upload-dropzone-ui" id="mnb-upload-dropzone-ui">
                                        <span class="mnb-upload-icon">&#128444;</span>
                                        <p class="mnb-upload-cta">Click to select or drag &amp; drop a PNG</p>
                                        <p class="mnb-upload-formats">PNG only &middot; Max 5 MB</p>
                                    </div>
                                    <div class="mnb-upload-preview" id="mnb-upload-preview" style="display:none;align-items:center;gap:12px;">
                                        <img id="mnb-upload-thumb" src="" alt="Uploaded image preview"
                                             style="max-height:60px;max-width:80px;object-fit:contain;border-radius:3px;border:1px solid #ddd;">
                                        <div>
                                            <p class="mnb-upload-filename" id="mnb-upload-filename" style="font-size:12px;color:#555;margin:0 0 4px;"></p>
                                            <button type="button" class="mnb-upload-remove mnb-btn-sm" id="mnb-upload-remove">
                                                &#10005; Remove Image
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p class="mnb-upload-note" id="mnb-upload-note"></p>
                                <!-- Position controls shown after upload -->
                                <div class="mnb-upload-controls" id="mnb-upload-controls" style="display:none;">
                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                                        <span id="mnb-upload-count" style="font-size:13px;color:#666;">0 images on canvas</span>
                                        <button type="button" class="mnb-btn-sm" id="mnb-upload-add-another"
                                                style="display:none;">
                                            &#43; Add Another Image
                                        </button>
                                        <button type="button" class="mnb-btn-sm mnb-btn-danger" id="mnb-upload-remove-last"
                                                style="display:none;">
                                            &#10005; Remove Last
                                        </button>
                                    </div>
                                    <label class="mnb-field-label">
                                        Image Size on Monument
                                        <span id="mnb-upload-size-val">30%</span>
                                    </label>
                                    <input type="range" id="mnb-upload-size" min="10" max="80" value="30" step="1"
                                           class="mnb-upload-range">
                                    <label class="mnb-toggle-label" style="margin-top:10px;">
                                        <input type="checkbox" id="mnb-upload-invert" class="mnb-toggle-input">
                                        <span class="mnb-toggle-switch"></span>
                                        <span class="mnb-toggle-text" style="font-size:13px;">
                                            Invert colours (white on dark stone)
                                        </span>
                                    </label>
                                    <p style="font-size:12px;color:#999;margin:8px 0 0;">
                                        Each image is draggable and resizable on the canvas. Max 5 images.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 STEP 4 — Design Adjustments
            ══════════════════════════════════════════ -->
            <div class="mnb-step-card">
                <div class="mnb-step-card-header">
                    <span class="mnb-step-num-tag"><?php echo $is_bronze ? 'STEP 3' : 'STEP 4'; ?></span>
                    <span class="mnb-step-card-title">Design Adjustments <em>(Optional)</em></span>
                </div>
                <div class="mnb-step-card-body">
                    <div class="mnb-field-block">
                        <label class="mnb-field-label" for="mnb_design_notes">
                            Special Instructions
                            <span class="mnb-char-count" data-max="300">0/300</span>
                        </label>
                        <textarea id="mnb_design_notes"
                                  name="mnb_design_notes"
                                  class="mnb-input mnb-textarea"
                                  rows="3"
                                  maxlength="300"
                                  placeholder="Include any specific design requests. Our team will review and contact you before production."></textarea>
                    </div>
                </div>
            </div>

            <!-- Hidden state fields -->
            <input type="hidden" name="mnb_canvas_json" id="mnb-canvas-json" value="">
            <input type="hidden" name="mnb_layout"      id="mnb-layout-input" value="<?php echo esc_attr( $layout ); ?>">

            <p class="mnb-note">
                * Required field. Text is subject to monument size limitations.
                Our team will contact you before production if adjustments are needed.
            </p>

        </div><!-- .mnb-configurator -->
        <?php
        return ob_get_clean();
    }

    // ── Epitaph field with preset dropdown ───────────────────────────────────

    private static function render_epitaph_field( $fonts, $presets, $key ) { ?>
        <div class="mnb-field-block mnb-epitaph-group" data-line="<?php echo esc_attr( $key ); ?>">
            <label class="mnb-field-label" for="mnb_epitaph_select">Select Epitaph</label>
            <select id="mnb_epitaph_select" class="mnb-input mnb-epitaph-select" name="mnb_epitaph_select">
                <option value="">-- Choose an option --</option>
                <?php foreach ( $presets as $preset ) : ?>
                    <option value="<?php echo esc_attr( $preset ); ?>"><?php echo esc_html( $preset ); ?></option>
                <?php endforeach; ?>
                <option value="__custom__">&#9999;&#xFE0F; Write Your Own</option>
            </select>

            <div class="mnb-epitaph-custom" id="mnb-epitaph-custom" style="display:none;margin-top:8px;">
                <label class="mnb-field-label" for="mnb_epitaph_custom_text">
                    Write Your Epitaph
                    <span class="mnb-char-count" data-max="80">0/80</span>
                </label>
                <textarea id="mnb_epitaph_custom_text"
                          class="mnb-input mnb-textarea mnb-epitaph-text"
                          maxlength="80" rows="2"
                          placeholder="Enter your custom epitaph..."></textarea>
            </div>

            <input type="hidden" id="mnb_<?php echo esc_attr( $key ); ?>"
                   name="mnb_<?php echo esc_attr( $key ); ?>"
                   class="mnb-epitaph-final">

            <div class="mnb-font-inline" data-for="<?php echo esc_attr( $key ); ?>" style="display:none;margin-top:8px;">
                <span class="mnb-field-label-sm">Epitaph Font:</span>
                <div class="mnb-font-options-inline">
                    <?php $first = true; foreach ( $fonts as $fk => $fn ) :
                        $iid    = 'mnb_font_' . $key . '_' . $fk;
                        $img_id = get_option( 'mnb_font_image_' . $fk );
                    ?>
                    <label class="mnb-font-option-sm" for="<?php echo esc_attr( $iid ); ?>">
                        <input type="radio"
                               id="<?php echo esc_attr( $iid ); ?>"
                               name="mnb_font_<?php echo esc_attr( $key ); ?>"
                               value="<?php echo esc_attr( $fk ); ?>"
                               data-font-label="<?php echo esc_attr( $fn ); ?>"
                               data-css-font="<?php echo esc_attr( self::css_font( $fk ) ); ?>"
                               <?php echo $first ? 'checked' : ''; ?>>
                        <?php if ( $img_id ) {
                            echo wp_get_attachment_image( $img_id, [ 80, 28 ], false, [ 'class' => 'mnb-font-img-sm' ] );
                        } else { ?>
                            <span style="font-family:<?php echo esc_attr( self::css_font( $fk ) ); ?>;font-size:13px;">
                                <?php echo esc_html( $fn ); ?>
                            </span>
                        <?php } ?>
                    </label>
                    <?php $first = false; endforeach; ?>
                </div>
            </div>
        </div>
    <?php }

    // ── Standard text field ───────────────────────────────────────────────────

    private static function render_field( $field, $fonts ) {
        $key      = $field['key'];
        $label    = $field['label'];
        $required = ! empty( $field['required'] );
        $max      = $field['max'] ?? 80;
        $textarea = ! empty( $field['textarea'] );
        ?>
        <div class="mnb-field-block mnb-line-group"
             data-line="<?php echo esc_attr( $key ); ?>"
             data-side="<?php echo esc_attr( $field['side'] ?? '' ); ?>">

            <label class="mnb-field-label" for="mnb_<?php echo esc_attr( $key ); ?>">
                <?php echo esc_html( $label ); ?>
                <?php if ( $required ) echo '<span class="mnb-required">*</span>'; ?>
                <span class="mnb-char-count" data-max="<?php echo esc_attr( $max ); ?>">0/<?php echo esc_attr( $max ); ?></span>
            </label>

            <?php if ( $textarea ) : ?>
                <textarea id="mnb_<?php echo esc_attr( $key ); ?>"
                          name="mnb_<?php echo esc_attr( $key ); ?>"
                          class="mnb-input mnb-textarea"
                          maxlength="<?php echo esc_attr( $max ); ?>"
                          rows="2"
                          <?php echo $required ? 'required' : ''; ?>
                          placeholder="Optional..."></textarea>
            <?php else : ?>
                <input type="text"
                       id="mnb_<?php echo esc_attr( $key ); ?>"
                       name="mnb_<?php echo esc_attr( $key ); ?>"
                       class="mnb-input"
                       maxlength="<?php echo esc_attr( $max ); ?>"
                       <?php echo $required ? 'required' : ''; ?>
                       placeholder="<?php echo $required ? 'Required' : 'Optional'; ?>">
            <?php endif; ?>

            <!-- Per-line font override (compact) -->
            <div class="mnb-font-inline<?php echo $required ? ' mnb-font-inline--visible' : ''; ?>"
                 data-for="<?php echo esc_attr( $key ); ?>">
                <span class="mnb-field-label-sm">Font:</span>
                <div class="mnb-font-options-inline">
                    <?php $first = true; foreach ( $fonts as $fk => $fn ) :
                        $iid    = 'mnb_font_' . $key . '_' . $fk;
                        $img_id = get_option( 'mnb_font_image_' . $fk );
                    ?>
                    <label class="mnb-font-option-sm<?php echo $first ? ' mnb-font-option-sm--active' : ''; ?>"
                           for="<?php echo esc_attr( $iid ); ?>">
                        <input type="radio"
                               id="<?php echo esc_attr( $iid ); ?>"
                               name="mnb_font_<?php echo esc_attr( $key ); ?>"
                               value="<?php echo esc_attr( $fk ); ?>"
                               data-font-label="<?php echo esc_attr( $fn ); ?>"
                               data-css-font="<?php echo esc_attr( self::css_font( $fk ) ); ?>"
                               <?php echo $first ? 'checked' : ''; ?>>
                        <?php if ( $img_id ) {
                            echo wp_get_attachment_image( $img_id, [ 80, 28 ], false, [ 'class' => 'mnb-font-img-sm' ] );
                        } else { ?>
                            <span style="font-family:<?php echo esc_attr( self::css_font( $fk ) ); ?>;font-size:13px;">
                                <?php echo esc_html( $fn ); ?>
                            </span>
                        <?php } ?>
                    </label>
                    <?php $first = false; endforeach; ?>
                </div>
            </div>

        </div>
    <?php }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function css_font( $key ) {
        $map = [
            'sunk_roman'         => 'Times New Roman, Times, serif',
            'vermarco_script'   => 'Georgia, serif',
            'spacerite_vermarco' => 'Georgia, serif',
        ];
        return $map[ $key ] ?? 'serif';
    }

    private static function get_font_urls() {
        $fonts = MNB_Product_Fields::get_fonts();
        $urls  = [];
        foreach ( $fonts as $key => $label ) {
            $att_id = get_option( 'mnb_font_file_' . $key );
            if ( $att_id ) {
                $url = wp_get_attachment_url( $att_id );
                if ( $url ) $urls[ $key ] = $url;
            }
        }
        return $urls;
    }

    public static function enqueue_assets( $product_id ) {
        if ( ! wp_script_is( 'fabricjs', 'enqueued' ) ) {
            wp_enqueue_script( 'fabricjs',
                'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js',
                [], '5.3.1', true );
        }

        wp_enqueue_style(  'mnb-frontend', MNB_PLUGIN_URL . 'assets/css/mnb-frontend.css', [], MNB_VERSION );
        wp_enqueue_script( 'mnb-canvas',   MNB_PLUGIN_URL . 'assets/js/mnb-canvas.js', [ 'jquery', 'fabricjs' ], MNB_VERSION, true );

        $layout = MNB_Canvas::get_layout( $product_id );
        wp_localize_script( 'mnb-canvas', 'mnbData', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'mnb_nonce' ),
            'fonts'     => MNB_Product_Fields::get_fonts(),
            'fields'    => MNB_Canvas::get_fields( $layout ),
            'layout'    => $layout,
            'productId' => $product_id,
            'canvasImg' => MNB_Canvas::get_canvas_image( $product_id ),
            'fontUrls'  => self::get_font_urls(),
            'fontMap'   => [
                'sunk_roman'      => 'Times New Roman, Times, serif',
                'vermarco_script' => 'Georgia, serif',
            ],
        ] );
    }

    // ── Elementor widgets ─────────────────────────────────────────────────────

    public static function register_elementor_widget( $widgets_manager ) {
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) return;

        $fields_widget = new class extends \Elementor\Widget_Base {
            public function get_name()       { return 'mnb_engraving_fields'; }
            public function get_title()      { return 'Monument Engraving Fields'; }
            public function get_icon()       { return 'eicon-form-horizontal'; }
            public function get_categories() { return [ 'general' ]; }

            protected function register_controls() {
                $this->start_controls_section( 's', [ 'label' => 'Settings', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ] );
                $this->add_control( 'show_title', [ 'label' => 'Show Title', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'no' ] );
                $this->add_control( 'title',      [ 'label' => 'Title', 'type' => \Elementor\Controls_Manager::TEXT, 'default' => 'Personalize Your Monument', 'condition' => [ 'show_title' => 'yes' ] ] );
                $this->add_control( 'hide_canvas', [ 'label' => 'Hide Canvas (preview in left col)', 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes' ] );
                $this->end_controls_section();
            }

            protected function render() {
                $s = $this->get_settings_for_display();
                echo do_shortcode( sprintf( '[mnb_engraving_fields show_title="%s" title="%s" hide_canvas="%s"]',
                    esc_attr( $s['show_title'] ?? 'no' ),
                    esc_attr( $s['title'] ?? 'Personalize Your Monument' ),
                    esc_attr( $s['hide_canvas'] ?? 'yes' )
                ) );
            }
        };

        $canvas_widget = new class extends \Elementor\Widget_Base {
            public function get_name()       { return 'mnb_canvas_preview'; }
            public function get_title()      { return 'Monument Canvas Preview'; }
            public function get_icon()       { return 'eicon-image'; }
            public function get_categories() { return [ 'general' ]; }

            protected function register_controls() {
                $this->start_controls_section( 's', [ 'label' => 'Info', 'tab' => \Elementor\Controls_Manager::TAB_CONTENT ] );
                $this->add_control( 'info', [ 'type' => \Elementor\Controls_Manager::RAW_HTML, 'raw' => 'Live monument canvas preview. Place in left (sticky) column.' ] );
                $this->end_controls_section();
            }

            protected function render() {
                echo do_shortcode( '[mnb_canvas_preview]' );
            }
        };

        $widgets_manager->register( $fields_widget );
        $widgets_manager->register( $canvas_widget );
    }
}
