<?php
class Test_ZNC_MyCred_Engine extends WP_UnitTestCase {
    private $engine;

    public function setUp(): void {
        parent::setUp();
        $this->engine = new ZNC_MyCred_Engine();
        update_site_option('znc_network_settings', array(
            'zcred_enabled' => true, 'zcred_exchange_rate' => 0.01, 'zcred_max_percent' => 50,
        ));
        $this->engine->init();
    }

    public function test_label_fallback() {
        $this->assertEquals('ZCred', $this->engine->get_label());
    }

    public function test_plural_label_fallback() {
        $this->assertEquals('ZCreds', $this->engine->get_plural_label());
    }

    public function test_exchange_rate() {
        $this->assertEquals(0.01, $this->engine->get_exchange_rate());
    }

    public function test_max_percent() {
        $this->assertEquals(50, $this->engine->get_max_percent());
    }

    public function test_balance_zero_without_mycred() {
        $this->assertEquals(0, $this->engine->get_balance(1));
    }

    public function test_parallel_total_structure() {
        $result = $this->engine->get_parallel_total(1, 100.0);
        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('balance', $result);
        $this->assertArrayHasKey('exchange_rate', $result);
        $this->assertArrayHasKey('max_percent', $result);
        $this->assertArrayHasKey('max_applicable', $result);
        $this->assertArrayHasKey('remaining_total', $result);
    }

    public function test_validate_deduction_unavailable() {
        $result = $this->engine->validate_deduction(1, 100, 50.0);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('znc_mycred_unavailable', $result->get_error_code());
    }

    public function test_deduct_unavailable() {
        $result = $this->engine->deduct(1, 100, 'test');
        $this->assertInstanceOf('WP_Error', $result);
    }

    public function test_refund_unavailable_returns_true() {
        $this->assertTrue($this->engine->refund(1, 100, 'test'));
    }

    public function test_deduct_zero_returns_true() {
        $this->assertTrue($this->engine->deduct(1, 0, 'test'));
    }
}
