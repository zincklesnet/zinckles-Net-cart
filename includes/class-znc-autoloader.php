<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Autoloader {
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }
    public static function autoload( $class ) {
        if ( strpos( $class, 'ZNC_' ) !== 0 ) return;
        $file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        $path = ZNC_PLUGIN_DIR . 'includes/' . $file;
        if ( file_exists( $path ) ) require_once $path;
    }
}
