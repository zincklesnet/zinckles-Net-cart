<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'product_mode' => 'all', 'include_products' => array(), 'exclude_products' => array(),
    'exclude_categories' => array(), 'exclude_tags' => array(),
    'exclude_backorders' => false, 'exclude_on_sale' => false,
    'min_price' => 0, 'max_price' => 0, 'snapshot_trigger' => 'auto',
    'include_meta' => true, 'include_images' => true, 'meta_keys' => array(),
) );
$categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
$tags       = get_terms( array( 'taxonomy' => 'product_tag', 'hide_empty' => false ) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Product Settings', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_subsite_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Selection Mode', 'zinckles-net-cart' ); ?></th>
                <td><select name="product_mode">
                    <option value="all" <?php selected( $s['product_mode'], 'all' ); ?>>All Products</option>
                    <option value="include" <?php selected( $s['product_mode'], 'include' ); ?>>Include Only (whitelist)</option>
                    <option value="exclude" <?php selected( $s['product_mode'], 'exclude' ); ?>>Exclude (blacklist)</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Category Exclusions', 'zinckles-net-cart' ); ?></th>
                <td><select name="exclude_categories[]" multiple size="6" style="min-width:300px;">
                    <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php echo in_array( $cat->term_id, $s['exclude_categories'] ) ? 'selected' : ''; ?>><?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( $cat->count ); ?>)</option>
                    <?php endforeach; ?>
                </select><p class="description">Hold Ctrl/Cmd to select multiple</p></td></tr>
            <tr><th><?php esc_html_e( 'Exclude Backorders', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="exclude_backorders" value="1" <?php checked( $s['exclude_backorders'] ); ?> /> Don't include backordered products</label></td></tr>
            <tr><th><?php esc_html_e( 'Exclude On-Sale', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="exclude_on_sale" value="1" <?php checked( $s['exclude_on_sale'] ); ?> /> Keep sale items exclusive to this shop</label></td></tr>
            <tr><th><?php esc_html_e( 'Min Price', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="min_price" value="<?php echo esc_attr( $s['min_price'] ); ?>" step="0.01" min="0" class="small-text" /> <span class="description">0 = no minimum</span></td></tr>
            <tr><th><?php esc_html_e( 'Max Price', 'zinckles-net-cart' ); ?></th>
                <td><input type="number" name="max_price" value="<?php echo esc_attr( $s['max_price'] ); ?>" step="0.01" min="0" class="small-text" /> <span class="description">0 = no maximum</span></td></tr>
            <tr><th><?php esc_html_e( 'Snapshot Trigger', 'zinckles-net-cart' ); ?></th>
                <td><select name="snapshot_trigger">
                    <option value="auto" <?php selected( $s['snapshot_trigger'], 'auto' ); ?>>Automatic (on add-to-cart)</option>
                    <option value="manual" <?php selected( $s['snapshot_trigger'], 'manual' ); ?>>Manual (button only)</option>
                </select></td></tr>
            <tr><th><?php esc_html_e( 'Include Meta', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="include_meta" value="1" <?php checked( $s['include_meta'] ); ?> /> Include product metadata in snapshots</label></td></tr>
            <tr><th><?php esc_html_e( 'Include Images', 'zinckles-net-cart' ); ?></th>
                <td><label><input type="checkbox" name="include_images" value="1" <?php checked( $s['include_images'] ); ?> /> Include product images in snapshots</label></td></tr>
        </table>
        <input type="hidden" name="znc_save_subsite" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
