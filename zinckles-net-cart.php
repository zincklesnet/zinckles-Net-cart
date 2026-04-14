<?php
/**
 * Plugin Name:  Zinckles Net Cart
 * Plugin URI:   https://zinckles.com/net-cart
 * Description:  Unified multisite cart — aggregate WooCommerce products from multiple subsites
 *               into a single checkout on the main site, with mixed currency support,
 *               MyCred (ZCreds) integration, parent/child orders, and inventory sync.
 * Version:      1.2.0
 * Author:       Zinckles
 * Author URI:   https://zinckles.com
 * License:      GPL-2.0-or-later
 * Network:      true
 * Text Domain:  zinckles-net-cart
 * Domain Path:  /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

/* ── Constants ────────────────────────────────────────── */
define( 'ZNC_VERSION',     '1.2.0' );
define( 'ZNC_PLUGIN_FILE', __FILE__ );
define( 'ZNC_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ZNC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'ZNC_DB_VERSION',  '1.2.0' );

/* ── Autoloader ───────────────────────────────────────── */
require_once ZNC_PLUGIN_DIR . 'includes/class-znc-autoloader.php';
ZNC_Autoloader::register();

/* ── Activation / Deactivation ──────────────────────── */
register_activation_hook( __FILE__, array( 'ZNC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ZNC_Deactivator', 'deactivate' ) );

/* ── Bootstrap ────────────────────────────────────────── */
add_action( 'plugins_loaded', 'znc_bootstrap', 20 );

function znc_bootstrap() {

    /* ── Network Admin (always — registers menus + AJAX handlers) ── */
    ZNC_Network_Admin::init();

    /* ── Subsite modules ──────────────────────────────── */
    if ( ! is_main_site() ) {
        $snapshot = new ZNC_Cart_Snapshot();
        $snapshot->init();

        $shop = new ZNC_Shop_Settings();
        $shop->init();

        $subsite_admin = new ZNC_Subsite_Admin();
        $subsite_admin->init();
    }

    /* ── Main-site modules ────────────────────────────── */
    if ( is_main_site() ) {
        $store    = new ZNC_Global_Cart_Store();
        $store->init();

        $currency = new ZNC_Currency_Handler();
        $currency->init();

        $mycred   = new ZNC_MyCred_Engine();
        $mycred->init();

        $merger   = new ZNC_Global_Cart_Merger( $store, $currency );
        $merger->init();

        $inventory = new ZNC_Inventory_Sync();
        $inventory->init();

        $orders   = new ZNC_Order_Factory();
        $orders->init();

        $checkout = new ZNC_Checkout_Orchestrator(
            $store, $merger, $currency, $mycred, $orders, $inventory
        );
        $checkout->init();

        $main_admin = new ZNC_Main_Admin( $store );
        $main_admin->init();
    }

    /* ── REST Endpoints (all sites) ───────────────────── */
    $rest = new ZNC_REST_Endpoints();
    $rest->init();

    $auth = new ZNC_REST_Auth();
    $auth->init();

    /* ── Admin Loader (bridges settings → modules) ───── */
    $loader = new ZNC_Admin_Loader();
    $loader->init();

    /* ── Admin Bar ────────────────────────────────────── */
    add_action( 'admin_bar_menu', 'znc_admin_bar_links', 100 );
}

/**
 * Admin-bar quick links.
 */
function znc_admin_bar_links( $wp_admin_bar ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $wp_admin_bar->add_node( array(
        'id'    => 'znc-net-cart',
        'title' => '🛒 Net Cart',
        'href'  => is_main_site()
            ? admin_url( 'admin.php?page=znc-settings' )
            : admin_url( 'admin.php?page=znc-subsite' ),
    ) );
}
