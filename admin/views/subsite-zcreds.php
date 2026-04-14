<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'accept_zcreds' => true, 'zcred_max_percent' => 100,
    'zcred_earn_multiplier' => 1.0, 'zcred_bonus_cats' => array(),
    'zcred_exclude_products' => array(),
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — ZCreds Settings', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_subsite_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Accept ZCreds', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="accept_zcreds" value="1" <?php checked( $s['accept_zcreds'] ); ?> /> Allow ZCred payments for products from this shop</label></td></tr>
            <tr><th><?php esc_html_e( 'Max % Override', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="zcred_max_percent" value="<?php echo esc_attr( $s['zcred_max_percent'] ); ?>" min="0" max="100" class="small-text" />%
                <p class="description">Override the network-wide max ZCred percentage for this shop</p></td></tr>
            <tr><th><?php esc_html_e( 'Earn Multiplier', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="zcred_earn_multiplier" value="<?php echo esc_attr( $s['zcred_earn_multiplier'] ); ?>" step="0.1" min="0" max="10" class="small-text" />&times;
                <p class="description">Multiply ZCred earning rate (1.0 = standard, 2.0 = double rewards)</p></td></tr>
        </table>
        <input type="hidden" name="znc_save_subsite" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
