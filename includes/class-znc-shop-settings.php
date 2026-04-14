<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Shop_Settings {

    public function init() {
        // Settings are served via REST endpoint
    }

    /**
     * Return full shop configuration for the current subsite.
     */
    public function get_settings() : array {
        $subsite = get_option( 'znc_subsite_settings', array() );

        return array(
            'site_id'       => get_current_blog_id(),
            'site_name'     => get_bloginfo( 'name' ),
            'site_url'      => home_url(),
            'currency'      => get_woocommerce_currency(),
            'currency_pos'  => get_option( 'woocommerce_currency_pos', 'left' ),
            'thousand_sep'  => wc_get_price_thousand_separator(),
            'decimal_sep'   => wc_get_price_decimal_separator(),
            'num_decimals'  => wc_get_price_decimals(),
            'tax_enabled'   => wc_tax_enabled(),
            'tax_display'   => get_option( 'woocommerce_tax_display_cart', 'excl' ),
            'prices_inc_tax'=> wc_prices_include_tax(),
            'calc_taxes'    => 'yes' === get_option( 'woocommerce_calc_taxes' ),
            'shipping'      => $this->get_shipping_config( $subsite ),
            'tax_override'  => $this->get_tax_override( $subsite ),
            'mycred'        => $this->get_mycred_config( $subsite ),
            'branding'      => $this->get_branding( $subsite ),
            'product_count' => $this->get_eligible_product_count(),
            'wc_version'    => defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A',
            'znc_version'   => ZNC_VERSION,
            'timestamp'     => current_time( 'mysql', true ),
        );
    }

    private function get_shipping_config( array $subsite ) : array {
        $mode = $subsite['shipping_mode'] ?? 'inherit';
        return array(
            'mode'               => $mode,
            'flat_rate'          => floatval( $subsite['shipping_flat_rate'] ?? 0 ),
            'free_threshold'     => floatval( $subsite['shipping_free_threshold'] ?? 0 ),
            'shipping_note'      => $subsite['shipping_note'] ?? '',
            'tax_on_shipping'    => ! empty( $subsite['tax_on_shipping'] ),
        );
    }

    private function get_tax_override( array $subsite ) : array {
        return array(
            'mode'      => $subsite['tax_mode'] ?? 'inherit',
            'rate'      => floatval( $subsite['tax_rate'] ?? 0 ),
            'label'     => $subsite['tax_label'] ?? 'Tax',
            'exempt'    => ! empty( $subsite['tax_exempt'] ),
        );
    }

    private function get_mycred_config( array $subsite ) : array {
        $active = function_exists( 'mycred' );
        return array(
            'available'       => $active,
            'accept_zcreds'   => ! empty( $subsite['accept_zcreds'] ) && $active,
            'max_percent'     => intval( $subsite['zcred_max_percent'] ?? 100 ),
            'earn_multiplier' => floatval( $subsite['zcred_earn_multiplier'] ?? 1.0 ),
            'label'           => $active ? mycred()->core->singular() : 'ZCred',
            'plural_label'    => $active ? mycred()->core->plural() : 'ZCreds',
        );
    }

    private function get_branding( array $subsite ) : array {
        return array(
            'display_name' => $subsite['brand_display_name'] ?? get_bloginfo( 'name' ),
            'tagline'      => $subsite['brand_tagline'] ?? get_bloginfo( 'description' ),
            'badge_color'  => $subsite['brand_badge_color'] ?? '#4f46e5',
            'icon_url'     => $subsite['brand_icon_url'] ?? '',
        );
    }

    private function get_eligible_product_count() : int {
        $args = array(
            'status' => 'publish',
            'limit'  => -1,
            'return' => 'ids',
        );
        return count( wc_get_products( $args ) );
    }
}
