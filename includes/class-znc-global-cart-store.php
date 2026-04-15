<?php
/**
 * Global Cart Store — v1.5.0 REWRITE
 *
 * Now reads/writes from wp_usermeta via ZNC_Cart_Snapshot static methods.
 * Zero switch_to_blog(), zero custom tables.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Store {

    /** @var ZNC_Checkout_Host */
    private $host;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {
        // AJAX handlers for cart operations on checkout host
        add_action( 'wp_ajax_znc_remove_cart_item',  array( $this, 'ajax_remove_item' ) );
        add_action( 'wp_ajax_znc_update_cart_qty',   array( $this, 'ajax_update_qty' ) );
        add_action( 'wp_ajax_znc_clear_cart',        array( $this, 'ajax_clear_cart' ) );
        add_action( 'wp_ajax_znc_get_cart_data',     array( $this, 'ajax_get_cart' ) );
    }

    /* ── Public API ───────────────────────────────────────────── */

    public function get_items( $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        return ZNC_Cart_Snapshot::get_cart( $user_id );
    }

    public function get_grouped( $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        return ZNC_Cart_Snapshot::get_grouped( $user_id );
    }

    public function get_count( $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        return ZNC_Cart_Snapshot::get_count( $user_id );
    }

    public function get_total( $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        return ZNC_Cart_Snapshot::get_total( $user_id );
    }

    public function get_shop_count( $user_id = null ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        return ZNC_Cart_Snapshot::get_shop_count( $user_id );
    }

    public function remove_item( $user_id, $item_key ) {
        return ZNC_Cart_Snapshot::remove_item( $user_id, $item_key );
    }

    public function update_qty( $user_id, $item_key, $quantity ) {
        return ZNC_Cart_Snapshot::update_qty( $user_id, $item_key, $quantity );
    }

    public function clear( $user_id ) {
        ZNC_Cart_Snapshot::clear_cart( $user_id );
    }

    /* ── AJAX Handlers ────────────────────────────────────────── */

    public function ajax_remove_item() {
        check_ajax_referer( 'znc_cart_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $item_key = isset( $_POST['item_key'] ) ? sanitize_text_field( $_POST['item_key'] ) : '';
        if ( ! $item_key ) wp_send_json_error( array( 'message' => 'Missing item key.' ) );

        $user_id = get_current_user_id();
        $result  = $this->remove_item( $user_id, $item_key );

        if ( $result ) {
            wp_send_json_success( array(
                'message' => 'Item removed.',
                'count'   => $this->get_count( $user_id ),
                'total'   => $this->get_total( $user_id ),
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Item not found.' ) );
        }
    }

    public function ajax_update_qty() {
        check_ajax_referer( 'znc_cart_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $item_key = isset( $_POST['item_key'] ) ? sanitize_text_field( $_POST['item_key'] ) : '';
        $quantity = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 0;
        if ( ! $item_key ) wp_send_json_error( array( 'message' => 'Missing item key.' ) );

        $user_id = get_current_user_id();
        $result  = $this->update_qty( $user_id, $item_key, $quantity );

        if ( $result ) {
            wp_send_json_success( array(
                'message' => 'Quantity updated.',
                'count'   => $this->get_count( $user_id ),
                'total'   => $this->get_total( $user_id ),
            ) );
        } else {
            wp_send_json_error( array( 'message' => 'Item not found.' ) );
        }
    }

    public function ajax_clear_cart() {
        check_ajax_referer( 'znc_cart_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $this->clear( get_current_user_id() );
        wp_send_json_success( array( 'message' => 'Cart cleared.', 'count' => 0, 'total' => 0 ) );
    }

    public function ajax_get_cart() {
        check_ajax_referer( 'znc_cart_action', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( array( 'message' => 'Not logged in.' ) );

        $user_id = get_current_user_id();
        wp_send_json_success( array(
            'items'      => $this->get_items( $user_id ),
            'grouped'    => $this->get_grouped( $user_id ),
            'count'      => $this->get_count( $user_id ),
            'total'      => $this->get_total( $user_id ),
            'shop_count' => $this->get_shop_count( $user_id ),
        ) );
    }

    /* ── Admin Stats ──────────────────────────────────────────── */

    /**
     * Get aggregate stats across all users for admin dashboard.
     * Uses direct DB query on wp_usermeta.
     */
    public static function get_admin_stats() {
        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
            ZNC_CART_META_KEY
        ) );

        $stats = array(
            'total_users'     => 0,
            'total_items'     => 0,
            'total_value'     => 0,
            'total_shops'     => array(),
            'users_with_cart' => 0,
        );

        if ( empty( $rows ) ) return $stats;

        $stats['users_with_cart'] = count( $rows );
        $stats['total_users']    = count( $rows );

        foreach ( $rows as $row ) {
            $cart = maybe_unserialize( $row->meta_value );
            if ( ! is_array( $cart ) ) continue;
            foreach ( $cart as $item ) {
                $qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
                $stats['total_items'] += $qty;
                $stats['total_value'] += $qty * ( isset( $item['price'] ) ? (float) $item['price'] : 0 );
                if ( isset( $item['blog_id'] ) ) {
                    $stats['total_shops'][ (int) $item['blog_id'] ] = true;
                }
            }
        }

        $stats['total_shops'] = count( $stats['total_shops'] );
        return $stats;
    }
}
