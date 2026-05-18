<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EFLT_Page_Gate {

    public static function init() {
        add_action( 'template_redirect', [ __CLASS__, 'check_access' ] );
    }

    public static function check_access() {
        if ( ! is_page() ) return;
        if ( current_user_can( 'manage_options' ) ) return;

        $page_id   = get_queried_object_id();
        $protected = get_option( 'eflt_protected_pages', [] );

        if ( ! isset( $protected[ $page_id ] ) ) return;

        self::set_no_cache_headers();

        $config      = $protected[ $page_id ];
        $cookie_name = EFLT_COOKIE_PREFIX . $page_id;
        $valid       = false;

        if ( ! empty( $_GET['eflt_token'] ) ) {
            $token = sanitize_text_field( $_GET['eflt_token'] );
            if ( EFLT_Database::token_valid_for_page( $token, $page_id ) ) {
                setcookie( $cookie_name, $token, [
                    'expires'  => time() + ( DAY_IN_SECONDS * EFLT_COOKIE_DAYS ),
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ] );
                $_COOKIE[ $cookie_name ] = $token;
                wp_safe_redirect( get_permalink( $page_id ) );
                exit;
            }
        }

        if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
            $token = sanitize_text_field( $_COOKIE[ $cookie_name ] );
            if ( EFLT_Database::token_valid_for_page( $token, $page_id ) ) {
                $valid = true;
            }
        }

        if ( ! $valid ) {
            $denied_page = $config['access_denied_page'] ?? 0;
            if ( $denied_page && $denied_page != $page_id ) {
                wp_safe_redirect( get_permalink( $denied_page ) );
            } else {
                wp_safe_redirect( home_url() );
            }
            exit;
        }
    }

    public static function is_page_protected( $page_id ) {
        $protected = get_option( 'eflt_protected_pages', [] );
        return isset( $protected[ $page_id ] );
    }

    public static function get_page_config( $page_id ) {
        $protected = get_option( 'eflt_protected_pages', [] );
        return $protected[ $page_id ] ?? null;
    }

    public static function unprotect_page( $page_id ) {
        $protected = get_option( 'eflt_protected_pages', [] );
        unset( $protected[ $page_id ] );
        update_option( 'eflt_protected_pages', $protected, false );
    }

    private static function set_no_cache_headers() {
        if ( headers_sent() ) return;
        if ( ! defined( 'DONOTCACHEPAGE' ) )  define( 'DONOTCACHEPAGE', true );
        if ( ! defined( 'DONOTCACHEDB' ) )    define( 'DONOTCACHEDB', true );
        if ( ! defined( 'DONOTMINIFY' ) )     define( 'DONOTMINIFY', true );
        if ( ! defined( 'DONOTCDN' ) )        define( 'DONOTCDN', true );
        if ( ! defined( 'DONOTCACHEOBJECT' ) ) define( 'DONOTCACHEOBJECT', true );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Cache-Control: post-check=0, pre-check=0', false );
        header( 'Pragma: no-cache' );
        header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
        header( 'X-Accel-Expires: 0' );
        header( 'CDN-Cache-Control: no-cache' );
        header( 'CF-Cache-Status: BYPASS' );
        add_action( 'wp_head', [ __CLASS__, 'output_no_cache_meta_tags' ], 1 );
    }

    public static function output_no_cache_meta_tags() {
        echo '<!-- Elementor Form Lead Tracker: Cache Bypass Active -->' . "\n";
        echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">' . "\n";
        echo '<meta http-equiv="Pragma" content="no-cache">' . "\n";
        echo '<meta http-equiv="Expires" content="0">' . "\n";
    }
}
