<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Network_Settings {

    private $defaults = array(
        'enrollment_mode'       => 'opt_in',
        'auto_enroll_new'       => false,
        'base_currency'         => 'USD',
        'mixed_currency'        => true,
        'zcred_enabled'         => true,
        'zcred_exchange_rate'   => 0.01,
        'zcred_max_percent'     => 100,
        'cart_expiry_days'      => 7,
        'max_items_per_cart'    => 50,
        'max_shops_per_cart'    => 10,
        'validation_mode'       => 'strict',
        'retry_max_attempts'    => 5,
        'retry_interval_minutes'=> 5,
        'log_level'             => 'info',
        'log_retention_days'    => 30,
        'debug_mode'            => false,
        'clock_skew_seconds'    => 300,
        'rate_limit_per_minute' => 60,
        'ip_whitelist'          => '',
    );

    public function init() {
        if ( is_network_admin() ) {
            add_action( 'network_admin_menu', array( $this, 'add_network_menu' ) );
            add_action( 'network_admin_edit_znc_network_settings', array( $this, 'save_network_settings' ) );
        }
        add_action( 'wp_initialize_site', array( $this, 'on_new_site' ), 20 );
    }

    public function add_network_menu() {
        add_menu_page(
            __( 'Net Cart', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_network_options',
            'znc-network',
            array( $this, 'render_settings_page' ),
            'dashicons-cart',
            30
        );

        add_submenu_page( 'znc-network', __( 'Settings', 'zinckles-net-cart' ), __( 'Settings', 'zinckles-net-cart' ), 'manage_network_options', 'znc-network', array( $this, 'render_settings_page' ) );
        add_submenu_page( 'znc-network', __( 'Subsites', 'zinckles-net-cart' ), __( 'Subsites', 'zinckles-net-cart' ), 'manage_network_options', 'znc-network-subsites', array( $this, 'render_subsites_page' ) );
        add_submenu_page( 'znc-network', __( 'Security', 'zinckles-net-cart' ), __( 'Security', 'zinckles-net-cart' ), 'manage_network_options', 'znc-network-security', array( $this, 'render_security_page' ) );
        add_submenu_page( 'znc-network', __( 'Diagnostics', 'zinckles-net-cart' ), __( 'Diagnostics', 'zinckles-net-cart' ), 'manage_network_options', 'znc-network-diagnostics', array( $this, 'render_diagnostics_page' ) );
    }

    public function get_settings() : array {
        $saved = get_site_option( 'znc_network_settings', array() );
        return wp_parse_args( $saved, $this->defaults );
    }

    public function save_network_settings() {
        check_admin_referer( 'znc_network_settings_nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $settings = array();
        foreach ( $this->defaults as $key => $default ) {
            if ( is_bool( $default ) ) {
                $settings[ $key ] = ! empty( $_POST[ $key ] );
            } elseif ( is_int( $default ) ) {
                $settings[ $key ] = intval( $_POST[ $key ] ?? $default );
            } elseif ( is_float( $default ) ) {
                $settings[ $key ] = floatval( $_POST[ $key ] ?? $default );
            } else {
                $settings[ $key ] = sanitize_text_field( $_POST[ $key ] ?? $default );
            }
        }

        update_site_option( 'znc_network_settings', $settings );

        wp_safe_redirect( add_query_arg( array(
            'page'    => 'znc-network',
            'updated' => 'true',
        ), network_admin_url( 'admin.php' ) ) );
        exit;
    }

    public function on_new_site( WP_Site $site ) {
        $settings = $this->get_settings();
        if ( $settings['auto_enroll_new'] ) {
            global $wpdb;
            switch_to_blog( get_main_site_id() );
            $wpdb->insert( $wpdb->prefix . 'znc_enrolled_sites', array(
                'site_id'     => $site->blog_id,
                'status'      => 'active',
                'enrolled_by' => get_current_user_id(),
            ) );
            restore_current_blog();
        }
    }

    /* ── Render pages ─────────────────────────────────────── */

    public function render_settings_page() {
        include ZNC_PLUGIN_DIR . 'admin/views/network-settings.php';
    }

    public function render_subsites_page() {
        include ZNC_PLUGIN_DIR . 'admin/views/network-subsites.php';
    }

    public function render_security_page() {
        include ZNC_PLUGIN_DIR . 'admin/views/network-security.php';
    }

    public function render_diagnostics_page() {
        include ZNC_PLUGIN_DIR . 'admin/views/network-diagnostics.php';
    }
}
