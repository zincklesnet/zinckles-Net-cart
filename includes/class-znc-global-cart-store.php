<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Store {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'znc_global_cart';
    }

    public function init() {
        add_action( 'znc_cart_cleanup', array( $this, 'cleanup_expired' ) );
    }

    /**
     * Get all cart items for a user, grouped by site.
     */
    public function get_cart( int $user_id ) : array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = %d AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY site_id, added_at",
            $user_id
        ), ARRAY_A );
        return $rows ?: array();
    }

    /**
     * Get cart items grouped by site_id.
     */
    public function get_cart_by_site( int $user_id ) : array {
        $items = $this->get_cart( $user_id );
        $grouped = array();
        foreach ( $items as $item ) {
            $sid = $item['site_id'];
            if ( ! isset( $grouped[ $sid ] ) ) {
                $grouped[ $sid ] = array();
            }
            $grouped[ $sid ][] = $item;
        }
        return $grouped;
    }

    /**
     * Upsert a cart line (insert or update quantity/price).
     */
    public function upsert_item( array $data ) : int {
        global $wpdb;

        $user_id      = absint( $data['user_id'] );
        $site_id      = absint( $data['site_id'] );
        $product_id   = absint( $data['product_id'] );
        $variation_id = absint( $data['variation_id'] ?? 0 );
        $quantity     = absint( $data['quantity'] ?? 1 );
        $unit_price   = floatval( $data['unit_price'] ?? 0 );
        $currency     = sanitize_text_field( $data['currency'] ?? 'USD' );
        $line_meta    = isset( $data['line_meta'] ) ? wp_json_encode( $data['line_meta'] ) : null;
        $coupon_codes = isset( $data['coupon_codes'] ) ? sanitize_text_field( $data['coupon_codes'] ) : null;

        $network_settings = get_site_option( 'znc_network_settings', array() );
        $expiry_days      = intval( $network_settings['cart_expiry_days'] ?? 7 );
        $expires_at       = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );

        // Check existing line
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$this->table} WHERE user_id = %d AND site_id = %d AND product_id = %d AND variation_id = %d",
            $user_id, $site_id, $product_id, $variation_id
        ) );

        if ( $existing ) {
            $wpdb->update( $this->table, array(
                'quantity'    => $quantity,
                'unit_price'  => $unit_price,
                'currency'    => $currency,
                'line_meta'   => $line_meta,
                'coupon_codes'=> $coupon_codes,
                'expires_at'  => $expires_at,
            ), array( 'id' => $existing->id ), array( '%d','%f','%s','%s','%s','%s' ), array( '%d' ) );
            return $existing->id;
        }

        // Enforce max items
        $max_items = intval( $network_settings['max_items_per_cart'] ?? 50 );
        $count     = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE user_id = %d AND (expires_at IS NULL OR expires_at > NOW())",
            $user_id
        ) );
        if ( $count >= $max_items ) {
            return 0; // Cart full
        }

        $wpdb->insert( $this->table, array(
            'user_id'      => $user_id,
            'site_id'      => $site_id,
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $quantity,
            'unit_price'   => $unit_price,
            'currency'     => $currency,
            'line_meta'    => $line_meta,
            'coupon_codes' => $coupon_codes,
            'expires_at'   => $expires_at,
        ) );

        return $wpdb->insert_id;
    }

    /**
     * Update quantity for a cart line.
     */
    public function update_quantity( int $line_id, int $quantity ) : bool {
        global $wpdb;
        if ( $quantity <= 0 ) {
            return $this->delete_item( $line_id );
        }
        return (bool) $wpdb->update( $this->table, array( 'quantity' => $quantity ), array( 'id' => $line_id ) );
    }

    /**
     * Remove a specific cart line.
     */
    public function remove_item( int $user_id, int $line_id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table, array( 'id' => $line_id, 'user_id' => $user_id ) );
    }

    /**
     * Remove item by product identifiers.
     */
    public function remove_by_product( int $user_id, int $site_id, int $product_id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table, array(
            'user_id'    => $user_id,
            'site_id'    => $site_id,
            'product_id' => $product_id,
        ) );
    }

    /**
     * Delete a cart line.
     */
    public function delete_item( int $line_id ) : bool {
        global $wpdb;
        return (bool) $wpdb->delete( $this->table, array( 'id' => $line_id ) );
    }

    /**
     * Clear entire cart for a user.
     */
    public function clear_cart( int $user_id ) : int {
        global $wpdb;
        return $wpdb->delete( $this->table, array( 'user_id' => $user_id ) );
    }

    /**
     * Get cart summary statistics.
     */
    public function get_cart_stats( int $user_id ) : array {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) as item_count, COUNT(DISTINCT site_id) as shop_count, SUM(quantity * unit_price) as raw_total
             FROM {$this->table} WHERE user_id = %d AND (expires_at IS NULL OR expires_at > NOW())",
            $user_id
        ), ARRAY_A );
        return $row ?: array( 'item_count' => 0, 'shop_count' => 0, 'raw_total' => 0 );
    }

    /**
     * Cleanup expired cart items (cron).
     */
    public function cleanup_expired() {
        global $wpdb;
        $wpdb->query( "DELETE FROM {$this->table} WHERE expires_at IS NOT NULL AND expires_at < NOW()" );
    }

    /**
     * Admin: get all active carts (paginated).
     */
    public function admin_get_all_carts( int $page = 1, int $per_page = 20 ) : array {
        global $wpdb;
        $offset = ( $page - 1 ) * $per_page;
        $total  = $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$this->table} WHERE expires_at IS NULL OR expires_at > NOW()" );
        $rows   = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, COUNT(*) as items, COUNT(DISTINCT site_id) as shops, SUM(quantity * unit_price) as total, MAX(updated_at) as last_updated
             FROM {$this->table}
             WHERE expires_at IS NULL OR expires_at > NOW()
             GROUP BY user_id
             ORDER BY last_updated DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ), ARRAY_A );

        return array( 'carts' => $rows, 'total' => intval( $total ), 'page' => $page, 'per_page' => $per_page );
    }
}
