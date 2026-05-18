<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFLT_Form_Handler {

    public static function init() {
        add_action( 'elementor_pro/forms/new_record', [ __CLASS__, 'handle_submission' ], 10, 2 );
        add_filter( 'wp_redirect', [ __CLASS__, 'intercept_redirect' ], 999 );
    }

    public static function handle_submission( $record, $handler ) {
        $dg_enabled = $record->get_form_settings( 'eflt_enable_secure_access' );

        if ( $dg_enabled === null ) {
            $all_settings = $record->get( 'form_settings' );
            if ( is_array( $all_settings ) ) {
                $dg_enabled = $all_settings['eflt_enable_secure_access'] ?? null;
            }
        }

        if ( $dg_enabled !== 'yes' ) return;

        $all_settings = $record->get( 'form_settings' );
        $get = function( $key, $default = null ) use ( $record, $all_settings ) {
            $val = $record->get_form_settings( $key );
            if ( $val === null && is_array( $all_settings ) ) {
                $val = $all_settings[ $key ] ?? null;
            }
            return $val !== null ? $val : $default;
        };

        $email_field_id    = $get( 'eflt_email_field_id', 'email' );
        $name_field_id     = $get( 'eflt_name_field_id', 'name' );
        $gated_page_id     = absint( $get( 'eflt_gated_page_id', 0 ) );
        $blocked_page_id   = absint( $get( 'eflt_blocked_page_id', 0 ) );
        $cookie_days       = absint( $get( 'eflt_cookie_days', 7 ) ) ?: 7;
        $track_downloads   = $get( 'eflt_track_downloads', 'yes' ) === 'yes';
        $enable_ga4        = $get( 'eflt_enable_ga4', '' ) === 'yes';
        $enable_freshsales = $get( 'eflt_enable_freshsales', '' ) === 'yes';
        $form_name         = $get( 'form_name', 'unknown' );

        $protected_page_id = $gated_page_id;

        if ( ! $protected_page_id ) {
            $redirect_url = $record->get_form_settings( 'redirect_to' );
            if ( ! empty( $redirect_url ) ) {
                $protected_page_id = url_to_postid( $redirect_url );
                if ( ! $protected_page_id ) {
                    $protected_page_id = self::resolve_url_to_page_id( $redirect_url );
                }
            }
        }

        if ( ! $protected_page_id ) return;

        $secure = [
            'access_denied_page' => $blocked_page_id,
            'cookie_days'        => $cookie_days,
            'track_downloads'    => $track_downloads,
            'enable_ga4'         => $enable_ga4,
            'enable_freshsales'  => $enable_freshsales,
        ];

        $raw_fields = $record->get( 'fields' );
        $fields     = [];
        foreach ( $raw_fields as $id => $field ) {
            $fields[ $id ] = $field['value'];
        }

        $email = sanitize_email( $fields[ $email_field_id ] ?? '' );
        $name  = sanitize_text_field( $fields[ $name_field_id ] ?? '' );

        if ( empty( $email ) ) return;

        $token = bin2hex( random_bytes( 32 ) );

        EFLT_Database::insert_submission( [
            'access_token'   => $token,
            'form_id'        => sanitize_text_field( $form_name ),
            'protected_page' => $protected_page_id,
            'email'          => $email,
            'name'           => $name,
            'phone'          => sanitize_text_field( $fields['phone'] ?? '' ),
            'company'        => sanitize_text_field( $fields['company'] ?? '' ),
            'form_data'      => wp_json_encode( $fields ),
            'downloads'      => wp_json_encode( [] ),
            'ip_address'     => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ] );

        self::register_protected_page( $protected_page_id, $form_name, $secure );

        if ( $enable_freshsales ) {
            EFLT_Freshsales::sync_contact( $email, $name, $fields['phone'] ?? '', $fields['company'] ?? '', $form_name );
        }

        $cookie_name = EFLT_COOKIE_PREFIX . $protected_page_id;
        $expiry      = time() + ( DAY_IN_SECONDS * $cookie_days );
        setcookie( $cookie_name, $token, [
            'expires'  => $expiry,
            'path'     => '/',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
        $_COOKIE[ $cookie_name ] = $token;

        $GLOBALS['dg_current_token']  = $token;
        $GLOBALS['dg_protected_page'] = $protected_page_id;

        $token_url = add_query_arg( 'eflt_token', $token, get_permalink( $protected_page_id ) );
        $handler->add_response_data( 'redirect_url', $token_url );
    }

    public static function intercept_redirect( $location ) {
        if ( empty( $GLOBALS['dg_current_token'] ) || empty( $GLOBALS['dg_protected_page'] ) ) {
            return $location;
        }

        $token   = $GLOBALS['dg_current_token'];
        $page_id = $GLOBALS['dg_protected_page'];
        $target  = get_permalink( $page_id );

        $loc_path    = wp_parse_url( $location, PHP_URL_PATH );
        $target_path = wp_parse_url( $target, PHP_URL_PATH );

        if ( $loc_path && $target_path && rtrim( $loc_path, '/' ) === rtrim( $target_path, '/' ) ) {
            return add_query_arg( 'eflt_token', $token, $target );
        }

        $loc_host  = wp_parse_url( $location, PHP_URL_HOST );
        $site_host = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( ( $loc_host === $site_host || empty( $loc_host ) ) && url_to_postid( $location ) == $page_id ) {
            return add_query_arg( 'eflt_token', $token, $target );
        }

        return $location;
    }

    private static function register_protected_page( $page_id, $form_id, $secure ) {
        $protected = get_option( 'eflt_protected_pages', [] );
        $protected[ $page_id ] = [
            'form_id'            => $form_id,
            'access_denied_page' => $secure['access_denied_page'],
            'track_downloads'    => $secure['track_downloads'],
            'enable_ga4'         => $secure['enable_ga4'],
            'enable_freshsales'  => $secure['enable_freshsales'],
        ];
        update_option( 'eflt_protected_pages', $protected, false );
    }

    private static function resolve_url_to_page_id( $url ) {
        $clean = strtok( $url, '?#' );
        $id = url_to_postid( trailingslashit( $clean ) );
        if ( $id ) return $id;
        $id = url_to_postid( untrailingslashit( $clean ) );
        if ( $id ) return $id;
        $path = wp_parse_url( $clean, PHP_URL_PATH );
        if ( $path ) {
            $page = get_page_by_path( trim( $path, '/' ) );
            if ( $page ) return $page->ID;
        }
        return 0;
    }
}
