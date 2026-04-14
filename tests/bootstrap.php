<?php
/**
 * PHPUnit Bootstrap for Zinckles Net Cart.
 *
 * Requires WP test library + WooCommerce test helpers.
 * Set WP_TESTS_DIR and WC_TESTS_DIR environment variables before running.
 *
 * Usage:
 *   WP_TESTS_DIR=/path/to/wp-tests-lib \
 *   WC_TESTS_DIR=/path/to/woocommerce/tests \
 *   vendor/bin/phpunit
 */

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
$wc_tests_dir = getenv( 'WC_TESTS_DIR' ) ?: '/tmp/woocommerce/tests';

// Load WP test suite bootstrap.
require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for tests.
 */
tests_add_filter( 'muplugins_loaded', function () {
    // Simulate multisite.
    if ( ! defined( 'MULTISITE' ) ) {
        define( 'MULTISITE', true );
    }

    require dirname( __DIR__ ) . '/zinckles-net-cart.php';
} );

require $wp_tests_dir . '/includes/bootstrap.php';

// Load WC test helpers if available.
if ( file_exists( $wc_tests_dir . '/legacy/framework/class-wc-unit-test-case.php' ) ) {
    require_once $wc_tests_dir . '/legacy/framework/class-wc-unit-test-case.php';
}
