<?php
/**
 * Deactivator — Cleanup on plugin deactivation.
 *
 * @package ZincklesNetCart
 * @since   1.7.2
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Deactivator {

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate() {
        // Clear scheduled cron events
        wp_clear_scheduled_hook( 'znc_daily_cart_purge' );

        // Flush rewrite rules to remove our endpoints
        flush_rewrite_rules();

        // Clear transient caches
        delete_site_transient( 'znc_wc_plugin_map' );
        delete_site_transient( 'znc_network_wc_plugins' );
    }
}
