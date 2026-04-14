<?php
/**
 * Base Admin class — shared utilities for admin pages.
 *
 * @package ZincklesNetCart
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

class ZNC_Admin {

    /**
     * Render a notice.
     */
    public static function notice( $message, $type = 'success' ) {
        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr( $type ),
            esc_html( $message )
        );
    }

    /**
     * Get the admin URL for a Net Cart page.
     */
    public static function page_url( $page, $args = array() ) {
        return add_query_arg(
            array_merge( array( 'page' => $page ), $args ),
            admin_url( 'admin.php' )
        );
    }
}
