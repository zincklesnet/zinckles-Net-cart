<?php
/**
 * Plugin Name: ZNC Flyout Cart
 * Description: Standalone AJAX flyout cart for Zinckles Net Cart — reads usermeta directly, zero class dependencies.
 * Version:     1.0.0
 * Author:      Zinckles
 * Network:     true
 *
 * Drop into: /wp-content/mu-plugins/znc-flyout-cart.php
 */
defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════
 *  CONFIGURATION
 * ═══════════════════════════════════════════════════════════════ */
define( 'ZNC_FC_META_KEY', 'znc_global_cart' );

/* ═══════════════════════════════════════════════════════════════
 *  AJAX HANDLERS — registered for logged-in users only
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'wp_ajax_znc_fc_load',       'znc_fc_ajax_load' );
add_action( 'wp_ajax_znc_fc_update_qty', 'znc_fc_ajax_update_qty' );
add_action( 'wp_ajax_znc_fc_remove',     'znc_fc_ajax_remove' );
add_action( 'wp_ajax_znc_fc_clear',      'znc_fc_ajax_clear' );

/* ═══════════════════════════════════════════════════════════════
 *  FRONTEND — inject flyout cart on every frontend page
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'wp_footer', 'znc_fc_render_flyout', 999 );
add_action( 'wp_head',   'znc_fc_inject_styles', 999 );

/* ═══════════════════════════════════════════════════════════════
 *  SHOP BANNER — "Items go to your Global Net Cart" notice
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'woocommerce_before_shop_loop', 'znc_fc_shop_banner', 5 );
add_action( 'woocommerce_before_single_product', 'znc_fc_shop_banner', 5 );

/* ═══════════════════════════════════════════════════════════════
 *  ADD-TO-CART INTERCEPTION — backup interception via WC hook
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'woocommerce_add_to_cart', 'znc_fc_intercept_add_to_cart', 20, 6 );

/* ═══════════════════════════════════════════════════════════════
 *  CHECKOUT REDIRECT SUPPRESSION (backup for mu-plugin)
 * ═══════════════════════════════════════════════════════════════ */
add_action( 'template_redirect', 'znc_fc_suppress_checkout_redirect', 0 );


/* ─────────────────────────────────────────────────────────────
 *  HELPER: Get checkout host URL
 * ───────────────────────────────────────────────────────────── */
function znc_fc_get_host_url() {
    $settings = get_site_option( 'znc_network_settings', array() );
    $host_id  = absint( $settings['checkout_host_id'] ?? get_main_site_id() );
    return get_home_url( $host_id );
}

function znc_fc_is_enrolled() {
    $settings = get_site_option( 'znc_network_settings', array() );
    $mode     = $settings['enrollment_mode'] ?? 'opt-in';
    $blog_id  = get_current_blog_id();
    $host_id  = absint( $settings['checkout_host_id'] ?? get_main_site_id() );

    if ( $blog_id === $host_id ) return true;

    $blocked = array_map( 'absint', (array) ( $settings['blocked_sites'] ?? array() ) );
    if ( in_array( $blog_id, $blocked, true ) ) return false;

    if ( $mode === 'auto' || $mode === 'opt-out' ) return true;

    $enrolled = array_map( 'absint', (array) ( $settings['enrolled_sites'] ?? array() ) );
    return in_array( $blog_id, $enrolled, true );
}

/* ─────────────────────────────────────────────────────────────
 *  HELPER: Read raw cart from usermeta
 * ───────────────────────────────────────────────────────────── */
function znc_fc_get_raw_cart( $user_id = 0 ) {
    if ( ! $user_id ) $user_id = get_current_user_id();
    if ( ! $user_id ) return array();
    $raw = get_user_meta( $user_id, ZNC_FC_META_KEY, true );
    return is_array( $raw ) ? $raw : array();
}

function znc_fc_save_cart( $user_id, $cart ) {
    if ( empty( $cart ) ) {
        delete_user_meta( $user_id, ZNC_FC_META_KEY );
    } else {
        update_user_meta( $user_id, ZNC_FC_META_KEY, $cart );
    }
}

function znc_fc_get_count( $user_id = 0 ) {
    $cart  = znc_fc_get_raw_cart( $user_id );
    $count = 0;
    foreach ( $cart as $item ) {
        $count += absint( $item['quantity'] ?? 1 );
    }
    return $count;
}

