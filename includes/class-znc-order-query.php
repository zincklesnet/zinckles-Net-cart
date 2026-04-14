<?php
/**
 * Zinckles Net Cart — Order Query Engine
 *
 * Retrieves and structures Net Cart order data across the multisite network,
 * including child orders, currency breakdowns, and ZCred transactions.
 *
 * @package Zinckles_Net_Cart
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZNC_Order_Query {

    /**
     * Get paginated orders for a user with optional filters.
     *
     * @param int   $user_id  User ID.
     * @param int   $page     Current page.
     * @param int   $per_page Items per page.
     * @param array $filters  Optional filters.
     * @return array { orders: array, total: int, pages: int, current_page: int }
     */
    public function get_user_orders( $user_id, $page = 1, $per_page = 10, $filters = array() ) {
        $args = array(
            'customer_id' => $user_id,
            'meta_key'    => '_znc_is_parent_order',
            'meta_value'  => 'yes',
            'orderby'     => 'date',
            'order'       => 'DESC',
            'paginate'    => true,
            'page'        => $page,
            'limit'       => $per_page,
            'status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded', 'wc-cancelled', 'wc-pending', 'wc-failed' ),
        );

        // Apply status filter.
        if ( ! empty( $filters['status'] ) ) {
            $status = sanitize_text_field( $filters['status'] );
            $args['status'] = 'wc-' . $status;
        }

        // Apply date filters.
        if ( ! empty( $filters['date_from'] ) ) {
            $args['date_created'] = '>=' . $filters['date_from'];
        }
        if ( ! empty( $filters['date_to'] ) ) {
            if ( isset( $args['date_created'] ) ) {
                $args['date_created'] .= '...' . $filters['date_to'] . ' 23:59:59';
            } else {
                $args['date_created'] = '<=' . $filters['date_to'] . ' 23:59:59';
            }
        }

        // Search by order ID or product name.
        if ( ! empty( $filters['search'] ) ) {
            $args['s'] = $filters['search'];
        }

        $results = wc_get_orders( $args );

        $orders = array();
        foreach ( $results->orders as $order ) {
            $order_data = $this->build_order_summary( $order );

            // Apply shop filter (post-query since it's in meta).
            if ( ! empty( $filters['shop_id'] ) ) {
                $has_shop = false;
                foreach ( $order_data['shops'] as $shop ) {
                    if ( (int) $shop['site_id'] === (int) $filters['shop_id'] ) {
                        $has_shop = true;
                        break;
                    }
                }
                if ( ! $has_shop ) {
                    continue;
                }
            }

            // Apply currency filter.
            if ( ! empty( $filters['currency'] ) ) {
                $has_currency = false;
                foreach ( $order_data['currencies'] as $curr ) {
                    if ( strtoupper( $curr['code'] ) === strtoupper( $filters['currency'] ) ) {
                        $has_currency = true;
                        break;
                    }
                }
                if ( ! $has_currency ) {
                    continue;
                }
            }

            $orders[] = $order_data;
        }

        return array(
            'orders'       => $orders,
            'total'        => $results->total,
            'pages'        => $results->max_num_pages,
            'current_page' => $page,
        );
    }

    /**
     * Build a summary array for a parent order.
     *
     * @param WC_Order $order Parent order.
     * @return array
     */
    private function build_order_summary( $order ) {
        $order_id = $order->get_id();

        // Get child order map.
        $child_orders = $this->get_child_orders( $order_id );

        // Build shops list.
        $shops = array();
        $currencies = array();
        $currency_seen = array();

        foreach ( $child_orders as $child ) {
            $site_id   = $child['site_id'];
            $site_name = $child['site_name'];
            $currency  = $child['currency'];
            $subtotal  = $child['subtotal'];

            $shops[] = array(
                'site_id'      => $site_id,
                'site_name'    => $site_name,
                'currency'     => $currency,
                'subtotal'     => $subtotal,
                'item_count'   => $child['item_count'],
                'child_order_id' => $child['child_order_id'],
                'status'       => $child['status'],
                'badge_color'  => $child['badge_color'],
                'badge_icon'   => $child['badge_icon'],
            );

            if ( ! in_array( $currency, $currency_seen, true ) ) {
                $currency_seen[] = $currency;
                $currencies[] = array(
                    'code'     => $currency,
                    'symbol'   => $this->get_currency_symbol( $currency ),
                    'subtotal' => $subtotal,
                );
            } else {
                foreach ( $currencies as &$c ) {
                    if ( $c['code'] === $currency ) {
                        $c['subtotal'] += $subtotal;
                    }
                }
                unset( $c );
            }
        }

        // ZCred data.
        $zcred_used    = (float) $order->get_meta( '_znc_zcred_deducted' );
        $zcred_value   = (float) $order->get_meta( '_znc_zcred_monetary_value' );
        $zcred_earned  = (float) $order->get_meta( '_znc_zcred_earned' );
        $zcred_rate    = $order->get_meta( '_znc_zcred_exchange_rate' );

        // Payment breakdown.
        $monetary_total  = (float) $order->get_total();
        $payment_method  = $order->get_payment_method_title();
        $base_currency   = $order->get_currency();

        return array(
            'order_id'       => $order_id,
            'order_number'   => $order->get_order_number(),
            'date'           => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
            'date_display'   => $order->get_date_created()->format( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
            'status'         => $order->get_status(),
            'status_label'   => wc_get_order_status_name( $order->get_status() ),
            'shops'          => $shops,
            'shop_count'     => count( $shops ),
            'currencies'     => $currencies,
            'is_mixed'       => count( $currencies ) > 1,
            'base_currency'  => $base_currency,
            'total_items'    => $order->get_item_count(),
            'monetary_total' => $monetary_total,
            'payment_method' => $payment_method,
            'zcred'          => array(
                'used'     => $zcred_used,
                'value'    => $zcred_value,
                'earned'   => $zcred_earned,
                'rate'     => $zcred_rate,
                'was_used' => $zcred_used > 0,
            ),
            'detail_url'     => wc_get_account_endpoint_url( ZNC_My_Account::DETAIL_ENDPOINT ) . $order_id . '/',
        );
    }

    /**
     * Get full order detail including line items grouped by shop.
     *
     * @param int $parent_order_id Parent order ID.
     * @param int $user_id         User ID for ownership check.
     * @return array|WP_Error
     */
    public function get_order_detail( $parent_order_id, $user_id ) {
        $order = wc_get_order( $parent_order_id );

        if ( ! $order ) {
            return new WP_Error( 'not_found', __( 'Order not found.', 'zinckles-net-cart' ) );
        }

        if ( $order->get_customer_id() !== $user_id ) {
            return new WP_Error( 'forbidden', __( 'You do not have permission to view this order.', 'zinckles-net-cart' ) );
        }

        if ( $order->get_meta( '_znc_is_parent_order' ) !== 'yes' ) {
            return new WP_Error( 'not_netcart', __( 'This is not a Net Cart order.', 'zinckles-net-cart' ) );
        }

        $summary = $this->build_order_summary( $order );

        // Enrich with line items grouped by shop.
        $shops_detail = array();
        $child_orders = $this->get_child_orders( $parent_order_id );

        foreach ( $child_orders as $child ) {
            $site_id = $child['site_id'];
            $items   = $this->get_child_order_items( $site_id, $child['child_order_id'] );

            $shops_detail[] = array(
                'site_id'        => $site_id,
                'site_name'      => $child['site_name'],
                'site_url'       => $child['site_url'],
                'currency'       => $child['currency'],
                'currency_symbol'=> $this->get_currency_symbol( $child['currency'] ),
                'subtotal'       => $child['subtotal'],
                'tax'            => $child['tax'],
                'shipping'       => $child['shipping'],
                'shipping_method'=> $child['shipping_method'],
                'shop_total'     => $child['total'],
                'child_order_id' => $child['child_order_id'],
                'status'         => $child['status'],
                'status_label'   => wc_get_order_status_name( $child['status'] ),
                'badge_color'    => $child['badge_color'],
                'badge_icon'     => $child['badge_icon'],
                'items'          => $items,
                'item_count'     => count( $items ),
                'coupons'        => $child['coupons'],
                'coupon_discount'=> $child['coupon_discount'],
                'notes'          => $child['notes'],
            );
        }

        // Payment timeline.
        $timeline = $this->build_payment_timeline( $order );

        // Conversion breakdown for mixed currency.
        $conversion = array();
        if ( $summary['is_mixed'] ) {
            $conversion = array(
                'base_currency'      => $summary['base_currency'],
                'base_currency_symbol'=> $this->get_currency_symbol( $summary['base_currency'] ),
                'per_currency'       => array(),
            );
            foreach ( $summary['currencies'] as $curr ) {
                $rate = $order->get_meta( '_znc_exchange_rate_' . $curr['code'] );
                $converted = $order->get_meta( '_znc_converted_' . $curr['code'] );
                $conversion['per_currency'][] = array(
                    'code'      => $curr['code'],
                    'symbol'    => $curr['symbol'],
                    'original'  => $curr['subtotal'],
                    'rate'      => $rate ? (float) $rate : 1.0,
                    'converted' => $converted ? (float) $converted : $curr['subtotal'],
                );
            }
        }

        return array(
            'summary'      => $summary,
            'shops'        => $shops_detail,
            'timeline'     => $timeline,
            'conversion'   => $conversion,
            'billing'      => array(
                'name'    => $order->get_formatted_billing_full_name(),
                'email'   => $order->get_billing_email(),
                'address' => $order->get_formatted_billing_address(),
            ),
            'shipping'     => array(
                'name'    => $order->get_formatted_shipping_full_name(),
                'address' => $order->get_formatted_shipping_address(),
            ),
            'order_notes'  => $this->get_customer_notes( $order ),
        );
    }

    /**
     * Get child orders for a parent order from the order map table.
     *
     * @param int $parent_order_id Parent order ID.
     * @return array
     */
    private function get_child_orders( $parent_order_id ) {
        global $wpdb;
        $table = $wpdb->base_prefix . 'znc_order_map';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE parent_order_id = %d ORDER BY site_id ASC",
            $parent_order_id
        ), ARRAY_A );

        if ( ! $rows ) {
            // Fallback: read from order meta.
            $order = wc_get_order( $parent_order_id );
            $meta_children = $order ? $order->get_meta( '_znc_child_orders' ) : array();
            if ( ! is_array( $meta_children ) ) {
                return array();
            }
            $rows = $meta_children;
        }

        $children = array();
        foreach ( $rows as $row ) {
            $site_id = isset( $row['site_id'] ) ? (int) $row['site_id'] : 0;

            // Get site details.
            $site_details = get_blog_details( $site_id );
            $site_name    = $site_details ? $site_details->blogname : __( 'Unknown Shop', 'zinckles-net-cart' );
            $site_url     = $site_details ? $site_details->siteurl : '';

            // Get child order details from subsite.
            $child_data = $this->fetch_child_order_data( $site_id, isset( $row['child_order_id'] ) ? (int) $row['child_order_id'] : 0 );

            // Get shop branding from Net Cart settings.
            $badge_color = '';
            $badge_icon  = '';
            if ( $site_id ) {
                switch_to_blog( $site_id );
                $znc_subsite = get_option( 'znc_subsite_settings', array() );
                $badge_color = isset( $znc_subsite['badge_color'] ) ? $znc_subsite['badge_color'] : '#7c3aed';
                $badge_icon  = isset( $znc_subsite['badge_icon'] ) ? $znc_subsite['badge_icon'] : '';
                restore_current_blog();
            }

            $children[] = array_merge( array(
                'site_id'        => $site_id,
                'site_name'      => $site_name,
                'site_url'       => $site_url,
                'child_order_id' => isset( $row['child_order_id'] ) ? (int) $row['child_order_id'] : 0,
                'badge_color'    => $badge_color,
                'badge_icon'     => $badge_icon,
            ), $child_data );
        }

        return $children;
    }

    /**
     * Fetch child order data from a subsite.
     *
     * @param int $site_id        Subsite ID.
     * @param int $child_order_id Child order ID.
     * @return array
     */
    private function fetch_child_order_data( $site_id, $child_order_id ) {
        $defaults = array(
            'currency'        => 'USD',
            'subtotal'        => 0,
            'tax'             => 0,
            'shipping'        => 0,
            'shipping_method' => '',
            'total'           => 0,
            'status'          => 'pending',
            'item_count'      => 0,
            'coupons'         => array(),
            'coupon_discount' => 0,
            'notes'           => '',
        );

        if ( ! $site_id || ! $child_order_id ) {
            return $defaults;
        }

        switch_to_blog( $site_id );

        $child_order = wc_get_order( $child_order_id );
        if ( ! $child_order ) {
            restore_current_blog();
            return $defaults;
        }

        $coupons = array();
        foreach ( $child_order->get_coupon_codes() as $code ) {
            $coupons[] = $code;
        }

        $data = array(
            'currency'        => $child_order->get_currency(),
            'subtotal'        => (float) $child_order->get_subtotal(),
            'tax'             => (float) $child_order->get_total_tax(),
            'shipping'        => (float) $child_order->get_shipping_total(),
            'shipping_method' => $child_order->get_shipping_method(),
            'total'           => (float) $child_order->get_total(),
            'status'          => $child_order->get_status(),
            'item_count'      => $child_order->get_item_count(),
            'coupons'         => $coupons,
            'coupon_discount' => (float) $child_order->get_discount_total(),
            'notes'           => $child_order->get_customer_note(),
        );

        restore_current_blog();

        return $data;
    }

    /**
     * Get line items from a child order on a subsite.
     *
     * @param int $site_id        Subsite ID.
     * @param int $child_order_id Child order ID.
     * @return array
     */
    private function get_child_order_items( $site_id, $child_order_id ) {
        if ( ! $site_id || ! $child_order_id ) {
            return array();
        }

        switch_to_blog( $site_id );

        $order = wc_get_order( $child_order_id );
        if ( ! $order ) {
            restore_current_blog();
            return array();
        }

        $items = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();

            $items[] = array(
                'item_id'      => $item_id,
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name'         => $item->get_name(),
                'quantity'     => $item->get_quantity(),
                'unit_price'   => (float) $order->get_item_subtotal( $item, false, true ),
                'line_total'   => (float) $item->get_total(),
                'line_tax'     => (float) $item->get_total_tax(),
                'sku'          => $product ? $product->get_sku() : '',
                'image'        => $product ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : '',
                'product_url'  => $product ? $product->get_permalink() : '',
                'attributes'   => $this->get_item_attributes( $item ),
                'meta'         => $this->get_item_display_meta( $item ),
            );
        }

        restore_current_blog();

        return $items;
    }

    /**
     * Get variation attributes for an order item.
     *
     * @param WC_Order_Item_Product $item Order item.
     * @return array
     */
    private function get_item_attributes( $item ) {
        $attributes = array();
        $meta_data  = $item->get_meta_data();

        foreach ( $meta_data as $meta ) {
            $key = $meta->key;
            if ( strpos( $key, 'pa_' ) === 0 || strpos( $key, 'attribute_' ) === 0 ) {
                $label = wc_attribute_label( str_replace( 'attribute_', '', $key ) );
                $attributes[] = array(
                    'label' => $label,
                    'value' => $meta->value,
                );
            }
        }

        return $attributes;
    }

    /**
     * Get display-safe meta for an order item.
     *
     * @param WC_Order_Item_Product $item Order item.
     * @return array
     */
    private function get_item_display_meta( $item ) {
        $display_meta = array();
        $hidden_keys  = array( '_reduced_stock', '_restock_refunded_items', '_znc_origin_site', '_znc_origin_product_id' );

        foreach ( $item->get_meta_data() as $meta ) {
            if ( in_array( $meta->key, $hidden_keys, true ) || strpos( $meta->key, '_' ) === 0 ) {
                continue;
            }
            if ( strpos( $meta->key, 'pa_' ) === 0 || strpos( $meta->key, 'attribute_' ) === 0 ) {
                continue; // Handled in attributes.
            }
            $display_meta[] = array(
                'key'   => $meta->key,
                'value' => $meta->value,
            );
        }

        return $display_meta;
    }

    /**
     * Build payment timeline from order notes and meta.
     *
     * @param WC_Order $order Parent order.
     * @return array
     */
    private function build_payment_timeline( $order ) {
        $timeline = array();

        // Order placed.
        $timeline[] = array(
            'type'      => 'created',
            'label'     => __( 'Order Placed', 'zinckles-net-cart' ),
            'date'      => $order->get_date_created()->format( 'Y-m-d H:i:s' ),
            'icon'      => 'cart',
            'detail'    => sprintf( __( '%d items from %d shops', 'zinckles-net-cart' ),
                $order->get_item_count(),
                count( $this->get_child_orders( $order->get_id() ) )
            ),
        );

        // ZCred deduction.
        $zcred_used = (float) $order->get_meta( '_znc_zcred_deducted' );
        if ( $zcred_used > 0 ) {
            $zcred_time = $order->get_meta( '_znc_zcred_deducted_at' );
            $timeline[] = array(
                'type'   => 'zcred',
                'label'  => __( 'ZCreds Deducted', 'zinckles-net-cart' ),
                'date'   => $zcred_time ?: $order->get_date_created()->format( 'Y-m-d H:i:s' ),
                'icon'   => 'zcred',
                'detail' => sprintf( __( '%s ZCreds applied (value: %s)', 'zinckles-net-cart' ),
                    number_format( $zcred_used ),
                    wc_price( $order->get_meta( '_znc_zcred_monetary_value' ), array( 'currency' => $order->get_currency() ) )
                ),
            );
        }

        // Payment.
        if ( $order->get_date_paid() ) {
            $timeline[] = array(
                'type'   => 'payment',
                'label'  => __( 'Payment Processed', 'zinckles-net-cart' ),
                'date'   => $order->get_date_paid()->format( 'Y-m-d H:i:s' ),
                'icon'   => 'payment',
                'detail' => sprintf( __( '%s via %s', 'zinckles-net-cart' ),
                    $order->get_formatted_order_total(),
                    $order->get_payment_method_title()
                ),
            );
        }

        // Completion.
        if ( $order->get_date_completed() ) {
            $timeline[] = array(
                'type'   => 'completed',
                'label'  => __( 'Order Completed', 'zinckles-net-cart' ),
                'date'   => $order->get_date_completed()->format( 'Y-m-d H:i:s' ),
                'icon'   => 'check',
                'detail' => __( 'All shop orders fulfilled', 'zinckles-net-cart' ),
            );
        }

        // ZCred earned.
        $zcred_earned = (float) $order->get_meta( '_znc_zcred_earned' );
        if ( $zcred_earned > 0 ) {
            $timeline[] = array(
                'type'   => 'zcred_earn',
                'label'  => __( 'ZCreds Earned', 'zinckles-net-cart' ),
                'date'   => $order->get_date_completed() ? $order->get_date_completed()->format( 'Y-m-d H:i:s' ) : '',
                'icon'   => 'zcred',
                'detail' => sprintf( __( '+%s ZCreds earned on this purchase', 'zinckles-net-cart' ),
                    number_format( $zcred_earned )
                ),
            );
        }

        // Refund.
        $refunds = $order->get_refunds();
        foreach ( $refunds as $refund ) {
            $timeline[] = array(
                'type'   => 'refund',
                'label'  => __( 'Refund Issued', 'zinckles-net-cart' ),
                'date'   => $refund->get_date_created()->format( 'Y-m-d H:i:s' ),
                'icon'   => 'refund',
                'detail' => sprintf( __( '%s refunded — %s', 'zinckles-net-cart' ),
                    wc_price( abs( $refund->get_total() ), array( 'currency' => $order->get_currency() ) ),
                    $refund->get_reason() ?: __( 'No reason provided', 'zinckles-net-cart' )
                ),
            );
        }

        // Sort by date.
        usort( $timeline, function( $a, $b ) {
            return strtotime( $a['date'] ) - strtotime( $b['date'] );
        });

        return $timeline;
    }

    /**
     * Get customer-visible order notes.
     *
     * @param WC_Order $order Order.
     * @return array
     */
    private function get_customer_notes( $order ) {
        $notes = wc_get_order_notes( array(
            'order_id'        => $order->get_id(),
            'customer_note'   => 1,
            'order'           => 'ASC',
        ) );

        $formatted = array();
        foreach ( $notes as $note ) {
            $formatted[] = array(
                'date'    => $note->date_created->format( 'Y-m-d H:i:s' ),
                'content' => $note->content,
            );
        }

        return $formatted;
    }

    /**
     * Get aggregate stats for a user's Net Cart orders.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_user_stats( $user_id ) {
        $cached = get_transient( 'znc_user_stats_' . $user_id );
        if ( false !== $cached ) {
            return $cached;
        }

        $args = array(
            'customer_id' => $user_id,
            'meta_key'    => '_znc_is_parent_order',
            'meta_value'  => 'yes',
            'limit'       => -1,
            'return'      => 'ids',
            'status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded', 'wc-cancelled', 'wc-pending', 'wc-failed' ),
        );

        $order_ids = wc_get_orders( $args );

        $stats = array(
            'total_orders'    => count( $order_ids ),
            'total_spent'     => 0,
            'total_zcred_used'   => 0,
            'total_zcred_earned' => 0,
            'total_items'     => 0,
            'shops_purchased' => array(),
            'currencies_used' => array(),
            'status_counts'   => array(),
            'first_order_date'=> null,
            'last_order_date' => null,
        );

        foreach ( $order_ids as $oid ) {
            $order = wc_get_order( $oid );
            if ( ! $order ) {
                continue;
            }

            $stats['total_spent']       += (float) $order->get_total();
            $stats['total_zcred_used']  += (float) $order->get_meta( '_znc_zcred_deducted' );
            $stats['total_zcred_earned']+= (float) $order->get_meta( '_znc_zcred_earned' );
            $stats['total_items']       += $order->get_item_count();

            $status = $order->get_status();
            if ( ! isset( $stats['status_counts'][ $status ] ) ) {
                $stats['status_counts'][ $status ] = 0;
            }
            $stats['status_counts'][ $status ]++;

            $date = $order->get_date_created()->format( 'Y-m-d H:i:s' );
            if ( ! $stats['first_order_date'] || $date < $stats['first_order_date'] ) {
                $stats['first_order_date'] = $date;
            }
            if ( ! $stats['last_order_date'] || $date > $stats['last_order_date'] ) {
                $stats['last_order_date'] = $date;
            }

            // Gather unique shops and currencies.
            $children = $order->get_meta( '_znc_child_orders' );
            if ( is_array( $children ) ) {
                foreach ( $children as $child ) {
                    if ( isset( $child['site_id'] ) ) {
                        $stats['shops_purchased'][ $child['site_id'] ] = true;
                    }
                    if ( isset( $child['currency'] ) && ! in_array( $child['currency'], $stats['currencies_used'], true ) ) {
                        $stats['currencies_used'][] = $child['currency'];
                    }
                }
            }
        }

        $stats['shops_purchased'] = count( $stats['shops_purchased'] );
        $stats['base_currency']   = get_option( 'woocommerce_currency', 'USD' );

        set_transient( 'znc_user_stats_' . $user_id, $stats, HOUR_IN_SECONDS );

        return $stats;
    }

    /**
     * Get available filter options for a user.
     *
     * @param int $user_id User ID.
     * @return array
     */
    public function get_filter_options( $user_id ) {
        $cached = get_transient( 'znc_user_filters_' . $user_id );
        if ( false !== $cached ) {
            return $cached;
        }

        $order_ids = wc_get_orders( array(
            'customer_id' => $user_id,
            'meta_key'    => '_znc_is_parent_order',
            'meta_value'  => 'yes',
            'limit'       => -1,
            'return'      => 'ids',
            'status'      => array( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded', 'wc-cancelled', 'wc-pending', 'wc-failed' ),
        ) );

        $shops      = array();
        $currencies = array();
        $statuses   = array();

        foreach ( $order_ids as $oid ) {
            $order = wc_get_order( $oid );
            if ( ! $order ) {
                continue;
            }

            $status = $order->get_status();
            $statuses[ $status ] = wc_get_order_status_name( $status );

            $children = $order->get_meta( '_znc_child_orders' );
            if ( is_array( $children ) ) {
                foreach ( $children as $child ) {
                    if ( isset( $child['site_id'] ) ) {
                        $site = get_blog_details( $child['site_id'] );
                        if ( $site ) {
                            $shops[ $child['site_id'] ] = $site->blogname;
                        }
                    }
                    if ( isset( $child['currency'] ) ) {
                        $currencies[ $child['currency'] ] = $this->get_currency_symbol( $child['currency'] ) . ' ' . $child['currency'];
                    }
                }
            }
        }

        $options = array(
            'shops'      => $shops,
            'currencies' => $currencies,
            'statuses'   => $statuses,
        );

        set_transient( 'znc_user_filters_' . $user_id, $options, HOUR_IN_SECONDS );

        return $options;
    }

    /**
     * Get currency symbol helper.
     *
     * @param string $code Currency code.
     * @return string
     */
    private function get_currency_symbol( $code ) {
        if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
            return get_woocommerce_currency_symbol( $code );
        }
        $symbols = array(
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'C$',
            'AUD' => 'A$', 'JPY' => '¥', 'INR' => '₹', 'BRL' => 'R$',
        );
        return isset( $symbols[ $code ] ) ? $symbols[ $code ] : $code;
    }
}
