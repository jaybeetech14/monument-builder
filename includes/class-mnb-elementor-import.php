<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Elementor_Import v28
 *
 * Provides the admin UI guide for manually building the Elementor template.
 * The programmatic template creation has been removed — Elementor Pro's
 * internal document system cannot be reliably bypassed via PHP hooks.
 * Manual creation via Theme Builder takes ~5 minutes and always works.
 */
class MNB_Elementor_Import {

    public static function init() {
        // No admin_post action needed — button removed
    }

    public static function elementor_active() {
        return defined( 'ELEMENTOR_VERSION' ) && class_exists( '\Elementor\Plugin' );
    }

    /**
     * Render the template setup guide in the Monument Builder dashboard.
     */
    public static function render_import_button() {
        if ( ! self::elementor_active() ) {
            echo '<div class="notice notice-warning inline" style="margin:16px 0;padding:10px 16px;">
                <p><strong>Elementor not detected.</strong> Activate Elementor to build the monument template.</p>
            </div>';
            return;
        }

        $theme_builder_url = admin_url( 'admin.php?page=elementor#tab-theme_builder' );
        ?>
        <div style="background:#fff8e1;border:1px solid #c8a84b;border-radius:6px;padding:20px 24px;margin-top:20px;">

            <h3 style="margin:0 0 6px;color:#2f2f2f;font-size:15px;">🎨 Elementor Template Setup</h3>
            <p style="margin:0 0 16px;font-size:13px;color:#555;">
                Build the 2-column monument product page template manually in Elementor Theme Builder.
                Takes about 5 minutes. Follow the steps below exactly.
            </p>

            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <?php
                $steps = [
                    [
                        'label' => 'Open Theme Builder',
                        'desc'  => 'Go to <strong>Templates → Theme Builder</strong> in your WordPress admin. Click <strong>Single Product</strong> in the left sidebar. Click <strong>+ Add New</strong>.',
                    ],
                    [
                        'label' => 'Name &amp; create',
                        'desc'  => 'Name it <strong>Monument Product Page</strong>. Click <strong>Create Template</strong>. Close the template library popup.',
                    ],
                    [
                        'label' => 'Set to full width',
                        'desc'  => 'Click <strong>+ Add Container</strong>. Choose the <strong>Flexbox Row</strong> layout. In the container settings → Layout tab: set Width to <strong>Full Width</strong> and enable <strong>Stretch Container</strong>.',
                    ],
                    [
                        'label' => 'Add two inner containers',
                        'desc'  => 'Click <strong>+</strong> inside the outer container twice to add two child containers. Set each to <strong>Column</strong> direction.',
                    ],
                    [
                        'label' => 'Set column widths',
                        'desc'  => 'Left container → Layout → Width: <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;">38%</code>. Right container → Width: <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;">62%</code>.',
                    ],
                    [
                        'label' => 'Make left column sticky',
                        'desc'  => 'Click left container → <strong>Advanced → Motion Effects → Sticky: Top</strong>. Offset: <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;">30</code>. Enable on Desktop &amp; Tablet only.',
                    ],
                    [
                        'label' => 'Left column widget',
                        'desc'  => 'Add one widget to the left container: <strong>Monument Canvas Preview</strong> (search for it in the MNB category).',
                    ],
                    [
                        'label' => 'Right column widgets',
                        'desc'  => 'Add these widgets to the right container in order:<br>
                            1. <strong>Product Title</strong><br>
                            2. <strong>Product Price</strong><br>
                            3. <strong>Short Description</strong><br>
                            4. <strong>Product Images</strong> (CSS class: <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;">mnb-product-gallery-right</code>)<br>
                            5. <strong>Add to Cart</strong> (CSS class: <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;">mnb-variations-only</code>)<br>
                            6. <strong>Monument Engraving Fields</strong> → enable <em>Hide Canvas</em> toggle<br>
                            7. <strong>Add to Cart</strong> (CSS class: <code style="background:#f0f0f0;padding:1px 4px;border-radius:3px;">mnb-cart-button-only</code>)<br>
                            8. <strong>Product Meta</strong>',
                    ],
                    [
                        'label' => 'Publish &amp; set conditions',
                        'desc'  => 'Click <strong>Publish</strong>. In the conditions dialog: <strong>Include → Products → In Category</strong> → select your monument categories (Flats, Uprights, Bevels etc). Click <strong>Save &amp; Close</strong>.',
                    ],
                ];

                foreach ( $steps as $i => $step ) :
                    $n = $i + 1;
                    $bg = $n % 2 === 0 ? '#fafafa' : '#fff';
                    ?>
                    <tr style="background:<?php echo $bg; ?>;border-bottom:1px solid #f0f0f0;">
                        <td style="padding:10px 12px;width:28px;vertical-align:top;">
                            <span style="display:inline-flex;width:22px;height:22px;border-radius:50%;background:#c8a84b;color:#fff;align-items:center;justify-content:center;font-size:11px;font-weight:700;">
                                <?php echo $n; ?>
                            </span>
                        </td>
                        <td style="padding:10px 0 10px 4px;vertical-align:top;font-weight:700;white-space:nowrap;width:160px;">
                            <?php echo $step['label']; ?>
                        </td>
                        <td style="padding:10px 12px;vertical-align:top;color:#555;line-height:1.6;">
                            <?php echo $step['desc']; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <p style="margin:14px 0 0;font-size:12px;color:#999;border-top:1px solid #ecdab0;padding-top:10px;">
                💡 The <strong>Monument Canvas Preview</strong> and <strong>Monument Engraving Fields</strong> widgets appear in the Elementor widget panel under the <em>General</em> category once the Monument Builder plugin is active.
            </p>

        </div>
        <?php
    }
}
