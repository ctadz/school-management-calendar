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
 * Calculate subscription payment date based on enrollment start date and installment number
 *
 * Logic:
 * - Normally preserves the original start day (e.g., Jan 31 → Feb 28 → Mar 31 → Apr 30 → May 31)
 * - If a previous payment was adjusted due to vacation, uses the adjusted day going forward
 * - Any vacation days between previous payment and next payment are added to the next payment date
 *   (student didn't have courses during vacation, so payment is deferred)
 *
 * @param string $start_date Enrollment start date in Y-m-d format
 * @param int $installment_number The installment number (1 = first payment on start_date)
 * @param string|null $previous_due_date Previous payment's due date (to detect vacation adjustments)
 * @return string Payment date in Y-m-d format (with vacation adjustment applied)
 */
function smc_calculate_subscription_payment_date( $start_date, $installment_number, $previous_due_date = null ) {
    // First payment is always on start_date (no vacation adjustment for first payment)
    if ( $installment_number === 1 ) {
        return $start_date;
    }

    // Calculate what the previous payment SHOULD have been based on start_date
    $expected_previous = smc_add_months_preserve_day( $start_date, $installment_number - 2 );

    // Check if vacation has adjusted a previous payment
    // If previous_due_date differs from expected, vacation has changed the pattern
    if ( $previous_due_date !== null && $previous_due_date !== $expected_previous ) {
        // Vacation adjustment detected - use previous payment's day going forward
        $base_date = smc_add_months_preserve_day( $previous_due_date, 1 );

        error_log( sprintf(
            'SMC Payment: Installment #%d using adjusted day pattern. Previous due: %s (expected: %s). Base: %s',
            $installment_number,
            $previous_due_date,
            $expected_previous,
            $base_date
        ) );
    } else {
        // No vacation adjustment - use original start_date's day
        $base_date = smc_add_months_preserve_day( $start_date, $installment_number - 1 );
    }

    // Apply vacation adjustment: add any vacation days between previous payment and base date
    $adjusted_date = smc_add_vacation_days_between( $previous_due_date, $base_date );

    if ( $adjusted_date !== $base_date ) {
        error_log( sprintf(
            'SMC Payment: Installment #%d base date %s adjusted to %s due to vacation days between %s and %s',
            $installment_number,
            $base_date,
            $adjusted_date,
            $previous_due_date,
            $base_date
        ) );
    }

    return $adjusted_date;
}

/**
 * Add vacation days that occur between two dates to the target date
 *
 * Handles two cases:
 * 1. If to_date falls INSIDE a vacation: new_date = vacation_end + (to_date - vacation_start)
 *    (Student gets the days they would have had before vacation, added after vacation)
 * 2. If to_date is AFTER vacation: add full vacation overlap days to to_date
 *    (Student gets all the vacation days added to their payment date)
 *
 * @param string $from_date Start of period (previous payment date) in Y-m-d format
 * @param string $to_date Target date (next payment date) in Y-m-d format
 * @return string Adjusted date with vacation days added
 */
function smc_add_vacation_days_between( $from_date, $to_date ) {
    if ( empty( $from_date ) || empty( $to_date ) ) {
        return $to_date;
    }

    // Search for vacations in a wider range to catch all relevant ones
    $search_start = $from_date;
    $search_end = date( 'Y-m-d', strtotime( '+2 months', strtotime( $to_date ) ) );

    $vacation_periods = smc_get_vacation_periods( $search_start, $search_end );

    if ( empty( $vacation_periods ) ) {
        return $to_date; // No vacations, return original date
    }

    $from_dt = new DateTime( $from_date );
    $to_dt = new DateTime( $to_date );
    $current_date = $to_date;
    $total_days_added = 0;
    $max_iterations = 5; // Prevent infinite loops

    for ( $iteration = 0; $iteration < $max_iterations; $iteration++ ) {
        $current_dt = new DateTime( $current_date );
        $adjustment_made = false;

        foreach ( $vacation_periods as $vacation ) {
            $vacation_start_dt = new DateTime( $vacation['start'] );
            $vacation_end_dt = new DateTime( $vacation['end'] );

            // Case 1: Current date falls INSIDE this vacation
            if ( $current_dt >= $vacation_start_dt && $current_dt <= $vacation_end_dt ) {
                // Calculate offset: days from vacation start to current date
                $offset = $vacation_start_dt->diff( $current_dt );
                $offset_days = $offset->days;

                // New date = vacation_end + offset
                $new_dt = clone $vacation_end_dt;
                $new_dt->modify( "+{$offset_days} days" );
                $new_date = $new_dt->format( 'Y-m-d' );

                error_log( sprintf(
                    'SMC Payment: Date %s falls inside vacation "%s" (%s to %s). Offset: %d days. New date: %s',
                    $current_date,
                    $vacation['title'],
                    $vacation['start'],
                    $vacation['end'],
                    $offset_days,
                    $new_date
                ) );

                $total_days_added += ( $new_dt->diff( $current_dt ) )->days;
                $current_date = $new_date;
                $adjustment_made = true;
                break; // Restart loop with new date to check for more vacations
            }

            // Case 2: Vacation is between from_date and current_date (but current_date is after vacation)
            if ( $vacation_end_dt < $current_dt && $vacation_start_dt > $from_dt ) {
                // This vacation is fully between from_date and current_date
                // Add the full vacation duration
                $vacation_duration = $vacation_start_dt->diff( $vacation_end_dt );
                $vacation_days = $vacation_duration->days + 1; // +1 to include both start and end

                $new_dt = new DateTime( $current_date );
                $new_dt->modify( "+{$vacation_days} days" );
                $new_date = $new_dt->format( 'Y-m-d' );

                error_log( sprintf(
                    'SMC Payment: Vacation "%s" (%s to %s) is between %s and %s. Adding %d days. New date: %s',
                    $vacation['title'],
                    $vacation['start'],
                    $vacation['end'],
                    $from_date,
                    $current_date,
                    $vacation_days,
                    $new_date
                ) );

                $total_days_added += $vacation_days;
                $current_date = $new_date;
                // Update from_dt to vacation_end so we don't count this vacation again
                $from_dt = clone $vacation_end_dt;
                $from_dt->modify( '+1 day' );
                $adjustment_made = true;
                break; // Restart loop with new date
            }

            // Case 3: Vacation overlaps with from_date (vacation started before from_date but ends after)
            if ( $vacation_start_dt <= $from_dt && $vacation_end_dt > $from_dt && $vacation_end_dt < $current_dt ) {
                // Only count days from from_date to vacation_end
                $overlap = $from_dt->diff( $vacation_end_dt );
                $overlap_days = $overlap->days + 1;

                $new_dt = new DateTime( $current_date );
                $new_dt->modify( "+{$overlap_days} days" );
                $new_date = $new_dt->format( 'Y-m-d' );

                error_log( sprintf(
                    'SMC Payment: Vacation "%s" overlaps start. Adding %d days (from %s to %s). New date: %s',
                    $vacation['title'],
                    $overlap_days,
                    $from_date,
                    $vacation['end'],
                    $new_date
                ) );

                $total_days_added += $overlap_days;
                $current_date = $new_date;
                $from_dt = clone $vacation_end_dt;
                $from_dt->modify( '+1 day' );
                $adjustment_made = true;
                break;
            }
        }

        if ( ! $adjustment_made ) {
            // No more adjustments needed
            break;
        }
    }

    if ( $total_days_added > 0 ) {
        error_log( sprintf(
            'SMC Payment: Total vacation adjustment: %s + %d days = %s',
            $to_date,
            $total_days_added,
            $current_date
        ) );
    }

    return $current_date;
}

/**
 * Add months to a date while preserving the original day of month
 * If the target month doesn't have that day, uses the last day of the month
 *
 * Examples:
 * - Jan 31 + 1 month = Feb 28 (Feb doesn't have 31 days)
 * - Jan 31 + 2 months = Mar 31
 * - Mar 31 + 1 month = Apr 30 (Apr doesn't have 31 days)
 * - Apr 30 + 1 month = May 31 (original day was 31, May has 31 days)
 *
 * Note: This function uses the ORIGINAL day from start_date, not the adjusted day
 *
 * @param string $start_date Original start date in Y-m-d format
 * @param int $months Number of months to add
 * @return string Target date in Y-m-d format
 */
function smc_add_months_preserve_day( $start_date, $months ) {
    if ( $months === 0 ) {
        return $start_date;
    }

    $dt = new DateTime( $start_date );
    $original_day = (int) $dt->format( 'j' ); // Day of month (1-31)

    // Move to first day of month, then add months
    $dt->modify( 'first day of this month' );
    $dt->modify( "+{$months} months" );

    // Get target month info
    $target_year = (int) $dt->format( 'Y' );
    $target_month = (int) $dt->format( 'n' );
    $last_day_of_target = (int) $dt->format( 't' ); // Days in target month

    // Use original day or last day of month, whichever is smaller
    $target_day = min( $original_day, $last_day_of_target );

    // Set the final date
    $dt->setDate( $target_year, $target_month, $target_day );

    return $dt->format( 'Y-m-d' );
}

/**
 * Calculate next payment date accounting for vacation periods (for subscriptions only)
 * DEPRECATED: Use smc_calculate_subscription_payment_date() instead for proper day preservation
 *
 * @param string $from_date Starting date in Y-m-d format
 * @param string $interval Date interval like '+1 month'
 * @return string Next date in Y-m-d format after vacation adjustment
 */
function smc_calculate_next_payment_date( $from_date, $interval = '+1 month' ) {
    // Calculate the initial next date (base calculation)
    $base_next_date = date( 'Y-m-d', strtotime( $interval, strtotime( $from_date ) ) );

    // Apply vacation adjustment (may need multiple passes if adjusted date falls in another vacation)
    $adjusted_date = smc_adjust_date_for_vacation( $base_next_date );

    if ( $adjusted_date !== $base_next_date ) {
        error_log( sprintf(
            'SMC Payment: Base date %s adjusted to %s due to vacation',
            $base_next_date,
            $adjusted_date
        ) );
    }

    return $adjusted_date;
}

/**
 * Adjust a date if it falls within a vacation period
 * Recursively handles cases where adjusted date falls into another vacation
 *
 * @param string $date Date in Y-m-d format
 * @param int $max_iterations Maximum iterations to prevent infinite loops
 * @return string Adjusted date in Y-m-d format
 */
function smc_adjust_date_for_vacation( $date, $max_iterations = 5 ) {
    $current_date = $date;

    for ( $i = 0; $i < $max_iterations; $i++ ) {
        $current_timestamp = strtotime( $current_date );

        // Look for vacations around the current date
        $search_start = date( 'Y-m-d', strtotime( '-2 months', $current_timestamp ) );
        $search_end = date( 'Y-m-d', strtotime( '+2 months', $current_timestamp ) );

        $vacation_periods = smc_get_vacation_periods( $search_start, $search_end );

        if ( empty( $vacation_periods ) ) {
            return $current_date; // No vacations found
        }

        $found_vacation = false;

        // Check if the current date falls within any vacation period
        foreach ( $vacation_periods as $vacation ) {
            $vacation_start_date = $vacation['start'];
            $vacation_end_date = $vacation['end'];

            // Use DateTime for more reliable date comparisons
            $payment_dt = new DateTime( $current_date );
            $vacation_start_dt = new DateTime( $vacation_start_date );
            $vacation_end_dt = new DateTime( $vacation_end_date );

            // Check if payment date falls within this vacation (inclusive)
            if ( $payment_dt >= $vacation_start_dt && $payment_dt <= $vacation_end_dt ) {
                // Payment date is during vacation
                // Calculate offset: days from vacation start to payment date
                $offset = $vacation_start_dt->diff( $payment_dt );
                $offset_days = $offset->days;

                // New payment date = vacation end + offset days
                $new_date_dt = clone $vacation_end_dt;
                $new_date_dt->modify( "+{$offset_days} days" );
                $new_date = $new_date_dt->format( 'Y-m-d' );

                // Log the adjustment for debugging
                error_log( sprintf(
                    'SMC Payment: Date %s falls in vacation "%s" (%s to %s). Offset: %d days. New date: %s',
                    $current_date,
                    $vacation['title'],
                    $vacation_start_date,
                    $vacation_end_date,
                    $offset_days,
                    $new_date
                ) );

                $current_date = $new_date;
                $found_vacation = true;
                break; // Restart the check with the new date
            }
        }

        if ( ! $found_vacation ) {
            // Current date doesn't fall within any vacation
            return $current_date;
        }
    }

    // If we've exhausted iterations, return the current date
    error_log( 'SMC Payment: Max vacation adjustment iterations reached for date: ' . $date );
    return $current_date;
}