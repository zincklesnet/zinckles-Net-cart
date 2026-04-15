<?php
/**
 * Widgets — 5 widgets for Net Cart display across the network.
 *
 * @package ZincklesNetCart
 * @since   1.6.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Widgets {
    public static function register() {
        add_action( 'widgets_init', function() {
            register_widget( 'ZNC_Widget_Cart_Badge' );
            register_widget( 'ZNC_Widget_Cart_Summary' );
            register_widget( 'ZNC_Widget_Shop_List' );
            register_widget( 'ZNC_Widget_Points_Balance' );
            register_widget( 'ZNC_Widget_Recent_Items' );
        } );
    }
}

/* ── Widget 1: Cart Badge — floating icon with live count ── */
class ZNC_Widget_Cart_Badge extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_cart_badge', 'Net Cart — Cart Badge', array(
            'description' => 'Displays global cart count with link to cart page.',
        ) );
    }

    public function widget( $args, $instance ) {
        $gc    = new ZNC_Global_Cart();
        $host  = new ZNC_Checkout_Host();
        $count = $gc->get_item_count();
        $title = ! empty( $instance['title'] ) ? $instance['title'] : '';

        echo $args['before_widget'];
        if ( $title ) echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        echo '<a href="' . esc_url( $host->get_cart_url() ) . '" class="znc-widget-cart-badge">';
        echo '<span class="znc-widget-cart-icon">&#x1F6D2;</span>';
        echo '<span class="znc-cart-count znc-widget-count">' . esc_html( $count ) . '</span>';
        echo '</a>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? '';
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ?? '' ) );
    }
}

/* ── Widget 2: Cart Summary — mini cart with thumbnails ── */
class ZNC_Widget_Cart_Summary extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_cart_summary', 'Net Cart — Cart Summary', array(
            'description' => 'Mini cart preview with product thumbnails and shop badges.',
        ) );
    }

    public function widget( $args, $instance ) {
        $gc       = new ZNC_Global_Cart();
        $host     = new ZNC_Checkout_Host();
        $count    = $gc->get_item_count();
        $max_show = absint( $instance['max_items'] ?? 5 );
        $title    = ! empty( $instance['title'] ) ? $instance['title'] : 'Net Cart';

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

        if ( ! is_user_logged_in() || $count === 0 ) {
            echo '<p class="znc-widget-empty">Your global cart is empty.</p>';
            echo $args['after_widget'];
            return;
        }

        $cart = $gc->get_cart();
        $shown = 0;

        echo '<ul class="znc-widget-cart-items">';
        foreach ( $cart as $key => $item ) {
            if ( $shown >= $max_show ) break;
            $name = 'Product #' . $item['product_id'];

            // Try to get product name from host or subsite
            $blog_details = get_blog_details( $item['blog_id'] );
            $shop_name    = $blog_details ? $blog_details->blogname : 'Shop';

            echo '<li class="znc-widget-cart-item">';
            echo '<span class="znc-widget-item-name">' . esc_html( $name ) . '</span>';
            echo '<span class="znc-widget-item-meta">' . esc_html( $shop_name ) . ' &times;' . esc_html( $item['quantity'] ) . '</span>';
            echo '</li>';
            $shown++;
        }
        echo '</ul>';

        if ( count( $cart ) > $max_show ) {
            echo '<p class="znc-widget-more">+' . ( count( $cart ) - $max_show ) . ' more items</p>';
        }

        echo '<div class="znc-widget-footer">';
        echo '<a href="' . esc_url( $host->get_cart_url() ) . '" class="znc-btn znc-btn-sm">View Cart (' . $count . ')</a>';
        echo '</div>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? 'Net Cart';
        $max   = $instance['max_items'] ?? 5;
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
        echo '<p><label>Max items: <input type="number" class="tiny-text" name="' . $this->get_field_name('max_items') . '" value="' . esc_attr($max) . '" min="1" max="20"></label></p>';
    }

    public function update( $new, $old ) {
        return array(
            'title'     => sanitize_text_field( $new['title'] ?? '' ),
            'max_items' => absint( $new['max_items'] ?? 5 ),
        );
    }
}

/* ── Widget 3: Shop List — enrolled shops with links ── */
class ZNC_Widget_Shop_List extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_shop_list', 'Net Cart — Shop List', array(
            'description' => 'Lists all enrolled shops with links.',
        ) );
    }

    public function widget( $args, $instance ) {
        $settings = get_site_option( 'znc_network_settings', array() );
        $enrolled = (array) ( $settings['enrolled_sites'] ?? array() );
        $title    = ! empty( $instance['title'] ) ? $instance['title'] : 'Our Shops';

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

        if ( empty( $enrolled ) ) {
            echo '<p>No shops enrolled yet.</p>';
            echo $args['after_widget'];
            return;
        }

        echo '<ul class="znc-widget-shop-list">';
        foreach ( $enrolled as $blog_id ) {
            $details = get_blog_details( absint( $blog_id ) );
            if ( ! $details ) continue;
            echo '<li><a href="' . esc_url( $details->siteurl ) . '">' . esc_html( $details->blogname ) . '</a></li>';
        }
        echo '</ul>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? 'Our Shops';
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ?? '' ) );
    }
}

