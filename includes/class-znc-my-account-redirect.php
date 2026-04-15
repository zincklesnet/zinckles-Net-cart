<?php
defined('ABSPATH') || exit;
class ZNC_My_Account_Redirect {
    private $host;
    private $enrolled = null;
    public function __construct(ZNC_Checkout_Host $host) { $this->host = $host; }
    public function init() {
        if ($this->host->is_current_site_host()) return;
        if (!$this->is_enrolled()) return;
        add_action('template_redirect', [$this, 'redirect_wc_pages'], 5);
        add_filter('woocommerce_get_myaccount_page_permalink', [$this, 'f_account']);
        add_filter('woocommerce_get_cart_url', [$this, 'f_cart']);
        add_filter('woocommerce_get_checkout_url', [$this, 'f_checkout']);
        add_action('woocommerce_before_shop_loop', [$this, 'cart_notice']);
        add_action('woocommerce_before_single_product', [$this, 'cart_notice']);
    }
    public function redirect_wc_pages() {
        if (!function_exists('is_account_page')) return;
        $r = '';
        if (is_account_page()) $r = $this->host->get_account_url();
        elseif (is_cart()) $r = $this->host->get_cart_url();
        elseif (is_checkout() && !is_wc_endpoint_url('order-received')) $r = $this->host->get_checkout_url();
        if ($r) { wp_redirect(add_query_arg('znc_from', get_current_blog_id(), $r), 302); exit; }
    }
    public function f_account($u) { return $this->host->get_account_url(); }
    public function f_cart($u) { return $this->host->get_cart_url(); }
    public function f_checkout($u) { return $this->host->get_checkout_url(); }
    public function cart_notice() {
        static $s = false; if ($s) return; $s = true;
        $cu = esc_url($this->host->get_cart_url()); $hi = $this->host->get_host_info();
        echo '<div class="znc-cart-redirect-notice" style="background:linear-gradient(135deg,#7c3aed15,#4f46e515);border:1px solid #7c3aed40;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:14px">';
        printf('&#x1F6D2; Items you add go to your <a href="%s"><strong>Global Net Cart</strong></a> on %s.', $cu, esc_html($hi['name']));
        echo '</div>';
    }
    private function is_enrolled() {
        if (null !== $this->enrolled) return $this->enrolled;
        $s = get_site_option('znc_network_settings', []); $bid = get_current_blog_id();
        $bl = (array)($s['blocked_sites'] ?? []);
        if (in_array($bid, $bl, true)) { $this->enrolled = false; return false; }
        $m = $s['enrollment_mode'] ?? 'opt-in';
        if ($m === 'opt-out') { $this->enrolled = true; return true; }
        $e = (array)($s['enrolled_sites'] ?? []);
        $this->enrolled = in_array($bid, $e, true);
        return $this->enrolled;
    }
}
