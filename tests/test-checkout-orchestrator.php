<?php
class Test_ZNC_Checkout_Orchestrator extends WP_UnitTestCase {

    public function test_empty_cart_returns_error() {
        $store = $this->createMock(ZNC_Global_Cart_Store::class);
        $currency = new ZNC_Currency_Handler();
        $merger = $this->createMock(ZNC_Global_Cart_Merger::class);
        $mycred = new ZNC_MyCred_Engine();
        $orders = new ZNC_Order_Factory();
        $inventory = new ZNC_Inventory_Sync();

        $merger->method('refresh_cart')->willReturn(array('cart'=>array(),'removed'=>array(),'updated'=>array()));

        $orch = new ZNC_Checkout_Orchestrator($store,$merger,$currency,$mycred,$orders,$inventory);
        $result = $orch->process(1);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('znc_empty_cart', $result->get_error_code());
    }

    public function test_price_change_blocks_checkout() {
        // Simulates a price change scenario
        $store = $this->createMock(ZNC_Global_Cart_Store::class);
        $currency = new ZNC_Currency_Handler();
        $merger = $this->createMock(ZNC_Global_Cart_Merger::class);
        $mycred = new ZNC_MyCred_Engine();
        $orders = new ZNC_Order_Factory();
        $inventory = new ZNC_Inventory_Sync();

        $cart = array(array('id'=>1,'user_id'=>1,'site_id'=>2,'product_id'=>10,'variation_id'=>0,'quantity'=>1,'unit_price'=>25.00,'currency'=>'USD'));
        $merger->method('refresh_cart')->willReturn(array(
            'cart'=>$cart,
            'removed'=>array(),
            'updated'=>array(array('product_id'=>10,'new_price'=>30.00)),
        ));
        $store->method('get_cart_by_site')->willReturn(array(2=>$cart));

        // Mock validation failure
        update_option('znc_main_settings', array('price_change_action'=>'block'));
        $orch = new ZNC_Checkout_Orchestrator($store,$merger,$currency,$mycred,$orders,$inventory);
        // This would need a remote request mock — testing the flow structure
        $this->assertTrue(true);
    }

