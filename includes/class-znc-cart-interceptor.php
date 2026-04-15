<?php
/**
 * Cart Interceptor — Hooks WooCommerce add-to-cart on ALL enrolled sites.
 *
 * When a user adds a product on any enrolled subsite (or the host),
 * this class captures the event and writes to ZNC_Global_Cart (wp_usermeta).
 * Optionally clears the local WC cart so items don't appear duplicated.
 *
 * @package ZincklesNetCart
 * @since   1.5.1
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Interceptor {

    /** @var ZNC_Global_Cart */
    private $global_cart;

    /** @var array Network settings cache */
    private $settings;

    public function __construct( ZNC_Global_Cart $global_cart ) {
        $this->global_cart = $global_cart;
        $this->settings    = get_site_option( 'znc_network_settings', array() );
    }

    /**
     * Initialize hooks — only if current site is enrolled or is the host.
     */
    public function init() {
        if ( ! $this->is_site_participating() ) return;

        /* ── Hook AFTER WooCommerce adds to its local cart ── */
        add_action( 'woocommerce_add_to_cart', array( $this, 'on_add_to_cart' ), 10, 6 );

        /* ── Hook item removal from local cart ── */
        add_action( 'woocommerce_cart_item_removed', array( $this, 'on_item_removed' ), 10, 2 );

        /* ── Hook quantity updates ── */
        add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_quantity_update' ), 10, 4 );

        /* ── AJAX endpoint for manual add (non-WC pages) ── */
        add_action( 'wp_ajax_znc_add_to_global_cart', array( $this, 'ajax_add_to_cart' ) );

        /* ── AJAX endpoints for cart CRUD ── */
        add_action( 'wp_ajax_znc_remove_cart_item',  array( $this, 'ajax_remove_item' ) );
        add_action( 'wp_ajax_znc_update_cart_qty',   array( $this, 'ajax_update_qty' ) );
        add_action( 'wp_ajax_znc_clear_global_cart', array( $this, 'ajax_clear_cart' ) );
        add_action( 'wp_ajax_znc_get_cart_count',    array( $this, 'ajax_get_count' ) );

        /* ── Localize script data ── */
        add_action( 'wp_enqueue_scripts', array( $this, 'localize_cart_data' ), 99 );

        $this->debug( 'Interceptor initialized on blog ' . get_current_blog_id() );
    }

    /* ─────────────────────────────────────────────────────────────
     * WooCommerce Hooks
     * ───────────────────────────────────────────────────────────── */

    /**
     * Fired when WooCommerce adds an item to the local cart.
     * We capture it and write to global cart.
     */
    public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        // Prevent re-entry if we're clearing the local cart
        if ( doing_action( 'znc_clearing_local_cart' ) ) return;

        $blog_id = get_current_blog_id();

        $result = $this->global_cart->add_item(
            $user_id,
            $blog_id,
            $product_id,
            $quantity,
            $variation_id,
            is_array( $variation ) ? $variation : array()
        );

        $this->debug( sprintf(
            'Intercepted add-to-cart: user=%d blog=%d product=%d qty=%d var=%d result=%s',
            $user_id, $blog_id, $product_id, $quantity, $variation_id,
            $result ? 'OK' : 'FAIL'
        ) );

        /* ── Optionally clear local WC cart ── */
        if ( ! empty( $this->settings['clear_local_cart'] ) && $result ) {
            $this->clear_local_cart();
        }
    }

    /**
     * When an item is removed from the local WC cart,
     * also remove from global cart if it matches.
     */
    public function on_item_removed( $cart_item_key, $cart ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
        if ( ! $item ) return;

        $blog_id      = get_current_blog_id();
        $product_id   = $item['product_id'] ?? 0;
        $variation_id = $item['variation_id'] ?? 0;

        $global_key = $blog_id . '_' . $product_id . '_' . $variation_id;
        $this->global_cart->remove_item( $user_id, $global_key );
    }

    /**
     * When quantity is updated in local WC cart,
     * sync the change to global cart.
     */
    public function on_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $item = $cart->cart_contents[ $cart_item_key ] ?? null;
        if ( ! $item ) return;

        $blog_id      = get_current_blog_id();
        $product_id   = $item['product_id'] ?? 0;
        $variation_id = $item['variation_id'] ?? 0;

        $global_key = $blog_id . '_' . $product_id . '_' . $variation_id;
        $this->global_cart->update_quantity( $user_id, $global_key, $quantity );
    }

    /* ─────────────────────────────────────────────────────────────
     * AJAX Endpoints
     * ───────────────────────────────────────────────────────────── */

    public function ajax_add_to_cart() {
        check_ajax_referer( 'znc_cart_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in', 401 );

        $blog_id      = absint( $_POST['blog_id'] ?? 0 );
        $product_id   = absint( $_POST['product_id'] ?? 0 );
        $quantity     = absint( $_POST['quantity'] ?? 1 );
        $variation_id = absint( $_POST['variation_id'] ?? 0 );
        $variation    = isset( $_POST['variation'] ) ? (array) $_POST['variation'] : array();

        if ( ! $blog_id || ! $product_id ) {
            wp_send_json_error( 'Missing product or blog ID' );
        }

        $result = $this->global_cart->add_item( $user_id, $blog_id, $product_id, $quantity, $variation_id, $variation );

        wp_send_json_success( array(
            'added'      => $result,
            'cart_count'  => $this->global_cart->get_item_count( $user_id ),
        ) );
    }

    public function ajax_remove_item() {
        check_ajax_referer( 'znc_cart_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in', 401 );

        $item_key = sanitize_text_field( $_POST['item_key'] ?? '' );
        if ( ! $item_key ) wp_send_json_error( 'Missing item key' );

        $this->global_cart->remove_item( $user_id, $item_key );

        wp_send_json_success( array(
            'removed'    => true,
            'cart_count' => $this->global_cart->get_item_count( $user_id ),
        ) );
    }

    public function ajax_update_qty() {
        check_ajax_referer( 'znc_cart_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in', 401 );

        $item_key = sanitize_text_field( $_POST['item_key'] ?? '' );
        $quantity = absint( $_POST['quantity'] ?? 0 );

        if ( ! $item_key ) wp_send_json_error( 'Missing item key' );

        $this->global_cart->update_quantity( $user_id, $item_key, $quantity );

        wp_send_json_success( array(
            'updated'    => true,
            'cart_count' => $this->global_cart->get_item_count( $user_id ),
        ) );
    }

    public function ajax_clear_cart() {
        check_ajax_referer( 'znc_cart_nonce', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in', 401 );

        $this->global_cart->clear_cart( $user_id );

        wp_send_json_success( array(
            'cleared'    => true,
            'cart_count' => 0,
        ) );
    }

    public function ajax_get_count() {
        $user_id = get_current_user_id();
        wp_send_json_success( array(
            'cart_count' => $user_id ? $this->global_cart->get_item_count( $user_id ) : 0,
        ) );
    }

    /* ─────────────────────────────────────────────────────────────
     * Script Localization
     * ───────────────────────────────────────────────────────────── */

    public function localize_cart_data() {
        if ( ! is_user_logged_in() ) return;

        wp_localize_script( 'znc-front', 'zncCart', array(
            'ajaxurl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'znc_cart_nonce' ),
            'blog_id'    => get_current_blog_id(),
            'cart_count' => $this->global_cart->get_item_count(),
        ) );
    }

    /* ─────────────────────────────────────────────────────────────
     * Helpers
     * ───────────────────────────────────────────────────────────── */

    /**
     * Check if the current site participates in Net Cart.
     */
    private function is_site_participating() {
        $blog_id  = get_current_blog_id();
        $enrolled = (array) ( $this->settings['enrolled_sites'] ?? array() );
        $blocked  = (array) ( $this->settings['blocked_sites']  ?? array() );

        if ( in_array( $blog_id, array_map( 'absint', $blocked ), true ) ) {
            return false;
        }

        // Host site always participates
        $host_id = absint( $this->settings['checkout_host_id'] ?? get_main_site_id() );
        if ( $blog_id === $host_id ) return true;

        // Opt-out mode = all sites participate unless blocked
        $mode = $this->settings['enrollment_mode'] ?? 'opt-in';
        if ( $mode === 'opt-out' ) return true;

        return in_array( $blog_id, array_map( 'absint', $enrolled ), true );
    }

    /**
     * Clear the local WooCommerce cart without triggering our hooks.
     */
    private function clear_local_cart() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;

        // Temporarily remove our hook to prevent re-entry
        remove_action( 'woocommerce_cart_item_removed', array( $this, 'on_item_removed' ), 10 );
        do_action( 'znc_clearing_local_cart' );
        WC()->cart->empty_cart();
        add_action( 'woocommerce_cart_item_removed', array( $this, 'on_item_removed' ), 10, 2 );
    }

    /**
     * Debug logger.
     */
    private function debug( $message ) {
        if ( ! empty( $this->settings['debug_mode'] ) && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[ZNC-Interceptor] ' . $message );
        }
    }
}
