<?php
/**
 * Main Site Admin — alias for ZNC_Main_Admin.
 * Keeps autoloader compatibility with the original repo file naming.
 *
 * @package ZincklesNetCart
 * @since   1.2.0
 */
defined( 'ABSPATH' ) || exit;

// This file exists for autoloader compatibility.
// All functionality lives in class-znc-main-admin.php.
if ( ! class_exists( 'ZNC_Main_Site_Admin' ) ) {
    class ZNC_Main_Site_Admin extends ZNC_Main_Admin {
        // Alias only.
    }
}
