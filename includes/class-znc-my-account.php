<?php
defined('ABSPATH') || exit;
class ZNC_My_Account {
    private $host;
    public function __construct(ZNC_Checkout_Host $h) { $this->host = $h; }
    public function init() {
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu_item']);
        add_action('woocommerce_account_net-cart-orders_endpoint', [$this, 'render_orders']);
        add_action('init', function() { add_rewrite_endpoint('net-cart-orders', EP_ROOT | EP_PAGES); });
    }
    public function add_menu_item($items) {
        $items['net-cart-orders'] = 'Net Cart Orders';
        return $items;
    }
    public function render_orders() {
        echo do_shortcode('[znc_order_history]');
    }
}
