<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get days of week in correct order based on settings
 * 
 * @return array Days of week with numeric keys and translated names
 */
function smc_get_days_of_week() {
    // Get first day of week from core plugin settings
    $settings = get_option( 'sm_school_settings', [] );
    $first_day_string = $settings['first_day_of_week'] ?? 'Monday'; // Default to Monday

    // Convert string to numeric (1 = Monday, 7 = Sunday in ISO-8601)
    $start_day = ( $first_day_string === 'Sunday' ) ? 7 : 1;
    
    // All days with ISO-8601 numeric representation (1 = Monday, 7 = Sunday)
    $all_days = [
        1 => __( 'Monday', 'school-management-calendar' ),
        2 => __( 'Tuesday', 'school-management-calendar' ),
        3 => __( 'Wednesday', 'school-management-calendar' ),
        4 => __( 'Thursday', 'school-management-calendar' ),
        5 => __( 'Friday', 'school-management-calendar' ),
        6 => __( 'Saturday', 'school-management-calendar' ),
        7 => __( 'Sunday', 'school-management-calendar' ),
    ];
    
    // Reorder days based on first day setting
    $ordered_days = [];
    
    // Build array starting from first day
    for ( $i = 0; $i < 7; $i++ ) {
        $day_num = ( ( $start_day - 1 + $i ) % 7 ) + 1;
        $ordered_days[ $day_num ] = $all_days[ $day_num ];
    }
    
    return $ordered_days;
}

/**
 * Get start of week day number
 * 
 * @return int Day number (1-7, where 1 = Monday, 7 = Sunday)
 */
function smc_get_start_of_week() {
    $settings = get_option( 'sm_school_settings', [] );
    $first_day_string = $settings['first_day_of_week'] ?? 'Monday';
    // Convert string to numeric (1 = Monday, 7 = Sunday)
    return ( $first_day_string === 'Sunday' ) ? 7 : 1;
}