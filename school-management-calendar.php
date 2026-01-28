<?php
/**
 * Plugin Name: School Management - Calendar & Schedule
 * Plugin URI: https://github.com/ahmedsebaa/school-management-calendar
 * Description: Advanced scheduling and calendar system for School Management plugin. Manage course schedules, events, and timetables.
 * Version: 1.1.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: school-management
 * Author: Ahmed Sebaa
 * Author URI: https://github.com/ahmedsebaa
 * License: GPL v2 or later (will change to Proprietary for marketplace)
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: school-management-calendar
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SMC_VERSION', '1.1.0' );
define( 'SMC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMC_PLUGIN_FILE', __FILE__ );
define( 'SMC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Development mode - set to false when ready to sell
define( 'SMC_DEV_MODE', true );

// Include GitHub updater for automatic plugin updates
require_once SMC_PLUGIN_DIR . 'includes/class-smc-github-updater.php';

/**
 * Initialize automatic updates from GitHub
 */
function smc_init_github_updater() {
	if ( is_admin() ) {
		new SMC_GitHub_Updater(
			SMC_PLUGIN_FILE,
			'ctadz/school-management-calendar', // GitHub repository
			defined( 'SMC_GITHUB_TOKEN' ) ? SMC_GITHUB_TOKEN : null
		);
	}
}
add_action( 'admin_init', 'smc_init_github_updater' );

/**
 * Check if core School Management plugin is active
 */
function smc_check_core_plugin() {
    // Check if core plugin is active
    // @phpstan-ignore-next-line - Constant defined in core plugin
    if ( ! defined( 'SM_VERSION' ) ) {
        add_action( 'admin_notices', 'smc_missing_core_notice' );
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return false;
    }

    // Check minimum core version required
    if ( version_compare( SM_VERSION, '0.3.0', '<' ) ) {
        add_action( 'admin_notices', 'smc_outdated_core_notice' );
        deactivate_plugins( plugin_basename( __FILE__ ) );
        return false;
    }

    return true;
}

/**
 * Notice: Core plugin missing
 */
function smc_missing_core_notice() {
    $plugin_name = esc_html__( 'School Management - Calendar & Schedule', 'school-management-calendar' );
    $message = esc_html__( 'requires the core School Management plugin to be installed and activated.', 'school-management-calendar' );
    $button_text = esc_html__( 'Install Core Plugin', 'school-management-calendar' );
    $button_url = admin_url( 'plugin-install.php?s=school+management&tab=search' );
    
    printf(
        '<div class="error notice"><p><strong>%s</strong> %s</p><p><a href="%s" class="button button-primary">%s</a></p></div>',
        $plugin_name,
        $message,
        esc_url( $button_url ),
        $button_text
    );
}

/**
 * Notice: Core plugin outdated
 */
function smc_outdated_core_notice() {
    $plugin_name = esc_html__( 'School Management - Calendar & Schedule', 'school-management-calendar' );
    $message = esc_html__( 'requires School Management plugin version 1.0.0 or higher. Please update the core plugin.', 'school-management-calendar' );
    
    printf(
        '<div class="error notice"><p><strong>%s</strong> %s</p></div>',
        $plugin_name,
        $message
    );
}

/**
 * Check license (placeholder for future)
 */
function smc_check_license() {
    // In DEV mode, always return true
    if ( SMC_DEV_MODE ) {
        return true;
    }

    // Future: Check license validity
    $license_key = get_option( 'smc_license_key', '' );
    $license_status = get_option( 'smc_license_status', '' );

    if ( empty( $license_key ) || $license_status !== 'valid' ) {
        add_action( 'admin_notices', 'smc_license_notice' );
        return false;
    }

    return true;
}

/**
 * Notice: License required
 */
function smc_license_notice() {
    $plugin_name = esc_html__( 'School Management - Calendar & Schedule', 'school-management-calendar' );
    $message = esc_html__( 'requires a valid license key to function. Please activate your license.', 'school-management-calendar' );
    $button_text = esc_html__( 'Activate License', 'school-management-calendar' );
    $button_url = admin_url( 'admin.php?page=school-management-calendar-license' );
    
    printf(
        '<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p><a href="%s" class="button button-primary">%s</a></p></div>',
        $plugin_name,
        $message,
        esc_url( $button_url ),
        $button_text
    );
}

/**
 * Initialize the plugin
 */
function smc_init() {
    // Check core plugin
    if ( ! smc_check_core_plugin() ) {
        return;
    }

    // Check license
    if ( ! smc_check_license() ) {
        return;
    }

    // Load text domain for translations
    load_plugin_textdomain( 'school-management-calendar', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Load plugin files
    require_once SMC_PLUGIN_DIR . 'includes/smc-loader.php';
}
add_action( 'plugins_loaded', 'smc_init' );

/**
 * Activation hook
 */
function smc_activate() {
    // Just create tables, don't check for core plugin during activation
    require_once SMC_PLUGIN_DIR . 'includes/class-smc-activator.php';
    SMC_Activator::activate();
}
register_activation_hook( __FILE__, 'smc_activate' );

/**
 * Deactivation hook
 */
function smc_deactivate() {
    // Future: Cleanup if needed
}
register_deactivation_hook( __FILE__, 'smc_deactivate' );