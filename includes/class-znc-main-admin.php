<?php
defined('ABSPATH') || exit;
class ZNC_Main_Admin {
    private $global_cart;
    public function __construct(ZNC_Global_Cart $gc) { $this->global_cart = $gc; }
    public function init() {
        add_action('admin_menu', [$this, 'add_menu']);
    }
    public function add_menu() {
        add_menu_page('Net Cart', 'Net Cart', 'manage_woocommerce', 'znc-main-admin', [$this, 'render'], 'dashicons-cart', 56);
    }
    public function render() {
        $s = get_site_option('znc_network_settings', []);
        $host = new ZNC_Checkout_Host();
        ?>
        <div class="wrap">
            <h1>Zinckles Net Cart</h1>
            <div class="znc-dashboard-cards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:20px">
                <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;border-radius:8px">
                    <h3>Quick Links</h3>
                    <ul style="list-style:disc;margin-left:20px">
                        <li><a href="<?php echo esc_url(network_admin_url('admin.php?page=znc-settings')); ?>">Network Settings</a></li>
                        <li><a href="<?php echo esc_url(network_admin_url('admin.php?page=znc-subsites')); ?>">Enrolled Subsites</a></li>
                        <li><a href="<?php echo esc_url(network_admin_url('admin.php?page=znc-security')); ?>">Security Settings</a></li>
                        <li><a href="<?php echo esc_url(network_admin_url('admin.php?page=znc-diagnostics')); ?>">Diagnostics</a></li>
                    </ul>
                </div>
                <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;border-radius:8px">
                    <h3>Cart Pages</h3>
                    <ul style="list-style:disc;margin-left:20px">
                        <li><a href="<?php echo esc_url($host->get_cart_url()); ?>">Global Cart</a></li>
                        <li><a href="<?php echo esc_url($host->get_checkout_url()); ?>">Checkout</a></li>
                    </ul>
                </div>
                <div class="card" style="padding:20px;background:#fff;border:1px solid #ccd0d4;border-radius:8px">
                    <h3>Status</h3>
                    <p>Version: <?php echo ZNC_VERSION; ?></p>
                    <p>Host: <?php echo esc_html($host->get_host_info()['name']); ?></p>
                    <p>Enrolled: <?php echo count((array)($s['enrolled_sites'] ?? [])); ?> sites</p>
                </div>
            </div>
        </div>
        <?php
    }
}
