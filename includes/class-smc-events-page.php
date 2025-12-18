<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SMC_Events_Page {

    /**
     * Validate event data
     */
    private static function validate_event_data( $post_data ) {
        global $wpdb;
        $errors = [];
        
        // Sanitize data
        $title = sanitize_text_field( trim( $post_data['title'] ?? '' ) );
        $description = sanitize_textarea_field( trim( $post_data['description'] ?? '' ) );
        $event_type = sanitize_text_field( $post_data['event_type'] ?? '' );
        $event_date = sanitize_text_field( trim( $post_data['event_date'] ?? '' ) );
        $is_all_day = isset( $post_data['is_all_day'] ) ? 1 : 0;
        $no_courses = isset( $post_data['no_courses'] ) ? 1 : 0;
        $start_time = $is_all_day ? null : sanitize_text_field( trim( $post_data['start_time'] ?? '' ) );
        $end_time = $is_all_day ? null : sanitize_text_field( trim( $post_data['end_time'] ?? '' ) );
        $course_id = ! empty( $post_data['course_id'] ) ? intval( $post_data['course_id'] ) : null;
        $classroom_id = ! empty( $post_data['classroom_id'] ) ? intval( $post_data['classroom_id'] ) : null;
        $teacher_id = ! empty( $post_data['teacher_id'] ) ? intval( $post_data['teacher_id'] ) : null;
        $color = sanitize_text_field( $post_data['color'] ?? '#3788d8' );
        $is_public = isset( $post_data['is_public'] ) ? 1 : 0;

        // Validate required fields
        if ( empty( $title ) ) {
            $errors[] = __( 'Event title is required.', 'school-management-calendar' );
        } elseif ( strlen( $title ) < 3 ) {
            $errors[] = __( 'Event title must be at least 3 characters long.', 'school-management-calendar' );
        }

        if ( empty( $event_type ) ) {
            $errors[] = __( 'Event type is required.', 'school-management-calendar' );
        }

        // Validate event type
        $valid_types = [ 'exam', 'holiday', 'meeting', 'special_event', 'school_closure' ];
        if ( ! empty( $event_type ) && ! in_array( $event_type, $valid_types ) ) {
            $errors[] = __( 'Invalid event type.', 'school-management-calendar' );
        }

        if ( empty( $event_date ) ) {
            $errors[] = __( 'Event date is required.', 'school-management-calendar' );
        }

        // Validate date format
        if ( ! empty( $event_date ) && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) ) {
            $errors[] = __( 'Event date must be in YYYY-MM-DD format.', 'school-management-calendar' );
        }

        // Validate time format and logic (if not all-day)
        if ( ! $is_all_day ) {
            if ( empty( $start_time ) ) {
                $errors[] = __( 'Start time is required for timed events.', 'school-management-calendar' );
            }
            if ( empty( $end_time ) ) {
                $errors[] = __( 'End time is required for timed events.', 'school-management-calendar' );
            }

            if ( ! empty( $start_time ) && ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $start_time ) ) {
                $errors[] = __( 'Start time must be in HH:MM format (e.g., 09:00).', 'school-management-calendar' );
            }
            if ( ! empty( $end_time ) && ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $end_time ) ) {
                $errors[] = __( 'End time must be in HH:MM format (e.g., 10:30).', 'school-management-calendar' );
            }
            
            if ( ! empty( $start_time ) && ! empty( $end_time ) && strtotime( $start_time ) >= strtotime( $end_time ) ) {
                $errors[] = __( 'End time must be after start time.', 'school-management-calendar' );
            }
        }

        // Validate color format
        if ( ! preg_match( '/^#[a-fA-F0-9]{6}$/', $color ) ) {
            $errors[] = __( 'Color must be a valid hex color code (e.g., #3788d8).', 'school-management-calendar' );
        }

        if ( empty( $errors ) ) {
            return [
                'success' => true,
                'data' => [
                    'title' => $title,
                    'description' => $description,
                    'event_type' => $event_type,
                    'event_date' => $event_date,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'is_all_day' => $is_all_day,
                    'no_courses' => $no_courses,
                    'course_id' => $course_id,
                    'classroom_id' => $classroom_id,
                    'teacher_id' => $teacher_id,
                    'color' => $color,
                    'is_public' => $is_public,
                    'created_by' => get_current_user_id(),
                ]
            ];
        }

        return [ 'success' => false, 'errors' => $errors ];
    }
    /**
     * Render the Events page
     */
    public static function render_events_page() {
        // Security check
        if ( ! current_user_can( 'manage_events' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'school-management-calendar' ) );
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'smc_events';

        // Handle delete action
        if ( isset( $_GET['delete'] ) && check_admin_referer( 'smc_delete_event_' . intval( $_GET['delete'] ) ) ) {
            $deleted = $wpdb->delete( $table, [ 'id' => intval( $_GET['delete'] ) ] );
            if ( $deleted ) {
                echo '<div class="updated notice"><p>' . esc_html__( 'Event deleted successfully.', 'school-management-calendar' ) . '</p></div>';
            } else {
                echo '<div class="error notice"><p>' . esc_html__( 'Error deleting event.', 'school-management-calendar' ) . '</p></div>';
            }
        }

        // Handle form submission
        if ( ( isset( $_POST['smc_save_event'] ) || isset( $_POST['smc_save_and_new'] ) ) && check_admin_referer( 'smc_save_event_action', 'smc_save_event_nonce' ) ) {
            $validation_result = self::validate_event_data( $_POST );
            
            if ( $validation_result['success'] ) {
                $data = $validation_result['data'];
                $save_and_new = isset( $_POST['smc_save_and_new'] );
                
                if ( ! empty( $_POST['event_id'] ) ) {
                    // Edit mode
                    $updated = $wpdb->update( $table, $data, [ 'id' => intval( $_POST['event_id'] ) ] );
                    if ( $updated !== false ) {
                        echo '<div class="updated notice"><p>' . esc_html__( 'Event updated successfully.', 'school-management-calendar' ) . '</p></div>';
                        echo '<script>setTimeout(function(){ window.location.href = "?page=school-management-events"; }, 1500);</script>';
                    }
                } else {
                    // Add mode
                    $inserted = $wpdb->insert( $table, $data );
                    if ( $inserted ) {
                        echo '<div class="updated notice"><p>' . esc_html__( 'Event added successfully.', 'school-management-calendar' ) . '</p></div>';
                        
                        if ( $save_and_new ) {
                            echo '<script>setTimeout(function(){ window.location.href = "?page=school-management-events&action=add"; }, 1500);</script>';
                        } else {
                            echo '<script>setTimeout(function(){ window.location.href = "?page=school-management-events"; }, 1500);</script>';
                        }
                    }
                }
            } else {
                echo '<div class="error notice"><p><strong>' . esc_html__( 'Please correct the following errors:', 'school-management-calendar' ) . '</strong></p>';
                echo '<ul class="ml-20">';
                foreach ( $validation_result['errors'] as $error ) {
                    echo '<li>' . esc_html( $error ) . '</li>';
                }
                echo '</ul></div>';
            }
        }

        // Determine view
        $action = $_GET['action'] ?? 'list';
        $event = null;

        if ( $action === 'edit' && isset( $_GET['event_id'] ) ) {
            $event = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", intval( $_GET['event_id'] ) ) );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'School Events', 'school-management-calendar' ); ?></h1>

            <?php
            switch ( $action ) {
                case 'add':
                    self::render_event_form( null );
                    break;
                case 'edit':
                    self::render_event_form( $event );
                    break;
                default:
                    self::render_events_list();
                    break;
            }
            ?>
        </div>
        <?php
    }
    /**
     * Render events list
     */
    private static function render_events_list() {
        global $wpdb;
        $events_table = $wpdb->prefix . 'smc_events';
        $courses_table = $wpdb->prefix . 'sm_courses';
        $classrooms_table = $wpdb->prefix . 'sm_classrooms';
        $teachers_table = $wpdb->prefix . 'sm_teachers';

        // Filtering
        $filter_type = $_GET['filter_type'] ?? '';
        $filter_date_from = $_GET['filter_date_from'] ?? '';
        $filter_date_to = $_GET['filter_date_to'] ?? '';

        // Build WHERE clause - SQL and params separately
        $where_sql_parts = [];
        $where_params = [];

        if ( ! empty( $filter_type ) ) {
            $where_sql_parts[] = "e.event_type = %s";
            $where_params[] = $filter_type;
        }

        if ( ! empty( $filter_date_from ) ) {
            $where_sql_parts[] = "e.event_date >= %s";
            $where_params[] = $filter_date_from;
        }

        if ( ! empty( $filter_date_to ) ) {
            $where_sql_parts[] = "e.event_date <= %s";
            $where_params[] = $filter_date_to;
        }

        $where_sql = ! empty( $where_sql_parts ) ? 'WHERE ' . implode( ' AND ', $where_sql_parts ) : '';

        // Pagination
        $per_page = 20;
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $offset = ( $current_page - 1 ) * $per_page;

        // Get total count
        $count_query = "SELECT COUNT(*) FROM $events_table e $where_sql";

        if ( ! empty( $where_params ) ) {
            $total_events = $wpdb->get_var( $wpdb->prepare( $count_query, $where_params ) );
        } else {
            $total_events = $wpdb->get_var( $count_query );
        }
        $total_pages = ceil( $total_events / $per_page );

        // Get events with related data
        $query = "SELECT e.*,
                         c.name as course_name,
                         cr.name as classroom_name,
                         CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                  FROM $events_table e
                  LEFT JOIN $courses_table c ON e.course_id = c.id
                  LEFT JOIN $classrooms_table cr ON e.classroom_id = cr.id
                  LEFT JOIN $teachers_table t ON e.teacher_id = t.id
                  $where_sql
                  ORDER BY e.event_date DESC, e.start_time DESC
                  LIMIT %d OFFSET %d";

        // Merge all parameters
        $all_params = array_merge( $where_params, array( $per_page, $offset ) );

        if ( ! empty( $all_params ) ) {
            $events = $wpdb->get_results( $wpdb->prepare( $query, $all_params ) );
        } else {
            $events = $wpdb->get_results( $wpdb->prepare( $query, $per_page, $offset ) );
        }

        // Event types
        $event_types = [
            'exam' => __( 'Exam', 'school-management-calendar' ),
            'holiday' => __( 'Holiday', 'school-management-calendar' ),
            'meeting' => __( 'Meeting', 'school-management-calendar' ),
            'special_event' => __( 'Special Event', 'school-management-calendar' ),
            'school_closure' => __( 'School Closure', 'school-management-calendar' ),
        ];

        ?>
        <div class="sm-header-actions d-flex justify-between align-items-center mb-20">
            <div>
                <h2 class="m-0"><?php esc_html_e( 'Events List', 'school-management-calendar' ); ?></h2>
                <p class="description"><?php printf( esc_html__( 'Total: %d events', 'school-management-calendar' ), $total_events ); ?></p>
            </div>
            <div>
                <a href="?page=school-management-events&action=add" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt align-middle"></span>
                    <?php esc_html_e( 'Add New Event', 'school-management-calendar' ); ?>
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="tablenav top" style="background: #f9f9f9; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd;">
            <form method="get" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="page" value="school-management-events" />
                
                <label>
                    <?php esc_html_e( 'Type:', 'school-management-calendar' ); ?>
                    <select name="filter_type">
                        <option value=""><?php esc_html_e( 'All Types', 'school-management-calendar' ); ?></option>
                        <?php foreach ( $event_types as $type => $label ) : ?>
                            <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filter_type, $type ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <?php esc_html_e( 'From:', 'school-management-calendar' ); ?>
                    <input type="date" name="filter_date_from" value="<?php echo esc_attr( $filter_date_from ); ?>" />
                </label>

                <label>
                    <?php esc_html_e( 'To:', 'school-management-calendar' ); ?>
                    <input type="date" name="filter_date_to" value="<?php echo esc_attr( $filter_date_to ); ?>" />
                </label>

                <button type="submit" class="button"><?php esc_html_e( 'Filter', 'school-management-calendar' ); ?></button>
                <a href="?page=school-management-events" class="button"><?php esc_html_e( 'Clear', 'school-management-calendar' ); ?></a>
            </form>
        </div>

        <?php if ( $events ) : ?>
            <table class="wp-list-table widefat fixed striped mobile-card-layout">
                <thead>
                    <tr>
                        <th></th>
                        <th><?php esc_html_e( 'Title', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Time', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Course', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Location', 'school-management-calendar' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'school-management-calendar' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $events as $event ) : ?>
                        <tr>
                            <td data-label="">
                                <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background-color: <?php echo esc_attr( $event->color ); ?>;"></span>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Title', 'school-management-calendar' ); ?>">
                                <span class="mobile-label"><?php esc_html_e( 'Title', 'school-management-calendar' ); ?>:</span>
                                <strong><?php echo esc_html( $event->title ); ?></strong>
                                <?php if ( ! $event->is_public ) : ?>
                                    <span class="dashicons dashicons-lock text-muted" title="<?php esc_attr_e( 'Private', 'school-management-calendar' ); ?>"></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Type', 'school-management-calendar' ); ?>">
                                <span class="mobile-label"><?php esc_html_e( 'Type', 'school-management-calendar' ); ?>:</span>
                                <?php echo esc_html( $event_types[ $event->event_type ] ?? $event->event_type ); ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Date', 'school-management-calendar' ); ?>">
                                <span class="mobile-label"><?php esc_html_e( 'Date', 'school-management-calendar' ); ?>:</span>
                                <?php echo esc_html( date( 'M d, Y', strtotime( $event->event_date ) ) ); ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Time', 'school-management-calendar' ); ?>">
                                <span class="mobile-label"><?php esc_html_e( 'Time', 'school-management-calendar' ); ?>:</span>
                                <?php if ( $event->is_all_day ) : ?>
                                    <em><?php esc_html_e( 'All Day', 'school-management-calendar' ); ?></em>
                                <?php else : ?>
                                    <?php echo esc_html( date( 'H:i', strtotime( $event->start_time ) ) . ' - ' . date( 'H:i', strtotime( $event->end_time ) ) ); ?>
                                <?php endif; ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Course', 'school-management-calendar' ); ?>">
                                <span class="mobile-label"><?php esc_html_e( 'Course', 'school-management-calendar' ); ?>:</span>
                                <?php echo esc_html( $event->course_name ?: '—' ); ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Location', 'school-management-calendar' ); ?>">
                                <span class="mobile-label"><?php esc_html_e( 'Location', 'school-management-calendar' ); ?>:</span>
                                <?php echo esc_html( $event->classroom_name ?: '—' ); ?>
                            </td>
                            <td data-label="<?php esc_attr_e( 'Actions', 'school-management-calendar' ); ?>" class="actions">
                                <a href="?page=school-management-events&action=edit&event_id=<?php echo intval( $event->id ); ?>" class="button button-small">
                                    <span class="dashicons dashicons-edit align-middle"></span>
                                    <span class="button-text"><?php esc_html_e( 'Edit', 'school-management-calendar' ); ?></span>
                                </a>
                                <?php
                                $delete_url = wp_nonce_url(
                                    '?page=school-management-events&delete=' . intval( $event->id ),
                                    'smc_delete_event_' . intval( $event->id )
                                );
                                ?>
                                <a href="<?php echo esc_url( $delete_url ); ?>"
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this event?', 'school-management-calendar' ) ); ?>')">
                                    <span class="dashicons dashicons-trash align-middle text-danger"></span>
                                    <span class="button-text"><?php esc_html_e( 'Delete', 'school-management-calendar' ); ?></span>
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
                <span class="dashicons dashicons-megaphone" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 16px;"></span>
                <h3><?php esc_html_e( 'No Events Yet', 'school-management-calendar' ); ?></h3>
                <p><?php esc_html_e( 'Create your first event to get started.', 'school-management-calendar' ); ?></p>
                <a href="?page=school-management-events&action=add" class="button button-primary">
                    <?php esc_html_e( 'Add First Event', 'school-management-calendar' ); ?>
                </a>
            </div>
        <?php endif;
    }
    /**
     * Render event form
     */
    private static function render_event_form( $event = null ) {
        global $wpdb;
        $is_edit = ! empty( $event );
        
        // Get courses, classrooms, and teachers
        $courses = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sm_courses WHERE is_active = 1 ORDER BY name ASC" );
        $classrooms = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sm_classrooms WHERE is_active = 1 ORDER BY name ASC" );
        $teachers = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sm_teachers WHERE is_active = 1 ORDER BY last_name ASC, first_name ASC" );
        
        // Event types
        $event_types = [
            'exam' => __( 'Exam', 'school-management-calendar' ),
            'holiday' => __( 'Holiday', 'school-management-calendar' ),
            'meeting' => __( 'Meeting', 'school-management-calendar' ),
            'special_event' => __( 'Special Event', 'school-management-calendar' ),
            'school_closure' => __( 'School Closure', 'school-management-calendar' ),
        ];
        
        // Form data
        $form_data = [];
        if ( isset( $_POST['smc_save_event'] ) || isset( $_POST['smc_save_and_new'] ) ) {
            $form_data = [
                'title' => sanitize_text_field( $_POST['title'] ?? '' ),
                'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
                'event_type' => sanitize_text_field( $_POST['event_type'] ?? '' ),
                'event_date' => sanitize_text_field( $_POST['event_date'] ?? '' ),
                'is_all_day' => isset( $_POST['is_all_day'] ),
                'no_courses' => isset( $_POST['no_courses'] ),
                'start_time' => sanitize_text_field( $_POST['start_time'] ?? '' ),
                'end_time' => sanitize_text_field( $_POST['end_time'] ?? '' ),
                'course_id' => intval( $_POST['course_id'] ?? 0 ),
                'classroom_id' => intval( $_POST['classroom_id'] ?? 0 ),
                'teacher_id' => intval( $_POST['teacher_id'] ?? 0 ),
                'color' => sanitize_text_field( $_POST['color'] ?? '#3788d8' ),
                'is_public' => isset( $_POST['is_public'] ),
            ];
        } elseif ( $event ) {
            $form_data = [
                'title' => $event->title,
                'description' => $event->description,
                'event_type' => $event->event_type,
                'event_date' => $event->event_date,
                'is_all_day' => $event->is_all_day,
                'no_courses' => $event->no_courses,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'course_id' => $event->course_id,
                'classroom_id' => $event->classroom_id,
                'teacher_id' => $event->teacher_id,
                'color' => $event->color,
                'is_public' => $event->is_public,
            ];
        } else {
            // Default values for new event
            $form_data = [
                'title' => '',
                'description' => '',
                'event_type' => 'special_event',
                'event_date' => date( 'Y-m-d' ),
                'is_all_day' => false,
                'no_courses' => false,
                'start_time' => '09:00',
                'end_time' => '10:00',
                'course_id' => 0,
                'classroom_id' => 0,
                'teacher_id' => 0,
                'color' => '#3788d8',
                'is_public' => true,
            ];
        }
        
        ?>
        <div class="sm-form-header mb-20">
            <a href="?page=school-management-events" class="button">
                <span class="dashicons dashicons-arrow-left-alt2 align-middle"></span>
                <?php esc_html_e( 'Back to Events', 'school-management-calendar' ); ?>
            </a>
            <h2 class="d-inline-block ml-10">
                <?php echo $is_edit ? esc_html__( 'Edit Event', 'school-management-calendar' ) : esc_html__( 'Add New Event', 'school-management-calendar' ); ?>
            </h2>
        </div>

        <form method="post">
            <?php wp_nonce_field( 'smc_save_event_action', 'smc_save_event_nonce' ); ?>
            <input type="hidden" name="event_id" value="<?php echo esc_attr( $event->id ?? '' ); ?>" />

            <h3><?php esc_html_e( 'Event Information', 'school-management-calendar' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="event_title"><?php esc_html_e( 'Event Title', 'school-management-calendar' ); ?> <span class="text-danger">*</span></label>
                    </th>
                    <td>
                        <input type="text" id="event_title" name="title" value="<?php echo esc_attr( $form_data['title'] ); ?>" class="regular-text" required />
                        <p class="description"><?php esc_html_e( 'E.g., "Final Exam - Mathematics", "Summer Holiday", "Parent-Teacher Meeting"', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="event_type"><?php esc_html_e( 'Event Type', 'school-management-calendar' ); ?> <span class="text-danger">*</span></label>
                    </th>
                    <td>
                        <select id="event_type" name="event_type" required>
                            <option value=""><?php esc_html_e( 'Select Type', 'school-management-calendar' ); ?></option>
                            <?php foreach ( $event_types as $type => $label ) : ?>
                                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $form_data['event_type'], $type ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="event_description"><?php esc_html_e( 'Description', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <textarea id="event_description" name="description" rows="4" class="large-text"><?php echo esc_textarea( $form_data['description'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Additional details about the event.', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="event_color"><?php esc_html_e( 'Color', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <input type="color" id="event_color" name="color" value="<?php echo esc_attr( $form_data['color'] ); ?>" />
                        <p class="description"><?php esc_html_e( 'Color used to display this event on the calendar.', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Date & Time', 'school-management-calendar' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="event_date"><?php esc_html_e( 'Event Date', 'school-management-calendar' ); ?> <span class="text-danger">*</span></label>
                    </th>
                    <td>
                        <input type="date" id="event_date" name="event_date" value="<?php echo esc_attr( $form_data['event_date'] ); ?>" required />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="event_is_all_day"><?php esc_html_e( 'All Day Event', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="event_is_all_day" name="is_all_day" value="1" <?php checked( $form_data['is_all_day'] ); ?> />
                            <?php esc_html_e( 'This event lasts all day', 'school-management-calendar' ); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="event_no_courses"><?php esc_html_e( 'No Courses/Classes', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="event_no_courses" name="no_courses" value="1" <?php checked( $form_data['no_courses'] ); ?> />
                            <?php esc_html_e( 'No regular courses/classes should occur during this event', 'school-management-calendar' ); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e( 'When checked, scheduled courses will not be displayed for this date in the calendar and schedules.', 'school-management-calendar' ); ?>
                        </p>
                    </td>
                </tr>

                <tr id="time_fields" style="<?php echo $form_data['is_all_day'] ? 'display:none;' : ''; ?>">
                    <th scope="row">
                        <label for="event_start_time"><?php esc_html_e( 'Time', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <input type="time" id="event_start_time" name="start_time" value="<?php echo esc_attr( $form_data['start_time'] ); ?>" />
                        <span style="margin: 0 10px;"><?php esc_html_e( 'to', 'school-management-calendar' ); ?></span>
                        <input type="time" id="event_end_time" name="end_time" value="<?php echo esc_attr( $form_data['end_time'] ); ?>" />
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Associated Resources', 'school-management-calendar' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="event_course"><?php esc_html_e( 'Course', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <select id="event_course" name="course_id" style="min-width: 300px;">
                            <option value=""><?php esc_html_e( 'No Course', 'school-management-calendar' ); ?></option>
                            <?php foreach ( $courses as $course ) : ?>
                                <option value="<?php echo intval( $course->id ); ?>" <?php selected( $form_data['course_id'], $course->id ); ?>>
                                    <?php echo esc_html( $course->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Optional: Link this event to a course (useful for exams).', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="event_classroom"><?php esc_html_e( 'Classroom', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <select id="event_classroom" name="classroom_id" style="min-width: 300px;">
                            <option value=""><?php esc_html_e( 'No Classroom', 'school-management-calendar' ); ?></option>
                            <?php foreach ( $classrooms as $classroom ) : ?>
                                <option value="<?php echo intval( $classroom->id ); ?>" <?php selected( $form_data['classroom_id'], $classroom->id ); ?>>
                                    <?php echo esc_html( $classroom->name ); ?>
                                    <?php if ( $classroom->location ) : ?>
                                        - <?php echo esc_html( $classroom->location ); ?>
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Optional: Specify location/classroom.', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="event_teacher"><?php esc_html_e( 'Teacher', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <select id="event_teacher" name="teacher_id" style="min-width: 300px;">
                            <option value=""><?php esc_html_e( 'No Teacher', 'school-management-calendar' ); ?></option>
                            <?php foreach ( $teachers as $teacher ) : ?>
                                <option value="<?php echo intval( $teacher->id ); ?>" <?php selected( $form_data['teacher_id'], $teacher->id ); ?>>
                                    <?php echo esc_html( $teacher->first_name . ' ' . $teacher->last_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Optional: Assign a teacher to this event.', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e( 'Visibility', 'school-management-calendar' ); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="event_is_public"><?php esc_html_e( 'Public Event', 'school-management-calendar' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="event_is_public" name="is_public" value="1" <?php checked( $form_data['is_public'] ); ?> />
                            <?php esc_html_e( 'Visible to students and teachers', 'school-management-calendar' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Uncheck to make this event visible only to administrators.', 'school-management-calendar' ); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <?php if ( $is_edit ) : ?>
                    <?php submit_button( 
                        __( 'Update Event', 'school-management-calendar' ), 
                        'primary', 
                        'smc_save_event', 
                        false 
                    ); ?>
                <?php else : ?>
                    <?php submit_button( 
                        __( 'Save & Exit', 'school-management-calendar' ), 
                        'primary', 
                        'smc_save_event', 
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
                <a href="?page=school-management-events" class="button ml-10"><?php esc_html_e( 'Cancel', 'school-management-calendar' ); ?></a>
            </p>
            
            <p class="description">
                <span class="text-danger">*</span> <?php esc_html_e( 'Required fields', 'school-management-calendar' ); ?>
            </p>

            <script>
            jQuery(document).ready(function($) {
                // Toggle time fields based on all-day checkbox
                $('#event_is_all_day').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('#time_fields').hide();
                        $('#event_start_time, #event_end_time').prop('required', false);
                    } else {
                        $('#time_fields').show();
                        $('#event_start_time, #event_end_time').prop('required', true);
                    }
                });
            });
            </script>
        </form>
        <?php
    }
}