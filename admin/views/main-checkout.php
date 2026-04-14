<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'steps_display' => 'progress_bar', 'pre_checkout_refresh' => true,
    'price_change_action' => 'block', 'stock_change_action' => 'block',
    'coupon_support' => true, 'coupon_scope' => 'per_shop',
    'shipping_aggregation' => 'per_shop', 'tax_display' => 'itemized',
    'payment_gateways' => array(), 'split_pay_mode' => false,
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Checkout Settings', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_checkout_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Steps Display', 'zinckles-net-cart' ); ?></th>
                <td><select name="steps_display">
                    <option value="progress_bar" <?php selected( $s['steps_display'], 'progress_bar' ); ?>>Progress Bar</option>
                    <option value="accordion" <?php selected( $s['steps_display'], 'accordion' ); ?>>Accordion</option>
                    <option value="single_page" <?php selected( $s['steps_display'], 'single_page' ); ?>>Single Page</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Pre-Checkout Refresh', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="pre_checkout_refresh" value="1" <?php checked( $s['pre_checkout_refresh'] ); ?> /> Re-validate all items with origin shops before checkout</label></td></tr>
            <tr><th><?php esc_html_e( 'Price Change Action', 'zinckles-net-cart' ); ?></th>
                <td><select name="price_change_action">
                    <option value="block" <?php selected( $s['price_change_action'], 'block' ); ?>>Block checkout</option>
                    <option value="warn" <?php selected( $s['price_change_action'], 'warn' ); ?>>Warn but allow</option>
                    <option value="accept" <?php selected( $s['price_change_action'], 'accept' ); ?>>Accept new price silently</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Stock Change Action', 'zinckles-net-cart' ); ?></th>
                <td><select name="stock_change_action">
                    <option value="remove" <?php selected( $s['stock_change_action'], 'remove' ); ?>>Remove unavailable items</option>
                    <option value="reduce" <?php selected( $s['stock_change_action'], 'reduce' ); ?>>Reduce to available quantity</option>
                    <option value="block" <?php selected( $s['stock_change_action'], 'block' ); ?>>Block checkout</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Coupon Support', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="coupon_support" value="1" <?php checked( $s['coupon_support'] ); ?> /> Allow coupons in Net Cart checkout</label></td></tr>
            <tr><th><?php esc_html_e( 'Coupon Scope', 'zinckles-net-cart' ); ?></th>
                <td><select name="coupon_scope">
                    <option value="per_shop" <?php selected( $s['coupon_scope'], 'per_shop' ); ?>>Per-Shop (coupons apply to originating shop only)</option>
                    <option value="global" <?php selected( $s['coupon_scope'], 'global' ); ?>>Global (coupons apply to entire cart)</option>
                    <option value="both" <?php selected( $s['coupon_scope'], 'both' ); ?>>Both (shop + global coupons)</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Shipping Aggregation', 'zinckles-net-cart' ); ?></th>
                <td><select name="shipping_aggregation">
                    <option value="per_shop" <?php selected( $s['shipping_aggregation'], 'per_shop' ); ?>>Per-Shop (each shop calculates its own)</option>
                    <option value="flat" <?php selected( $s['shipping_aggregation'], 'flat' ); ?>>Flat Rate (single shipping fee)</option>
                    <option value="highest" <?php selected( $s['shipping_aggregation'], 'highest' ); ?>>Highest (use highest shop rate)</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Split Pay Mode', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="split_pay_mode" value="1" <?php checked( $s['split_pay_mode'] ); ?> /> Allow splitting payment between monetary + ZCreds</label></td></tr>
        </table>
        <input type="hidden" name="znc_save_checkout" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
