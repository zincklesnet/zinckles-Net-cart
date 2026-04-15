<?php
/**
 * Template: Global Checkout
 *
 * Renders the unified checkout page for the global cart.
 * Handles billing/shipping, payment method selection,
 * points redemption sliders, and order review.
 *
 * Shortcode: [znc_checkout]
 *
 * @package ZincklesNetCart
 * @since   1.4.0
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    echo '<div class="znc-notice znc-notice-info">';
    echo '<p>' . esc_html__( 'Please log in to checkout.', 'zinckles-net-cart' ) . '</p>';
    echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="znc-checkout-btn" style="max-width:200px;margin:12px auto 0;">' . esc_html__( 'Log In', 'zinckles-net-cart' ) . '</a>';
    echo '</div>';
    return;
}

$user_id = get_current_user_id();
$user    = wp_get_current_user();

/* ── Fetch cart items ─────────────────────────────────────────── */
global $wpdb;
$table = $wpdb->prefix . 'znc_global_cart';

$table_exists = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME, $table
) );

if ( ! $table_exists ) {
    echo '<div class="znc-notice znc-notice-warning">';
    echo '<p>' . esc_html__( 'Cart tables not initialized. Please reactivate Net Cart.', 'zinckles-net-cart' ) . '</p>';
    echo '</div>';
    return;
}

/* Purge expired */
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$table} WHERE user_id = %d AND expires_at < NOW()",
    $user_id
) );

$items = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$table} WHERE user_id = %d ORDER BY source_blog_id ASC, added_at DESC",
    $user_id
), ARRAY_A );

if ( empty( $items ) ) {
    $checkout_host = new ZNC_Checkout_Host();
    echo '<div class="znc-cart-empty">';
    echo '<div class="znc-cart-empty-icon">🛒</div>';
    echo '<h3>' . esc_html__( 'Your cart is empty', 'zinckles-net-cart' ) . '</h3>';
    echo '<p>' . esc_html__( 'Add products from any shop across the network.', 'zinckles-net-cart' ) . '</p>';
    echo '<a href="' . esc_url( $checkout_host->get_cart_url() ) . '" class="znc-browse-shops-btn">' . esc_html__( 'View Cart', 'zinckles-net-cart' ) . '</a>';
    echo '</div>';
    return;
}

/* ── Aggregate data ───────────────────────────────────────────── */
$shops       = [];
$grand_total = 0;
$total_items = 0;
$currencies  = [];
$base_currency = get_site_option( 'znc_base_currency', 'USD' );

foreach ( $items as $item ) {
    $blog_id = (int) $item['source_blog_id'];
    if ( ! isset( $shops[ $blog_id ] ) ) {
        $shops[ $blog_id ] = [
            'name'     => $item['shop_name'] ?: 'Shop #' . $blog_id,
            'url'      => $item['shop_url'] ?: '',
            'currency' => $item['currency'] ?: 'USD',
            'items'    => [],
            'subtotal' => 0,
        ];
    }
    $line_total = (float) $item['product_price'] * (int) $item['quantity'];
    $shops[ $blog_id ]['items'][]  = $item;
    $shops[ $blog_id ]['subtotal'] += $line_total;
    $grand_total += $line_total;
    $total_items += (int) $item['quantity'];

    $cur = $item['currency'] ?: 'USD';
    if ( ! isset( $currencies[ $cur ] ) ) $currencies[ $cur ] = 0;
    $currencies[ $cur ] += $line_total;
}

$nonce = wp_create_nonce( 'znc_checkout_action' );

/* ── Points systems ───────────────────────────────────────────── */
$points_systems = [];

if ( class_exists( 'ZNC_MyCred_Engine' ) ) {
    $mycred_engine = new ZNC_MyCred_Engine();
    $mycred_config = $mycred_engine->get_types_config();
    foreach ( $mycred_config as $slug => $config ) {
        if ( empty( $config['enabled'] ) ) continue;
        $balance = $mycred_engine->get_balance( $user_id, $slug );
        $rate    = (float) ( $config['exchange_rate'] ?? 0 );
        if ( $balance > 0 && $rate > 0 ) {
            $max_pct  = (int) ( $config['max_percent'] ?? 100 );
            $max_val  = ( $grand_total * $max_pct / 100 );
            $max_pts  = floor( $max_val / $rate );
            $max_pts  = min( $max_pts, $balance );
            $points_systems[] = [
                'engine'  => 'mycred',
                'slug'    => $slug,
                'label'   => $config['info']['label'] ?? $slug,
                'icon'    => '⚡',
                'balance' => $balance,
                'rate'    => $rate,
                'max_pts' => $max_pts,
                'max_val' => $max_pts * $rate,
                'max_pct' => $max_pct,
            ];
        }
    }
}

