<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Deactivator {

    public static function deactivate( $network_wide ) {
        if ( $network_wide && is_multisite() ) {
            $sites = get_sites( array( 'number' => 0 ) );
            foreach ( $sites as $site ) {
                switch_to_blog( $site->blog_id );
                self::single_deactivate();
                restore_current_blog();
            }
        } else {
            self::single_deactivate();
        }
    }

    private static function single_deactivate() {
        wp_clear_scheduled_hook( 'znc_cart_cleanup' );
        wp_clear_scheduled_hook( 'znc_inventory_retry' );
        flush_rewrite_rules();
    }
}
