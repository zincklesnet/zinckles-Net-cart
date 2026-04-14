<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'shipping_mode' => 'inherit', 'shipping_flat_rate' => 0, 'shipping_free_threshold' => 0,
    'shipping_note' => '', 'tax_on_shipping' => true,
    'tax_mode' => 'inherit', 'tax_rate' => 0, 'tax_label' => 'Tax', 'tax_exempt' => false,
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Shipping & Tax', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_subsite_nonce' ); ?>
        <h2><?php esc_html_e( 'Shipping', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Shipping Mode', 'zinckles-net-cart' ); ?></th>
                <td><select name="shipping_mode">
                    <option value="inherit" <?php selected( $s['shipping_mode'], 'inherit' ); ?>>Inherit from WooCommerce</option>
                    <option value="flat" <?php selected( $s['shipping_mode'], 'flat' ); ?>>Flat Rate</option>
                    <option value="free" <?php selected( $s['shipping_mode'], 'free' ); ?>>Free Shipping</option>
                    <option value="disabled" <?php selected( $s['shipping_mode'], 'disabled' ); ?>>Disabled (no shipping)</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Flat Rate Amount', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="shipping_flat_rate" value="<?php echo esc_attr( $s['shipping_flat_rate'] ); ?>" step="0.01" min="0" class="small-text" /></td></tr>
            <tr><th><?php esc_html_e( 'Free Shipping Threshold', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="shipping_free_threshold" value="<?php echo esc_attr( $s['shipping_free_threshold'] ); ?>" step="0.01" min="0" class="small-text" /> <span class="description">0 = no free shipping threshold</span></td></tr>
            <tr><th><?php esc_html_e( 'Shipping Note', 'zinckles-net-cart' ); ?></th>
                <td><input type="text" name="shipping_note" value="<?php echo esc_attr( $s['shipping_note'] ); ?>" class="large-text" placeholder="e.g. Ships from Portland, OR" /></td></tr>
            <tr><th><?php esc_html_e( 'Tax on Shipping', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="tax_on_shipping" value="1" <?php checked( $s['tax_on_shipping'] ); ?> /> Apply tax to shipping charges</label></td></tr>
        </table>
        <h2><?php esc_html_e( 'Tax', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Tax Mode', 'zinckles-net-cart' ); ?></th>
                <td><select name="tax_mode">
                    <option value="inherit" <?php selected( $s['tax_mode'], 'inherit' ); ?>>Inherit from WooCommerce</option>
                    <option value="override" <?php selected( $s['tax_mode'], 'override' ); ?>>Override Rate</option>
                    <option value="exempt" <?php selected( $s['tax_mode'], 'exempt' ); ?>>Tax Exempt</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Override Rate', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="tax_rate" value="<?php echo esc_attr( $s['tax_rate'] ); ?>" step="0.01" min="0" class="small-text" />%</td></tr>
            <tr><th><?php esc_html_e( 'Tax Label', 'zinckles-net-cart' ); ?></th>
                <td><input type="text" name="tax_label" value="<?php echo esc_attr( $s['tax_label'] ); ?>" class="regular-text" /></td></tr>
        </table>
        <input type="hidden" name="znc_save_subsite" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
