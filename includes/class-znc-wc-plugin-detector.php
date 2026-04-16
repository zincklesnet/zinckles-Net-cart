<?php
/**
 * WC Plugin Detector — Detects all WooCommerce-dependent plugins across the network.
 *
 * Scans every site in the multisite network for plugins that depend on or
 * extend WooCommerce. Results are cached and refreshed on plugin activation
 * or deactivation events.
 *
 * @package ZincklesNetCart
 * @since   1.7.2
 */
defined( 'ABSPATH' ) || exit;

class ZNC_WC_Plugin_Detector {

    /** @var string Cache key for network-wide WC plugin map */
    const CACHE_KEY = 'znc_network_wc_plugins';

    /** @var int Cache TTL in seconds (6 hours) */
    const CACHE_TTL = 21600;

    /**
     * Initialize hooks to detect plugin changes across the network.
     */
    public static function init() {
        // Refresh cache when plugins are activated/deactivated on any site
        add_action( 'activated_plugin',   array( __CLASS__, 'invalidate_cache' ), 10, 0 );
        add_action( 'deactivated_plugin', array( __CLASS__, 'invalidate_cache' ), 10, 0 );

        // Also catch network-wide activation/deactivation
        add_action( 'activate_plugin',   array( __CLASS__, 'invalidate_cache' ), 10, 0 );
        add_action( 'deactivate_plugin', array( __CLASS__, 'invalidate_cache' ), 10, 0 );

        // Network admin AJAX handler for on-demand scan
        add_action( 'wp_ajax_znc_scan_wc_plugins', array( __CLASS__, 'ajax_scan' ) );
    }

    /**
     * Invalidate the cached plugin map.
     */
    public static function invalidate_cache() {
        delete_site_transient( self::CACHE_KEY );
    }

    /**
     * Get the full network WC plugin map.
     *
     * @param bool $force_refresh Skip cache and rescan.
     * @return array [ blog_id => [ 'site_name' => string, 'plugins' => array[] ] ]
     */
    public static function get_network_map( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_site_transient( self::CACHE_KEY );
            if ( is_array( $cached ) && ! empty( $cached ) ) {
                return $cached;
            }
        }

        $map   = array();
        $sites = get_sites( array( 'number' => 500 ) );

        foreach ( $sites as $site ) {
            $blog_id = (int) $site->blog_id;
            $plugins = self::scan_site( $blog_id );

            if ( ! empty( $plugins ) ) {
                $blog_details = get_blog_details( $blog_id );
                $map[ $blog_id ] = array(
                    'site_name' => $blog_details ? $blog_details->blogname : "Site #{$blog_id}",
                    'site_url'  => get_home_url( $blog_id ),
                    'plugins'   => $plugins,
                );
            }
        }

