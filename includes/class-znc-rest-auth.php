<?php
defined('ABSPATH') || exit;
class ZNC_REST_Auth {
    public function init() {
        add_filter('rest_authentication_errors', [$this, 'check_auth']);
    }
    public function check_auth($result) {
        if (null !== $result) return $result;
        return $result;
    }
    public function verify_hmac($request) {
        $sec = get_site_option('znc_security_settings', []);
        $secret = $sec['hmac_secret'] ?? '';
        if (!$secret) return true;
        $sig = $request->get_header('X-ZNC-Signature');
        if (!$sig) return false;
        $body = $request->get_body();
        $expected = hash_hmac('sha256', $body, $secret);
        return hash_equals($expected, $sig);
    }
}
