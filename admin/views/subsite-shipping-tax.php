<?php
/**
 * Subsite Admin — Shipping & Tax Overrides
 *
 * @var array $settings Current subsite settings.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$opt = ZNC_Subsite_Admin::OPTION_KEY;

// Get WC shipping classes.
$shipping_classes = array();
if ( function_exists( 'WC' ) ) {
    $shipping_classes = WC()->shipping()->get_shipping_classes();
}
?>
<div class="wrap znc-admin-wrap">
    <h1><?php esc_html_e( 'Shipping & Tax Overrides', 'znc' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Override how shipping and tax are calculated for this shop\'s items in the global Net Cart checkout.', 'znc' ); ?></p>

    <?php settings_errors( 'znc_subsite_settings' ); ?>

    <form method="post" action="options.php">
        <?php settings_fields( 'znc_subsite_settings' ); ?>

        <!-- ═══ Shipping ═══ -->
        <div class="znc-card">
            <h2><?php esc_html_e( 'Shipping', 'znc' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Shipping Mode', 'znc' ); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[shipping_mode]" value="inherit" <?php checked( $settings['shipping_mode'], 'inherit' ); ?> /> <strong><?php esc_html_e( 'Inherit', 'znc' ); ?></strong> — <?php esc_html_e( 'Use this shop\'s WooCommerce shipping zones and rates', 'znc' ); ?></label><br>
                            <label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[shipping_mode]" value="flat" <?php checked( $settings['shipping_mode'], 'flat' ); ?> /> <strong><?php esc_html_e( 'Flat Rate', 'znc' ); ?></strong> — <?php esc_html_e( 'Fixed shipping amount for all Net Cart orders', 'znc' ); ?></label><br>
                            <label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[shipping_mode]" value="free" <?php checked( $settings['shipping_mode'], 'free' ); ?> /> <strong><?php esc_html_e( 'Free Shipping', 'znc' ); ?></strong> — <?php esc_html_e( 'Always free for Net Cart orders', 'znc' ); ?></label><br>
                            <label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[shipping_mode]" value="disable" <?php checked( $settings['shipping_mode'], 'disable' ); ?> /> <strong><?php esc_html_e( 'Disabled', 'znc' ); ?></strong> — <?php esc_html_e( 'No shipping (digital products only)', 'znc' ); ?></label>
                        </fieldset>
                    </td>
                </tr>
                <tr class="znc-flat-rate-row">
                    <th><?php esc_html_e( 'Flat Rate Amount', 'znc' ); ?></th>
                    <td>
                        <input type="number" step="0.01" min="0" name="<?php echo esc_attr( $opt ); ?>[flat_rate_amount]" value="<?php echo esc_attr( $settings['flat_rate_amount'] ); ?>" class="small-text" />
                        <span><?php echo esc_html( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '' ); ?></span>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Free Shipping Threshold', 'znc' ); ?></th>
                    <td>
                        <input type="number" step="0.01" min="0" name="<?php echo esc_attr( $opt ); ?>[free_shipping_threshold]" value="<?php echo esc_attr( $settings['free_shipping_threshold'] ); ?>" class="small-text" />
                        <span class="description"><?php esc_html_e( 'Order subtotal (from this shop) to qualify for free shipping. 0 = never free.', 'znc' ); ?></span>
                    </td>
                </tr>
                <?php if ( ! empty( $shipping_classes ) ) : ?>
                <tr>
                    <th><?php esc_html_e( 'Exclude Shipping Classes', 'znc' ); ?></th>
                    <td>
                        <?php foreach ( $shipping_classes as $class ) : ?>
                            <label style="display:block; margin-bottom:4px;">
                                <input type="checkbox"
                                    name="<?php echo esc_attr( $opt ); ?>[shipping_classes_exclude][]"
                                    value="<?php echo esc_attr( $class->slug ); ?>"
                                    <?php checked( in_array( $class->slug, (array) $settings['shipping_classes_exclude'], true ) ); ?> />
                                <?php echo esc_html( $class->name ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Products in checked shipping classes won\'t be available in Net Cart.', 'znc' ); ?></p>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e( 'Shipping Note', 'znc' ); ?></th>
                    <td>
                        <textarea name="<?php echo esc_attr( $opt ); ?>[shipping_note]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'e.g., Ships from Vancouver, BC. Allow 3-5 business days.', 'znc' ); ?>"><?php echo esc_textarea( $settings['shipping_note'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Shown to customers at checkout next to this shop\'s items.', 'znc' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ═══ Tax ═══ -->
        <div class="znc-card">
            <h2><?php esc_html_e( 'Tax', 'znc' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Tax Mode', 'znc' ); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[tax_mode]" value="inherit" <?php checked( $settings['tax_mode'], 'inherit' ); ?> /> <strong><?php esc_html_e( 'Inherit', 'znc' ); ?></strong> — <?php esc_html_e( 'Use this shop\'s WooCommerce tax settings', 'znc' ); ?></label><br>
                            <label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[tax_mode]" value="override" <?php checked( $settings['tax_mode'], 'override' ); ?> /> <strong><?php esc_html_e( 'Override', 'znc' ); ?></strong> — <?php esc_html_e( 'Use a fixed tax rate for Net Cart', 'znc' ); ?></label><br>
                            <label><input type="radio" name="<?php echo esc_attr( $opt ); ?>[tax_mode]" value="exempt" <?php checked( $settings['tax_mode'], 'exempt' ); ?> /> <strong><?php esc_html_e( 'Tax Exempt', 'znc' ); ?></strong> — <?php esc_html_e( 'No tax on Net Cart orders from this shop', 'znc' ); ?></label>
                        </fieldset>
                    </td>
                </tr>
                <tr class="znc-tax-override-row">
                    <th><?php esc_html_e( 'Tax Rate Override', 'znc' ); ?></th>
                    <td>
                        <input type="number" step="0.01" min="0" max="100" name="<?php echo esc_attr( $opt ); ?>[tax_rate_override]" value="<?php echo esc_attr( $settings['tax_rate_override'] ); ?>" class="small-text" />
                        <span>%</span>
                    </td>
                </tr>
                <tr class="znc-tax-override-row">
                    <th><?php esc_html_e( 'Tax Label', 'znc' ); ?></th>
                    <td>
                        <input type="text" name="<?php echo esc_attr( $opt ); ?>[tax_label]" value="<?php echo esc_attr( $settings['tax_label'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g., GST, VAT, Sales Tax', 'znc' ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Tax on Shipping', 'znc' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[tax_applies_to_shipping]" value="1" <?php checked( $settings['tax_applies_to_shipping'] ); ?> />
                            <?php esc_html_e( 'Apply tax to shipping costs from this shop', 'znc' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Shipping & Tax', 'znc' ) ); ?>
    </form>
</div>

<script>
jQuery(function($) {
    // Toggle flat rate row visibility.
    $('input[name$="[shipping_mode]"]').on('change', function() {
        $('.znc-flat-rate-row').toggle( $(this).val() === 'flat' );
    }).filter(':checked').trigger('change');

    // Toggle tax override rows.
    $('input[name$="[tax_mode]"]').on('change', function() {
        $('.znc-tax-override-row').toggle( $(this).val() === 'override' );
    }).filter(':checked').trigger('change');
});
</script>
