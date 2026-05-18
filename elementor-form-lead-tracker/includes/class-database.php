<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFLT_Database {

    public static function create_tables() {
        global $wpdb;
        $table   = $wpdb->prefix . EFLT_TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            access_token    VARCHAR(64)     NOT NULL,
            form_id         VARCHAR(100)    NOT NULL DEFAULT '',
            protected_page  BIGINT UNSIGNED NOT NULL DEFAULT 0,
            email           VARCHAR(255)    NOT NULL,
            name            VARCHAR(255)    DEFAULT '',
            phone           VARCHAR(50)     DEFAULT '',
            company         VARCHAR(255)    DEFAULT '',
            form_data       LONGTEXT        DEFAULT '',
            downloads       LONGTEXT        DEFAULT '',
            ip_address      VARCHAR(45)     DEFAULT '',
            user_agent      TEXT            DEFAULT '',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (access_token),
            KEY email (email),
            KEY form_id (form_id),
            KEY protected_page (protected_page)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        update_option( 'eflt_db_version', EFLT_VERSION );
    }

    public static function insert_submission( $data ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . EFLT_TABLE, $data );
        return $wpdb->insert_id;
    }

    public static function get_by_token( $token ) {
        global $wpdb;
        $table = $wpdb->prefix . EFLT_TABLE;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE access_token = %s", $token
        ) );
    }

    public static function token_valid_for_page( $token, $page_id ) {
        global $wpdb;
        $table = $wpdb->prefix . EFLT_TABLE;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE access_token = %s AND protected_page = %d",
            $token, $page_id
        ) );
    }

    public static function append_download( $token, $download_data ) {
        global $wpdb;
        $table = $wpdb->prefix . EFLT_TABLE;
        $row = self::get_by_token( $token );
        if ( ! $row ) return false;
        $downloads   = json_decode( $row->downloads, true ) ?: [];
        $downloads[] = $download_data;
        $wpdb->update( $table, [ 'downloads' => wp_json_encode( $downloads ) ], [ 'access_token' => $token ] );
        return $row;
    }

    public static function get_submissions( $page = 1, $per_page = 25, $form_filter = '' ) {
        global $wpdb;
        $table  = $wpdb->prefix . EFLT_TABLE;
        $offset = ( $page - 1 ) * $per_page;
        $where  = '';
        $params = [];
        if ( $form_filter ) {
            $where    = 'WHERE form_id = %s';
            $params[] = $form_filter;
        }
        $params[] = $per_page;
        $params[] = $offset;
        $total = (int) $wpdb->get_var(
            $form_filter
                ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $form_filter )
                : "SELECT COUNT(*) FROM {$table}"
        );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            ...$params
        ) );
        return [ 'rows' => $rows, 'total' => $total ];
    }

    public static function get_form_ids() {
        global $wpdb;
        $table = $wpdb->prefix . EFLT_TABLE;
        return $wpdb->get_col( "SELECT DISTINCT form_id FROM {$table} WHERE form_id != '' ORDER BY form_id" );
    }
}
