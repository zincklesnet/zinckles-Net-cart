<?php
/**
 * Checkout Handler — Processes multi-shop checkout from the global cart.
 *
 * Creates a parent order on the checkout host and child orders on each
 * subsite shop. Handles payment on the host, then distributes.
 *
 * @package ZincklesNetCart
 * @since   1.5.1
 */
defined( 'ABSPATH' ) || exit;

class ZNC_Checkout_Handler {

    /** @var ZNC_Global_Cart */
    private $global_cart;

    /** @var ZNC_Checkout_Host */
    private $checkout_host;

    public function __construct( ZNC_Global_Cart $global_cart, ZNC_Checkout_Host $checkout_host ) {
        $this->global_cart   = $global_cart;
        $this->checkout_host = $checkout_host;
    }

    public function init() {
        /* Process checkout form submission */
        add_action( 'wp_ajax_znc_process_checkout', array( $this, 'process_checkout' ) );

        /* Render checkout form shortcode */
        add_action( 'init', array( $this, 'register_checkout_handling' ) );
    }

    public function register_checkout_handling() {
        /* Handle form POST for non-AJAX checkout */
        if ( isset( $_POST['znc_checkout_submit'] ) && wp_verify_nonce( $_POST['_znc_checkout_nonce'] ?? '', 'znc_checkout' ) ) {
            $this->process_checkout_form();
        }
    }

    /**
     * Render checkout page.
     */
    public function render_checkout( $user_id = null ) {
        $user_id  = $user_id ?: get_current_user_id();
        $renderer = new ZNC_Cart_Renderer( $this->global_cart );
        $enriched = $renderer->get_enriched_cart( $user_id );

        if ( empty( $enriched ) ) {
            return '<div class="znc-notice znc-notice-info">'
                 . '<p>' . esc_html__( 'Your cart is empty. Add items from our shops first!', 'zinckles-net-cart' ) . '</p>'
                 . '</div>';
        }

        ob_start();
        $grand_total = $renderer->get_cart_total( $user_id );
        $total_items = $this->global_cart->get_item_count( $user_id );
        ?>

        <div class="znc-checkout-wrap">

            <div class="znc-checkout-summary">
                <h3><?php esc_html_e( 'Order Summary', 'zinckles-net-cart' ); ?></h3>

                <?php foreach ( $enriched as $blog_id => $shop ) : ?>
                <div class="znc-checkout-shop">
                    <h4 class="znc-checkout-shop-name">
                        <?php echo esc_html( $shop['name'] ); ?>
                        <span class="znc-checkout-shop-currency">(<?php echo esc_html( $shop['currency'] ); ?>)</span>
                    </h4>
                    <ul class="znc-checkout-items">
                    <?php foreach ( $shop['items'] as $item ) : ?>
                        <li class="znc-checkout-item">
                            <span class="znc-checkout-item-name"><?php echo esc_html( $item['name'] ); ?> &times; <?php echo esc_html( $item['quantity'] ); ?></span>
                            <span class="znc-checkout-item-total"><?php echo esc_html( $shop['currency_symbol'] . number_format( $item['line_total'], 2 ) ); ?></span>
                        </li>
                    <?php endforeach; ?>
                        <li class="znc-checkout-subtotal">
                            <strong><?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?></strong>
                            <strong><?php echo esc_html( $shop['currency_symbol'] . number_format( $shop['subtotal'], 2 ) ); ?></strong>
                        </li>
                    </ul>
                </div>
                <?php endforeach; ?>

                <div class="znc-checkout-grand-total">
                    <span><?php esc_html_e( 'Grand Total', 'zinckles-net-cart' ); ?> (<?php echo esc_html( $total_items ); ?> <?php esc_html_e( 'items', 'zinckles-net-cart' ); ?>)</span>
                    <span class="znc-grand-total-amount">$<?php echo number_format( $grand_total, 2 ); ?></span>
                </div>
            </div>

            <form method="post" class="znc-checkout-form" id="znc-checkout-form">
                <?php wp_nonce_field( 'znc_checkout', '_znc_checkout_nonce' ); ?>
                <input type="hidden" name="znc_checkout_submit" value="1">

                <h3><?php esc_html_e( 'Billing Details', 'zinckles-net-cart' ); ?></h3>

                <div class="znc-form-row">
                    <label><?php esc_html_e( 'Full Name', 'zinckles-net-cart' ); ?> <span class="required">*</span></label>
                    <input type="text" name="billing_name" required value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>">
                </div>

                <div class="znc-form-row">
                    <label><?php esc_html_e( 'Email', 'zinckles-net-cart' ); ?> <span class="required">*</span></label>
                    <input type="email" name="billing_email" required value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>">
                </div>

                <div class="znc-form-row">
                    <label><?php esc_html_e( 'Address', 'zinckles-net-cart' ); ?></label>
                    <input type="text" name="billing_address">
                </div>

                <div class="znc-form-row znc-form-row-half">
                    <div>
                        <label><?php esc_html_e( 'City', 'zinckles-net-cart' ); ?></label>
                        <input type="text" name="billing_city">
                    </div>
                    <div>
                        <label><?php esc_html_e( 'Postal Code', 'zinckles-net-cart' ); ?></label>
                        <input type="text" name="billing_postcode">
                    </div>
                </div>

                <div class="znc-form-row">
                    <label><?php esc_html_e( 'Order Notes', 'zinckles-net-cart' ); ?></label>
                    <textarea name="order_notes" rows="3"></textarea>
                </div>

                <button type="submit" class="znc-btn znc-btn-checkout znc-btn-place-order">
                    <?php esc_html_e( 'Place Order', 'zinckles-net-cart' ); ?> — $<?php echo number_format( $grand_total, 2 ); ?>
                </button>
            </form>

        </div>

        <?php
        return ob_get_clean();
    }

