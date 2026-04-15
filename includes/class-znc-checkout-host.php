<?php
defined('ABSPATH') || exit;
class ZNC_Checkout_Host {
    private $host_id = null;
    private $urls = null;

    public function get_host_id() {
        if (null !== $this->host_id) return $this->host_id;
        $s = get_site_option('znc_network_settings', []);
        $c = isset($s['checkout_host_id']) ? absint($s['checkout_host_id']) : 0;
        $this->host_id = ($c > 0 && get_blog_details($c)) ? $c : get_main_site_id();
        return $this->host_id;
    }
    public function is_current_site_host() { return get_current_blog_id() === $this->get_host_id(); }
    public function is_host($bid) { return absint($bid) === $this->get_host_id(); }
    public function get_host_url() { return get_home_url($this->get_host_id()); }

    private function resolve_urls() {
        if (null !== $this->urls) return $this->urls;
        $ck = 'znc_host_urls_' . $this->get_host_id();
        $c = get_site_transient($ck);
        if (is_array($c) && !empty($c['cart'])) { $this->urls = $c; return $this->urls; }
        $hid = $this->get_host_id(); $cur = get_current_blog_id(); $sw = ($cur !== $hid);
        if ($sw) switch_to_blog($hid);
        $u = [];
        $cp = get_option('znc_cart_page_id', 0);
        if ($cp) { $u['cart'] = get_permalink($cp); }
        else {
            $pp = get_posts(['post_type'=>'page','post_status'=>'publish','s'=>'[znc_global_cart]','numberposts'=>1,'fields'=>'ids']);
            if (!empty($pp)) { $u['cart'] = get_permalink($pp[0]); update_option('znc_cart_page_id', $pp[0]); }
            else { $u['cart'] = home_url('/cart-g/'); }
        }
        $co = get_option('znc_checkout_page_id', 0);
        if ($co) { $u['checkout'] = get_permalink($co); }
        else {
            $pp = get_posts(['post_type'=>'page','post_status'=>'publish','s'=>'[znc_checkout]','numberposts'=>1,'fields'=>'ids']);
            if (!empty($pp)) { $u['checkout'] = get_permalink($pp[0]); update_option('znc_checkout_page_id', $pp[0]); }
            else { $u['checkout'] = home_url('/checkout-g/'); }
        }
        $u['account'] = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
        $u['orders'] = trailingslashit($u['account']) . 'orders/';
        if ($sw) restore_current_blog();
        $this->urls = $u;
        set_site_transient($ck, $u, HOUR_IN_SECONDS);
        return $this->urls;
    }
    public function get_cart_url() { $u = $this->resolve_urls(); return $u['cart']; }
    public function get_checkout_url() { $u = $this->resolve_urls(); return $u['checkout']; }
    public function get_account_url() { $u = $this->resolve_urls(); return $u['account']; }
    public function get_orders_url() { $u = $this->resolve_urls(); return $u['orders']; }
    public function flush_url_cache() { delete_site_transient('znc_host_urls_' . $this->get_host_id()); $this->urls = null; }
    public function get_enrolled_shop_ids() {
        $s = get_site_option('znc_network_settings', []);
        $e = (array)($s['enrolled_sites'] ?? []); $b = (array)($s['blocked_sites'] ?? []); $h = $this->get_host_id();
        return array_values(array_filter($e, function($id) use ($b,$h) { return absint($id) !== $h && !in_array(absint($id),$b,true); }));
    }
    public function get_host_info() {
        $h = $this->get_host_id(); $d = get_blog_details($h);
        return ['blog_id'=>$h,'name'=>$d?$d->blogname:'Main','url'=>$d?$d->siteurl:network_home_url(),'is_main'=>$h===get_main_site_id()];
    }
}
