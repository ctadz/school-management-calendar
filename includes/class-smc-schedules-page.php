<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMC_Schedules_Page {

    /**
     * Render the Schedules page
     */
    public static function render_schedules_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Course Schedules', 'school-management-calendar' ); ?></h1>
            <p><?php esc_html_e( 'Manage recurring course sessions (weekly classes).', 'school-management-calendar' ); ?></p>
            
            <div style="padding: 40px; background: #f0f0f1; border: 2px dashed #c3c4c7; text-align: center; margin-top: 20px;">
                <span class="dashicons dashicons-calendar-alt" style="font-size: 48px; color: #c3c4c7;"></span>
                <h2><?php esc_html_e( 'Schedules Management - Coming Next!', 'school-management-calendar' ); ?></h2>
                <p><?php esc_html_e( 'This page will allow you to create and manage recurring course schedules.', 'school-management-calendar' ); ?></p>
            </div>
        </div>
        <?php
    }
}