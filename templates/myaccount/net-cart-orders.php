<?php
/**
 * Net Cart Orders — My Account Tab
 *
 * Shows paginated order history with shop badges, currency breakdowns,
 * ZCred usage, and filterable controls.
 *
 * @var array $result         { orders, total, pages, current_page }
 * @var array $filter_options { shops, currencies, statuses }
 * @var array $stats          User aggregate stats
 * @var array $filters        Active filters
 *
 * @package Zinckles_Net_Cart
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="znc-myaccount znc-orders-page">

    <!-- ── Stats Summary Bar ────────────────────────────────── -->
    <div class="znc-stats-bar">
        <div class="znc-stat">
            <span class="znc-stat__value"><?php echo esc_html( $stats['total_orders'] ); ?></span>
            <span class="znc-stat__label"><?php esc_html_e( 'Total Orders', 'zinckles-net-cart' ); ?></span>
        </div>
        <div class="znc-stat">
            <span class="znc-stat__value"><?php echo wp_kses_post( wc_price( $stats['total_spent'], array( 'currency' => $stats['base_currency'] ) ) ); ?></span>
            <span class="znc-stat__label"><?php esc_html_e( 'Total Spent', 'zinckles-net-cart' ); ?></span>
        </div>
        <div class="znc-stat">
            <span class="znc-stat__value"><?php echo esc_html( $stats['shops_purchased'] ); ?></span>
            <span class="znc-stat__label"><?php esc_html_e( 'Shops', 'zinckles-net-cart' ); ?></span>
        </div>
        <?php if ( $stats['total_zcred_used'] > 0 || $stats['total_zcred_earned'] > 0 ) : ?>
        <div class="znc-stat znc-stat--zcred">
            <span class="znc-stat__value">
                <span class="znc-zcred-icon">⚡</span>
                <?php echo esc_html( number_format( $stats['total_zcred_earned'] - $stats['total_zcred_used'] ) ); ?>
            </span>
            <span class="znc-stat__label"><?php esc_html_e( 'Net ZCreds', 'zinckles-net-cart' ); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Filters ──────────────────────────────────────────── -->
    <div class="znc-filters">
        <form method="get" class="znc-filters__form">
            <div class="znc-filters__row">
                <?php if ( ! empty( $filter_options['shops'] ) ) : ?>
                <div class="znc-filter-group">
                    <label for="znc-filter-shop"><?php esc_html_e( 'Shop', 'zinckles-net-cart' ); ?></label>
                    <select id="znc-filter-shop" name="shop">
                        <option value=""><?php esc_html_e( 'All Shops', 'zinckles-net-cart' ); ?></option>
                        <?php foreach ( $filter_options['shops'] as $shop_id => $shop_name ) : ?>
                            <option value="<?php echo esc_attr( $shop_id ); ?>" <?php selected( $filters['shop_id'], $shop_id ); ?>>
                                <?php echo esc_html( $shop_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $filter_options['statuses'] ) ) : ?>
                <div class="znc-filter-group">
                    <label for="znc-filter-status"><?php esc_html_e( 'Status', 'zinckles-net-cart' ); ?></label>
                    <select id="znc-filter-status" name="status">
                        <option value=""><?php esc_html_e( 'All Statuses', 'zinckles-net-cart' ); ?></option>
                        <?php foreach ( $filter_options['statuses'] as $status_key => $status_label ) : ?>
                            <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $filters['status'], $status_key ); ?>>
                                <?php echo esc_html( $status_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $filter_options['currencies'] ) && count( $filter_options['currencies'] ) > 1 ) : ?>
                <div class="znc-filter-group">
                    <label for="znc-filter-currency"><?php esc_html_e( 'Currency', 'zinckles-net-cart' ); ?></label>
                    <select id="znc-filter-currency" name="currency">
                        <option value=""><?php esc_html_e( 'All Currencies', 'zinckles-net-cart' ); ?></option>
                        <?php foreach ( $filter_options['currencies'] as $code => $label ) : ?>
                            <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $filters['currency'], $code ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="znc-filter-group">
                    <label for="znc-filter-from"><?php esc_html_e( 'From', 'zinckles-net-cart' ); ?></label>
                    <input type="date" id="znc-filter-from" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
                </div>

                <div class="znc-filter-group">
                    <label for="znc-filter-to"><?php esc_html_e( 'To', 'zinckles-net-cart' ); ?></label>
                    <input type="date" id="znc-filter-to" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
                </div>

                <div class="znc-filter-group znc-filter-group--search">
                    <label for="znc-filter-search"><?php esc_html_e( 'Search', 'zinckles-net-cart' ); ?></label>
                    <input type="text" id="znc-filter-search" name="s" placeholder="<?php esc_attr_e( 'Order # or product...', 'zinckles-net-cart' ); ?>" value="<?php echo esc_attr( $filters['search'] ); ?>">
                </div>
            </div>

            <div class="znc-filters__actions">
                <button type="submit" class="button znc-btn znc-btn--filter"><?php esc_html_e( 'Filter', 'zinckles-net-cart' ); ?></button>
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( ZNC_My_Account::ENDPOINT ) ); ?>" class="znc-btn znc-btn--clear"><?php esc_html_e( 'Clear', 'zinckles-net-cart' ); ?></a>
            </div>
        </form>
    </div>

    <!-- ── Orders List ──────────────────────────────────────── -->
    <?php if ( empty( $result['orders'] ) ) : ?>
        <div class="znc-empty">
            <div class="znc-empty__icon">🛒</div>
            <h3><?php esc_html_e( 'No Net Cart orders found', 'zinckles-net-cart' ); ?></h3>
            <p><?php esc_html_e( 'Orders placed through the global cart from multiple shops will appear here.', 'zinckles-net-cart' ); ?></p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button znc-btn znc-btn--primary"><?php esc_html_e( 'Start Shopping', 'zinckles-net-cart' ); ?></a>
        </div>
    <?php else : ?>
        <div class="znc-orders-list">
            <?php foreach ( $result['orders'] as $order ) : ?>
                <div class="znc-order-card" data-order-id="<?php echo esc_attr( $order['order_id'] ); ?>">

                    <!-- Order Header -->
                    <div class="znc-order-card__header">
                        <div class="znc-order-card__id">
                            <a href="<?php echo esc_url( $order['detail_url'] ); ?>">
                                <?php
                                /* translators: %s: order number */
                                printf( esc_html__( 'Order #%s', 'zinckles-net-cart' ), esc_html( $order['order_number'] ) );
                                ?>
                            </a>
                            <?php if ( $order['is_mixed'] ) : ?>
                                <span class="znc-badge znc-badge--mixed" title="<?php esc_attr_e( 'Mixed currency order', 'zinckles-net-cart' ); ?>">
                                    <?php esc_html_e( 'Mixed Currency', 'zinckles-net-cart' ); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="znc-order-card__meta">
                            <span class="znc-order-card__date"><?php echo esc_html( $order['date_display'] ); ?></span>
                            <span class="znc-order-card__status znc-status--<?php echo esc_attr( $order['status'] ); ?>">
                                <?php echo esc_html( $order['status_label'] ); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Shop Badges Row -->
                    <div class="znc-order-card__shops">
                        <?php foreach ( $order['shops'] as $shop ) : ?>
                            <div class="znc-shop-badge" style="--shop-color: <?php echo esc_attr( $shop['badge_color'] ?: '#7c3aed' ); ?>">
                                <?php if ( $shop['badge_icon'] ) : ?>
                                    <img src="<?php echo esc_url( $shop['badge_icon'] ); ?>" alt="" class="znc-shop-badge__icon">
                                <?php else : ?>
                                    <span class="znc-shop-badge__initial"><?php echo esc_html( mb_substr( $shop['site_name'], 0, 1 ) ); ?></span>
                                <?php endif; ?>
                                <span class="znc-shop-badge__name"><?php echo esc_html( $shop['site_name'] ); ?></span>
                                <span class="znc-shop-badge__count">
                                    <?php
                                    /* translators: %d: number of items */
                                    printf( esc_html( _n( '%d item', '%d items', $shop['item_count'], 'zinckles-net-cart' ) ), $shop['item_count'] );
                                    ?>
                                </span>
                                <span class="znc-shop-badge__currency"><?php echo esc_html( $shop['currency'] ); ?></span>
                                <span class="znc-shop-badge__status znc-status--<?php echo esc_attr( $shop['status'] ); ?>">
                                    <?php echo esc_html( wc_get_order_status_name( $shop['status'] ) ); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Payment Summary Row -->
                    <div class="znc-order-card__payment">
                        <!-- Currency breakdown -->
                        <div class="znc-payment-breakdown">
                            <?php if ( $order['is_mixed'] ) : ?>
                                <div class="znc-payment-currencies">
                                    <?php foreach ( $order['currencies'] as $curr ) : ?>
                                        <span class="znc-currency-chip">
                                            <span class="znc-currency-chip__symbol"><?php echo esc_html( $curr['symbol'] ); ?></span>
                                            <span class="znc-currency-chip__amount"><?php echo esc_html( number_format( $curr['subtotal'], 2 ) ); ?></span>
                                            <span class="znc-currency-chip__code"><?php echo esc_html( $curr['code'] ); ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                    <span class="znc-payment-converted">
                                        → <?php echo wp_kses_post( wc_price( $order['monetary_total'], array( 'currency' => $order['base_currency'] ) ) ); ?>
                                    </span>
                                </div>
                            <?php else : ?>
                                <span class="znc-payment-total">
                                    <?php echo wp_kses_post( wc_price( $order['monetary_total'], array( 'currency' => $order['base_currency'] ) ) ); ?>
                                </span>
                            <?php endif; ?>

                            <!-- ZCred display -->
                            <?php if ( $order['zcred']['was_used'] ) : ?>
                                <span class="znc-zcred-chip znc-zcred-chip--used" title="<?php esc_attr_e( 'ZCreds applied to this order', 'zinckles-net-cart' ); ?>">
                                    <span class="znc-zcred-icon">⚡</span>
                                    -<?php echo esc_html( number_format( $order['zcred']['used'] ) ); ?>
                                    <span class="znc-zcred-label"><?php esc_html_e( 'ZCreds', 'zinckles-net-cart' ); ?></span>
                                </span>
                            <?php endif; ?>

                            <?php if ( $order['zcred']['earned'] > 0 ) : ?>
                                <span class="znc-zcred-chip znc-zcred-chip--earned" title="<?php esc_attr_e( 'ZCreds earned from this order', 'zinckles-net-cart' ); ?>">
                                    <span class="znc-zcred-icon">⚡</span>
                                    +<?php echo esc_html( number_format( $order['zcred']['earned'] ) ); ?>
                                    <span class="znc-zcred-label"><?php esc_html_e( 'earned', 'zinckles-net-cart' ); ?></span>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Payment method -->
                        <div class="znc-payment-method">
                            <?php if ( $order['payment_method'] ) : ?>
                                <span class="znc-payment-method__label"><?php echo esc_html( $order['payment_method'] ); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action footer -->
                    <div class="znc-order-card__footer">
                        <span class="znc-order-card__summary">
                            <?php
                            printf(
                                /* translators: 1: item count, 2: shop count */
                                esc_html__( '%1$d items from %2$d shops', 'zinckles-net-cart' ),
                                $order['total_items'],
                                $order['shop_count']
                            );
                            ?>
                        </span>
                        <a href="<?php echo esc_url( $order['detail_url'] ); ?>" class="button znc-btn znc-btn--outline">
                            <?php esc_html_e( 'View Details', 'zinckles-net-cart' ); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Pagination ───────────────────────────────────── -->
        <?php if ( $result['pages'] > 1 ) : ?>
            <div class="znc-pagination">
                <?php
                $base_url = wc_get_account_endpoint_url( ZNC_My_Account::ENDPOINT );
                for ( $i = 1; $i <= $result['pages']; $i++ ) :
                    $page_url = add_query_arg( array_merge( $filters, array( 'paged' => $i ) ), $base_url );
                    $is_current = ( $i === $result['current_page'] );
                ?>
                    <a href="<?php echo esc_url( $page_url ); ?>"
                       class="znc-pagination__page <?php echo $is_current ? 'znc-pagination__page--active' : ''; ?>">
                        <?php echo esc_html( $i ); ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
