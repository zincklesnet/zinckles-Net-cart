<?php
class Test_ZNC_Currency_Handler extends WP_UnitTestCase {
    private $handler;

    public function setUp(): void {
        parent::setUp();
        $this->handler = new ZNC_Currency_Handler();
        update_site_option('znc_network_settings', array('base_currency' => 'USD'));
        update_option('znc_main_settings', array('exchange_rates' => array(
            'USD_EUR' => 0.92, 'USD_GBP' => 0.79, 'USD_CAD' => 1.36,
            'USD_AUD' => 1.53, 'USD_JPY' => 149.50, 'EUR_USD' => 1.09,
            'GBP_USD' => 1.27, 'CAD_USD' => 0.74,
        )));
        $this->handler->init();
    }

    public function test_base_currency_default() {
        $this->assertEquals('USD', $this->handler->get_base_currency());
    }

    public function test_is_mixed_single_currency() {
        $items = array(
            array('currency' => 'USD'), array('currency' => 'USD'),
        );
        $this->assertFalse($this->handler->is_mixed($items));
    }

    public function test_is_mixed_multiple_currencies() {
        $items = array(
            array('currency' => 'USD'), array('currency' => 'EUR'),
        );
        $this->assertTrue($this->handler->is_mixed($items));
    }

    public function test_get_currencies() {
        $items = array(
            array('currency' => 'USD'), array('currency' => 'EUR'), array('currency' => 'USD'),
        );
        $currencies = $this->handler->get_currencies($items);
        $this->assertCount(2, $currencies);
    }

    public function test_convert_same_currency() {
        $this->assertEquals(100.0, $this->handler->convert(100, 'USD', 'USD'));
    }

    public function test_convert_usd_to_eur() {
        $result = $this->handler->convert(100, 'USD', 'EUR');
        $this->assertEqualsWithDelta(92.0, $result, 0.01);
    }

    public function test_convert_eur_to_usd() {
        $result = $this->handler->convert(100, 'EUR', 'USD');
        $this->assertEqualsWithDelta(109.0, $result, 0.01);
    }

    public function test_convert_inverse_rate() {
        $result = $this->handler->convert(100, 'GBP', 'USD');
        $this->assertEqualsWithDelta(127.0, $result, 0.01);
    }

    public function test_parallel_totals_single_currency() {
        $items = array(
            array('unit_price' => 10, 'quantity' => 2, 'currency' => 'USD'),
            array('unit_price' => 15, 'quantity' => 1, 'currency' => 'USD'),
        );
        $totals = $this->handler->parallel_totals($items);
        $this->assertFalse($totals['is_mixed']);
        $this->assertEquals(35.0, $totals['converted_total']);
        $this->assertCount(1, $totals['breakdowns']);
    }

    public function test_parallel_totals_mixed_currency() {
        $items = array(
            array('unit_price' => 100, 'quantity' => 1, 'currency' => 'USD'),
            array('unit_price' => 50, 'quantity' => 2, 'currency' => 'EUR'),
        );
        $totals = $this->handler->parallel_totals($items);
        $this->assertTrue($totals['is_mixed']);
        $this->assertCount(2, $totals['breakdowns']);
        $this->assertGreaterThan(100, $totals['converted_total']);
    }

    public function test_format_price_usd() {
        $this->assertEquals('$25.00', $this->handler->format_price(25, 'USD'));
    }

    public function test_format_price_eur() {
        $this->assertEquals('€25.00', $this->handler->format_price(25, 'EUR'));
    }

    public function test_fallback_rate_returns_one() {
        $result = $this->handler->get_rate('XYZ', 'ABC');
        $this->assertEquals(1.0, $result);
    }
}
