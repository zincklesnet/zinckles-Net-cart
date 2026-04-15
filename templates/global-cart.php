<?php
/**
 * Template: Global Cart
 *
 * Renders the unified cross-site cart with items grouped by shop,
 * currency/points breakdowns, quantity controls, and totals.
 *
 * Shortcode: [znc_global_cart]
 *
 * @package ZincklesNetCart
 * @since   1.4.0
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    echo '<div class="znc-notice znc-notice-info">';
    echo '<p>' . esc_html__( 'Please log in to view your global cart.', 'zinckles-net-cart' ) . '</p>';
    echo '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="znc-checkout-btn" style="max-width:200px;margin:12px auto 0;">' . esc_html__( 'Log In', 'zinckles-net-cart' ) . '</a>';
    echo '</div>';
    return;
}

$user_id = get_current_user_id();

/* Get cart items from global store */
global $wpdb;
$table = $wpdb->prefix . 'znc_global_cart';

/* Check table exists */
$table_exists = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
    DB_NAME, $table
) );

if ( ! $table_exists ) {
    echo '<div class="znc-notice znc-notice-warning">';
    echo '<p>' . esc_html__( 'Global cart tables not initialized. Please deactivate and reactivate Zinckles Net Cart.', 'zinckles-net-cart' ) . '</p>';
    echo '</div>';
    return;
}

/* Fetch items, purge expired */
$wpdb->query( $wpdb->prepare(
    "DELETE FROM {$table} WHERE user_id = %d AND expires_at < NOW()",
    $user_id
) );

$items = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$table} WHERE user_id = %d ORDER BY source_blog_id ASC, added_at DESC",
    $user_id
), ARRAY_A );

/* Build nonce for AJAX actions */
$nonce = wp_create_nonce( 'znc_cart_action' );

/* Checkout URL */
$checkout_host = new ZNC_Checkout_Host();
$checkout_url  = $checkout_host->get_checkout_url();

