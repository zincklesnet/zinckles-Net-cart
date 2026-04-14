<?php
defined( 'ABSPATH' ) || exit;
$s = wp_parse_args( $settings, array(
    'brand_display_name' => '', 'brand_tagline' => '',
    'brand_badge_color' => '#4f46e5', 'brand_icon_url' => '',
) );
?>
<div class="wrap znc-wrap">
    <h1><?php esc_html_e( 'Net Cart — Branding', 'zinckles-net-cart' ); ?></h1>
    <?php settings_errors( 'znc' ); ?>
    <form method="post">
        <?php wp_nonce_field( 'znc_subsite_nonce' ); ?>
        <table class="form-table">
            <tr><th><?php esc_html_e( 'Display Name', 'zinckles-net-cart' ); ?></th>
                <td><input type="text" name="brand_display_name" value="<?php echo esc_attr( $s['brand_display_name'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo('name') ); ?>" />
                <p class="description">How your shop appears in the global cart (leave empty for site name)</p></td></tr>
            <tr><th><?php esc_html_e( 'Tagline', 'zinckles-net-cart' ); ?></th>
                <td><input type="text" name="brand_tagline" value="<?php echo esc_attr( $s['brand_tagline'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo('description') ); ?>" /></td></tr>
            <tr><th><?php esc_html_e( 'Badge Color', 'zinckles-net-cart' ); ?></th>
                <td><input type="text" name="brand_badge_color" value="<?php echo esc_attr( $s['brand_badge_color'] ); ?>" class="znc-color-picker" /></td></tr>
            <tr><th><?php esc_html_e( 'Shop Icon', 'zinckles-net-cart' ); ?></th>
                <td>
                    <input type="hidden" name="brand_icon_url" id="znc-icon-url" value="<?php echo esc_attr( $s['brand_icon_url'] ); ?>" />
                    <?php if ( $s['brand_icon_url'] ) : ?>
                        <img src="<?php echo esc_url( $s['brand_icon_url'] ); ?>" style="max-width:64px;max-height:64px;display:block;margin-bottom:10px;" id="znc-icon-preview" />
                    <?php else : ?>
                        <img src="" style="max-width:64px;max-height:64px;display:none;margin-bottom:10px;" id="znc-icon-preview" />
                    <?php endif; ?>
                    <button type="button" class="button" id="znc-upload-icon"><?php esc_html_e( 'Upload Icon', 'zinckles-net-cart' ); ?></button>
                    <button type="button" class="button" id="znc-remove-icon" <?php echo empty( $s['brand_icon_url'] ) ? 'style="display:none;"' : ''; ?>><?php esc_html_e( 'Remove', 'zinckles-net-cart' ); ?></button>
                </td></tr>
        </table>

        <h2><?php esc_html_e( 'Preview', 'zinckles-net-cart' ); ?></h2>
        <div style="background:#f9fafb;padding:20px;border-radius:8px;max-width:400px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <?php if ( $s['brand_icon_url'] ) : ?>
                    <img src="<?php echo esc_url( $s['brand_icon_url'] ); ?>" style="width:32px;height:32px;border-radius:4px;" />
                <?php endif; ?>
                <div>
                    <span style="background:<?php echo esc_attr( $s['brand_badge_color'] ); ?>;color:#fff;padding:2px 10px;border-radius:12px;font-size:13px;font-weight:600;">
                        <?php echo esc_html( $s['brand_display_name'] ?: get_bloginfo('name') ); ?>
                    </span>
                    <?php if ( $s['brand_tagline'] ) : ?>
                        <p style="margin:4px 0 0;font-size:12px;color:#6b7280;"><?php echo esc_html( $s['brand_tagline'] ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <input type="hidden" name="znc_save_subsite" value="1" />
        <?php submit_button(); ?>
    </form>
</div>
