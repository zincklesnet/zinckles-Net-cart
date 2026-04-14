<?php
/**
 * Zinckles Net Cart — My Account Integration
 *
 * Adds Net Cart Orders tab to WooCommerce My Account with full
 * cross-site order history, currency/ZCred breakdowns, and subsite details.
 *
 * @package Zinckles_Net_Cart
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZNC_My_Account {

    /** @var string Endpoint slug */
    const ENDPOINT = 'net-cart-orders';

    /** @var string Detail endpoint slug */
    const DETAIL_ENDPOINT = 'net-cart-order';

    /** @var ZNC_Order_Query */
    private $order_query;

    /**
     * Boot the My Account integration.
     */
    public function __construct() {
        $this->order_query = new ZNC_Order_Query();

        // Register endpoints.
        add_action( 'init', array( $this, 'register_endpoints' ) );

        // Add menu items.
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_items' ), 20 );

        // Endpoint content.
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'render_orders_page' ) );
        add_action( 'woocommerce_account_' . self::DETAIL_ENDPOINT . '_endpoint', array( $this, 'render_order_detail' ) );

        // Endpoint titles.
        add_filter( 'the_title', array( $this, 'endpoint_title' ), 10, 2 );

        // Dashboard widget.
        add_action( 'woocommerce_account_dashboard', array( $this, 'render_dashboard_widget' ), 15 );

        // Enqueue assets.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Query vars.
        add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_vars' ) );

        // Flush rewrite rules on activation.
        add_action( 'znc_activation', array( $this, 'flush_rewrites' ) );

        // Order status change hooks — update account cache.
        add_action( 'woocommerce_order_status_changed', array( $this, 'invalidate_cache' ), 10, 3 );

        // Add Net Cart badge to standard WC orders list.
        add_action( 'woocommerce_my_account_my_orders_column_order-number', array( $this, 'add_netcart_badge' ), 20 );
    }

    /**
     * Register WP rewrite endpoints.
     */
    public function register_endpoints() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
        add_rewrite_endpoint( self::DETAIL_ENDPOINT, EP_ROOT | EP_PAGES );
    }

    /**
     * Add query vars for endpoints.
     *
     * @param array $vars Existing query vars.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[ self::ENDPOINT ]        = self::ENDPOINT;
        $vars[ self::DETAIL_ENDPOINT ] = self::DETAIL_ENDPOINT;
        return $vars;
    }

    /**
     * Insert Net Cart Orders tab into My Account menu.
     *
     * @param array $items Menu items.
     * @return array
     */
    public function add_menu_items( $items ) {
        $new_items = array();
        foreach ( $items as $key => $label ) {
            $new_items[ $key ] = $label;
            // Insert after "Orders".
            if ( 'orders' === $key ) {
                $new_items[ self::ENDPOINT ] = __( 'Net Cart Orders', 'zinckles-net-cart' );
            }
        }
        return $new_items;
    }

    /**
     * Set page title for endpoints.
     *
     * @param string $title Page title.
     * @param int    $id    Post ID.
     * @return string
     */
    public function endpoint_title( $title, $id = 0 ) {
        if ( ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
            global $wp_query;
            if ( isset( $wp_query->query_vars[ self::ENDPOINT ] ) ) {
                return __( 'Net Cart Orders', 'zinckles-net-cart' );
            }
            if ( isset( $wp_query->query_vars[ self::DETAIL_ENDPOINT ] ) ) {
                return __( 'Net Cart Order Details', 'zinckles-net-cart' );
            }
        }
        return $title;
    }

    /**
     * Enqueue front-end assets on My Account pages.
     */
    public function enqueue_assets() {
        if ( ! is_account_page() ) {
            return;
        }

        wp_enqueue_style(
            'znc-my-account',
            ZNC_PLUGIN_URL . 'assets/css/znc-my-account.css',
            array(),
            ZNC_VERSION
        );

        wp_enqueue_script(
            'znc-my-account',
            ZNC_PLUGIN_URL . 'assets/js/znc-my-account.js',
            array( 'jquery' ),
            ZNC_VERSION,
            true
        );

        wp_localize_script( 'znc-my-account', 'zncMyAccount', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'znc_my_account' ),
            'i18n'     => array(
                'loading'    => __( 'Loading order details...', 'zinckles-net-cart' ),
                'error'      => __( 'Could not load order details.', 'zinckles-net-cart' ),
                'noOrders'   => __( 'No Net Cart orders found.', 'zinckles-net-cart' ),
                'viewDetail' => __( 'View Details', 'zinckles-net-cart' ),
                'allShops'   => __( 'All Shops', 'zinckles-net-cart' ),
                'allStatus'  => __( 'All Statuses', 'zinckles-net-cart' ),
                'allCurrency'=> __( 'All Currencies', 'zinckles-net-cart' ),
            ),
        ) );
    }

    // ─── Render Methods ────────────────────────────────────────────

    /**
     * Render the main Net Cart Orders list page.
     */
    public function render_orders_page() {
        $user_id      = get_current_user_id();
        $current_page = max( 1, absint( get_query_var( 'paged', 1 ) ) );
        $per_page     = 10;

        // Filters.
        $filters = array(
            'shop_id'  => isset( $_GET['shop'] ) ? absint( $_GET['shop'] ) : 0,
            'status'   => isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '',
            'currency' => isset( $_GET['currency'] ) ? sanitize_text_field( $_GET['currency'] ) : '',
            'date_from'=> isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '',
            'date_to'  => isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '',
            'search'   => isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '',
        );

        $result = $this->order_query->get_user_orders( $user_id, $current_page, $per_page, $filters );

        // Available filter options.
        $filter_options = $this->order_query->get_filter_options( $user_id );

        // Stats summary.
        $stats = $this->order_query->get_user_stats( $user_id );

        include ZNC_PLUGIN_DIR . 'templates/myaccount/net-cart-orders.php';
    }

    /**
     * Render single Net Cart order detail page.
     */
    public function render_order_detail() {
        $parent_order_id = absint( get_query_var( self::DETAIL_ENDPOINT ) );
        $user_id         = get_current_user_id();

        if ( ! $parent_order_id ) {
            wc_print_notice( __( 'Invalid order.', 'zinckles-net-cart' ), 'error' );
            return;
        }

        $order_data = $this->order_query->get_order_detail( $parent_order_id, $user_id );

        if ( is_wp_error( $order_data ) ) {
            wc_print_notice( $order_data->get_error_message(), 'error' );
            return;
        }

        include ZNC_PLUGIN_DIR . 'templates/myaccount/net-cart-order-detail.php';
    }

    /**
     * Render dashboard widget showing recent Net Cart activity.
     */
    public function render_dashboard_widget() {
        $user_id = get_current_user_id();
        $stats   = $this->order_query->get_user_stats( $user_id );
        $recent  = $this->order_query->get_user_orders( $user_id, 1, 3 );

        include ZNC_PLUGIN_DIR . 'templates/myaccount/net-cart-dashboard.php';
    }

    /**
     * Add "Net Cart" badge to standard WC orders that are Net Cart parent orders.
     *
     * @param WC_Order $order The order object.
     */
    public function add_netcart_badge( $order ) {
        if ( $order->get_meta( '_znc_is_parent_order' ) === 'yes' ) {
            $parent_id = $order->get_id();
            $url = wc_get_account_endpoint_url( self::DETAIL_ENDPOINT ) . $parent_id . '/';
            echo '<span class="znc-badge znc-badge--netcart">';
            echo '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Net Cart', 'zinckles-net-cart' ) . '</a>';
            echo '</span>';
        }
    }

    /**
     * Invalidate user's order cache on status change.
     *
     * @param int    $order_id   Order ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     */
    public function invalidate_cache( $order_id, $old_status, $new_status ) {
        $order = wc_get_order( $order_id );
        if ( $order && $order->get_meta( '_znc_is_parent_order' ) === 'yes' ) {
            $user_id = $order->get_customer_id();
            delete_transient( 'znc_user_stats_' . $user_id );
            delete_transient( 'znc_user_filters_' . $user_id );
        }
    }

    /**
     * Flush rewrite rules.
     */
    public function flush_rewrites() {
        $this->register_endpoints();
        flush_rewrite_rules();
    }
}
