<?php
defined( 'ABSPATH' ) || exit;

class ZNC_Activator {

    public static function activate( $network_wide ) {
        if ( $network_wide && is_multisite() ) {
            $sites = get_sites( array( 'number' => 0 ) );
            foreach ( $sites as $site ) {
                switch_to_blog( $site->blog_id );
                self::single_activate();
                restore_current_blog();
            }
        } else {
            self::single_activate();
        }
    }

    private static function single_activate() {
        self::create_tables();
        self::provision_rest_secret();
        self::schedule_crons();
        update_option( 'znc_db_version', ZNC_DB_VERSION );
        flush_rewrite_rules();
    }

    private static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        // Global Cart table (main site)
        $sql_cart = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}znc_global_cart (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id         BIGINT UNSIGNED NOT NULL,
            site_id         BIGINT UNSIGNED NOT NULL,
            product_id      BIGINT UNSIGNED NOT NULL,
            variation_id    BIGINT UNSIGNED DEFAULT 0,
            quantity        INT UNSIGNED    NOT NULL DEFAULT 1,
            unit_price      DECIMAL(13,4)   NOT NULL DEFAULT 0,
            currency        VARCHAR(3)      NOT NULL DEFAULT 'USD',
            line_meta       LONGTEXT        DEFAULT NULL,
            coupon_codes    TEXT            DEFAULT NULL,
            added_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at      DATETIME        DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user     (user_id),
            KEY idx_site     (site_id),
            KEY idx_product  (product_id),
            KEY idx_expires  (expires_at),
            UNIQUE KEY uq_cart_line (user_id, site_id, product_id, variation_id)
        ) $charset;";

        // Order Mapping table (main site)
        $sql_orders = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}znc_order_map (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_order_id BIGINT UNSIGNED NOT NULL,
            child_order_id  BIGINT UNSIGNED NOT NULL,
            child_site_id   BIGINT UNSIGNED NOT NULL,
            status          VARCHAR(30)     NOT NULL DEFAULT 'pending',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_parent  (parent_order_id),
            KEY idx_child   (child_order_id, child_site_id)
        ) $charset;";

        // Inventory Retry Queue
        $sql_retry = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}znc_inventory_retry (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id         BIGINT UNSIGNED NOT NULL,
            product_id      BIGINT UNSIGNED NOT NULL,
            quantity         INT            NOT NULL,
            action          VARCHAR(20)     NOT NULL DEFAULT 'deduct',
            attempts        INT UNSIGNED    NOT NULL DEFAULT 0,
            max_attempts    INT UNSIGNED    NOT NULL DEFAULT 5,
            last_attempt    DATETIME        DEFAULT NULL,
            next_attempt    DATETIME        DEFAULT NULL,
            status          VARCHAR(20)     NOT NULL DEFAULT 'pending',
            error_message   TEXT            DEFAULT NULL,
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status  (status, next_attempt)
        ) $charset;";

        // Enrolled Sites table
        $sql_enrolled = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}znc_enrolled_sites (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id         BIGINT UNSIGNED NOT NULL,
            status          VARCHAR(20)     NOT NULL DEFAULT 'active',
            enrolled_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            enrolled_by     BIGINT UNSIGNED DEFAULT NULL,
            settings_json   LONGTEXT        DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_site (site_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_cart );
        dbDelta( $sql_orders );
        dbDelta( $sql_retry );
        dbDelta( $sql_enrolled );
    }

    private static function provision_rest_secret() {
        $existing = get_site_option( 'znc_rest_secret' );
        if ( empty( $existing ) ) {
            $secret = wp_generate_password( 64, true, true );
            update_site_option( 'znc_rest_secret', $secret );
        }
    }

    private static function schedule_crons() {
        if ( ! wp_next_scheduled( 'znc_cart_cleanup' ) ) {
            wp_schedule_event( time(), 'hourly', 'znc_cart_cleanup' );
        }
        if ( ! wp_next_scheduled( 'znc_inventory_retry' ) ) {
            wp_schedule_event( time(), 'five_minutes', 'znc_inventory_retry' );
        }
        // Register custom cron interval
        add_filter( 'cron_schedules', function( $schedules ) {
            $schedules['five_minutes'] = array(
                'interval' => 300,
                'display'  => __( 'Every 5 Minutes', 'zinckles-net-cart' ),
            );
            return $schedules;
        } );
    }
}
