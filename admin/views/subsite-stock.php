<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'stock_reservation_minutes' => 0, 'low_stock_threshold' => 0,
    'realtime_stock_push' => false, 'coupon_available' => true,
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Stock Settings', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_subsite_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Stock Reservation', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="stock_reservation_minutes" value="<?php echo esc_attr( $s['stock_reservation_minutes'] ); ?>" min="0" max="1440" class="small-text" /> minutes
                <p class="description">Reserve stock when added to Net Cart (0 = no reservation)</p></td></tr>
            <tr><th><?php esc_html_e( 'Low Stock Threshold', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="low_stock_threshold" value="<?php echo esc_attr( $s['low_stock_threshold'] ); ?>" min="0" class="small-text" />
                <p class="description">Stop pushing to Net Cart when stock falls to this level (0 = disabled)</p></td></tr>
            <tr><th><?php esc_html_e( 'Real-Time Stock Push', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="realtime_stock_push" value="1" <?php checked( $s['realtime_stock_push'] ); ?> /> Notify main site immediately when stock changes</label></td></tr>
            <tr><th><?php esc_html_e( 'Coupons in Net Cart', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="coupon_available" value="1" <?php checked( $s['coupon_available'] ); ?> /> Allow this shop's coupons to be used in Net Cart checkout</label></td></tr>
        </table>
        <input type="hidden" name="znc_save_subsite" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
