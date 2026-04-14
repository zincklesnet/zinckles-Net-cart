<?php
/**
 * Admin — network admin settings page for Zinckles Net Cart.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Admin {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );

        // Network admin menu.
        add_action( 'network_admin_menu', [ __CLASS__, 'add_network_menu' ] );
    }

    public static function add_menu(): void {
        if ( ! is_main_site() ) {
            return;
        }

        add_menu_page(
            __( 'Net Cart', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_options',
            'znc-settings',
            [ __CLASS__, 'render_settings_page' ],
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'znc-settings',
            __( 'Global Cart', 'zinckles-net-cart' ),
            __( 'Global Cart', 'zinckles-net-cart' ),
            'manage_options',
            'znc-global-cart',
            [ __CLASS__, 'render_cart_admin' ]
        );

        add_submenu_page(
            'znc-settings',
            __( 'Order Map', 'zinckles-net-cart' ),
            __( 'Order Map', 'zinckles-net-cart' ),
            'manage_options',
            'znc-order-map',
            [ __CLASS__, 'render_order_map' ]
        );
    }

    public static function add_network_menu(): void {
        add_menu_page(
            __( 'Net Cart', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_network_options',
            'znc-network',
            [ __CLASS__, 'render_network_page' ],
            'dashicons-cart',
            56
        );
    }

    public static function register_settings(): void {
        register_setting( 'znc_settings', 'znc_enabled' );
        register_setting( 'znc_settings', 'znc_fallback_currency' );
        register_setting( 'znc_settings', 'znc_mycred_enabled' );
        register_setting( 'znc_settings', 'znc_mycred_label' );
        register_setting( 'znc_settings', 'znc_inventory_sync' );
        register_setting( 'znc_settings', 'znc_price_tolerance' );

        add_settings_section(
            'znc_general',
            __( 'General Settings', 'zinckles-net-cart' ),
            null,
            'znc-settings'
        );

        add_settings_field(
            'znc_enabled',
            __( 'Enable Net Cart', 'zinckles-net-cart' ),
            [ __CLASS__, 'field_checkbox' ],
            'znc-settings',
            'znc_general',
            [ 'id' => 'znc_enabled', 'label' => 'Enable the global cart system across the network.' ]
        );

        add_settings_field(
            'znc_fallback_currency',
            __( 'Fallback Currency', 'zinckles-net-cart' ),
            [ __CLASS__, 'field_text' ],
            'znc-settings',
            'znc_general',
            [ 'id' => 'znc_fallback_currency', 'desc' => 'ISO 4217 code (e.g. USD, EUR, CAD).' ]
        );

        add_settings_field(
            'znc_mycred_enabled',
            __( 'MyCred Integration', 'zinckles-net-cart' ),
            [ __CLASS__, 'field_select' ],
            'znc-settings',
            'znc_general',
            [
                'id'      => 'znc_mycred_enabled',
                'options' => [
                    'auto' => 'Auto-detect',
                    'yes'  => 'Force On',
                    'no'   => 'Disabled',
                ],
            ]
        );

        add_settings_field(
            'znc_mycred_label',
            __( 'Credit Label', 'zinckles-net-cart' ),
            [ __CLASS__, 'field_text' ],
            'znc-settings',
            'znc_general',
            [ 'id' => 'znc_mycred_label', 'desc' => 'Display name for credits (e.g. ZCreds).' ]
        );

        add_settings_field(
            'znc_price_tolerance',
            __( 'Price Tolerance', 'zinckles-net-cart' ),
            [ __CLASS__, 'field_text' ],
            'znc-settings',
            'znc_general',
            [ 'id' => 'znc_price_tolerance', 'desc' => 'Allowed price drift in currency units (0 = exact match).' ]
        );
    }

    // ── Render Callbacks ─────────────────────────────────────────────────────

    public static function render_settings_page(): void {
        include ZNC_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public static function render_cart_admin(): void {
        include ZNC_PLUGIN_DIR . 'admin/views/cart-admin.php';
    }

    public static function render_order_map(): void {
        include ZNC_PLUGIN_DIR . 'admin/views/order-map.php';
    }

    public static function render_network_page(): void {
        include ZNC_PLUGIN_DIR . 'admin/views/network.php';
    }

    // ── Field Renderers ──────────────────────────────────────────────────────

    public static function field_checkbox( array $args ): void {
        $value = get_option( $args['id'], 'yes' );
        printf(
            '<label><input type="checkbox" name="%s" value="yes" %s /> %s</label>',
            esc_attr( $args['id'] ),
            checked( $value, 'yes', false ),
            esc_html( $args['label'] ?? '' )
        );
    }

    public static function field_text( array $args ): void {
        $value = get_option( $args['id'], '' );
        printf(
            '<input type="text" name="%s" value="%s" class="regular-text" />',
            esc_attr( $args['id'] ),
            esc_attr( $value )
        );
        if ( ! empty( $args['desc'] ) ) {
            printf( '<p class="description">%s</p>', esc_html( $args['desc'] ) );
        }
    }

    public static function field_select( array $args ): void {
        $value = get_option( $args['id'], '' );
        printf( '<select name="%s">', esc_attr( $args['id'] ) );
        foreach ( $args['options'] as $k => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $k ),
                selected( $value, $k, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }
}
