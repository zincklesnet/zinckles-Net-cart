<?php
/**
 * Cart Snapshot — v1.5.0 REWRITE
 *
 * ARCHITECTURE CHANGE: Uses wp_usermeta instead of custom DB table.
 * wp_usermeta is shared across ALL sites in a WordPress multisite network.
 * This means update_user_meta() and get_user_meta() work identically
 * from ANY blog — zero switch_to_blog(), zero custom tables, zero REST calls.
 *
 * Cart data is stored as a JSON array in user meta key '_znc_global_cart'.
 * Each item is keyed by "{blog_id}_{product_id}_{variation_id}".
 *
 * @package ZincklesNetCart
 * @since   1.5.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Snapshot {

    /** @var ZNC_Checkout_Host */
    private $host;

    /** @var bool Recursion guard */
    private static $pushing = false;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {
        add_action( 'woocommerce_add_to_cart',                        array( $this, 'on_add_to_cart' ), 20, 6 );
        add_action( 'woocommerce_cart_item_removed',                  array( $this, 'on_remove_from_cart' ), 20, 2 );
        add_action( 'woocommerce_after_cart_item_quantity_update',     array( $this, 'on_qty_update' ), 20, 4 );
        add_filter( 'woocommerce_add_to_cart_fragments',              array( $this, 'inject_cart_count_fragment' ) );
    }

    /* ────────────────────────────────────────────────────────────
     * ADD TO CART
     * ──────────────────────────────────────────────────────────── */

    public function on_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        if ( self::$pushing ) return;
        if ( ! is_user_logged_in() ) return;

        self::$pushing = true;

        $product = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product ) {
            self::$pushing = false;
            return;
        }

        $user_id   = get_current_user_id();
        $blog_id   = get_current_blog_id();
        $item_key  = self::make_key( $blog_id, $product_id, $variation_id );

        // Build item data — all captured in the subsite's context
        $item = array(
            'blog_id'        => $blog_id,
            'product_id'     => $product_id,
            'variation_id'   => $variation_id ? (int) $variation_id : 0,
            'quantity'       => (int) $quantity,
            'product_name'   => $product->get_name(),
            'price'          => (float) $product->get_price(),
            'currency'       => get_woocommerce_currency(),
            'image_url'      => wp_get_attachment_url( $product->get_image_id() ) ?: '',
            'sku'            => $product->get_sku(),
            'permalink'      => get_permalink( $product_id ),
            'shop_name'      => get_bloginfo( 'name' ),
            'shop_url'       => home_url(),
            'variation_data' => $variation ? $variation : array(),
            'in_stock'       => $product->is_in_stock() ? 1 : 0,
            'stock_qty'      => $product->get_stock_quantity(),
            'meta_data'      => $cart_item_data,
            'updated_at'     => current_time( 'mysql', true ),
        );

        // Get existing global cart from usermeta
        $cart = self::get_cart( $user_id );

        // Upsert: if item exists, update quantity; otherwise add
        if ( isset( $cart[ $item_key ] ) ) {
            $cart[ $item_key ]['quantity']   = $item['quantity'];
            $cart[ $item_key ]['price']      = $item['price'];
            $cart[ $item_key ]['in_stock']   = $item['in_stock'];
            $cart[ $item_key ]['stock_qty']  = $item['stock_qty'];
            $cart[ $item_key ]['updated_at'] = $item['updated_at'];
        } else {
            $item['created_at'] = current_time( 'mysql', true );
            $cart[ $item_key ]  = $item;
        }

        // Save back to usermeta — works from ANY blog in the network
        self::save_cart( $user_id, $cart );

        // Optionally clear from local WC cart
        $settings = get_site_option( 'znc_network_settings', array() );
        if ( ! empty( $settings['clear_local_cart'] ) ) {
            WC()->cart->remove_cart_item( $cart_item_key );
        }

        // Invalidate cart count cache
        ZNC_Cart_Sync::invalidate( $user_id );

        self::$pushing = false;

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[ZNC v1.5] Added product %d from blog %d to global cart (user %d, key %s, total items: %d)',
                $product_id, $blog_id, $user_id, $item_key, count( $cart )
            ) );
        }
    }

    /* ────────────────────────────────────────────────────────────
     * REMOVE FROM CART
     * ──────────────────────────────────────────────────────────── */

    public function on_remove_from_cart( $cart_item_key, $wc_cart ) {
        if ( ! is_user_logged_in() ) return;

        $item = isset( $wc_cart->removed_cart_contents[ $cart_item_key ] )
            ? $wc_cart->removed_cart_contents[ $cart_item_key ]
            : null;
        if ( ! $item ) return;

        $user_id  = get_current_user_id();
        $blog_id  = get_current_blog_id();
        $item_key = self::make_key( $blog_id, $item['product_id'], isset( $item['variation_id'] ) ? $item['variation_id'] : 0 );

        $cart = self::get_cart( $user_id );
        if ( isset( $cart[ $item_key ] ) ) {
            unset( $cart[ $item_key ] );
            self::save_cart( $user_id, $cart );
        }

        ZNC_Cart_Sync::invalidate( $user_id );
    }

    /* ────────────────────────────────────────────────────────────
     * QUANTITY UPDATE
     * ──────────────────────────────────────────────────────────── */

    public function on_qty_update( $cart_item_key, $quantity, $old_quantity, $wc_cart ) {
        if ( ! is_user_logged_in() ) return;

        $item = isset( $wc_cart->cart_contents[ $cart_item_key ] )
            ? $wc_cart->cart_contents[ $cart_item_key ]
            : null;
        if ( ! $item ) return;

        $user_id  = get_current_user_id();
        $blog_id  = get_current_blog_id();
        $item_key = self::make_key( $blog_id, $item['product_id'], isset( $item['variation_id'] ) ? $item['variation_id'] : 0 );

        $cart = self::get_cart( $user_id );
        if ( isset( $cart[ $item_key ] ) ) {
            $cart[ $item_key ]['quantity']   = (int) $quantity;
            $cart[ $item_key ]['updated_at'] = current_time( 'mysql', true );
            self::save_cart( $user_id, $cart );
        }

        ZNC_Cart_Sync::invalidate( $user_id );
    }

    /* ────────────────────────────────────────────────────────────
     * CART COUNT FRAGMENT (AJAX)
     * ──────────────────────────────────────────────────────────── */

    public function inject_cart_count_fragment( $fragments ) {
        if ( ! is_user_logged_in() ) return $fragments;

        $cart  = self::get_cart( get_current_user_id() );
        $count = 0;
        foreach ( $cart as $item ) {
            $count += isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
        }

        $fragments['.znc-global-cart-count'] = '<span class="znc-global-cart-count">' . $count . '</span>';
        $fragments['.znc-cart-badge']        = '<span class="znc-cart-badge">' . $count . '</span>';
        return $fragments;
    }

    /* ════════════════════════════════════════════════════════════
     * STATIC HELPERS — used by shortcodes, widgets, templates
     * ════════════════════════════════════════════════════════════ */

    /**
     * Generate a unique key for a cart item.
     */
    public static function make_key( $blog_id, $product_id, $variation_id = 0 ) {
        return (int) $blog_id . '_' . (int) $product_id . '_' . (int) $variation_id;
    }

    /**
     * Get the global cart for a user.
     * Works from ANY blog in the network — zero switch_to_blog().
     *
     * @param int $user_id
     * @return array Associative array of cart items keyed by item_key
     */
    public static function get_cart( $user_id ) {
        $raw = get_user_meta( $user_id, ZNC_CART_META_KEY, true );
        if ( empty( $raw ) || ! is_array( $raw ) ) {
            return array();
        }
        return $raw;
    }

    /**
     * Save the global cart for a user.
     * Works from ANY blog in the network — zero switch_to_blog().
     *
     * @param int   $user_id
     * @param array $cart
     */
    public static function save_cart( $user_id, $cart ) {
        if ( empty( $cart ) ) {
            delete_user_meta( $user_id, ZNC_CART_META_KEY );
        } else {
            update_user_meta( $user_id, ZNC_CART_META_KEY, $cart );
        }
    }

    /**
     * Get total item count in global cart.
     */
    public static function get_count( $user_id ) {
        $cart  = self::get_cart( $user_id );
        $count = 0;
        foreach ( $cart as $item ) {
            $count += isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
        }
        return $count;
    }

    /**
     * Get total value of global cart.
     */
    public static function get_total( $user_id ) {
        $cart  = self::get_cart( $user_id );
        $total = 0;
        foreach ( $cart as $item ) {
            $qty   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
            $price = isset( $item['price'] ) ? (float) $item['price'] : 0;
            $total += $qty * $price;
        }
        return $total;
    }

    /**
     * Get cart items grouped by blog_id (shop).
     */
    public static function get_grouped( $user_id ) {
        $cart    = self::get_cart( $user_id );
        $grouped = array();
        foreach ( $cart as $key => $item ) {
            $blog_id = isset( $item['blog_id'] ) ? (int) $item['blog_id'] : 0;
            if ( ! isset( $grouped[ $blog_id ] ) ) {
                $grouped[ $blog_id ] = array(
                    'shop_name' => isset( $item['shop_name'] ) ? $item['shop_name'] : 'Shop #' . $blog_id,
                    'shop_url'  => isset( $item['shop_url'] ) ? $item['shop_url'] : '',
                    'currency'  => isset( $item['currency'] ) ? $item['currency'] : 'USD',
                    'items'     => array(),
                );
            }
            $grouped[ $blog_id ]['items'][ $key ] = $item;
        }
        return $grouped;
    }

    /**
     * Get unique shop count.
     */
    public static function get_shop_count( $user_id ) {
        $cart  = self::get_cart( $user_id );
        $blogs = array();
        foreach ( $cart as $item ) {
            if ( isset( $item['blog_id'] ) ) {
                $blogs[ (int) $item['blog_id'] ] = true;
            }
        }
        return count( $blogs );
    }

    /**
     * Remove a specific item by key.
     */
    public static function remove_item( $user_id, $item_key ) {
        $cart = self::get_cart( $user_id );
        if ( isset( $cart[ $item_key ] ) ) {
            unset( $cart[ $item_key ] );
            self::save_cart( $user_id, $cart );
            return true;
        }
        return false;
    }

    /**
     * Update quantity of a specific item.
     */
    public static function update_qty( $user_id, $item_key, $quantity ) {
        $cart = self::get_cart( $user_id );
        if ( isset( $cart[ $item_key ] ) ) {
            if ( $quantity <= 0 ) {
                unset( $cart[ $item_key ] );
            } else {
                $cart[ $item_key ]['quantity']   = (int) $quantity;
                $cart[ $item_key ]['updated_at'] = current_time( 'mysql', true );
            }
            self::save_cart( $user_id, $cart );
            return true;
        }
        return false;
    }

    /**
     * Clear entire global cart for a user.
     */
    public static function clear_cart( $user_id ) {
        delete_user_meta( $user_id, ZNC_CART_META_KEY );
    }
}
