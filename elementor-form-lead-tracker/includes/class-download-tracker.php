<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFLT_Download_Tracker {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_tracker' ] );
        add_action( 'wp_ajax_eflt_track_download', [ __CLASS__, 'ajax_track' ] );
        add_action( 'wp_ajax_nopriv_eflt_track_download', [ __CLASS__, 'ajax_track' ] );
    }

    public static function enqueue_tracker() {
        if ( ! is_page() ) return;

        $page_id   = get_queried_object_id();
        $protected = get_option( 'eflt_protected_pages', [] );

        if ( ! isset( $protected[ $page_id ] ) ) return;

        $config = $protected[ $page_id ];

        if ( empty( $config['track_downloads'] ) ) return;

        wp_enqueue_script( 'eflt-tracker', EFLT_PLUGIN_URL . 'assets/tracker.js', [], EFLT_VERSION, true );

        $ga4_enabled = ! empty( $config['enable_ga4'] );
        $ga4_id      = $ga4_enabled ? get_option( 'eflt_ga4_measurement_id', '' ) : '';

        wp_localize_script( 'eflt-tracker', 'efltData', [
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'eflt_download_nonce' ),
            'pageId'           => $page_id,
            'enableGA4'        => $ga4_enabled,
            'ga4Id'            => $ga4_id,
            'enableFreshsales' => ! empty( $config['enable_freshsales'] ),
        ] );
    }

    public static function ajax_track() {
        check_ajax_referer( 'eflt_download_nonce', 'nonce' );

        $page_id     = absint( $_POST['page_id'] ?? 0 );
        $cookie_name = EFLT_COOKIE_PREFIX . $page_id;
        $token       = sanitize_text_field( $_COOKIE[ $cookie_name ] ?? '' );

        if ( empty( $token ) ) {
            wp_send_json_error( 'No valid session.', 403 );
        }

        $button_id    = sanitize_text_field( $_POST['button_id'] ?? '' );
        $button_label = sanitize_text_field( $_POST['button_label'] ?? '' );
        $file_url     = esc_url_raw( $_POST['file_url'] ?? '' );

        if ( empty( $button_id ) && empty( $button_label ) ) {
            wp_send_json_error( 'Missing button data.', 400 );
        }

        $download_data = [
            'button_id' => $button_id,
            'label'     => $button_label,
            'file_url'  => $file_url,
            'time'      => current_time( 'mysql' ),
            'ip'        => $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        $row = EFLT_Database::append_download( $token, $download_data );

        if ( ! $row ) {
            wp_send_json_error( 'Session not found.', 404 );
        }

        $config = EFLT_Page_Gate::get_page_config( $page_id );
        if ( $config && ! empty( $config['enable_freshsales'] ) ) {
            EFLT_Freshsales::tag_download( $row->email, $button_label, $file_url );
        }

        wp_send_json_success( [
            'message'  => 'Download tracked.',
            'email'    => $row->email,
            'download' => $button_label,
        ] );
    }
}
