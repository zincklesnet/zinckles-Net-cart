<?php
/**
 * Net Cart Dashboard Widget — My Account Dashboard
 *
 * Shows recent Net Cart activity and quick stats on the WooCommerce
 * My Account dashboard page.
 *
 * @var array $stats  User aggregate stats.
 * @var array $recent { orders, total, pages, current_page }
 *
 * @package Zinckles_Net_Cart
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( $stats['total_orders'] < 1 ) {
    return; // Don't show widget if no Net Cart orders.
}
?>

<div class="znc-dashboard-widget">
    <h3 class="znc-dashboard-widget__title">
        🛒 <?php esc_html_e( 'Net Cart Activity', 'zinckles-net-cart' ); ?>
    </h3>

    <div class="znc-dashboard-stats">
        <div class="znc-dash-stat">
            <span class="znc-dash-stat__value"><?php echo esc_html( $stats['total_orders'] ); ?></span>
            <span class="znc-dash-stat__label"><?php esc_html_e( 'Orders', 'zinckles-net-cart' ); ?></span>
        </div>
        <div class="znc-dash-stat">
            <span class="znc-dash-stat__value"><?php echo wp_kses_post( wc_price( $stats['total_spent'], array( 'currency' => $stats['base_currency'] ) ) ); ?></span>
            <span class="znc-dash-stat__label"><?php esc_html_e( 'Spent', 'zinckles-net-cart' ); ?></span>
        </div>
        <div class="znc-dash-stat">
            <span class="znc-dash-stat__value"><?php echo esc_html( $stats['shops_purchased'] ); ?></span>
            <span class="znc-dash-stat__label"><?php esc_html_e( 'Shops', 'zinckles-net-cart' ); ?></span>
        </div>
        <?php if ( $stats['total_zcred_earned'] > 0 ) : ?>
        <div class="znc-dash-stat znc-dash-stat--zcred">
            <span class="znc-dash-stat__value">⚡ <?php echo esc_html( number_format( $stats['total_zcred_earned'] ) ); ?></span>
            <span class="znc-dash-stat__label"><?php esc_html_e( 'ZCreds Earned', 'zinckles-net-cart' ); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php if ( ! empty( $recent['orders'] ) ) : ?>
    <div class="znc-dashboard-recent">
        <h4><?php esc_html_e( 'Recent Orders', 'zinckles-net-cart' ); ?></h4>
        <div class="znc-dashboard-recent__list">
            <?php foreach ( $recent['orders'] as $order ) : ?>
            <a href="<?php echo esc_url( $order['detail_url'] ); ?>" class="znc-dashboard-order">
                <div class="znc-dashboard-order__left">
                    <span class="znc-dashboard-order__id">#<?php echo esc_html( $order['order_number'] ); ?></span>
                    <span class="znc-dashboard-order__date"><?php echo esc_html( $order['date_display'] ); ?></span>
                </div>
                <div class="znc-dashboard-order__right">
                    <span class="znc-dashboard-order__shops">
                        <?php foreach ( $order['shops'] as $shop ) : ?>
                            <span class="znc-mini-badge" style="--shop-color: <?php echo esc_attr( $shop['badge_color'] ?: '#7c3aed' ); ?>"
                                  title="<?php echo esc_attr( $shop['site_name'] ); ?>">
                                <?php echo esc_html( mb_substr( $shop['site_name'], 0, 1 ) ); ?>
                            </span>
                        <?php endforeach; ?>
                    </span>
                    <span class="znc-dashboard-order__total">
                        <?php echo wp_kses_post( wc_price( $order['monetary_total'], array( 'currency' => $order['base_currency'] ) ) ); ?>
                        <?php if ( $order['zcred']['was_used'] ) : ?>
                            <span class="znc-zcred-mini">⚡-<?php echo esc_html( number_format( $order['zcred']['used'] ) ); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="znc-order-card__status znc-status--<?php echo esc_attr( $order['status'] ); ?>">
                        <?php echo esc_html( $order['status_label'] ); ?>
                    </span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <a href="<?php echo esc_url( wc_get_account_endpoint_url( ZNC_My_Account::ENDPOINT ) ); ?>" class="znc-dashboard-widget__link">
        <?php esc_html_e( 'View All Net Cart Orders →', 'zinckles-net-cart' ); ?>
    </a>
</div>
