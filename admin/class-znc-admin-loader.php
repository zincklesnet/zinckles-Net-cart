<?php
/**
 * Admin Loader — bridges settings to core modules via WordPress filters.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Admin_Loader {

    public function init() {
        add_filter( 'znc_cart_snapshot_enabled', array( $this, 'check_enrollment' ) );
        add_filter( 'znc_product_eligible',     array( $this, 'check_product' ), 10, 2 );
        add_filter( 'znc_checkout_config',      array( $this, 'get_checkout_config' ) );
        add_filter( 'znc_shipping_config',      array( $this, 'get_shipping_config' ) );
        add_filter( 'znc_tax_config',           array( $this, 'get_tax_config' ) );
        add_filter( 'znc_shop_display',         array( $this, 'get_shop_display' ) );

        // Auto-enroll new sites if configured.
        add_action( 'wp_initialize_site', array( $this, 'maybe_auto_enroll' ), 100, 1 );
    }

    public function check_enrollment( $enabled = true ) {
        return ZNC_Network_Admin::is_site_enrolled( get_current_blog_id() );
    }

    public function check_product( $eligible, $product_id ) {
        $subsite = get_option( 'znc_subsite_settings', array() );
        $mode    = $subsite['product_mode'] ?? 'all';

        if ( $mode === 'all' ) {
            return true;
        }

        $included = (array) ( $subsite['included_products'] ?? array() );
        $excluded = (array) ( $subsite['excluded_products'] ?? array() );

        if ( $mode === 'include' ) {
            return in_array( $product_id, $included, true );
        }

        if ( $mode === 'exclude' ) {
            return ! in_array( $product_id, $excluded, true );
        }

        return $eligible;
    }

    public function get_checkout_config( $config = array() ) {
        $settings = ZNC_Network_Admin::get_settings();
        return array_merge( $config, array(
            'validation'    => $settings['checkout_validation'],
            'retry_max'     => $settings['inventory_retry_max'],
            'retry_delay'   => $settings['inventory_retry_delay'],
            'mycred_enabled' => $settings['mycred_enabled'],
        ) );
    }

    public function get_shipping_config( $config = array() ) {
        $subsite = get_option( 'znc_subsite_settings', array() );
        return array(
            'mode'       => $subsite['shipping_mode'] ?? 'inherit',
            'flat_rate'  => floatval( $subsite['shipping_flat_rate'] ?? 0 ),
            'free_above' => floatval( $subsite['shipping_free_threshold'] ?? 0 ),
        );
    }

    public function get_tax_config( $config = array() ) {
        $subsite = get_option( 'znc_subsite_settings', array() );
        return array(
            'mode'  => $subsite['tax_mode'] ?? 'inherit',
            'rate'  => floatval( $subsite['tax_rate'] ?? 0 ),
            'label' => $subsite['tax_label'] ?? 'Tax',
        );
    }

    public function get_shop_display( $display = array() ) {
        $subsite = get_option( 'znc_subsite_settings', array() );
        return array(
            'name'  => $subsite['display_name'] ?? get_bloginfo( 'name' ),
            'color' => $subsite['badge_color'] ?? '#7c3aed',
            'icon'  => $subsite['badge_icon'] ?? '',
        );
    }

    public function maybe_auto_enroll( $site ) {
        $settings = ZNC_Network_Admin::get_settings();
        if ( ! empty( $settings['auto_enroll_new'] ) ) {
            ZNC_Network_Admin::set_site_enrollment( $site->blog_id, true );
        }
    }
}
