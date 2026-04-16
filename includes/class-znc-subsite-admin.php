<?php
/**
 * Subsite Admin — Per-subsite admin dashboard for Net Cart.
 *
 * Shows subsite admins their enrollment status, connection to the
 * checkout host, and basic cart diagnostics.
 *
 * @package ZincklesNetCart
 * @since   1.7.2
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Subsite_Admin {

    public function init() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'Net Cart', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_woocommerce',
            'znc-subsite-admin',
            array( $this, 'render' ),
            'dashicons-cart',
            56
        );
    }

    public function render() {
        $host      = ZNC_Checkout_Host::instance();
        $settings  = get_site_option( 'znc_network_settings', array() );
        $blog_id   = get_current_blog_id();
        $enrolled  = (array) ( $settings['enrolled_sites'] ?? array() );
        $mode      = $settings['enrollment_mode'] ?? 'opt-in';
        $is_enrolled = ( $mode === 'auto' || $mode === 'opt-out' )
            ? true
            : in_array( $blog_id, array_map( 'absint', $enrolled ), true );

        $wc_plugins = array();
        if ( class_exists( 'ZNC_WC_Plugin_Detector' ) ) {
            $wc_plugins = ZNC_WC_Plugin_Detector::get_site_wc_plugins( $blog_id );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Zinckles Net Cart — Subsite Dashboard', 'zinckles-net-cart' ); ?></h1>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin-top:20px">

                <!-- Status Card -->
                <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;border-radius:8px">
                    <h3><?php esc_html_e( 'Connection Status', 'zinckles-net-cart' ); ?></h3>
                    <table class="widefat striped" style="margin-top:10px">
                        <tr>
                            <td><strong><?php esc_html_e( 'This Site', 'zinckles-net-cart' ); ?></strong></td>
                            <td><?php echo esc_html( get_bloginfo( 'name' ) ); ?> (ID: <?php echo esc_html( $blog_id ); ?>)</td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Enrollment', 'zinckles-net-cart' ); ?></strong></td>
                            <td>
                                <?php if ( $is_enrolled ) : ?>
                                    <span style="color:#46b450">&#10004; <?php esc_html_e( 'Enrolled', 'zinckles-net-cart' ); ?></span>
                                <?php else : ?>
                                    <span style="color:#dc3232">&#10008; <?php esc_html_e( 'Not Enrolled', 'zinckles-net-cart' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></strong></td>
                            <td><a href="<?php echo esc_url( $host->get_host_url() ); ?>"><?php echo esc_html( $host->get_host_url() ); ?></a></td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'WooCommerce', 'zinckles-net-cart' ); ?></strong></td>
                            <td>
                                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                                    <span style="color:#46b450">&#10004; v<?php echo esc_html( WC()->version ); ?></span>
                                <?php else : ?>
                                    <span style="color:#dc3232">&#10008; <?php esc_html_e( 'Not Active', 'zinckles-net-cart' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e( 'Currency', 'zinckles-net-cart' ); ?></strong></td>
                            <td>
                                <?php
                                if ( function_exists( 'get_woocommerce_currency' ) ) {
                                    $code   = get_woocommerce_currency();
                                    $symbol = get_woocommerce_currency_symbol();
                                    echo esc_html( "{$code} ({$symbol})" );
                                } else {
                                    esc_html_e( 'N/A', 'zinckles-net-cart' );
                                }
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Quick Links Card -->
                <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;border-radius:8px">
                    <h3><?php esc_html_e( 'Quick Links', 'zinckles-net-cart' ); ?></h3>
                    <ul style="list-style:disc;margin-left:20px;line-height:2">
                        <li><a href="<?php echo esc_url( $host->get_cart_url() ); ?>"><?php esc_html_e( 'Global Cart', 'zinckles-net-cart' ); ?></a></li>
                        <li><a href="<?php echo esc_url( $host->get_checkout_url() ); ?>"><?php esc_html_e( 'Checkout', 'zinckles-net-cart' ); ?></a></li>
                        <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=znc-settings' ) ); ?>"><?php esc_html_e( 'Network Settings', 'zinckles-net-cart' ); ?></a></li>
                    </ul>
                </div>

                <!-- WC Plugins Card -->
                <?php if ( ! empty( $wc_plugins ) ) : ?>
                <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;border-radius:8px">
                    <h3><?php esc_html_e( 'WooCommerce Plugins on This Site', 'zinckles-net-cart' ); ?></h3>
                    <ul style="list-style:disc;margin-left:20px;line-height:2">
                        <?php foreach ( $wc_plugins as $plugin ) : ?>
                            <li>
                                <strong><?php echo esc_html( $plugin['name'] ); ?></strong>
                                <?php if ( ! empty( $plugin['version'] ) ) : ?>
                                    <span style="color:#666">(v<?php echo esc_html( $plugin['version'] ); ?>)</span>
                                <?php endif; ?>
                                — <span style="color:<?php echo $plugin['active'] ? '#46b450' : '#dc3232'; ?>">
                                    <?php echo $plugin['active'] ? esc_html__( 'Active', 'zinckles-net-cart' ) : esc_html__( 'Inactive', 'zinckles-net-cart' ); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

            </div>
        </div>
        <?php
    }
}
