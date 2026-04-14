<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'znc_order_map';
$maps  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Order Map — Parent ↔ Child', 'zinckles-net-cart' ); ?></h1>
    <p><?php printf( '%d mapping(s) recorded.', count( $maps ) ); ?></p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Parent Order</th>
                <th>Child Order</th>
                <th>Child Site</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $maps ) ) : ?>
            <tr><td colspan="6">No order mappings found.</td></tr>
        <?php else : ?>
            <?php foreach ( $maps as $row ) : ?>
            <tr>
                <td><?php echo esc_html( $row['id'] ); ?></td>
                <td>#<?php echo esc_html( $row['parent_order_id'] ); ?></td>
                <td>#<?php echo esc_html( $row['child_order_id'] ); ?></td>
                <td><?php echo esc_html( $row['child_site_id'] ); ?></td>
                <td><?php echo esc_html( $row['status'] ); ?></td>
                <td><?php echo esc_html( $row['created_at'] ); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
