<?php
/**
 * Main Site Admin — cart host settings, cart browser, order map.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Main_Admin {

    private $store;

    public function __construct( ZNC_Global_Cart_Store $store ) {
        $this->store = $store;
    }

    public function init() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Register shortcodes.
        add_shortcode( 'znc_global_cart', array( $this, 'shortcode_global_cart' ) );
        add_shortcode( 'znc_checkout',   array( $this, 'shortcode_checkout' ) );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Net Cart', 'znc' ),
            __( 'Net Cart', 'znc' ),
            'manage_options',
            'znc-settings',
            array( $this, 'render_settings' ),
            'dashicons-networking',
            58
        );

        add_submenu_page(
            'znc-settings',
            __( 'Cart Browser', 'znc' ),
            __( 'Cart Browser', 'znc' ),
            'manage_options',
            'znc-cart-browser',
            array( $this, 'render_cart_browser' )
        );

        add_submenu_page(
            'znc-settings',
            __( 'Order Map', 'znc' ),
            __( 'Order Map', 'znc' ),
            'manage_options',
            'znc-order-map',
            array( $this, 'render_order_map' )
        );
    }

    public function enqueue_scripts( $hook ) {
        if ( false === strpos( $hook, 'znc-' ) ) {
            return;
        }
        wp_enqueue_style( 'znc-admin', ZNC_PLUGIN_URL . 'admin/assets/css/znc-network-admin.css', array(), ZNC_VERSION );
    }

    public function render_settings() {
        $settings = ZNC_Network_Admin::get_settings();
        ?>
        <div class="wrap">
            <h1><?php _e( 'Zinckles Net Cart — Main Site Settings', 'znc' ); ?></h1>
            <div class="card" style="max-width:600px;">
                <h2><?php _e( 'Quick Info', 'znc' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e( 'Enrolled Shops', 'znc' ); ?></th>
                        <td><strong><?php echo count( ZNC_Network_Admin::get_enrolled_sites() ); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Base Currency', 'znc' ); ?></th>
                        <td><code><?php echo esc_html( $settings['base_currency'] ); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'MyCred', 'znc' ); ?></th>
                        <td><?php echo ! empty( $settings['mycred_enabled'] ) ? '✅ Enabled' : '❌ Disabled'; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e( 'Plugin Version', 'znc' ); ?></th>
                        <td><code><?php echo ZNC_VERSION; ?></code></td>
                    </tr>
                </table>
            </div>
            <div class="card" style="max-width:600px;margin-top:20px;">
                <h2><?php _e( 'Shortcodes', 'znc' ); ?></h2>
                <p><code>[znc_global_cart]</code> — <?php _e( 'Displays the unified global cart from all enrolled shops.', 'znc' ); ?></p>
                <p><code>[znc_checkout]</code> — <?php _e( 'Displays the Net Cart checkout form.', 'znc' ); ?></p>
            </div>
        </div>
        <?php
    }

    public function render_cart_browser() {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_global_cart';

        // Get unique users with carts.
        $users = $wpdb->get_results(
            "SELECT user_id, COUNT(*) as items, COUNT(DISTINCT blog_id) as shops,
                    SUM(line_total) as total, MAX(updated_at) as last_updated
             FROM {$table} GROUP BY user_id ORDER BY last_updated DESC LIMIT 50"
        );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Net Cart — Cart Browser', 'znc' ); ?></h1>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e( 'User', 'znc' ); ?></th>
                        <th><?php _e( 'Items', 'znc' ); ?></th>
                        <th><?php _e( 'Shops', 'znc' ); ?></th>
                        <th><?php _e( 'Total', 'znc' ); ?></th>
                        <th><?php _e( 'Last Updated', 'znc' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $users ) ) : ?>
                        <tr><td colspan="5"><?php _e( 'No active carts.', 'znc' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $users as $u ) :
                            $user = get_user_by( 'id', $u->user_id );
                        ?>
                        <tr>
                            <td><?php echo $user ? esc_html( $user->display_name . ' (' . $user->user_email . ')' ) : 'User #' . $u->user_id; ?></td>
                            <td><?php echo (int) $u->items; ?></td>
                            <td><?php echo (int) $u->shops; ?></td>
                            <td><?php echo number_format( (float) $u->total, 2 ); ?></td>
                            <td><?php echo esc_html( $u->last_updated ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_order_map() {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_order_map';

        $orders = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 50"
        );
        ?>
        <div class="wrap">
            <h1><?php _e( 'Net Cart — Order Map', 'znc' ); ?></h1>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Parent Order', 'znc' ); ?></th>
                        <th><?php _e( 'Child Order', 'znc' ); ?></th>
                        <th><?php _e( 'Subsite', 'znc' ); ?></th>
                        <th><?php _e( 'Currency', 'znc' ); ?></th>
                        <th><?php _e( 'Subtotal', 'znc' ); ?></th>
                        <th><?php _e( 'Status', 'znc' ); ?></th>
                        <th><?php _e( 'Created', 'znc' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $orders ) ) : ?>
                        <tr><td colspan="7"><?php _e( 'No orders yet.', 'znc' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $orders as $o ) : ?>
                        <tr>
                            <td>#<?php echo (int) $o->parent_order_id; ?></td>
                            <td>#<?php echo (int) $o->child_order_id; ?></td>
                            <td>Site <?php echo (int) $o->child_blog_id; ?></td>
                            <td><code><?php echo esc_html( $o->currency ); ?></code></td>
                            <td><?php echo number_format( (float) $o->subtotal, 2 ); ?></td>
                            <td><?php echo esc_html( $o->status ); ?></td>
                            <td><?php echo esc_html( $o->created_at ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function shortcode_global_cart() {
        ob_start();
        include ZNC_PLUGIN_DIR . 'templates/global-cart.php';
        return ob_get_clean();
    }

    public function shortcode_checkout() {
        ob_start();
        include ZNC_PLUGIN_DIR . 'templates/checkout.php';
        return ob_get_clean();
    }
}
