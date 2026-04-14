<?php
/**
 * PSR-4-style autoloader for Zinckles Net Cart.
 *
 * Maps class prefixes:  ZNC_ → includes/class-znc-*.php
 *                       ZNC_ → admin/class-znc-*.php
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Autoloader {

    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    public static function autoload( $class ) {
        if ( 0 !== strpos( $class, 'ZNC_' ) ) {
            return;
        }

        $file = 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
        $dirs = array(
            ZNC_PLUGIN_DIR . 'includes/',
            ZNC_PLUGIN_DIR . 'admin/',
        );

        foreach ( $dirs as $dir ) {
            $path = $dir . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
                return;
            }
        }
    }
}
