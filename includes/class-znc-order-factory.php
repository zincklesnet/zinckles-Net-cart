<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Order_Factory {

    public function init() {
        add_action( 'woocommerce_order_status_changed', array( $this, 'sync_child_status' ), 10, 3 );
    }

    /**
     * Create the parent order on the main site.
     */
    public function create_parent_order( int $user_id, array $cart_items, array $params = array() ) {
        if ( ! class_exists( 'WC_Order' ) ) {
            return new WP_Error( 'znc_wc_missing', 'WooCommerce not active on main site.' );
        }

        $main_settings = get_option( 'znc_main_settings', array() );
        $prefix        = $main_settings['order_prefix_parent'] ?? 'ZNC';

        try {
            $order = wc_create_order( array(
                'customer_id' => $user_id,
                'status'      => $main_settings['default_parent_status'] ?? 'processing',
            ) );

            if ( is_wp_error( $order ) ) {
                return $order;
            }

            // Add line items grouped by origin site
            foreach ( $cart_items as $item ) {
                $line = new WC_Order_Item_Product();
                $line->set_name( sprintf( '[Site %d] Product #%d', $item['site_id'], $item['product_id'] ) );
                $line->set_quantity( $item['quantity'] );
                $line->set_subtotal( $item['unit_price'] * $item['quantity'] );
                $line->set_total( $item['unit_price'] * $item['quantity'] );

                // Store origin metadata
                $line->add_meta_data( '_znc_origin_site_id', $item['site_id'] );
                $line->add_meta_data( '_znc_origin_product_id', $item['product_id'] );
                $line->add_meta_data( '_znc_origin_variation_id', $item['variation_id'] ?? 0 );
                $line->add_meta_data( '_znc_origin_currency', $item['currency'] ?? '' );
                $line->add_meta_data( '_znc_origin_price', $item['unit_price'] );

                $order->add_item( $line );
            }

            // Set order metadata
            $order->set_currency( $params['currency'] ?? 'USD' );
            $order->set_total( $params['total'] ?? 0 );

            if ( ! empty( $params['payment_method'] ) ) {
                $order->set_payment_method( $params['payment_method'] );
            }

            // Billing and shipping
            if ( ! empty( $params['billing'] ) ) {
                foreach ( $params['billing'] as $key => $value ) {
                    $setter = "set_billing_{$key}";
                    if ( method_exists( $order, $setter ) ) {
                        $order->$setter( $value );
                    }
                }
            }
            if ( ! empty( $params['shipping'] ) ) {
                foreach ( $params['shipping'] as $key => $value ) {
                    $setter = "set_shipping_{$key}";
                    if ( method_exists( $order, $setter ) ) {
                        $order->$setter( $value );
                    }
                }
            }

            // Net Cart metadata
            $order->update_meta_data( '_znc_parent_order', true );
            $order->update_meta_data( '_znc_zcred_deducted', $params['zcred_deducted'] ?? 0 );
            $order->update_meta_data( '_znc_zcred_value', $params['zcred_value'] ?? 0 );
            $order->update_meta_data( '_znc_totals', $params['totals'] ?? array() );
            $order->update_meta_data( '_znc_sites_involved', array_unique( array_column( $cart_items, 'site_id' ) ) );

            $order->add_order_note( sprintf(
                'Net Cart parent order — %d items from %d shops. ZCreds: %s',
                count( $cart_items ),
                count( array_unique( array_column( $cart_items, 'site_id' ) ) ),
                $params['zcred_deducted'] ?? 0
            ) );

            $order->save();

            return array(
                'order_id' => $order->get_id(),
                'status'   => $order->get_status(),
                'total'    => $order->get_total(),
                'currency' => $order->get_currency(),
            );

        } catch ( Exception $e ) {
            return new WP_Error( 'znc_order_failed', $e->getMessage() );
        }
    }

    /**
     * Create a child order on a subsite via REST.
     */
    public function create_child_order( int $site_id, int $user_id, array $items, int $parent_order_id ) {
        $payload = array(
            'customer_id'     => $user_id,
            'parent_order_id' => $parent_order_id,
            'parent_site_id'  => get_current_blog_id(),
            'currency'        => $items[0]['currency'] ?? 'USD',
            'items'           => array_map( function( $item ) {
                return array(
                    'product_id'   => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? 0,
                    'quantity'     => $item['quantity'],
                    'line_total'   => $item['unit_price'] * $item['quantity'],
                );
            }, $items ),
        );

        $result = ZNC_REST_Auth::remote_request( $site_id, '/orders/create-child', $payload );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Record mapping
        $this->record_mapping( $parent_order_id, $result['child_order_id'], $site_id );

        return array(
            'site_id'        => $site_id,
            'child_order_id' => $result['child_order_id'],
            'parent_order_id'=> $parent_order_id,
        );
    }

    /**
     * Record parent→child order mapping.
     */
    private function record_mapping( int $parent_id, int $child_id, int $site_id ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'znc_order_map', array(
            'parent_order_id' => $parent_id,
            'child_order_id'  => $child_id,
            'child_site_id'   => $site_id,
            'status'          => 'processing',
        ) );
    }

    /**
     * Get all child orders for a parent.
     */
    public function get_children( int $parent_order_id ) : array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}znc_order_map WHERE parent_order_id = %d",
            $parent_order_id
        ), ARRAY_A ) ?: array();
    }

    /**
     * Sync parent order status changes to child orders.
     */
    public function sync_child_status( $order_id, $old_status, $new_status ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! $order->get_meta( '_znc_parent_order' ) ) {
            return;
        }

        $main_settings = get_option( 'znc_main_settings', array() );
        if ( empty( $main_settings['sync_child_status'] ) ) {
            return;
        }

        $children = $this->get_children( $order_id );
        foreach ( $children as $child ) {
            ZNC_REST_Auth::remote_request( intval( $child['child_site_id'] ), '/orders/update-status', array(
                'order_id'   => $child['child_order_id'],
                'new_status' => $new_status,
            ) );

            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'znc_order_map',
                array( 'status' => $new_status ),
                array( 'id' => $child['id'] )
            );
        }
    }
}
