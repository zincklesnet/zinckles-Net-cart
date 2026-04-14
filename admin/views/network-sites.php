<?php
/**
 * Network Admin — Enrolled Subsites
 *
 * v1.2.0 FIXES:
 *  - Buttons use data-blog-id and correct CSS classes for JS binding
 *  - Status badge updates instantly on AJAX success
 *  - Main site excluded from enrollment list
 */
defined( 'ABSPATH' ) || exit;

$main_site_id = get_main_site_id();
?>
<div class="wrap">
    <h1><?php _e( 'Net Cart — Enrolled Subsites', 'znc' ); ?></h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( 'Enrollment settings saved.', 'znc' ); ?></p>
        </div>
    <?php endif; ?>

    <p class="description">
        <?php printf(
            __( 'Enrollment mode: <strong>%s</strong>. Only subsites with WooCommerce active can participate.', 'znc' ),
            esc_html( $settings['enrollment_mode'] )
        ); ?>
    </p>

    <table class="wp-list-table widefat striped" id="znc-sites-table">
        <thead>
            <tr>
                <th><?php _e( 'Site', 'znc' ); ?></th>
                <th><?php _e( 'URL', 'znc' ); ?></th>
                <th><?php _e( 'WooCommerce', 'znc' ); ?></th>
                <th><?php _e( 'MyCred', 'znc' ); ?></th>
                <th><?php _e( 'Currency', 'znc' ); ?></th>
                <th><?php _e( 'Products', 'znc' ); ?></th>
                <th><?php _e( 'Status', 'znc' ); ?></th>
                <th><?php _e( 'Actions', 'znc' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $all_sites as $site ) :
                // Skip the main site — it's the cart host, not a shop.
                if ( (int) $site->blog_id === (int) $main_site_id ) {
                    continue;
                }

                $blog_id    = (int) $site->blog_id;
                $is_enrolled = ZNC_Network_Admin::is_site_enrolled( $blog_id );

                switch_to_blog( $blog_id );
                $site_name  = get_bloginfo( 'name' );
                $site_url   = home_url();
                $has_wc     = class_exists( 'WooCommerce' );
                $has_mycred = function_exists( 'mycred' );
                $currency   = function_exists( 'get_woocommerce_currency' )
                    ? get_woocommerce_currency() : '—';
                $products   = $has_wc && function_exists( 'wc_get_products' )
                    ? count( wc_get_products( array( 'status' => 'publish', 'limit' => -1, 'return' => 'ids' ) ) )
                    : 0;
                restore_current_blog();
            ?>
                <tr data-blog-id="<?php echo $blog_id; ?>">
                    <td>
                        <strong><?php echo esc_html( $site_name ); ?></strong>
                        <br><small>ID: <?php echo $blog_id; ?></small>
                    </td>
                    <td><a href="<?php echo esc_url( $site_url ); ?>" target="_blank"><?php echo esc_html( $site_url ); ?></a></td>
                    <td>
                        <?php if ( $has_wc ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color:#4caf50" title="Active"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-dismiss" style="color:#f44336" title="Not active"></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $has_mycred ) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color:#4caf50" title="Active"></span>
                        <?php else : ?>
                            <span class="dashicons dashicons-minus" style="color:#999" title="Not installed"></span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo esc_html( $currency ); ?></code></td>
                    <td><?php echo $products; ?></td>
                    <td>
                        <?php if ( $is_enrolled ) : ?>
                            <span class="znc-status-badge znc-status-enrolled">Enrolled</span>
                        <?php else : ?>
                            <span class="znc-status-badge znc-status-not-enrolled">Not Enrolled</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( $has_wc ) : ?>
                            <button type="button"
                                class="button znc-toggle-enroll <?php echo $is_enrolled ? 'znc-enrolled button-secondary' : 'button-primary'; ?>"
                                data-blog-id="<?php echo $blog_id; ?>">
                                <?php echo $is_enrolled ? __( 'Remove', 'znc' ) : __( 'Enroll', 'znc' ); ?>
                            </button>
                            <button type="button"
                                class="button button-link znc-test-connection"
                                data-blog-id="<?php echo $blog_id; ?>">
                                <?php _e( 'Test', 'znc' ); ?>
                            </button>
                            <span class="znc-connection-result"></span>
                        <?php else : ?>
                            <em><?php _e( 'WooCommerce required', 'znc' ); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.znc-status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
}
.znc-status-enrolled {
    background: #e8f5e9;
    color: #2e7d32;
}
.znc-status-not-enrolled {
    background: #f3f4f6;
    color: #6b7280;
}
.znc-toggle-enroll.znc-processing {
    opacity: 0.6;
    cursor: wait;
}
.znc-connection-result {
    display: inline-block;
    margin-left: 8px;
    vertical-align: middle;
}
#znc-sites-table td {
    vertical-align: middle;
}
</style>
