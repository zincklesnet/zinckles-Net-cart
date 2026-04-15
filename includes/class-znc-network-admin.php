<?php
/**
 * Network Admin — Menus, pages, and AJAX handlers.
 *
 * v1.6.0 FIXES:
 * - GamiPress detection now uses direct $wpdb queries instead of
 *   post_type_exists() which doesn't work after switch_to_blog().
 * - All AJAX handlers use consistent 'znc_network_admin' nonce.
 *
 * @package ZincklesNetCart
 * @since   1.6.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Network_Admin {

    /** Register menus — call ONLY in is_network_admin(). */
    public static function init() {
        add_action( 'network_admin_menu', array( __CLASS__, 'add_menus' ) );
    }

    /** Register AJAX handlers — call on ALL sites. */
    public static function register_ajax_handlers() {
        $actions = array(
            'znc_save_settings'       => 'ajax_save_settings',
            'znc_enroll_site'         => 'ajax_enroll_site',
            'znc_remove_site'         => 'ajax_remove_site',
            'znc_save_security'       => 'ajax_save_security',
            'znc_regenerate_secret'   => 'ajax_regenerate_secret',
            'znc_test_connection'     => 'ajax_test_connection',
            'znc_detect_point_types'  => 'ajax_detect_point_types',
        );
        foreach ( $actions as $action => $method ) {
            if ( ! has_action( 'wp_ajax_' . $action ) ) {
                add_action( 'wp_ajax_' . $action, array( __CLASS__, $method ) );
            }
        }
    }

    /** Add network admin menu pages. */
    public static function add_menus() {
        add_menu_page( 'Net Cart', 'Net Cart', 'manage_network_options', 'znc-settings', array( __CLASS__, 'page_settings' ), 'dashicons-cart', 30 );
        add_submenu_page( 'znc-settings', 'Settings',    'Settings',    'manage_network_options', 'znc-settings',     array( __CLASS__, 'page_settings' ) );
        add_submenu_page( 'znc-settings', 'Subsites',    'Subsites',    'manage_network_options', 'znc-subsites',     array( __CLASS__, 'page_subsites' ) );
        add_submenu_page( 'znc-settings', 'Security',    'Security',    'manage_network_options', 'znc-security',     array( __CLASS__, 'page_security' ) );
        add_submenu_page( 'znc-settings', 'Diagnostics', 'Diagnostics', 'manage_network_options', 'znc-diagnostics',  array( __CLASS__, 'page_diagnostics' ) );
    }

    public static function page_settings()    { include ZNC_PLUGIN_DIR . 'admin/views/network-settings.php'; }
    public static function page_subsites()    { include ZNC_PLUGIN_DIR . 'admin/views/network-subsites.php'; }
    public static function page_security()    { include ZNC_PLUGIN_DIR . 'admin/views/network-security.php'; }
    public static function page_diagnostics() { include ZNC_PLUGIN_DIR . 'admin/views/network-diagnostics.php'; }

    /* ── AJAX: Save Settings ──────────────────────────────── */
    public static function ajax_save_settings() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $settings = get_site_option( 'znc_network_settings', array() );

        $fields = array( 'checkout_host_id', 'enrollment_mode', 'base_currency', 'cart_expiry_days' );
        foreach ( $fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $settings[ $f ] = sanitize_text_field( $_POST[ $f ] );
            }
        }

        $toggles = array( 'clear_local_cart', 'debug_mode', 'tutor_lms_support', 'enable_cart_sync', 'enable_admin_bar_cart' );
        foreach ( $toggles as $t ) {
            $settings[ $t ] = ! empty( $_POST[ $t ] ) ? 1 : 0;
        }

        if ( isset( $_POST['mycred_types'] ) && is_array( $_POST['mycred_types'] ) ) {
            $settings['mycred_types'] = array_map( 'sanitize_text_field', $_POST['mycred_types'] );
        }
        if ( isset( $_POST['gamipress_types'] ) && is_array( $_POST['gamipress_types'] ) ) {
            $settings['gamipress_types'] = array_map( 'sanitize_text_field', $_POST['gamipress_types'] );
        }

        update_site_option( 'znc_network_settings', $settings );

        $host = new ZNC_Checkout_Host();
        $host->flush_url_cache();

        wp_send_json_success( 'Settings saved' );
    }

    /* ── AJAX: Enroll Site ────────────────────────────────── */
    public static function ajax_enroll_site() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $blog_id = absint( $_POST['blog_id'] ?? 0 );
        if ( ! $blog_id || ! get_blog_details( $blog_id ) ) {
            wp_send_json_error( 'Invalid site ID' );
        }

        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );

        if ( ! in_array( $blog_id, $enrolled, true ) ) {
            $enrolled[] = $blog_id;
            $settings['enrolled_sites'] = $enrolled;
            update_site_option( 'znc_network_settings', $settings );
        }

        wp_send_json_success( array( 'enrolled' => true, 'blog_id' => $blog_id ) );
    }

    /* ── AJAX: Remove Site ────────────────────────────────── */
    public static function ajax_remove_site() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $blog_id  = absint( $_POST['blog_id'] ?? 0 );
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );

        $enrolled = array_values( array_filter( $enrolled, function( $id ) use ( $blog_id ) {
            return absint( $id ) !== $blog_id;
        } ) );

        $settings['enrolled_sites'] = $enrolled;
        update_site_option( 'znc_network_settings', $settings );

        wp_send_json_success( array( 'removed' => true, 'blog_id' => $blog_id ) );
    }

    /* ── AJAX: Save Security ──────────────────────────────── */
    public static function ajax_save_security() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $security = get_site_option( 'znc_security_settings', array() );

        if ( isset( $_POST['clock_skew'] ) )   $security['clock_skew']   = absint( $_POST['clock_skew'] );
        if ( isset( $_POST['rate_limit'] ) )   $security['rate_limit']   = absint( $_POST['rate_limit'] );
        if ( isset( $_POST['ip_whitelist'] ) ) $security['ip_whitelist'] = sanitize_textarea_field( $_POST['ip_whitelist'] );

        update_site_option( 'znc_security_settings', $security );
        wp_send_json_success( 'Security settings saved' );
    }

    /* ── AJAX: Regenerate HMAC Secret ─────────────────────── */
    public static function ajax_regenerate_secret() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $secret   = wp_generate_password( 64, true, true );
        $security = get_site_option( 'znc_security_settings', array() );
        $security['hmac_secret']       = $secret;
        $security['hmac_generated_at'] = current_time( 'mysql' );
        update_site_option( 'znc_security_settings', $security );

        wp_send_json_success( array(
            'secret'       => $secret,
            'generated_at' => $security['hmac_generated_at'],
        ) );
    }

    /* ── AJAX: Test Connection ────────────────────────────── */
    public static function ajax_test_connection() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $blog_id = absint( $_POST['blog_id'] ?? 0 );
        $details = get_blog_details( $blog_id );
        if ( ! $details ) wp_send_json_error( 'Site not found' );

        global $wpdb;
        $prefix  = $wpdb->get_blog_prefix( $blog_id );
        $plugins = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins' LIMIT 1" );

        $has_wc    = $plugins && strpos( $plugins, 'woocommerce' ) !== false;
        $has_tutor = $plugins && ( strpos( $plugins, 'tutor' ) !== false );

        wp_send_json_success( array(
            'blog_id'   => $blog_id,
            'name'      => $details->blogname,
            'url'       => $details->siteurl,
            'has_wc'    => $has_wc,
            'has_tutor' => $has_tutor,
            'reachable' => true,
        ) );
    }

    /* ── AJAX: Detect Point Types — FIXED GamiPress detection ─ */
    public static function ajax_detect_point_types() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        global $wpdb;
        $settings    = get_site_option( 'znc_network_settings', array() );
        $enrolled    = (array) ( $settings['enrolled_sites'] ?? array() );
        $host_id     = absint( $settings['checkout_host_id'] ?? get_main_site_id() );
        $all_sites   = array_unique( array_merge( array( $host_id ), $enrolled ) );

        $mycred_types    = array();
        $gamipress_types = array();
        $tutor_sites     = array();

        foreach ( $all_sites as $blog_id ) {
            $blog_id = absint( $blog_id );
            $details = get_blog_details( $blog_id );
            if ( ! $details ) continue;

            $prefix  = $wpdb->get_blog_prefix( $blog_id );
            $plugins = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins' LIMIT 1" );

            // ── MyCred detection via DB ──
            if ( $plugins && strpos( $plugins, 'mycred' ) !== false ) {
                // Try to get MyCred types from the options table
                $mc_option = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'mycred_types' LIMIT 1" );
                if ( $mc_option ) {
                    $types = maybe_unserialize( $mc_option );
                    if ( is_array( $types ) ) {
                        foreach ( $types as $slug => $label ) {
                            $mycred_types[ $slug ] = is_array( $label ) ? ( $label['plural'] ?? $slug ) : $label;
                        }
                    }
                }
                // Fallback: always include default
                if ( empty( $mycred_types ) ) {
                    $mycred_types['mycred_default'] = 'Points';
                }
            }

            // ── GamiPress detection via DB (FIXED: no post_type_exists) ──
            if ( $plugins && strpos( $plugins, 'gamipress' ) !== false ) {
                // GamiPress stores point types as custom post type 'points-type'
                $gp_types = $wpdb->get_results(
                    "SELECT post_name, post_title FROM {$prefix}posts
                     WHERE post_type = 'points-type' AND post_status = 'publish'"
                );
                if ( $gp_types ) {
                    foreach ( $gp_types as $gpt ) {
                        $gamipress_types[ $gpt->post_name ] = $gpt->post_title;
                    }
                }
            }

            // ── Tutor LMS detection ──
            if ( $plugins && strpos( $plugins, 'tutor' ) !== false ) {
                $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'courses' AND post_status = 'publish'" );
                $tutor_sites[ $blog_id ] = array(
                    'name'    => $details->blogname,
                    'courses' => (int) $count,
                );
            }
        }

        // Save detected types
        $settings['mycred_types']    = array_keys( $mycred_types );
        $settings['gamipress_types'] = array_keys( $gamipress_types );
        $settings['tutor_sites']     = array_keys( $tutor_sites );
        update_site_option( 'znc_network_settings', $settings );

        wp_send_json_success( array(
            'mycred'    => $mycred_types,
            'gamipress' => $gamipress_types,
            'tutor'     => $tutor_sites,
        ) );
    }
}
