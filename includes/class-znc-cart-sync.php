<?php
/**
 * Cart Sync — v1.5.0 REWRITE
 *
 * Replaces WooCommerce menu cart count/total with global cart data.
 * Now reads from wp_usermeta — zero switch_to_blog().
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Cart_Sync {

    /** @var ZNC_Checkout_Host */
    private $host;

    public function __construct( ZNC_Checkout_Host $host ) {
        $this->host = $host;
    }

    public function init() {
        add_filter( 'woocommerce_cart_contents_count',      array( $this, 'filter_count' ), 999 );
        add_filter( 'woocommerce_cart_subtotal',            array( $this, 'filter_subtotal' ), 999 );
        add_filter( 'woocommerce_widget_cart_item_visible',  '__return_true' );
        add_filter( 'woocommerce_add_to_cart_fragments',     array( $this, 'fragments' ), 999 );
        add_action( 'wp_head',                               array( $this, 'inline_badge_css' ) );
    }

    /**
     * Replace WC cart count with global cart count.
     * Reads from wp_usermeta — works from ANY blog.
     */
    public function filter_count( $count ) {
        if ( ! is_user_logged_in() ) return $count;
        return ZNC_Cart_Snapshot::get_count( get_current_user_id() );
    }

    /**
     * Replace WC cart subtotal with global cart total.
     */
    public function filter_subtotal( $subtotal ) {
        if ( ! is_user_logged_in() ) return $subtotal;
        $total = ZNC_Cart_Snapshot::get_total( get_current_user_id() );
        $settings = get_site_option( 'znc_network_settings', array() );
        $currency = isset( $settings['base_currency'] ) ? $settings['base_currency'] : 'USD';
        return html_entity_decode( get_woocommerce_currency_symbol( $currency ) ) . number_format( $total, 2 );
    }

    /**
     * AJAX fragments — update cart badge in header.
     */
    public function fragments( $fragments ) {
        if ( ! is_user_logged_in() ) return $fragments;
        $count = ZNC_Cart_Snapshot::get_count( get_current_user_id() );
        $fragments['.znc-global-cart-count'] = '<span class="znc-global-cart-count">' . $count . '</span>';
        $fragments['.znc-cart-badge']        = '<span class="znc-cart-badge">' . $count . '</span>';
        $fragments['.cart-contents .count']  = '<span class="count">' . $count . '</span>';
        return $fragments;
    }

    /**
     * Inline CSS for cart badge.
     */
    public function inline_badge_css() {
        echo '<style>.znc-cart-badge{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 6px;background:#7c3aed;color:#fff;border-radius:10px;font-size:11px;font-weight:700;line-height:1}</style>';
    }

    /**
     * Invalidate cached cart count for a user.
     * Called by Cart Snapshot after add/remove/update.
     */
    public static function invalidate( $user_id ) {
        // With wp_usermeta, no cache to invalidate —
        // get_user_meta() always returns fresh data.
        // This method is kept for backward compatibility.
    }
}
