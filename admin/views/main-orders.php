<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'order_prefix_parent' => 'ZNC', 'order_prefix_child' => 'ZNC-C',
    'default_parent_status' => 'processing', 'default_child_status' => 'processing',
    'auto_complete_digital' => false, 'verbose_notes' => true, 'sync_child_status' => true,
) );
$factory = new ZNC_Order_Factory();
$lookup_id = intval( $_GET['lookup'] ?? 0 );
$children = $lookup_id ? $factory->get_children( $lookup_id ) : array();
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Orders', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_orders_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Parent Order Prefix', 'zinckles-net-cart' ); ?></th>
                <td><input type="text" name="order_prefix_parent" value="<?php echo esc_attr( $s['order_prefix_parent'] ); ?>" class="small-text" /></td></tr>
            <tr><th><?php esc_html_e( 'Child Order Prefix', 'zinckles-net-cart' ); ?></th>
                <td><input type="text" name="order_prefix_child" value="<?php echo esc_attr( $s['order_prefix_child'] ); ?>" class="small-text" /></td></tr>
            <tr><th><?php esc_html_e( 'Default Parent Status', 'zinckles-net-cart' ); ?></th>
                <td><select name="default_parent_status">
                    <?php foreach ( wc_get_order_statuses() as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( str_replace( 'wc-', '', $key ) ); ?>" <?php selected( $s['default_parent_status'], str_replace( 'wc-', '', $key ) ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Auto-Complete Digital', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="auto_complete_digital" value="1" <?php checked( $s['auto_complete_digital'] ); ?> /> Auto-complete orders with only digital/downloadable products</label></td></tr>
            <tr><th><?php esc_html_e( 'Verbose Order Notes', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="verbose_notes" value="1" <?php checked( $s['verbose_notes'] ); ?> /> Add detailed Net Cart metadata to order notes</label></td></tr>
            <tr><th><?php esc_html_e( 'Sync Child Status', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="sync_child_status" value="1" <?php checked( $s['sync_child_status'] ); ?> /> When parent status changes, update all child orders</label></td></tr>
        </table>
        <input type="hidden" name="znc_save_orders" value="1" />
        <?php submit_button(); ?>
    </form>

    <hr />
    <h2><?php esc_html_e( 'Order Map Lookup', 'zinckles-net-cart' ); ?></h2>
    <form method="get" style="margin-bottom:20px;">
        <input type="hidden" name="page" value="znc-orders" />
        <input type="number" name="lookup" value="<?php echo esc_attr( $lookup_id ); ?>" placeholder="Parent Order ID" class="small-text" />
        <button type="submit" class="button"><?php esc_html_e( 'Look Up Children', 'zinckles-net-cart' ); ?></button>
    </form>
    <?php if ( $lookup_id && ! empty( $children ) ) : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead><tr><th>Child Order ID</th><th>Site ID</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ( $children as $c ) : ?>
        <tr>
            <td>#<?php echo esc_html( $c['child_order_id'] ); ?></td>
            <td><?php echo esc_html( $c['child_site_id'] ); ?></td>
            <td><?php echo esc_html( $c['status'] ); ?></td>
            <td><?php echo esc_html( $c['created_at'] ); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php elseif ( $lookup_id ) : ?>
    <p><?php esc_html_e( 'No child orders found for this parent.', 'zinckles-net-cart' ); ?></p>
    <?php endif; ?>
</div>
