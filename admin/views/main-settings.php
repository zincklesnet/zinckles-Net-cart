<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'cart_page_id' => 0, 'checkout_page_id' => 0, 'thankyou_page_id' => 0,
    'min_order_amount' => 0, 'max_order_amount' => 0,
    'require_account' => true, 'allow_guest_checkout' => false,
    'merge_duplicates' => true, 'quantity_cap' => 99,
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Main Site Settings', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_settings_nonce' ); ?>

        <h2><?php esc_html_e( 'Page Assignments', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Cart Page', 'zinckles-net-cart' ); ?></th>
                <td><?php wp_dropdown_pages( array( 'name' => 'cart_page_id', 'selected' => $s['cart_page_id'], 'show_option_none' => '— Select —' ) ); ?>
                <p class="description">Use shortcode <code>[znc_global_cart]</code></p></td></tr>
            <tr><th><?php esc_html_e( 'Checkout Page', 'zinckles-net-cart' ); ?></th>
                <td><?php wp_dropdown_pages( array( 'name' => 'checkout_page_id', 'selected' => $s['checkout_page_id'], 'show_option_none' => '— Select —' ) ); ?>
                <p class="description">Use shortcode <code>[znc_checkout]</code></p></td></tr>
            <tr><th><?php esc_html_e( 'Thank You Page', 'zinckles-net-cart' ); ?></th>
                <td><?php wp_dropdown_pages( array( 'name' => 'thankyou_page_id', 'selected' => $s['thankyou_page_id'], 'show_option_none' => '— Select —' ) ); ?></td></tr>
        </table>

        <h2><?php esc_html_e( 'Order Limits', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Minimum Order Amount', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="min_order_amount" value="<?php echo esc_attr( $s['min_order_amount'] ); ?>" step="0.01" min="0" class="small-text" /> <span class="description">0 = no minimum</span></td></tr>
            <tr><th><?php esc_html_e( 'Maximum Order Amount', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="max_order_amount" value="<?php echo esc_attr( $s['max_order_amount'] ); ?>" step="0.01" min="0" class="small-text" /> <span class="description">0 = no maximum</span></td></tr>
            <tr><th><?php esc_html_e( 'Quantity Cap Per Item', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="quantity_cap" value="<?php echo esc_attr( $s['quantity_cap'] ); ?>" min="1" class="small-text" /></td></tr>
        </table>

        <h2><?php esc_html_e( 'Account & Checkout', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Require Account', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="require_account" value="1" <?php checked( $s['require_account'] ); ?> /> Users must be logged in to use Net Cart</label></td></tr>
            <tr><th><?php esc_html_e( 'Guest Checkout', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="allow_guest_checkout" value="1" <?php checked( $s['allow_guest_checkout'] ); ?> /> Allow checkout without account (overrides above)</label></td></tr>
            <tr><th><?php esc_html_e( 'Merge Duplicates', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="merge_duplicates" value="1" <?php checked( $s['merge_duplicates'] ); ?> /> Merge identical items from the same shop into one line</label></td></tr>
        </table>

        <input type="hidden" name="znc_save_settings" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