/* ─────────────────────────────────────────────────────────────
 *  HELPER: Enrich cart items with product data from subsites
 * ───────────────────────────────────────────────────────────── */
function znc_fc_enrich_cart() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) return array();

    $raw_cart  = znc_fc_get_raw_cart( $user_id );
    $current   = get_current_blog_id();
    $enriched  = array();

    foreach ( $raw_cart as $key => $item ) {
        $bid = absint( $item['blog_id'] ?? 0 );
        $pid = absint( $item['product_id'] ?? 0 );
        if ( ! $bid || ! $pid ) continue;

        $switched = ( $current !== $bid );
        if ( $switched ) switch_to_blog( $bid );

        $product  = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
        $details  = get_blog_details( $bid );
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        $symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : '$';

        $name  = $product ? $product->get_name() : "Product #{$pid}";
        $price = $product ? (float) $product->get_price() : (float) ( $item['price'] ?? 0 );
        $image = '';
        if ( $product ) {
            $thumb_id = $product->get_image_id();
            if ( $thumb_id ) {
                $image = wp_get_attachment_image_url( $thumb_id, 'woocommerce_thumbnail' );
            }
        }
        if ( ! $image ) {
            $image = function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'woocommerce_thumbnail' ) : '';
        }

        $shop_name = $details ? $details->blogname : "Shop #{$bid}";
        $shop_url  = $details ? $details->siteurl . '/shop/' : '#';

        if ( $switched ) restore_current_blog();

        $qty = absint( $item['quantity'] ?? 1 );

        $enriched[] = array(
            'key'          => $key,
            'blog_id'      => $bid,
            'product_id'   => $pid,
            'name'         => $name,
            'price'        => $price,
            'quantity'     => $qty,
            'line_total'   => $price * $qty,
            'image'        => $image,
            'currency'     => $currency,
            'symbol'       => $symbol,
            'shop_name'    => $shop_name,
            'shop_url'     => $shop_url,
            'variation_id' => absint( $item['variation_id'] ?? 0 ),
        );
    }

    return $enriched;
}


