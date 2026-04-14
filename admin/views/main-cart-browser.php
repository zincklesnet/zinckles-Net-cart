<?php
defined( 'ABSPATH' ) || exit;
$store = new ZNC_Global_Cart_Store();
$page  = max( 1, intval( $_GET['paged'] ?? 1 ) );
$search = sanitize_text_field( $_GET['znc_search'] ?? '' );
$data  = $store->admin_get_all_carts( $page );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Cart Browser', 'zinckles-net-cart' ); ?></h1>
    <p class="description"><?php esc_html_e( 'View and manage active user carts across the network.', 'zinckles-net-cart' ); ?></p>

    <form method="get" style="margin-bottom:20px;">
        <input type="hidden" name="page" value="znc-cart-browser" />
        <input type="text" name="znc_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Search by user email or ID" class="regular-text" />
        <button type="submit" class="button"><?php esc_html_e( 'Search', 'zinckles-net-cart' ); ?></button>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'User', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Items', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Shops', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Total', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Last Updated', 'zinckles-net-cart' ); ?></th>
                <th><?php esc_html_e( 'Actions', 'zinckles-net-cart' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $data['carts'] ) ) : ?>
                <tr><td colspan="6"><?php esc_html_e( 'No active carts found.', 'zinckles-net-cart' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $data['carts'] as $cart ) :
                    $user = get_user_by( 'id', $cart['user_id'] );
                ?>
                <tr>
                    <td>
                        <?php if ( $user ) : ?>
                            <?php echo esc_html( $user->display_name ); ?><br />
                            <small><?php echo esc_html( $user->user_email ); ?></small>
                        <?php else : ?>
                            #<?php echo esc_html( $cart['user_id'] ); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $cart['items'] ); ?></td>
                    <td><?php echo esc_html( $cart['shops'] ); ?></td>
                    <td><?php echo esc_html( '$' . number_format( $cart['total'], 2 ) ); ?></td>
                    <td><?php echo esc_html( $cart['last_updated'] ); ?></td>
                    <td>
                        <button class="button button-small znc-view-cart" data-user="<?php echo esc_attr( $cart['user_id'] ); ?>"><?php esc_html_e( 'View', 'zinckles-net-cart' ); ?></button>
                        <button class="button button-small znc-clear-cart" data-user="<?php echo esc_attr( $cart['user_id'] ); ?>" onclick="return confirm('Clear this user\'s entire cart?');"><?php esc_html_e( 'Clear', 'zinckles-net-cart' ); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $data['total'] > $data['per_page'] ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php echo paginate_links( array(
                'base'    => add_query_arg( 'paged', '%#%' ),
                'format'  => '',
                'current' => $page,
                'total'   => ceil( $data['total'] / $data['per_page'] ),
            ) ); ?>
        </div>
    </div>
    <?php endif; ?>
</div>
