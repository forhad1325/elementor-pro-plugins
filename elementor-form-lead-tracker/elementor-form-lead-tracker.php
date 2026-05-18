<?php
/**
 * Plugin Name:       Elementor Form Lead Tracker — Protected Content & Analytics
 * Plugin URI:        https://github.com/forhad1325/elementor-pro-plugins
 * Description:       Gate protected content behind Elementor forms with secure cookie-based tokens. Track leads, monitor downloads, and sync data with GA4 and Freshsales CRM.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            TrustyCoder
 * Author URI:        https://trustycoder.com
 * License:           GPL-2.0-or-later
 * Text Domain:       elementor-form-lead-tracker
 * Requires Plugins:  elementor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EFLT_VERSION', '1.0.0' );
define( 'EFLT_TABLE', 'eflt_submissions' );
define( 'EFLT_COOKIE_PREFIX', 'eflt_access_' );
define( 'EFLT_COOKIE_DAYS', 7 );
define( 'EFLT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EFLT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

register_activation_hook( __FILE__, 'eflt_activate' );
function eflt_activate() {
    require_once EFLT_PLUGIN_DIR . 'includes/class-database.php';
    EFLT_Database::create_tables();
}

add_action( 'plugins_loaded', 'eflt_load_plugin' );
function eflt_load_plugin() {
    if ( ! did_action( 'elementor/loaded' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>Elementor Form Lead Tracker</strong> requires Elementor Pro to be installed and activated.</p></div>';
        } );
        return;
    }
    require_once EFLT_PLUGIN_DIR . 'includes/class-database.php';
    require_once EFLT_PLUGIN_DIR . 'includes/class-form-controls.php';
    require_once EFLT_PLUGIN_DIR . 'includes/class-form-handler.php';
    require_once EFLT_PLUGIN_DIR . 'includes/class-page-gate.php';
    require_once EFLT_PLUGIN_DIR . 'includes/class-download-tracker.php';
    require_once EFLT_PLUGIN_DIR . 'includes/class-freshsales.php';
    require_once EFLT_PLUGIN_DIR . 'includes/class-admin.php';

    EFLT_Form_Controls::init();
    EFLT_Form_Handler::init();
    EFLT_Page_Gate::init();
    EFLT_Download_Tracker::init();
    EFLT_Admin::init();
}