/* ═══════════════════════════════════════════════════════════════
 *  AJAX: Load enriched cart
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_ajax_load() {
    check_ajax_referer( 'znc_fc_nonce', 'nonce' );
    $items = znc_fc_enrich_cart();

    // Group totals by currency
    $totals = array();
    foreach ( $items as $it ) {
        $c = $it['currency'];
        if ( ! isset( $totals[ $c ] ) ) {
            $totals[ $c ] = array( 'total' => 0, 'symbol' => $it['symbol'] );
        }
        $totals[ $c ]['total'] += $it['line_total'];
    }

    wp_send_json_success( array(
        'items'  => $items,
        'totals' => $totals,
        'count'  => znc_fc_get_count(),
    ) );
}


/* ═══════════════════════════════════════════════════════════════
 *  AJAX: Update quantity
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_ajax_update_qty() {
    check_ajax_referer( 'znc_fc_nonce', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

    $key = sanitize_text_field( $_POST['item_key'] ?? '' );
    $qty = absint( $_POST['quantity'] ?? 1 );
    if ( ! $key ) wp_send_json_error( 'Missing item key' );

    $cart = znc_fc_get_raw_cart( $user_id );
    if ( ! isset( $cart[ $key ] ) ) wp_send_json_error( 'Item not found' );

    if ( $qty < 1 ) {
        unset( $cart[ $key ] );
    } else {
        $cart[ $key ]['quantity'] = min( $qty, 999 );
        $cart[ $key ]['updated']  = time();
    }

    znc_fc_save_cart( $user_id, $cart );
    wp_send_json_success( array( 'count' => znc_fc_get_count( $user_id ) ) );
}


/* ═══════════════════════════════════════════════════════════════
 *  AJAX: Remove item
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_ajax_remove() {
    check_ajax_referer( 'znc_fc_nonce', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

    $key = sanitize_text_field( $_POST['item_key'] ?? '' );
    if ( ! $key ) wp_send_json_error( 'Missing item key' );

    $cart = znc_fc_get_raw_cart( $user_id );
    unset( $cart[ $key ] );
    znc_fc_save_cart( $user_id, $cart );

    wp_send_json_success( array( 'count' => znc_fc_get_count( $user_id ) ) );
}


/* ═══════════════════════════════════════════════════════════════
 *  AJAX: Clear cart
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_ajax_clear() {
    check_ajax_referer( 'znc_fc_nonce', 'nonce' );
    $user_id = get_current_user_id();
    if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

    znc_fc_save_cart( $user_id, array() );
    wp_send_json_success( array( 'count' => 0 ) );
}


/* ═══════════════════════════════════════════════════════════════
 *  ADD-TO-CART INTERCEPTION (backup — in case main plugin fails)
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_intercept_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
    if ( ! is_user_logged_in() ) return;
    if ( ! znc_fc_is_enrolled() ) return;

    $user_id = get_current_user_id();
    $blog_id = get_current_blog_id();
    $cart    = znc_fc_get_raw_cart( $user_id );

    // Build a deterministic key
    $parts = array(
        'b' => $blog_id,
        'p' => absint( $product_id ),
        'v' => absint( $variation_id ),
        'd' => ! empty( $variation ) ? md5( wp_json_encode( $variation ) ) : '',
    );
    $key = md5( wp_json_encode( $parts ) );

    // Skip if already exists (main plugin already added it)
    if ( isset( $cart[ $key ] ) ) {
        // Just ensure quantity is synced
        return;
    }

    $product = wc_get_product( $product_id );
    if ( ! $product ) return;

    $cart[ $key ] = array(
        'blog_id'      => $blog_id,
        'product_id'   => absint( $product_id ),
        'variation_id' => absint( $variation_id ),
        'variation'     => is_array( $variation ) ? array_map( 'sanitize_text_field', $variation ) : array(),
        'quantity'     => max( 1, absint( $quantity ) ),
        'price'        => (float) $product->get_price(),
        'added'        => time(),
        'updated'      => time(),
    );

    znc_fc_save_cart( $user_id, $cart );
}


/* ═══════════════════════════════════════════════════════════════
 *  CHECKOUT REDIRECT SUPPRESSION
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_suppress_checkout_redirect() {
    if ( is_admin() || wp_doing_ajax() ) return;

    global $post;
    if ( ! $post instanceof WP_Post ) return;

    $is_znc = has_shortcode( $post->post_content, 'znc_checkout' )
           || $post->post_name === 'checkout-g';

    if ( ! $is_znc ) return;

    remove_action( 'template_redirect', 'wc_template_redirect' );
    add_filter( 'woocommerce_is_checkout', '__return_false', 999 );
}


/* ═══════════════════════════════════════════════════════════════
 *  SHOP BANNER
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_shop_banner() {
    if ( ! is_user_logged_in() ) return;
    if ( ! znc_fc_is_enrolled() ) return;

    $host_url = znc_fc_get_host_url();
    $cart_url = $host_url . '/cart-g/';

    echo '<div class="znc-fc-shop-banner">';
    echo '<span class="znc-fc-banner-icon">🛒</span> ';
    echo 'Items you add go to your <strong>Global Net Cart</strong> on ';
    echo '<a href="' . esc_url( $cart_url ) . '">' . esc_html( get_blog_details( get_main_site_id() )->blogname ) . '</a>';
    echo '</div>';
}


/* ═══════════════════════════════════════════════════════════════
 *  STYLES — injected inline in <head>
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_inject_styles() {
    if ( is_admin() || ! is_user_logged_in() ) return;
    ?>
<style id="znc-flyout-cart-css">
/* ── Floating Cart Button ── */
.znc-fc-trigger {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 999999;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    border: none;
    cursor: pointer;
    box-shadow: 0 4px 24px rgba(99,102,241,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s, box-shadow 0.2s;
    -webkit-tap-highlight-color: transparent;
}
.znc-fc-trigger:hover {
    transform: scale(1.1);
    box-shadow: 0 8px 32px rgba(99,102,241,0.55);
}
.znc-fc-trigger svg {
    width: 28px;
    height: 28px;
    fill: #fff;
}
.znc-fc-badge {
    position: absolute;
    top: -4px;
    right: -4px;
    min-width: 22px;
    height: 22px;
    border-radius: 11px;
    background: #ef4444;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    box-shadow: 0 2px 8px rgba(239,68,68,0.5);
    transition: transform 0.3s cubic-bezier(.68,-.55,.27,1.55);
}
.znc-fc-badge.znc-fc-bounce {
    animation: zncBadgeBounce 0.5s cubic-bezier(.68,-.55,.27,1.55);
}
@keyframes zncBadgeBounce {
    0%   { transform: scale(1); }
    40%  { transform: scale(1.5); }
    70%  { transform: scale(0.85); }
    100% { transform: scale(1); }
}
.znc-fc-badge:empty, .znc-fc-badge[data-count="0"] { display: none; }

/* ── Overlay ── */
.znc-fc-overlay {
    position: fixed;
    inset: 0;
    z-index: 999998;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(2px);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s, visibility 0.3s;
}
.znc-fc-overlay.znc-fc-open {
    opacity: 1;
    visibility: visible;
}

/* ── Slide-in Panel ── */
.znc-fc-panel {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    z-index: 999999;
    width: 420px;
    max-width: 92vw;
    background: #fff;
    box-shadow: -8px 0 40px rgba(0,0,0,0.15);
    transform: translateX(100%);
    transition: transform 0.35s cubic-bezier(.22,1,.36,1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.znc-fc-panel.znc-fc-open {
    transform: translateX(0);
}

/* ── Panel Header ── */
.znc-fc-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
    flex-shrink: 0;
}
.znc-fc-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}
.znc-fc-header .znc-fc-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: rgba(255,255,255,0.2);
    color: #fff;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}
.znc-fc-header .znc-fc-close:hover {
    background: rgba(255,255,255,0.35);
}

/* ── Panel Body ── */
.znc-fc-body {
    flex: 1;
    overflow-y: auto;
    padding: 0;
}
.znc-fc-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #9ca3af;
    font-size: 15px;
}
.znc-fc-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #9ca3af;
    text-align: center;
}
.znc-fc-empty svg {
    width: 64px;
    height: 64px;
    fill: #e5e7eb;
    margin-bottom: 16px;
}
.znc-fc-empty p {
    font-size: 16px;
    margin: 0 0 4px;
    color: #6b7280;
}
.znc-fc-empty small {
    font-size: 13px;
    color: #9ca3af;
}

