<?php
/**
 * Shortcodes — v1.5.0 REWRITE
 * All shortcodes now read from wp_usermeta via ZNC_Cart_Snapshot.
 * Zero switch_to_blog(), zero custom table queries.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Shortcodes {

    private static $host = null;

    public static function init( ZNC_Checkout_Host $host ) {
        self::$host = $host;
        add_shortcode( 'znc_global_cart',     array( __CLASS__, 'global_cart' ) );
        add_shortcode( 'znc_checkout',        array( __CLASS__, 'checkout' ) );
        add_shortcode( 'znc_cart_count',      array( __CLASS__, 'cart_count' ) );
        add_shortcode( 'znc_cart_total',      array( __CLASS__, 'cart_total' ) );
        add_shortcode( 'znc_cart_button',     array( __CLASS__, 'cart_button' ) );
        add_shortcode( 'znc_mini_cart',       array( __CLASS__, 'mini_cart' ) );
        add_shortcode( 'znc_shop_count',      array( __CLASS__, 'shop_count' ) );
        add_shortcode( 'znc_points_balance',  array( __CLASS__, 'points_balance' ) );
        add_shortcode( 'znc_enrolled_shops',  array( __CLASS__, 'enrolled_shops' ) );
        add_shortcode( 'znc_cart_summary',    array( __CLASS__, 'cart_summary' ) );
    }

    /* ── [znc_global_cart] ────────────────────────────────────── */
    public static function global_cart( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="znc-login-required"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your global cart.</p></div>';
        }

        wp_enqueue_style( 'znc-front', ZNC_PLUGIN_URL . 'assets/css/znc-front.css', array(), ZNC_VERSION );
        wp_enqueue_script( 'znc-front', ZNC_PLUGIN_URL . 'assets/js/znc-front.js', array( 'jquery' ), ZNC_VERSION, true );
        wp_localize_script( 'znc-front', 'zncFront', array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'znc_cart_action' ),
            'checkoutUrl'  => self::$host ? self::$host->get_checkout_url() : '',
            'currency'     => get_woocommerce_currency_symbol(),
        ) );

        ob_start();
        include ZNC_PLUGIN_DIR . 'templates/global-cart.php';
        return ob_get_clean();
    }

    /* ── [znc_checkout] ───────────────────────────────────────── */
    public static function checkout( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<div class="znc-login-required"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to checkout.</p></div>';
        }

        wp_enqueue_style( 'znc-front', ZNC_PLUGIN_URL . 'assets/css/znc-front.css', array(), ZNC_VERSION );
        wp_enqueue_script( 'znc-front', ZNC_PLUGIN_URL . 'assets/js/znc-front.js', array( 'jquery' ), ZNC_VERSION, true );
        wp_localize_script( 'znc-front', 'zncFront', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'znc_cart_action' ),
            'currency' => get_woocommerce_currency_symbol(),
        ) );

        ob_start();
        include ZNC_PLUGIN_DIR . 'templates/checkout.php';
        return ob_get_clean();
    }

    /* ── [znc_cart_count] ─────────────────────────────────────── */
    public static function cart_count( $atts ) {
        if ( ! is_user_logged_in() ) return '<span class="znc-cart-count">0</span>';
        $count = ZNC_Cart_Snapshot::get_count( get_current_user_id() );
        return '<span class="znc-cart-count znc-global-cart-count">' . $count . '</span>';
    }

    /* ── [znc_cart_total] ─────────────────────────────────────── */
    public static function cart_total( $atts ) {
        if ( ! is_user_logged_in() ) return '<span class="znc-cart-total">$0.00</span>';
        $total    = ZNC_Cart_Snapshot::get_total( get_current_user_id() );
        $settings = get_site_option( 'znc_network_settings', array() );
        $currency = isset( $settings['base_currency'] ) ? $settings['base_currency'] : 'USD';
        $symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : '$';
        return '<span class="znc-cart-total">' . $symbol . number_format( $total, 2 ) . '</span>';
    }

    /* ── [znc_cart_button] ────────────────────────────────────── */
    public static function cart_button( $atts ) {
        $atts = shortcode_atts( array( 'text' => 'View Global Cart', 'class' => '' ), $atts );
        $url  = self::$host ? self::$host->get_cart_url() : '#';
        $count = is_user_logged_in() ? ZNC_Cart_Snapshot::get_count( get_current_user_id() ) : 0;
        $cls   = 'znc-cart-button ' . esc_attr( $atts['class'] );
        return '<a href="' . esc_url( $url ) . '" class="' . $cls . '">🛒 ' . esc_html( $atts['text'] ) . ' <span class="znc-cart-badge">' . $count . '</span></a>';
    }

    /* ── [znc_mini_cart] ──────────────────────────────────────── */
    public static function mini_cart( $atts ) {
        if ( ! is_user_logged_in() ) return '';

        $user_id = get_current_user_id();
        $cart    = ZNC_Cart_Snapshot::get_cart( $user_id );
        $count   = ZNC_Cart_Snapshot::get_count( $user_id );
        $total   = ZNC_Cart_Snapshot::get_total( $user_id );
        $symbol  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

        $html = '<div class="znc-mini-cart">';
        $html .= '<div class="znc-mini-header">🛒 Net Cart <span class="znc-cart-badge">' . $count . '</span></div>';

        if ( empty( $cart ) ) {
            $html .= '<p class="znc-empty">Your global cart is empty.</p>';
        } else {
            $html .= '<ul class="znc-mini-items">';
            $shown = 0;
            foreach ( $cart as $item ) {
                if ( $shown >= 5 ) break;
                $name  = esc_html( isset( $item['product_name'] ) ? $item['product_name'] : 'Product' );
                $qty   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
                $price = isset( $item['price'] ) ? (float) $item['price'] : 0;
                $shop  = esc_html( isset( $item['shop_name'] ) ? $item['shop_name'] : '' );
                $img   = isset( $item['image_url'] ) && $item['image_url'] ? '<img src="' . esc_url( $item['image_url'] ) . '" width="40" height="40" alt="">' : '';

                $html .= '<li>' . $img . '<div><strong>' . $name . '</strong>';
                if ( $shop ) $html .= ' <small>(' . $shop . ')</small>';
                $html .= '<br>' . $qty . ' × ' . $symbol . number_format( $price, 2 ) . '</div></li>';
                $shown++;
            }
            $html .= '</ul>';
            if ( count( $cart ) > 5 ) {
                $html .= '<p class="znc-more">+ ' . ( count( $cart ) - 5 ) . ' more items</p>';
            }
        }

        $cart_url = self::$host ? self::$host->get_cart_url() : '#';
        $html .= '<div class="znc-mini-footer">';
        $html .= '<strong>Total: ' . $symbol . number_format( $total, 2 ) . '</strong>';
        $html .= '<a href="' . esc_url( $cart_url ) . '" class="znc-cart-button">View Cart →</a>';
        $html .= '</div></div>';

        return $html;
    }

    /* ── [znc_shop_count] ─────────────────────────────────────── */
    public static function shop_count( $atts ) {
        if ( ! is_user_logged_in() ) return '0';
        return (string) ZNC_Cart_Snapshot::get_shop_count( get_current_user_id() );
    }

    /* ── [znc_points_balance] ─────────────────────────────────── */
    public static function points_balance( $atts ) {
        if ( ! is_user_logged_in() ) return '<span class="znc-points">0</span>';

        $user_id = get_current_user_id();
        $html    = '<div class="znc-points-balance">';

        // MyCred
        if ( function_exists( 'mycred_get_users_balance' ) && function_exists( 'mycred_get_types' ) ) {
            $types = mycred_get_types();
            foreach ( $types as $slug => $label ) {
                $balance = mycred_get_users_balance( $user_id, $slug );
                $html .= '<div class="znc-point-type"><span class="znc-point-label">' . esc_html( $label ) . '</span>';
                $html .= '<span class="znc-point-value">' . number_format( $balance, 0 ) . '</span></div>';
            }
        }

        // GamiPress
        if ( function_exists( 'gamipress_get_user_points' ) ) {
            $gp_types = get_posts( array( 'post_type' => 'points-type', 'post_status' => 'publish', 'numberposts' => 20 ) );
            foreach ( $gp_types as $pt ) {
                $balance = gamipress_get_user_points( $user_id, $pt->post_name );
                $html .= '<div class="znc-point-type"><span class="znc-point-label">' . esc_html( $pt->post_title ) . '</span>';
                $html .= '<span class="znc-point-value">' . number_format( $balance, 0 ) . '</span></div>';
            }
        }

        $html .= '</div>';
        return $html;
    }

    /* ── [znc_enrolled_shops] ─────────────────────────────────── */
    public static function enrolled_shops( $atts ) {
        $atts = shortcode_atts( array( 'layout' => 'grid' ), $atts );
        $sites = ZNC_Checkout_Host::get_all_sites_for_admin();
        $enrolled = array_filter( $sites, function( $s ) { return ! empty( $s['is_enrolled'] ); } );

        if ( empty( $enrolled ) ) return '<p>No shops enrolled yet.</p>';

        $cls  = $atts['layout'] === 'list' ? 'znc-shops-list' : 'znc-shops-grid';
        $html = '<div class="' . $cls . '">';
        foreach ( $enrolled as $site ) {
            $html .= '<div class="znc-shop-card">';
            $html .= '<h4><a href="' . esc_url( $site['siteurl'] ) . '">' . esc_html( $site['blogname'] ) . '</a></h4>';
            $html .= '<span class="znc-product-count">' . (int) $site['product_count'] . ' products</span>';
            if ( $site['has_wc'] )        $html .= ' <span class="znc-badge znc-wc">WC</span>';
            if ( $site['has_mycred'] )    $html .= ' <span class="znc-badge znc-mc">MyCred</span>';
            if ( $site['has_gamipress'] ) $html .= ' <span class="znc-badge znc-gp">GamiPress</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /* ── [znc_cart_summary] ───────────────────────────────────── */
    public static function cart_summary( $atts ) {
        if ( ! is_user_logged_in() ) return '';

        $user_id = get_current_user_id();
        $grouped = ZNC_Cart_Snapshot::get_grouped( $user_id );
        $count   = ZNC_Cart_Snapshot::get_count( $user_id );
        $total   = ZNC_Cart_Snapshot::get_total( $user_id );
        $symbol  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

        $html = '<div class="znc-cart-summary">';
        $html .= '<h3>🛒 Cart Summary</h3>';
        $html .= '<p>' . $count . ' items from ' . count( $grouped ) . ' shops</p>';

        foreach ( $grouped as $blog_id => $group ) {
            $html .= '<div class="znc-summary-shop">';
            $html .= '<strong>' . esc_html( $group['shop_name'] ) . '</strong>';
            $html .= '<span class="znc-summary-count">' . count( $group['items'] ) . ' items</span>';
            $shop_total = 0;
            foreach ( $group['items'] as $item ) {
                $shop_total += ( isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 ) * ( isset( $item['price'] ) ? (float) $item['price'] : 0 );
            }
            $html .= '<span class="znc-summary-total">' . $symbol . number_format( $shop_total, 2 ) . '</span>';
            $html .= '</div>';
        }

        $html .= '<div class="znc-summary-grand-total"><strong>Total: ' . $symbol . number_format( $total, 2 ) . '</strong></div>';
        $cart_url = self::$host ? self::$host->get_cart_url() : '#';
        $html .= '<a href="' . esc_url( $cart_url ) . '" class="znc-cart-button">View Full Cart →</a>';
        $html .= '</div>';
        return $html;
    }
}
