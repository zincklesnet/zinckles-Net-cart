<?php
/**
 * Subsite Admin — per-shop settings panel.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Subsite_Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Net Cart', 'znc' ),
            __( 'Net Cart', 'znc' ),
            'manage_options',
            'znc-subsite',
            array( $this, 'render_dashboard' ),
            'dashicons-networking',
            58
        );

        add_submenu_page(
            'znc-subsite',
            __( 'Products', 'znc' ),
            __( 'Products', 'znc' ),
            'manage_options',
            'znc-subsite-products',
            array( $this, 'render_products' )
        );

        add_submenu_page(
            'znc-subsite',
            __( 'Branding', 'znc' ),
            __( 'Branding', 'znc' ),
            'manage_options',
            'znc-subsite-branding',
            array( $this, 'render_branding' )
        );
    }

    public function register_settings() {
        register_setting( 'znc_subsite', 'znc_subsite_settings', array(
            'sanitize_callback' => array( $this, 'sanitize' ),
        ) );
    }

    public function sanitize( $input ) {
        $clean = array();
        $clean['display_name']     = sanitize_text_field( $input['display_name'] ?? '' );
        $clean['tagline']          = sanitize_text_field( $input['tagline'] ?? '' );
        $clean['badge_color']      = sanitize_hex_color( $input['badge_color'] ?? '#7c3aed' );
        $clean['badge_icon']       = esc_url_raw( $input['badge_icon'] ?? '' );
        $clean['product_mode']     = in_array( $input['product_mode'] ?? '', array( 'all', 'include', 'exclude' ), true )
            ? $input['product_mode'] : 'all';
        $clean['shipping_mode']    = sanitize_text_field( $input['shipping_mode'] ?? 'inherit' );
        $clean['shipping_flat_rate'] = floatval( $input['shipping_flat_rate'] ?? 0 );
        $clean['shipping_free_threshold'] = floatval( $input['shipping_free_threshold'] ?? 0 );
        $clean['tax_mode']         = sanitize_text_field( $input['tax_mode'] ?? 'inherit' );
        $clean['tax_rate']         = floatval( $input['tax_rate'] ?? 0 );
        $clean['tax_label']        = sanitize_text_field( $input['tax_label'] ?? 'Tax' );
        $clean['zcred_accept']     = ! empty( $input['zcred_accept'] );
        $clean['zcred_max_percent'] = min( 100, max( 0, absint( $input['zcred_max_percent'] ?? 50 ) ) );
        $clean['zcred_earn_multiplier'] = floatval( $input['zcred_earn_multiplier'] ?? 1.0 );
        $clean['custom_meta_keys'] = sanitize_text_field( $input['custom_meta_keys'] ?? '' );
        return $clean;
    }

    public function render_dashboard() {
        $settings    = get_option( 'znc_subsite_settings', array() );
        $is_enrolled = ZNC_Network_Admin::is_site_enrolled( get_current_blog_id() );
        $has_wc      = class_exists( 'WooCommerce' );
        $has_mycred  = function_exists( 'mycred' );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Net Cart — Shop Dashboard', 'znc' ); ?></h1>
            <div class="card" style="max-width:600px;">
                <h2><?php _e( 'Status', 'znc' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e( 'Enrollment', 'znc' ); ?></th>
                        <td><?php echo $is_enrolled
                            ? '<span style="color:#2e7d32;font-weight:600;">✅ Enrolled</span>'
                            : '<span style="color:#f44336;font-weight:600;">❌ Not Enrolled</span>'; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'WooCommerce', 'znc' ); ?></th>
                        <td><?php echo $has_wc ? '✅ Active' : '❌ Not active'; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'MyCred', 'znc' ); ?></th>
                        <td><?php echo $has_mycred ? '✅ Active' : '— Not installed'; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Currency', 'znc' ); ?></th>
                        <td><code><?php echo function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '—'; ?></code></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    public function render_products() {
        $settings = get_option( 'znc_subsite_settings', array() );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Net Cart — Product Settings', 'znc' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'znc_subsite' ); ?>
                <div class="card" style="max-width:600px;">
                    <table class="form-table">
                        <tr>
                            <th><label><?php _e( 'Product Mode', 'znc' ); ?></label></th>
                            <td>
                                <select name="znc_subsite_settings[product_mode]">
                                    <option value="all" <?php selected( $settings['product_mode'] ?? 'all', 'all' ); ?>><?php _e( 'All products', 'znc' ); ?></option>
                                    <option value="include" <?php selected( $settings['product_mode'] ?? '', 'include' ); ?>><?php _e( 'Include only selected', 'znc' ); ?></option>
                                    <option value="exclude" <?php selected( $settings['product_mode'] ?? '', 'exclude' ); ?>><?php _e( 'Exclude selected', 'znc' ); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_branding() {
        $settings = get_option( 'znc_subsite_settings', array() );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Net Cart — Shop Branding', 'znc' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'znc_subsite' ); ?>
                <div class="card" style="max-width:600px;">
                    <table class="form-table">
                        <tr>
                            <th><label><?php _e( 'Display Name', 'znc' ); ?></label></th>
                            <td><input type="text" name="znc_subsite_settings[display_name]" value="<?php echo esc_attr( $settings['display_name'] ?? get_bloginfo( 'name' ) ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label><?php _e( 'Tagline', 'znc' ); ?></label></th>
                            <td><input type="text" name="znc_subsite_settings[tagline]" value="<?php echo esc_attr( $settings['tagline'] ?? '' ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label><?php _e( 'Badge Color', 'znc' ); ?></label></th>
                            <td><input type="color" name="znc_subsite_settings[badge_color]" value="<?php echo esc_attr( $settings['badge_color'] ?? '#7c3aed' ); ?>"></td>
                        </tr>
                    </table>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
