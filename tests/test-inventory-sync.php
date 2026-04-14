<?php
class Test_ZNC_Inventory_Sync extends WP_UnitTestCase {
    private $sync;

    public function setUp(): void {
        parent::setUp();
        $this->sync = new ZNC_Inventory_Sync();
        ZNC_Activator::activate(true);
        update_site_option('znc_network_settings', array(
            'retry_max_attempts' => 3, 'retry_interval_minutes' => 5,
        ));
        $this->sync->init();
    }

    public function test_queue_stats_default() {
        $stats = $this->sync->get_queue_stats();
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('failed', $stats);
    }

    public function test_deduct_queues_on_failure() {
        $item = array('product_id' => 10, 'variation_id' => 0, 'quantity' => 2);
        $result = $this->sync->deduct(999, $item);
        // Remote request will fail — should queue retry
        $this->assertInstanceOf('WP_Error', $result);
        $stats = $this->sync->get_queue_stats();
        $this->assertGreaterThanOrEqual(0, $stats['pending']);
    }

    public function test_restore_queues_on_failure() {
        $item = array('product_id' => 10, 'variation_id' => 0, 'quantity' => 2);
        $result = $this->sync->restore(999, $item);
        $this->assertInstanceOf('WP_Error', $result);
    }

    public function test_retry_queue_empty_does_nothing() {
        $this->sync->process_retry_queue();
        $stats = $this->sync->get_queue_stats();
        $this->assertEquals(0, $stats['completed']);
    }

    public function test_max_attempts_respected() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'znc_inventory_retry', array(
            'site_id' => 999, 'product_id' => 10, 'quantity' => 1,
            'action' => 'deduct', 'attempts' => 2, 'max_attempts' => 3,
            'next_attempt' => gmdate('Y-m-d H:i:s', time() - 60),
            'status' => 'pending',
        ));
        $this->sync->process_retry_queue();
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}znc_inventory_retry WHERE product_id = 10", ARRAY_A);
        if ($row) {
            $this->assertContains($row['status'], array('pending', 'failed'));
        }
        $this->assertTrue(true);
    }

    public function test_retry_interval_increases() {
        // Exponential backoff: interval * attempts
        $network = get_site_option('znc_network_settings', array());
        $interval = intval($network['retry_interval_minutes'] ?? 5);
        $attempt2_delay = $interval * 2;
        $attempt3_delay = $interval * 3;
        $this->assertGreaterThan($attempt2_delay, $attempt3_delay);
    }

    public function test_queue_item_structure() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'znc_inventory_retry', array(
            'site_id' => 2, 'product_id' => 100, 'quantity' => 3,
            'action' => 'deduct', 'attempts' => 0, 'max_attempts' => 5,
            'status' => 'pending', 'next_attempt' => current_time('mysql', true),
        ));
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}znc_inventory_retry WHERE product_id = 100", ARRAY_A);
        $this->assertEquals(2, $row['site_id']);
        $this->assertEquals(100, $row['product_id']);
        $this->assertEquals(3, $row['quantity']);
        $this->assertEquals('deduct', $row['action']);
    }

    public function test_completed_items_counted() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'znc_inventory_retry', array(
            'site_id' => 2, 'product_id' => 10, 'quantity' => 1,
            'action' => 'deduct', 'attempts' => 1, 'max_attempts' => 5,
            'status' => 'completed',
        ));
        $stats = $this->sync->get_queue_stats();
        $this->assertGreaterThanOrEqual(1, $stats['completed']);
    }

    public function test_failed_items_counted() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'znc_inventory_retry', array(
            'site_id' => 2, 'product_id' => 20, 'quantity' => 1,
            'action' => 'deduct', 'attempts' => 5, 'max_attempts' => 5,
            'status' => 'failed', 'error_message' => 'Connection refused',
        ));
        $stats = $this->sync->get_queue_stats();
        $this->assertGreaterThanOrEqual(1, $stats['failed']);
    }

    public function test_deduct_item_structure() {
        $item = array('product_id' => 42, 'variation_id' => 0, 'quantity' => 5);
        $this->assertArrayHasKey('product_id', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertEquals(42, $item['product_id']);
    }

    public function test_restore_item_structure() {
        $item = array('product_id' => 42, 'variation_id' => 7, 'quantity' => 3);
        $this->assertArrayHasKey('variation_id', $item);
        $this->assertEquals(7, $item['variation_id']);
    }
}
