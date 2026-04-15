<?php
/**
 * Widgets — v1.5.0 REWRITE
 * All widgets now read from wp_usermeta via ZNC_Cart_Snapshot.
 * Zero switch_to_blog(), zero custom table queries.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Widgets {

    private static $host = null;

    public static function init( ZNC_Checkout_Host $host ) {
        self::$host = $host;
        add_action( 'widgets_init', array( __CLASS__, 'register' ) );
    }

    public static function register() {
        register_widget( 'ZNC_Widget_Cart_Badge' );
        register_widget( 'ZNC_Widget_Cart_Summary' );
        register_widget( 'ZNC_Widget_Shop_List' );
        register_widget( 'ZNC_Widget_Points_Balance' );
    }

    public static function get_host() { return self::$host; }
}

/* ── Cart Badge Widget ────────────────────────────────────────── */
class ZNC_Widget_Cart_Badge extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_cart_badge', 'ZNC: Cart Badge', array(
            'description' => 'Shows global cart item count with link to cart page.',
        ) );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;

        $count = ZNC_Cart_Snapshot::get_count( get_current_user_id() );
        $host  = ZNC_Widgets::get_host();
        $url   = $host ? $host->get_cart_url() : '#';
        $title = ! empty( $instance['title'] ) ? $instance['title'] : 'Net Cart';

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        echo '<a href="' . esc_url( $url ) . '" class="znc-widget-cart-link">';
        echo '🛒 <span class="znc-global-cart-count">' . $count . '</span> items';
        echo '</a>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : 'Net Cart';
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name( 'title' ) . '" value="' . esc_attr( $title ) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ) );
    }
}

/* ── Cart Summary Widget ──────────────────────────────────────── */
class ZNC_Widget_Cart_Summary extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_cart_summary', 'ZNC: Cart Summary', array(
            'description' => 'Mini cart preview with thumbnails and shop badges.',
        ) );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;

        $user_id = get_current_user_id();
        $cart    = ZNC_Cart_Snapshot::get_cart( $user_id );
        $count   = ZNC_Cart_Snapshot::get_count( $user_id );
        $total   = ZNC_Cart_Snapshot::get_total( $user_id );
        $host    = ZNC_Widgets::get_host();
        $url     = $host ? $host->get_cart_url() : '#';
        $title   = ! empty( $instance['title'] ) ? $instance['title'] : 'Cart Summary';
        $symbol  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$';

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

        if ( empty( $cart ) ) {
            echo '<p class="znc-empty">Your cart is empty.</p>';
        } else {
            echo '<ul class="znc-widget-items">';
            $shown = 0;
            foreach ( $cart as $item ) {
                if ( $shown >= 3 ) break;
                $name  = esc_html( isset( $item['product_name'] ) ? $item['product_name'] : 'Product' );
                $qty   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
                $price = isset( $item['price'] ) ? (float) $item['price'] : 0;
                $shop  = esc_html( isset( $item['shop_name'] ) ? $item['shop_name'] : '' );
                $img   = isset( $item['image_url'] ) && $item['image_url']
                    ? '<img src="' . esc_url( $item['image_url'] ) . '" width="32" height="32" alt="" style="border-radius:4px;margin-right:8px;vertical-align:middle">'
                    : '';
                echo '<li style="margin-bottom:6px;font-size:13px">' . $img . '<strong>' . $name . '</strong>';
                if ( $shop ) echo ' <small style="color:#888">(' . $shop . ')</small>';
                echo '<br><span style="color:#666">' . $qty . ' × ' . $symbol . number_format( $price, 2 ) . '</span></li>';
                $shown++;
            }
            echo '</ul>';
            if ( count( $cart ) > 3 ) {
                echo '<p style="font-size:12px;color:#888">+ ' . ( count( $cart ) - 3 ) . ' more items</p>';
            }
            echo '<p style="font-weight:700;margin:8px 0">Total: ' . $symbol . number_format( $total, 2 ) . '</p>';
        }
        echo '<a href="' . esc_url( $url ) . '" class="button" style="display:block;text-align:center;background:#7c3aed;color:#fff;border:0;border-radius:6px;padding:8px">View Cart (' . $count . ')</a>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : 'Cart Summary';
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name( 'title' ) . '" value="' . esc_attr( $title ) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ) );
    }
}

/* ── Shop List Widget ─────────────────────────────────────────── */
class ZNC_Widget_Shop_List extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_shop_list', 'ZNC: Enrolled Shops', array(
            'description' => 'Lists all enrolled shops with product counts.',
        ) );
    }

    public function widget( $args, $instance ) {
        $sites  = ZNC_Checkout_Host::get_all_sites_for_admin();
        $title  = ! empty( $instance['title'] ) ? $instance['title'] : 'Our Shops';

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        echo '<ul class="znc-widget-shops">';
        foreach ( $sites as $site ) {
            if ( empty( $site['is_enrolled'] ) ) continue;
            echo '<li style="margin-bottom:6px">';
            echo '<a href="' . esc_url( $site['siteurl'] ) . '">' . esc_html( $site['blogname'] ) . '</a>';
            echo ' <small style="color:#888">(' . (int) $site['product_count'] . ' products)</small>';
            echo '</li>';
        }
        echo '</ul>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : 'Our Shops';
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name( 'title' ) . '" value="' . esc_attr( $title ) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ) );
    }
}

/* ── Points Balance Widget ────────────────────────────────────── */
class ZNC_Widget_Points_Balance extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_points_balance', 'ZNC: Points Balance', array(
            'description' => 'Shows MyCred and GamiPress point balances.',
        ) );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;

        $user_id = get_current_user_id();
        $title   = ! empty( $instance['title'] ) ? $instance['title'] : 'My Points';
        $found   = false;

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

        // MyCred
        if ( function_exists( 'mycred_get_users_balance' ) && function_exists( 'mycred_get_types' ) ) {
            $types = mycred_get_types();
            foreach ( $types as $slug => $label ) {
                $balance = mycred_get_users_balance( $user_id, $slug );
                echo '<div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>' . esc_html( $label ) . '</span><strong>' . number_format( $balance, 0 ) . '</strong></div>';
                $found = true;
            }
        }

        // GamiPress
        if ( function_exists( 'gamipress_get_user_points' ) ) {
            $gp_types = get_posts( array( 'post_type' => 'points-type', 'post_status' => 'publish', 'numberposts' => 20 ) );
            foreach ( $gp_types as $pt ) {
                $balance = gamipress_get_user_points( $user_id, $pt->post_name );
                echo '<div style="display:flex;justify-content:space-between;margin-bottom:4px"><span>' . esc_html( $pt->post_title ) . '</span><strong>' . number_format( $balance, 0 ) . '</strong></div>';
                $found = true;
            }
        }

        if ( ! $found ) {
            echo '<p style="font-size:13px;color:#888">No point systems detected.</p>';
        }

        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = isset( $instance['title'] ) ? $instance['title'] : 'My Points';
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name( 'title' ) . '" value="' . esc_attr( $title ) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ) );
    }
}
