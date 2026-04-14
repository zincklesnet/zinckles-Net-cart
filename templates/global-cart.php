<?php
defined( 'ABSPATH' ) || exit;
$user_id = get_current_user_id();
$store   = new ZNC_Global_Cart_Store();
$currency = new ZNC_Currency_Handler();
$merger  = new ZNC_Global_Cart_Merger( $store, $currency );
$mycred  = new ZNC_MyCred_Engine();
$cart    = $merger->get_cart_with_totals( $user_id );
$items   = $cart['items'];
$totals  = $cart['totals'];
$stats   = $cart['stats'];
$settings = get_option( 'znc_main_settings', array() );
$layout  = $settings['layout_style'] ?? 'grouped';
$by_site = array();
foreach ( $items as $item ) {
    $by_site[ $item['site_id'] ][] = $item;
}
?>
<div class="znc-global-cart" data-layout="<?php echo esc_attr( $layout ); ?>">
    <?php if ( empty( $items ) ) : ?>
        <div class="znc-empty-cart">
            <div class="znc-empty-icon">&#128722;</div>
            <p><?php echo esc_html( $settings['empty_cart_message'] ?? 'Your Net Cart is empty. Browse our shops to find something you love!' ); ?></p>
        </div>
    <?php else : ?>
        <div class="znc-cart-header">
            <h2><?php printf( esc_html__( 'Your Net Cart (%d items from %d shops)', 'zinckles-net-cart' ), $stats['item_count'], $stats['shop_count'] ); ?></h2>
        </div>

        <?php if ( 'tabbed' === $layout ) : ?>
        <div class="znc-tabs">
            <?php foreach ( $by_site as $site_id => $site_items ) :
                $display = apply_filters( 'znc_shop_display', array(), $site_id );
            ?>
            <button class="znc-tab" data-site="<?php echo esc_attr( $site_id ); ?>">
                <span class="znc-badge-dot" style="background:<?php echo esc_attr( $display['badge_color'] ?? '#4f46e5' ); ?>"></span>
                <?php echo esc_html( $display['name'] ?? 'Shop #' . $site_id ); ?>
                <span class="znc-tab-count">(<?php echo count( $site_items ); ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php foreach ( $by_site as $site_id => $site_items ) :
            $display = apply_filters( 'znc_shop_display', array(), $site_id );
        ?>
        <div class="znc-shop-group" data-site="<?php echo esc_attr( $site_id ); ?>">
            <?php if ( 'flat' !== $layout ) : ?>
            <div class="znc-shop-header">
                <?php if ( ! empty( $display['icon_url'] ) ) : ?>
                    <img src="<?php echo esc_url( $display['icon_url'] ); ?>" class="znc-shop-icon" alt="" />
                <?php endif; ?>
                <span class="znc-shop-badge" style="background:<?php echo esc_attr( $display['badge_color'] ?? '#4f46e5' ); ?>">
                    <?php echo esc_html( $display['name'] ?? 'Shop #' . $site_id ); ?>
                </span>
                <?php if ( ! empty( $settings['show_origin_links'] ) ) : ?>
                    <a href="<?php echo esc_url( $display['site_url'] ?? '#' ); ?>" class="znc-shop-link" target="_blank">&#8599; Visit Shop</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <table class="znc-cart-table">
                <thead><tr><th></th><th><?php esc_html_e( 'Product', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Price', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Qty', 'zinckles-net-cart' ); ?></th><th><?php esc_html_e( 'Total', 'zinckles-net-cart' ); ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ( $site_items as $item ) :
                    $meta = json_decode( $item['line_meta'] ?? '{}', true );
                    $line_total = floatval( $item['unit_price'] ) * intval( $item['quantity'] );
                ?>
                <tr class="znc-cart-item" data-line="<?php echo esc_attr( $item['id'] ); ?>">
                    <td class="znc-item-image">
                        <?php if ( ! empty( $meta['image_url'] ) ) : ?>
                            <img src="<?php echo esc_url( $meta['image_url'] ); ?>" alt="" />
                        <?php else : ?>
                            <div class="znc-placeholder-img">&#128230;</div>
                        <?php endif; ?>
                    </td>
                    <td class="znc-item-name">
                        <?php echo esc_html( $meta['name'] ?? 'Product #' . $item['product_id'] ); ?>
                        <?php if ( 'flat' === $layout && ! empty( $settings['show_shop_badges'] ) ) : ?>
                            <span class="znc-inline-badge" style="background:<?php echo esc_attr( $display['badge_color'] ?? '#4f46e5' ); ?>"><?php echo esc_html( $display['name'] ?? '' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="znc-item-price">
                        <?php echo esc_html( $currency->format_price( floatval( $item['unit_price'] ), $item['currency'] ) ); ?>
                        <?php if ( $item['currency'] !== $currency->get_base_currency() && ! empty( $settings['conversion_display'] ) && $settings['conversion_display'] !== 'original' ) : ?>
                            <br /><small class="znc-converted"><?php echo esc_html( $currency->format_price( $currency->convert( floatval( $item['unit_price'] ), $item['currency'], $currency->get_base_currency() ), $currency->get_base_currency() ) ); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="znc-item-qty">
                        <input type="number" value="<?php echo esc_attr( $item['quantity'] ); ?>" min="1" max="99" class="znc-qty-input" data-line="<?php echo esc_attr( $item['id'] ); ?>" />
                    </td>
                    <td class="znc-item-total"><?php echo esc_html( $currency->format_price( $line_total, $item['currency'] ) ); ?></td>
                    <td class="znc-item-remove">
                        <button class="znc-remove-btn" data-line="<?php echo esc_attr( $item['id'] ); ?>" title="Remove">&times;</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <!-- Totals -->
        <div class="znc-cart-totals">
            <?php if ( $totals['is_mixed'] && ! empty( $settings['show_currency_breakdown'] ) ) : ?>
            <div class="znc-currency-breakdown">
                <h4><?php esc_html_e( 'Currency Breakdown', 'zinckles-net-cart' ); ?></h4>
                <?php foreach ( $totals['breakdowns'] as $b ) : ?>
                <div class="znc-breakdown-line">
                    <span><?php echo esc_html( $b['currency'] ); ?></span>
                    <span><?php echo esc_html( $currency->format_price( $b['subtotal'], $b['currency'] ) ); ?></span>
                    <span class="znc-converted">&rarr; <?php echo esc_html( $currency->format_price( $b['converted'], $b['base_currency'] ) ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="znc-total-line znc-total-grand">
                <span><?php esc_html_e( 'Total', 'zinckles-net-cart' ); ?></span>
                <span><?php echo esc_html( $currency->format_price( $totals['converted_total'], $totals['base_currency'] ) ); ?></span>
            </div>

            <?php if ( $mycred->is_available() && ! empty( $settings['show_zcred_widget'] ) ) :
                $parallel = $mycred->get_parallel_total( $user_id, $totals['converted_total'] );
            ?>
            <div class="znc-zcred-widget">
                <h4><?php echo esc_html( $parallel['label'] ); ?> Balance: <?php echo esc_html( number_format( $parallel['balance'], 0 ) ); ?></h4>
                <p>Max applicable: <?php echo esc_html( $currency->format_price( $parallel['max_applicable'], $totals['base_currency'] ) ); ?> (<?php echo esc_html( $parallel['credits_needed'] ); ?> <?php echo esc_html( $parallel['label'] ); ?>)</p>
            </div>
            <?php endif; ?>

            <a href="<?php echo esc_url( get_permalink( $settings['checkout_page_id'] ?? 0 ) ); ?>" class="znc-checkout-btn button">
                <?php esc_html_e( 'Proceed to Checkout', 'zinckles-net-cart' ); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
