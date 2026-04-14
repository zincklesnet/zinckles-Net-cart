<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'cache_shop_settings' => true, 'cache_ttl_minutes' => 60,
    'async_cart_push' => true, 'parallel_validation' => true,
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Performance', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_performance_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Cache Shop Settings', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="cache_shop_settings" value="1" <?php checked( $s['cache_shop_settings'] ); ?> /> Cache subsite settings to reduce cross-site requests</label></td></tr>
            <tr><th><?php esc_html_e( 'Cache TTL', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="cache_ttl_minutes" value="<?php echo esc_attr( $s['cache_ttl_minutes'] ); ?>" min="1" class="small-text" /> minutes</td></tr>
            <tr><th><?php esc_html_e( 'Async Cart Push', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="async_cart_push" value="1" <?php checked( $s['async_cart_push'] ); ?> /> Push cart changes to main site asynchronously (non-blocking)</label></td></tr>
            <tr><th><?php esc_html_e( 'Parallel Validation', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="parallel_validation" value="1" <?php checked( $s['parallel_validation'] ); ?> /> Validate multiple subsites simultaneously at checkout</label></td></tr>
        </table>
        <input type="hidden" name="znc_save_performance" value="1" />
        <?php submit_button(); ?>
        <hr />
        <h2><?php esc_html_e( 'Cache Management', 'zinckles-net-cart' ); ?></h2>
        <button type="button" class="button button-secondary" id="znc-flush-cache"><?php esc_html_e( 'Flush All Net Cart Caches', 'zinckles-net-cart' ); ?></button>
        <span id="znc-flush-result"></span>
    </form>
</div>
