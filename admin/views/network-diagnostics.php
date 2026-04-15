<?php
/**
 * Network Diagnostics View — v1.5.0
 * System info, global cart stats, health checks.
 *
 * v1.5.0: Cart stats now read from wp_usermeta (_znc_global_cart)
 *         instead of custom znc_global_cart table.
 *         Custom table checks removed for cart (retained for order_map).
 *         MyCred/GamiPress live detection preserved from v1.4.2.
 */
defined( 'ABSPATH' ) || exit;

global $wpdb;
$settings = get_site_option( 'znc_network_settings', array() );
$host_id  = isset( $settings['checkout_host_id'] ) ? (int) $settings['checkout_host_id'] : get_main_site_id();

// v1.5.0: Cart stats from wp_usermeta — works from ANY blog context
$meta_key   = '_znc_global_cart';
$cart_rows  = $wpdb->get_results( $wpdb->prepare(
    "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != '' AND meta_value IS NOT NULL",
    $meta_key
) );

$cart_items  = 0;
$cart_users  = 0;
$cart_value  = 0;
$oldest_item = '—';
$user_ids_with_carts = array();

if ( $cart_rows ) {
    foreach ( $cart_rows as $row ) {
        $data = maybe_unserialize( $row->meta_value );
        if ( ! is_array( $data ) || empty( $data ) ) {
            continue;
        }
        $user_ids_with_carts[] = (int) $row->user_id;
        foreach ( $data as $item ) {
            $cart_items++;
            $qty   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
            $price = isset( $item['price'] ) ? (float) $item['price'] : 0;
            $cart_value += $qty * $price;
            if ( isset( $item['created_at'] ) && $item['created_at'] ) {
                if ( $oldest_item === '—' || $item['created_at'] < $oldest_item ) {
                    $oldest_item = $item['created_at'];
                }
            }
        }
    }
    $cart_users = count( array_unique( $user_ids_with_carts ) );
}

$enrolled = isset( $settings['enrolled_sites'] ) ? (array) $settings['enrolled_sites'] : array();

// v1.4.2: Live detection via DB queries — works in network admin context
$live_mycred_types    = array();
$live_gamipress_types = array();

if ( class_exists( 'ZNC_MyCred_Engine' ) && method_exists( 'ZNC_MyCred_Engine', 'detect_network_types' ) ) {
    $live_mycred_types = ZNC_MyCred_Engine::detect_network_types();
}
if ( class_exists( 'ZNC_GamiPress_Engine' ) && method_exists( 'ZNC_GamiPress_Engine', 'detect_network_types' ) ) {
    $live_gamipress_types = ZNC_GamiPress_Engine::detect_network_types();
}

// Order map table
$prefix = $wpdb->get_blog_prefix( $host_id );
$map_table = $prefix . 'znc_order_map';
$map_table_exists = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$map_table}'" );

