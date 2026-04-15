<?php
/**
 * Tutor LMS Engine — Integrates Tutor LMS courses with Net Cart.
 *
 * Tutor LMS links courses to WooCommerce products via the
 * _tutor_course_product_id post meta. When a WC product in the
 * global cart is actually a Tutor course, this engine enriches
 * the cart item with course metadata (title, thumbnail, instructor).
 *
 * Also provides:
 * - Auto-enrollment after successful checkout
 * - Course detection for admin diagnostics
 * - [znc_tutor_courses] shortcode for enrolled courses
 *
 * @package ZincklesNetCart
 * @since   1.6.0
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Tutor_Engine {

    /** @var ZNC_Global_Cart */
    private $global_cart;

    public function __construct( ZNC_Global_Cart $global_cart ) {
        $this->global_cart = $global_cart;
    }

    public function init() {
        // Enrich cart items with Tutor course data
        add_filter( 'znc_enrich_cart_item', array( $this, 'enrich_item' ), 10, 3 );

        // Auto-enroll student after Net Cart checkout completes
        add_action( 'znc_checkout_order_complete', array( $this, 'auto_enroll_student' ), 10, 3 );

        // Register Tutor-specific shortcode
        add_shortcode( 'znc_tutor_courses', array( $this, 'sc_enrolled_courses' ) );

        // AJAX: detect Tutor LMS across network
        add_action( 'wp_ajax_znc_detect_tutor', array( $this, 'ajax_detect_tutor' ) );
    }

    /**
     * Check if a WC product is linked to a Tutor LMS course.
     *
     * @param int $product_id WooCommerce product ID.
     * @return int|false Course post ID or false.
     */
    public function get_course_for_product( $product_id ) {
        global $wpdb;

        // Tutor stores the product ID on the course post
        $course_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}
             WHERE meta_key = '_tutor_course_product_id'
             AND meta_value = %s
             LIMIT 1",
            $product_id
        ) );

        return $course_id ? (int) $course_id : false;
    }

    /**
     * Enrich a cart item with Tutor course metadata.
     *
     * Hooked to znc_enrich_cart_item filter in Cart Renderer.
     */
    public function enrich_item( $enriched_item, $raw_item, $blog_id ) {
        if ( ! function_exists( 'tutor_utils' ) ) return $enriched_item;

        $course_id = $this->get_course_for_product( $raw_item['product_id'] );
        if ( ! $course_id ) return $enriched_item;

        $course = get_post( $course_id );
        if ( ! $course || $course->post_type !== 'courses' ) return $enriched_item;

        // Override product name with course title
        $enriched_item['name']      = $course->post_title;
        $enriched_item['is_course'] = true;
        $enriched_item['course_id'] = $course_id;

        // Course thumbnail
        $thumb = get_post_thumbnail_id( $course_id );
        if ( $thumb ) {
            $enriched_item['image'] = wp_get_attachment_image_url( $thumb, 'woocommerce_thumbnail' );
        }

        // Instructor name
        $instructor = get_userdata( $course->post_author );
        if ( $instructor ) {
            $enriched_item['instructor'] = $instructor->display_name;
        }

        // Course duration if available
        $duration = get_post_meta( $course_id, '_course_duration', true );
        if ( $duration ) {
            $enriched_item['duration'] = $duration;
        }

        // Course level
        $level = get_post_meta( $course_id, '_tutor_course_level', true );
        if ( $level ) {
            $enriched_item['level'] = ucfirst( $level );
        }

        return $enriched_item;
    }

    /**
     * Auto-enroll student in Tutor LMS course after checkout.
     */
    public function auto_enroll_student( $user_id, $blog_id, $order_id ) {
        if ( ! function_exists( 'tutor_utils' ) ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $course_id  = $this->get_course_for_product( $product_id );

            if ( $course_id && function_exists( 'tutor_utils' ) ) {
                tutor_utils()->do_enroll( $course_id, $order_id, $user_id );
            }
        }
    }

    /**
     * Detect Tutor LMS across all enrolled subsites.
     */
    public function ajax_detect_tutor() {
        check_ajax_referer( 'znc_network_admin', 'nonce' );
        if ( ! current_user_can( 'manage_network_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        global $wpdb;
        $settings  = get_site_option( 'znc_network_settings', array() );
        $enrolled  = (array) ( $settings['enrolled_sites'] ?? array() );
        $host_id   = absint( $settings['checkout_host_id'] ?? get_main_site_id() );
        $all_sites = array_unique( array_merge( array( $host_id ), $enrolled ) );

        $tutor_sites = array();

        foreach ( $all_sites as $blog_id ) {
            $blog_id = absint( $blog_id );
            $details = get_blog_details( $blog_id );
            if ( ! $details ) continue;

            // Check if Tutor LMS is active on this site via DB
            $prefix  = $wpdb->get_blog_prefix( $blog_id );
            $plugins = $wpdb->get_var( "SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins' LIMIT 1" );

            if ( $plugins && ( strpos( $plugins, 'tutor' ) !== false || strpos( $plugins, 'tutor-pro' ) !== false ) ) {
                // Count courses
                $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}posts WHERE post_type = 'courses' AND post_status = 'publish'" );

                $tutor_sites[ $blog_id ] = array(
                    'name'    => $details->blogname,
                    'courses' => (int) $count,
                );
            }
        }

        $settings['tutor_sites'] = array_keys( $tutor_sites );
        update_site_option( 'znc_network_settings', $settings );

        wp_send_json_success( array( 'tutor' => $tutor_sites ) );
    }

    /**
     * Shortcode: [znc_tutor_courses] — Shows user's enrolled Tutor courses.
     */
    public function sc_enrolled_courses( $atts ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return '<p>' . esc_html__( 'Please log in to view your courses.', 'zinckles-net-cart' ) . '</p>';
        }

        if ( ! function_exists( 'tutor_utils' ) ) return '';

        $enrolled = tutor_utils()->get_enrolled_courses_by_user( $user_id );
        if ( ! $enrolled || ! $enrolled->have_posts() ) {
            return '<p>' . esc_html__( 'No enrolled courses yet.', 'zinckles-net-cart' ) . '</p>';
        }

        ob_start();
        echo '<div class="znc-tutor-courses-grid">';
        while ( $enrolled->have_posts() ) {
            $enrolled->the_post();
            $course_id = get_the_ID();
            echo '<div class="znc-tutor-course-card">';
            if ( has_post_thumbnail() ) {
                echo '<div class="znc-course-thumb">' . get_the_post_thumbnail( $course_id, 'medium' ) . '</div>';
            }
            echo '<div class="znc-course-info">';
            echo '<h4 class="znc-course-title"><a href="' . esc_url( get_the_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h4>';
            $completion = tutor_utils()->get_course_completed_percent( $course_id, $user_id );
            echo '<div class="znc-course-progress">';
            echo '<div class="znc-progress-bar"><div class="znc-progress-fill" style="width:' . esc_attr( $completion ) . '%"></div></div>';
            echo '<span class="znc-progress-text">' . esc_html( $completion ) . '% ' . esc_html__( 'complete', 'zinckles-net-cart' ) . '</span>';
            echo '</div>';
            echo '</div></div>';
        }
        wp_reset_postdata();
        echo '</div>';
        return ob_get_clean();
    }
}
