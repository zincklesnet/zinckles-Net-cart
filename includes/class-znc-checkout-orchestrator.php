<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Orchestrator {

    private $store;
    private $merger;
    private $currency;
    private $mycred;
    private $orders;
    private $inventory;

    public function __construct(
        ZNC_Global_Cart_Store $store,
        ZNC_Global_Cart_Merger $merger,
        ZNC_Currency_Handler $currency,
        ZNC_MyCred_Engine $mycred,
        ZNC_Order_Factory $orders,
        ZNC_Inventory_Sync $inventory
    ) {
        $this->store     = $store;
        $this->merger    = $merger;
        $this->currency  = $currency;
        $this->mycred    = $mycred;
        $this->orders    = $orders;
        $this->inventory = $inventory;
    }

    public function init() {
        add_shortcode( 'znc_checkout', array( $this, 'render_checkout' ) );
    }

    /**
     * 10-step checkout process.
     */
    public function process( int $user_id, array $params = array() ) {
        $log = array();

        // Step 1: Refresh & re-validate all lines
        $log[] = 'Step 1: Refreshing cart...';
        $refresh = $this->merger->refresh_cart( $user_id );
        $cart    = $refresh['cart'];

        if ( empty( $cart ) ) {
            return new WP_Error( 'znc_empty_cart', 'Your cart is empty.', array( 'status' => 400 ) );
        }

        // Step 2: Reject removed items
        $log[] = 'Step 2: Checking removed items...';
        if ( ! empty( $refresh['removed'] ) ) {
            $names = array_map( function( $item ) {
                return $item['product_id'] . ' (site ' . $item['site_id'] . ')';
            }, $refresh['removed'] );

            $config = apply_filters( 'znc_checkout_config', array() );
            $stock_action = $config['stock_change_action'] ?? 'block';

            if ( 'block' === $stock_action ) {
                return new WP_Error( 'znc_items_removed', 'Items removed due to stock changes: ' . implode( ', ', $names ), array(
                    'status'  => 409,
                    'removed' => $refresh['removed'],
                ) );
            }
            // 'remove' action: continue without those items
        }

        // Step 3: Build parallel totals
        $log[] = 'Step 3: Calculating totals...';
        $totals = $this->currency->parallel_totals( $cart );

        // Step 4: Validate MyCred deduction
        $log[]       = 'Step 4: Validating ZCreds...';
        $zcred_apply = floatval( $params['zcred_amount'] ?? 0 );
        $zcred_result = null;

        if ( $zcred_apply > 0 && $this->mycred->is_available() ) {
            $zcred_result = $this->mycred->validate_deduction( $user_id, $zcred_apply, $totals['converted_total'] );
            if ( is_wp_error( $zcred_result ) ) {
                return $zcred_result;
            }
        }

        // Step 5: Per-subsite price/stock validation
        $log[] = 'Step 5: Final validation with subsites...';
        $by_site = $this->store->get_cart_by_site( $user_id );

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
                return new WP_Error( 'znc_validation_failed', "Cannot reach shop (site {$site_id}) for final validation.", array( 'status' => 503 ) );
            }

            foreach ( $validation['results'] ?? array() as $result ) {
                if ( ! $result['valid'] ) {
                    $config = apply_filters( 'znc_checkout_config', array() );
                    $price_action = $config['price_change_action'] ?? 'block';

                    if ( 'block' === $price_action ) {
                        return new WP_Error( 'znc_price_changed', sprintf(
                            'Price changed for product %d on site %d: expected %s, now %s',
                            $result['product_id'], $site_id, $items[0]['unit_price'] ?? '?', $result['current_price'] ?? '?'
                        ), array( 'status' => 409, 'validation' => $result ) );
                    }
                }
            }
        }

        // Step 6: Deduct MyCred points
        $log[] = 'Step 6: Deducting ZCreds...';
        $zcred_deducted = 0;
        if ( $zcred_result && $zcred_apply > 0 ) {
            $deduct = $this->mycred->deduct( $user_id, $zcred_apply, 'Net Cart checkout' );
            if ( is_wp_error( $deduct ) ) {
                return $deduct;
            }
            $zcred_deducted = $zcred_apply;
        }

        // Step 7: Create parent order on main site
        $log[] = 'Step 7: Creating parent order...';
        $monetary_total = $totals['converted_total'] - ( $zcred_result['monetary_value'] ?? 0 );
        $parent_order   = $this->orders->create_parent_order( $user_id, $cart, array(
            'total'          => max( 0, $monetary_total ),
            'currency'       => $this->currency->get_base_currency(),
            'zcred_deducted' => $zcred_deducted,
            'zcred_value'    => $zcred_result['monetary_value'] ?? 0,
            'totals'         => $totals,
            'payment_method' => $params['payment_method'] ?? 'manual',
            'billing'        => $params['billing'] ?? array(),
            'shipping'       => $params['shipping'] ?? array(),
        ) );

        if ( is_wp_error( $parent_order ) ) {
            // Rollback ZCreds
            if ( $zcred_deducted > 0 ) {
                $this->mycred->refund( $user_id, $zcred_deducted, 'Checkout failed — refund' );
            }
            return $parent_order;
        }

        // Step 8: Create child orders on each subsite
        $log[]       = 'Step 8: Creating child orders...';
        $child_errors = array();
        $child_orders = array();

        foreach ( $by_site as $site_id => $items ) {
            $child = $this->orders->create_child_order( intval( $site_id ), $user_id, $items, $parent_order['order_id'] );
            if ( is_wp_error( $child ) ) {
                $child_errors[] = array( 'site_id' => $site_id, 'error' => $child->get_error_message() );
            } else {
                $child_orders[] = $child;
            }
        }

        // Step 9: Sync inventory
        $log[] = 'Step 9: Syncing inventory...';
        $sync_results = array();
        foreach ( $by_site as $site_id => $items ) {
            foreach ( $items as $item ) {
                $sync = $this->inventory->deduct( intval( $site_id ), $item );
                $sync_results[] = array(
                    'site_id'    => $site_id,
                    'product_id' => $item['product_id'],
                    'success'    => ! is_wp_error( $sync ),
                    'queued'     => is_wp_error( $sync ),
                );
            }
        }

        // Step 10: Clear cart & fire completion
        $log[] = 'Step 10: Finalizing...';
        $this->store->clear_cart( $user_id );

        do_action( 'znc_checkout_completed', array(
            'user_id'       => $user_id,
            'parent_order'  => $parent_order,
            'child_orders'  => $child_orders,
            'zcred_deducted'=> $zcred_deducted,
            'totals'        => $totals,
        ) );

        return array(
            'success'        => true,
            'parent_order_id'=> $parent_order['order_id'],
            'child_orders'   => $child_orders,
            'child_errors'   => $child_errors,
            'zcred_deducted' => $zcred_deducted,
            'monetary_total' => max( 0, $monetary_total ),
            'currency'       => $this->currency->get_base_currency(),
            'totals'         => $totals,
            'inventory_sync' => $sync_results,
            'log'            => $log,
        );
    }

    /**
     * Render checkout shortcode.
     */
    public function render_checkout( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p class="znc-notice">' . esc_html__( 'Please log in to checkout.', 'zinckles-net-cart' ) . '</p>';
        }
        ob_start();
        include ZNC_PLUGIN_DIR . 'templates/checkout.php';
        return ob_get_clean();
    }
}
