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

/**
 * Get vacation/holiday periods between two dates
 * Returns all vacation events that overlap with the given date range
 *
 * @param string $start_date Start date in Y-m-d format
 * @param string $end_date End date in Y-m-d format
 * @return array Array of vacation periods with 'start' and 'end' dates
 */
function smc_get_vacation_periods( $start_date, $end_date ) {
    global $wpdb;

    $events_table = $wpdb->prefix . 'smc_events';

    // Get all vacation/holiday events that overlap with the given date range
    // Vacation types: 'holiday', 'school_closure'
    $vacation_events = $wpdb->get_results( $wpdb->prepare(
        "SELECT event_date, event_end_date, title
         FROM $events_table
         WHERE event_type IN ('holiday', 'school_closure')
         AND (
             (event_date BETWEEN %s AND %s)
             OR (event_end_date IS NOT NULL AND event_end_date BETWEEN %s AND %s)
             OR (event_date <= %s AND (event_end_date IS NULL OR event_end_date >= %s))
         )
         ORDER BY event_date ASC",
        $start_date, $end_date,
        $start_date, $end_date,
        $start_date, $end_date
    ) );

    $periods = [];
    foreach ( $vacation_events as $event ) {
        $periods[] = [
            'start' => $event->event_date,
            'end' => $event->event_end_date ?: $event->event_date, // If no end date, vacation is just one day
            'title' => $event->title
        ];
    }

    return $periods;
}

/**
 * Calculate next payment date accounting for vacation periods
 * Takes a date and adds a specified interval, then adds any vacation days that occurred
 * between the original date and the calculated next date
 *
 * @param string $from_date Starting date in Y-m-d format
 * @param string $interval Date interval like '+1 month'
 * @return string Next date in Y-m-d format after adding vacation days
 */
function smc_calculate_next_payment_date( $from_date, $interval = '+1 month' ) {
    // Calculate the initial next date (base calculation)
    $base_next_date = date( 'Y-m-d', strtotime( $interval, strtotime( $from_date ) ) );

    // Get vacation periods that overlap with the period between from_date and base_next_date
    $vacation_periods = smc_get_vacation_periods( $from_date, $base_next_date );

    if ( empty( $vacation_periods ) ) {
        return $base_next_date; // No vacations, return the base calculated date
    }

    // Calculate total vacation days that occur between from_date and base_next_date
    $total_vacation_days = 0;
    $from_timestamp = strtotime( $from_date );
    $base_next_timestamp = strtotime( $base_next_date );

    foreach ( $vacation_periods as $vacation ) {
        $vacation_start = strtotime( $vacation['start'] );
        $vacation_end = strtotime( $vacation['end'] );

        // Determine the overlap between vacation and the payment period
        // Start of overlap: later of (from_date, vacation_start)
        $overlap_start = max( $from_timestamp, $vacation_start );

        // End of overlap: earlier of (base_next_date, vacation_end)
        $overlap_end = min( $base_next_timestamp, $vacation_end );

        // If there's actual overlap (overlap_start < overlap_end)
        if ( $overlap_start < $overlap_end ) {
            // Calculate days of overlap (+1 to include both start and end days)
            $overlap_days = ( $overlap_end - $overlap_start ) / ( 60 * 60 * 24 ) + 1;
            $total_vacation_days += $overlap_days;

            // Log the adjustment for debugging
            error_log( sprintf(
                'SMC Payment: Vacation overlap detected - %s (%s to %s): %d days added',
                $vacation['title'],
                date( 'Y-m-d', $overlap_start ),
                date( 'Y-m-d', $overlap_end ),
                $overlap_days
            ) );
        }
    }

    // Add total vacation days to the base next date
    if ( $total_vacation_days > 0 ) {
        $final_next_date = date( 'Y-m-d', strtotime( "+{$total_vacation_days} days", strtotime( $base_next_date ) ) );

        error_log( sprintf(
            'SMC Payment: Total adjustment - Base: %s + %d vacation days = Final: %s',
            $base_next_date,
            $total_vacation_days,
            $final_next_date
        ) );

        return $final_next_date;
    }

    return $base_next_date;
}