        set_site_transient( self::CACHE_KEY, $map, self::CACHE_TTL );
        return $map;
    }

    /**
     * Get WC-related plugins for a specific site.
     *
     * @param int $blog_id
     * @return array [ [ 'file', 'name', 'version', 'active', 'type', 'wc_dependency' ] ]
     */
    public static function get_site_wc_plugins( $blog_id ) {
        $map = self::get_network_map();
        return isset( $map[ $blog_id ] ) ? $map[ $blog_id ]['plugins'] : array();
    }

    /**
     * Scan a single site for WC-dependent plugins.
     *
     * @param int $blog_id
     * @return array
     */
    private static function scan_site( $blog_id ) {
        $current  = get_current_blog_id();
        $switched = ( $current !== $blog_id );

        if ( $switched ) {
            switch_to_blog( $blog_id );
        }

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins    = get_plugins();
        $active_plugins = (array) get_option( 'active_plugins', array() );

        // Also get network-active plugins
        $network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );

        $wc_plugins = array();

        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $wc_type = self::classify_plugin( $plugin_file, $plugin_data );

            if ( $wc_type !== false ) {
                $is_active = in_array( $plugin_file, $active_plugins, true )
                    || isset( $network_plugins[ $plugin_file ] );

                $wc_plugins[] = array(
                    'file'          => $plugin_file,
                    'name'          => $plugin_data['Name'] ?? $plugin_file,
                    'version'       => $plugin_data['Version'] ?? '',
                    'active'        => $is_active,
                    'type'          => $wc_type,
                    'wc_dependency' => self::get_wc_dependency_type( $plugin_file, $plugin_data ),
                );
            }
        }

        if ( $switched ) {
            restore_current_blog();
        }

        return $wc_plugins;
    }

    /**
     * Classify whether a plugin is WooCommerce-related.
     *
     * @param string $plugin_file
     * @param array  $plugin_data
     * @return string|false  Plugin type or false if not WC-related.
     */
    private static function classify_plugin( $plugin_file, $plugin_data ) {
        $name = strtolower( $plugin_data['Name'] ?? '' );
        $desc = strtolower( $plugin_data['Description'] ?? '' );
        $file = strtolower( $plugin_file );

        // WooCommerce itself
        if ( $file === 'woocommerce/woocommerce.php' ) {
            return 'core';
        }

        // Known WC extension patterns
        $wc_indicators = array(
            'woocommerce',
            'woo ',
            'woo-',
            'wcj_',
            'wc_',
            'wc-',
            'for woo',
            'for woocommerce',
            'woocommerce extension',
            'woocommerce plugin',
        );

        foreach ( $wc_indicators as $indicator ) {
            if ( strpos( $name, $indicator ) !== false || strpos( $file, $indicator ) !== false ) {
                return self::determine_type( $name, $desc, $file );
            }
        }

        // Check description for WC dependency mentions
        $desc_indicators = array(
            'requires woocommerce',
            'woocommerce extension',
            'extends woocommerce',
            'woocommerce add-on',
            'woocommerce addon',
            'wc:',
        );

        foreach ( $desc_indicators as $indicator ) {
            if ( strpos( $desc, $indicator ) !== false ) {
                return self::determine_type( $name, $desc, $file );
            }
        }

        // Check RequiresPlugins header (WP 6.5+)
        $requires = strtolower( $plugin_data['RequiresPlugins'] ?? '' );
        if ( strpos( $requires, 'woocommerce' ) !== false ) {
            return self::determine_type( $name, $desc, $file );
        }

        // Known WC-related plugins that may not have obvious names
        $known_wc_plugins = array(
            'booster-plus-for-woocommerce',
            'booster-for-woocommerce',
            'mycred',
            'gamipress',
            'tutor/tutor.php',
            'terawallet',
            'woo-wallet',
            'yith-',
            'dokan',
            'wcfm',
            'elementor-pro', // has WC widgets
        );

        foreach ( $known_wc_plugins as $known ) {
            if ( strpos( $file, $known ) !== false ) {
                return self::determine_type( $name, $desc, $file );
            }
        }

        return false;
    }

    /**
     * Determine the sub-type of a WC plugin.
     *
     * @param string $name
     * @param string $desc
     * @param string $file
     * @return string
     */
    private static function determine_type( $name, $desc, $file ) {
        $combined = $name . ' ' . $desc . ' ' . $file;

        $type_map = array(
            'payment'    => array( 'payment', 'gateway', 'stripe', 'paypal', 'checkout', 'pay ', 'wallet', 'terawallet' ),
            'shipping'   => array( 'shipping', 'shipment', 'delivery', 'freight', 'tracking' ),
            'points'     => array( 'mycred', 'gamipress', 'points', 'rewards', 'credits', 'loyalty' ),
            'membership' => array( 'membership', 'subscription', 'member', 'restrict' ),
            'lms'        => array( 'tutor', 'learndash', 'sensei', 'course', 'lms', 'lesson' ),
            'booking'    => array( 'booking', 'appointment', 'reservation', 'schedule' ),
            'marketing'  => array( 'email', 'newsletter', 'marketing', 'seo', 'analytics', 'ads' ),
            'import'     => array( 'import', 'export', 'csv', 'migrate' ),
            'tax'        => array( 'tax', 'invoice', 'accounting', 'vat' ),
            'booster'    => array( 'booster', 'wcj' ),
            'product'    => array( 'product', 'variation', 'attribute', 'bundle', 'composite' ),
            'ui'         => array( 'elementor', 'widget', 'theme', 'template', 'slider', 'gallery' ),
        );

        foreach ( $type_map as $type => $keywords ) {
            foreach ( $keywords as $keyword ) {
                if ( strpos( $combined, $keyword ) !== false ) {
                    return $type;
                }
            }
        }

        return 'extension';
    }

    /**
     * Determine how a plugin depends on WooCommerce.
     *
     * @param string $plugin_file
     * @param array  $plugin_data
     * @return string  'required' | 'optional' | 'enhances'
     */
    private static function get_wc_dependency_type( $plugin_file, $plugin_data ) {
        $file = strtolower( $plugin_file );
        $desc = strtolower( $plugin_data['Description'] ?? '' );
        $requires = strtolower( $plugin_data['RequiresPlugins'] ?? '' );

        // Core WooCommerce
        if ( $file === 'woocommerce/woocommerce.php' ) {
            return 'core';
        }

        // Explicit WC requirement
        if ( strpos( $requires, 'woocommerce' ) !== false ) {
            return 'required';
        }

        // Strong dependency indicators
        if ( strpos( $desc, 'requires woocommerce' ) !== false
            || strpos( $desc, 'woocommerce extension' ) !== false
            || strpos( $file, 'woocommerce' ) !== false
            || strpos( $file, 'woo-' ) !== false ) {
            return 'required';
        }

        // Optional/enhancement plugins
        $optional_indicators = array( 'mycred', 'gamipress', 'elementor', 'tutor', 'buddypress' );
        foreach ( $optional_indicators as $opt ) {
            if ( strpos( $file, $opt ) !== false ) {
                return 'enhances';
            }
        }

        return 'optional';
    }

    /**
     * Get a summary of WC plugin counts across the network.
     *
     * @return array [ 'total_sites' => int, 'wc_sites' => int, 'total_plugins' => int, 'by_type' => [...] ]
     */
    public static function get_network_summary() {
        $map = self::get_network_map();

        $summary = array(
            'total_sites'   => count( get_sites( array( 'number' => 500 ) ) ),
            'wc_sites'      => count( $map ),
            'total_plugins' => 0,
            'active_plugins'=> 0,
            'by_type'       => array(),
        );

        foreach ( $map as $blog_id => $site_data ) {
            foreach ( $site_data['plugins'] as $plugin ) {
                $summary['total_plugins']++;
                if ( $plugin['active'] ) {
                    $summary['active_plugins']++;
                }
                $type = $plugin['type'];
                if ( ! isset( $summary['by_type'][ $type ] ) ) {
                    $summary['by_type'][ $type ] = 0;
                }
                $summary['by_type'][ $type ]++;
            }
        }

        return $summary;
    }

    /**
     * Check if a specific plugin is active on a given site.
     *
     * @param int    $blog_id
     * @param string $plugin_slug  e.g. 'booster-for-woocommerce'
     * @return bool
     */
    public static function is_plugin_active_on_site( $blog_id, $plugin_slug ) {
        $plugins = self::get_site_wc_plugins( $blog_id );
        foreach ( $plugins as $plugin ) {
            if ( strpos( strtolower( $plugin['file'] ), strtolower( $plugin_slug ) ) !== false ) {
                return $plugin['active'];
            }
        }
        return false;
    }

    /**
     * Get all sites where a specific plugin is active.
     *
     * @param string $plugin_slug
     * @return array [ blog_id => site_name ]
     */
    public static function get_sites_with_plugin( $plugin_slug ) {
        $map    = self::get_network_map();
        $result = array();

        foreach ( $map as $blog_id => $site_data ) {
            foreach ( $site_data['plugins'] as $plugin ) {
                if ( strpos( strtolower( $plugin['file'] ), strtolower( $plugin_slug ) ) !== false
                    && $plugin['active'] ) {
                    $result[ $blog_id ] = $site_data['site_name'];
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * AJAX handler for on-demand network scan.
     */
    public static function ajax_scan() {
        if ( ! current_user_can( 'manage_network' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        check_ajax_referer( 'znc_network_admin', 'nonce' );

        $map     = self::get_network_map( true );
        $summary = self::get_network_summary();

        wp_send_json_success( array(
            'map'     => $map,
            'summary' => $summary,
        ) );
    }
}
