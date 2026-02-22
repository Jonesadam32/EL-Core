<?php
/**
 * Events Module
 * 
 * Business logic for event management: CRUD operations, RSVP handling,
 * and event reminders. Infrastructure (database, shortcodes, settings)
 * is handled by EL Core based on module.json.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Events_Module {

    private static ?EL_Events_Module $instance = null;
    private ?EL_Core $core = null;

    public static function instance( ?EL_Core $core = null ): self {
        if ( null === self::$instance ) {
            self::$instance = new self( $core );
        }
        return self::$instance;
    }

    private function __construct( ?EL_Core $core = null ) {
        $this->core = $core;
        $this->init_hooks();
    }

    /**
     * Register module-specific hooks
     */
    private function init_hooks(): void {
        // AJAX handlers
        add_action( 'el_core_ajax_rsvp_event', [ $this, 'handle_rsvp' ] );
        add_action( 'el_core_ajax_create_event', [ $this, 'handle_create_event' ] );

        // Register admin menu at priority 20 (after core at priority 10)
        add_action( 'admin_menu', [ $this, 'register_admin_pages' ], 20 );
    }

    public function register_admin_pages(): void {
        add_submenu_page(
            'el-core',
            __( 'Events', 'el-core' ),
            __( 'Events', 'el-core' ),
            'manage_options',
            'el-core-events',
            [ $this, 'render_admin_page' ]
        );
    }

    public function render_admin_page(): void {
        echo '<div class="wrap"><h1>Events</h1><p>Events admin coming soon.</p></div>';
    }

    // ═══════════════════════════════════════════
    // QUERIES
    // ═══════════════════════════════════════════

    /**
     * Get upcoming events
     */
    public function get_upcoming_events( int $limit = 6 ): array {
        return $this->core->database->query(
            'el_events',
            [ 'start_date >' => current_time( 'mysql' ), 'status' => 'published' ],
            [ 'orderby' => 'start_date', 'order' => 'ASC', 'limit' => $limit ]
        );
    }

    /**
     * Get a single event by ID
     */
    public function get_event( int $id ): ?object {
        return $this->core->database->get( 'el_events', $id );
    }

    /**
     * Get all events (for admin)
     */
    public function get_all_events( int $limit = 50 ): array {
        return $this->core->database->query(
            'el_events',
            [],
            [ 'orderby' => 'start_date', 'order' => 'DESC', 'limit' => $limit ]
        );
    }

    /**
     * Get RSVPs for an event
     */
    public function get_event_rsvps( int $event_id ): array {
        return $this->core->database->query(
            'el_event_rsvps',
            [ 'event_id' => $event_id, 'status' => 'attending' ]
        );
    }

    /**
     * Get RSVP count for an event
     */
    public function get_rsvp_count( int $event_id ): int {
        return $this->core->database->count(
            'el_event_rsvps',
            [ 'event_id' => $event_id, 'status' => 'attending' ]
        );
    }

    /**
     * Check if current user has RSVP'd to an event
     */
    public function user_has_rsvp( int $event_id, int $user_id = 0 ): bool {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        return $this->core->database->count(
            'el_event_rsvps',
            [ 'event_id' => $event_id, 'user_id' => $user_id, 'status' => 'attending' ]
        ) > 0;
    }

    // ═══════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════

    /**
     * Create a new event
     */
    public function create_event( array $data ): int|false {
        return $this->core->database->insert( 'el_events', [
            'title'          => sanitize_text_field( $data['title'] ),
            'description'    => wp_kses_post( $data['description'] ?? '' ),
            'start_date'     => sanitize_text_field( $data['start_date'] ),
            'end_date'       => sanitize_text_field( $data['end_date'] ?? '' ),
            'location'       => sanitize_text_field( $data['location'] ?? '' ),
            'max_attendees'  => absint( $data['max_attendees'] ?? 0 ),
            'created_by'     => get_current_user_id(),
            'status'         => 'published',
        ]);
    }

    /**
     * Handle RSVP AJAX request
     */
    public function handle_rsvp( array $data ): void {
        $event_id = absint( $data['event_id'] ?? 0 );
        $user_id  = get_current_user_id();

        if ( ! $event_id || ! $user_id ) {
            EL_AJAX_Handler::error( 'Invalid request.' );
            return;
        }

        if ( ! el_core_can( 'rsvp_events' ) ) {
            EL_AJAX_Handler::error( 'You do not have permission to RSVP.', 403 );
            return;
        }

        // Check if already RSVP'd
        if ( $this->user_has_rsvp( $event_id, $user_id ) ) {
            // Cancel RSVP
            $this->core->database->update(
                'el_event_rsvps',
                [ 'status' => 'cancelled' ],
                [ 'event_id' => $event_id, 'user_id' => $user_id ]
            );
            EL_AJAX_Handler::success( [ 'rsvp_status' => 'cancelled' ], 'RSVP cancelled.' );
            return;
        }

        // Check capacity
        $event = $this->get_event( $event_id );
        if ( ! $event ) {
            EL_AJAX_Handler::error( 'Event not found.', 404 );
            return;
        }

        if ( $event->max_attendees > 0 ) {
            $count = $this->get_rsvp_count( $event_id );
            if ( $count >= $event->max_attendees ) {
                EL_AJAX_Handler::error( 'This event is full.' );
                return;
            }
        }

        // Create RSVP
        $this->core->database->insert( 'el_event_rsvps', [
            'event_id' => $event_id,
            'user_id'  => $user_id,
            'status'   => 'attending',
        ]);

        EL_AJAX_Handler::success( [ 'rsvp_status' => 'attending' ], 'RSVP confirmed!' );
    }

    /**
     * Handle create event AJAX request
     */
    public function handle_create_event( array $data ): void {
        if ( ! el_core_can( 'create_events' ) ) {
            EL_AJAX_Handler::error( 'You do not have permission to create events.', 403 );
            return;
        }

        $event_id = $this->create_event( $data );

        if ( $event_id ) {
            EL_AJAX_Handler::success( [ 'event_id' => $event_id ], 'Event created!' );
        } else {
            EL_AJAX_Handler::error( 'Failed to create event.' );
        }
    }
}
