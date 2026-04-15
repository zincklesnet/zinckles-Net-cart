<?php
/**
 * Main Admin — v1.5.0
 * Admin page on the main/checkout-host site showing global cart stats.
 *
 * v1.5.0: Dashboard stats now read from wp_usermeta via
 *         ZNC_Global_Cart_Store::get_admin_stats() instead of
 *         querying custom znc_global_cart table.
 *         All other functionality preserved from v1.4.2.
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Main_Admin {

    /** @var ZNC_Global_Cart_Store */
    private $store;

    /** @var ZNC_Checkout_Host */
    private $host;

    /**
     * v1.4.2: Accept both $store and $host as constructor params.
     *
     * @param ZNC_Global_Cart_Store $store
     * @param ZNC_Checkout_Host     $host
     */
    public function __construct( ZNC_Global_Cart_Store $store, ZNC_Checkout_Host $host ) {
        $this->store = $store;
        $this->host  = $host;
    }

    public function init() {
        // Show on the checkout host site (which may or may not be the main site)
        if ( ! $this->host->is_current_site_host() ) return;
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'Zinckles Net Cart', 'zinckles-net-cart' ),
            __( 'Net Cart', 'zinckles-net-cart' ),
            'manage_options',
            'znc-main-admin',
            array( $this, 'render_page' ),
            'dashicons-cart',
            58
        );
    }

    public function render_page() {
        $settings = get_site_option( 'znc_network_settings', array() );
        $host_id  = $this->host->get_host_id();
        $enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();

        // v1.5.0: Stats from wp_usermeta via Store class
        $stats = ZNC_Global_Cart_Store::get_admin_stats();

        $total_items = $stats['total_items'];
        $total_users = $stats['total_users'];
        $total_value = $stats['total_value'];

        $currency = isset( $settings['base_currency'] ) ? $settings['base_currency'] : 'USD';
        if ( class_exists( 'ZNC_Currency_Handler' ) && method_exists( 'ZNC_Currency_Handler', 'format' ) ) {
            $formatted_value = ZNC_Currency_Handler::format( $total_value, $currency );
        } else {
            $formatted_value = '$' . number_format( $total_value, 2 );
        }
        ?>
        <div class="wrap znc-admin-wrap">
            <h1><span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Zinckles Net Cart — Dashboard', 'zinckles-net-cart' ); ?></h1>
            <div class="znc-stats-grid">
                <div class="znc-stat-card">
                    <span class="znc-stat-value"><?php echo esc_html( $total_items ); ?></span>
                    <span class="znc-stat-label"><?php esc_html_e( 'Cart Items', 'zinckles-net-cart' ); ?></span>
                </div>
                <div class="znc-stat-card">
                    <span class="znc-stat-value"><?php echo esc_html( $total_users ); ?></span>
                    <span class="znc-stat-label"><?php esc_html_e( 'Active Carts', 'zinckles-net-cart' ); ?></span>
                </div>
                <div class="znc-stat-card">
                    <span class="znc-stat-value"><?php echo esc_html( $formatted_value ); ?></span>
                    <span class="znc-stat-label"><?php esc_html_e( 'Total Value', 'zinckles-net-cart' ); ?></span>
                </div>
                <div class="znc-stat-card">
                    <span class="znc-stat-value"><?php echo count( $enrolled ); ?></span>
                    <span class="znc-stat-label"><?php esc_html_e( 'Enrolled Sites', 'zinckles-net-cart' ); ?></span>
                </div>
            </div>

            <div class="znc-info-card">
                <h2><?php esc_html_e( 'Quick Links', 'zinckles-net-cart' ); ?></h2>
                <ul>
                    <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=zinckles-net-cart' ) ); ?>"><?php esc_html_e( 'Network Settings', 'zinckles-net-cart' ); ?></a></li>
                    <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=znc-network-subsites' ) ); ?>"><?php esc_html_e( 'Enrolled Subsites', 'zinckles-net-cart' ); ?></a></li>
                    <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=znc-network-security' ) ); ?>"><?php esc_html_e( 'Security Settings', 'zinckles-net-cart' ); ?></a></li>
                    <li><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=znc-network-diagnostics' ) ); ?>"><?php esc_html_e( 'Diagnostics', 'zinckles-net-cart' ); ?></a></li>
                </ul>
            </div>

            <div class="znc-info-card">
                <h2><?php esc_html_e( 'Version Info', 'zinckles-net-cart' ); ?></h2>
                <table class="widefat striped">
                    <tr><td><?php esc_html_e( 'Plugin Version', 'zinckles-net-cart' ); ?></td><td><strong><?php echo esc_html( ZNC_VERSION ); ?></strong></td></tr>
                    <tr><td><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></td><td>Blog ID <?php echo esc_html( $host_id ); ?> — <?php echo esc_html( get_blog_option( $host_id, 'blogname' ) ); ?></td></tr>
                    <tr><td><?php esc_html_e( 'Cart Storage', 'zinckles-net-cart' ); ?></td><td><span style="color:#46b450;">✓ wp_usermeta (v1.5.0)</span></td></tr>
                    <tr><td><?php esc_html_e( 'Base Currency', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( $currency ); ?></td></tr>
                </table>
            </div>
        </div>
        <?php
    }
}
