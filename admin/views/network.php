<?php
defined( 'ABSPATH' ) || exit;

$sites = get_sites( [ 'number' => 100 ] );
$secret_set = (bool) get_site_option( 'znc_rest_secret' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Zinckles Net Cart — Network Overview', 'zinckles-net-cart' ); ?></h1>

    <h2>Authentication</h2>
    <table class="widefat striped" style="max-width:500px;">
        <tr>
            <td><strong>REST Secret</strong></td>
            <td><?php echo $secret_set ? '✅ Configured' : '❌ Missing — re-activate the plugin network-wide'; ?></td>
        </tr>
    </table>

    <h2 style="margin-top:20px;">Sites in Network</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Blog ID</th>
                <th>Domain / Path</th>
                <th>Main Site</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $sites as $site ) : ?>
            <tr>
                <td><?php echo esc_html( $site->blog_id ); ?></td>
                <td><?php echo esc_html( $site->domain . $site->path ); ?></td>
                <td><?php echo $site->blog_id == get_main_site_id() ? '✅' : '—'; ?></td>
                <td>
                    <?php
                    if ( $site->deleted ) echo '🗑 Deleted';
                    elseif ( $site->archived ) echo '📦 Archived';
                    elseif ( $site->spam ) echo '🚫 Spam';
                    else echo '✅ Active';
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
