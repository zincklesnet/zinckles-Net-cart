<?php
class Test_ZNC_Global_Cart_Store extends WP_UnitTestCase {
    private $store;

    public function setUp(): void {
        parent::setUp();
        $this->store = new ZNC_Global_Cart_Store();
        ZNC_Activator::activate(true);
    }

    public function test_empty_cart() {
        $cart = $this->store->get_cart(999);
        $this->assertIsArray($cart);
        $this->assertEmpty($cart);
    }

    public function test_upsert_insert() {
        $id = $this->store->upsert_item(array(
            'user_id' => 1, 'site_id' => 2, 'product_id' => 100,
            'quantity' => 2, 'unit_price' => 25.99, 'currency' => 'USD',
        ));
        $this->assertGreaterThan(0, $id);
    }

    public function test_upsert_update() {
        $id1 = $this->store->upsert_item(array(
            'user_id' => 1, 'site_id' => 2, 'product_id' => 100,
            'quantity' => 2, 'unit_price' => 25.99, 'currency' => 'USD',
        ));
        $id2 = $this->store->upsert_item(array(
            'user_id' => 1, 'site_id' => 2, 'product_id' => 100,
            'quantity' => 5, 'unit_price' => 29.99, 'currency' => 'USD',
        ));
        $this->assertEquals($id1, $id2);
        $cart = $this->store->get_cart(1);
        $this->assertCount(1, $cart);
        $this->assertEquals(5, $cart[0]['quantity']);
    }

    public function test_get_cart_by_site() {
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>10,'quantity'=>1,'unit_price'=>10,'currency'=>'USD'));
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>3,'product_id'=>20,'quantity'=>1,'unit_price'=>20,'currency'=>'EUR'));
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>11,'quantity'=>1,'unit_price'=>15,'currency'=>'USD'));
        $grouped = $this->store->get_cart_by_site(1);
        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped[2]);
        $this->assertCount(1, $grouped[3]);
    }

    public function test_remove_item() {
        $id = $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>10,'quantity'=>1,'unit_price'=>10,'currency'=>'USD'));
        $this->assertTrue($this->store->remove_item(1, $id));
        $this->assertEmpty($this->store->get_cart(1));
    }

    public function test_clear_cart() {
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>10,'quantity'=>1,'unit_price'=>10,'currency'=>'USD'));
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>3,'product_id'=>20,'quantity'=>1,'unit_price'=>20,'currency'=>'USD'));
        $this->store->clear_cart(1);
        $this->assertEmpty($this->store->get_cart(1));
    }

    public function test_user_isolation() {
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>10,'quantity'=>1,'unit_price'=>10,'currency'=>'USD'));
        $this->store->upsert_item(array('user_id'=>2,'site_id'=>2,'product_id'=>20,'quantity'=>1,'unit_price'=>20,'currency'=>'USD'));
        $this->assertCount(1, $this->store->get_cart(1));
        $this->assertCount(1, $this->store->get_cart(2));
    }

    public function test_cart_stats() {
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>10,'quantity'=>3,'unit_price'=>10,'currency'=>'USD'));
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>3,'product_id'=>20,'quantity'=>1,'unit_price'=>25,'currency'=>'EUR'));
        $stats = $this->store->get_cart_stats(1);
        $this->assertEquals(2, $stats['item_count']);
        $this->assertEquals(2, $stats['shop_count']);
        $this->assertEquals(55, $stats['raw_total']);
    }

    public function test_update_quantity() {
        $id = $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>10,'quantity'=>1,'unit_price'=>10,'currency'=>'USD'));
        $this->store->update_quantity($id, 5);
        $cart = $this->store->get_cart(1);
        $this->assertEquals(5, $cart[0]['quantity']);
    }

    public function test_update_quantity_zero_deletes() {
        $id = $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>10,'quantity'=>1,'unit_price'=>10,'currency'=>'USD'));
        $this->store->update_quantity($id, 0);
        $this->assertEmpty($this->store->get_cart(1));
    }

    public function test_mixed_currency_cart() {
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>2,'product_id'=>10,'quantity'=>1,'unit_price'=>50,'currency'=>'USD'));
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>3,'product_id'=>20,'quantity'=>1,'unit_price'=>40,'currency'=>'EUR'));
        $this->store->upsert_item(array('user_id'=>1,'site_id'=>4,'product_id'=>30,'quantity'=>1,'unit_price'=>30,'currency'=>'GBP'));
        $cart = $this->store->get_cart(1);
        $currencies = array_unique(array_column($cart, 'currency'));
        $this->assertCount(3, $currencies);
    }
}
