<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Global_Cart_Merger {

    private $store;
    private $currency;

    public function __construct( ZNC_Global_Cart_Store $store, ZNC_Currency_Handler $currency ) {
        $this->store    = $store;
        $this->currency = $currency;
    }

    public function init() {
        // Merger is invoked on demand
    }

    /**
     * Add an item to the global cart with live validation.
     */
    public function add_item( array $data ) {
        $site_id    = absint( $data['site_id'] ?? 0 );
        $product_id = absint( $data['product_id'] ?? 0 );
        $user_id    = absint( $data['user_id'] ?? get_current_user_id() );

        if ( ! $site_id || ! $product_id || ! $user_id ) {
            return new WP_Error( 'znc_invalid_item', 'Missing site_id, product_id, or user_id.' );
        }

        // Check enrollment
        if ( ! $this->is_site_enrolled( $site_id ) ) {
            return new WP_Error( 'znc_not_enrolled', 'This shop is not enrolled in Net Cart.' );
        }

        // Validate price/stock on origin subsite
        $validation = ZNC_REST_Auth::remote_request( $site_id, '/pricing/validate', array(
            'products' => array( array(
                'product_id'     => $product_id,
                'variation_id'   => $data['variation_id'] ?? 0,
                'quantity'       => $data['quantity'] ?? 1,
                'expected_price' => $data['unit_price'] ?? 0,
            ) ),
        ) );

        if ( is_wp_error( $validation ) ) {
            // Fall back to provided data if subsite is unreachable
            $data['user_id'] = $user_id;
            $line_id = $this->store->upsert_item( $data );
            if ( ! $line_id ) {
                return new WP_Error( 'znc_cart_full', 'Cart item limit reached.' );
            }
            return $this->store->get_cart( $user_id );
        }

        $result = $validation['results'][0] ?? null;
        if ( $result && ! $result['in_stock'] ) {
            return new WP_Error( 'znc_out_of_stock', 'Product is out of stock on the origin shop.' );
        }

        // Use live price from validation
        if ( $result && isset( $result['current_price'] ) ) {
            $data['unit_price'] = $result['current_price'];
        }

        $data['user_id'] = $user_id;
        $line_id = $this->store->upsert_item( $data );

        if ( ! $line_id ) {
            return new WP_Error( 'znc_cart_full', 'Cart item limit reached.' );
        }

        return $this->store->get_cart( $user_id );
    }

    /**
     * Refresh the entire cart — re-validate all items against their origin subsites.
     */
    public function refresh_cart( int $user_id ) : array {
        $by_site = $this->store->get_cart_by_site( $user_id );
        $removed = array();
        $updated = array();

        foreach ( $by_site as $site_id => $items ) {
            $products = array();
            foreach ( $items as $item ) {
                $products[] = array(
                    'product_id'     => $item['product_id'],
                    'variation_id'   => $item['variation_id'],
                    'quantity'       => $item['quantity'],
                    'expected_price' => $item['unit_price'],
                );
            }

            $validation = ZNC_REST_Auth::remote_request( intval( $site_id ), '/pricing/validate', array(
                'products' => $products,
            ) );

            if ( is_wp_error( $validation ) ) {
                continue; // Keep items if subsite is unreachable
            }

            foreach ( $validation['results'] ?? array() as $idx => $result ) {
                $item = $items[ $idx ] ?? null;
                if ( ! $item ) continue;

                if ( ! $result['valid'] ) {
                    if ( $result['reason'] === 'not_found' || ! $result['in_stock'] ) {
                        $this->store->delete_item( $item['id'] );
                        $removed[] = $item;
                    } elseif ( $result['price_changed'] ) {
                        // Update to current price
                        global $wpdb;
                        $wpdb->update(
                            $wpdb->prefix . 'znc_global_cart',
                            array( 'unit_price' => $result['current_price'] ),
                            array( 'id' => $item['id'] )
                        );
                        $updated[] = array_merge( $item, array( 'new_price' => $result['current_price'] ) );
                    }
                }
            }
        }

        return array(
            'cart'    => $this->store->get_cart( $user_id ),
            'removed' => $removed,
            'updated' => $updated,
        );
    }

    /**
     * Get full cart with parallel totals.
     */
    public function get_cart_with_totals( int $user_id ) : array {
        $items  = $this->store->get_cart( $user_id );
        $totals = $this->currency->parallel_totals( $items );

        return array(
            'items'  => $items,
            'totals' => $totals,
            'stats'  => $this->store->get_cart_stats( $user_id ),
        );
    }

    private function is_site_enrolled( int $site_id ) : bool {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_enrolled_sites';
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE site_id = %d AND status = 'active'",
            $site_id
        ) );
    }
}
