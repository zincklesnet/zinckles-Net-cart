<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Currency_Handler {

    private $base_currency;
    private $rates;

    public function init() {
        $network = get_site_option( 'znc_network_settings', array() );
        $this->base_currency = $network['base_currency'] ?? 'USD';

        $main_settings = get_option( 'znc_main_settings', array() );
        $this->rates = $main_settings['exchange_rates'] ?? $this->default_rates();
    }

    public function get_base_currency() : string {
        return $this->base_currency ?: 'USD';
    }

    /**
     * Check if a cart has mixed currencies.
     */
    public function is_mixed( array $items ) : bool {
        $currencies = array_unique( array_column( $items, 'currency' ) );
        return count( $currencies ) > 1;
    }

    /**
     * Get all unique currencies in a cart.
     */
    public function get_currencies( array $items ) : array {
        return array_unique( array_column( $items, 'currency' ) );
    }

    /**
     * Calculate parallel totals — per-currency subtotals + unified converted total.
     */
    public function parallel_totals( array $items ) : array {
        $by_currency = array();
        foreach ( $items as $item ) {
            $cur = $item['currency'] ?? $this->base_currency;
            if ( ! isset( $by_currency[ $cur ] ) ) {
                $by_currency[ $cur ] = 0;
            }
            $by_currency[ $cur ] += floatval( $item['unit_price'] ) * intval( $item['quantity'] );
        }

        $converted_total = 0;
        $breakdowns      = array();

        foreach ( $by_currency as $cur => $subtotal ) {
            $rate      = $this->get_rate( $cur, $this->base_currency );
            $converted = $subtotal * $rate;
            $converted_total += $converted;

            $breakdowns[] = array(
                'currency'        => $cur,
                'subtotal'        => round( $subtotal, 4 ),
                'exchange_rate'   => $rate,
                'converted'       => round( $converted, 4 ),
                'base_currency'   => $this->base_currency,
            );
        }

        return array(
            'is_mixed'        => count( $by_currency ) > 1,
            'base_currency'   => $this->base_currency,
            'converted_total' => round( $converted_total, 2 ),
            'breakdowns'      => $breakdowns,
            'item_count'      => count( $items ),
        );
    }

    /**
     * Convert amount from one currency to another.
     */
    public function convert( float $amount, string $from, string $to ) : float {
        if ( $from === $to ) return $amount;
        $rate = $this->get_rate( $from, $to );
        return round( $amount * $rate, 4 );
    }

    /**
     * Get exchange rate between two currencies.
     */
    public function get_rate( string $from, string $to ) : float {
        if ( $from === $to ) return 1.0;

        $key = strtoupper( $from ) . '_' . strtoupper( $to );
        if ( isset( $this->rates[ $key ] ) ) {
            return floatval( $this->rates[ $key ] );
        }

        // Try inverse
        $inv = strtoupper( $to ) . '_' . strtoupper( $from );
        if ( isset( $this->rates[ $inv ] ) && floatval( $this->rates[ $inv ] ) > 0 ) {
            return 1.0 / floatval( $this->rates[ $inv ] );
        }

        // Try via base currency
        $from_base = strtoupper( $from ) . '_' . strtoupper( $this->base_currency );
        $to_base   = strtoupper( $to ) . '_' . strtoupper( $this->base_currency );

        if ( isset( $this->rates[ $from_base ] ) && isset( $this->rates[ $to_base ] ) ) {
            $from_rate = floatval( $this->rates[ $from_base ] );
            $to_rate   = floatval( $this->rates[ $to_base ] );
            if ( $to_rate > 0 ) {
                return $from_rate / $to_rate;
            }
        }

        // Fallback: check cached API rates
        $cached = get_transient( 'znc_exchange_rates' );
        if ( $cached && isset( $cached[ $key ] ) ) {
            return floatval( $cached[ $key ] );
        }

        return apply_filters( 'znc_fallback_exchange_rate', 1.0, $from, $to );
    }

    /**
     * Refresh exchange rates from configured API.
     */
    public function refresh_rates() : bool {
        $main_settings = get_option( 'znc_main_settings', array() );
        $provider      = $main_settings['rate_api_provider'] ?? 'manual';
        $api_key       = $main_settings['rate_api_key'] ?? '';

        if ( 'manual' === $provider || empty( $api_key ) ) {
            return false;
        }

        $urls = array(
            'exchangerate_api'    => "https://v6.exchangerate-api.com/v6/{$api_key}/latest/{$this->base_currency}",
            'open_exchange_rates' => "https://openexchangerates.org/api/latest.json?app_id={$api_key}&base={$this->base_currency}",
            'fixer'              => "http://data.fixer.io/api/latest?access_key={$api_key}&base={$this->base_currency}",
        );

        $url = $urls[ $provider ] ?? '';
        if ( empty( $url ) ) return false;

        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
        if ( is_wp_error( $response ) ) return false;

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $api_rates = $data['conversion_rates'] ?? $data['rates'] ?? array();

        if ( empty( $api_rates ) ) return false;

        $new_rates = array();
        foreach ( $api_rates as $currency => $rate ) {
            $new_rates[ $this->base_currency . '_' . $currency ] = $rate;
        }

        set_transient( 'znc_exchange_rates', $new_rates, intval( $main_settings['rate_refresh_hours'] ?? 24 ) * HOUR_IN_SECONDS );
        $this->rates = array_merge( $this->rates, $new_rates );

        return true;
    }

    /**
     * Format a price with currency symbol.
     */
    public function format_price( float $amount, string $currency ) : string {
        $symbols = array(
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'CAD' => 'C$',
            'AUD' => 'A$', 'JPY' => '¥', 'CHF' => 'CHF', 'CNY' => '¥',
            'INR' => '₹', 'MXN' => 'MX$', 'BRL' => 'R$', 'KRW' => '₩',
        );
        $symbol = $symbols[ strtoupper( $currency ) ] ?? $currency . ' ';
        return $symbol . number_format( $amount, 2 );
    }

    private function default_rates() : array {
        return array(
            'USD_EUR' => 0.92, 'USD_GBP' => 0.79, 'USD_CAD' => 1.36,
            'USD_AUD' => 1.53, 'USD_JPY' => 149.50, 'USD_CHF' => 0.88,
            'USD_CNY' => 7.24, 'USD_INR' => 83.12, 'USD_MXN' => 17.15,
            'EUR_USD' => 1.09, 'GBP_USD' => 1.27, 'CAD_USD' => 0.74,
        );
    }
}
