<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Deactivator {
    public static function deactivate() {
        delete_site_transient( 'znc_host_urls_' . get_main_site_id() );
    }
}
