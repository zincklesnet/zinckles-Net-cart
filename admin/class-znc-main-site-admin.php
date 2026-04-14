<?php
/**
 * Zinckles Net Cart — Main Site Admin Settings
 *
 * Controls cart display, checkout flow, currency conversion rates,
 * MyCred per-site overrides, order handling, notifications, and
 * the live cart browser for customer support.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZNC_Main_Site_Admin {

    /** Option key in wp_options (main site). */
    const OPTION_KEY = 'znc_main_site_settings';

    /** Default settings. */
    private static $defaults = array(
        // ── Cart Display ────────────────────────────────────────
        'cart_page_id'              => 0,
        'checkout_page_id'          => 0,
        'thankyou_page_id'          => 0,
        'cart_layout'               => 'grouped',       // grouped | flat | tabbed
        'show_shop_badges'          => true,
        'show_origin_links'         => true,
        'show_currency_breakdown'   => true,
        'show_zcred_widget'         => true,
        'empty_cart_message'        => '',
        'cart_icon_style'           => 'badge',          // badge | count | hidden

        // ── Cart Rules ──────────────────────────────────────────
        'min_order_amount'          => 0,
        'max_order_amount'          => 0,                // 0 = unlimited
        'require_account'           => true,
        'guest_checkout'            => false,
        'merge_duplicates'          => true,             // merge same product from same shop
        'quantity_cap_per_item'     => 99,

        // ── Currency Conversion ─────────────────────────────────
        'exchange_rate_source'      => 'manual',         // manual | api
        'exchange_api_provider'     => 'exchangerate',   // exchangerate | openexchangerates | fixer
        'exchange_api_key'          => '',
        'exchange_api_refresh'      => 3600,             // seconds between API pulls
        'manual_rates'              => array(
            'USD' => 1.00,
            'EUR' => 0.92,
            'GBP' => 0.79,
            'CAD' => 1.37,
            'AUD' => 1.53,
            'JPY' => 149.50,
        ),
        'conversion_display'        => 'both',          // original | converted | both
        'rounding_precision'        => 2,

        // ── MyCred / ZCreds (Main Site Overrides) ───────────────
        'zcred_checkout_enabled'    => true,
        'zcred_slider'              => true,             // slider vs. fixed input
        'zcred_min_applicable'      => 0,
        'zcred_display_balance'     => true,
        'zcred_earn_on_purchase'    => false,
        'zcred_earn_rate'           => 1,                // points per $1 spent
        'zcred_excluded_categories' => array(),
        'zcred_excluded_shops'      => array(),

        // ── Checkout Flow ───────────────────────────────────────
        'checkout_steps_display'    => 'progress',       // progress | accordion | single
        'pre_checkout_refresh'      => true,             // re-validate all lines before pay
        'price_change_action'       => 'block',          // block | warn | accept
        'stock_change_action'       => 'remove',         // remove | reduce | block
        'coupon_support'            => true,
        'coupon_scope'              => 'per-shop',       // per-shop | global | both
        'shipping_aggregation'      => 'per-shop',       // per-shop | flat | highest
        'tax_display'               => 'inclusive',      // inclusive | exclusive | both

        // ── Payment Gateways ────────────────────────────────────
        'payment_gateways'          => array( 'stripe', 'paypal' ),
        'gateway_split_mode'        => 'single',         // single | split (pay per shop)

        // ── Order Handling ──────────────────────────────────────
        'parent_order_status'       => 'processing',
        'child_order_status'        => 'processing',
        'order_number_prefix'       => 'ZNC-',
        'child_order_prefix'        => 'ZNC-SUB-',
        'order_notes_verbose'       => true,
        'auto_complete_digital'     => true,

        // ── Notifications ───────────────────────────────────────
        'email_customer_receipt'    => true,
        'email_shop_notification'   => true,
        'email_admin_summary'       => true,
        'admin_email_override'      => '',               // empty = default admin email
        'slack_webhook_url'         => '',
        'notify_on_zcred_deduct'    => true,

        // ── Performance ─────────────────────────────────────────
        'cache_shop_settings'       => true,
        'cache_ttl'                 => 300,              // seconds
        'async_cart_push'           => true,              // non-blocking add-to-cart push
        'batch_validation'          => true,              // validate all shops in parallel
    );

    /**
     * Boot the main site admin panel.
     */
    public static function init() {
        if ( ! is_main_site() ) {
            return;
        }

        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_znc_browse_carts', array( __CLASS__, 'ajax_browse_carts' ) );
        add_action( 'wp_ajax_znc_clear_user_cart', array( __CLASS__, 'ajax_clear_user_cart' ) );
        add_action( 'wp_ajax_znc_refresh_rates', array( __CLASS__, 'ajax_refresh_exchange_rates' ) );
        add_action( 'wp_ajax_znc_view_order_map', array( __CLASS__, 'ajax_view_order_map' ) );
    }

    /* ─── Menu ─────────────────────────────────────────────── */

    public static function register_menu() {
        // Top-level menu on main site.
        add_menu_page(
            __( 'Net Cart', 'znc' ),
            __( 'Net Cart', 'znc' ),
            'manage_woocommerce',
            'znc-settings',
            array( __CLASS__, 'render_settings_page' ),
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'znc-settings',
            __( 'General Settings', 'znc' ),
            __( 'Settings', 'znc' ),
            'manage_woocommerce',
            'znc-settings',
            array( __CLASS__, 'render_settings_page' )
        );

        add_submenu_page(
            'znc-settings',
            __( 'Cart Display', 'znc' ),
            __( 'Cart Display', 'znc' ),
            'manage_woocommerce',
            'znc-cart-display',
            array( __CLASS__, 'render_cart_display_page' )
        );

        add_submenu_page(
            'znc-settings',
            __( 'Checkout Flow', 'znc' ),
            __( 'Checkout', 'znc' ),
            'manage_woocommerce',
            'znc-checkout',
            array( __CLASS__, 'render_checkout_page' )
        );

        add_submenu_page(
            'znc-settings',
            __( 'Currency & ZCreds', 'znc' ),
            __( 'Currency & ZCreds', 'znc' ),
            'manage_woocommerce',
            'znc-currency',
            array( __CLASS__, 'render_currency_page' )
        );

        add_submenu_page(
            'znc-settings',
            __( 'Orders', 'znc' ),
            __( 'Orders', 'znc' ),
            'manage_woocommerce',
            'znc-orders',
            array( __CLASS__, 'render_orders_page' )
        );

        add_submenu_page(
            'znc-settings',
            __( 'Cart Browser', 'znc' ),
            __( 'Cart Browser', 'znc' ),
            'manage_woocommerce',
            'znc-cart-browser',
            array( __CLASS__, 'render_cart_browser_page' )
        );

        add_submenu_page(
            'znc-settings',
            __( 'Notifications', 'znc' ),
            __( 'Notifications', 'znc' ),
            'manage_woocommerce',
            'znc-notifications',
            array( __CLASS__, 'render_notifications_page' )
        );

        add_submenu_page(
            'znc-settings',
            __( 'Performance', 'znc' ),
            __( 'Performance', 'znc' ),
            'manage_woocommerce',
            'znc-performance',
            array( __CLASS__, 'render_performance_page' )
        );
    }

    /* ─── Settings API ─────────────────────────────────────── */

    public static function register_settings() {
        register_setting( 'znc_main_settings', self::OPTION_KEY, array(
            'type'              => 'array',
            'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
            'default'           => self::$defaults,
        ) );
    }

    /**
     * Get all main site settings merged with defaults.
     */
    public static function get_settings() {
        $saved = get_option( self::OPTION_KEY, array() );
        return wp_parse_args( $saved, self::$defaults );
    }

    /**
     * Get a single setting value.
     */
    public static function get( $key, $fallback = null ) {
        $settings = self::get_settings();
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $fallback;
    }

    /**
     * Update settings.
     */
    public static function update_settings( $new_values ) {
        $current = self::get_settings();
        $merged  = wp_parse_args( $new_values, $current );
        update_option( self::OPTION_KEY, $merged );
        do_action( 'znc_main_site_settings_updated', $merged, $current );
        return $merged;
    }

    /* ─── Sanitisation ─────────────────────────────────────── */

    public static function sanitize_settings( $input ) {
        $clean = array();

        // Cart Display.
        $clean['cart_page_id']            = absint( $input['cart_page_id'] ?? 0 );
        $clean['checkout_page_id']        = absint( $input['checkout_page_id'] ?? 0 );
        $clean['thankyou_page_id']        = absint( $input['thankyou_page_id'] ?? 0 );
        $clean['cart_layout']             = in_array( $input['cart_layout'] ?? '', array( 'grouped', 'flat', 'tabbed' ), true )
            ? $input['cart_layout'] : 'grouped';
        $clean['show_shop_badges']        = ! empty( $input['show_shop_badges'] );
        $clean['show_origin_links']       = ! empty( $input['show_origin_links'] );
        $clean['show_currency_breakdown'] = ! empty( $input['show_currency_breakdown'] );
        $clean['show_zcred_widget']       = ! empty( $input['show_zcred_widget'] );
        $clean['empty_cart_message']      = sanitize_textarea_field( $input['empty_cart_message'] ?? '' );
        $clean['cart_icon_style']         = in_array( $input['cart_icon_style'] ?? '', array( 'badge', 'count', 'hidden' ), true )
            ? $input['cart_icon_style'] : 'badge';

        // Cart Rules.
        $clean['min_order_amount']        = floatval( $input['min_order_amount'] ?? 0 );
        $clean['max_order_amount']        = floatval( $input['max_order_amount'] ?? 0 );
        $clean['require_account']         = ! empty( $input['require_account'] );
        $clean['guest_checkout']          = ! empty( $input['guest_checkout'] );
        $clean['merge_duplicates']        = ! empty( $input['merge_duplicates'] );
        $clean['quantity_cap_per_item']   = absint( $input['quantity_cap_per_item'] ?? 99 );

        // Currency.
        $clean['exchange_rate_source']    = in_array( $input['exchange_rate_source'] ?? '', array( 'manual', 'api' ), true )
            ? $input['exchange_rate_source'] : 'manual';
        $clean['exchange_api_provider']   = sanitize_key( $input['exchange_api_provider'] ?? 'exchangerate' );
        $clean['exchange_api_key']        = sanitize_text_field( $input['exchange_api_key'] ?? '' );
        $clean['exchange_api_refresh']    = absint( $input['exchange_api_refresh'] ?? 3600 );
        $clean['conversion_display']      = in_array( $input['conversion_display'] ?? '', array( 'original', 'converted', 'both' ), true )
            ? $input['conversion_display'] : 'both';
        $clean['rounding_precision']      = min( 4, max( 0, absint( $input['rounding_precision'] ?? 2 ) ) );

        // Manual rates.
        if ( isset( $input['manual_rates'] ) && is_array( $input['manual_rates'] ) ) {
            $clean['manual_rates'] = array();
            foreach ( $input['manual_rates'] as $code => $rate ) {
                $code = strtoupper( sanitize_text_field( $code ) );
                $clean['manual_rates'][ $code ] = floatval( $rate );
            }
        }

        // MyCred / ZCreds.
        $clean['zcred_checkout_enabled']    = ! empty( $input['zcred_checkout_enabled'] );
        $clean['zcred_slider']              = ! empty( $input['zcred_slider'] );
        $clean['zcred_min_applicable']      = floatval( $input['zcred_min_applicable'] ?? 0 );
        $clean['zcred_display_balance']     = ! empty( $input['zcred_display_balance'] );
        $clean['zcred_earn_on_purchase']    = ! empty( $input['zcred_earn_on_purchase'] );
        $clean['zcred_earn_rate']           = floatval( $input['zcred_earn_rate'] ?? 1 );
        $clean['zcred_excluded_categories'] = array_map( 'absint', (array) ( $input['zcred_excluded_categories'] ?? array() ) );
        $clean['zcred_excluded_shops']      = array_map( 'absint', (array) ( $input['zcred_excluded_shops'] ?? array() ) );

        // Checkout.
        $clean['checkout_steps_display']    = in_array( $input['checkout_steps_display'] ?? '', array( 'progress', 'accordion', 'single' ), true )
            ? $input['checkout_steps_display'] : 'progress';
        $clean['pre_checkout_refresh']      = ! empty( $input['pre_checkout_refresh'] );
        $clean['price_change_action']       = in_array( $input['price_change_action'] ?? '', array( 'block', 'warn', 'accept' ), true )
            ? $input['price_change_action'] : 'block';
        $clean['stock_change_action']       = in_array( $input['stock_change_action'] ?? '', array( 'remove', 'reduce', 'block' ), true )
            ? $input['stock_change_action'] : 'remove';
        $clean['coupon_support']            = ! empty( $input['coupon_support'] );
        $clean['coupon_scope']              = in_array( $input['coupon_scope'] ?? '', array( 'per-shop', 'global', 'both' ), true )
            ? $input['coupon_scope'] : 'per-shop';
        $clean['shipping_aggregation']      = in_array( $input['shipping_aggregation'] ?? '', array( 'per-shop', 'flat', 'highest' ), true )
            ? $input['shipping_aggregation'] : 'per-shop';
        $clean['tax_display']               = in_array( $input['tax_display'] ?? '', array( 'inclusive', 'exclusive', 'both' ), true )
            ? $input['tax_display'] : 'inclusive';

        // Payment.
        $clean['payment_gateways']          = array_map( 'sanitize_key', (array) ( $input['payment_gateways'] ?? array() ) );
        $clean['gateway_split_mode']        = in_array( $input['gateway_split_mode'] ?? '', array( 'single', 'split' ), true )
            ? $input['gateway_split_mode'] : 'single';

        // Orders.
        $clean['parent_order_status']       = sanitize_key( $input['parent_order_status'] ?? 'processing' );
        $clean['child_order_status']        = sanitize_key( $input['child_order_status'] ?? 'processing' );
        $clean['order_number_prefix']       = sanitize_text_field( $input['order_number_prefix'] ?? 'ZNC-' );
        $clean['child_order_prefix']        = sanitize_text_field( $input['child_order_prefix'] ?? 'ZNC-SUB-' );
        $clean['order_notes_verbose']       = ! empty( $input['order_notes_verbose'] );
        $clean['auto_complete_digital']     = ! empty( $input['auto_complete_digital'] );

        // Notifications.
        $clean['email_customer_receipt']    = ! empty( $input['email_customer_receipt'] );
        $clean['email_shop_notification']   = ! empty( $input['email_shop_notification'] );
        $clean['email_admin_summary']       = ! empty( $input['email_admin_summary'] );
        $clean['admin_email_override']      = sanitize_email( $input['admin_email_override'] ?? '' );
        $clean['slack_webhook_url']         = esc_url_raw( $input['slack_webhook_url'] ?? '' );
        $clean['notify_on_zcred_deduct']    = ! empty( $input['notify_on_zcred_deduct'] );

        // Performance.
        $clean['cache_shop_settings']       = ! empty( $input['cache_shop_settings'] );
        $clean['cache_ttl']                 = absint( $input['cache_ttl'] ?? 300 );
        $clean['async_cart_push']           = ! empty( $input['async_cart_push'] );
        $clean['batch_validation']          = ! empty( $input['batch_validation'] );

        return $clean;
    }

    /* ─── Cart Browser (Admin Tool) ────────────────────────── */

    /**
     * AJAX: Browse active carts for support / debugging.
     */
    public static function ajax_browse_carts() {
        check_ajax_referer( 'znc_main_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        global $wpdb;
        $table = $wpdb->base_prefix . 'znc_global_cart';
        $page  = absint( $_POST['page'] ?? 1 );
        $per   = 20;
        $offset = ( $page - 1 ) * $per;

        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $where  = '';
        if ( $search ) {
            $user = get_user_by( 'email', $search );
            if ( $user ) {
                $where = $wpdb->prepare( "WHERE user_id = %d", $user->ID );
            } else {
                $where = $wpdb->prepare( "WHERE user_id = %d", absint( $search ) );
            }
        }

        $total = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table} {$where}" );
        $rows  = $wpdb->get_results(
            "SELECT user_id, COUNT(*) AS item_count, 
                    GROUP_CONCAT(DISTINCT origin_blog_id) AS shops,
                    MAX(updated_at) AS last_updated
             FROM {$table} {$where}
             GROUP BY user_id
             ORDER BY last_updated DESC
             LIMIT {$per} OFFSET {$offset}"
        );

        $carts = array();
        foreach ( $rows as $row ) {
            $user = get_user_by( 'id', $row->user_id );
            $carts[] = array(
                'user_id'      => (int) $row->user_id,
                'user_email'   => $user ? $user->user_email : '(deleted)',
                'display_name' => $user ? $user->display_name : '(deleted)',
                'item_count'   => (int) $row->item_count,
                'shops'        => array_map( 'intval', explode( ',', $row->shops ) ),
                'last_updated' => $row->last_updated,
            );
        }

        wp_send_json_success( array(
            'carts' => $carts,
            'total' => (int) $total,
            'page'  => $page,
            'pages' => ceil( $total / $per ),
        ) );
    }

    /**
     * AJAX: Clear a user's cart (admin action).
     */
    public static function ajax_clear_user_cart() {
        check_ajax_referer( 'znc_main_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $user_id = absint( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_send_json_error( 'Invalid user' );
        }

        global $wpdb;
        $table   = $wpdb->base_prefix . 'znc_global_cart';
        $deleted = $wpdb->delete( $table, array( 'user_id' => $user_id ), array( '%d' ) );

        do_action( 'znc_admin_cart_cleared', $user_id, $deleted );

        wp_send_json_success( array(
            'user_id' => $user_id,
            'deleted' => $deleted,
        ) );
    }

    /**
     * AJAX: Pull exchange rates from API.
     */
    public static function ajax_refresh_exchange_rates() {
        check_ajax_referer( 'znc_main_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $settings = self::get_settings();
        $base     = ZNC_Network_Admin::get( 'base_currency', 'CAD' );
        $provider = $settings['exchange_api_provider'];
        $key      = $settings['exchange_api_key'];

        $urls = array(
            'exchangerate'       => "https://api.exchangerate-api.com/v4/latest/{$base}",
            'openexchangerates'  => "https://openexchangerates.org/api/latest.json?app_id={$key}&base={$base}",
            'fixer'              => "http://data.fixer.io/api/latest?access_key={$key}&base={$base}",
        );

        if ( ! isset( $urls[ $provider ] ) ) {
            wp_send_json_error( 'Unknown provider' );
        }

        $response = wp_remote_get( $urls[ $provider ], array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $body  = json_decode( wp_remote_retrieve_body( $response ), true );
        $rates = $body['rates'] ?? array();

        if ( ! empty( $rates ) ) {
            $settings['manual_rates'] = array_map( 'floatval', $rates );
            self::update_settings( $settings );
        }

        wp_send_json_success( array(
            'rates'   => $rates,
            'source'  => $provider,
            'fetched' => current_time( 'mysql' ),
        ) );
    }

    /**
     * AJAX: View order map (parent → children).
     */
    public static function ajax_view_order_map() {
        check_ajax_referer( 'znc_main_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $parent_id = absint( $_POST['order_id'] ?? 0 );
        global $wpdb;
        $table = $wpdb->base_prefix . 'znc_order_map';
        $children = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE parent_order_id = %d ORDER BY origin_blog_id",
                $parent_id
            )
        );

        wp_send_json_success( array(
            'parent_id' => $parent_id,
            'children'  => $children,
        ) );
    }

    /* ─── Render Pages ─────────────────────────────────────── */

    public static function render_settings_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/main-settings.php';
    }

    public static function render_cart_display_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/main-cart-display.php';
    }

    public static function render_checkout_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/main-checkout.php';
    }

    public static function render_currency_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/main-currency.php';
    }

    public static function render_orders_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/main-orders.php';
    }

    public static function render_cart_browser_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/main-cart-browser.php';
    }

    public static function render_notifications_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/main-notifications.php';
    }

    public static function render_performance_page() {
        $settings = self::get_settings();
        include __DIR__ . '/views/main-performance.php';
    }
}