if ( class_exists( 'ZNC_GamiPress_Engine' ) ) {
    $gp_engine = new ZNC_GamiPress_Engine();
    $gp_config = $gp_engine->get_types_config();
    foreach ( $gp_config as $slug => $config ) {
        if ( empty( $config['enabled'] ) ) continue;
        $balance = $gp_engine->get_balance( $user_id, $slug );
        $rate    = (float) ( $config['exchange_rate'] ?? 0 );
        if ( $balance > 0 && $rate > 0 ) {
            $max_pct  = (int) ( $config['max_percent'] ?? 100 );
            $max_val  = ( $grand_total * $max_pct / 100 );
            $max_pts  = floor( $max_val / $rate );
            $max_pts  = min( $max_pts, $balance );
            $points_systems[] = [
                'engine'  => 'gamipress',
                'slug'    => $slug,
                'label'   => $config['info']['label'] ?? $slug,
                'icon'    => '🏆',
                'balance' => $balance,
                'rate'    => $rate,
                'max_pts' => $max_pts,
                'max_val' => $max_pts * $rate,
                'max_pct' => $max_pct,
            ];
        }
    }
}

/* ── Pre-fill billing from WC customer ────────────────────────── */
$billing = [
    'first_name' => get_user_meta( $user_id, 'billing_first_name', true ) ?: $user->first_name,
    'last_name'  => get_user_meta( $user_id, 'billing_last_name', true )  ?: $user->last_name,
    'email'      => get_user_meta( $user_id, 'billing_email', true )      ?: $user->user_email,
    'phone'      => get_user_meta( $user_id, 'billing_phone', true ),
    'address_1'  => get_user_meta( $user_id, 'billing_address_1', true ),
    'address_2'  => get_user_meta( $user_id, 'billing_address_2', true ),
    'city'       => get_user_meta( $user_id, 'billing_city', true ),
    'state'      => get_user_meta( $user_id, 'billing_state', true ),
    'postcode'   => get_user_meta( $user_id, 'billing_postcode', true ),
    'country'    => get_user_meta( $user_id, 'billing_country', true ) ?: 'US',
];
?>

