<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Snapshot {

    public function init() {
        add_action( 'woocommerce_add_to_cart', array( $this, 'on_add_to_cart' ), 20, 6 );
        add_action( 'woocommerce_cart_item_removed', array( $this, 'on_remove_from_cart' ), 20, 2 );
        add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'on_quantity_update' ), 20, 4 );
    }

    /**
     * Build a full cart snapshot for a user on the current subsite.
     */
    public function build( int $user_id ) : array {
        $subsite_settings = $this->get_subsite_settings();
        $eligible_mode    = $subsite_settings['product_mode'] ?? 'all';
        $include_ids      = $subsite_settings['include_products'] ?? array();
        $exclude_ids      = $subsite_settings['exclude_products'] ?? array();
        $exclude_cats     = $subsite_settings['exclude_categories'] ?? array();

        // Get WC cart for this user
        $cart_items = array();
        $wc_cart    = WC()->cart;

        if ( ! $wc_cart ) {
            return array(
                'site_id'  => get_current_blog_id(),
                'user_id'  => $user_id,
                'currency' => get_woocommerce_currency(),
                'items'    => array(),
            );
        }

        foreach ( $wc_cart->get_cart() as $key => $item ) {
            $product = $item['data'];
            if ( ! $product ) continue;

            // Product eligibility filter
            if ( ! $this->is_product_eligible( $product, $eligible_mode, $include_ids, $exclude_ids, $exclude_cats ) ) {
                continue;
            }

            $cart_items[] = array(
                'product_id'   => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? 0,
                'quantity'     => $item['quantity'],
                'unit_price'   => floatval( $product->get_price() ),
                'line_total'   => floatval( $item['line_total'] ?? ( $product->get_price() * $item['quantity'] ) ),
                'name'         => $product->get_name(),
                'sku'          => $product->get_sku(),
                'weight'       => $product->get_weight(),
                'in_stock'     => $product->is_in_stock(),
                'stock_qty'    => $product->managing_stock() ? $product->get_stock_quantity() : null,
                'image_url'    => wp_get_attachment_url( $product->get_image_id() ),
                'permalink'    => $product->get_permalink(),
                'meta'         => $this->get_item_meta( $item ),
            );
        }

        // Coupons applied to this cart
        $coupons = array();
        foreach ( $wc_cart->get_applied_coupons() as $code ) {
            $coupon = new WC_Coupon( $code );
            $coupons[] = array(
                'code'            => $code,
                'discount_type'   => $coupon->get_discount_type(),
                'amount'          => floatval( $coupon->get_amount() ),
                'discount_amount' => floatval( $wc_cart->get_coupon_discount_amount( $code ) ),
            );
        }

        return array(
            'site_id'    => get_current_blog_id(),
            'site_name'  => get_bloginfo( 'name' ),
            'site_url'   => home_url(),
            'user_id'    => $user_id,
            'currency'   => get_woocommerce_currency(),
            'items'      => $cart_items,
            'coupons'    => $coupons,
            'subtotal'   => floatval( $wc_cart->get_subtotal() ),
            'tax_total'  => floatval( $wc_cart->get_total_tax() ),
            'total'      => floatval( $wc_cart->get_total( 'edit' ) ),
            'timestamp'  => current_time( 'mysql', true ),
        );
    }

    /**
     * Auto-push snapshot to main site when item is added to subsite cart.
     */
    public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        if ( is_main_site() ) return;

        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        // Non-blocking push via shutdown hook
        add_action( 'shutdown', function() use ( $user_id, $product_id, $variation_id, $quantity ) {
            $product = wc_get_product( $variation_id ?: $product_id );
            if ( ! $product ) return;

            $subsite_settings = $this->get_subsite_settings();
            $eligible_mode    = $subsite_settings['product_mode'] ?? 'all';
            if ( ! $this->is_product_eligible( $product, $eligible_mode,
                $subsite_settings['include_products'] ?? array(),
                $subsite_settings['exclude_products'] ?? array(),
                $subsite_settings['exclude_categories'] ?? array()
            ) ) {
                return;
            }

            $payload = array(
                'user_id'      => $user_id,
                'site_id'      => get_current_blog_id(),
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'quantity'     => $quantity,
                'unit_price'   => floatval( $product->get_price() ),
                'currency'     => get_woocommerce_currency(),
                'name'         => $product->get_name(),
                'image_url'    => wp_get_attachment_url( $product->get_image_id() ),
            );

            ZNC_REST_Auth::remote_request( get_main_site_id(), '/global-cart/add', $payload );
        } );
    }

    /**
     * Remove item from global cart when removed from subsite cart.
     */
    public function on_remove_from_cart( $cart_item_key, $cart ) {
        if ( is_main_site() ) return;
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
        if ( ! $item ) return;

        add_action( 'shutdown', function() use ( $user_id, $item ) {
            ZNC_REST_Auth::remote_request( get_main_site_id(), '/global-cart/remove', array(
                'user_id'    => $user_id,
                'site_id'    => get_current_blog_id(),
                'product_id' => $item['product_id'],
            ) );
        } );
    }

    /**
     * Update quantity in global cart.
     */
    public function on_quantity_update( $cart_item_key, $quantity, $old_quantity, $cart ) {
        if ( is_main_site() || ! get_current_user_id() ) return;

        $item = $cart->get_cart_item( $cart_item_key );
        if ( ! $item ) return;

        add_action( 'shutdown', function() use ( $item, $quantity ) {
            $product = $item['data'];
            ZNC_REST_Auth::remote_request( get_main_site_id(), '/global-cart/add', array(
                'user_id'      => get_current_user_id(),
                'site_id'      => get_current_blog_id(),
                'product_id'   => $item['product_id'],
                'variation_id' => $item['variation_id'] ?? 0,
                'quantity'     => $quantity,
                'unit_price'   => floatval( $product->get_price() ),
                'currency'     => get_woocommerce_currency(),
                'name'         => $product->get_name(),
            ) );
        } );
    }

    /**
     * Check if a product passes the subsite eligibility filters.
     */
    private function is_product_eligible( $product, string $mode, array $include, array $exclude, array $exclude_cats ) : bool {
        $id = $product->get_id();

        if ( 'include' === $mode && ! in_array( $id, $include, true ) ) {
            return false;
        }
        if ( in_array( $id, $exclude, true ) ) {
            return false;
        }

        $cat_ids = $product->get_category_ids();
        if ( array_intersect( $cat_ids, $exclude_cats ) ) {
            return false;
        }

        // Check subsite-level settings
        $subsite = $this->get_subsite_settings();
        if ( ! empty( $subsite['exclude_backorders'] ) && $product->is_on_backorder() ) {
            return false;
        }
        if ( ! empty( $subsite['exclude_on_sale'] ) && $product->is_on_sale() ) {
            return false;
        }
        $min = floatval( $subsite['min_price'] ?? 0 );
        $max = floatval( $subsite['max_price'] ?? 0 );
        $price = floatval( $product->get_price() );
        if ( $min > 0 && $price < $min ) return false;
        if ( $max > 0 && $price > $max ) return false;

        return apply_filters( 'znc_product_eligible', true, $product );
    }

    private function get_item_meta( array $item ) : array {
        $meta = array();
        $custom_keys = get_option( 'znc_subsite_meta_keys', array() );
        foreach ( $custom_keys as $key ) {
            if ( isset( $item[ $key ] ) ) {
                $meta[ $key ] = $item[ $key ];
            }
        }
        return $meta;
    }

    private function get_subsite_settings() : array {
        return get_option( 'znc_subsite_settings', array(
            'product_mode'       => 'all',
            'include_products'   => array(),
            'exclude_products'   => array(),
            'exclude_categories' => array(),
            'exclude_backorders' => false,
            'exclude_on_sale'    => false,
            'min_price'          => 0,
            'max_price'          => 0,
        ) );
    }
}
