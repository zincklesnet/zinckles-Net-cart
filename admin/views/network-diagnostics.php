<?php defined('ABSPATH') || exit;
global $wpdb;
$s = get_site_option('znc_network_settings', []);
$sec = get_site_option('znc_security_settings', []);
$enrolled = (array)($s['enrolled_sites'] ?? []);

// Detect plugins via DB for accuracy
$main_prefix = $wpdb->get_blog_prefix(get_main_site_id());
$main_plugins = $wpdb->get_var("SELECT option_value FROM {$main_prefix}options WHERE option_name = 'active_plugins' LIMIT 1");
$has_mycred = $main_plugins && strpos($main_plugins, 'mycred') !== false;
$has_gamipress = $main_plugins && strpos($main_plugins, 'gamipress') !== false;
$has_tutor = $main_plugins && strpos($main_plugins, 'tutor') !== false;

// Also check enrolled sites
foreach ($enrolled as $bid) {
    $p = $wpdb->get_blog_prefix(absint($bid));
    $pl = $wpdb->get_var("SELECT option_value FROM {$p}options WHERE option_name = 'active_plugins' LIMIT 1");
    if ($pl) {
        if (!$has_mycred && strpos($pl, 'mycred') !== false) $has_mycred = true;
        if (!$has_gamipress && strpos($pl, 'gamipress') !== false) $has_gamipress = true;
        if (!$has_tutor && strpos($pl, 'tutor') !== false) $has_tutor = true;
    }
}
?>
<div class="wrap znc-admin-wrap">
<h1>Net Cart &mdash; Diagnostics</h1>
<table class="widefat striped" style="max-width:700px">
<tbody>
    <tr><td><strong>Plugin Version</strong></td><td><?php echo ZNC_VERSION; ?></td></tr>
    <tr><td><strong>PHP Version</strong></td><td><?php echo PHP_VERSION; ?></td></tr>
    <tr><td><strong>WordPress</strong></td><td><?php echo get_bloginfo('version'); ?></td></tr>
    <tr><td><strong>WooCommerce</strong></td><td><?php echo defined('WC_VERSION') ? WC_VERSION : 'Not active on this site'; ?></td></tr>
    <tr><td><strong>Checkout Host</strong></td><td><?php $h = new ZNC_Checkout_Host(); $hi = $h->get_host_info(); echo esc_html($hi['name'] . ' (ID: ' . $hi['blog_id'] . ')'); ?></td></tr>
    <tr><td><strong>Enrolled Sites</strong></td><td><?php echo count($enrolled); ?></td></tr>
    <tr><td><strong>MyCred</strong></td><td><?php echo $has_mycred ? '<span style="color:#10b981">Detected</span>' : 'Not found'; ?></td></tr>
    <tr><td><strong>GamiPress</strong></td><td><?php echo $has_gamipress ? '<span style="color:#10b981">Detected</span>' : 'Not found'; ?></td></tr>
    <tr><td><strong>Tutor LMS</strong></td><td><?php echo $has_tutor ? '<span style="color:#10b981">Detected</span>' : 'Not found'; ?></td></tr>
    <tr><td><strong>HMAC Secret</strong></td><td><?php echo !empty($sec['hmac_secret']) ? '<span style="color:#10b981">Generated</span> (' . esc_html($sec['hmac_generated_at'] ?? 'unknown') . ')' : '<span style="color:#ef4444">Not set</span>'; ?></td></tr>
    <tr><td><strong>Cart Storage</strong></td><td>wp_usermeta (<code>_znc_global_cart</code>)</td></tr>
    <tr><td><strong>Memory Limit</strong></td><td><?php echo ini_get('memory_limit'); ?></td></tr>
    <tr><td><strong>Memory Usage</strong></td><td><?php echo round(memory_get_usage(true)/1048576, 1); ?> MB</td></tr>
    <tr><td><strong>Debug Mode</strong></td><td><?php echo !empty($s['debug_mode']) ? 'Enabled' : 'Disabled'; ?></td></tr>
    <tr><td><strong>Cart Sync</strong></td><td><?php echo ($s['enable_cart_sync'] ?? 1) ? 'Enabled' : 'Disabled'; ?></td></tr>
    <tr><td><strong>Admin Bar Cart</strong></td><td><?php echo ($s['enable_admin_bar_cart'] ?? 1) ? 'Enabled' : 'Disabled'; ?></td></tr>
    <tr><td><strong>Tutor LMS Support</strong></td><td><?php echo !empty($s['tutor_lms_support']) ? 'Enabled' : 'Disabled'; ?></td></tr>
</tbody>
</table>

<h2 style="margin-top:20px">Current User Cart</h2>
<?php
$gc = new ZNC_Global_Cart();
$cart = $gc->get_cart();
$count = $gc->get_item_count();
?>
<p>Items in global cart: <strong><?php echo $count; ?></strong></p>
<?php if (!empty($cart)): ?>
<table class="widefat striped" style="max-width:700px">
<thead><tr><th>Key</th><th>Blog</th><th>Product</th><th>Qty</th><th>Added</th></tr></thead>
<tbody>
<?php foreach ($cart as $key => $item):
    $blog = get_blog_details($item['blog_id']);
?>
<tr>
    <td><code><?php echo esc_html($key); ?></code></td>
    <td><?php echo $blog ? esc_html($blog->blogname) : $item['blog_id']; ?></td>
    <td><?php echo esc_html($item['product_id']); ?><?php echo $item['variation_id'] ? ' (var: ' . $item['variation_id'] . ')' : ''; ?></td>
    <td><?php echo esc_html($item['quantity']); ?></td>
    <td><?php echo isset($item['added_at']) ? esc_html(human_time_diff($item['added_at']) . ' ago') : '—'; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
