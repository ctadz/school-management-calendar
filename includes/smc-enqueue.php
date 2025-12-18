<?php
/**
 * Enqueue scripts and styles for School Management Calendar
 *
 * @package SchoolManagementCalendar
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue admin styles and scripts
 */
function smc_enqueue_admin_assets( $hook ) {
    // Only load on our plugin pages
    $our_pages = array(
        'school-management_page_school-management-calendar',
        'school-management_page_school-management-schedules',
        'school-management_page_school-management-events',
    );

    if ( ! in_array( $hook, $our_pages, true ) ) {
        return;
    }

    // Enqueue calendar CSS
    wp_enqueue_style(
        'smc-calendar-admin',
        SMC_PLUGIN_URL . 'assets/css/smc-calendar.css',
        array(),
        SMC_VERSION,
        'all'
    );

    // Enqueue color picker for events
    if ( strpos( $hook, 'school-management-events' ) !== false ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }

    // Future: Enqueue JavaScript files
    // wp_enqueue_script(
    //     'smc-calendar-admin',
    //     SMC_PLUGIN_URL . 'assets/js/smc-calendar.js',
    //     array( 'jquery' ),
    //     SMC_VERSION,
    //     true
    // );
}
add_action( 'admin_enqueue_scripts', 'smc_enqueue_admin_assets' );
