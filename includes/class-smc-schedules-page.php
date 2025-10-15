<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMC_Schedules_Page {

    /**
     * Validate schedule data
     */
    private static function validate_schedule_data( $post_data ) {
        global $wpdb;
        $errors = [];
        
        // Sanitize data
        $course_id = intval( $post_data['course_id'] ?? 0 );
        $classroom_id = ! empty( $post_data['classroom_id'] ) ? intval( $post_data['classroom_id'] ) : null;
        $teacher_id = ! empty( $post_data['teacher_id'] ) ? intval( $post_data['teacher_id'] ) : null;
        $day_of_week = intval( $post_data['day_of_week'] ?? 0 );
        $start_time = sanitize_text_field( trim( $post_data['start_time'] ?? '' ) );
        $end_time = sanitize_text_field( trim( $post_data['end_time'] ?? '' ) );
        $effective_from = sanitize_text_field( trim( $post_data['effective_from'] ?? '' ) );
        $effective_until = ! empty( $post_data['effective_until'] ) ? sanitize_text_field( trim( $post_data['effective_until'] ) ) : null;
        $recurrence_type = sanitize_text_field( $post_data['recurrence_type'] ?? 'weekly' );
        $recurrence_interval = intval( $post_data['recurrence_interval'] ?? 1 );
        $notes = sanitize_textarea_field( trim( $post_data['notes'] ?? '' ) );
        $is_active = isset( $post_data['is_active'] ) ? 1 : 0;

        // Validate required fields
        if ( $course_id <= 0 ) {
            $errors[] = __( 'Course is required.', 'school-management-calendar' );
        }

        if ( $day_of_week < 1 || $day_of_week > 7 ) {
            $errors[] = __( 'Valid day of week is required (1-7).', 'school-management-calendar' );
        }

        if ( empty( $start_time ) ) {
            $errors[] = __( 'Start time is required.', 'school-management-calendar' );
        }

        if ( empty( $end_time ) ) {
            $errors[] = __( 'End time is required.', 'school-management-calendar' );
        }

        // Validate time format and logic
        if ( ! empty( $start_time ) && ! empty( $end_time ) ) {
            if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time ) ) {
                $errors[] = __( 'Start time must be in HH:MM format (e.g., 09:00).', 'school-management-calendar' );
            }
            if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time ) ) {
                $errors[] = __( 'End time must be in HH:MM format (e.g., 10:30).', 'school-management-calendar' );
            }
            
            if ( strtotime( $start_time ) >= strtotime( $end_time ) ) {
                $errors[] = __( 'End time must be after start time.', 'school-management-calendar' );
            }
        }

        if ( empty( $effective_from ) ) {
            $errors[] = __( 'Effective from date is required.', 'school-management-calendar' );
        }

        // Validate date format
        if ( ! empty( $effective_from ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $effective_from ) ) {
            $errors[] = __( 'Effective from date must be in YYYY-MM-DD format.', 'school-management-calendar' );
        }

        if ( ! empty( $effective_until ) ) {
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $effective_until ) ) {
                $errors[] = __( 'Effective until date must be in YYYY-MM-DD format.', 'school-management-calendar' );
            } elseif ( $effective_until < $effective_from ) {
                $errors[] = __( 'Effective until date must be after effective from date.', 'school-management-calendar' );
            }
        }

        if ( empty( $errors ) ) {
            return [
                'success' => true,
                'data' => [
                    'course_id' => $course_id,
                    'classroom_id' => $classroom_id,
                    'teacher_id' => $teacher_id,
                    'day_of_week' => $day_of_week,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'effective_from' => $effective_from,
                    'effective_until' => $effective_until,
                    'recurrence_type' => $recurrence_type,
                    'recurrence_interval' => $recurrence_interval,
                    'notes' => $notes,
                    'is_active' => $is_active,
                ]
            ];
        }

        return [ 'success' => false, 'errors' => $errors ];
    }
    /**
     * Render the Schedules page
     */
    public static function render_schedules_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'smc_schedules';

        // Handle delete action
        if ( isset( $_GET['delete'] ) && check_admin_referer( 'smc_delete_schedule_' . intval( $_GET['delete'] ) ) ) {
            $deleted = $wpdb->delete( $table, [ 'id' => intval( $_GET['delete'] ) ] );
            if ( $deleted ) {
                echo '<div class="updated notice"><p>' . esc_html__( 'Schedule deleted successfully.', 'school-management-calendar' ) . '</p></div>';
            } else {
                echo '<div class="error notice"><p>' . esc_html__( 'Error deleting schedule.', 'school-management-calendar' ) . '</p></div>';
            }
        }

        // Handle form submission
        if ( ( isset( $_POST['smc_save_schedule'] ) || isset( $_POST['smc_save_and_new'] ) ) && check_admin_referer( 'smc_save_schedule_action', 'smc_save_schedule_nonce' ) ) {
            $validation_result = self::validate_schedule_data( $_POST );
            
            if ( $validation_result['success'] ) {
                $data = $validation_result['data'];
                $save_and_new = isset( $_POST['smc_save_and_new'] );
                
                if ( ! empty( $_POST['schedule_id'] ) ) {
                    // Edit mode
                    $updated = $wpdb->update( $table, $data, [ 'id' => intval( $_POST['schedule_id'] ) ] );
                    if ( $updated !== false ) {
                        echo '<div class="updated notice"><p>' . esc_html__( 'Schedule updated successfully.', 'school-management-calendar' ) . '</p></div>';
                        echo '<script>setTimeout(function(){ window.location.href = "?page=school-management-schedules"; }, 1500);</script>';
                    }
                } else {
                    // Add mode
                    $inserted = $wpdb->insert( $table, $data );
                    if ( $inserted ) {
                        echo '<div class="updated notice"><p>' . esc_html__( 'Schedule added successfully.', 'school-management-calendar' ) . '</p></div>';
                        
                        if ( $save_and_new ) {
                            echo '<script>setTimeout(function(){ window.location.href = "?page=school-management-schedules&action=add"; }, 1500);</script>';
                        } else {
                            echo '<script>setTimeout(function(){ window.location.href = "?page=school-management-schedules"; }, 1500);</script>';
                        }
                    }
                }
            } else {
                echo '<div class="error notice"><p><strong>' . esc_html__( 'Please correct the following errors:', 'school-management-calendar' ) . '</strong></p>';
                echo '<ul style="margin-left: 20px;">';
                foreach ( $validation_result['errors'] as $error ) {
                    echo '<li>' . esc_html( $error ) . '</li>';
                }
                echo '</ul></div>';
            }
        }

        // Determine view
        $action = $_GET['action'] ?? 'list';
        $schedule = null;

        if ( $action === 'edit' && isset( $_GET['schedule_id'] ) ) {
            $schedule = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", intval( $_GET['schedule_id'] ) ) );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Course Schedules', 'school-management-calendar' ); ?></h1>

            <?php
            switch ( $action ) {
                case 'add':
                    self::render_schedule_form( null );
                    break;
                case 'edit':
                    self::render_schedule_form( $schedule );
                    break;
                default:
                    self::render_schedules_list();
                    break;
            }
            ?>
        </div>
        <?php
    }
    /**
     * Render schedules list
     */
    private static function render_schedules_list() {
        global $wpdb;
        $schedules_table = $wpdb->prefix . 'smc_schedules';
        $courses_table = $wpdb->prefix . 'sm_courses';
        $classrooms_table = $wpdb->prefix . 'sm_classrooms';
        $teachers_table = $wpdb->prefix . 'sm_teachers';

        // Pagination
        $per_page = 20;
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;

        $total_schedules = $wpdb->get_var( "SELECT COUNT(*) FROM $schedules_table" );
        $total_pages = ceil( $total_schedules / $per_page );

        // Get schedules with related data
        $schedules = $wpdb->get_results( $wpdb->prepare( 
            "SELECT s.*, 
                    c.name as course_name,
                    cr.name as classroom_name,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name
             FROM $schedules_table s 
             LEFT JOIN $courses_table c ON s.course_id = c.id 
             LEFT JOIN $classrooms_table cr ON s.classroom_id = cr.id
             LEFT JOIN $teachers_table t ON s.teacher_id = t.id
             ORDER BY s.day_of_week ASC, s.start_time ASC 
             LIMIT %d OFFSET %d", 
            $per_page, 
            $offset 
        ) );

        // Days of week mapping (respects first day of week setting)
        $days = smc_get_days_of_week();
    
        ?>
        <div class="sm-header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="margin: 0;"><?php esc_html_e( 'Schedules List', 'school-management-calendar' ); ?></h2>
                <p class="description"><?php printf( esc_html__( 'Total: %d schedules', 'school-management-calendar' ), $total_schedules ); ?></p>
            </div>
            <div>
                <a href="?page=school-management-schedules&action=add" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
                    <?php esc_html_e( 'Add New Schedule', 'school-management-calendar' ); ?>
                </a>
            </div>
        </div>

        <?php if ( $schedules ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Course', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Day', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Time', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Classroom', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Teacher', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Effective Period', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'school-management-calendar' ); ?></th>
                        <th style="width: 150px;"><?php esc_html_e( 'Actions', 'school-management-calendar' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $schedules as $schedule ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $schedule->course_name ); ?></strong></td>
                            <td><?php echo esc_html( $days[ $schedule->day_of_week ] ?? '' ); ?></td>
                            <td><?php echo esc_html( date( 'H:i', strtotime( $schedule->start_time ) ) . ' - ' . date( 'H:i', strtotime( $schedule->end_time ) ) ); ?></td>
                            <td><?php echo esc_html( $schedule->classroom_name ?: '—' ); ?></td>
                            <td><?php echo esc_html( $schedule->teacher_name ?: '—' ); ?></td>
                            <td>
                                <?php 
                                echo esc_html( date( 'M d, Y', strtotime( $schedule->effective_from ) ) );
                                if ( $schedule->effective_until ) {
                                    echo ' → ' . esc_html( date( 'M d, Y', strtotime( $schedule->effective_until ) ) );
                                } else {
                                    echo ' → ' . esc_html__( 'Ongoing', 'school-management-calendar' );
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ( $schedule->is_active ) : ?>
                                    <span style="color: #46b450;">● <?php esc_html_e( 'Active', 'school-management-calendar' ); ?></span>
                                <?php else : ?>
                                    <span style="color: #dc3232;">● <?php esc_html_e( 'Inactive', 'school-management-calendar' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?page=school-management-schedules&action=edit&schedule_id=<?php echo intval( $schedule->id ); ?>" class="button button-small">
                                    <span class="dashicons dashicons-edit" style="vertical-align: middle;"></span>
                                </a>
                                <?php
                                $delete_url = wp_nonce_url( 
                                    '?page=school-management-schedules&delete=' . intval( $schedule->id ), 
                                    'smc_delete_schedule_' . intval( $schedule->id ) 
                                );
                                ?>
                                <a href="<?php echo esc_url( $delete_url ); ?>" 
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this schedule?', 'school-management-calendar' ) ); ?>')">
                                    <span class="dashicons dashicons-trash" style="vertical-align: middle; color: #d63638;"></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Pagination
            if ( $total_pages > 1 ) {
                $pagination_args = [
                    'base' => add_query_arg( 'paged', '%#%' ),
                    'format' => '',
                    'prev_text' => __( '« Previous', 'school-management-calendar' ),
                    'next_text' => __( 'Next »', 'school-management-calendar' ),
                    'total' => $total_pages,
                    'current' => $current_page,
                ];
                echo '<div class="tablenav bottom"><div class="tablenav-pages">';
                echo paginate_links( $pagination_args );
                echo '</div></div>';
            }
            ?>

        <?php else : ?>
            <div class="sm-empty-state" style="text-align: center; padding: 60px 20px; background: #fafafa; border: 1px dashed #ddd; border-radius: 4px;">
                <span class="dashicons dashicons-calendar-alt" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 16px;"></span>
                <h3><?php esc_html_e( 'No Schedules Yet', 'school-management-calendar' ); ?></h3>
                <p><?php esc_html_e( 'Create your first course schedule to get started.', 'school-management-calendar' ); ?></p>
                <a href="?page=school-management-schedules&action=add" class="button button-primary">
                    <?php esc_html_e( 'Add First Schedule', 'school-management-calendar' ); ?>
                </a>
            </div>
        <?php endif;
    }
    /**
     * Render schedule form
     */
    private static function render_schedule_form( $schedule = null ) {
        global $wpdb;
        $is_edit = ! empty( $schedule );
        
        // Get courses, classrooms, and teachers
        $courses = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sm_courses WHERE is_active = 1 ORDER BY name ASC" );
        $classrooms = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sm_classrooms WHERE is_active = 1 ORDER BY name ASC" );
        $teachers = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sm_teachers WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC" );
        
        // Days of week (respects first day of week setting)
        $days = smc_get_days_of_week();

        // Form data
        $form_data = [];
        if ( isset( $_POST['smc_save_schedule'] ) || isset( $_POST['smc_save_and_new'] ) ) {
            $form_data = [
                'course_id' => intval( $_POST['course_id'] ?? 0 ),
                'classroom_id' => intval( $_POST['classroom_id'] ?? 0 ),
                'teacher_id' => intval( $_POST['teacher_id'] ?? 0 ),
                'day_of_week' => intval( $_POST['day_of_week'] ?? 0 ),
                'start_time' => sanitize_text_field( $_POST['start_time'] ?? '' ),
                'end_time' => sanitize_text_field( $_POST['end_time'] ?? '' ),
                'effective_from' => sanitize_text_field( $_POST['effective_from'] ?? '' ),
                'effective_until' => sanitize_text_field( $_POST['effective_until'] ?? '' ),
                'recurrence_type' => sanitize_text_field( $_POST['recurrence_type'] ?? 'weekly' ),
                'recurrence_interval' => intval( $_POST['recurrence_interval'] ?? 1 ),
                'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' ),
                'is_active' => isset( $_POST['is_active'] ),
            ];
        } elseif ( $schedule ) {
            $form_data = [
                'course_id' => $schedule->course_id,
                'classroom_id' => $schedule->classroom_id,
                'teacher_id' => $schedule->teacher_id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'effective_from' => $schedule->effective_from,
                'effective_until' => $schedule->effective_until,
                'recurrence_type' => $schedule->recurrence_type,
                'recurrence_interval' => $schedule->recurrence_interval,
                'notes' => $schedule->notes,
                'is_active' => $schedule->is_active,
            ];
        } else {
            // Default values for new schedule
            $form_data = [
                'course_id' => 0,
                'classroom_id' => 0,
                'teacher_id' => 0,
                'day_of_week' => 1,
                'start_time' => '09:00',
                'end_time' => '10:30',
                'effective_from' => date( 'Y-m-d' ),
                'effective_until' => '',
                'recurrence_type' => 'weekly',
                'recurrence_interval' => 1,
                'notes' => '',
                'is_active' => true,
            ];
        }
        
        ?>
        <div class="sm-form-header" style="margin-bottom: 20px;">
            <a href="?page=school-management-schedules" class="button">
                <span class="dashicons dashicons-arrow-left-alt2" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Back to Schedules', 'school-management-calendar' ); ?>
            </a>
            <h2 style="display: inline-block; margin-left: 10px;">
                <?php echo $is_edit ? esc_html__( 'Edit Schedule', 'school-management-calendar' ) : esc_html__( 'Add New Schedule', 'school-management-calendar' ); ?>
            </h2>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'smc_save_schedule_action', 'smc_save_schedule_nonce' ); ?>
            <input type="hidden" name="schedule_id" value="<?php echo esc_attr( $schedule->id ?? '' ); ?>" />

            <h3><?php esc_html_e( 'Basic Information', 'school-management-calendar' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="schedule_course"><?php esc_html_e( 'Course', 'school-management-calendar' ); ?> <span style="color: #d63638;">*</span></label>
                    </th>
                    <td>
                        <select id="schedule_course" name="course_id" required style="min-width: 300px;">
                            <option value=""><?php esc_html_e( 'Select Course', 'school-management-calendar' ); ?></option>
                            <?php foreach ( $courses as $course ) : ?>
                                <option value="<?php echo intval( $course->id ); ?>" <?php selected( $form_data['course_id'], $course->id ); ?>>
                                    <?php echo esc_html( $course->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <a href="?page=school-management-courses" target="_blank"><?php esc_html_e( 'Manage courses', 'school-management-calendar' ); ?></a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_classroom"><?php esc_html_e( 'Classroom', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <select id="schedule_classroom" name="classroom_id" style="min-width: 300px;">
                            <option value=""><?php esc_html_e( 'No Classroom Assigned', 'school-management-calendar' ); ?></option>
                            <?php foreach ( $classrooms as $classroom ) : ?>
                                <option value="<?php echo intval( $classroom->id ); ?>" <?php selected( $form_data['classroom_id'], $classroom->id ); ?>>
                                    <?php echo esc_html( $classroom->name ); ?>
                                    <?php if ( $classroom->location ) : ?>
                                        - <?php echo esc_html( $classroom->location ); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Optional: Assign to a specific classroom.', 'school-management-calendar' ); ?>
                            <a href="?page=school-management-classrooms" target="_blank"><?php esc_html_e( 'Manage classrooms', 'school-management-calendar' ); ?></a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_teacher"><?php esc_html_e( 'Teacher', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <select id="schedule_teacher" name="teacher_id" style="min-width: 300px;">
                            <option value=""><?php esc_html_e( 'Use Course Default Teacher', 'school-management-calendar' ); ?></option>
                            <?php foreach ( $teachers as $teacher ) : ?>
                                <option value="<?php echo intval( $teacher->id ); ?>" <?php selected( $form_data['teacher_id'], $teacher->id ); ?>>
                                    <?php echo esc_html( $teacher->first_name . ' ' . $teacher->last_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Optional: Override course teacher or specify substitute teacher.', 'school-management-calendar' ); ?>
                            <a href="?page=school-management-teachers" target="_blank"><?php esc_html_e( 'Manage teachers', 'school-management-calendar' ); ?></a>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Schedule Details', 'school-management-calendar' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="schedule_day"><?php esc_html_e( 'Day of Week', 'school-management-calendar' ); ?> <span style="color: #d63638;">*</span></label>
                    </th>
                    <td>
                        <select id="schedule_day" name="day_of_week" required>
                            <?php foreach ( $days as $day_num => $day_name ) : ?>
                                <option value="<?php echo intval( $day_num ); ?>" <?php selected( $form_data['day_of_week'], $day_num ); ?>>
                                    <?php echo esc_html( $day_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_start_time"><?php esc_html_e( 'Start Time', 'school-management-calendar' ); ?> <span style="color: #d63638;">*</span></label>
                    </th>
                    <td>
                        <input type="time" id="schedule_start_time" name="start_time" value="<?php echo esc_attr( $form_data['start_time'] ); ?>" required />
                        <p class="description"><?php esc_html_e( 'Class start time (e.g., 09:00).', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_end_time"><?php esc_html_e( 'End Time', 'school-management-calendar' ); ?> <span style="color: #d63638;">*</span></label>
                    </th>
                    <td>
                        <input type="time" id="schedule_end_time" name="end_time" value="<?php echo esc_attr( $form_data['end_time'] ); ?>" required />
                        <p class="description"><?php esc_html_e( 'Class end time (e.g., 10:30).', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_effective_from"><?php esc_html_e( 'Effective From', 'school-management-calendar' ); ?> <span style="color: #d63638;">*</span></label>
                    </th>
                    <td>
                        <input type="date" id="schedule_effective_from" name="effective_from" value="<?php echo esc_attr( $form_data['effective_from'] ); ?>" required />
                        <p class="description"><?php esc_html_e( 'Start date for this schedule (e.g., semester start).', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_effective_until"><?php esc_html_e( 'Effective Until', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <input type="date" id="schedule_effective_until" name="effective_until" value="<?php echo esc_attr( $form_data['effective_until'] ); ?>" />
                        <p class="description"><?php esc_html_e( 'Optional: End date for this schedule. Leave empty for ongoing schedule.', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_recurrence_type"><?php esc_html_e( 'Recurrence Type', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <select id="schedule_recurrence_type" name="recurrence_type">
                            <option value="weekly" <?php selected( $form_data['recurrence_type'], 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'school-management-calendar' ); ?></option>
                            <option value="biweekly" <?php selected( $form_data['recurrence_type'], 'biweekly' ); ?>><?php esc_html_e( 'Bi-weekly', 'school-management-calendar' ); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e( 'How often this class repeats.', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_notes"><?php esc_html_e( 'Notes', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <textarea id="schedule_notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $form_data['notes'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Optional notes about this schedule.', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="schedule_is_active"><?php esc_html_e( 'Status', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="schedule_is_active" name="is_active" value="1" <?php checked( $form_data['is_active'] ); ?> />
                            <?php esc_html_e( 'Active (this schedule is currently in effect)', 'school-management-calendar' ); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <?php if ( $is_edit ) : ?>
                    <?php submit_button( 
                        __( 'Update Schedule', 'school-management-calendar' ), 
                        'primary', 
                        'smc_save_schedule', 
                        false 
                    ); ?>
                <?php else : ?>
                    <?php submit_button( 
                        __( 'Save & Exit', 'school-management-calendar' ), 
                        'primary', 
                        'smc_save_schedule', 
                        false 
                    ); ?>
                    <?php submit_button( 
                        __( 'Save & Add New', 'school-management-calendar' ), 
                        'secondary', 
                        'smc_save_and_new', 
                        false,
                        [ 'style' => 'margin-left: 5px;' ]
                    ); ?>
                <?php endif; ?>
                <a href="?page=school-management-schedules" class="button" style="margin-left: 10px;"><?php esc_html_e( 'Cancel', 'school-management-calendar' ); ?></a>
            </p>
            
            <p class="description">
                <span style="color: #d63638;">*</span> <?php esc_html_e( 'Required fields', 'school-management-calendar' ); ?>
            </p>
        </form>
        <?php
    }
}