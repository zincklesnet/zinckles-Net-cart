<?php
/**
 * Checkout Template — v1.5.0
 * Reads from wp_usermeta via ZNC_Cart_Snapshot — zero switch_to_blog().
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    echo '<div class="znc-login-required"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to checkout.</p></div>';
    return;
}

$user_id = get_current_user_id();
$grouped = ZNC_Cart_Snapshot::get_grouped( $user_id );
$count   = ZNC_Cart_Snapshot::get_count( $user_id );
$total   = ZNC_Cart_Snapshot::get_total( $user_id );
$shops   = ZNC_Cart_Snapshot::get_shop_count( $user_id );

$settings = get_site_option( 'znc_network_settings', array() );
$currency = isset( $settings['base_currency'] ) ? $settings['base_currency'] : 'USD';
$symbol   = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency ) : '$';

if ( empty( $grouped ) ) {
    echo '<div class="znc-cart-empty"><h3>Your cart is empty</h3><p>Add some products first!</p></div>';
    return;
}

$current_user = wp_get_current_user();
?>

<div class="znc-checkout" id="znc-checkout">
    <h2>🛒 Net Cart Checkout</h2>
    <p class="znc-checkout-summary"><?php echo $count; ?> items from <?php echo $shops; ?> shops — Total: <strong><?php echo esc_html( $symbol . number_format( $total, 2 ) ); ?></strong></p>

    <div class="znc-checkout-layout">
        <div class="znc-checkout-main">

            <!-- Billing Details -->
            <div class="znc-section">
                <h3>Billing Details</h3>
                <div class="znc-form-grid">
                    <div class="znc-field">
                        <label for="znc-first-name">First Name *</label>
                        <input type="text" id="znc-first-name" name="billing_first_name" value="<?php echo esc_attr( $current_user->first_name ); ?>" required>
                    </div>
                    <div class="znc-field">
                        <label for="znc-last-name">Last Name *</label>
                        <input type="text" id="znc-last-name" name="billing_last_name" value="<?php echo esc_attr( $current_user->last_name ); ?>" required>
                    </div>
                    <div class="znc-field znc-full">
                        <label for="znc-email">Email *</label>
                        <input type="email" id="znc-email" name="billing_email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required>
                    </div>
                    <div class="znc-field znc-full">
                        <label for="znc-address">Address *</label>
                        <input type="text" id="znc-address" name="billing_address_1" required>
                    </div>
                    <div class="znc-field">
                        <label for="znc-city">City *</label>
                        <input type="text" id="znc-city" name="billing_city" required>
                    </div>
                    <div class="znc-field">
                        <label for="znc-state">State / Province</label>
                        <input type="text" id="znc-state" name="billing_state">
                    </div>
                    <div class="znc-field">
                        <label for="znc-postcode">Postal Code *</label>
                        <input type="text" id="znc-postcode" name="billing_postcode" required>
                    </div>
                    <div class="znc-field">
                        <label for="znc-country">Country *</label>
                        <select id="znc-country" name="billing_country">
                            <option value="US">United States</option>
                            <option value="CA">Canada</option>
                            <option value="GB">United Kingdom</option>
                            <option value="AU">Australia</option>
                            <option value="DE">Germany</option>
                            <option value="FR">France</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Points Payment -->
            <?php
            $has_points = false;
            // MyCred
            if ( function_exists( 'mycred_get_users_balance' ) && function_exists( 'mycred_get_types' ) ) {
                $types = mycred_get_types();
                foreach ( $types as $slug => $label ) {
                    $balance = mycred_get_users_balance( $user_id, $slug );
                    if ( $balance > 0 ) {
                        if ( ! $has_points ) {
                            echo '<div class="znc-section"><h3>Pay with Points</h3>';
                            $has_points = true;
                        }
                        $config = isset( $settings['mycred_types_config'][ $slug ] ) ? $settings['mycred_types_config'][ $slug ] : array();
                        $rate   = isset( $config['exchange_rate'] ) ? (float) $config['exchange_rate'] : 0;
                        $max_pct = isset( $config['max_percent'] ) ? (int) $config['max_percent'] : 100;
                        echo '<div class="znc-points-row">';
                        echo '<span>' . esc_html( $label ) . ': <strong>' . number_format( $balance, 0 ) . '</strong></span>';
                        if ( $rate > 0 ) {
                            echo ' <small>(1 point = ' . $symbol . number_format( $rate, 2 ) . ', max ' . $max_pct . '%)</small>';
                        }
                        echo '</div>';
                    }
                }
            }
            // GamiPress
            if ( function_exists( 'gamipress_get_user_points' ) ) {
                $gp_types = get_posts( array( 'post_type' => 'points-type', 'post_status' => 'publish', 'numberposts' => 20 ) );
                foreach ( $gp_types as $pt ) {
                    $balance = gamipress_get_user_points( $user_id, $pt->post_name );
                    if ( $balance > 0 ) {
                        if ( ! $has_points ) {
                            echo '<div class="znc-section"><h3>Pay with Points</h3>';
                            $has_points = true;
                        }
                        $config = isset( $settings['gamipress_types_config'][ $pt->post_name ] ) ? $settings['gamipress_types_config'][ $pt->post_name ] : array();
                        $rate   = isset( $config['exchange_rate'] ) ? (float) $config['exchange_rate'] : 0;
                        echo '<div class="znc-points-row">';
                        echo '<span>' . esc_html( $pt->post_title ) . ': <strong>' . number_format( $balance, 0 ) . '</strong></span>';
                        if ( $rate > 0 ) {
                            echo ' <small>(1 point = ' . $symbol . number_format( $rate, 2 ) . ')</small>';
                        }
                        echo '</div>';
                    }
                }
            }
            if ( $has_points ) echo '</div>';
            ?>

            <!-- Order Notes -->
            <div class="znc-section">
                <h3>Order Notes</h3>
                <textarea name="order_notes" rows="3" placeholder="Any special instructions..."></textarea>
            </div>
        </div>

        <!-- Order Summary Sidebar -->
        <div class="znc-checkout-sidebar">
            <div class="znc-order-summary">
                <h3>Order Summary</h3>

                <?php foreach ( $grouped as $blog_id => $group ) : ?>
                <div class="znc-summary-shop">
                    <h4><?php echo esc_html( $group['shop_name'] ); ?></h4>
                    <?php foreach ( $group['items'] as $item ) :
                        $name  = isset( $item['product_name'] ) ? $item['product_name'] : 'Product';
                        $qty   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
                        $price = isset( $item['price'] ) ? (float) $item['price'] : 0;
                    ?>
                    <div class="znc-summary-item">
                        <span><?php echo esc_html( $name ); ?> × <?php echo $qty; ?></span>
                        <span><?php echo esc_html( $symbol . number_format( $qty * $price, 2 ) ); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>

                <div class="znc-summary-total">
                    <span>Total</span>
                    <strong><?php echo esc_html( $symbol . number_format( $total, 2 ) ); ?></strong>
                </div>

                <button class="znc-place-order-btn" id="znc-place-order">Place Order</button>
                <p class="znc-checkout-note">Orders are processed per-shop. You'll receive separate confirmations from each shop.</p>
            </div>
        </div>
    </div>
</div>