/* ── Widget 4: Points Balance — MyCred + GamiPress ── */
class ZNC_Widget_Points_Balance extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_points_balance', 'Net Cart — Points Balance', array(
            'description' => 'Shows MyCred and GamiPress point balances.',
        ) );
    }

    public function widget( $args, $instance ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) return;

        $title = ! empty( $instance['title'] ) ? $instance['title'] : 'My Points';
        $has_points = false;

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
        echo '<div class="znc-widget-points">';

        // MyCred
        if ( function_exists( 'mycred_get_users_balance' ) ) {
            $types = function_exists( 'mycred_get_types' ) ? mycred_get_types() : array( 'mycred_default' => 'Points' );
            foreach ( $types as $slug => $label ) {
                $balance = mycred_get_users_balance( $user_id, $slug );
                echo '<div class="znc-widget-point-row">';
                echo '<span class="znc-point-label">' . esc_html( $label ) . '</span>';
                echo '<span class="znc-point-value">' . esc_html( number_format( $balance, 0 ) ) . '</span>';
                echo '</div>';
                $has_points = true;
            }
        }

        // GamiPress
        if ( function_exists( 'gamipress_get_user_points' ) ) {
            $types = get_posts( array( 'post_type' => 'points-type', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
            foreach ( $types as $type ) {
                $balance = gamipress_get_user_points( $user_id, $type->post_name );
                echo '<div class="znc-widget-point-row">';
                echo '<span class="znc-point-label">' . esc_html( $type->post_title ) . '</span>';
                echo '<span class="znc-point-value">' . esc_html( number_format( $balance, 0 ) ) . '</span>';
                echo '</div>';
                $has_points = true;
            }
        }

        if ( ! $has_points ) {
            echo '<p class="znc-widget-empty">No point systems active.</p>';
        }

        echo '</div>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? 'My Points';
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
    }

    public function update( $new, $old ) {
        return array( 'title' => sanitize_text_field( $new['title'] ?? '' ) );
    }
}

/* ── Widget 5: Recent Items — last items added to global cart ── */
class ZNC_Widget_Recent_Items extends WP_Widget {
    public function __construct() {
        parent::__construct( 'znc_recent_items', 'Net Cart — Recent Items', array(
            'description' => 'Shows recently added items in global cart.',
        ) );
    }

    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) return;

        $gc    = new ZNC_Global_Cart();
        $cart  = $gc->get_cart();
        $host  = new ZNC_Checkout_Host();
        $title = ! empty( $instance['title'] ) ? $instance['title'] : 'Recently Added';
        $max   = absint( $instance['max_items'] ?? 3 );

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

        if ( empty( $cart ) ) {
            echo '<p class="znc-widget-empty">No items in cart.</p>';
            echo $args['after_widget'];
            return;
        }

        // Sort by added_at descending
        uasort( $cart, function( $a, $b ) {
            return ( $b['added_at'] ?? 0 ) - ( $a['added_at'] ?? 0 );
        } );

        $shown = 0;
        echo '<ul class="znc-widget-recent-items">';
        foreach ( $cart as $item ) {
            if ( $shown >= $max ) break;
            $blog = get_blog_details( $item['blog_id'] );
            $shop = $blog ? $blog->blogname : 'Shop';
            $ago  = human_time_diff( $item['added_at'] ?? time() );

            echo '<li class="znc-widget-recent-item">';
            echo '<span class="znc-recent-product">Product #' . esc_html( $item['product_id'] ) . '</span>';
            echo '<span class="znc-recent-meta">' . esc_html( $shop ) . ' &middot; ' . esc_html( $ago ) . ' ago</span>';
            echo '</li>';
            $shown++;
        }
        echo '</ul>';

        echo '<a href="' . esc_url( $host->get_cart_url() ) . '" class="znc-widget-link">View Full Cart &rarr;</a>';
        echo $args['after_widget'];
    }

    public function form( $instance ) {
        $title = $instance['title'] ?? 'Recently Added';
        $max   = $instance['max_items'] ?? 3;
        echo '<p><label>Title: <input class="widefat" name="' . $this->get_field_name('title') . '" value="' . esc_attr($title) . '"></label></p>';
        echo '<p><label>Max items: <input type="number" class="tiny-text" name="' . $this->get_field_name('max_items') . '" value="' . esc_attr($max) . '" min="1" max="10"></label></p>';
    }

    public function update( $new, $old ) {
        return array(
            'title'     => sanitize_text_field( $new['title'] ?? '' ),
            'max_items' => absint( $new['max_items'] ?? 3 ),
        );
    }
}
