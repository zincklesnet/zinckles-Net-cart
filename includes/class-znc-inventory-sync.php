<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Inventory_Sync {

    public function init() {
        add_action( 'znc_inventory_retry', array( $this, 'process_retry_queue' ) );
    }

    /**
     * Deduct stock on a subsite for a cart item.
     */
    public function deduct( int $site_id, array $item ) {
        $result = ZNC_REST_Auth::remote_request( $site_id, '/inventory/deduct', array(
            'product_id'   => $item['product_id'],
            'variation_id' => $item['variation_id'] ?? 0,
            'quantity'     => $item['quantity'],
        ) );

        if ( is_wp_error( $result ) ) {
            $this->queue_retry( $site_id, $item, 'deduct', $result->get_error_message() );
            return $result;
        }

        return $result;
    }

    /**
     * Restore stock on a subsite (rollback).
     */
    public function restore( int $site_id, array $item ) {
        $result = ZNC_REST_Auth::remote_request( $site_id, '/inventory/restore', array(
            'product_id'   => $item['product_id'],
            'variation_id' => $item['variation_id'] ?? 0,
            'quantity'     => $item['quantity'],
        ) );

        if ( is_wp_error( $result ) ) {
            $this->queue_retry( $site_id, $item, 'restore', $result->get_error_message() );
            return $result;
        }

        return $result;
    }

    /**
     * Add a failed sync operation to the retry queue.
     */
    private function queue_retry( int $site_id, array $item, string $action, string $error = '' ) {
        global $wpdb;

        $network  = get_site_option( 'znc_network_settings', array() );
        $max      = intval( $network['retry_max_attempts'] ?? 5 );
        $interval = intval( $network['retry_interval_minutes'] ?? 5 );

        $wpdb->insert( $wpdb->prefix . 'znc_inventory_retry', array(
            'site_id'      => $site_id,
            'product_id'   => $item['product_id'],
            'quantity'     => $item['quantity'],
            'action'       => $action,
            'attempts'     => 0,
            'max_attempts' => $max,
            'next_attempt' => gmdate( 'Y-m-d H:i:s', time() + ( $interval * 60 ) ),
            'status'       => 'pending',
            'error_message'=> $error,
        ) );
    }

    /**
     * Process the retry queue (cron).
     */
    public function process_retry_queue() {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_inventory_retry';

        $pending = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'pending' AND next_attempt <= NOW() ORDER BY created_at ASC LIMIT 20",
            ARRAY_A
        );

        if ( empty( $pending ) ) return;

        $network  = get_site_option( 'znc_network_settings', array() );
        $interval = intval( $network['retry_interval_minutes'] ?? 5 );

        foreach ( $pending as $row ) {
            $endpoint = ( 'deduct' === $row['action'] ) ? '/inventory/deduct' : '/inventory/restore';

            $result = ZNC_REST_Auth::remote_request( intval( $row['site_id'] ), $endpoint, array(
                'product_id' => $row['product_id'],
                'quantity'   => $row['quantity'],
            ) );

            $attempts = intval( $row['attempts'] ) + 1;

            if ( is_wp_error( $result ) ) {
                if ( $attempts >= intval( $row['max_attempts'] ) ) {
                    $wpdb->update( $table, array(
                        'status'       => 'failed',
                        'attempts'     => $attempts,
                        'last_attempt' => current_time( 'mysql', true ),
                        'error_message'=> $result->get_error_message(),
                    ), array( 'id' => $row['id'] ) );

                    do_action( 'znc_inventory_sync_failed', $row );
                } else {
                    $wpdb->update( $table, array(
                        'attempts'     => $attempts,
                        'last_attempt' => current_time( 'mysql', true ),
                        'next_attempt' => gmdate( 'Y-m-d H:i:s', time() + ( $interval * 60 * $attempts ) ),
                        'error_message'=> $result->get_error_message(),
                    ), array( 'id' => $row['id'] ) );
                }
            } else {
                $wpdb->update( $table, array(
                    'status'       => 'completed',
                    'attempts'     => $attempts,
                    'last_attempt' => current_time( 'mysql', true ),
                    'error_message'=> '',
                ), array( 'id' => $row['id'] ) );
            }
        }
    }

    /**
     * Admin: get retry queue status.
     */
    public function get_queue_stats() : array {
        global $wpdb;
        $table = $wpdb->prefix . 'znc_inventory_retry';

        return array(
            'pending'   => intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'" ) ),
            'completed' => intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" ) ),
            'failed'    => intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" ) ),
        );
    }
}
