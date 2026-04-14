<?php
/**
 * Net Cart Order Detail — Single Order View
 *
 * Shows full breakdown of a Net Cart order: per-shop line items,
 * currency conversions, ZCred transactions, payment timeline, and statuses.
 *
 * @var array $order_data {
 *     @type array $summary    Order summary with totals, currencies, ZCred data.
 *     @type array $shops      Per-shop detail with line items.
 *     @type array $timeline   Payment/fulfillment timeline events.
 *     @type array $conversion Currency conversion breakdown (mixed orders only).
 *     @type array $billing    Billing address.
 *     @type array $shipping   Shipping address.
 *     @type array $order_notes Customer-visible order notes.
 * }
 *
 * @package Zinckles_Net_Cart
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$summary    = $order_data['summary'];
$shops      = $order_data['shops'];
$timeline   = $order_data['timeline'];
$conversion = $order_data['conversion'];
?>

<div class="znc-myaccount znc-order-detail">

    <!-- ── Back link ────────────────────────────────────────── -->
    <a href="<?php echo esc_url( wc_get_account_endpoint_url( ZNC_My_Account::ENDPOINT ) ); ?>" class="znc-back-link">
        ← <?php esc_html_e( 'Back to Net Cart Orders', 'zinckles-net-cart' ); ?>
    </a>

    <!-- ── Order Header ─────────────────────────────────────── -->
    <div class="znc-detail-header">
        <div class="znc-detail-header__title">
            <h2>
                <?php
                /* translators: %s: order number */
                printf( esc_html__( 'Order #%s', 'zinckles-net-cart' ), esc_html( $summary['order_number'] ) );
                ?>
            </h2>
            <span class="znc-order-card__status znc-status--<?php echo esc_attr( $summary['status'] ); ?>">
                <?php echo esc_html( $summary['status_label'] ); ?>
            </span>
            <?php if ( $summary['is_mixed'] ) : ?>
                <span class="znc-badge znc-badge--mixed"><?php esc_html_e( 'Mixed Currency', 'zinckles-net-cart' ); ?></span>
            <?php endif; ?>
        </div>
        <div class="znc-detail-header__meta">
            <span><?php echo esc_html( $summary['date_display'] ); ?></span>
            <span>•</span>
            <span>
                <?php
                printf(
                    /* translators: 1: item count, 2: shop count */
                    esc_html__( '%1$d items from %2$d shops', 'zinckles-net-cart' ),
                    $summary['total_items'],
                    $summary['shop_count']
                );
                ?>
            </span>
        </div>
    </div>

    <!-- ── Payment Summary Card ─────────────────────────────── -->
    <div class="znc-detail-card znc-payment-summary">
        <h3><?php esc_html_e( 'Payment Summary', 'zinckles-net-cart' ); ?></h3>

        <div class="znc-payment-grid">
            <!-- Monetary payment -->
            <div class="znc-payment-row znc-payment-row--monetary">
                <div class="znc-payment-row__icon">💳</div>
                <div class="znc-payment-row__details">
                    <span class="znc-payment-row__label"><?php echo esc_html( $summary['payment_method'] ?: __( 'Payment', 'zinckles-net-cart' ) ); ?></span>
                    <span class="znc-payment-row__amount">
                        <?php echo wp_kses_post( wc_price( $summary['monetary_total'], array( 'currency' => $summary['base_currency'] ) ) ); ?>
                    </span>
                </div>
            </div>

            <!-- ZCred payment -->
            <?php if ( $summary['zcred']['was_used'] ) : ?>
            <div class="znc-payment-row znc-payment-row--zcred">
                <div class="znc-payment-row__icon">⚡</div>
                <div class="znc-payment-row__details">
                    <span class="znc-payment-row__label">
                        <?php esc_html_e( 'ZCreds Applied', 'zinckles-net-cart' ); ?>
                        <?php if ( $summary['zcred']['rate'] ) : ?>
                            <small class="znc-payment-row__rate">
                                (<?php
                                /* translators: %s: exchange rate */
                                printf( esc_html__( '1 ZCred = %s', 'zinckles-net-cart' ), esc_html( $summary['zcred']['rate'] ) );
                                ?>)
                            </small>
                        <?php endif; ?>
                    </span>
                    <span class="znc-payment-row__amount znc-payment-row__amount--zcred">
                        -<?php echo esc_html( number_format( $summary['zcred']['used'] ) ); ?> ZCreds
                        <small>(<?php echo wp_kses_post( wc_price( $summary['zcred']['value'], array( 'currency' => $summary['base_currency'] ) ) ); ?>)</small>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <!-- ZCred earned -->
            <?php if ( $summary['zcred']['earned'] > 0 ) : ?>
            <div class="znc-payment-row znc-payment-row--earned">
                <div class="znc-payment-row__icon">🌟</div>
                <div class="znc-payment-row__details">
                    <span class="znc-payment-row__label"><?php esc_html_e( 'ZCreds Earned', 'zinckles-net-cart' ); ?></span>
                    <span class="znc-payment-row__amount znc-payment-row__amount--earned">
                        +<?php echo esc_html( number_format( $summary['zcred']['earned'] ) ); ?> ZCreds
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Currency Conversion (Mixed Orders Only) ──────────── -->
    <?php if ( ! empty( $conversion ) && $summary['is_mixed'] ) : ?>
    <div class="znc-detail-card znc-conversion-card">
        <h3><?php esc_html_e( 'Currency Conversion', 'zinckles-net-cart' ); ?></h3>
        <table class="znc-conversion-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Currency', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Original Amount', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Exchange Rate', 'zinckles-net-cart' ); ?></th>
                    <th><?php esc_html_e( 'Converted', 'zinckles-net-cart' ); ?> (<?php echo esc_html( $conversion['base_currency'] ); ?>)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $conversion['per_currency'] as $curr ) : ?>
                <tr>
                    <td>
                        <span class="znc-currency-flag"><?php echo esc_html( $curr['symbol'] ); ?></span>
                        <?php echo esc_html( $curr['code'] ); ?>
                    </td>
                    <td><?php echo esc_html( $curr['symbol'] . number_format( $curr['original'], 2 ) ); ?></td>
                    <td><?php echo esc_html( number_format( $curr['rate'], 4 ) ); ?></td>
                    <td><?php echo esc_html( $conversion['base_currency_symbol'] . number_format( $curr['converted'], 2 ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3"><strong><?php esc_html_e( 'Total Charged', 'zinckles-net-cart' ); ?></strong></td>
                    <td><strong><?php echo wp_kses_post( wc_price( $summary['monetary_total'], array( 'currency' => $summary['base_currency'] ) ) ); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <!-- ── Per-Shop Breakdown ───────────────────────────────── -->
    <div class="znc-shops-section">
        <h3><?php esc_html_e( 'Shop Breakdown', 'zinckles-net-cart' ); ?></h3>

        <?php foreach ( $shops as $shop ) : ?>
        <div class="znc-shop-section" data-site-id="<?php echo esc_attr( $shop['site_id'] ); ?>">

            <!-- Shop header -->
            <div class="znc-shop-section__header" style="--shop-color: <?php echo esc_attr( $shop['badge_color'] ?: '#7c3aed' ); ?>">
                <div class="znc-shop-section__badge">
                    <?php if ( $shop['badge_icon'] ) : ?>
                        <img src="<?php echo esc_url( $shop['badge_icon'] ); ?>" alt="" class="znc-shop-section__icon">
                    <?php else : ?>
                        <span class="znc-shop-section__initial"><?php echo esc_html( mb_substr( $shop['site_name'], 0, 1 ) ); ?></span>
                    <?php endif; ?>
                </div>
                <div class="znc-shop-section__info">
                    <h4>
                        <?php if ( $shop['site_url'] ) : ?>
                            <a href="<?php echo esc_url( $shop['site_url'] ); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html( $shop['site_name'] ); ?>
                                <small>↗</small>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html( $shop['site_name'] ); ?>
                        <?php endif; ?>
                    </h4>
                    <div class="znc-shop-section__meta">
                        <span class="znc-order-card__status znc-status--<?php echo esc_attr( $shop['status'] ); ?>">
                            <?php echo esc_html( $shop['status_label'] ); ?>
                        </span>
                        <span class="znc-shop-section__currency-badge"><?php echo esc_html( $shop['currency_symbol'] . ' ' . $shop['currency'] ); ?></span>
                        <span>
                            <?php
                            printf(
                                /* translators: 1: child order ID */
                                esc_html__( 'Shop Order #%d', 'zinckles-net-cart' ),
                                $shop['child_order_id']
                            );
                            ?>
                        </span>
                    </div>
                </div>
                <div class="znc-shop-section__total">
                    <?php echo esc_html( $shop['currency_symbol'] . number_format( $shop['shop_total'], 2 ) ); ?>
                </div>
            </div>

            <!-- Line items -->
            <div class="znc-shop-items">
                <?php foreach ( $shop['items'] as $item ) : ?>
                <div class="znc-line-item">
                    <div class="znc-line-item__image">
                        <?php if ( $item['image'] ) : ?>
                            <img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>">
                        <?php else : ?>
                            <div class="znc-line-item__placeholder">📦</div>
                        <?php endif; ?>
                    </div>
                    <div class="znc-line-item__details">
                        <div class="znc-line-item__name">
                            <?php if ( $item['product_url'] ) : ?>
                                <a href="<?php echo esc_url( $item['product_url'] ); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html( $item['name'] ); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html( $item['name'] ); ?>
                            <?php endif; ?>
                        </div>

                        <?php if ( $item['sku'] ) : ?>
                            <span class="znc-line-item__sku">SKU: <?php echo esc_html( $item['sku'] ); ?></span>
                        <?php endif; ?>

                        <?php if ( ! empty( $item['attributes'] ) ) : ?>
                            <div class="znc-line-item__attributes">
                                <?php foreach ( $item['attributes'] as $attr ) : ?>
                                    <span class="znc-attribute">
                                        <span class="znc-attribute__label"><?php echo esc_html( $attr['label'] ); ?>:</span>
                                        <span class="znc-attribute__value"><?php echo esc_html( $attr['value'] ); ?></span>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $item['meta'] ) ) : ?>
                            <div class="znc-line-item__meta">
                                <?php foreach ( $item['meta'] as $m ) : ?>
                                    <span class="znc-meta-item"><?php echo esc_html( $m['key'] ); ?>: <?php echo esc_html( $m['value'] ); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="znc-line-item__qty">
                        × <?php echo esc_html( $item['quantity'] ); ?>
                    </div>
                    <div class="znc-line-item__price">
                        <span class="znc-line-item__unit"><?php echo esc_html( $shop['currency_symbol'] . number_format( $item['unit_price'], 2 ) ); ?> ea.</span>
                        <span class="znc-line-item__total"><?php echo esc_html( $shop['currency_symbol'] . number_format( $item['line_total'], 2 ) ); ?></span>
                        <?php if ( $item['line_tax'] > 0 ) : ?>
                            <span class="znc-line-item__tax">
                                +<?php echo esc_html( $shop['currency_symbol'] . number_format( $item['line_tax'], 2 ) ); ?> tax
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Shop totals -->
            <div class="znc-shop-totals">
                <div class="znc-shop-totals__row">
                    <span><?php esc_html_e( 'Subtotal', 'zinckles-net-cart' ); ?></span>
                    <span><?php echo esc_html( $shop['currency_symbol'] . number_format( $shop['subtotal'], 2 ) ); ?></span>
                </div>

                <?php if ( ! empty( $shop['coupons'] ) ) : ?>
                <div class="znc-shop-totals__row znc-shop-totals__row--discount">
                    <span>
                        <?php esc_html_e( 'Coupons', 'zinckles-net-cart' ); ?>
                        <small>(<?php echo esc_html( implode( ', ', $shop['coupons'] ) ); ?>)</small>
                    </span>
                    <span>-<?php echo esc_html( $shop['currency_symbol'] . number_format( $shop['coupon_discount'], 2 ) ); ?></span>
                </div>
                <?php endif; ?>

                <?php if ( $shop['shipping'] > 0 ) : ?>
                <div class="znc-shop-totals__row">
                    <span>
                        <?php esc_html_e( 'Shipping', 'zinckles-net-cart' ); ?>
                        <?php if ( $shop['shipping_method'] ) : ?>
                            <small>(<?php echo esc_html( $shop['shipping_method'] ); ?>)</small>
                        <?php endif; ?>
                    </span>
                    <span><?php echo esc_html( $shop['currency_symbol'] . number_format( $shop['shipping'], 2 ) ); ?></span>
                </div>
                <?php endif; ?>

                <?php if ( $shop['tax'] > 0 ) : ?>
                <div class="znc-shop-totals__row">
                    <span><?php esc_html_e( 'Tax', 'zinckles-net-cart' ); ?></span>
                    <span><?php echo esc_html( $shop['currency_symbol'] . number_format( $shop['tax'], 2 ) ); ?></span>
                </div>
                <?php endif; ?>

                <div class="znc-shop-totals__row znc-shop-totals__row--total">
                    <span><?php esc_html_e( 'Shop Total', 'zinckles-net-cart' ); ?></span>
                    <span><?php echo esc_html( $shop['currency_symbol'] . number_format( $shop['shop_total'], 2 ) ); ?></span>
                </div>
            </div>

            <?php if ( $shop['notes'] ) : ?>
            <div class="znc-shop-notes">
                <strong><?php esc_html_e( 'Shop Note:', 'zinckles-net-cart' ); ?></strong>
                <?php echo esc_html( $shop['notes'] ); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Payment Timeline ─────────────────────────────────── -->
    <?php if ( ! empty( $timeline ) ) : ?>
    <div class="znc-detail-card znc-timeline-card">
        <h3><?php esc_html_e( 'Order Timeline', 'zinckles-net-cart' ); ?></h3>
        <div class="znc-timeline">
            <?php foreach ( $timeline as $event ) : ?>
            <div class="znc-timeline__event znc-timeline--<?php echo esc_attr( $event['type'] ); ?>">
                <div class="znc-timeline__dot"></div>
                <div class="znc-timeline__content">
                    <div class="znc-timeline__label"><?php echo esc_html( $event['label'] ); ?></div>
                    <div class="znc-timeline__detail"><?php echo wp_kses_post( $event['detail'] ); ?></div>
                    <div class="znc-timeline__date">
                        <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $event['date'] ) ) ); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Addresses ────────────────────────────────────────── -->
    <div class="znc-detail-addresses">
        <div class="znc-detail-card znc-address-card">
            <h3><?php esc_html_e( 'Billing Address', 'zinckles-net-cart' ); ?></h3>
            <p><strong><?php echo esc_html( $order_data['billing']['name'] ); ?></strong></p>
            <p><?php echo esc_html( $order_data['billing']['email'] ); ?></p>
            <address><?php echo wp_kses_post( $order_data['billing']['address'] ); ?></address>
        </div>

        <?php if ( $order_data['shipping']['address'] ) : ?>
        <div class="znc-detail-card znc-address-card">
            <h3><?php esc_html_e( 'Shipping Address', 'zinckles-net-cart' ); ?></h3>
            <p><strong><?php echo esc_html( $order_data['shipping']['name'] ); ?></strong></p>
            <address><?php echo wp_kses_post( $order_data['shipping']['address'] ); ?></address>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Order Notes ──────────────────────────────────────── -->
    <?php if ( ! empty( $order_data['order_notes'] ) ) : ?>
    <div class="znc-detail-card znc-notes-card">
        <h3><?php esc_html_e( 'Order Notes', 'zinckles-net-cart' ); ?></h3>
        <?php foreach ( $order_data['order_notes'] as $note ) : ?>
            <div class="znc-note">
                <span class="znc-note__date">
                    <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $note['date'] ) ) ); ?>
                </span>
                <p class="znc-note__content"><?php echo wp_kses_post( $note['content'] ); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
