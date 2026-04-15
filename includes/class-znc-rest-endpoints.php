<?php
defined('ABSPATH') || exit;
class ZNC_REST_Endpoints {
    private $auth;
    public function __construct(ZNC_REST_Auth $auth) { $this->auth = $auth; }
    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    public function register_routes() {
        register_rest_route('znc/v1', '/cart', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cart'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);
        register_rest_route('znc/v1', '/cart/add', [
            'methods' => 'POST',
            'callback' => [$this, 'add_to_cart'],
            'permission_callback' => function() { return is_user_logged_in(); },
        ]);
    }
    public function get_cart($request) {
        $gc = new ZNC_Global_Cart();
        return rest_ensure_response($gc->get_cart());
    }
    public function add_to_cart($request) {
        $gc = new ZNC_Global_Cart();
        $uid = get_current_user_id();
        $bid = absint($request->get_param('blog_id'));
        $pid = absint($request->get_param('product_id'));
        $qty = absint($request->get_param('quantity') ?: 1);
        $vid = absint($request->get_param('variation_id') ?: 0);
        $gc->add_item($uid, $bid, $pid, $qty, $vid);
        return rest_ensure_response(['success'=>true,'count'=>$gc->get_item_count($uid)]);
    }
}
