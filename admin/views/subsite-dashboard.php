<?php
defined( 'ABSPATH' ) || exit;
$admin = new ZNC_Subsite_Admin();
$enrollment = $admin->get_enrollment_status();
$prereqs    = $admin->get_prerequisites();
$settings   = get_option( 'znc_subsite_settings', array() );
$network    = get_site_option( 'znc_network_settings', array() );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Shop Dashboard', 'zinckles-net-cart' ); ?></h1>

    <div class="znc-dashboard-grid">
        <div class="znc-card">
            <h3><?php esc_html_e( 'Enrollment Status', 'zinckles-net-cart' ); ?></h3>
            <?php if ( 'active' === $enrollment['status'] ) : ?>
                <span class="znc-badge znc-badge-green" style="font-size:1.2em;">Enrolled &amp; Active</span>
                <p>Since: <?php echo esc_html( $enrollment['enrolled_at'] ?? 'N/A' ); ?></p>
            <?php elseif ( 'pending' === $enrollment['status'] ) : ?>
                <span class="znc-badge znc-badge-yellow" style="font-size:1.2em;">Pending Approval</span>
                <p><?php esc_html_e( 'Your enrollment request is awaiting network admin approval.', 'zinckles-net-cart' ); ?></p>
            <?php else : ?>
                <span class="znc-badge znc-badge-gray" style="font-size:1.2em;">Not Enrolled</span>
                <?php if ( ( $network['enrollment_mode'] ?? 'opt_in' ) !== 'manual' ) : ?>
                <p><button class="button button-primary" id="znc-request-enrollment"><?php esc_html_e( 'Request Enrollment', 'zinckles-net-cart' ); ?></button></p>
                <?php else : ?>
                <p><?php esc_html_e( 'Contact your network administrator to enroll this site.', 'zinckles-net-cart' ); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="znc-card">
            <h3><?php esc_html_e( 'Shop Info', 'zinckles-net-cart' ); ?></h3>
            <ul>
                <li><strong><?php esc_html_e( 'Currency:', 'zinckles-net-cart' ); ?></strong> <?php echo esc_html( get_woocommerce_currency() ); ?></li>
                <li><strong><?php esc_html_e( 'Products:', 'zinckles-net-cart' ); ?></strong> <?php echo esc_html( count( wc_get_products( array( 'limit' => -1, 'return' => 'ids', 'status' => 'publish' ) ) ) ); ?> published</li>
                <li><strong><?php esc_html_e( 'ZCreds:', 'zinckles-net-cart' ); ?></strong> <?php echo ! empty( $settings['accept_zcreds'] ) ? 'Accepted' : 'Not accepted'; ?></li>
            </ul>
        </div>

        <div class="znc-card">
            <h3><?php esc_html_e( 'Prerequisites', 'zinckles-net-cart' ); ?></h3>
            <ul>
                <li><?php echo $prereqs['woocommerce'] ? '&#9989;' : '&#10060;'; ?> WooCommerce</li>
                <li><?php echo $prereqs['mycred'] ? '&#9989;' : '&#9898;'; ?> MyCred (optional)</li>
                <li><?php echo $prereqs['rest_secret'] ? '&#9989;' : '&#10060;'; ?> REST Secret</li>
                <li><?php echo $prereqs['has_products'] ? '&#9989;' : '&#10060;'; ?> Published Products</li>
            </ul>
        </div>
    </div>

    <h2><?php esc_html_e( 'Snapshot Preview', 'zinckles-net-cart' ); ?></h2>
    <p><?php esc_html_e( 'Preview what your current cart would look like as a Net Cart snapshot:', 'zinckles-net-cart' ); ?></p>
    <button class="button" id="znc-preview-snapshot"><?php esc_html_e( 'Generate Snapshot Preview', 'zinckles-net-cart' ); ?></button>
    <pre id="znc-snapshot-output" style="display:none; background:#f5f5f5; padding:15px; margin-top:10px; max-height:400px; overflow:auto;"></pre>
</div>
