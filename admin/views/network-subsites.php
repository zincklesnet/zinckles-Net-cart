<?php defined('ABSPATH') || exit;
global $wpdb;
$settings = get_site_option('znc_network_settings', []);
$enrolled = (array)($settings['enrolled_sites'] ?? []);
$sites = get_sites(['number' => 100]);
?>
<div class="wrap znc-admin-wrap">
<h1>Net Cart &mdash; Enrolled Subsites</h1>
<p class="description">Enroll subsites to include their WooCommerce products in the global cart.</p>
<table class="wp-list-table widefat striped">
<thead><tr><th>ID</th><th>Site</th><th>URL</th><th>WC</th><th>Tutor</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($sites as $site):
    $d = get_blog_details($site->blog_id);
    $is_enrolled = in_array((int)$site->blog_id, array_map('intval', $enrolled), true);
    $prefix = $wpdb->get_blog_prefix($site->blog_id);
    $plugins = $wpdb->get_var("SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins' LIMIT 1");
    $has_wc = $plugins && strpos($plugins, 'woocommerce') !== false;
    $has_tutor = $plugins && strpos($plugins, 'tutor') !== false;
?>
<tr data-blog-id="<?php echo $site->blog_id; ?>">
    <td><?php echo $site->blog_id; ?></td>
    <td><strong><?php echo esc_html($d->blogname); ?></strong></td>
    <td><a href="<?php echo esc_url($d->siteurl); ?>" target="_blank"><?php echo esc_html($d->siteurl); ?></a></td>
    <td><?php echo $has_wc ? '<span style="color:#10b981">&#10003;</span>' : '<span style="color:#999">&mdash;</span>'; ?></td>
    <td><?php echo $has_tutor ? '<span style="color:#10b981">&#10003;</span>' : '<span style="color:#999">&mdash;</span>'; ?></td>
    <td><span class="znc-status-badge <?php echo $is_enrolled ? 'znc-enrolled' : 'znc-not-enrolled'; ?>"><?php echo $is_enrolled ? 'Enrolled' : 'Not Enrolled'; ?></span></td>
    <td>
        <?php if ($is_enrolled): ?>
            <button class="button znc-remove-btn" data-blog-id="<?php echo $site->blog_id; ?>">Remove</button>
            <button class="button znc-test-btn" data-blog-id="<?php echo $site->blog_id; ?>">Test</button>
        <?php else: ?>
            <button class="button button-primary znc-enroll-btn" data-blog-id="<?php echo $site->blog_id; ?>">Enroll</button>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