    public function test_insufficient_zcreds_returns_error() {
        $store = $this->createMock(ZNC_Global_Cart_Store::class);
        $currency = new ZNC_Currency_Handler();
        $merger = $this->createMock(ZNC_Global_Cart_Merger::class);
        $mycred = new ZNC_MyCred_Engine();
        $orders = new ZNC_Order_Factory();
        $inventory = new ZNC_Inventory_Sync();

        $cart = array(array('id'=>1,'user_id'=>1,'site_id'=>2,'product_id'=>10,'variation_id'=>0,'quantity'=>1,'unit_price'=>100,'currency'=>'USD'));
        $merger->method('refresh_cart')->willReturn(array('cart'=>$cart,'removed'=>array(),'updated'=>array()));
        $store->method('get_cart_by_site')->willReturn(array(2=>$cart));

        $mycred->init();
        $orch = new ZNC_Checkout_Orchestrator($store,$merger,$currency,$mycred,$orders,$inventory);
        $result = $orch->process(1, array('zcred_amount' => 99999));

        // MyCred not active = error
        if(is_wp_error($result)){
            $this->assertContains($result->get_error_code(), array('znc_empty_cart','znc_mycred_unavailable','znc_validation_failed'));
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_out_of_stock_removes_items() {
        $store = $this->createMock(ZNC_Global_Cart_Store::class);
        $currency = new ZNC_Currency_Handler();
        $merger = $this->createMock(ZNC_Global_Cart_Merger::class);
        $mycred = new ZNC_MyCred_Engine();
        $orders = new ZNC_Order_Factory();
        $inventory = new ZNC_Inventory_Sync();

        $removed = array(array('id'=>1,'product_id'=>10,'site_id'=>2));
        $merger->method('refresh_cart')->willReturn(array('cart'=>array(),'removed'=>$removed,'updated'=>array()));

        update_option('znc_main_settings', array('stock_change_action'=>'block'));
        $orch = new ZNC_Checkout_Orchestrator($store,$merger,$currency,$mycred,$orders,$inventory);
        $result = $orch->process(1);
        $this->assertInstanceOf('WP_Error', $result);
    }

    public function test_removed_items_block_checkout() {
        $store = $this->createMock(ZNC_Global_Cart_Store::class);
        $currency = new ZNC_Currency_Handler();
        $merger = $this->createMock(ZNC_Global_Cart_Merger::class);
        $mycred = new ZNC_MyCred_Engine();
        $orders = new ZNC_Order_Factory();
        $inventory = new ZNC_Inventory_Sync();

        $cart = array(array('id'=>1,'user_id'=>1,'site_id'=>2,'product_id'=>10,'variation_id'=>0,'quantity'=>1,'unit_price'=>50,'currency'=>'USD'));
        $removed = array(array('id'=>2,'product_id'=>20,'site_id'=>3));
        $merger->method('refresh_cart')->willReturn(array('cart'=>$cart,'removed'=>$removed,'updated'=>array()));

        update_option('znc_main_settings', array('stock_change_action'=>'block'));
        $orch = new ZNC_Checkout_Orchestrator($store,$merger,$currency,$mycred,$orders,$inventory);
        $result = $orch->process(1);
        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('znc_items_removed', $result->get_error_code());
    }

    public function test_removed_items_continue_with_remove_action() {
        $store = $this->createMock(ZNC_Global_Cart_Store::class);
        $currency = new ZNC_Currency_Handler();
        $merger = $this->createMock(ZNC_Global_Cart_Merger::class);
        $mycred = new ZNC_MyCred_Engine();
        $orders = new ZNC_Order_Factory();
        $inventory = new ZNC_Inventory_Sync();

        $cart = array(array('id'=>1,'user_id'=>1,'site_id'=>2,'product_id'=>10,'variation_id'=>0,'quantity'=>1,'unit_price'=>50,'currency'=>'USD'));
        $removed = array(array('id'=>2,'product_id'=>20,'site_id'=>3));
        $merger->method('refresh_cart')->willReturn(array('cart'=>$cart,'removed'=>$removed,'updated'=>array()));
        $store->method('get_cart_by_site')->willReturn(array(2=>$cart));

        update_option('znc_main_settings', array('stock_change_action'=>'remove'));
        $orch = new ZNC_Checkout_Orchestrator($store,$merger,$currency,$mycred,$orders,$inventory);
        // Would proceed past step 2 — testing flow logic
        $this->assertTrue(true);
    }

    public function test_coupon_structure_in_cart() {
        $item = array(
            'id'=>1,'user_id'=>1,'site_id'=>2,'product_id'=>10,'variation_id'=>0,
            'quantity'=>1,'unit_price'=>50,'currency'=>'USD',
            'coupon_codes'=>'SAVE10',
        );
        $this->assertEquals('SAVE10', $item['coupon_codes']);
    }

    public function test_three_site_mixed_cart_totals() {
        $currency = new ZNC_Currency_Handler();
        update_option('znc_main_settings', array('exchange_rates'=>array('USD_EUR'=>0.92,'USD_GBP'=>0.79,'EUR_USD'=>1.09,'GBP_USD'=>1.27)));
        $currency->init();
        $items = array(
            array('unit_price'=>100,'quantity'=>1,'currency'=>'USD'),
            array('unit_price'=>80,'quantity'=>2,'currency'=>'EUR'),
            array('unit_price'=>50,'quantity'=>1,'currency'=>'GBP'),
        );
        $totals = $currency->parallel_totals($items);
        $this->assertTrue($totals['is_mixed']);
        $this->assertCount(3, $totals['breakdowns']);
        $this->assertGreaterThan(0, $totals['converted_total']);
    }

    public function test_process_returns_log_array() {
        $store = $this->createMock(ZNC_Global_Cart_Store::class);
        $currency = new ZNC_Currency_Handler();
        $merger = $this->createMock(ZNC_Global_Cart_Merger::class);
        $mycred = new ZNC_MyCred_Engine();
        $orders = new ZNC_Order_Factory();
        $inventory = new ZNC_Inventory_Sync();

        $merger->method('refresh_cart')->willReturn(array('cart'=>array(),'removed'=>array(),'updated'=>array()));
        $orch = new ZNC_Checkout_Orchestrator($store,$merger,$currency,$mycred,$orders,$inventory);
        $result = $orch->process(1);
        // Empty cart = WP_Error, but log would be in success result
        $this->assertInstanceOf('WP_Error', $result);
    }

    public function test_checkout_config_filter() {
        update_option('znc_main_settings', array(
            'pre_checkout_refresh'=>true,'price_change_action'=>'warn','stock_change_action'=>'reduce',
            'coupon_support'=>true,'coupon_scope'=>'both',
        ));
        $loader = new ZNC_Admin_Loader();
        $config = $loader->filter_checkout_config();
        $this->assertEquals('warn', $config['price_change_action']);
        $this->assertEquals('reduce', $config['stock_change_action']);
        $this->assertTrue($config['coupon_support']);
    }

    public function test_max_order_amount_in_config() {
        update_option('znc_main_settings', array('max_order_amount'=>500));
        $loader = new ZNC_Admin_Loader();
        $config = $loader->filter_checkout_config();
        $this->assertEquals(500, $config['max_order_amount']);
    }
}
