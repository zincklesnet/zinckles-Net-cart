<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'rate_source' => 'manual', 'rate_api_provider' => '', 'rate_api_key' => '',
    'rate_refresh_hours' => 24, 'exchange_rates' => array(),
    'zcred_checkout_enabled' => true, 'zcred_input_style' => 'slider',
    'zcred_show_balance' => true, 'zcred_earn_enabled' => false, 'zcred_earn_rate' => 1,
    'zcred_excluded_sites' => array(),
) );
$network = get_site_option( 'znc_network_settings', array() );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Currency & ZCreds', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_currency_nonce' ); ?>

        <h2><?php esc_html_e( 'Exchange Rates', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Rate Source', 'zinckles-net-cart' ); ?></th>
                <td><select name="rate_source" id="znc-rate-source">
                    <option value="manual" <?php selected( $s['rate_source'], 'manual' ); ?>>Manual (enter rates below)</option>
                    <option value="api" <?php selected( $s['rate_source'], 'api' ); ?>>API (auto-refresh)</option>
                </select></td></tr>
            <tr class="znc-api-row"><th><?php esc_html_e( 'API Provider', 'zinckles-net-cart' ); ?></th>
                <td><select name="rate_api_provider">
                    <option value="exchangerate_api" <?php selected( $s['rate_api_provider'], 'exchangerate_api' ); ?>>ExchangeRate-API</option>
                    <option value="open_exchange_rates" <?php selected( $s['rate_api_provider'], 'open_exchange_rates' ); ?>>Open Exchange Rates</option>
                    <option value="fixer" <?php selected( $s['rate_api_provider'], 'fixer' ); ?>>Fixer.io</option>
                </select></td></tr>
            <tr class="znc-api-row"><th><?php esc_html_e( 'API Key', 'zinckles-net-cart' ); ?></th>
                <td><input type="text" name="rate_api_key" value="<?php echo esc_attr( $s['rate_api_key'] ); ?>" class="regular-text" /></td></tr>
            <tr class="znc-api-row"><th><?php esc_html_e( 'Refresh Interval', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="rate_refresh_hours" value="<?php echo esc_attr( $s['rate_refresh_hours'] ); ?>" min="1" class="small-text" /> hours</td></tr>
            <tr><th><?php esc_html_e( 'Manual Rates (JSON)', 'zinckles-net-cart' ); ?></th>
                <td><textarea name="exchange_rates" rows="8" class="large-text code" placeholder='{"USD_EUR": 0.92, "USD_GBP": 0.79}'><?php echo esc_textarea( wp_json_encode( $s['exchange_rates'], JSON_PRETTY_PRINT ) ); ?></textarea>
                <p class="description">Format: <code>{"FROM_TO": rate}</code> — e.g. <code>{"USD_CAD": 1.36, "EUR_USD": 1.09}</code></p></td></tr>
        </table>

        <h2><?php esc_html_e( 'ZCreds at Checkout', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Enable ZCred Payments', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="zcred_checkout_enabled" value="1" <?php checked( $s['zcred_checkout_enabled'] ); ?> /> Allow customers to pay with ZCreds at checkout</label></td></tr>
            <tr><th><?php esc_html_e( 'Input Style', 'zinckles-net-cart' ); ?></th>
                <td><select name="zcred_input_style">
                    <option value="slider" <?php selected( $s['zcred_input_style'], 'slider' ); ?>>Slider</option>
                    <option value="numeric" <?php selected( $s['zcred_input_style'], 'numeric' ); ?>>Numeric Input</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Show Balance', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="zcred_show_balance" value="1" <?php checked( $s['zcred_show_balance'] ); ?> /> Display ZCred balance in cart and checkout</label></td></tr>
            <tr><th><?php esc_html_e( 'Earn on Purchase', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="zcred_earn_enabled" value="1" <?php checked( $s['zcred_earn_enabled'] ); ?> /> Award ZCreds for completed purchases</label></td></tr>
            <tr><th><?php esc_html_e( 'Earn Rate', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="zcred_earn_rate" value="<?php echo esc_attr( $s['zcred_earn_rate'] ); ?>" step="0.1" min="0" class="small-text" /> ZCreds per $1 spent</td></tr>
        </table>

        <input type="hidden" name="znc_save_currency" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
