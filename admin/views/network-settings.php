<?php defined('ABSPATH') || exit;
$settings = get_site_option('znc_network_settings', []);
$sites = get_sites(['number' => 100]);
$mycred_types = (array)($settings['mycred_types'] ?? []);
$gamipress_types = (array)($settings['gamipress_types'] ?? []);
$tutor_sites = (array)($settings['tutor_sites'] ?? []);
?>
<div class="wrap znc-admin-wrap">
<h1>Net Cart &mdash; Network Settings</h1>
<form id="znc-settings-form">

    <h2>General</h2>
    <table class="form-table">
        <tr><th>Checkout Host</th><td>
            <select name="checkout_host_id">
            <?php foreach ($sites as $site): $d = get_blog_details($site->blog_id); ?>
                <option value="<?php echo $site->blog_id; ?>" <?php selected($settings['checkout_host_id'] ?? '', $site->blog_id); ?>><?php echo esc_html($d->blogname); ?> (ID: <?php echo $site->blog_id; ?>)</option>
            <?php endforeach; ?>
            </select>
            <p class="description">The site where global cart and checkout pages live.</p>
        </td></tr>
        <tr><th>Enrollment Mode</th><td>
            <select name="enrollment_mode">
                <option value="opt-in" <?php selected($settings['enrollment_mode'] ?? 'opt-in', 'opt-in'); ?>>Opt-in (manual enrollment)</option>
                <option value="opt-out" <?php selected($settings['enrollment_mode'] ?? '', 'opt-out'); ?>>Opt-out (all sites auto-enrolled)</option>
            </select>
        </td></tr>
        <tr><th>Base Currency</th><td><input type="text" name="base_currency" value="<?php echo esc_attr($settings['base_currency'] ?? 'USD'); ?>" size="6"></td></tr>
        <tr><th>Cart Expiry</th><td><input type="number" name="cart_expiry_days" value="<?php echo esc_attr($settings['cart_expiry_days'] ?? 30); ?>" min="1"> days</td></tr>
    </table>

    <h2>Cart Options</h2>
    <table class="form-table">
        <tr><th>Clear Local Cart</th><td><label><input type="checkbox" name="clear_local_cart" value="1" <?php checked(!empty($settings['clear_local_cart'])); ?>> Remove items from subsite WooCommerce cart after adding to global cart</label></td></tr>
        <tr><th>Cart Sync</th><td><label><input type="checkbox" name="enable_cart_sync" value="1" <?php checked($settings['enable_cart_sync'] ?? 1); ?>> Override WooCommerce cart count/fragments with global cart data</label></td></tr>
        <tr><th>Admin Bar Cart</th><td><label><input type="checkbox" name="enable_admin_bar_cart" value="1" <?php checked($settings['enable_admin_bar_cart'] ?? 1); ?>> Show Net Cart link in WordPress admin bar</label></td></tr>
    </table>

    <h2>Tutor LMS</h2>
    <table class="form-table">
        <tr><th>Tutor LMS Support</th><td><label><input type="checkbox" name="tutor_lms_support" value="1" <?php checked(!empty($settings['tutor_lms_support'])); ?>> Enable Tutor LMS course integration (auto-enrollment after purchase)</label></td></tr>
        <tr><th>Tutor Sites</th><td>
            <div id="znc-tutor-sites">
            <?php if (empty($tutor_sites)): ?>
                <em>None detected &mdash; click Auto-Detect below</em>
            <?php else: ?>
                <?php foreach ($tutor_sites as $bid):
                    $d = get_blog_details(absint($bid));
                    if ($d) echo '<span class="znc-tag">' . esc_html($d->blogname) . '</span> ';
                endforeach; ?>
            <?php endif; ?>
            </div>
        </td></tr>
    </table>

    <h2>Point Type Detection</h2>
    <p><button type="button" id="znc-detect-points" class="button button-secondary">&#x1F50D; Auto-Detect Point Types</button></p>
    <div id="znc-detected-points"></div>
    <table class="form-table">
        <tr><th>MyCred Types</th><td><div id="znc-mycred-types"><?php
            if (empty($mycred_types)) echo '<em>None detected</em>';
            else foreach ($mycred_types as $t) echo '<label><input type="checkbox" name="mycred_types[]" value="' . esc_attr($t) . '" checked> ' . esc_html($t) . '</label><br>';
        ?></div></td></tr>
        <tr><th>GamiPress Types</th><td><div id="znc-gamipress-types"><?php
            if (empty($gamipress_types)) echo '<em>None detected</em>';
            else foreach ($gamipress_types as $t) echo '<label><input type="checkbox" name="gamipress_types[]" value="' . esc_attr($t) . '" checked> ' . esc_html($t) . '</label><br>';
        ?></div></td></tr>
    </table>

    <h2>Debug</h2>
    <table class="form-table">
        <tr><th>Debug Mode</th><td><label><input type="checkbox" name="debug_mode" value="1" <?php checked(!empty($settings['debug_mode'])); ?>> Enable verbose logging to debug.log</label></td></tr>
    </table>

    <p><button type="submit" class="button button-primary button-large">Save Settings</button></p>
</form>
</div>
