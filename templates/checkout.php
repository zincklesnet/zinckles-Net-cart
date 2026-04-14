<?php
defined( 'ABSPATH' ) || exit;
$user_id  = get_current_user_id();
$store    = new ZNC_Global_Cart_Store();
$currency = new ZNC_Currency_Handler();
$merger   = new ZNC_Global_Cart_Merger( $store, $currency );
$mycred   = new ZNC_MyCred_Engine();
$cart     = $merger->get_cart_with_totals( $user_id );
$items    = $cart['items'];
$totals   = $cart['totals'];
$settings = get_option( 'znc_main_settings', array() );
$steps_display = $settings['steps_display'] ?? 'progress_bar';
?>
<div class="znc-checkout" id="znc-checkout">
    <?php if ( empty( $items ) ) : ?>
        <div class="znc-empty-cart">
            <p><?php esc_html_e( 'Your cart is empty. Nothing to checkout.', 'zinckles-net-cart' ); ?></p>
            <a href="<?php echo esc_url( get_permalink( $settings['cart_page_id'] ?? 0 ) ); ?>" class="button"><?php esc_html_e( 'Back to Cart', 'zinckles-net-cart' ); ?></a>
        </div>
    <?php else : ?>

        <?php if ( 'progress_bar' === $steps_display ) : ?>
        <div class="znc-progress-bar">
            <div class="znc-step active" data-step="1"><span>1</span> Review</div>
            <div class="znc-step" data-step="2"><span>2</span> Payment</div>
            <div class="znc-step" data-step="3"><span>3</span> Confirm</div>
        </div>
        <?php endif; ?>

        <form id="znc-checkout-form" method="post">
            <?php wp_nonce_field( 'znc_checkout_nonce', 'znc_nonce' ); ?>

            <!-- Step 1: Review -->
            <div class="znc-checkout-section" data-step="1">
                <h3><?php esc_html_e( 'Order Review', 'zinckles-net-cart' ); ?></h3>
                <table class="znc-review-table">
                    <thead><tr><th>Product</th><th>Shop</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
                    <tbody>
                    <?php foreach ( $items as $item ) :
                        $display = apply_filters( 'znc_shop_display', array(), $item['site_id'] );
                        $line = floatval( $item['unit_price'] ) * intval( $item['quantity'] );
                    ?>
                    <tr>
                        <td><?php echo esc_html( json_decode( $item['line_meta'] ?? '{}', true )['name'] ?? 'Product #' . $item['product_id'] ); ?></td>
                        <td><span class="znc-inline-badge" style="background:<?php echo esc_attr( $display['badge_color'] ?? '#4f46e5' ); ?>"><?php echo esc_html( $display['name'] ?? 'Shop #' . $item['site_id'] ); ?></span></td>
                        <td><?php echo esc_html( $currency->format_price( floatval( $item['unit_price'] ), $item['currency'] ) ); ?></td>
                        <td><?php echo esc_html( $item['quantity'] ); ?></td>
                        <td><?php echo esc_html( $currency->format_price( $line, $item['currency'] ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="znc-total-row">
                            <td colspan="4"><strong><?php esc_html_e( 'Total', 'zinckles-net-cart' ); ?></strong></td>
                            <td><strong><?php echo esc_html( $currency->format_price( $totals['converted_total'], $totals['base_currency'] ) ); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>

                <?php if ( $mycred->is_available() && ! empty( $settings['zcred_checkout_enabled'] ) ) :
                    $parallel = $mycred->get_parallel_total( $user_id, $totals['converted_total'] );
                ?>
                <div class="znc-zcred-checkout">
                    <h4><?php printf( esc_html__( 'Pay with %s', 'zinckles-net-cart' ), esc_html( $parallel['label'] ) ); ?></h4>
                    <p>Balance: <strong><?php echo esc_html( number_format( $parallel['balance'], 0 ) ); ?></strong> <?php echo esc_html( $parallel['label'] ); ?></p>
                    <?php if ( 'slider' === ( $settings['zcred_input_style'] ?? 'slider' ) ) : ?>
                        <input type="range" name="zcred_amount" id="znc-zcred-slider" min="0" max="<?php echo esc_attr( $parallel['credits_needed'] ); ?>" value="0" />
                        <span id="znc-zcred-display">0</span> <?php echo esc_html( $parallel['label'] ); ?>
                        = <span id="znc-zcred-value">$0.00</span>
                    <?php else : ?>
                        <input type="number" name="zcred_amount" min="0" max="<?php echo esc_attr( $parallel['credits_needed'] ); ?>" value="0" class="small-text" />
                    <?php endif; ?>
                    <p class="description"><?php printf( esc_html__( 'Max: %s %s (%s)', 'zinckles-net-cart' ), number_format( $parallel['credits_needed'], 0 ), esc_html( $parallel['label'] ), esc_html( $currency->format_price( $parallel['max_applicable'], $totals['base_currency'] ) ) ); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Step 2: Payment -->
            <div class="znc-checkout-section" data-step="2" style="display:none;">
                <h3><?php esc_html_e( 'Billing Details', 'zinckles-net-cart' ); ?></h3>
                <div class="znc-billing-fields">
                    <p><label>First Name<br /><input type="text" name="billing[first_name]" required /></label></p>
                    <p><label>Last Name<br /><input type="text" name="billing[last_name]" required /></label></p>
                    <p><label>Email<br /><input type="email" name="billing[email]" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required /></label></p>
                    <p><label>Address<br /><input type="text" name="billing[address_1]" required /></label></p>
                    <p><label>City<br /><input type="text" name="billing[city]" required /></label></p>
                    <p><label>State<br /><input type="text" name="billing[state]" /></label></p>
                    <p><label>ZIP/Postal<br /><input type="text" name="billing[postcode]" required /></label></p>
                    <p><label>Country<br /><input type="text" name="billing[country]" value="US" required /></label></p>
                </div>

                <h3><?php esc_html_e( 'Payment Method', 'zinckles-net-cart' ); ?></h3>
                <select name="payment_method">
                    <?php
                    $gateways = WC()->payment_gateways()->get_available_payment_gateways();
                    foreach ( $gateways as $id => $gw ) :
                    ?>
                    <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $gw->get_title() ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Step 3: Confirm -->
            <div class="znc-checkout-section" data-step="3" style="display:none;">
                <h3><?php esc_html_e( 'Confirm Your Order', 'zinckles-net-cart' ); ?></h3>
                <div id="znc-order-summary"></div>
                <button type="submit" class="znc-place-order-btn button alt"><?php esc_html_e( 'Place Order', 'zinckles-net-cart' ); ?></button>
            </div>

            <div class="znc-checkout-nav">
                <button type="button" class="button znc-prev-step" style="display:none;">&larr; Back</button>
                <button type="button" class="button button-primary znc-next-step">Continue &rarr;</button>
            </div>
        </form>
    <?php endif; ?>
</div>
