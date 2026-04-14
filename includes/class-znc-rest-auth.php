<?php
defined( 'ABSPATH' ) || exit;

class ZNC_REST_Auth {

    const CLOCK_SKEW = 300; // seconds

    public function init() {
        add_filter( 'znc_rest_verify_request', array( $this, 'verify' ), 10, 1 );
    }

    /**
     * Sign an outgoing request array.
     */
    public static function sign( array $args, string $endpoint ) : array {
        $secret    = get_site_option( 'znc_rest_secret', '' );
        $timestamp = time();
        $nonce     = wp_generate_password( 16, false );
        $body      = isset( $args['body'] ) ? ( is_string( $args['body'] ) ? $args['body'] : wp_json_encode( $args['body'] ) ) : '';
        $payload   = $timestamp . '|' . $nonce . '|' . $endpoint . '|' . $body;
        $signature = hash_hmac( 'sha256', $payload, $secret );

        $args['headers'] = array_merge( $args['headers'] ?? array(), array(
            'X-ZNC-Timestamp' => $timestamp,
            'X-ZNC-Nonce'     => $nonce,
            'X-ZNC-Signature' => $signature,
            'Content-Type'    => 'application/json',
        ) );

        return $args;
    }

    /**
     * Verify an incoming WP_REST_Request.
     */
    public function verify( WP_REST_Request $request ) {
        $secret    = get_site_option( 'znc_rest_secret', '' );
        $timestamp = $request->get_header( 'x_znc_timestamp' );
        $nonce     = $request->get_header( 'x_znc_nonce' );
        $signature = $request->get_header( 'x_znc_signature' );

        if ( empty( $timestamp ) || empty( $nonce ) || empty( $signature ) ) {
            return new WP_Error( 'znc_auth_missing', 'Missing authentication headers.', array( 'status' => 401 ) );
        }

        // Clock skew check
        if ( abs( time() - intval( $timestamp ) ) > self::CLOCK_SKEW ) {
            return new WP_Error( 'znc_auth_expired', 'Request timestamp out of range.', array( 'status' => 401 ) );
        }

        // Rate limiting
        $rate_key = 'znc_rate_' . md5( $nonce );
        if ( get_transient( $rate_key ) ) {
            return new WP_Error( 'znc_auth_replay', 'Nonce already used.', array( 'status' => 429 ) );
        }
        set_transient( $rate_key, 1, self::CLOCK_SKEW );

        // Rebuild and compare signature
        $body    = $request->get_body();
        $route   = $request->get_route();
        $payload = $timestamp . '|' . $nonce . '|' . $route . '|' . $body;
        $expected = hash_hmac( 'sha256', $payload, $secret );

        if ( ! hash_equals( $expected, $signature ) ) {
            return new WP_Error( 'znc_auth_invalid', 'Invalid signature.', array( 'status' => 403 ) );
        }

        return true;
    }

    /**
     * Helper: send authenticated request to a subsite.
     */
    public static function remote_request( int $site_id, string $endpoint, array $body = array(), string $method = 'POST' ) {
        switch_to_blog( $site_id );
        $url = rest_url( 'znc/v1' . $endpoint );
        restore_current_blog();

        $args = array(
            'method'  => $method,
            'timeout' => 15,
            'body'    => wp_json_encode( $body ),
        );

        $args = self::sign( $args, '/znc/v1' . $endpoint );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            return new WP_Error(
                'znc_remote_error',
                $data['message'] ?? 'Remote request failed.',
                array( 'status' => $code, 'response' => $data )
            );
        }

        return $data;
    }
}
