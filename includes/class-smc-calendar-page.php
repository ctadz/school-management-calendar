<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMC_Calendar_Page {

    /**
     * Render the Calendar page
     */
    public static function render_calendar_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'School Calendar', 'school-management-calendar' ); ?></h1>
            <p><?php esc_html_e( 'Unified view of all schedules and events.', 'school-management-calendar' ); ?></p>
            
            <div style="padding: 40px; background: #f0f0f1; border: 2px dashed #c3c4c7; text-align: center; margin-top: 20px;">
                <span class="dashicons dashicons-calendar" style="font-size: 48px; color: #c3c4c7;"></span>
                <h2><?php esc_html_e( 'Calendar View - Coming Next!', 'school-management-calendar' ); ?></h2>
                <p><?php esc_html_e( 'This page will display a full calendar with month, week, and day views.', 'school-management-calendar' ); ?></p>
            </div>
        </div>
        <?php
    }
}