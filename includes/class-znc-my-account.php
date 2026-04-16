<?php
/**
 * My Account — Adds Net Cart Orders tab to WooCommerce My Account.
 *
 * v1.7.1 FIX: Constructor now accepts 0 args (uses singleton internally)
 *             to match the v1.7.0 bootstrap which calls new ZNC_My_Account().
 *             Still accepts 1 arg for backward compat.
 *
 * @package ZincklesNetCart
 * @since   1.6.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_My_Account {

    /** @var ZNC_Checkout_Host */
    private $host;

    /**
     * Constructor — accepts 0 or 1 argument.
     *
     * v1.7.0 bootstrap calls: new ZNC_My_Account()      (0 args)
     * v1.6.x bootstrap calls: new ZNC_My_Account($host) (1 arg)
     *
     * Both signatures now work.
     */
    public function __construct( $host = null ) {
        $this->host = $host instanceof ZNC_Checkout_Host
            ? $host
            : ZNC_Checkout_Host::instance();
    }

    public function init() {
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
        add_action( 'woocommerce_account_net-cart-orders_endpoint', array( $this, 'render_orders' ) );
        add_action( 'init', function () {
            add_rewrite_endpoint( 'net-cart-orders', EP_ROOT | EP_PAGES );
        } );
    }

    public function add_menu_item( $items ) {
        $items['net-cart-orders'] = __( 'Net Cart Orders', 'zinckles-net-cart' );
        return $items;
    }

    public function render_orders() {
        echo do_shortcode( '[znc_order_history]' );
    }
}
