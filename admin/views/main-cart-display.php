<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'layout_style' => 'grouped', 'show_shop_badges' => true, 'show_origin_links' => true,
    'show_currency_breakdown' => true, 'show_zcred_widget' => true,
    'empty_cart_message' => 'Your Net Cart is empty. Browse our shops to find something you love!',
    'header_icon_style' => 'badge', 'conversion_display' => 'both', 'rounding_precision' => 2,
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Cart Display', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_cart_display_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Layout Style', 'zinckles-net-cart' ); ?></th>
                <td><select name="layout_style">
                    <option value="grouped" <?php selected( $s['layout_style'], 'grouped' ); ?>>Grouped by Shop</option>
                    <option value="tabbed" <?php selected( $s['layout_style'], 'tabbed' ); ?>>Tabbed (one tab per shop)</option>
                    <option value="flat" <?php selected( $s['layout_style'], 'flat' ); ?>>Flat List</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Shop Badges', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="show_shop_badges" value="1" <?php checked( $s['show_shop_badges'] ); ?> /> Show colored shop badges next to items</label></td></tr>
            <tr><th><?php esc_html_e( 'Origin Links', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="show_origin_links" value="1" <?php checked( $s['show_origin_links'] ); ?> /> Link items back to their shop product page</label></td></tr>
            <tr><th><?php esc_html_e( 'Currency Breakdown', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="show_currency_breakdown" value="1" <?php checked( $s['show_currency_breakdown'] ); ?> /> Show per-currency subtotals in mixed-currency carts</label></td></tr>
            <tr><th><?php esc_html_e( 'ZCred Widget', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="show_zcred_widget" value="1" <?php checked( $s['show_zcred_widget'] ); ?> /> Show ZCred balance and apply widget in cart</label></td></tr>
            <tr><th><?php esc_html_e( 'Conversion Display', 'zinckles-net-cart' ); ?></th>
                <td><select name="conversion_display">
                    <option value="original" <?php selected( $s['conversion_display'], 'original' ); ?>>Original currency only</option>
                    <option value="converted" <?php selected( $s['conversion_display'], 'converted' ); ?>>Converted to base currency only</option>
                    <option value="both" <?php selected( $s['conversion_display'], 'both' ); ?>>Both (original + converted)</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Rounding Precision', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="rounding_precision" value="<?php echo esc_attr( $s['rounding_precision'] ); ?>" min="0" max="4" class="small-text" /> decimal places</td></tr>
            <tr><th><?php esc_html_e( 'Empty Cart Message', 'zinckles-net-cart' ); ?></th>
                <td><textarea name="empty_cart_message" rows="3" class="large-text"><?php echo esc_textarea( $s['empty_cart_message'] ); ?></textarea></td></tr>
        </table>
        <input type="hidden" name="znc_save_cart_display" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
