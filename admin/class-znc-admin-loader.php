<?php
defined( 'ABSPATH' ) || exit;

/**
 * Bridges admin settings to core plugin modules via WordPress filters.
 */
class ZNC_Admin_Loader {

    public function init() {
        add_filter( 'znc_cart_snapshot_enabled', array( $this, 'filter_snapshot_enabled' ), 10, 1 );
        add_filter( 'znc_product_eligible',     array( $this, 'filter_product_eligible' ), 10, 2 );
        add_filter( 'znc_checkout_config',       array( $this, 'filter_checkout_config' ) );
        add_filter( 'znc_currency_rates',        array( $this, 'filter_currency_rates' ) );
        add_filter( 'znc_mycred_config',         array( $this, 'filter_mycred_config' ) );
        add_filter( 'znc_shipping_config',       array( $this, 'filter_shipping_config' ), 10, 2 );
        add_filter( 'znc_tax_config',            array( $this, 'filter_tax_config' ), 10, 2 );
        add_filter( 'znc_shop_display',          array( $this, 'filter_shop_display' ), 10, 2 );
    }

    /**
     * Check if cart snapshot is enabled for the current subsite.
     */
    public function filter_snapshot_enabled( $enabled ) {
        if ( is_main_site() ) return false;

        $site_id = get_current_blog_id();
        global $wpdb;

        switch_to_blog( get_main_site_id() );
        $enrolled = $wpdb->get_var( $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}znc_enrolled_sites WHERE site_id = %d",
            $site_id
        ) );
        restore_current_blog();

        return 'active' === $enrolled;
    }

    /**
     * Apply subsite product eligibility rules.
     */
    public function filter_product_eligible( $eligible, $product ) {
        if ( ! $eligible ) return false;

        $settings = get_option( 'znc_subsite_settings', array() );

        // Low stock threshold
        $threshold = intval( $settings['low_stock_threshold'] ?? 0 );
        if ( $threshold > 0 && $product->managing_stock() ) {
            if ( $product->get_stock_quantity() <= $threshold ) {
                return false;
            }
        }

        // ZCred product exclusions (applied at cart level, not eligibility)
        return true;
    }

    /**
     * Provide checkout configuration from admin settings.
     */
    public function filter_checkout_config( $config = array() ) {
        $main = get_option( 'znc_main_settings', array() );
        return array_merge( $config, array(
            'pre_checkout_refresh'  => ! empty( $main['pre_checkout_refresh'] ),
            'price_change_action'   => $main['price_change_action'] ?? 'block',
            'stock_change_action'   => $main['stock_change_action'] ?? 'block',
            'coupon_support'        => ! empty( $main['coupon_support'] ),
            'coupon_scope'          => $main['coupon_scope'] ?? 'per_shop',
            'shipping_aggregation'  => $main['shipping_aggregation'] ?? 'per_shop',
            'split_pay_mode'        => ! empty( $main['split_pay_mode'] ),
            'min_order_amount'      => floatval( $main['min_order_amount'] ?? 0 ),
            'max_order_amount'      => floatval( $main['max_order_amount'] ?? 0 ),
        ) );
    }

    /**
     * Provide exchange rates from admin settings.
     */
    public function filter_currency_rates( $rates = array() ) {
        $main = get_option( 'znc_main_settings', array() );
        $custom = $main['exchange_rates'] ?? array();
        return array_merge( $rates, $custom );
    }

    /**
     * Provide MyCred configuration from admin settings.
     */
    public function filter_mycred_config( $config = array() ) {
        $network = get_site_option( 'znc_network_settings', array() );
        $main    = get_option( 'znc_main_settings', array() );
        return array_merge( $config, array(
            'enabled'          => ! empty( $network['zcred_enabled'] ),
            'exchange_rate'    => floatval( $network['zcred_exchange_rate'] ?? 0.01 ),
            'max_percent'      => intval( $network['zcred_max_percent'] ?? 100 ),
            'checkout_enabled' => ! empty( $main['zcred_checkout_enabled'] ),
            'earn_enabled'     => ! empty( $main['zcred_earn_enabled'] ),
            'earn_rate'        => floatval( $main['zcred_earn_rate'] ?? 1 ),
        ) );
    }

    /**
     * Provide per-subsite shipping overrides.
     */
    public function filter_shipping_config( $config, $site_id ) {
        switch_to_blog( $site_id );
        $sub = get_option( 'znc_subsite_settings', array() );
        restore_current_blog();

        return array(
            'mode'           => $sub['shipping_mode'] ?? 'inherit',
            'flat_rate'      => floatval( $sub['shipping_flat_rate'] ?? 0 ),
            'free_threshold' => floatval( $sub['shipping_free_threshold'] ?? 0 ),
            'note'           => $sub['shipping_note'] ?? '',
            'tax_on_shipping'=> ! empty( $sub['tax_on_shipping'] ),
        );
    }

    /**
     * Provide per-subsite tax overrides.
     */
    public function filter_tax_config( $config, $site_id ) {
        switch_to_blog( $site_id );
        $sub = get_option( 'znc_subsite_settings', array() );
        restore_current_blog();

        return array(
            'mode'   => $sub['tax_mode'] ?? 'inherit',
            'rate'   => floatval( $sub['tax_rate'] ?? 0 ),
            'label'  => $sub['tax_label'] ?? 'Tax',
            'exempt' => ! empty( $sub['tax_exempt'] ),
        );
    }

    /**
     * Provide shop branding for global cart display.
     */
    public function filter_shop_display( $display, $site_id ) {
        switch_to_blog( $site_id );
        $sub = get_option( 'znc_subsite_settings', array() );
        $display = array(
            'name'        => $sub['brand_display_name'] ?: get_bloginfo( 'name' ),
            'tagline'     => $sub['brand_tagline'] ?: get_bloginfo( 'description' ),
            'badge_color' => $sub['brand_badge_color'] ?? '#4f46e5',
            'icon_url'    => $sub['brand_icon_url'] ?? '',
            'site_url'    => home_url(),
        );
        restore_current_blog();
        return $display;
    }
}
