<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMC_Activator {

    /**
     * Activate the plugin
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        
        // Store version
        update_option( 'smc_version', SMC_VERSION );
        
        // Set activation flag
        update_option( 'smc_activated', current_time( 'mysql' ) );
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table 1: Schedules (Recurring Course Sessions)
        $table_schedules = $wpdb->prefix . 'smc_schedules';
        $sql_schedules = "CREATE TABLE IF NOT EXISTS $table_schedules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            course_id BIGINT UNSIGNED NOT NULL,
            classroom_id BIGINT UNSIGNED NULL,
            teacher_id BIGINT UNSIGNED NULL,
            day_of_week TINYINT NOT NULL COMMENT '1=Monday, 7=Sunday',
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            effective_from DATE NOT NULL,
            effective_until DATE NULL,
            recurrence_type VARCHAR(20) DEFAULT 'weekly',
            recurrence_interval INT DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            notes TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_course (course_id),
            INDEX idx_classroom (classroom_id),
            INDEX idx_teacher (teacher_id),
            INDEX idx_day (day_of_week),
            INDEX idx_dates (effective_from, effective_until)
        ) $charset_collate;";

        // Table 2: Events (One-Time Events)
        $table_events = $wpdb->prefix . 'smc_events';
        $sql_events = "CREATE TABLE IF NOT EXISTS $table_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            event_type VARCHAR(30) NOT NULL,
            event_date DATE NOT NULL,
            start_time TIME NULL,
            end_time TIME NULL,
            is_all_day TINYINT(1) DEFAULT 0,
            course_id BIGINT UNSIGNED NULL,
            classroom_id BIGINT UNSIGNED NULL,
            teacher_id BIGINT UNSIGNED NULL,
            color VARCHAR(7) DEFAULT '#3788d8',
            is_public TINYINT(1) DEFAULT 1,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_event_date (event_date),
            INDEX idx_course (course_id),
            INDEX idx_teacher (teacher_id),
            INDEX idx_public (is_public)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_schedules );
        dbDelta( $sql_events );
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = [
            'smc_calendar_start_day' => 1, // Monday
            'smc_time_slot_duration' => 30, // 30 minutes
            'smc_calendar_start_hour' => 8, // 8 AM
            'smc_calendar_end_hour' => 18, // 6 PM
            'smc_enable_conflict_check' => 1,
        ];

        foreach ( $defaults as $key => $value ) {
            if ( get_option( $key ) === false ) {
                add_option( $key, $value );
            }
        }
    }
}