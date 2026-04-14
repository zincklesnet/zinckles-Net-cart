<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'notify_customer' => true, 'notify_shop_admin' => true, 'notify_network_admin' => false,
    'admin_email_override' => '', 'zcred_notice' => true, 'slack_webhook' => '', 'slack_enabled' => false,
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Notifications', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_notifications_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Customer Receipt', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="notify_customer" value="1" <?php checked( $s['notify_customer'] ); ?> /> Send order confirmation email to customer</label></td></tr>
            <tr><th><?php esc_html_e( 'Shop Admin Notification', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="notify_shop_admin" value="1" <?php checked( $s['notify_shop_admin'] ); ?> /> Notify each subsite admin of their child orders</label></td></tr>
            <tr><th><?php esc_html_e( 'Network Admin Summary', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="notify_network_admin" value="1" <?php checked( $s['notify_network_admin'] ); ?> /> Send order summary to network admin</label></td></tr>
            <tr><th><?php esc_html_e( 'Admin Email Override', 'zinckles-net-cart' ); ?></th>
                <td><input type="email" name="admin_email_override" value="<?php echo esc_attr( $s['admin_email_override'] ); ?>" class="regular-text" placeholder="Leave empty for site admin email" /></td></tr>
            <tr><th><?php esc_html_e( 'ZCred Deduction Notice', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="zcred_notice" value="1" <?php checked( $s['zcred_notice'] ); ?> /> Include ZCred deduction details in customer email</label></td></tr>
        </table>
        <h2><?php esc_html_e( 'Slack Integration', 'zinckles-net-cart' ); ?></h2>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Enable Slack', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="slack_enabled" value="1" <?php checked( $s['slack_enabled'] ); ?> /> Post order notifications to Slack</label></td></tr>
            <tr><th><?php esc_html_e( 'Webhook URL', 'zinckles-net-cart' ); ?></th>
                <td><input type="url" name="slack_webhook" value="<?php echo esc_attr( $s['slack_webhook'] ); ?>" class="large-text" placeholder="https://hooks.slack.com/services/..." /></td></tr>
        </table>
        <input type="hidden" name="znc_save_notifications" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
