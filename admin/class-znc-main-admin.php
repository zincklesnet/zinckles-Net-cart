<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Main_Admin {

    private $store;

    public function __construct( ZNC_Global_Cart_Store $store = null ) {
        $this->store = $store ?: new ZNC_Global_Cart_Store();
    }

    public function init() {
        if ( ! is_admin() || ! is_main_site() ) return;
        add_action( 'admin_menu', array( $this, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_znc_clear_user_cart', array( $this, 'ajax_clear_cart' ) );
        add_action( 'wp_ajax_znc_flush_cache', array( $this, 'ajax_flush_cache' ) );
    }

    public function add_menus() {
        add_menu_page(
            __( 'Net Cart', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_woocommerce',
            'znc-settings',
            array( $this, 'render_settings' ),
            'dashicons-cart',
            56
        );

        $pages = array(
            'znc-settings'      => __( 'Settings', 'zinckles-net-cart' ),
            'znc-cart-display'   => __( 'Cart Display', 'zinckles-net-cart' ),
            'znc-checkout'       => __( 'Checkout', 'zinckles-net-cart' ),
            'znc-currency'       => __( 'Currency & ZCreds', 'zinckles-net-cart' ),
            'znc-orders'         => __( 'Orders', 'zinckles-net-cart' ),
            'znc-cart-browser'   => __( 'Cart Browser', 'zinckles-net-cart' ),
            'znc-notifications'  => __( 'Notifications', 'zinckles-net-cart' ),
            'znc-performance'    => __( 'Performance', 'zinckles-net-cart' ),
        );

        foreach ( $pages as $slug => $title ) {
            $callback = 'render_' . str_replace( array( 'znc-', '-' ), array( '', '_' ), $slug );
            add_submenu_page(
                'znc-settings',
                $title,
                $title,
                'manage_woocommerce',
                $slug,
                array( $this, $callback )
            );
        }
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'znc-' ) === false ) return;
        wp_enqueue_style( 'znc-admin', ZNC_PLUGIN_URL . 'admin/assets/admin.css', array(), ZNC_VERSION );
        wp_enqueue_script( 'znc-admin', ZNC_PLUGIN_URL . 'admin/assets/admin.js', array( 'jquery' ), ZNC_VERSION, true );
        wp_localize_script( 'znc-admin', 'zncAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'znc_admin_nonce' ),
        ) );

        if ( strpos( $hook, 'znc-currency' ) !== false || strpos( $hook, 'znc-cart-display' ) !== false ) {
            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
        }
    }

    /* ── Save handler ─────────────────────────────────────── */

    private function save_if_posted( string $group ) {
        if ( ! isset( $_POST['znc_save_' . $group] ) ) return;
        check_admin_referer( 'znc_' . $group . '_nonce' );

        $settings = get_option( 'znc_main_settings', array() );
        $fields   = $this->get_fields( $group );

        foreach ( $fields as $key => $type ) {
            if ( 'bool' === $type ) {
                $settings[ $key ] = ! empty( $_POST[ $key ] );
            } elseif ( 'int' === $type ) {
                $settings[ $key ] = intval( $_POST[ $key ] ?? 0 );
            } elseif ( 'float' === $type ) {
                $settings[ $key ] = floatval( $_POST[ $key ] ?? 0 );
            } elseif ( 'array' === $type ) {
                $settings[ $key ] = array_map( 'sanitize_text_field', (array) ( $_POST[ $key ] ?? array() ) );
            } elseif ( 'json' === $type ) {
                $raw = $_POST[ $key ] ?? '{}';
                $settings[ $key ] = json_decode( stripslashes( $raw ), true ) ?: array();
            } else {
                $settings[ $key ] = sanitize_text_field( $_POST[ $key ] ?? '' );
            }
        }

        update_option( 'znc_main_settings', $settings );
        add_settings_error( 'znc', 'znc_saved', __( 'Settings saved.', 'zinckles-net-cart' ), 'success' );
    }

    private function get_fields( string $group ) : array {
        $map = array(
            'settings' => array(
                'cart_page_id' => 'int', 'checkout_page_id' => 'int', 'thankyou_page_id' => 'int',
                'min_order_amount' => 'float', 'max_order_amount' => 'float',
                'require_account' => 'bool', 'allow_guest_checkout' => 'bool',
                'merge_duplicates' => 'bool', 'quantity_cap' => 'int',
            ),
            'cart_display' => array(
                'layout_style' => 'string', 'show_shop_badges' => 'bool', 'show_origin_links' => 'bool',
                'show_currency_breakdown' => 'bool', 'show_zcred_widget' => 'bool',
                'empty_cart_message' => 'string', 'header_icon_style' => 'string',
                'conversion_display' => 'string', 'rounding_precision' => 'int',
            ),
            'checkout' => array(
                'steps_display' => 'string', 'pre_checkout_refresh' => 'bool',
                'price_change_action' => 'string', 'stock_change_action' => 'string',
                'coupon_support' => 'bool', 'coupon_scope' => 'string',
                'shipping_aggregation' => 'string', 'tax_display' => 'string',
                'payment_gateways' => 'array', 'split_pay_mode' => 'bool',
            ),
            'currency' => array(
                'rate_source' => 'string', 'rate_api_provider' => 'string', 'rate_api_key' => 'string',
                'rate_refresh_hours' => 'int', 'exchange_rates' => 'json',
                'zcred_checkout_enabled' => 'bool', 'zcred_input_style' => 'string',
                'zcred_show_balance' => 'bool', 'zcred_earn_enabled' => 'bool',
                'zcred_earn_rate' => 'float', 'zcred_excluded_sites' => 'array',
            ),
            'orders' => array(
                'order_prefix_parent' => 'string', 'order_prefix_child' => 'string',
                'default_parent_status' => 'string', 'default_child_status' => 'string',
                'auto_complete_digital' => 'bool', 'verbose_notes' => 'bool',
                'sync_child_status' => 'bool',
            ),
            'notifications' => array(
                'notify_customer' => 'bool', 'notify_shop_admin' => 'bool', 'notify_network_admin' => 'bool',
                'admin_email_override' => 'string', 'zcred_notice' => 'bool',
                'slack_webhook' => 'string', 'slack_enabled' => 'bool',
            ),
            'performance' => array(
                'cache_shop_settings' => 'bool', 'cache_ttl_minutes' => 'int',
                'async_cart_push' => 'bool', 'parallel_validation' => 'bool',
            ),
        );
        return $map[ $group ] ?? array();
    }

    /* ── AJAX handlers ────────────────────────────────────── */

    public function ajax_clear_cart() {
        check_ajax_referer( 'znc_admin_nonce', 'nonce' );
        $user_id = intval( $_POST['user_id'] ?? 0 );
        if ( $user_id ) {
            $this->store->clear_cart( $user_id );
            wp_send_json_success( array( 'message' => 'Cart cleared.' ) );
        }
        wp_send_json_error( array( 'message' => 'Invalid user.' ) );
    }

    public function ajax_flush_cache() {
        check_ajax_referer( 'znc_admin_nonce', 'nonce' );
        global $wpdb;
        $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_znc_%' OR option_name LIKE '_transient_timeout_znc_%'" );
        wp_send_json_success( array( 'message' => 'Cache flushed.' ) );
    }

    /* ── Page renderers ───────────────────────────────────── */

    public function render_settings() {
        $this->save_if_posted( 'settings' );
        $settings = get_option( 'znc_main_settings', array() );
        include ZNC_PLUGIN_DIR . 'admin/views/main-settings.php';
    }

    public function render_cart_display() {
        $this->save_if_posted( 'cart_display' );
        $settings = get_option( 'znc_main_settings', array() );
        include ZNC_PLUGIN_DIR . 'admin/views/main-cart-display.php';
    }

    public function render_checkout() {
        $this->save_if_posted( 'checkout' );
        $settings = get_option( 'znc_main_settings', array() );
        include ZNC_PLUGIN_DIR . 'admin/views/main-checkout.php';
    }

    public function render_currency() {
        $this->save_if_posted( 'currency' );
        $settings = get_option( 'znc_main_settings', array() );
        include ZNC_PLUGIN_DIR . 'admin/views/main-currency.php';
    }

    public function render_orders() {
        $this->save_if_posted( 'orders' );
        $settings = get_option( 'znc_main_settings', array() );
        include ZNC_PLUGIN_DIR . 'admin/views/main-orders.php';
    }

    public function render_cart_browser() {
        $settings = get_option( 'znc_main_settings', array() );
        include ZNC_PLUGIN_DIR . 'admin/views/main-cart-browser.php';
    }

    public function render_notifications() {
        $this->save_if_posted( 'notifications' );
        $settings = get_option( 'znc_main_settings', array() );
        include ZNC_PLUGIN_DIR . 'admin/views/main-notifications.php';
    }

    public function render_performance() {
        $this->save_if_posted( 'performance' );
        $settings = get_option( 'znc_main_settings', array() );
        include ZNC_PLUGIN_DIR . 'admin/views/main-performance.php';
    }
}