$currency = isset( $settings['base_currency'] ) ? $settings['base_currency'] : 'USD';
$symbol   = '$';
if ( class_exists( 'ZNC_Currency_Handler' ) && method_exists( 'ZNC_Currency_Handler', 'format' ) ) {
    $formatted_value = ZNC_Currency_Handler::format( $cart_value, $currency );
} else {
    $formatted_value = $symbol . number_format( $cart_value, 2 );
}
?>
<div class="wrap znc-admin-wrap">
    <h1><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'Net Cart — Diagnostics', 'zinckles-net-cart' ); ?></h1>

    <!-- Health Checks -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'Health Checks', 'zinckles-net-cart' ); ?></h2>
        <table class="widefat striped">
            <tr>
                <td width="300"><?php esc_html_e( 'Cart Storage Engine', 'zinckles-net-cart' ); ?></td>
                <td><span style="color:#46b450;">✓ wp_usermeta (v1.5.0)</span> — <small>Zero switch_to_blog(), shared across all sites</small></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Cart Meta Key', 'zinckles-net-cart' ); ?></td>
                <td><code><?php echo esc_html( $meta_key ); ?></code></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Order Map Table', 'zinckles-net-cart' ); ?></td>
                <td><?php echo $map_table_exists ? '<span style="color:#46b450;">✓ ' . esc_html( $map_table ) . '</span>' : '<span style="color:#dc3232;">✗ Table missing: ' . esc_html( $map_table ) . '</span>'; ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Checkout Host', 'zinckles-net-cart' ); ?></td>
                <td>Blog ID <?php echo esc_html( $host_id ); ?> — <?php echo esc_html( get_blog_option( $host_id, 'blogname' ) ); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Enrolled Sites', 'zinckles-net-cart' ); ?></td>
                <td><?php echo count( $enrolled ); ?> site(s): <?php echo esc_html( implode( ', ', $enrolled ) ); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'HMAC Secret', 'zinckles-net-cart' ); ?></td>
                <td><?php echo ! empty( $settings['hmac_secret'] ) ? '<span style="color:#46b450;">✓ Configured (' . esc_html( substr( $settings['hmac_secret'], 0, 8 ) ) . '…)</span>' : '<span style="color:#f0ad4e;">⚠ Not set</span>'; ?></td>
            </tr>
        </table>
    </div>

    <!-- v1.5.0: Architecture Info -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'Architecture (v1.5.0)', 'zinckles-net-cart' ); ?></h2>
        <table class="widefat striped">
            <tr>
                <td width="300"><?php esc_html_e( 'Storage Method', 'zinckles-net-cart' ); ?></td>
                <td>wp_usermeta — single shared table across all network sites</td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'switch_to_blog() Calls', 'zinckles-net-cart' ); ?></td>
                <td><span style="color:#46b450;">0</span> — no blog switching for cart read/write</td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'REST API Calls', 'zinckles-net-cart' ); ?></td>
                <td><span style="color:#46b450;">0</span> — no inter-site REST for cart operations</td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Custom Tables (Cart)', 'zinckles-net-cart' ); ?></td>
                <td><span style="color:#46b450;">0</span> — no custom cart table needed</td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Memory Overhead', 'zinckles-net-cart' ); ?></td>
                <td>Minimal — no WooCommerce re-initialization per site</td>
            </tr>
        </table>
    </div>

    <!-- v1.4.2: Live Point Type Detection (DB-based) -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'Point Type Detection (Live)', 'zinckles-net-cart' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Real-time scan of all sites using direct database queries. This works regardless of whether plugins are loaded in network admin context.', 'zinckles-net-cart' ); ?></p>

        <h3 style="margin-top:16px;"><?php esc_html_e( 'MyCred Types', 'zinckles-net-cart' ); ?></h3>
        <?php if ( ! empty( $live_mycred_types ) ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Slug', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Label', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Blog ID', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Configured?', 'zinckles-net-cart' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $live_mycred_types as $slug => $info ) :
                    $is_configured = ! empty( $settings['mycred_types_config'][ $slug ] );
                ?>
                <tr>
                    <td><code><?php echo esc_html( $slug ); ?></code></td>
                    <td><?php echo esc_html( $info['label'] ?? $slug ); ?></td>
                    <td><?php echo esc_html( $info['blog_id'] ?? '—' ); ?></td>
                    <td><?php echo $is_configured ? '<span style="color:#46b450;">✓</span>' : '<span style="color:#f0ad4e;">⚠ Not saved</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p><span style="color:#999;"><?php esc_html_e( 'No MyCred point types found on any enrolled site.', 'zinckles-net-cart' ); ?></span></p>
        <?php endif; ?>

        <h3 style="margin-top:16px;"><?php esc_html_e( 'GamiPress Types', 'zinckles-net-cart' ); ?></h3>
        <?php if ( ! empty( $live_gamipress_types ) ) : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Slug', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Label', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Blog ID', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Configured?', 'zinckles-net-cart' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $live_gamipress_types as $slug => $info ) :
                    $is_configured = ! empty( $settings['gamipress_types_config'][ $slug ] );
                ?>
                <tr>
                    <td><code><?php echo esc_html( $slug ); ?></code></td>
                    <td><?php echo esc_html( $info['label'] ?? $slug ); ?></td>
                    <td><?php echo esc_html( $info['blog_id'] ?? '—' ); ?></td>
                    <td><?php echo $is_configured ? '<span style="color:#46b450;">✓</span>' : '<span style="color:#f0ad4e;">⚠ Not saved</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p><span style="color:#999;"><?php esc_html_e( 'No GamiPress point types found on any enrolled site.', 'zinckles-net-cart' ); ?></span></p>
        <?php endif; ?>

        <h3 style="margin-top:16px;"><?php esc_html_e( 'Saved Configuration', 'zinckles-net-cart' ); ?></h3>
        <table class="widefat striped">
            <tr>
                <td width="300"><?php esc_html_e( 'MyCred (Saved)', 'zinckles-net-cart' ); ?></td>
                <td><?php echo ! empty( $settings['mycred_types_config'] ) ? count( $settings['mycred_types_config'] ) . ' type(s) configured' : '<span style="color:#999;">None saved — run Auto-Detect in Network Settings</span>'; ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'GamiPress (Saved)', 'zinckles-net-cart' ); ?></td>
                <td><?php echo ! empty( $settings['gamipress_types_config'] ) ? count( $settings['gamipress_types_config'] ) . ' type(s) configured' : '<span style="color:#999;">None saved — run Auto-Detect in Network Settings</span>'; ?></td>
            </tr>
        </table>
    </div>

    <!-- Global Cart Stats -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'Global Cart Statistics', 'zinckles-net-cart' ); ?></h2>
        <div class="znc-stats-grid">
            <div class="znc-stat-card">
                <span class="znc-stat-value"><?php echo esc_html( $cart_items ); ?></span>
                <span class="znc-stat-label"><?php esc_html_e( 'Total Items', 'zinckles-net-cart' ); ?></span>
            </div>
            <div class="znc-stat-card">
                <span class="znc-stat-value"><?php echo esc_html( $cart_users ); ?></span>
                <span class="znc-stat-label"><?php esc_html_e( 'Active Carts', 'zinckles-net-cart' ); ?></span>
            </div>
            <div class="znc-stat-card">
                <span class="znc-stat-value"><?php echo esc_html( $formatted_value ); ?></span>
                <span class="znc-stat-label"><?php esc_html_e( 'Total Value', 'zinckles-net-cart' ); ?></span>
            </div>
        </div>
        <table class="widefat striped" style="margin-top:16px;">
            <tr>
                <td><?php esc_html_e( 'Oldest Cart Item', 'zinckles-net-cart' ); ?></td>
                <td><?php echo esc_html( $oldest_item ); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e( 'Cart Expiry Setting', 'zinckles-net-cart' ); ?></td>
                <td><?php echo esc_html( $settings['cart_expiry_days'] ?? 7 ); ?> days</td>
            </tr>
        </table>
    </div>

    <!-- System Info -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'System Information', 'zinckles-net-cart' ); ?></h2>
        <table class="widefat striped">
            <tr><td><?php esc_html_e( 'Plugin Version', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( ZNC_VERSION ); ?></td></tr>
            <tr><td><?php esc_html_e( 'WordPress', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            <tr><td><?php esc_html_e( 'PHP', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( phpversion() ); ?></td></tr>
            <tr><td><?php esc_html_e( 'MySQL / MariaDB', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( $wpdb->db_version() ); ?></td></tr>
            <tr><td><?php esc_html_e( 'Multisite', 'zinckles-net-cart' ); ?></td><td><?php echo is_multisite() ? '✓ Yes' : '✗ No'; ?></td></tr>
            <tr><td><?php esc_html_e( 'Network Sites', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( get_blog_count() ); ?></td></tr>
            <tr><td><?php esc_html_e( 'PHP Memory Limit', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( ini_get( 'memory_limit' ) ); ?></td></tr>
            <tr><td><?php esc_html_e( 'Max Execution Time', 'zinckles-net-cart' ); ?></td><td><?php echo esc_html( ini_get( 'max_execution_time' ) ); ?>s</td></tr>
            <tr><td><?php esc_html_e( 'WP Debug', 'zinckles-net-cart' ); ?></td><td><?php echo defined( 'WP_DEBUG' ) && WP_DEBUG ? '✓ On' : '✗ Off'; ?></td></tr>
        </table>
    </div>

    <!-- Debug Log -->
    <div class="znc-settings-section">
        <h2><?php esc_html_e( 'Debug Settings', 'zinckles-net-cart' ); ?></h2>
        <p>
            <?php esc_html_e( 'Net Cart Debug Mode:', 'zinckles-net-cart' ); ?>
            <strong><?php echo ! empty( $settings['debug_mode'] ) ? '✓ Enabled' : '✗ Disabled'; ?></strong>
        </p>
        <p class="description"><?php esc_html_e( 'When enabled, Net Cart writes detailed logs to the WordPress debug.log file. Enable this in Network Settings.', 'zinckles-net-cart' ); ?></p>
    </div>
</div>
