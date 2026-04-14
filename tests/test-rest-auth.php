<?php
class Test_ZNC_REST_Auth extends WP_UnitTestCase {
    private $auth;

    public function setUp(): void {
        parent::setUp();
        $this->auth = new ZNC_REST_Auth();
        update_site_option('znc_rest_secret', 'test_secret_key_64_characters_long_for_hmac_sha256_signing_ok!!!!');
        $this->auth->init();
    }

    public function test_sign_adds_headers() {
        $args = array('body' => '{"test":true}');
        $signed = ZNC_REST_Auth::sign($args, '/znc/v1/test');
        $this->assertArrayHasKey('headers', $signed);
        $this->assertArrayHasKey('X-ZNC-Timestamp', $signed['headers']);
        $this->assertArrayHasKey('X-ZNC-Nonce', $signed['headers']);
        $this->assertArrayHasKey('X-ZNC-Signature', $signed['headers']);
    }

    public function test_signature_is_64_hex_chars() {
        $args = array('body' => '{}');
        $signed = ZNC_REST_Auth::sign($args, '/znc/v1/test');
        $this->assertEquals(64, strlen($signed['headers']['X-ZNC-Signature']));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signed['headers']['X-ZNC-Signature']);
    }

    public function test_timestamp_is_current() {
        $args = array('body' => '{}');
        $signed = ZNC_REST_Auth::sign($args, '/znc/v1/test');
        $ts = intval($signed['headers']['X-ZNC-Timestamp']);
        $this->assertLessThanOrEqual(2, abs(time() - $ts));
    }

    public function test_nonce_is_16_chars() {
        $args = array('body' => '{}');
        $signed = ZNC_REST_Auth::sign($args, '/znc/v1/test');
        $this->assertEquals(16, strlen($signed['headers']['X-ZNC-Nonce']));
    }

    public function test_different_bodies_produce_different_signatures() {
        $s1 = ZNC_REST_Auth::sign(array('body' => '{"a":1}'), '/znc/v1/test');
        $s2 = ZNC_REST_Auth::sign(array('body' => '{"b":2}'), '/znc/v1/test');
        $this->assertNotEquals($s1['headers']['X-ZNC-Signature'], $s2['headers']['X-ZNC-Signature']);
    }

    public function test_different_endpoints_produce_different_signatures() {
        $body = '{"test":true}';
        $s1 = ZNC_REST_Auth::sign(array('body' => $body), '/znc/v1/endpoint-a');
        $s2 = ZNC_REST_Auth::sign(array('body' => $body), '/znc/v1/endpoint-b');
        $this->assertNotEquals($s1['headers']['X-ZNC-Signature'], $s2['headers']['X-ZNC-Signature']);
    }

    public function test_sign_preserves_existing_headers() {
        $args = array('body' => '{}', 'headers' => array('X-Custom' => 'value'));
        $signed = ZNC_REST_Auth::sign($args, '/znc/v1/test');
        $this->assertEquals('value', $signed['headers']['X-Custom']);
        $this->assertArrayHasKey('X-ZNC-Signature', $signed['headers']);
    }

    public function test_sign_empty_body() {
        $args = array();
        $signed = ZNC_REST_Auth::sign($args, '/znc/v1/test');
        $this->assertArrayHasKey('X-ZNC-Signature', $signed['headers']);
    }

    public function test_content_type_set_to_json() {
        $args = array('body' => '{}');
        $signed = ZNC_REST_Auth::sign($args, '/znc/v1/test');
        $this->assertEquals('application/json', $signed['headers']['Content-Type']);
    }

    public function test_clock_skew_constant() {
        $this->assertEquals(300, ZNC_REST_Auth::CLOCK_SKEW);
    }
}