<div class="znc-checkout" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-grand-total="<?php echo esc_attr( $grand_total ); ?>">

    <h2><?php esc_html_e( '🛍️ Checkout', 'zinckles-net-cart' ); ?></h2>

    <div class="znc-checkout-grid">

        <!-- LEFT: Billing & Payment -->
        <div class="znc-checkout-left">

            <!-- Billing Details -->
            <div class="card" style="background:#fff;border:1px solid var(--znc-border,#e5e7eb);border-radius:12px;padding:24px;margin-bottom:20px;">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;"><?php esc_html_e( 'Billing Details', 'zinckles-net-cart' ); ?></h3>
                <form id="znc-checkout-form" class="znc-checkout-form">
                    <input type="hidden" name="action" value="znc_place_order">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>">

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="znc-field">
                            <label for="znc-first-name"><?php esc_html_e( 'First Name', 'zinckles-net-cart' ); ?> *</label>
                            <input type="text" id="znc-first-name" name="billing_first_name" value="<?php echo esc_attr( $billing['first_name'] ); ?>" required>
                        </div>
                        <div class="znc-field">
                            <label for="znc-last-name"><?php esc_html_e( 'Last Name', 'zinckles-net-cart' ); ?> *</label>
                            <input type="text" id="znc-last-name" name="billing_last_name" value="<?php echo esc_attr( $billing['last_name'] ); ?>" required>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="znc-field">
                            <label for="znc-email"><?php esc_html_e( 'Email', 'zinckles-net-cart' ); ?> *</label>
                            <input type="email" id="znc-email" name="billing_email" value="<?php echo esc_attr( $billing['email'] ); ?>" required>
                        </div>
                        <div class="znc-field">
                            <label for="znc-phone"><?php esc_html_e( 'Phone', 'zinckles-net-cart' ); ?></label>
                            <input type="tel" id="znc-phone" name="billing_phone" value="<?php echo esc_attr( $billing['phone'] ); ?>">
                        </div>
                    </div>

                    <div class="znc-field">
                        <label for="znc-address1"><?php esc_html_e( 'Address', 'zinckles-net-cart' ); ?> *</label>
                        <input type="text" id="znc-address1" name="billing_address_1" value="<?php echo esc_attr( $billing['address_1'] ); ?>" required>
                    </div>

                    <div class="znc-field">
                        <label for="znc-address2"><?php esc_html_e( 'Address Line 2', 'zinckles-net-cart' ); ?></label>
                        <input type="text" id="znc-address2" name="billing_address_2" value="<?php echo esc_attr( $billing['address_2'] ); ?>">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div class="znc-field">
                            <label for="znc-city"><?php esc_html_e( 'City', 'zinckles-net-cart' ); ?> *</label>
                            <input type="text" id="znc-city" name="billing_city" value="<?php echo esc_attr( $billing['city'] ); ?>" required>
                        </div>
                        <div class="znc-field">
                            <label for="znc-state"><?php esc_html_e( 'State / Province', 'zinckles-net-cart' ); ?></label>
                            <input type="text" id="znc-state" name="billing_state" value="<?php echo esc_attr( $billing['state'] ); ?>">
                        </div>
                        <div class="znc-field">
                            <label for="znc-postcode"><?php esc_html_e( 'Postal Code', 'zinckles-net-cart' ); ?> *</label>
                            <input type="text" id="znc-postcode" name="billing_postcode" value="<?php echo esc_attr( $billing['postcode'] ); ?>" required>
                        </div>
                    </div>

                    <div class="znc-field">
                        <label for="znc-country"><?php esc_html_e( 'Country', 'zinckles-net-cart' ); ?> *</label>
                        <select id="znc-country" name="billing_country">
                            <?php
                            $countries = WC()->countries->get_countries();
                            foreach ( $countries as $code => $name ) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    esc_attr( $code ),
                                    selected( $billing['country'], $code, false ),
                                    esc_html( $name )
                                );
                            }
                            ?>
                        </select>
                    </div>

                    <div class="znc-field">
                        <label for="znc-notes"><?php esc_html_e( 'Order Notes', 'zinckles-net-cart' ); ?></label>
                        <textarea id="znc-notes" name="order_notes" rows="3" placeholder="<?php esc_attr_e( 'Notes about your order, e.g. special delivery instructions.', 'zinckles-net-cart' ); ?>"></textarea>
                    </div>
                </form>
            </div>

            <?php if ( ! empty( $points_systems ) ) : ?>
            <!-- Points Redemption -->
            <div class="znc-points-section">
                <h4><?php esc_html_e( 'Redeem Points', 'zinckles-net-cart' ); ?></h4>
                <?php foreach ( $points_systems as $i => $ps ) : ?>
                    <div class="znc-points-type" data-engine="<?php echo esc_attr( $ps['engine'] ); ?>" data-slug="<?php echo esc_attr( $ps['slug'] ); ?>" data-rate="<?php echo esc_attr( $ps['rate'] ); ?>">
                        <div class="znc-points-type-header">
                            <span class="znc-points-type-name"><?php echo esc_html( $ps['icon'] . ' ' . $ps['label'] ); ?></span>
                            <span class="znc-points-type-balance"><?php echo esc_html( number_format( $ps['balance'] ) . ' available' ); ?></span>
                        </div>
                        <input type="range" class="znc-points-slider" name="points_<?php echo esc_attr( $ps['engine'] . '_' . $ps['slug'] ); ?>" min="0" max="<?php echo esc_attr( $ps['max_pts'] ); ?>" value="0" step="1">
                        <div class="znc-points-value">
                            <span class="znc-pts-label">0 <?php echo esc_html( $ps['label'] ); ?></span>
                            <span class="znc-pts-currency"><?php echo esc_html( $base_currency ); ?> 0.00</span>
                        </div>
                        <input type="hidden" name="redeem_<?php echo esc_attr( $ps['engine'] . '_' . $ps['slug'] ); ?>" value="0">
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Payment Methods -->
            <div class="znc-payment-methods" style="background:#fff;border:1px solid var(--znc-border,#e5e7eb);border-radius:12px;padding:24px;margin-top:20px;">
                <h4><?php esc_html_e( 'Payment Method', 'zinckles-net-cart' ); ?></h4>

                <?php
                $available_gateways = [];
                if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
                    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                }

                if ( ! empty( $available_gateways ) ) :
                    $first = true;
                    foreach ( $available_gateways as $gateway_id => $gateway ) :
                ?>
                    <label class="znc-payment-option <?php echo $first ? 'active' : ''; ?>">
                        <input type="radio" name="payment_method" value="<?php echo esc_attr( $gateway_id ); ?>" <?php checked( $first ); ?>>
                        <span class="znc-payment-label"><?php echo esc_html( $gateway->get_title() ); ?></span>
                        <?php if ( $gateway->get_icon() ) echo wp_kses_post( $gateway->get_icon() ); ?>
                    </label>
                <?php
                        $first = false;
                    endforeach;
                else :
                ?>
                    <div class="znc-notice znc-notice-warning" style="margin:0;">
                        <p><?php esc_html_e( 'No payment methods configured. Please set up WooCommerce payment gateways.', 'zinckles-net-cart' ); ?></p>
                    </div>
                <?php endif; ?>

                <?php if ( ! empty( $points_systems ) ) : ?>
                    <label class="znc-payment-option">
                        <input type="radio" name="payment_method" value="znc_points_only">
                        <span class="znc-payment-label"><?php esc_html_e( '⚡ Pay 100% with Points', 'zinckles-net-cart' ); ?></span>
                    </label>
                <?php endif; ?>
            </div>

        </div>

        <!-- RIGHT: Order Summary -->
        <div class="znc-checkout-order-summary">
            <h3><?php esc_html_e( 'Order Summary', 'zinckles-net-cart' ); ?></h3>

            <?php foreach ( $shops as $blog_id => $shop ) : ?>
                <div style="margin-bottom:12px;">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                        <span class="znc-shop-avatar" style="width:22px;height:22px;font-size:10px;">
                            <?php echo esc_html( strtoupper( substr( $shop['name'], 0, 1 ) ) ); ?>
                        </span>
                        <strong style="font-size:13px;"><?php echo esc_html( $shop['name'] ); ?></strong>
                        <span class="znc-currency-chip" style="font-size:10px;padding:2px 6px;"><?php echo esc_html( $shop['currency'] ); ?></span>
                    </div>

                    <?php foreach ( $shop['items'] as $item ) :
                        $line_total = (float) $item['product_price'] * (int) $item['quantity'];
                    ?>
                        <div class="znc-checkout-item">
                            <?php if ( ! empty( $item['product_image'] ) ) : ?>
                                <img class="znc-checkout-item-thumb" src="<?php echo esc_url( $item['product_image'] ); ?>" alt="">
                            <?php else : ?>
                                <div class="znc-checkout-item-thumb" style="background:var(--znc-purple-lt,#ede9fe);display:flex;align-items:center;justify-content:center;font-size:16px;">📦</div>
                            <?php endif; ?>
                            <div class="znc-checkout-item-info">
                                <div class="znc-checkout-item-name"><?php echo esc_html( $item['product_name'] ); ?></div>
                                <div class="znc-checkout-item-shop"><?php echo esc_html( 'Qty: ' . (int) $item['quantity'] ); ?></div>
                            </div>
                            <div class="znc-checkout-item-price">
                                <?php echo esc_html( $item['currency'] . ' ' . number_format( $line_total, 2 ) ); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div style="border-top:1px solid var(--znc-border,#e5e7eb);margin-top:12px;padding-top:12px;">
                <?php if ( count( $currencies ) > 1 ) : ?>
                    <?php foreach ( $currencies as $cur => $amount ) : ?>
                        <div class="znc-total-row" style="font-size:13px;">
                            <span><?php echo esc_html( $cur ); ?></span>
                            <span><?php echo esc_html( $cur . ' ' . number_format( $amount, 2 ) ); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="znc-total-row" style="font-size:13px;">
                    <span><?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?></span>
                    <span><?php echo esc_html( $base_currency . ' ' . number_format( $grand_total, 2 ) ); ?></span>
                </div>

                <div class="znc-total-row znc-points-deduction" id="znc-points-discount-row" style="display:none;font-size:13px;">
                    <span><?php esc_html_e( 'Points Discount', 'zinckles-net-cart' ); ?></span>
                    <span id="znc-points-discount-val">- <?php echo esc_html( $base_currency ); ?> 0.00</span>
                </div>

                <div class="znc-total-row znc-total-final">
                    <span><?php esc_html_e( 'Total', 'zinckles-net-cart' ); ?></span>
                    <span id="znc-checkout-total"><?php echo esc_html( $base_currency . ' ' . number_format( $grand_total, 2 ) ); ?></span>
                </div>
            </div>

            <button type="submit" form="znc-checkout-form" class="znc-place-order-btn" id="znc-place-order-btn">
                <?php esc_html_e( 'Place Order', 'zinckles-net-cart' ); ?>
            </button>

            <p style="text-align:center;font-size:11px;color:var(--znc-muted,#6b7280);margin-top:12px;">
                <?php esc_html_e( 'Your order will create separate sub-orders for each shop.', 'zinckles-net-cart' ); ?>
            </p>
        </div>

    </div>