?>
<div class="znc-global-cart" data-nonce="<?php echo esc_attr( $nonce ); ?>">

    <h2><?php esc_html_e( '🛒 Global Net Cart', 'zinckles-net-cart' ); ?></h2>

    <?php if ( empty( $items ) ) : ?>

        <div class="znc-cart-empty">
            <div class="znc-cart-empty-icon">🛒</div>
            <h3><?php esc_html_e( 'Your global cart is empty', 'zinckles-net-cart' ); ?></h3>
            <p><?php esc_html_e( 'Browse shops across the network and add products to get started.', 'zinckles-net-cart' ); ?></p>
            <a href="<?php echo esc_url( network_home_url() ); ?>" class="znc-browse-shops-btn">
                <?php esc_html_e( 'Browse Shops', 'zinckles-net-cart' ); ?>
            </a>
        </div>

    <?php else :

        /* Group items by source_blog_id */
        $shops = [];
        $grand_total = 0;
        $total_items = 0;
        $currencies  = [];

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

        $shop_count = count( $shops );
    ?>

        <!-- Stats Bar -->
        <div class="znc-cart-stats">
            <div class="znc-cart-stat">
                <div class="znc-cart-stat-value"><?php echo esc_html( $total_items ); ?></div>
                <div class="znc-cart-stat-label"><?php esc_html_e( 'Items', 'zinckles-net-cart' ); ?></div>
            </div>
            <div class="znc-cart-stat">
                <div class="znc-cart-stat-value"><?php echo esc_html( $shop_count ); ?></div>
                <div class="znc-cart-stat-label"><?php esc_html_e( 'Shops', 'zinckles-net-cart' ); ?></div>
            </div>
            <div class="znc-cart-stat">
                <div class="znc-cart-stat-value"><?php echo esc_html( count( $currencies ) ); ?></div>
                <div class="znc-cart-stat-label"><?php esc_html_e( 'Currencies', 'zinckles-net-cart' ); ?></div>
            </div>
            <div class="znc-cart-stat">
                <div class="znc-cart-stat-value">
                    <?php
                    $base_currency = get_site_option( 'znc_base_currency', 'USD' );
                    echo esc_html( $base_currency . ' ' . number_format( $grand_total, 2 ) );
                    ?>
                </div>
                <div class="znc-cart-stat-label"><?php esc_html_e( 'Total', 'zinckles-net-cart' ); ?></div>
            </div>
        </div>

        <!-- Shop Groups -->
        <?php foreach ( $shops as $blog_id => $shop ) : ?>
            <div class="znc-shop-group" data-blog-id="<?php echo esc_attr( $blog_id ); ?>">

                <div class="znc-shop-header">
                    <div class="znc-shop-avatar">
                        <?php echo esc_html( strtoupper( substr( $shop['name'], 0, 1 ) ) ); ?>
                    </div>
                    <div>
                        <div class="znc-shop-name"><?php echo esc_html( $shop['name'] ); ?></div>
                        <?php if ( $shop['url'] ) : ?>
                            <div class="znc-shop-url"><?php echo esc_html( $shop['url'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="znc-shop-meta">
                        <span class="znc-currency-chip"><?php echo esc_html( $shop['currency'] ); ?></span>
                        <span class="znc-currency-chip"><?php echo esc_html( count( $shop['items'] ) ); ?> items</span>
                    </div>
                </div>

                <ul class="znc-cart-items">
                    <?php foreach ( $shop['items'] as $item ) :
                        $line_total = (float) $item['product_price'] * (int) $item['quantity'];
                        $variations = json_decode( $item['variation_data'] ?? '{}', true );
                    ?>
                        <li class="znc-cart-item" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">

                            <?php if ( ! empty( $item['product_image'] ) ) : ?>
                                <img class="znc-item-image" src="<?php echo esc_url( $item['product_image'] ); ?>" alt="<?php echo esc_attr( $item['product_name'] ); ?>">
                            <?php else : ?>
                                <div class="znc-item-image-placeholder">📦</div>
                            <?php endif; ?>

                            <div class="znc-item-info">
                                <div class="znc-item-name">
                                    <?php if ( ! empty( $item['product_url'] ) ) : ?>
                                        <a href="<?php echo esc_url( $item['product_url'] ); ?>" target="_blank">
                                            <?php echo esc_html( $item['product_name'] ); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo esc_html( $item['product_name'] ); ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ( ! empty( $item['product_sku'] ) ) : ?>
                                    <div class="znc-item-sku">SKU: <?php echo esc_html( $item['product_sku'] ); ?></div>
                                <?php endif; ?>
                                <?php if ( ! empty( $variations ) && is_array( $variations ) ) : ?>
                                    <div class="znc-item-variation">
                                        <?php
                                        $parts = [];
                                        foreach ( $variations as $attr => $val ) {
                                            $label = str_replace( 'attribute_', '', $attr );
                                            $label = ucfirst( str_replace( [ 'pa_', '-', '_' ], [ '', ' ', ' ' ], $label ) );
                                            $parts[] = $label . ': ' . $val;
                                        }
                                        echo esc_html( implode( ' | ', $parts ) );
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="znc-item-price">
                                <?php echo esc_html( $item['currency'] . ' ' . number_format( (float) $item['product_price'], 2 ) ); ?>
                            </div>

                            <div class="znc-item-qty">
                                <button class="znc-qty-btn znc-qty-minus" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" data-dir="minus">−</button>
                                <input type="number" class="znc-qty-input" value="<?php echo esc_attr( (int) $item['quantity'] ); ?>" min="1" max="<?php echo esc_attr( $item['stock_qty'] ?: 999 ); ?>" data-item-id="<?php echo esc_attr( $item['id'] ); ?>">
                                <button class="znc-qty-btn znc-qty-plus" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" data-dir="plus">+</button>
                            </div>

                            <div class="znc-item-actions">
                                <button class="znc-remove-btn" data-item-id="<?php echo esc_attr( $item['id'] ); ?>" title="<?php esc_attr_e( 'Remove item', 'zinckles-net-cart' ); ?>">✕</button>
                            </div>

                        </li>
                    <?php endforeach; ?>
                </ul>

                <div style="padding:12px 20px;text-align:right;border-top:1px solid var(--znc-border);">
                    <strong><?php esc_html_e( 'Shop Subtotal:', 'zinckles-net-cart' ); ?></strong>
                    <?php echo esc_html( $shop['currency'] . ' ' . number_format( $shop['subtotal'], 2 ) ); ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Cart Totals -->
        <div class="znc-cart-totals">
            <h3><?php esc_html_e( 'Cart Totals', 'zinckles-net-cart' ); ?></h3>

            <?php if ( count( $currencies ) > 1 ) : ?>
                <?php foreach ( $currencies as $cur => $amount ) : ?>
                    <div class="znc-total-row">
                        <span><?php echo esc_html( $cur ); ?> <?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?></span>
                        <span><?php echo esc_html( $cur . ' ' . number_format( $amount, 2 ) ); ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="znc-total-row" style="font-size:12px;color:var(--znc-muted);">
                    <span><?php esc_html_e( 'Converted to base currency', 'zinckles-net-cart' ); ?></span>
                    <span><?php echo esc_html( $base_currency ); ?></span>
                </div>
            <?php else : ?>
                <div class="znc-total-row">
                    <span><?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?></span>
                    <span><?php echo esc_html( $base_currency . ' ' . number_format( $grand_total, 2 ) ); ?></span>
                </div>
            <?php endif; ?>

            <?php
            /* Show points balances if MyCred / GamiPress available */
            if ( class_exists( 'ZNC_MyCred_Engine' ) ) {
                $mycred_engine = new ZNC_MyCred_Engine();
                $types_config  = $mycred_engine->get_types_config();
                foreach ( $types_config as $slug => $config ) {
                    if ( empty( $config['enabled'] ) ) continue;
                    $balance = $mycred_engine->get_balance( $user_id, $slug );
                    $label   = $config['info']['label'] ?? $slug;
                    $rate    = $config['exchange_rate'] ?? 0;
                    if ( $balance > 0 && $rate > 0 ) {
                        $max_deduct = min( $balance, $grand_total / $rate );
                        echo '<div class="znc-total-row znc-points-deduction">';
                        echo '<span>⚡ ' . esc_html( $label ) . ' (' . number_format( $balance ) . ' available)</span>';
                        echo '<span>−' . esc_html( $base_currency . ' ' . number_format( $max_deduct * $rate, 2 ) ) . '</span>';
                        echo '</div>';
                    }
                }
            }

            if ( class_exists( 'ZNC_GamiPress_Engine' ) ) {
                $gp_engine = new ZNC_GamiPress_Engine();
                $gp_types  = $gp_engine->get_types_config();
                foreach ( $gp_types as $slug => $config ) {
                    if ( empty( $config['enabled'] ) ) continue;
                    $balance = $gp_engine->get_balance( $user_id, $slug );
                    $label   = $config['info']['label'] ?? $slug;
                    $rate    = $config['exchange_rate'] ?? 0;
                    if ( $balance > 0 && $rate > 0 ) {
                        echo '<div class="znc-total-row znc-points-deduction">';
                        echo '<span>🏆 ' . esc_html( $label ) . ' (' . number_format( $balance ) . ' available)</span>';
                        echo '<span>−' . esc_html( $base_currency . ' ' . number_format( min( $balance, $grand_total / $rate ) * $rate, 2 ) ) . '</span>';
                        echo '</div>';
                    }
                }
            }
            ?>

            <div class="znc-total-row znc-total-final">
                <span><?php esc_html_e( 'Total', 'zinckles-net-cart' ); ?></span>
                <span><?php echo esc_html( $base_currency . ' ' . number_format( $grand_total, 2 ) ); ?></span>
            </div>

            <a href="<?php echo esc_url( $checkout_url ); ?>" class="znc-checkout-btn">
                <?php esc_html_e( 'Proceed to Checkout', 'zinckles-net-cart' ); ?>
            </a>

            <a href="<?php echo esc_url( network_home_url() ); ?>" class="znc-continue-shopping">
                <?php esc_html_e( '← Continue Shopping', 'zinckles-net-cart' ); ?>
            </a>
        </div>

    <?php endif; ?>

</div>
