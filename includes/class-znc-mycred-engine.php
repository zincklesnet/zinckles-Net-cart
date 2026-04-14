<?php
defined( 'ABSPATH' ) || exit;

class ZNC_MyCred_Engine {

    private $active = false;
    private $type   = 'mycred_default';

    public function init() {
        $this->active = function_exists( 'mycred' );
    }

    public function is_available() : bool {
        return $this->active;
    }

    public function get_label() : string {
        if ( ! $this->active ) return 'ZCred';
        return mycred()->core->singular();
    }

    public function get_plural_label() : string {
        if ( ! $this->active ) return 'ZCreds';
        return mycred()->core->plural();
    }

    /**
     * Get user's current balance.
     */
    public function get_balance( int $user_id ) : float {
        if ( ! $this->active ) return 0;
        return floatval( mycred_get_users_balance( $user_id, $this->type ) );
    }

    /**
     * Get the exchange rate: how much 1 ZCred is worth in base currency.
     */
    public function get_exchange_rate() : float {
        $network  = get_site_option( 'znc_network_settings', array() );
        return floatval( $network['zcred_exchange_rate'] ?? 0.01 );
    }

    /**
     * Get the maximum percentage of a cart total payable with ZCreds.
     */
    public function get_max_percent() : int {
        $network = get_site_option( 'znc_network_settings', array() );
        return intval( $network['zcred_max_percent'] ?? 100 );
    }

    /**
     * Calculate parallel total showing ZCred potential.
     */
    public function get_parallel_total( int $user_id, float $cart_total ) : array {
        $balance       = $this->get_balance( $user_id );
        $rate          = $this->get_exchange_rate();
        $max_pct       = $this->get_max_percent();
        $max_by_pct    = $cart_total * ( $max_pct / 100 );
        $max_by_balance = $balance * $rate;
        $max_applicable = min( $max_by_pct, $max_by_balance, $cart_total );
        $credits_needed = $rate > 0 ? ceil( $max_applicable / $rate ) : 0;

        return array(
            'available'       => $this->active,
            'balance'         => $balance,
            'exchange_rate'   => $rate,
            'max_percent'     => $max_pct,
            'max_applicable'  => round( $max_applicable, 2 ),
            'credits_needed'  => $credits_needed,
            'monetary_value'  => round( $max_applicable, 2 ),
            'remaining_total' => round( $cart_total - $max_applicable, 2 ),
            'label'           => $this->get_plural_label(),
        );
    }

    /**
     * Validate a ZCred deduction before processing.
     */
    public function validate_deduction( int $user_id, float $amount, float $cart_total ) {
        if ( ! $this->active ) {
            return new WP_Error( 'znc_mycred_unavailable', 'ZCreds system is not available.', array( 'status' => 400 ) );
        }

        $balance  = $this->get_balance( $user_id );
        $rate     = $this->get_exchange_rate();
        $max_pct  = $this->get_max_percent();
        $max_val  = $cart_total * ( $max_pct / 100 );
        $monetary = $amount * $rate;

        if ( $amount > $balance ) {
            return new WP_Error( 'znc_insufficient_zcreds', sprintf(
                'Insufficient %s balance: have %.2f, need %.2f.',
                $this->get_plural_label(), $balance, $amount
            ), array( 'status' => 400, 'balance' => $balance, 'requested' => $amount ) );
        }

        if ( $monetary > $max_val ) {
            return new WP_Error( 'znc_zcred_exceeds_max', sprintf(
                '%s value ($%.2f) exceeds maximum allowed (%.0f%% = $%.2f).',
                $this->get_plural_label(), $monetary, $max_pct, $max_val
            ), array( 'status' => 400 ) );
        }

        return array(
            'valid'          => true,
            'amount'         => $amount,
            'monetary_value' => round( $monetary, 2 ),
            'remaining'      => round( $cart_total - $monetary, 2 ),
            'new_balance'    => $balance - $amount,
        );
    }

    /**
     * Deduct ZCreds from user balance.
     */
    public function deduct( int $user_id, float $amount, string $reason = '' ) {
        if ( ! $this->active ) {
            return new WP_Error( 'znc_mycred_unavailable', 'ZCreds not available.' );
        }
        if ( $amount <= 0 ) return true;

        $result = mycred_subtract( 'net_cart_checkout', $user_id, $amount, $reason, '', $this->type );
        if ( ! $result ) {
            return new WP_Error( 'znc_deduct_failed', 'Failed to deduct ZCreds.' );
        }

        do_action( 'znc_zcreds_deducted', $user_id, $amount, $reason );
        return true;
    }

    /**
     * Refund ZCreds to user balance (rollback).
     */
    public function refund( int $user_id, float $amount, string $reason = '' ) {
        if ( ! $this->active || $amount <= 0 ) return true;

        mycred_add( 'net_cart_refund', $user_id, $amount, $reason, '', $this->type );
        do_action( 'znc_zcreds_refunded', $user_id, $amount, $reason );
        return true;
    }

    /**
     * Award ZCreds for a completed purchase.
     */
    public function award_purchase( int $user_id, float $order_total, array $items = array() ) {
        if ( ! $this->active ) return;

        $main = get_option( 'znc_main_settings', array() );
        if ( empty( $main['zcred_earn_enabled'] ) ) return;

        $base_rate = floatval( $main['zcred_earn_rate'] ?? 1 );
        $earned    = floor( $order_total * $base_rate );

        if ( $earned > 0 ) {
            mycred_add( 'net_cart_purchase', $user_id, $earned, 'Net Cart purchase reward', '', $this->type );
            do_action( 'znc_zcreds_awarded', $user_id, $earned, $order_total );
        }
    }
}
