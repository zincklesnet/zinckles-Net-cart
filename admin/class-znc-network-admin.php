<?php
/**
 * Zinckles Net Cart — Network Admin Settings
 *
 * Controls which subsites participate, global REST authentication,
 * network-wide defaults, and diagnostic tools.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 *
 * v1.2.0 FIXES:
 *  - init() is now called from zinckles-net-cart.php (was missing — caused enrollment AJAX to never register)
 *  - admin_enqueue_scripts added to properly load JS with nonce + ajaxurl on network admin pages
 *  - ajax_toggle_site sends back full site row data for instant UI update
 *  - mycred_point_type replaced with mycred_point_types (array) to support multiple MyCred types
 *  - Security save handler added
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZNC_Network_Admin {

    /** Option key in sitemeta. */
    const OPTION_KEY = 'znc_network_settings';

    /** Default settings. */
    private static $defaults = array(
        // ── Subsite Enrollment ──────────────────────────────────
        'enrollment_mode'    => 'opt-in',   // opt-in | opt-out | manual
        'enrolled_sites'     => array(),    // blog_ids explicitly enrolled
        'blocked_sites'      => array(),    // blog_ids explicitly blocked
        'auto_enroll_new'    => false,      // auto-enroll newly created subsites

        // ── REST Authentication ─────────────────────────────────
        'rest_shared_secret' => '',         // 64-char HMAC secret
        'rest_clock_skew'    => 300,        // seconds tolerance
        'rest_rate_limit'    => 120,        // requests per minute per site
        'rest_ip_whitelist'  => '',         // comma-separated IPs (empty = all)

        // ── Global Defaults ─────────────────────────────────────
        'base_currency'          => 'CAD',
        'allow_mixed_currency'   => true,
        'mycred_enabled'         => true,
        'mycred_point_types'     => array(), // auto-detected, each: slug => { label, exchange_rate, max_percent }
        'mycred_exchange_rate'   => 1.0,     // fallback: 1 point = $1.00
        'mycred_max_percent'     => 50,      // fallback max %

        // ── Cart Behaviour ──────────────────────────────────────
        'cart_expiry_hours'      => 168,     // 7 days
        'max_items_per_cart'     => 100,
        'max_shops_per_cart'     => 10,

        // ── Checkout ────────────────────────────────────────────
        'checkout_validation'    => 'strict', // strict | relaxed
        'inventory_retry_max'   => 5,
        'inventory_retry_delay' => 300,      // seconds

        // ── Logging & Debug ─────────────────────────────────────
        'logging_enabled'       => true,
        'log_level'             => 'info',   // debug | info | warning | error
        'log_retention_days'    => 30,
        'debug_mode'            => false,
    );

    /**
     * Boot the network admin panel.
     */
    public static function init() {
        add_action( 'network_admin_menu',   array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

        // Network settings save (form POST).
        add_action( 'network_admin_edit_znc_save_network',   array( __CLASS__, 'save_settings' ) );
        add_action( 'network_admin_edit_znc_save_security',  array( __CLASS__, 'save_security' ) );

        // AJAX handlers — hooked on BOTH wp_ajax_ (logged-in).
        add_action( 'wp_ajax_znc_toggle_site',          array( __CLASS__, 'ajax_toggle_site' ) );
        add_action( 'wp_ajax_znc_regenerate_secret',     array( __CLASS__, 'ajax_regenerate_secret' ) );
        add_action( 'wp_ajax_znc_test_site_connection',  array( __CLASS__, 'ajax_test_connection' ) );
        add_action( 'wp_ajax_znc_detect_mycred_types',   array( __CLASS__, 'ajax_detect_mycred_types' ) );
    }

    /* ─── Enqueue Scripts ───────────────────────────────────── */

    public static function enqueue_scripts( $hook ) {
        // Only load on our network admin pages.
        if ( false === strpos( $hook, 'znc-network' ) ) {
            return;
        }

        wp_enqueue_style(
            'znc-network-admin',
            ZNC_PLUGIN_URL . 'admin/assets/css/znc-network-admin.css',
            array(),
            ZNC_VERSION
        );

        wp_enqueue_script(
            'znc-network-admin',
            ZNC_PLUGIN_URL . 'admin/assets/js/znc-network-admin.js',
            array( 'jquery' ),
            ZNC_VERSION,
            true
        );

        wp_localize_script( 'znc-network-admin', 'zncAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'znc_network_nonce' ),
        ) );
    }

    /* ─── Menu ──────────────────────────────────────────────── */

    public static function register_menu() {
        add_menu_page(
            __( 'Net Cart Network', 'znc' ),
            __( 'Net Cart', 'znc' ),
            'manage_network_options',
            'znc-network',
            array( __CLASS__, 'render_page' ),
            'dashicons-networking',
            30
        );

        add_submenu_page(
            'znc-network',
            __( 'Subsite Enrollment', 'znc' ),
            __( 'Subsites', 'znc' ),
            'manage_network_options',
            'znc-network-sites',
            array( __CLASS__, 'render_sites_page' )
        );

        add_submenu_page(
            'znc-network',
            __( 'REST Security', 'znc' ),
            __( 'Security', 'znc' ),
            'manage_network_options',
            'znc-network-security',
            array( __CLASS__, 'render_security_page' )
        );

        add_submenu_page(
            'znc-network',
            __( 'Diagnostics', 'znc' ),
            __( 'Diagnostics', 'znc' ),
            'manage_network_options',
            'znc-network-diagnostics',
            array( __CLASS__, 'render_diagnostics_page' )
        );
    }

    /* ─── Settings CRUD ─────────────────────────────────────── */

    public static function get_settings() {
        $saved = get_site_option( self::OPTION_KEY, array() );
        return wp_parse_args( $saved, self::$defaults );
    }

    public static function get( $key, $fallback = null ) {
        $settings = self::get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
    }

    public static function update_settings( $new_values ) {
        $current = self::get_settings();
        $merged  = wp_parse_args( $new_values, $current );
        update_site_option( self::OPTION_KEY, $merged );
        do_action( 'znc_network_settings_updated', $merged, $current );
        return $merged;
    }

    /* ─── Subsite Enrollment ────────────────────────────────── */

    public static function is_site_enrolled( $blog_id ) {
        $settings = self::get_settings();
        $blog_id  = (int) $blog_id;

        // Main site is never "enrolled" as a shop.
        if ( $blog_id === (int) get_main_site_id() ) {
            return false;
        }

        // Explicitly blocked always wins.
        if ( in_array( $blog_id, (array) $settings['blocked_sites'], true ) ) {
            return false;
        }

        switch ( $settings['enrollment_mode'] ) {
            case 'opt-out':
                return true;
            case 'manual':
            case 'opt-in':
            default:
                return in_array( $blog_id, (array) $settings['enrolled_sites'], true );
        }
    }

    public static function get_enrolled_sites() {
        $sites    = get_sites( array( 'number' => 500, 'public' => 1 ) );
        $enrolled = array();

        foreach ( $sites as $site ) {
            if ( self::is_site_enrolled( $site->blog_id ) ) {
                switch_to_blog( $site->blog_id );
                $enrolled[] = array(
                    'blog_id'  => (int) $site->blog_id,
                    'name'     => get_bloginfo( 'name' ),
                    'url'      => home_url(),
                    'currency' => function_exists( 'get_woocommerce_currency' )
                        ? get_woocommerce_currency() : '—',
                    'wc_active' => class_exists( 'WooCommerce' ),
                    'mycred'    => function_exists( 'mycred' ),
                    'products'  => self::count_products(),
                );
                restore_current_blog();
            }
        }
        return $enrolled;
    }

    /**
     * Enroll or unenroll a site. Returns the new enrollment state.
     */
    public static function set_site_enrollment( $blog_id, $enrolled ) {
        $settings = self::get_settings();
        $blog_id  = (int) $blog_id;

        // Remove from both lists first.
        $settings['enrolled_sites'] = array_values(
            array_diff( (array) $settings['enrolled_sites'], array( $blog_id ) )
        );
        $settings['blocked_sites'] = array_values(
            array_diff( (array) $settings['blocked_sites'], array( $blog_id ) )
        );

        if ( $enrolled ) {
            $settings['enrolled_sites'][] = $blog_id;
        } else {
            if ( $settings['enrollment_mode'] === 'opt-out' ) {
                $settings['blocked_sites'][] = $blog_id;
            }
        }

        self::update_settings( $settings );
        do_action( 'znc_site_enrollment_changed', $blog_id, $enrolled );

        return $enrolled;
    }

    /* ─── REST Secret Management ────────────────────────────── */

    public static function regenerate_secret() {
        $secret   = wp_generate_password( 64, true, true );
        $settings = self::get_settings();
        $settings['rest_shared_secret'] = $secret;
        self::update_settings( $settings );

        // Propagate to all enrolled sites.
        foreach ( self::get_enrolled_sites() as $site ) {
            switch_to_blog( $site['blog_id'] );
            update_option( 'znc_rest_shared_secret', $secret );
            restore_current_blog();
        }

        return $secret;
    }

    /* ─── MyCred Multi-Type Detection ───────────────────────── */

    /**
     * Detect all registered MyCred point types across the network.
     */
    public static function detect_mycred_point_types() {
        $types = array();

        // Check main site first.
        if ( function_exists( 'mycred_get_types' ) ) {
            $registered = mycred_get_types();
            foreach ( $registered as $slug => $label ) {
                $mycred_obj = mycred( $slug );
                $types[ $slug ] = array(
                    'slug'          => $slug,
                    'label'         => $label,
                    'singular'      => $mycred_obj ? $mycred_obj->singular() : $label,
                    'plural'        => $mycred_obj ? $mycred_obj->plural() : $label,
                    'prefix'        => $mycred_obj ? $mycred_obj->before : '',
                    'suffix'        => $mycred_obj ? $mycred_obj->after : '',
                    'exchange_rate' => 1.0,
                    'max_percent'   => 50,
                    'enabled'       => true,
                    'source'        => 'main_site',
                );
            }
        }

        // Scan enrolled subsites for additional types.
        $enrolled = self::get_enrolled_sites();
        foreach ( $enrolled as $site ) {
            switch_to_blog( $site['blog_id'] );
            if ( function_exists( 'mycred_get_types' ) ) {
                $sub_types = mycred_get_types();
                foreach ( $sub_types as $slug => $label ) {
                    if ( ! isset( $types[ $slug ] ) ) {
                        $mycred_obj = mycred( $slug );
                        $types[ $slug ] = array(
                            'slug'          => $slug,
                            'label'         => $label,
                            'singular'      => $mycred_obj ? $mycred_obj->singular() : $label,
                            'plural'        => $mycred_obj ? $mycred_obj->plural() : $label,
                            'prefix'        => $mycred_obj ? $mycred_obj->before : '',
                            'suffix'        => $mycred_obj ? $mycred_obj->after : '',
                            'exchange_rate' => 1.0,
                            'max_percent'   => 50,
                            'enabled'       => true,
                            'source'        => 'subsite_' . $site['blog_id'],
                        );
                    }
                }
            }
            restore_current_blog();
        }

        return $types;
    }

    /* ─── AJAX Handlers ─────────────────────────────────────── */

    public static function ajax_toggle_site() {
        check_ajax_referer( 'znc_network_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $blog_id  = absint( $_POST['blog_id'] ?? 0 );
        $enroll   = filter_var( $_POST['enroll'] ?? false, FILTER_VALIDATE_BOOLEAN );

        if ( ! $blog_id ) {
            wp_send_json_error( array( 'message' => 'Invalid site ID.' ) );
        }

        self::set_site_enrollment( $blog_id, $enroll );

        // Return the confirmed state so JS can update the UI.
        $is_enrolled = self::is_site_enrolled( $blog_id );

        // Gather site info for the row update.
        switch_to_blog( $blog_id );
        $site_data = array(
            'blog_id'    => $blog_id,
            'name'       => get_bloginfo( 'name' ),
            'url'        => home_url(),
            'currency'   => function_exists( 'get_woocommerce_currency' )
                ? get_woocommerce_currency() : '—',
            'wc_active'  => class_exists( 'WooCommerce' ),
            'mycred'     => function_exists( 'mycred' ),
            'products'   => self::count_products(),
            'enrolled'   => $is_enrolled,
        );
        restore_current_blog();

        wp_send_json_success( $site_data );
    }

    public static function ajax_regenerate_secret() {
        check_ajax_referer( 'znc_network_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $secret = self::regenerate_secret();

        wp_send_json_success( array(
            'secret_preview' => substr( $secret, 0, 8 ) . '…' . substr( $secret, -8 ),
            'message'        => __( 'Secret regenerated and propagated to all enrolled sites.', 'znc' ),
        ) );
    }

    public static function ajax_test_connection() {
        check_ajax_referer( 'znc_network_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $blog_id = absint( $_POST['blog_id'] ?? 0 );
        $result  = self::test_site_connection( $blog_id );
        wp_send_json_success( $result );
    }

    public static function ajax_detect_mycred_types() {
        check_ajax_referer( 'znc_network_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $types = self::detect_mycred_point_types();

        // Save detected types to settings.
        $settings = self::get_settings();
        // Merge with any existing exchange rates / max_percent overrides.
        $existing = (array) ( $settings['mycred_point_types'] ?? array() );
        foreach ( $types as $slug => $type ) {
            if ( isset( $existing[ $slug ] ) ) {
                $types[ $slug ]['exchange_rate'] = $existing[ $slug ]['exchange_rate'] ?? 1.0;
                $types[ $slug ]['max_percent']   = $existing[ $slug ]['max_percent'] ?? 50;
                $types[ $slug ]['enabled']       = $existing[ $slug ]['enabled'] ?? true;
            }
        }
        $settings['mycred_point_types'] = $types;
        self::update_settings( $settings );

        wp_send_json_success( array(
            'types'   => $types,
            'count'   => count( $types ),
            'message' => sprintf(
                __( 'Detected %d MyCred point type(s) across the network.', 'znc' ),
                count( $types )
            ),
        ) );
    }

    /* ─── Connection Test ───────────────────────────────────── */

    public static function test_site_connection( $blog_id ) {
        switch_to_blog( $blog_id );
        $url = rest_url( 'znc/v1/shop-settings' );
        restore_current_blog();

        $settings  = self::get_settings();
        $timestamp = time();
        $signature = hash_hmac( 'sha256', $timestamp . ':' . $url, $settings['rest_shared_secret'] );

        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'X-ZNC-Timestamp' => $timestamp,
                'X-ZNC-Signature' => $signature,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'status'  => 'error',
                'message' => $response->get_error_message(),
                'latency' => null,
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        return array(
            'status'  => $code === 200 ? 'ok' : 'error',
            'code'    => $code,
            'message' => $code === 200 ? 'Connection OK' : 'HTTP ' . $code,
            'body'    => json_decode( wp_remote_retrieve_body( $response ), true ),
        );
    }

    /* ─── Save Handler: Network Settings ────────────────────── */

    public static function save_settings() {
        check_admin_referer( 'znc_save_network_settings' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $input = $_POST['znc'] ?? array();
        $clean = array();

        // Enrollment.
        $clean['enrollment_mode'] = in_array(
            $input['enrollment_mode'] ?? '',
            array( 'opt-in', 'opt-out', 'manual' ),
            true
        ) ? $input['enrollment_mode'] : 'opt-in';
        $clean['auto_enroll_new'] = ! empty( $input['auto_enroll_new'] );

        // Currency.
        $clean['base_currency']        = sanitize_text_field( $input['base_currency'] ?? 'CAD' );
        $clean['allow_mixed_currency'] = ! empty( $input['allow_mixed_currency'] );

        // MyCred — multi-type.
        $clean['mycred_enabled'] = ! empty( $input['mycred_enabled'] );
        if ( ! empty( $input['mycred_point_types'] ) && is_array( $input['mycred_point_types'] ) ) {
            $types = array();
            foreach ( $input['mycred_point_types'] as $slug => $data ) {
                $types[ sanitize_key( $slug ) ] = array(
                    'slug'          => sanitize_key( $slug ),
                    'label'         => sanitize_text_field( $data['label'] ?? $slug ),
                    'exchange_rate' => floatval( $data['exchange_rate'] ?? 1.0 ),
                    'max_percent'   => min( 100, max( 0, absint( $data['max_percent'] ?? 50 ) ) ),
                    'enabled'       => ! empty( $data['enabled'] ),
                );
            }
            $clean['mycred_point_types'] = $types;
        }
        // Legacy fallbacks.
        $clean['mycred_exchange_rate'] = floatval( $input['mycred_exchange_rate'] ?? 1.0 );
        $clean['mycred_max_percent']   = min( 100, max( 0, absint( $input['mycred_max_percent'] ?? 50 ) ) );

        // Cart.
        $clean['cart_expiry_hours']   = absint( $input['cart_expiry_hours'] ?? 168 );
        $clean['max_items_per_cart']  = absint( $input['max_items_per_cart'] ?? 100 );
        $clean['max_shops_per_cart']  = absint( $input['max_shops_per_cart'] ?? 10 );

        // Checkout.
        $clean['checkout_validation'] = in_array(
            $input['checkout_validation'] ?? '',
            array( 'strict', 'relaxed' ),
            true
        ) ? $input['checkout_validation'] : 'strict';
        $clean['inventory_retry_max']   = absint( $input['inventory_retry_max'] ?? 5 );
        $clean['inventory_retry_delay'] = absint( $input['inventory_retry_delay'] ?? 300 );

        // Logging.
        $clean['logging_enabled']    = ! empty( $input['logging_enabled'] );
        $clean['log_level'] = in_array(
            $input['log_level'] ?? '',
            array( 'debug', 'info', 'warning', 'error' ),
            true
        ) ? $input['log_level'] : 'info';
        $clean['log_retention_days'] = absint( $input['log_retention_days'] ?? 30 );
        $clean['debug_mode']         = ! empty( $input['debug_mode'] );

        self::update_settings( $clean );

        wp_redirect( add_query_arg( array(
            'page'    => 'znc-network',
            'updated' => 'true',
        ), network_admin_url( 'admin.php' ) ) );
        exit;
    }

    /* ─── Save Handler: Security Settings ───────────────────── */

    public static function save_security() {
        check_admin_referer( 'znc_save_security_settings' );

        if ( ! current_user_can( 'manage_network_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $input = $_POST['znc'] ?? array();
        $clean = array();

        $clean['rest_clock_skew']    = absint( $input['rest_clock_skew'] ?? 300 );
        $clean['rest_rate_limit']    = absint( $input['rest_rate_limit'] ?? 120 );
        $clean['rest_ip_whitelist']  = sanitize_text_field( $input['rest_ip_whitelist'] ?? '' );

        self::update_settings( $clean );

        wp_redirect( add_query_arg( array(
            'page'    => 'znc-network-security',
            'updated' => 'true',
        ), network_admin_url( 'admin.php' ) ) );
        exit;
    }

    /* ─── Render Pages ──────────────────────────────────────── */

    public static function render_page() {
        $settings = self::get_settings();
        // Auto-detect MyCred types if not yet populated.
        if ( empty( $settings['mycred_point_types'] ) && function_exists( 'mycred_get_types' ) ) {
            $settings['mycred_point_types'] = self::detect_mycred_point_types();
            self::update_settings( array( 'mycred_point_types' => $settings['mycred_point_types'] ) );
        }
        include __DIR__ . '/views/network-settings.php';
    }

    public static function render_sites_page() {
        $settings  = self::get_settings();
        $all_sites = get_sites( array( 'number' => 500, 'public' => 1 ) );
        include __DIR__ . '/views/network-sites.php';
    }

    public static function render_security_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/network-security.php';
    }

    public static function render_diagnostics_page() {
        $settings = self::get_settings();
        $enrolled = self::get_enrolled_sites();
        include __DIR__ . '/views/network-diagnostics.php';
    }

    /* ─── Helpers ────────────────────────────────────────────── */

    private static function count_products() {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return 0;
        }
        return count( wc_get_products( array(
            'status' => 'publish',
            'limit'  => -1,
            'return' => 'ids',
        ) ) );
    }
}
