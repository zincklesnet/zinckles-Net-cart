<?php
/**
 * Shortcodes — 9 shortcodes for global cart display.
 *
 * @package ZincklesNetCart
 * @since   1.5.1
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Shortcodes {

    private $global_cart;
    private $renderer;
    private $checkout_host;
    private $checkout_handler;

    public function __construct( ZNC_Global_Cart $global_cart, ZNC_Cart_Renderer $renderer, ZNC_Checkout_Host $checkout_host, ZNC_Checkout_Handler $checkout_handler = null ) {
        $this->global_cart      = $global_cart;
        $this->renderer         = $renderer;
        $this->checkout_host    = $checkout_host;
        $this->checkout_handler = $checkout_handler;
    }

    public function init() {
        add_shortcode( 'znc_global_cart',    array( $this, 'sc_global_cart' ) );
        add_shortcode( 'znc_checkout',       array( $this, 'sc_checkout' ) );
        add_shortcode( 'znc_cart_count',     array( $this, 'sc_cart_count' ) );
        add_shortcode( 'znc_cart_total',     array( $this, 'sc_cart_total' ) );
        add_shortcode( 'znc_cart_button',    array( $this, 'sc_cart_button' ) );
        add_shortcode( 'znc_mini_cart',      array( $this, 'sc_mini_cart' ) );
        add_shortcode( 'znc_points_balance', array( $this, 'sc_points_balance' ) );
        add_shortcode( 'znc_shop_list',      array( $this, 'sc_shop_list' ) );
        add_shortcode( 'znc_order_history',  array( $this, 'sc_order_history' ) );
    }

    /** [znc_global_cart] — Full cart page */
    public function sc_global_cart( $atts ) {
        return $this->renderer->render_cart();
    }

    /** [znc_checkout] — Full checkout page */
    public function sc_checkout( $atts ) {
        if ( $this->checkout_handler ) {
            return $this->checkout_handler->render_checkout();
        }
        return '<p>' . esc_html__( 'Checkout is not available.', 'zinckles-net-cart' ) . '</p>';
    }

    /** [znc_cart_count] — Inline item count badge */
    public function sc_cart_count( $atts ) {
        $count = $this->global_cart->get_item_count();
        return '<span class="znc-cart-count znc-sc-count">' . esc_html( $count ) . '</span>';
    }

    /** [znc_cart_total] — Inline cart total */
    public function sc_cart_total( $atts ) {
        $total = $this->renderer->get_cart_total();
        return '<span class="znc-cart-total znc-sc-total">$' . number_format( $total, 2 ) . '</span>';
    }

    /** [znc_cart_button] — Styled button linking to global cart */
    public function sc_cart_button( $atts ) {
        $atts = shortcode_atts( array(
            'text'  => __( 'View Global Cart', 'zinckles-net-cart' ),
            'class' => '',
        ), $atts );

        $count    = $this->global_cart->get_item_count();
        $cart_url = $this->checkout_host->get_cart_url();

        return sprintf(
            '<a href="%s" class="znc-btn znc-btn-cart %s">%s <span class="znc-cart-count">%d</span></a>',
            esc_url( $cart_url ),
            esc_attr( $atts['class'] ),
            esc_html( $atts['text'] ),
            $count
        );
    }

    /** [znc_mini_cart] — Compact cart summary */
    public function sc_mini_cart( $atts ) {
        $enriched = $this->renderer->get_enriched_cart();
        $count    = $this->global_cart->get_item_count();
        $total    = $this->renderer->get_cart_total();
        $cart_url = $this->checkout_host->get_cart_url();

        ob_start();
        echo '<div class="znc-mini-cart">';
        echo '<div class="znc-mini-header">';
        echo '<span class="znc-mini-icon">&#x1F6D2;</span>';
        echo '<span class="znc-mini-title">' . esc_html__( 'Net Cart', 'zinckles-net-cart' ) . '</span>';
        echo '<span class="znc-mini-count">' . esc_html( $count ) . ' ' . esc_html__( 'items', 'zinckles-net-cart' ) . '</span>';
        echo '</div>';

        if ( ! empty( $enriched ) ) {
            echo '<ul class="znc-mini-items">';
            foreach ( $enriched as $shop ) {
                foreach ( $shop['items'] as $item ) {
                    echo '<li>';
                    echo '<span class="znc-mini-item-name">' . esc_html( $item['name'] ) . '</span>';
                    echo '<span class="znc-mini-item-qty">&times;' . esc_html( $item['quantity'] ) . '</span>';
                    echo '</li>';
                }
            }
            echo '</ul>';
        }

        echo '<div class="znc-mini-footer">';
        echo '<span class="znc-mini-total">$' . number_format( $total, 2 ) . '</span>';
        echo '<a href="' . esc_url( $cart_url ) . '" class="znc-btn znc-btn-sm">' . esc_html__( 'View Cart', 'zinckles-net-cart' ) . '</a>';
        echo '</div></div>';
        return ob_get_clean();
    }

    /** [znc_points_balance] — MyCred + GamiPress balances */
    public function sc_points_balance( $atts ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return '';

        ob_start();
        echo '<div class="znc-points-balance">';

        // MyCred
        if ( function_exists( 'mycred_get_users_balance' ) ) {
            $types = function_exists( 'mycred_get_types' ) ? mycred_get_types() : array( 'mycred_default' => 'Points' );
            foreach ( $types as $slug => $label ) {
                $balance = mycred_get_users_balance( $user_id, $slug );
                echo '<div class="znc-balance-item znc-balance-mycred">';
                echo '<span class="znc-balance-label">' . esc_html( $label ) . '</span>';
                echo '<span class="znc-balance-value">' . esc_html( number_format( $balance, 0 ) ) . '</span>';
                echo '</div>';
            }
        }

        // GamiPress
        if ( function_exists( 'gamipress_get_user_points' ) ) {
            $types = get_posts( array( 'post_type' => 'points-type', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
            foreach ( $types as $type ) {
                $balance = gamipress_get_user_points( $user_id, $type->post_name );
                echo '<div class="znc-balance-item znc-balance-gamipress">';
                echo '<span class="znc-balance-label">' . esc_html( $type->post_title ) . '</span>';
                echo '<span class="znc-balance-value">' . esc_html( number_format( $balance, 0 ) ) . '</span>';
                echo '</div>';
            }
        }

        echo '</div>';
        return ob_get_clean();
    }

    /** [znc_shop_list] — Grid of enrolled shops */
    public function sc_shop_list( $atts ) {
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $host_id  = $this->checkout_host->get_host_id();

        if ( ! in_array( $host_id, $enrolled, true ) ) {
            array_unshift( $enrolled, $host_id );
        }

        ob_start();
        echo '<div class="znc-shop-grid">';
        foreach ( $enrolled as $blog_id ) {
            $details = get_blog_details( absint( $blog_id ) );
            if ( ! $details ) continue;

            echo '<div class="znc-shop-card">';
            echo '<h4 class="znc-shop-card-name">' . esc_html( $details->blogname ) . '</h4>';
            echo '<a href="' . esc_url( $details->siteurl ) . '" class="znc-btn znc-btn-sm">';
            echo esc_html__( 'Visit Shop', 'zinckles-net-cart' ) . '</a>';
            echo '</div>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    /** [znc_order_history] — User's Net Cart orders */
    public function sc_order_history( $atts ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return '<p>' . esc_html__( 'Please log in to view your orders.', 'zinckles-net-cart' ) . '</p>';
        }

        if ( ! function_exists( 'wc_get_orders' ) ) return '';

        $orders = wc_get_orders( array(
            'customer_id' => $user_id,
            'limit'       => 20,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'meta_key'    => '_znc_child_orders',
        ) );

        if ( empty( $orders ) ) {
            return '<p>' . esc_html__( 'No Net Cart orders found.', 'zinckles-net-cart' ) . '</p>';
        }

        ob_start();
        echo '<table class="znc-orders-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Order', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Date', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Status', 'zinckles-net-cart' ) . '</th>';
        echo '<th>' . esc_html__( 'Total', 'zinckles-net-cart' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $orders as $order ) {
            echo '<tr>';
            echo '<td>#' . esc_html( $order->get_order_number() ) . '</td>';
            echo '<td>' . esc_html( $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) ) . '</td>';
            echo '<td><span class="znc-order-status znc-status-' . esc_attr( $order->get_status() ) . '">' . esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</span></td>';
            echo '<td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        return ob_get_clean();
    }
}
