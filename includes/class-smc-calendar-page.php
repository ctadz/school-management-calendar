<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMC_Calendar_Page {

    /**
     * Get schedules for a date range
     */
    private static function get_schedules_for_range( $start_date, $end_date ) {
        global $wpdb;
        $schedules_table = $wpdb->prefix . 'smc_schedules';
        $courses_table = $wpdb->prefix . 'sm_courses';
        $classrooms_table = $wpdb->prefix . 'sm_classrooms';
        $teachers_table = $wpdb->prefix . 'sm_teachers';

        $schedules = $wpdb->get_results( $wpdb->prepare( 
            "SELECT s.*, 
                    c.name as course_name,
                    cr.name as classroom_name,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
             FROM $schedules_table s 
             LEFT JOIN $courses_table c ON s.course_id = c.id 
             LEFT JOIN $classrooms_table cr ON s.classroom_id = cr.id
             LEFT JOIN $teachers_table t ON s.teacher_id = t.id
             WHERE s.is_active = 1
               AND s.effective_from <= %s
               AND (s.effective_until IS NULL OR s.effective_until >= %s)
             ORDER BY s.day_of_week ASC, s.start_time ASC", 
            $end_date,
            $start_date
        ) );

        return $schedules;
    }

    /**
     * Get events for a date range
     */
    private static function get_events_for_range( $start_date, $end_date ) {
        global $wpdb;
        $events_table = $wpdb->prefix . 'smc_events';
        $courses_table = $wpdb->prefix . 'sm_courses';
        $classrooms_table = $wpdb->prefix . 'sm_classrooms';
        $teachers_table = $wpdb->prefix . 'sm_teachers';

        $events = $wpdb->get_results( $wpdb->prepare( 
            "SELECT e.*, 
                    c.name as course_name,
                    cr.name as classroom_name,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
             FROM $events_table e 
             LEFT JOIN $courses_table c ON e.course_id = c.id 
             LEFT JOIN $classrooms_table cr ON e.classroom_id = cr.id
             LEFT JOIN $teachers_table t ON e.teacher_id = t.id
             WHERE e.event_date BETWEEN %s AND %s
             ORDER BY e.event_date ASC, e.start_time ASC", 
            $start_date,
            $end_date
        ) );

        return $events;
    }

    /**
     * Generate schedule instances for a date range
     */
    private static function generate_schedule_instances( $schedules, $start_date, $end_date ) {
        $instances = [];
        
        $start = new DateTime( $start_date );
        $end = new DateTime( $end_date );
        
        foreach ( $schedules as $schedule ) {
            $current = clone $start;
            
            while ( $current <= $end ) {
                // Check if current day matches schedule's day of week
                // PHP's N format: 1=Monday, 7=Sunday (matches our DB)
                if ( $current->format('N') == $schedule->day_of_week ) {
                    $date = $current->format('Y-m-d');
                    
                    $instances[] = [
                        'type' => 'schedule',
                        'id' => $schedule->id,
                        'date' => $date,
                        'title' => $schedule->course_name,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'classroom' => $schedule->classroom_name,
                        'teacher' => $schedule->teacher_name,
                        'color' => '#0073aa', // Blue for schedules
                        'is_all_day' => false,
                    ];
                }
                
                $current->modify('+1 day');
            }
        }
        
        return $instances;
    }

    /**
     * Combine and sort all calendar items
     */
    private static function get_calendar_items( $start_date, $end_date ) {
        $schedules = self::get_schedules_for_range( $start_date, $end_date );
        $events = self::get_events_for_range( $start_date, $end_date );
        
        // Generate schedule instances
        $schedule_instances = self::generate_schedule_instances( $schedules, $start_date, $end_date );
        
        // Format events
        $event_items = [];
        foreach ( $events as $event ) {
            $event_items[] = [
                'type' => 'event',
                'id' => $event->id,
                'date' => $event->event_date,
                'title' => $event->title,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'classroom' => $event->classroom_name,
                'teacher' => $event->teacher_name,
                'color' => $event->color,
                'is_all_day' => $event->is_all_day,
                'event_type' => $event->event_type,
            ];
        }
        
        // Combine and sort
        $all_items = array_merge( $schedule_instances, $event_items );
        
        // Sort by date and time
        usort( $all_items, function( $a, $b ) {
            if ( $a['date'] === $b['date'] ) {
                if ( $a['is_all_day'] && ! $b['is_all_day'] ) return -1;
                if ( ! $a['is_all_day'] && $b['is_all_day'] ) return 1;
                return strcmp( $a['start_time'], $b['start_time'] );
            }
            return strcmp( $a['date'], $b['date'] );
        });
        
        return $all_items;
    }
    /**
     * Render the Calendar page
     */
    public static function render_calendar_page() {
        // security check
        if ( ! current_user_can( 'view_calendar' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'school-management-calendar' ) );
        }        
 
       // Get current view and date
        $view = $_GET['view'] ?? 'month';
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Validate view
        $valid_views = ['month', 'week', 'day'];
        if ( ! in_array( $view, $valid_views ) ) {
            $view = 'month';
        }
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'School Calendar', 'school-management-calendar' ); ?></h1>

            <!-- View Switcher and Navigation -->
            <div class="tablenav top" style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <!-- View Buttons -->
                    <div class="button-group">
                        <a href="?page=school-management-calendar&view=month&date=<?php echo esc_attr( $date ); ?>" 
                           class="button <?php echo $view === 'month' ? 'button-primary' : ''; ?>">
                            <?php esc_html_e( 'Month', 'school-management-calendar' ); ?>
                        </a>
                        <a href="?page=school-management-calendar&view=week&date=<?php echo esc_attr( $date ); ?>" 
                           class="button <?php echo $view === 'week' ? 'button-primary' : ''; ?>">
                            <?php esc_html_e( 'Week', 'school-management-calendar' ); ?>
                        </a>
                        <a href="?page=school-management-calendar&view=day&date=<?php echo esc_attr( $date ); ?>" 
                           class="button <?php echo $view === 'day' ? 'button-primary' : ''; ?>">
                            <?php esc_html_e( 'Day', 'school-management-calendar' ); ?>
                        </a>
                    </div>

                    <!-- Today Button -->
                    <a href="?page=school-management-calendar&view=<?php echo esc_attr( $view ); ?>&date=<?php echo date('Y-m-d'); ?>" 
                       class="button">
                        <?php esc_html_e( 'Today', 'school-management-calendar' ); ?>
                    </a>
                </div>

                <!-- Quick Add Buttons -->
                <div style="display: flex; gap: 5px;">
                    <a href="?page=school-management-schedules&action=add" class="button">
                        <span class="dashicons dashicons-calendar-alt" style="vertical-align: middle;"></span>
                        <?php esc_html_e( 'Add Schedule', 'school-management-calendar' ); ?>
                    </a>
                    <a href="?page=school-management-events&action=add" class="button">
                        <span class="dashicons dashicons-megaphone" style="vertical-align: middle;"></span>
                        <?php esc_html_e( 'Add Event', 'school-management-calendar' ); ?>
                    </a>
                </div>
            </div>

            <!-- Render appropriate view -->
            <?php
            switch ( $view ) {
                case 'week':
                    self::render_week_view( $date );
                    break;
                case 'day':
                    self::render_day_view( $date );
                    break;
                case 'month':
                default:
                    self::render_month_view( $date );
                    break;
            }
            ?>

            <!-- Legend -->
            <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
                <strong><?php esc_html_e( 'Legend:', 'school-management-calendar' ); ?></strong>
                <span style="margin-left: 15px;">
                    <span style="display: inline-block; width: 12px; height: 12px; background: #0073aa; border-radius: 2px; vertical-align: middle;"></span>
                    <?php esc_html_e( 'Schedules (Recurring)', 'school-management-calendar' ); ?>
                </span>
                <span style="margin-left: 15px;">
                    <span style="display: inline-block; width: 12px; height: 12px; background: #46b450; border-radius: 2px; vertical-align: middle;"></span>
                    <?php esc_html_e( 'Events', 'school-management-calendar' ); ?>
                </span>
            </div>
        </div>
        <?php
    }
    /**
     * Render month view
     */
    private static function render_month_view( $date ) {
        $current_date = new DateTime( $date );
        $year = $current_date->format('Y');
        $month = $current_date->format('m');
        
        // Get first and last day of month
        $first_day = new DateTime("$year-$month-01");
        $last_day = new DateTime( $first_day->format('Y-m-t') );
        
        // Get start of week setting (1=Monday, 7=Sunday)
        $start_of_week = smc_get_start_of_week();
        
        // Find the calendar start date (may be in previous month)
        $calendar_start = clone $first_day;
        while ( $calendar_start->format('N') != $start_of_week ) {
            $calendar_start->modify('-1 day');
        }
        
        // Find the calendar end date (may be in next month)
        $calendar_end = clone $last_day;
        while ( $calendar_end->format('N') != ( $start_of_week == 1 ? 7 : $start_of_week - 1 ) ) {
            $calendar_end->modify('+1 day');
        }
        
        // Get all calendar items for this range
        $items = self::get_calendar_items( 
            $calendar_start->format('Y-m-d'), 
            $calendar_end->format('Y-m-d') 
        );
        
        // Group items by date
        $items_by_date = [];
        foreach ( $items as $item ) {
            $items_by_date[ $item['date'] ][] = $item;
        }
        
        // Navigation
        $prev_month = clone $first_day;
        $prev_month->modify('-1 month');
        $next_month = clone $first_day;
        $next_month->modify('+1 month');
        
        $days_of_week = smc_get_days_of_week();
        
        ?>
        <div class="smc-calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="?page=school-management-calendar&view=month&date=<?php echo esc_attr( $prev_month->format('Y-m-d') ); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Previous', 'school-management-calendar' ); ?>
            </a>
            
            <h2 style="margin: 0;"><?php echo esc_html( $first_day->format('F Y') ); ?></h2>
            
            <a href="?page=school-management-calendar&view=month&date=<?php echo esc_attr( $next_month->format('Y-m-d') ); ?>" class="button">
                <?php esc_html_e( 'Next', 'school-management-calendar' ); ?>
                <span class="dashicons dashicons-arrow-right-alt2" style="vertical-align: middle;"></span>
            </a>
        </div>

        <table class="smc-calendar-month" style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
            <thead>
                <tr>
                    <?php foreach ( $days_of_week as $day_num => $day_name ) : ?>
                        <th style="padding: 10px; background: #f0f0f1; border: 1px solid #ddd; text-align: center; font-weight: bold;">
                            <?php echo esc_html( $day_name ); ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php
                $current = clone $calendar_start;
                $week_count = 0;
                
                while ( $current <= $calendar_end ) {
                    if ( $current->format('N') == $start_of_week ) {
                        if ( $week_count > 0 ) {
                            echo '</tr>';
                        }
                        echo '<tr>';
                        $week_count++;
                    }
                    
                    $date_str = $current->format('Y-m-d');
                    $is_current_month = $current->format('m') == $month;
                    $is_today = $current->format('Y-m-d') === date('Y-m-d');
                    $day_items = $items_by_date[ $date_str ] ?? [];
                    
                    $style = 'padding: 5px; border: 1px solid #ddd; vertical-align: top; height: 100px; min-width: 120px;';
                    if ( ! $is_current_month ) {
                        $style .= ' background: #fafafa; color: #999;';
                    } elseif ( $is_today ) {
                        $style .= ' background: #fff8e5;';
                    }
                    ?>
                    <td style="<?php echo esc_attr( $style ); ?>">
                        <div style="font-weight: bold; margin-bottom: 5px; <?php echo $is_today ? 'color: #d63638;' : ''; ?>">
                            <?php echo esc_html( $current->format('j') ); ?>
                        </div>
                        
                        <?php if ( ! empty( $day_items ) ) : ?>
                            <div style="font-size: 11px;">
                                <?php 
                                $display_count = 0;
                                $max_display = 3;
                                foreach ( $day_items as $item ) : 
                                    if ( $display_count >= $max_display ) {
                                        $remaining = count( $day_items ) - $max_display;
                                        echo '<div style="margin-top: 2px; color: #666; font-style: italic;">+' . $remaining . ' ' . esc_html__( 'more', 'school-management-calendar' ) . '</div>';
                                        break;
                                    }
                                    
                                    $time_display = $item['is_all_day'] ? __( 'All Day', 'school-management-calendar' ) : date( 'H:i', strtotime( $item['start_time'] ) );
                                    ?>
                                    <div style="margin-bottom: 2px; padding: 2px 4px; background: <?php echo esc_attr( $item['color'] ); ?>; color: white; border-radius: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo esc_attr( $item['title'] . ' - ' . $time_display ); ?>">
                                        <?php echo esc_html( $time_display . ' ' . $item['title'] ); ?>
                                    </div>
                                    <?php 
                                    $display_count++;
                                endforeach; 
                                ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <?php
                    $current->modify('+1 day');
                }
                echo '</tr>';
                ?>
            </tbody>
        </table>
        <?php
    }
    /**
     * Render week view
     */
    private static function render_week_view( $date ) {
        $current_date = new DateTime( $date );
        
        // Get start of week setting
        $start_of_week = smc_get_start_of_week();
        
        // Find the week start
        $week_start = clone $current_date;
        while ( $week_start->format('N') != $start_of_week ) {
            $week_start->modify('-1 day');
        }
        
        // Find the week end
        $week_end = clone $week_start;
        $week_end->modify('+6 days');
        
        // Get items for this week
        $items = self::get_calendar_items( 
            $week_start->format('Y-m-d'), 
            $week_end->format('Y-m-d') 
        );
        
        // Group items by date
        $items_by_date = [];
        foreach ( $items as $item ) {
            $items_by_date[ $item['date'] ][] = $item;
        }
        
        // Navigation
        $prev_week = clone $week_start;
        $prev_week->modify('-7 days');
        $next_week = clone $week_start;
        $next_week->modify('+7 days');
        
        $days_of_week = smc_get_days_of_week();
        
        ?>
        <div class="smc-calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="?page=school-management-calendar&view=week&date=<?php echo esc_attr( $prev_week->format('Y-m-d') ); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Previous Week', 'school-management-calendar' ); ?>
            </a>
            
            <h2 style="margin: 0;">
                <?php 
                echo esc_html( sprintf( 
                    __( '%s - %s', 'school-management-calendar' ),
                    $week_start->format('M j, Y'),
                    $week_end->format('M j, Y')
                ) ); 
                ?>
            </h2>
            
            <a href="?page=school-management-calendar&view=week&date=<?php echo esc_attr( $next_week->format('Y-m-d') ); ?>" class="button">
                <?php esc_html_e( 'Next Week', 'school-management-calendar' ); ?>
                <span class="dashicons dashicons-arrow-right-alt2" style="vertical-align: middle;"></span>
            </a>
        </div>

        <table class="smc-calendar-week" style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">
            <thead>
                <tr>
                    <th style="padding: 10px; background: #f0f0f1; border: 1px solid #ddd; width: 100px;"><?php esc_html_e( 'Time', 'school-management-calendar' ); ?></th>
                    <?php
                    $current = clone $week_start;
                    for ( $i = 0; $i < 7; $i++ ) {
                        $is_today = $current->format('Y-m-d') === date('Y-m-d');
                        $style = $is_today ? 'background: #fff8e5; font-weight: bold;' : 'background: #f0f0f1;';
                        ?>
                        <th style="padding: 10px; border: 1px solid #ddd; text-align: center; <?php echo esc_attr( $style ); ?>">
                            <?php echo esc_html( $current->format('D j') ); ?>
                        </th>
                        <?php
                        $current->modify('+1 day');
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Time slots from 8 AM to 6 PM
                for ( $hour = 8; $hour < 18; $hour++ ) {
                    echo '<tr>';
                    echo '<td style="padding: 10px; border: 1px solid #ddd; background: #f9f9f9; font-weight: bold; text-align: center;">';
                    echo sprintf( '%02d:00', $hour );
                    echo '</td>';
                    
                    $current = clone $week_start;
                    for ( $i = 0; $i < 7; $i++ ) {
                        $date_str = $current->format('Y-m-d');
                        $day_items = $items_by_date[ $date_str ] ?? [];
                        
                        // Filter items for this hour
                        $hour_items = array_filter( $day_items, function( $item ) use ( $hour ) {
                            if ( $item['is_all_day'] ) return false;
                            $start_hour = intval( date( 'H', strtotime( $item['start_time'] ) ) );
                            $end_hour = intval( date( 'H', strtotime( $item['end_time'] ) ) );
                            return $start_hour <= $hour && $end_hour > $hour;
                        });
                        
                        $is_today = $current->format('Y-m-d') === date('Y-m-d');
                        $style = 'padding: 5px; border: 1px solid #ddd; vertical-align: top; min-height: 50px;';
                        if ( $is_today ) {
                            $style .= ' background: #fffef5;';
                        }
                        
                        echo '<td style="' . esc_attr( $style ) . '">';
                        
                        foreach ( $hour_items as $item ) {
                            $time_display = date( 'H:i', strtotime( $item['start_time'] ) );
                            ?>
                            <div style="margin-bottom: 3px; padding: 4px; background: <?php echo esc_attr( $item['color'] ); ?>; color: white; border-radius: 3px; font-size: 11px;">
                                <strong><?php echo esc_html( $time_display ); ?></strong><br>
                                <?php echo esc_html( $item['title'] ); ?>
                                <?php if ( $item['classroom'] ) : ?>
                                    <br><small><?php echo esc_html( $item['classroom'] ); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                        
                        echo '</td>';
                        $current->modify('+1 day');
                    }
                    
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render day view
     */
    private static function render_day_view( $date ) {
        $current_date = new DateTime( $date );
        
        // Get items for this day
        $items = self::get_calendar_items( $date, $date );
        
        // Separate all-day and timed events
        $all_day_items = array_filter( $items, function( $item ) {
            return $item['is_all_day'];
        });
        
        $timed_items = array_filter( $items, function( $item ) {
            return ! $item['is_all_day'];
        });
        
        // Navigation
        $prev_day = clone $current_date;
        $prev_day->modify('-1 day');
        $next_day = clone $current_date;
        $next_day->modify('+1 day');
        
        $is_today = $date === date('Y-m-d');
        
        ?>
        <div class="smc-calendar-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <a href="?page=school-management-calendar&view=day&date=<?php echo esc_attr( $prev_day->format('Y-m-d') ); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Previous Day', 'school-management-calendar' ); ?>
            </a>
            
            <h2 style="margin: 0; <?php echo $is_today ? 'color: #d63638;' : ''; ?>">
                <?php echo esc_html( $current_date->format('l, F j, Y') ); ?>
                <?php if ( $is_today ) : ?>
                    <span style="font-size: 14px; color: #d63638;">(<?php esc_html_e( 'Today', 'school-management-calendar' ); ?>)</span>
                <?php endif; ?>
            </h2>
            
            <a href="?page=school-management-calendar&view=day&date=<?php echo esc_attr( $next_day->format('Y-m-d') ); ?>" class="button">
                <?php esc_html_e( 'Next Day', 'school-management-calendar' ); ?>
                <span class="dashicons dashicons-arrow-right-alt2" style="vertical-align: middle;"></span>
            </a>
        </div>

        <!-- All-day events -->
        <?php if ( ! empty( $all_day_items ) ) : ?>
            <div style="margin-bottom: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #666;">
                <h3 style="margin: 0 0 10px 0;"><?php esc_html_e( 'All Day', 'school-management-calendar' ); ?></h3>
                <?php foreach ( $all_day_items as $item ) : ?>
                    <div style="margin-bottom: 5px; padding: 8px; background: <?php echo esc_attr( $item['color'] ); ?>; color: white; border-radius: 3px;">
                        <strong><?php echo esc_html( $item['title'] ); ?></strong>
                        <?php if ( $item['classroom'] ) : ?>
                            - <?php echo esc_html( $item['classroom'] ); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Timed events -->
        <?php if ( ! empty( $timed_items ) ) : ?>
            <div style="background: white; border: 1px solid #ddd;">
                <?php foreach ( $timed_items as $item ) : ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; gap: 15px;">
                        <div style="min-width: 80px; font-weight: bold; color: #666;">
                            <?php echo esc_html( date( 'H:i', strtotime( $item['start_time'] ) ) ); ?>
                            -
                            <?php echo esc_html( date( 'H:i', strtotime( $item['end_time'] ) ) ); ?>
                        </div>
                        <div style="flex: 1; border-left: 4px solid <?php echo esc_attr( $item['color'] ); ?>; padding-left: 15px;">
                            <h4 style="margin: 0 0 5px 0;"><?php echo esc_html( $item['title'] ); ?></h4>
                            <?php if ( $item['classroom'] || $item['teacher'] ) : ?>
                                <p style="margin: 0; color: #666; font-size: 13px;">
                                    <?php if ( $item['classroom'] ) : ?>
                                        <span class="dashicons dashicons-location" style="font-size: 13px; vertical-align: middle;"></span>
                                        <?php echo esc_html( $item['classroom'] ); ?>
                                    <?php endif; ?>
                                    <?php if ( $item['teacher'] ) : ?>
                                        <span style="margin-left: 10px;">
                                            <span class="dashicons dashicons-businessperson" style="font-size: 13px; vertical-align: middle;"></span>
                                            <?php echo esc_html( $item['teacher'] ); ?>
                                        </span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( empty( $all_day_items ) && empty( $timed_items ) ) : ?>
            <div class="sm-empty-state" style="text-align: center; padding: 60px 20px; background: #fafafa; border: 1px dashed #ddd; border-radius: 4px;">
                <span class="dashicons dashicons-calendar" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 16px;"></span>
                <h3><?php esc_html_e( 'No Events or Schedules', 'school-management-calendar' ); ?></h3>
                <p><?php esc_html_e( 'This day has no scheduled classes or events.', 'school-management-calendar' ); ?></p>
            </div>
        <?php endif; ?>
        <?php
    }
}