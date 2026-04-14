<?php
/**
 * Network Settings — thin wrapper that delegates to ZNC_Network_Admin.
 *
 * v1.2.0: The original main plugin referenced ZNC_Network_Settings but
 * all logic lived in ZNC_Network_Admin. This class bridges the gap
 * so existing code doesn't break.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Network_Settings {

    public function init() {
        // Delegate to ZNC_Network_Admin — the real implementation.
        // ZNC_Network_Admin::init() is now called directly from the main plugin file.
    }

    /**
     * Proxy to ZNC_Network_Admin::get_settings().
     */
    public static function get_settings() {
        return ZNC_Network_Admin::get_settings();
    }

    /**
     * Proxy to ZNC_Network_Admin::get().
     */
    public static function get( $key, $fallback = null ) {
        return ZNC_Network_Admin::get( $key, $fallback );
    }

    /**
     * Proxy to ZNC_Network_Admin::is_site_enrolled().
     */
    public static function is_site_enrolled( $blog_id ) {
        return ZNC_Network_Admin::is_site_enrolled( $blog_id );
    }

    /**
     * Proxy to ZNC_Network_Admin::get_enrolled_sites().
     */
    public static function get_enrolled_sites() {
        return ZNC_Network_Admin::get_enrolled_sites();
    }
}
