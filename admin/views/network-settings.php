<?php
/**
 * Network Admin — Settings Page
 *
 * v1.2.0: Added multi MyCred point type detection + per-type exchange rates.
 */
defined( 'ABSPATH' ) || exit;

$mycred_types = (array) ( $settings['mycred_point_types'] ?? array() );
?>
<div class="wrap">
    <h1><?php _e( 'Zinckles Net Cart — Network Settings', 'znc' ); ?></h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e( 'Settings saved.', 'znc' ); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=znc_save_network' ) ); ?>">
        <?php wp_nonce_field( 'znc_save_network_settings' ); ?>

        <!-- ── Enrollment ─────────────────────────────────────── -->
        <div class="card" style="max-width:780px;margin-bottom:24px;">
            <h2><?php _e( 'Subsite Enrollment', 'znc' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="znc-enrollment-mode"><?php _e( 'Enrollment Mode', 'znc' ); ?></label></th>
                    <td>
                        <select id="znc-enrollment-mode" name="znc[enrollment_mode]">
                            <option value="opt-in" <?php selected( $settings['enrollment_mode'], 'opt-in' ); ?>><?php _e( 'Opt-In — only manually enrolled sites participate', 'znc' ); ?></option>
                            <option value="opt-out" <?php selected( $settings['enrollment_mode'], 'opt-out' ); ?>><?php _e( 'Opt-Out — all sites participate unless blocked', 'znc' ); ?></option>
                            <option value="manual" <?php selected( $settings['enrollment_mode'], 'manual' ); ?>><?php _e( 'Manual — network admin has sole control', 'znc' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <label>
                            <input type="checkbox" name="znc[auto_enroll_new]" value="1" <?php checked( $settings['auto_enroll_new'] ); ?>>
                            <?php _e( 'Auto-enroll newly created subsites', 'znc' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Currency ───────────────────────────────────────── -->
        <div class="card" style="max-width:780px;margin-bottom:24px;">
            <h2><?php _e( 'Currency', 'znc' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="znc-base-currency"><?php _e( 'Base Currency', 'znc' ); ?></label></th>
                    <td>
                        <input type="text" id="znc-base-currency" name="znc[base_currency]"
                               value="<?php echo esc_attr( $settings['base_currency'] ); ?>"
                               class="small-text" maxlength="3" style="text-transform:uppercase;">
                        <p class="description"><?php _e( 'ISO 4217 code (e.g. CAD, USD, EUR). All conversions reference this.', 'znc' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <label>
                            <input type="checkbox" name="znc[allow_mixed_currency]" value="1" <?php checked( $settings['allow_mixed_currency'] ); ?>>
                            <?php _e( 'Allow mixed-currency carts (products in different currencies)', 'znc' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── MyCred / ZCreds — Multi-Type ───────────────────── -->
        <div class="card" style="max-width:780px;margin-bottom:24px;">
            <h2><?php _e( 'MyCred / Points Integration', 'znc' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th></th>
                    <td>
                        <label>
                            <input type="checkbox" name="znc[mycred_enabled]" value="1" <?php checked( $settings['mycred_enabled'] ); ?>>
                            <strong><?php _e( 'Enable MyCred point payments at checkout', 'znc' ); ?></strong>
                        </label>
                    </td>
                </tr>
            </table>

            <h3 style="margin:16px 0 8px;">
                <?php _e( 'Detected Point Types', 'znc' ); ?>
                <button type="button" class="button button-small" id="znc-detect-mycred" style="margin-left:12px;">
                    <?php _e( 'Detect Point Types', 'znc' ); ?>
                </button>
            </h3>
            <div id="znc-mycred-detect-status" style="margin-bottom:12px;"></div>

            <?php if ( ! empty( $mycred_types ) ) : ?>
                <table class="wp-list-table widefat striped" id="znc-mycred-types-table" style="margin-bottom:16px;">
                    <thead>
                        <tr>
                            <th><?php _e( 'Slug', 'znc' ); ?></th>
                            <th><?php _e( 'Label', 'znc' ); ?></th>
                            <th><?php _e( 'Names', 'znc' ); ?></th>
                            <th><?php _e( 'Exchange Rate', 'znc' ); ?></th>
                            <th><?php _e( 'Max %', 'znc' ); ?></th>
                            <th><?php _e( 'Active', 'znc' ); ?></th>
                            <th><?php _e( 'Source', 'znc' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $mycred_types as $slug => $type ) : ?>
                            <tr>
                                <td><code><?php echo esc_html( $slug ); ?></code></td>
                                <td><?php echo esc_html( $type['label'] ?? $slug ); ?></td>
                                <td><?php echo esc_html( ( $type['singular'] ?? '' ) . ' / ' . ( $type['plural'] ?? '' ) ); ?></td>
                                <td>
                                    <input type="number" step="0.01" min="0"
                                           name="znc[mycred_point_types][<?php echo esc_attr( $slug ); ?>][exchange_rate]"
                                           value="<?php echo esc_attr( $type['exchange_rate'] ?? 1 ); ?>"
                                           class="small-text">
                                    <span class="description">= 1 <?php echo esc_html( $settings['base_currency'] ); ?></span>
                                </td>
                                <td>
                                    <input type="number" min="0" max="100"
                                           name="znc[mycred_point_types][<?php echo esc_attr( $slug ); ?>][max_percent]"
                                           value="<?php echo esc_attr( $type['max_percent'] ?? 50 ); ?>"
                                           class="small-text">%
                                </td>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               name="znc[mycred_point_types][<?php echo esc_attr( $slug ); ?>][enabled]"
                                               value="1" <?php checked( $type['enabled'] ?? true ); ?>>
                                    </label>
                                </td>
                                <td>
                                    <span class="znc-source-badge"><?php echo esc_html( $type['source'] ?? 'unknown' ); ?></span>
                                </td>
                                <input type="hidden"
                                       name="znc[mycred_point_types][<?php echo esc_attr( $slug ); ?>][label]"
                                       value="<?php echo esc_attr( $type['label'] ?? $slug ); ?>">
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="description" style="padding:12px;background:#f9f9f9;border-left:4px solid #ddd;">
                    <?php _e( 'No point types detected yet. Click "Detect Point Types" to scan the network.', 'znc' ); ?>
                </p>
            <?php endif; ?>

            <h4 style="margin:16px 0 8px;"><?php _e( 'Fallback Defaults', 'znc' ); ?></h4>
            <p class="description" style="margin-bottom:12px;">
                <?php _e( 'Used when a point type has no specific override above.', 'znc' ); ?>
            </p>
            <table class="form-table">
                <tr>
                    <th><label><?php _e( 'Default Exchange Rate', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" step="0.01" min="0" name="znc[mycred_exchange_rate]"
                               value="<?php echo esc_attr( $settings['mycred_exchange_rate'] ); ?>" class="small-text">
                        <span class="description">
                            <?php printf( __( 'points = 1 %s', 'znc' ), esc_html( $settings['base_currency'] ) ); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e( 'Default Max %', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" min="0" max="100" name="znc[mycred_max_percent]"
                               value="<?php echo esc_attr( $settings['mycred_max_percent'] ); ?>" class="small-text">%
                        <p class="description"><?php _e( 'Maximum percentage of order payable with any point type.', 'znc' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Cart Behaviour ─────────────────────────────────── -->
        <div class="card" style="max-width:780px;margin-bottom:24px;">
            <h2><?php _e( 'Cart Behaviour', 'znc' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php _e( 'Cart Expiry', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" min="1" name="znc[cart_expiry_hours]"
                               value="<?php echo esc_attr( $settings['cart_expiry_hours'] ); ?>" class="small-text">
                        <?php _e( 'hours', 'znc' ); ?>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e( 'Max Items Per Cart', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" min="1" name="znc[max_items_per_cart]"
                               value="<?php echo esc_attr( $settings['max_items_per_cart'] ); ?>" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e( 'Max Shops Per Cart', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" min="1" name="znc[max_shops_per_cart]"
                               value="<?php echo esc_attr( $settings['max_shops_per_cart'] ); ?>" class="small-text">
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Checkout ───────────────────────────────────────── -->
        <div class="card" style="max-width:780px;margin-bottom:24px;">
            <h2><?php _e( 'Checkout & Inventory', 'znc' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label><?php _e( 'Validation Mode', 'znc' ); ?></label></th>
                    <td>
                        <select name="znc[checkout_validation]">
                            <option value="strict" <?php selected( $settings['checkout_validation'], 'strict' ); ?>><?php _e( 'Strict — block checkout on any price/stock change', 'znc' ); ?></option>
                            <option value="relaxed" <?php selected( $settings['checkout_validation'], 'relaxed' ); ?>><?php _e( 'Relaxed — warn but allow checkout', 'znc' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e( 'Inventory Retry', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" min="0" name="znc[inventory_retry_max]"
                               value="<?php echo esc_attr( $settings['inventory_retry_max'] ); ?>" class="small-text">
                        <?php _e( 'attempts, every', 'znc' ); ?>
                        <input type="number" min="60" name="znc[inventory_retry_delay]"
                               value="<?php echo esc_attr( $settings['inventory_retry_delay'] ); ?>" class="small-text">
                        <?php _e( 'seconds', 'znc' ); ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ── Logging ────────────────────────────────────────── -->
        <div class="card" style="max-width:780px;margin-bottom:24px;">
            <h2><?php _e( 'Logging & Debug', 'znc' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th></th>
                    <td>
                        <label>
                            <input type="checkbox" name="znc[logging_enabled]" value="1" <?php checked( $settings['logging_enabled'] ); ?>>
                            <?php _e( 'Enable logging', 'znc' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e( 'Log Level', 'znc' ); ?></label></th>
                    <td>
                        <select name="znc[log_level]">
                            <option value="debug" <?php selected( $settings['log_level'], 'debug' ); ?>>Debug</option>
                            <option value="info" <?php selected( $settings['log_level'], 'info' ); ?>>Info</option>
                            <option value="warning" <?php selected( $settings['log_level'], 'warning' ); ?>>Warning</option>
                            <option value="error" <?php selected( $settings['log_level'], 'error' ); ?>>Error</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label><?php _e( 'Retention', 'znc' ); ?></label></th>
                    <td>
                        <input type="number" min="1" name="znc[log_retention_days]"
                               value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" class="small-text">
                        <?php _e( 'days', 'znc' ); ?>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <label>
                            <input type="checkbox" name="znc[debug_mode]" value="1" <?php checked( $settings['debug_mode'] ); ?>>
                            <?php _e( 'Debug mode (shows REST payloads in admin)', 'znc' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Network Settings', 'znc' ), 'primary' ); ?>
    </form>
</div>

<style>
.znc-source-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    background: #e3f2fd;
    color: #1565c0;
}
</style>
