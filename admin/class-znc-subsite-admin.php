<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Subsite_Admin {

    private $defaults = array(
        'product_mode'        => 'all',
        'include_products'    => array(),
        'exclude_products'    => array(),
        'exclude_categories'  => array(),
        'exclude_tags'        => array(),
        'exclude_backorders'  => false,
        'exclude_on_sale'     => false,
        'min_price'           => 0,
        'max_price'           => 0,
        'include_meta'        => true,
        'include_images'      => true,
        'meta_keys'           => array(),
        'snapshot_trigger'    => 'auto',
        'shipping_mode'       => 'inherit',
        'shipping_flat_rate'  => 0,
        'shipping_free_threshold' => 0,
        'shipping_note'       => '',
        'tax_on_shipping'     => true,
        'tax_mode'            => 'inherit',
        'tax_rate'            => 0,
        'tax_label'           => 'Tax',
        'tax_exempt'          => false,
        'accept_zcreds'       => true,
        'zcred_max_percent'   => 100,
        'zcred_earn_multiplier'=> 1.0,
        'zcred_bonus_cats'    => array(),
        'zcred_exclude_products'=> array(),
        'brand_display_name'  => '',
        'brand_tagline'       => '',
        'brand_badge_color'   => '#4f46e5',
        'brand_icon_url'      => '',
        'stock_reservation_minutes' => 0,
        'low_stock_threshold' => 0,
        'realtime_stock_push' => false,
        'coupon_available'    => true,
    );

    public function init() {
        if ( ! is_admin() || is_main_site() ) return;
        add_action( 'admin_menu', array( $this, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_znc_enroll_request', array( $this, 'ajax_enrollment_request' ) );
        add_action( 'wp_ajax_znc_snapshot_preview', array( $this, 'ajax_snapshot_preview' ) );
    }

    public function add_menus() {
        add_menu_page(
            __( 'Net Cart', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_woocommerce',
            'znc-subsite',
            array( $this, 'render_dashboard' ),
            'dashicons-cart',
            56
        );

        $pages = array(
            'znc-subsite'          => __( 'Dashboard', 'zinckles-net-cart' ),
            'znc-subsite-products' => __( 'Products', 'zinckles-net-cart' ),
            'znc-subsite-shipping' => __( 'Shipping & Tax', 'zinckles-net-cart' ),
            'znc-subsite-zcreds'   => __( 'ZCreds', 'zinckles-net-cart' ),
            'znc-subsite-branding' => __( 'Branding', 'zinckles-net-cart' ),
            'znc-subsite-stock'    => __( 'Stock', 'zinckles-net-cart' ),
        );

        foreach ( $pages as $slug => $title ) {
            $callback = 'render_' . str_replace( array( 'znc-subsite-', 'znc-' ), array( '', '' ), $slug );
            add_submenu_page( 'znc-subsite', $title, $title, 'manage_woocommerce', $slug, array( $this, $callback ) );
        }
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'znc-subsite' ) === false ) return;
        wp_enqueue_style( 'znc-admin', ZNC_PLUGIN_URL . 'admin/assets/admin.css', array(), ZNC_VERSION );
        wp_enqueue_script( 'znc-admin', ZNC_PLUGIN_URL . 'admin/assets/admin.js', array( 'jquery' ), ZNC_VERSION, true );
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_enqueue_media();
        wp_localize_script( 'znc-admin', 'zncAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'znc_admin_nonce' ),
        ) );
    }

    private function save_if_posted() {
        if ( ! isset( $_POST['znc_save_subsite'] ) ) return;
        check_admin_referer( 'znc_subsite_nonce' );

        $settings = get_option( 'znc_subsite_settings', array() );

        foreach ( $this->defaults as $key => $default ) {
            if ( is_bool( $default ) ) {
                $settings[ $key ] = ! empty( $_POST[ $key ] );
            } elseif ( is_int( $default ) ) {
                $settings[ $key ] = intval( $_POST[ $key ] ?? $default );
            } elseif ( is_float( $default ) ) {
                $settings[ $key ] = floatval( $_POST[ $key ] ?? $default );
            } elseif ( is_array( $default ) ) {
                $settings[ $key ] = array_map( 'sanitize_text_field', (array) ( $_POST[ $key ] ?? array() ) );
            } else {
                $settings[ $key ] = sanitize_text_field( $_POST[ $key ] ?? $default );
            }
        }

        update_option( 'znc_subsite_settings', $settings );
        add_settings_error( 'znc', 'znc_saved', __( 'Settings saved.', 'zinckles-net-cart' ), 'success' );
    }

    public function get_enrollment_status() : array {
        global $wpdb;
        switch_to_blog( get_main_site_id() );
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}znc_enrolled_sites WHERE site_id = %d",
            get_current_blog_id()
        ), ARRAY_A );
        restore_current_blog();

        if ( ! $row ) {
            return array( 'status' => 'not_enrolled', 'enrolled_at' => null );
        }
        return $row;
    }

    public function get_prerequisites() : array {
        return array(
            'woocommerce'  => class_exists( 'WooCommerce' ),
            'mycred'       => function_exists( 'mycred' ),
            'rest_secret'  => ! empty( get_site_option( 'znc_rest_secret' ) ),
            'has_products' => function_exists( 'wc_get_products' ) && count( wc_get_products( array( 'limit' => 1, 'return' => 'ids' ) ) ) > 0,
        );
    }

    /* ── AJAX ─────────────────────────────────────────────── */

    public function ajax_enrollment_request() {
        check_ajax_referer( 'znc_admin_nonce', 'nonce' );
        // Request enrollment from network admin
        $network = get_site_option( 'znc_network_settings', array() );
        if ( ( $network['enrollment_mode'] ?? 'opt_in' ) === 'opt_in' ) {
            global $wpdb;
            switch_to_blog( get_main_site_id() );
            $wpdb->replace( $wpdb->prefix . 'znc_enrolled_sites', array(
                'site_id'     => get_current_blog_id(),
                'status'      => 'pending',
                'enrolled_by' => get_current_user_id(),
            ) );
            restore_current_blog();
            wp_send_json_success( array( 'message' => 'Enrollment request submitted.' ) );
        }
        wp_send_json_error( array( 'message' => 'Enrollment mode does not allow self-enrollment.' ) );
    }

    public function ajax_snapshot_preview() {
        check_ajax_referer( 'znc_admin_nonce', 'nonce' );
        $snapshot = new ZNC_Cart_Snapshot();
        wp_send_json_success( $snapshot->build( get_current_user_id() ) );
    }

    /* ── Renderers ────────────────────────────────────────── */

    public function render_dashboard() {
        $this->save_if_posted();
        include ZNC_PLUGIN_DIR . 'admin/views/subsite-dashboard.php';
    }

    public function render_subsite() {
        $this->render_dashboard();
    }

    public function render_products() {
        $this->save_if_posted();
        $settings = get_option( 'znc_subsite_settings', wp_parse_args( array(), $this->defaults ) );
        include ZNC_PLUGIN_DIR . 'admin/views/subsite-products.php';
    }

    public function render_shipping() {
        $this->save_if_posted();
        $settings = get_option( 'znc_subsite_settings', wp_parse_args( array(), $this->defaults ) );
        include ZNC_PLUGIN_DIR . 'admin/views/subsite-shipping.php';
    }

    public function render_zcreds() {
        $this->save_if_posted();
        $settings = get_option( 'znc_subsite_settings', wp_parse_args( array(), $this->defaults ) );
        include ZNC_PLUGIN_DIR . 'admin/views/subsite-zcreds.php';
    }

    public function render_branding() {
        $this->save_if_posted();
        $settings = get_option( 'znc_subsite_settings', wp_parse_args( array(), $this->defaults ) );
        include ZNC_PLUGIN_DIR . 'admin/views/subsite-branding.php';
    }

    public function render_stock() {
        $this->save_if_posted();
        $settings = get_option( 'znc_subsite_settings', wp_parse_args( array(), $this->defaults ) );
        include ZNC_PLUGIN_DIR . 'admin/views/subsite-stock.php';
    }
}
