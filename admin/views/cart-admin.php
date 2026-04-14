<?php
defined( 'ABSPATH' ) || exit;

global $wpdb;
$table = $wpdb->prefix . 'znc_global_cart';
$lines = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY user_id, site_id, id LIMIT 200", ARRAY_A );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Global Cart — All Users', 'zinckles-net-cart' ); ?></h1>
    <p><?php printf( '%d line(s) across the network.', count( $lines ) ); ?></p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Site</th>
                <th>Product</th>
                <th>Variation</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Currency</th>
                <th>MyCred</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $lines ) ) : ?>
            <tr><td colspan="10">No cart lines found.</td></tr>
        <?php else : ?>
            <?php foreach ( $lines as $row ) : ?>
            <tr>
                <td><?php echo esc_html( $row['id'] ); ?></td>
                <td><?php echo esc_html( $row['user_id'] ); ?></td>
                <td><?php echo esc_html( $row['site_id'] ); ?></td>
                <td><?php echo esc_html( $row['product_id'] ); ?></td>
                <td><?php echo esc_html( $row['variation_id'] ); ?></td>
                <td><?php echo esc_html( $row['quantity'] ); ?></td>
                <td><?php echo esc_html( number_format( (float) $row['unit_price'], 2 ) ); ?></td>
                <td><?php echo esc_html( $row['currency'] ); ?></td>
                <td><?php echo esc_html( number_format( (float) $row['mycred_value'], 2 ) ); ?></td>
                <td><?php echo esc_html( $row['updated_at'] ); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
