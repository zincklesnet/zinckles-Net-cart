<?php
/**
 * Autoloader — PSR-4-ish loader for ZNC_ classes.
 *
 * Maps class names like ZNC_Global_Cart to includes/class-znc-global-cart.php.
 *
 * @package ZincklesNetCart
 * @since   1.7.2
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Autoloader {

    /**
     * Register the autoloader.
     */
    public static function register() {
        spl_autoload_register( array( __CLASS__, 'autoload' ) );
    }

    /**
     * Autoload a ZNC_ prefixed class.
     *
     * @param string $class_name
     */
    public static function autoload( $class_name ) {
        // Only handle ZNC_ classes
        if ( 0 !== strpos( $class_name, 'ZNC_' ) ) {
            return;
        }

        // Convert class name to filename
        // ZNC_Global_Cart → class-znc-global-cart.php
        $file_name = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';
        $file_path = ZNC_PLUGIN_DIR . 'includes/' . $file_name;

        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
}