</div>

<script>
(function(){
    /* Points slider live update */
    var sliders = document.querySelectorAll('.znc-points-slider');
    var grandTotal = parseFloat(document.querySelector('.znc-checkout').dataset.grandTotal) || 0;
    var baseCurrency = '<?php echo esc_js( $base_currency ); ?>';

    function updatePointsTotals() {
        var totalDiscount = 0;
        sliders.forEach(function(slider) {
            var pts = parseInt(slider.value) || 0;
            var rate = parseFloat(slider.closest('.znc-points-type').dataset.rate) || 0;
            var val = pts * rate;
            totalDiscount += val;

            var container = slider.closest('.znc-points-type');
            var label = container.querySelector('.znc-pts-label');
            var currLabel = container.querySelector('.znc-pts-currency');
            var hidden = container.querySelector('input[type="hidden"]');
            var typeName = container.querySelector('.znc-points-type-name').textContent.replace(/[⚡🏆]\s*/, '');

            label.textContent = pts.toLocaleString() + ' ' + typeName.trim();
            currLabel.textContent = baseCurrency + ' ' + val.toFixed(2);
            hidden.value = pts;
        });

        var discountRow = document.getElementById('znc-points-discount-row');
        var discountVal = document.getElementById('znc-points-discount-val');
        var totalEl     = document.getElementById('znc-checkout-total');

        if (totalDiscount > 0) {
            discountRow.style.display = 'flex';
            discountVal.textContent = '- ' + baseCurrency + ' ' + totalDiscount.toFixed(2);
        } else {
            discountRow.style.display = 'none';
        }

        var finalTotal = Math.max(0, grandTotal - totalDiscount);
        totalEl.textContent = baseCurrency + ' ' + finalTotal.toFixed(2);

        /* Toggle points-only payment visibility */
        var pointsOnlyRadio = document.querySelector('input[value="znc_points_only"]');
        if (pointsOnlyRadio) {
            var parentLabel = pointsOnlyRadio.closest('.znc-payment-option');
            if (totalDiscount >= grandTotal) {
                parentLabel.style.display = 'flex';
            } else {
                parentLabel.style.display = totalDiscount > 0 ? 'flex' : 'none';
                if (pointsOnlyRadio.checked && totalDiscount < grandTotal) {
                    pointsOnlyRadio.checked = false;
                    var first = document.querySelector('.znc-payment-option input[type="radio"]');
                    if (first) { first.checked = true; first.closest('.znc-payment-option').classList.add('active'); }
                }
            }
        }
    }

    sliders.forEach(function(slider) {
        slider.addEventListener('input', updatePointsTotals);
    });

    /* Payment method toggle */
    document.querySelectorAll('.znc-payment-option').forEach(function(opt) {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.znc-payment-option').forEach(function(o) { o.classList.remove('active'); });
            opt.classList.add('active');
            opt.querySelector('input[type="radio"]').checked = true;
        });
    });

    /* Place order */
    var form = document.getElementById('znc-checkout-form');
    var btn  = document.getElementById('znc-place-order-btn');

    if (btn && form) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Processing...';

            var formData = new FormData(form);

            /* Add points data */
            document.querySelectorAll('.znc-points-type input[type="hidden"]').forEach(function(h) {
                formData.append(h.name, h.value);
            });

            /* Add selected payment method */
            var pm = document.querySelector('input[name="payment_method"]:checked');
            if (pm) formData.append('payment_method', pm.value);

            formData.append('action', 'znc_place_order');
            formData.append('nonce', '<?php echo esc_js( $nonce ); ?>');

            fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.data && data.data.redirect) {
                        window.location.href = data.data.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js( __( 'Place Order', 'zinckles-net-cart' ) ); ?>';
                    alert(data.data && data.data.message ? data.data.message : 'Checkout failed. Please try again.');
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js( __( 'Place Order', 'zinckles-net-cart' ) ); ?>';
                alert('Network error. Please try again.');
                console.error('[ZNC Checkout]', err);
            });
        });
    }

    /* Init points-only visibility */
    updatePointsTotals();
})();
</script>