/* ── Shop Group ── */
.znc-fc-shop-group {
    border-bottom: 1px solid #f3f4f6;
}
.znc-fc-shop-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 12px 20px 8px;
    font-size: 12px;
    font-weight: 600;
    color: #6366f1;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: #f9fafb;
}

/* ── Cart Item ── */
.znc-fc-item {
    display: flex;
    gap: 14px;
    padding: 14px 20px;
    border-top: 1px solid #f3f4f6;
    position: relative;
    transition: background 0.2s, opacity 0.3s, max-height 0.4s;
    max-height: 200px;
    overflow: hidden;
}
.znc-fc-item.znc-fc-removing {
    opacity: 0;
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
}
.znc-fc-item-img {
    width: 64px;
    height: 64px;
    border-radius: 10px;
    object-fit: cover;
    background: #f3f4f6;
    flex-shrink: 0;
}
.znc-fc-item-info {
    flex: 1;
    min-width: 0;
}
.znc-fc-item-name {
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.znc-fc-item-price {
    font-size: 13px;
    color: #6b7280;
    margin: 0 0 8px;
}
.znc-fc-item-price strong {
    color: #1f2937;
}
.znc-fc-qty-row {
    display: flex;
    align-items: center;
    gap: 4px;
}
.znc-fc-qty-btn {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    background: #fff;
    color: #374151;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.znc-fc-qty-btn:hover {
    border-color: #6366f1;
    color: #6366f1;
    background: #eef2ff;
}
.znc-fc-qty-val {
    min-width: 32px;
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    color: #1f2937;
}
.znc-fc-remove {
    position: absolute;
    top: 12px;
    right: 16px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: #d1d5db;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.znc-fc-remove:hover {
    background: #fef2f2;
    color: #ef4444;
}

/* ── Panel Footer ── */
.znc-fc-footer {
    padding: 16px 20px;
    border-top: 2px solid #f3f4f6;
    background: #fff;
    flex-shrink: 0;
}
.znc-fc-totals {
    margin: 0 0 14px;
}
.znc-fc-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    font-size: 14px;
    color: #374151;
}
.znc-fc-total-row strong {
    font-size: 18px;
    color: #1f2937;
}
.znc-fc-total-label {
    color: #6b7280;
}
.znc-fc-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.znc-fc-btn {
    display: block;
    width: 100%;
    padding: 14px;
    border-radius: 10px;
    border: none;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s;
}
.znc-fc-btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: #fff;
}
.znc-fc-btn-primary:hover {
    box-shadow: 0 4px 16px rgba(99,102,241,0.4);
    transform: translateY(-1px);
    color: #fff;
    text-decoration: none;
}
.znc-fc-btn-outline {
    background: transparent;
    color: #6366f1;
    border: 2px solid #e5e7eb;
}
.znc-fc-btn-outline:hover {
    border-color: #6366f1;
    background: #eef2ff;
    text-decoration: none;
    color: #6366f1;
}
.znc-fc-btn-clear {
    background: transparent;
    color: #9ca3af;
    font-size: 13px;
    font-weight: 500;
    padding: 8px;
}
.znc-fc-btn-clear:hover {
    color: #ef4444;
}

