<?php
defined( 'ABSPATH' ) || exit;

/**
 * MNB_Epitaph
 *
 * Manages the list of preset epitaphs available in the
 * "Select Epitaph" dropdown on the product configurator.
 *
 * Presets are stored in wp_options as an ordered array.
 * The last option is always "Write Your Own" which reveals
 * a free-text textarea.
 */
class MNB_Epitaph {

    const OPTION_KEY = 'mnb_epitaph_presets';

    public static function init() {
        add_action( 'admin_post_mnb_save_epitaphs', [ __CLASS__, 'save_presets' ] );
    }

    /**
     * Get the current list of preset epitaphs.
     * Returns an array of strings.
     */
    public static function get_presets() {
        $defaults = [
            'Forever In Our Hearts',
            'Always Remembered, Never Forgotten',
            'Rest In Eternal Peace',
            'Beloved Husband, Father & Friend',
            'Beloved Wife, Mother & Friend',
            'Gone But Never Forgotten',
            'In Loving Memory',
            'Until We Meet Again',
            'A Life Well Lived',
            'Loved Beyond Measure',
        ];
        return get_option( self::OPTION_KEY, $defaults );
    }

    /**
     * Save updated presets list from admin form.
     */
    public static function save_presets() {
        check_admin_referer( 'mnb_save_epitaphs', 'mnb_epitaphs_nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        $raw     = $_POST['mnb_epitaphs'] ?? '';
        $lines   = explode( "\n", sanitize_textarea_field( wp_unslash( $raw ) ) );
        $presets = array_values( array_filter( array_map( 'trim', $lines ) ) );

        update_option( self::OPTION_KEY, $presets );
        wp_redirect( admin_url( 'admin.php?page=mnb-epitaphs&saved=1' ) );
        exit;
    }

    /**
     * Render the admin page for managing epitaph presets.
     * Called by MNB_Admin.
     */
    public static function render_admin_page() {
        $presets = self::get_presets();
        ?>
        <div class="wrap mnb-admin-wrap">
            <h1>Epitaph Presets</h1>
            <p>
                These options appear in the <strong>Select Epitaph</strong> dropdown on every monument
                product page. Customers can choose one or select <strong>Write Your Own</strong>
                to enter a custom message. One preset per line.
            </p>

            <?php if ( isset( $_GET['saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Epitaph presets saved.</p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'mnb_save_epitaphs', 'mnb_epitaphs_nonce' ); ?>
                <input type="hidden" name="action" value="mnb_save_epitaphs">

                <textarea name="mnb_epitaphs" rows="16" style="width:100%;max-width:600px;font-size:14px;padding:10px;"><?php
                    echo esc_textarea( implode( "\n", $presets ) );
                ?></textarea>

                <p class="description" style="max-width:600px;">
                    Enter one epitaph per line. A "Write Your Own" option is always appended
                    automatically — you do not need to add it here.
                </p>

                <?php submit_button( 'Save Epitaph Presets' ); ?>
            </form>

            <h2 style="margin-top:32px;">Preview</h2>
            <p style="color:#666;font-size:13px;">This is how the dropdown will appear to customers:</p>
            <select style="width:100%;max-width:400px;padding:8px;font-size:14px;border:1px solid #ddd;border-radius:4px;">
                <option value="">— Choose an epitaph —</option>
                <?php foreach ( $presets as $p ) : ?>
                    <option><?php echo esc_html( $p ); ?></option>
                <?php endforeach; ?>
                <option value="custom">✏️ Write Your Own</option>
            </select>
        </div>
        <?php
    }
}