    /**
     * Process the checkout form submission.
     */
    private function process_checkout_form() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_die( 'Not logged in', 403 );
        }

        $renderer = new ZNC_Cart_Renderer( $this->global_cart );
        $enriched = $renderer->get_enriched_cart( $user_id );
        if ( empty( $enriched ) ) {
            wp_safe_redirect( $this->checkout_host->get_cart_url() );
            exit;
        }

        $billing = array(
            'name'     => sanitize_text_field( $_POST['billing_name'] ?? '' ),
            'email'    => sanitize_email( $_POST['billing_email'] ?? '' ),
            'address'  => sanitize_text_field( $_POST['billing_address'] ?? '' ),
            'city'     => sanitize_text_field( $_POST['billing_city'] ?? '' ),
            'postcode' => sanitize_text_field( $_POST['billing_postcode'] ?? '' ),
        );

        $order_notes = sanitize_textarea_field( $_POST['order_notes'] ?? '' );
        $parent_order_id = null;

        /* ── Create child orders on each subsite ── */
        $child_orders = array();

        foreach ( $enriched as $blog_id => $shop ) {
            switch_to_blog( $blog_id );

            if ( ! function_exists( 'wc_create_order' ) ) {
                restore_current_blog();
                continue;
            }

            try {
                $order = wc_create_order( array(
                    'customer_id' => $user_id,
                    'status'      => 'processing',
                ) );

                foreach ( $shop['items'] as $item ) {
                    $product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
                    if ( $product ) {
                        $order->add_product( $product, $item['quantity'] );
                    }
                }

                $order->set_address( array(
                    'first_name' => $billing['name'],
                    'email'      => $billing['email'],
                    'address_1'  => $billing['address'],
                    'city'       => $billing['city'],
                    'postcode'   => $billing['postcode'],
                ), 'billing' );

                if ( $order_notes ) {
                    $order->add_order_note( $order_notes, false, false );
                }

                $order->add_order_note(
                    sprintf( 'Net Cart global order — from %s', home_url() ),
                    false, false
                );

                $order->calculate_totals();
                $order->save();

                $child_orders[ $blog_id ] = $order->get_id();

            } catch ( \Exception $e ) {
                if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                    error_log( '[ZNC-Checkout] Error creating order on blog ' . $blog_id . ': ' . $e->getMessage() );
                }
            }

            restore_current_blog();
        }

        /* ── Create parent order on checkout host ── */
        if ( ! empty( $child_orders ) && function_exists( 'wc_create_order' ) ) {
            $parent = wc_create_order( array(
                'customer_id' => $user_id,
                'status'      => 'processing',
            ) );

            $parent->set_address( array(
                'first_name' => $billing['name'],
                'email'      => $billing['email'],
                'address_1'  => $billing['address'],
                'city'       => $billing['city'],
                'postcode'   => $billing['postcode'],
            ), 'billing' );

            $grand_total = $renderer->get_cart_total( $user_id );
            $parent->set_total( $grand_total );

            $parent->add_order_note(
                sprintf( 'Net Cart parent order — child orders: %s',
                    implode( ', ', array_map( function( $bid, $oid ) {
                        $details = get_blog_details( $bid );
                        return ( $details ? $details->blogname : "Blog $bid" ) . " #$oid";
                    }, array_keys( $child_orders ), $child_orders ) )
                ),
                false, false
            );

            // Store child order references
            foreach ( $child_orders as $bid => $oid ) {
                $parent->update_meta_data( '_znc_child_order_' . $bid, $oid );
            }
            $parent->update_meta_data( '_znc_child_orders', $child_orders );

            $parent->calculate_totals();
            $parent->save();

            $parent_order_id = $parent->get_id();
        }

        /* ── Clear global cart ── */
        $this->global_cart->clear_cart( $user_id );

        /* ── Redirect to order received ── */
        if ( $parent_order_id && function_exists( 'wc_get_order' ) ) {
            $order = wc_get_order( $parent_order_id );
            if ( $order ) {
                wp_safe_redirect( $order->get_checkout_order_received_url() );
                exit;
            }
        }

        wp_safe_redirect( $this->checkout_host->get_account_url() );
        exit;
    }
}