/* ── Fly-in animation ── */
.znc-fc-fly-dot {
    position: fixed;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #6366f1;
    z-index: 9999999;
    pointer-events: none;
    box-shadow: 0 2px 12px rgba(99,102,241,0.6);
    transition: all 0.6s cubic-bezier(.22,1,.36,1);
}

/* ── Spinner ── */
.znc-fc-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: zncSpin 0.6s linear infinite;
    display: inline-block;
}
@keyframes zncSpin {
    to { transform: rotate(360deg); }
}

/* ── Shop Banner ── */
.znc-fc-shop-banner {
    background: linear-gradient(135deg, #eef2ff 0%, #ede9fe 100%);
    border: 1px solid #c7d2fe;
    border-radius: 10px;
    padding: 12px 18px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #4338ca;
    display: flex;
    align-items: center;
    gap: 6px;
}
.znc-fc-shop-banner a {
    color: #6366f1;
    text-decoration: underline;
    font-weight: 600;
}
.znc-fc-banner-icon {
    font-size: 18px;
}

/* ── Dark mode support ── */
body.flavor-flavor-flavor .znc-fc-panel,
html[data-flavor-flavor-mode="dark"] .znc-fc-panel,
.flavor-dark .znc-fc-panel {
    background: #1f2937;
}
</style>
    <?php
}


/* ═══════════════════════════════════════════════════════════════
 *  RENDER FLYOUT HTML + JS — injected in wp_footer
 * ═══════════════════════════════════════════════════════════════ */
