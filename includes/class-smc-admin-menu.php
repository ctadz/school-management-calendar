<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMC_Admin_Menu {

    /**
     * Initialize hooks
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_menus' ], 20 );
    }

    /**
     * Add plugin menus to School Management parent menu
     */
    public static function add_menus() {
        // Calendar submenu
        add_submenu_page(
            'school-management',
            __( 'Calendar', 'school-management-calendar' ),
            '<span class="dashicons dashicons-calendar" style="font-size: 17px; vertical-align: middle; color: #8e44ad !important;"></span> ' . __( 'Calendar', 'school-management-calendar' ),
            'view_calendar',
            'school-management-calendar',
            [ 'SMC_Calendar_Page', 'render_calendar_page' ]
        );

        // Schedules submenu
        add_submenu_page(
            'school-management',
            __( 'Schedules', 'school-management-calendar' ),
             '<span class="dashicons dashicons-clock" style="font-size: 17px; vertical-align: middle; color: #8e44ad !important;"></span> ' . __( 'Schedules', 'school-management-calendar' ),
            'manage_schedules',
            'school-management-schedules',
            [ 'SMC_Schedules_Page', 'render_schedules_page' ]
        );

        // Events submenu
        add_submenu_page(
            'school-management',
            __( 'Events', 'school-management-calendar' ),
            '<span class="dashicons dashicons-megaphone" style="font-size: 17px; vertical-align: middle; color: #c0392b !important;"></span> ' . __( 'Events', 'school-management-calendar' ),
            'manage_events',
            'school-management-events',
            [ 'SMC_Events_Page', 'render_events_page' ]
        );
    }
}

// Initialize the menu
SMC_Admin_Menu::init();