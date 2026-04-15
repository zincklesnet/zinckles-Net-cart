<?php
/**
 * Global Cart Template — v1.5.0
 * Reads from wp_usermeta via ZNC_Cart_Snapshot — zero switch_to_blog().
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
    echo '<div class="znc-login-required"><p>Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to view your global cart.</p></div>';
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

$checkout_url = '';
if ( isset( $GLOBALS['znc_checkout_host'] ) ) {
    $checkout_url = $GLOBALS['znc_checkout_host']->get_checkout_url();
} elseif ( function_exists( 'wc_get_checkout_url' ) ) {
    $checkout_url = wc_get_checkout_url();
}
?>

<div class="znc-global-cart" id="znc-global-cart">

    <div class="znc-cart-header">
        <h2>🛒 Global Net Cart</h2>
        <div class="znc-cart-stats">
            <span class="znc-stat"><strong class="znc-global-cart-count"><?php echo $count; ?></strong> items</span>
            <span class="znc-stat"><strong><?php echo $shops; ?></strong> shops</span>
            <span class="znc-stat">Total: <strong><?php echo esc_html( $symbol . number_format( $total, 2 ) ); ?></strong></span>
        </div>
    </div>

    <?php if ( empty( $grouped ) ) : ?>
        <div class="znc-cart-empty">
            <div class="znc-empty-icon">🛒</div>
            <h3>Your global cart is empty</h3>
            <p>Browse our shops and add products to get started!</p>
        </div>
    <?php else : ?>

        <?php foreach ( $grouped as $blog_id => $group ) :
            $shop_total = 0;
            foreach ( $group['items'] as $item ) {
                $qty   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
                $price = isset( $item['price'] ) ? (float) $item['price'] : 0;
                $shop_total += $qty * $price;
            }
        ?>
        <div class="znc-shop-group" data-blog-id="<?php echo esc_attr( $blog_id ); ?>">
            <div class="znc-shop-header">
                <h3>
                    <a href="<?php echo esc_url( $group['shop_url'] ); ?>" target="_blank">
                        <?php echo esc_html( $group['shop_name'] ); ?>
                    </a>
                    <span class="znc-shop-badge"><?php echo esc_html( $group['currency'] ); ?></span>
                </h3>
                <span class="znc-shop-subtotal"><?php echo esc_html( $symbol . number_format( $shop_total, 2 ) ); ?></span>
            </div>

            <div class="znc-shop-items">
                <?php foreach ( $group['items'] as $item_key => $item ) :
                    $name     = isset( $item['product_name'] ) ? $item['product_name'] : 'Product';
                    $qty      = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
                    $price    = isset( $item['price'] ) ? (float) $item['price'] : 0;
                    $img      = isset( $item['image_url'] ) && $item['image_url'] ? $item['image_url'] : '';
                    $link     = isset( $item['permalink'] ) ? $item['permalink'] : '#';
                    $sku      = isset( $item['sku'] ) && $item['sku'] ? $item['sku'] : '';
                    $in_stock = isset( $item['in_stock'] ) ? (int) $item['in_stock'] : 1;
                    $var_data = isset( $item['variation_data'] ) ? $item['variation_data'] : array();
                    $line     = $qty * $price;
                ?>
                <div class="znc-cart-item" data-item-key="<?php echo esc_attr( $item_key ); ?>" id="znc-item-<?php echo esc_attr( $item_key ); ?>">
                    <div class="znc-item-image">
                        <?php if ( $img ) : ?>
                            <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $name ); ?>" width="80" height="80">
                        <?php else : ?>
                            <div class="znc-no-image">📦</div>
                        <?php endif; ?>
                    </div>

                    <div class="znc-item-details">
                        <h4><a href="<?php echo esc_url( $link ); ?>" target="_blank"><?php echo esc_html( $name ); ?></a></h4>
                        <?php if ( $sku ) : ?>
                            <span class="znc-sku">SKU: <?php echo esc_html( $sku ); ?></span>
                        <?php endif; ?>
                        <?php if ( is_array( $var_data ) && ! empty( $var_data ) ) : ?>
                            <div class="znc-variations">
                                <?php foreach ( $var_data as $attr => $val ) : ?>
                                    <span class="znc-var"><?php echo esc_html( str_replace( 'attribute_', '', $attr ) ); ?>: <?php echo esc_html( $val ); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! $in_stock ) : ?>
                            <span class="znc-out-of-stock">⚠ Out of Stock</span>
                        <?php endif; ?>
                    </div>

                    <div class="znc-item-price">
                        <span class="znc-unit-price"><?php echo esc_html( $symbol . number_format( $price, 2 ) ); ?></span>
                    </div>

                    <div class="znc-item-qty">
                        <button class="znc-qty-btn znc-qty-minus" data-item-key="<?php echo esc_attr( $item_key ); ?>" data-action="decrease">−</button>
                        <input type="number" class="znc-qty-input" value="<?php echo esc_attr( $qty ); ?>" min="1" max="99" data-item-key="<?php echo esc_attr( $item_key ); ?>">
                        <button class="znc-qty-btn znc-qty-plus" data-item-key="<?php echo esc_attr( $item_key ); ?>" data-action="increase">+</button>
                    </div>

                    <div class="znc-item-line-total">
                        <strong><?php echo esc_html( $symbol . number_format( $line, 2 ) ); ?></strong>
                    </div>

                    <div class="znc-item-remove">
                        <button class="znc-remove-btn" data-item-key="<?php echo esc_attr( $item_key ); ?>" title="Remove item">✕</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="znc-cart-footer">
            <div class="znc-cart-actions">
                <button class="znc-clear-cart-btn" id="znc-clear-cart">🗑 Clear Cart</button>
            </div>
            <div class="znc-cart-totals">
                <div class="znc-grand-total">
                    <span>Grand Total:</span>
                    <strong id="znc-grand-total"><?php echo esc_html( $symbol . number_format( $total, 2 ) ); ?></strong>
                </div>
                <?php if ( $checkout_url ) : ?>
                    <a href="<?php echo esc_url( $checkout_url ); ?>" class="znc-checkout-btn">Proceed to Checkout →</a>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div>