function znc_fc_render_flyout() {
    if ( is_admin() || ! is_user_logged_in() ) return;

    $count        = znc_fc_get_count();
    $host_url     = znc_fc_get_host_url();
    $cart_url     = $host_url . '/cart-g/';
    $checkout_url = $host_url . '/checkout-g/';
    $nonce        = wp_create_nonce( 'znc_fc_nonce' );
    ?>

<!-- ZNC Flyout Cart -->
<div class="znc-fc-overlay" id="znc-fc-overlay"></div>

<div class="znc-fc-panel" id="znc-fc-panel">
    <div class="znc-fc-header">
        <h3>🛒 Net Cart <span class="znc-fc-header-count">(<?php echo $count; ?>)</span></h3>
        <button class="znc-fc-close" id="znc-fc-close" title="Close">&times;</button>
    </div>
    <div class="znc-fc-body" id="znc-fc-body">
        <div class="znc-fc-loading"><span class="znc-fc-spinner"></span>&nbsp; Loading cart...</div>
    </div>
    <div class="znc-fc-footer" id="znc-fc-footer" style="display:none">
        <div class="znc-fc-totals" id="znc-fc-totals"></div>
        <div class="znc-fc-actions">
            <a href="<?php echo esc_url( $checkout_url ); ?>" class="znc-fc-btn znc-fc-btn-primary">Proceed to Checkout →</a>
            <a href="<?php echo esc_url( $cart_url ); ?>" class="znc-fc-btn znc-fc-btn-outline">View Full Cart</a>
            <button class="znc-fc-btn znc-fc-btn-clear" id="znc-fc-clear">Clear Cart</button>
        </div>
    </div>
</div>

<button class="znc-fc-trigger" id="znc-fc-trigger" title="Open Net Cart">
    <svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0020 4H5.21l-.94-2H1zm16 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
    <span class="znc-fc-badge" id="znc-fc-badge" data-count="<?php echo $count; ?>"><?php echo $count; ?></span>
</button>

<script>
(function(){
    var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
    var nonce   = '<?php echo $nonce; ?>';
    var cartUrl = '<?php echo esc_url( $cart_url ); ?>';
    var checkoutUrl = '<?php echo esc_url( $checkout_url ); ?>';

    var trigger = document.getElementById('znc-fc-trigger');
    var panel   = document.getElementById('znc-fc-panel');
    var overlay = document.getElementById('znc-fc-overlay');
    var closeBtn= document.getElementById('znc-fc-close');
    var body    = document.getElementById('znc-fc-body');
    var footer  = document.getElementById('znc-fc-footer');
    var totals  = document.getElementById('znc-fc-totals');
    var badge   = document.getElementById('znc-fc-badge');
    var clearBtn= document.getElementById('znc-fc-clear');
    var headerCount = panel.querySelector('.znc-fc-header-count');

    var isOpen  = false;
    var loaded  = false;

    function open() {
        isOpen = true;
        panel.classList.add('znc-fc-open');
        overlay.classList.add('znc-fc-open');
        document.body.style.overflow = 'hidden';
        if ( ! loaded ) loadCart();
    }
    function close() {
        isOpen = false;
        panel.classList.remove('znc-fc-open');
        overlay.classList.remove('znc-fc-open');
        document.body.style.overflow = '';
    }

    trigger.addEventListener('click', open);
    overlay.addEventListener('click', close);
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && isOpen) close(); });

    function updateBadge(count) {
        badge.textContent = count;
        badge.setAttribute('data-count', count);
        badge.style.display = count > 0 ? 'flex' : 'none';
        headerCount.textContent = '(' + count + ')';
        badge.classList.remove('znc-fc-bounce');
        void badge.offsetWidth;
        badge.classList.add('znc-fc-bounce');
    }

    function ajax(action, data, cb) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', nonce);
        for (var k in data) fd.append(k, data[k]);
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(r){ if (cb) cb(r); })
            .catch(function(e){ console.error('[ZNC FC]', e); });
    }

    function loadCart() {
        body.innerHTML = '<div class="znc-fc-loading"><span class="znc-fc-spinner"></span>&nbsp; Loading cart...</div>';
        footer.style.display = 'none';

        ajax('znc_fc_load', {}, function(r) {
            loaded = true;
            if ( ! r.success || ! r.data.items || r.data.items.length === 0 ) {
                renderEmpty();
                updateBadge(0);
                return;
            }
            renderItems(r.data.items, r.data.totals);
            updateBadge(r.data.count);
        });
    }

    function renderEmpty() {
        body.innerHTML =
            '<div class="znc-fc-empty">' +
            '<svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0020 4H5.21l-.94-2H1zm16 16c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>' +
            '<p>Your Net Cart is empty</p>' +
            '<small>Add items from any shop across the network</small>' +
            '</div>';
        footer.style.display = 'none';
    }

    function renderItems(items, tots) {
        // Group by shop
        var shops = {};
        items.forEach(function(it){
            if (!shops[it.blog_id]) shops[it.blog_id] = { name: it.shop_name, url: it.shop_url, items: [] };
            shops[it.blog_id].items.push(it);
        });

        var html = '';
        for (var bid in shops) {
            var shop = shops[bid];
            html += '<div class="znc-fc-shop-group">';
            html += '<div class="znc-fc-shop-header">🏪 ' + escHtml(shop.name) + '</div>';
            shop.items.forEach(function(it){
                html += '<div class="znc-fc-item" data-key="' + it.key + '">';
                html += '<img class="znc-fc-item-img" src="' + escHtml(it.image || '') + '" alt="" loading="lazy">';
                html += '<div class="znc-fc-item-info">';
                html += '<div class="znc-fc-item-name" title="' + escHtml(it.name) + '">' + escHtml(it.name) + '</div>';
                html += '<div class="znc-fc-item-price">' + escHtml(it.symbol) + fmtNum(it.price) + ' &times; ' + it.quantity + ' = <strong>' + escHtml(it.symbol) + fmtNum(it.line_total) + '</strong></div>';
                html += '<div class="znc-fc-qty-row">';
                html += '<button class="znc-fc-qty-btn" data-dir="-1" data-key="' + it.key + '">−</button>';
                html += '<span class="znc-fc-qty-val">' + it.quantity + '</span>';
                html += '<button class="znc-fc-qty-btn" data-dir="1" data-key="' + it.key + '">+</button>';
                html += '</div>';
                html += '</div>';
                html += '<button class="znc-fc-remove" data-key="' + it.key + '" title="Remove">&times;</button>';
                html += '</div>';
            });
            html += '</div>';
        }

        body.innerHTML = html;

        // Totals
        var thtml = '';
        for (var cur in tots) {
            thtml += '<div class="znc-fc-total-row">';
            thtml += '<span class="znc-fc-total-label">Subtotal (' + escHtml(cur) + ')</span>';
            thtml += '<strong>' + escHtml(tots[cur].symbol) + fmtNum(tots[cur].total) + '</strong>';
            thtml += '</div>';
        }
        totals.innerHTML = thtml;
        footer.style.display = 'block';

        // Wire qty buttons
        body.querySelectorAll('.znc-fc-qty-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                var key = this.getAttribute('data-key');
                var dir = parseInt(this.getAttribute('data-dir'));
                var row = body.querySelector('.znc-fc-item[data-key="' + key + '"]');
                var valEl = row.querySelector('.znc-fc-qty-val');
                var newQty = parseInt(valEl.textContent) + dir;
                if (newQty < 1) newQty = 0;

                if (newQty === 0) {
                    doRemove(key, row);
                } else {
                    valEl.textContent = newQty;
                    ajax('znc_fc_update_qty', { item_key: key, quantity: newQty }, function(r){
                        if (r.success) {
                            updateBadge(r.data.count);
                            loadCart(); // refresh totals
                        }
                    });
                }
            });
        });

        // Wire remove buttons
        body.querySelectorAll('.znc-fc-remove').forEach(function(btn){
            btn.addEventListener('click', function(){
                var key = this.getAttribute('data-key');
                var row = body.querySelector('.znc-fc-item[data-key="' + key + '"]');
                doRemove(key, row);
            });
        });
    }

    function doRemove(key, row) {
        row.classList.add('znc-fc-removing');
        ajax('znc_fc_remove', { item_key: key }, function(r){
            if (r.success) {
                updateBadge(r.data.count);
                setTimeout(function(){ loadCart(); }, 400);
            }
        });
    }

    // Clear cart
    clearBtn.addEventListener('click', function(){
        if ( ! confirm('Clear all items from your Net Cart?') ) return;
        ajax('znc_fc_clear', {}, function(r){
            if (r.success) {
                updateBadge(0);
                renderEmpty();
            }
        });
    });

    // ── Fly-in animation when WC adds item ──
    if (typeof jQuery !== 'undefined') {
        jQuery(document.body).on('added_to_cart', function(e, fragments, hash, btn){
            flyToCart(btn);
            // Refresh our cart data
            setTimeout(function(){
                loaded = false;
                ajax('znc_fc_load', {}, function(r){
                    loaded = true;
                    if (r.success) updateBadge(r.data.count);
                    if (isOpen && r.success) renderItems(r.data.items, r.data.totals);
                });
            }, 500);
        });
    }

    function flyToCart(btn) {
        if (!btn || !btn.length) return;
        var el = btn[0] || btn;
        var rect = el.getBoundingClientRect();
        var tRect = trigger.getBoundingClientRect();

        var dot = document.createElement('div');
        dot.className = 'znc-fc-fly-dot';
        dot.style.left = rect.left + rect.width/2 - 10 + 'px';
        dot.style.top  = rect.top  + rect.height/2 - 10 + 'px';
        document.body.appendChild(dot);

        requestAnimationFrame(function(){
            dot.style.left    = tRect.left + tRect.width/2 - 10 + 'px';
            dot.style.top     = tRect.top  + tRect.height/2 - 10 + 'px';
            dot.style.width   = '8px';
            dot.style.height  = '8px';
            dot.style.opacity = '0.3';
        });

        setTimeout(function(){
            dot.remove();
            updateBadge(parseInt(badge.textContent || '0') + 1);
        }, 650);
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s || ''));
        return d.innerHTML;
    }
    function fmtNum(n) {
        return parseFloat(n || 0).toFixed(2);
    }

})();
</script>
<!-- /ZNC Flyout Cart -->
    <?php
}
