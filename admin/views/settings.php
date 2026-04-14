<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap">
    <h1><?php esc_html_e( 'Zinckles Net Cart — Settings', 'zinckles-net-cart' ); ?></h1>

    <form method="post" action="options.php">
        <?php
        settings_fields( 'znc_settings' );
        do_settings_sections( 'znc-settings' );
        submit_button();
        ?>
    </form>

    <hr />

    <h2><?php esc_html_e( 'System Status', 'zinckles-net-cart' ); ?></h2>
    <table class="widefat striped" style="max-width:600px;">
        <tbody>
            <tr>
                <td><strong>Plugin Version</strong></td>
                <td><?php echo esc_html( ZNC_VERSION ); ?></td>
            </tr>
            <tr>
                <td><strong>DB Version</strong></td>
                <td><?php echo esc_html( get_option( 'znc_db_version', 'n/a' ) ); ?></td>
            </tr>
            <tr>
                <td><strong>MyCred Detected</strong></td>
                <td><?php echo esc_html( get_option( 'znc_mycred_detected', 'n/a' ) ); ?></td>
            </tr>
            <tr>
                <td><strong>REST Secret Set</strong></td>
                <td><?php echo get_site_option( 'znc_rest_secret' ) ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>Main Site</strong></td>
                <td><?php echo is_main_site() ? '✅ Yes' : '❌ No'; ?></td>
            </tr>
            <tr>
                <td><strong>WooCommerce</strong></td>
                <td><?php echo class_exists( 'WooCommerce' ) ? '✅ ' . WC()->version : '❌ Not found'; ?></td>
            </tr>
            <tr>
                <td><strong>Inventory Queue</strong></td>
                <td><?php echo count( get_option( 'znc_inventory_queue', [] ) ); ?> pending jobs</td>
            </tr>
        </tbody>
    </table>
</div>
