<?php
/**
 * Expand Site Module
 *
 * Business logic for the 8-stage client site-building pipeline.
 * Manages projects, stages, deliverables, feedback, and change orders.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Expand_Site_Module {

    private static ?EL_Expand_Site_Module $instance = null;
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

    private function init_hooks(): void {
        // AJAX handlers (authenticated users only — clients will be logged in)
        add_action( 'el_core_ajax_es_create_project',      [ $this, 'handle_create_project' ] );
        add_action( 'el_core_ajax_es_update_project',      [ $this, 'handle_update_project' ] );
        add_action( 'el_core_ajax_es_delete_project',      [ $this, 'handle_delete_project' ] );
        add_action( 'el_core_ajax_es_advance_stage',       [ $this, 'handle_advance_stage' ] );
        add_action( 'el_core_ajax_es_submit_feedback',     [ $this, 'handle_submit_feedback' ] );
        add_action( 'el_core_ajax_es_add_deliverable',     [ $this, 'handle_add_deliverable' ] );
        add_action( 'el_core_ajax_es_review_deliverable',  [ $this, 'handle_review_deliverable' ] );
        add_action( 'el_core_ajax_es_add_page',            [ $this, 'handle_add_page' ] );
        add_action( 'el_core_ajax_es_update_page',         [ $this, 'handle_update_page' ] );
        add_action( 'el_core_ajax_es_update_feedback',     [ $this, 'handle_update_feedback' ] );
        add_action( 'el_core_ajax_es_client_review_page',  [ $this, 'handle_client_review_page' ] );
        add_action( 'el_core_ajax_es_add_stakeholder',     [ $this, 'handle_add_stakeholder' ] );
        add_action( 'el_core_ajax_es_remove_stakeholder',  [ $this, 'handle_remove_stakeholder' ] );
        add_action( 'el_core_ajax_es_change_stakeholder_role', [ $this, 'handle_change_stakeholder_role' ] );
        add_action( 'el_core_ajax_es_search_users',        [ $this, 'handle_search_users' ] );
        
        // Deadline management
        add_action( 'el_core_ajax_es_set_deadline',        [ $this, 'handle_set_deadline' ] );
        add_action( 'el_core_ajax_es_extend_deadline',     [ $this, 'handle_extend_deadline' ] );
        add_action( 'el_core_ajax_es_clear_flag',          [ $this, 'handle_clear_flag' ] );
        
        // User switching
        add_action( 'admin_init', [ $this, 'handle_switch_to_user' ] );

        // Register admin menu at priority 20 (after core at priority 10)
        add_action( 'admin_menu', [ $this, 'register_admin_pages' ], 20 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    // ═══════════════════════════════════════════
    // ADMIN PAGES
    // ═══════════════════════════════════════════

    public function register_admin_pages(): void {
        add_submenu_page(
            'el-core',
            __( 'Expand Site', 'el-core' ),
            __( 'Expand Site', 'el-core' ),
            'manage_options',
            'el-core-projects',
            [ $this, 'render_admin_page' ]
        );
        
        add_submenu_page(
            'el-core',
            __( 'Expand Site Settings', 'el-core' ),
            __( 'Expand Site Settings', 'el-core' ),
            'manage_options',
            'el-core-expand-site-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    public function render_admin_page(): void {
        $project_id = absint( $_GET['project'] ?? 0 );
        $action     = sanitize_text_field( $_GET['action'] ?? '' );

        if ( $project_id && $action === 'edit' ) {
            require_once __DIR__ . '/admin/views/project-form.php';
        } elseif ( $project_id ) {
            require_once __DIR__ . '/admin/views/project-detail.php';
        } else {
            require_once __DIR__ . '/admin/views/project-list.php';
        }
    }

    public function render_settings_page(): void {
        require_once __DIR__ . '/admin/views/settings.php';
    }

    // ═══════════════════════════════════════════
    // ASSET ENQUEUING
    // ═══════════════════════════════════════════

    public function enqueue_frontend_assets(): void {
        global $post;
        if ( ! $post ) return;

        $shortcodes = [ 'el_expand_site_portal', 'el_project_status', 'el_page_review', 'el_feedback_form' ];
        $has_shortcode = false;
        foreach ( $shortcodes as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) {
                $has_shortcode = true;
                break;
            }
        }

        if ( $has_shortcode ) {
            wp_enqueue_style(
                'el-expand-site',
                EL_CORE_URL . 'modules/expand-site/assets/css/expand-site.css',
                [ 'el-core' ],
                EL_CORE_VERSION
            );
            wp_enqueue_script(
                'el-expand-site',
                EL_CORE_URL . 'modules/expand-site/assets/js/expand-site.js',
                [ 'el-core' ],
                EL_CORE_VERSION,
                true
            );
        }
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, 'el-core-projects' ) === false ) return;

        wp_enqueue_style(
            'el-expand-site-admin',
            EL_CORE_URL . 'modules/expand-site/assets/css/expand-site.css',
            [ 'el-admin' ],
            EL_CORE_VERSION
        );
        wp_enqueue_script(
            'el-expand-site-admin',
            EL_CORE_URL . 'modules/expand-site/assets/js/expand-site-admin.js',
            [],
            EL_CORE_VERSION,
            true
        );
        
        // Localize script with AJAX URL, nonce, and project URL template
        wp_localize_script( 'el-expand-site-admin', 'elExpandSiteAdmin', [
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'el_core_nonce' ),
            'projectUrl' => admin_url( 'admin.php?page=el-core-projects&project=PROJECT_ID' ),
        ] );
    }

    // ═══════════════════════════════════════════
    // STAGE DEFINITIONS
    // ═══════════════════════════════════════════

    /**
     * Hardcoded stage configuration
     * Expand Site is proprietary - these stages are fixed for ELS workflow
     */
    public const STAGES = [
        1 => [ 'name' => 'Qualification',   'slug' => 'qualification',   'has_client_gate' => true ],
        2 => [ 'name' => 'Discovery',       'slug' => 'discovery',       'has_client_gate' => true ],
        3 => [ 'name' => 'Scope Lock',      'slug' => 'scope-lock',      'has_client_gate' => true ],
        4 => [ 'name' => 'Visual Identity', 'slug' => 'visual-identity', 'has_client_gate' => true ],
        5 => [ 'name' => 'Wireframes',      'slug' => 'wireframes',      'has_client_gate' => true ],
        6 => [ 'name' => 'Build',           'slug' => 'build',           'has_client_gate' => false ],
        7 => [ 'name' => 'Review',          'slug' => 'review',          'has_client_gate' => true ],
        8 => [ 'name' => 'Delivery',        'slug' => 'delivery',        'has_client_gate' => true ],
    ];

    /**
     * Default deadline days per stage (from when stage starts)
     * Used as smart defaults in the Advance Stage modal date picker
     */
    public const STAGE_DEADLINE_DAYS = [
        1 => 3,   // Qualification: 3 days
        2 => 7,   // Discovery: 7 days
        3 => 5,   // Scope Lock: 5 days
        4 => 10,  // Visual Identity: 10 days
        5 => 10,  // Wireframes: 10 days
        6 => 14,  // Build: 14 days
        7 => 7,   // Review: 7 days
        8 => 3,   // Delivery: 3 days
    ];

    /**
     * Get stage configuration array
     */
    public function get_stages(): array {
        return self::STAGES;
    }

    public function get_stage_name( int $stage ): string {
        return self::STAGES[$stage]['name'] ?? "Stage {$stage}";
    }

    /**
     * Get default deadline days for a stage
     */
    public function get_stage_deadline_days( int $stage ): int {
        return self::STAGE_DEADLINE_DAYS[$stage] ?? 7;
    }

    /**
     * Static version for backward compatibility
     */
    public static function get_stage_name_static( int $stage ): string {
        return self::STAGES[$stage]['name'] ?? 'Unknown';
    }

    public static function get_stage_badge_variant( int $stage ): string {
        if ( $stage <= 2 ) return 'info';
        if ( $stage === 3 ) return 'warning';
        if ( $stage <= 5 ) return 'primary';
        if ( $stage === 6 ) return 'default';
        if ( $stage === 7 ) return 'warning';
        if ( $stage === 8 ) return 'success';
        return 'default';
    }

    public static function get_status_badge_variant( string $status ): string {
        switch ( $status ) {
            case 'active':    return 'success';
            case 'paused':    return 'warning';
            case 'completed': return 'info';
            case 'cancelled': return 'error';
            default:          return 'default';
        }
    }

    // ═══════════════════════════════════════════
    // PERMISSION HELPERS
    // ═══════════════════════════════════════════

    /**
     * Check if current user is the decision maker for a project
     */
    public function is_decision_maker( int $project_id ): bool {
        $project = $this->get_project( $project_id );
        if ( ! $project ) {
            return false;
        }

        $user_id = get_current_user_id();
        
        // Agency admins can act as decision makers
        if ( el_core_can( 'manage_expand_site' ) ) {
            return true;
        }

        // Check if user is the designated decision maker
        if ( (int) $project->decision_maker_id === $user_id ) {
            return el_core_can( 'es_decision_maker' );
        }

        return false;
    }

    /**
     * Check if current user is a stakeholder (any role) for a project
     */
    public function is_stakeholder( int $project_id ): bool {
        $user_id = get_current_user_id();
        
        // Agency admins can act as stakeholders
        if ( el_core_can( 'manage_expand_site' ) ) {
            return true;
        }

        // Check stakeholders table
        $stakeholders = $this->core->database->query( 'el_es_stakeholders', [
            'project_id' => $project_id,
            'user_id'    => $user_id,
        ] );

        return ! empty( $stakeholders );
    }

    /**
     * Check if current user can provide input (contributor or higher)
     */
    public function can_contribute( int $project_id ): bool {
        if ( el_core_can( 'manage_expand_site' ) ) {
            return true;
        }

        if ( ! $this->is_stakeholder( $project_id ) ) {
            return false;
        }

        return el_core_can( 'es_contributor' ) || el_core_can( 'es_decision_maker' );
    }

    // ═══════════════════════════════════════════
    // QUERIES
    // ═══════════════════════════════════════════

    public function get_all_projects( array $where = [], array $options = [] ): array {
        if ( ! $this->core ) {
            error_log( 'EL Expand Site: Core not initialized in get_all_projects' );
            return [];
        }
        $defaults = [ 'orderby' => 'created_at', 'order' => 'DESC' ];
        $options = array_merge( $defaults, $options );
        return $this->core->database->query( 'el_es_projects', $where, $options );
    }

    public function get_project( int $id ): ?object {
        return $this->core->database->get( 'el_es_projects', $id );
    }

    public function get_stage_history( int $project_id ): array {
        return $this->core->database->query( 'el_es_stage_history', [
            'project_id' => $project_id,
        ], [
            'orderby' => 'created_at',
            'order'   => 'ASC',
        ] );
    }

    public function get_deliverables( int $project_id, int $stage = 0 ): array {
        $where = [ 'project_id' => $project_id ];
        if ( $stage > 0 ) {
            $where['stage'] = $stage;
        }
        return $this->core->database->query( 'el_es_deliverables', $where, [
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ] );
    }

    public function get_deliverable( int $id ): ?object {
        return $this->core->database->get( 'el_es_deliverables', $id );
    }

    public function get_feedback( int $project_id, int $stage = 0 ): array {
        $where = [ 'project_id' => $project_id ];
        if ( $stage > 0 ) {
            $where['stage'] = $stage;
        }
        return $this->core->database->query( 'el_es_feedback', $where, [
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ] );
    }

    public function get_pages( int $project_id ): array {
        return $this->core->database->query( 'el_es_pages', [
            'project_id' => $project_id,
        ], [
            'orderby' => 'sort_order',
            'order'   => 'ASC',
        ] );
    }

    public function get_stakeholders( int $project_id ): array {
        return $this->core->database->query( 'el_es_stakeholders', [
            'project_id' => $project_id,
        ], [
            'orderby' => 'added_at',
            'order'   => 'ASC',
        ] );
    }

    public function get_change_orders( int $project_id ): array {
        return $this->core->database->query( 'el_es_feedback', [
            'project_id'      => $project_id,
            'is_change_order' => 1,
        ], [
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ] );
    }

    public function count_projects( array $where = [] ): int {
        return $this->core->database->count( 'el_es_projects', $where );
    }

    public function count_feedback( int $project_id, array $extra_where = [] ): int {
        $where = array_merge( [ 'project_id' => $project_id ], $extra_where );
        return $this->core->database->count( 'el_es_feedback', $where );
    }

    // ═══════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════

    public function create_project( array $data ): int|false {
        $db = $this->core->database;

        $project_id = $db->insert( 'el_es_projects', [
            'name'             => sanitize_text_field( $data['name'] ?? '' ),
            'client_name'      => sanitize_text_field( $data['client_name'] ?? '' ),
            'client_user_id'   => absint( $data['client_user_id'] ?? 0 ),
            'current_stage'    => 1,
            'status'           => 'active',
            'budget_range_low' => floatval( $data['budget_range_low'] ?? 0 ),
            'budget_range_high'=> floatval( $data['budget_range_high'] ?? 0 ),
            'notes'            => wp_kses_post( $data['notes'] ?? '' ),
            'created_by'       => get_current_user_id(),
            'created_at'       => current_time( 'mysql' ),
            'updated_at'       => current_time( 'mysql' ),
        ] );

        if ( $project_id ) {
            $db->insert( 'el_es_stage_history', [
                'project_id' => $project_id,
                'stage'      => 1,
                'action'     => 'entered',
                'notes'      => __( 'Project created', 'el-core' ),
                'acted_by'   => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ] );
        }

        return $project_id;
    }

    public function update_project( int $id, array $data ): int|false {
        $clean = [];

        if ( isset( $data['name'] ) )              $clean['name']              = sanitize_text_field( $data['name'] );
        if ( isset( $data['client_name'] ) )        $clean['client_name']       = sanitize_text_field( $data['client_name'] );
        if ( isset( $data['client_user_id'] ) )     $clean['client_user_id']    = absint( $data['client_user_id'] );
        if ( isset( $data['status'] ) )             $clean['status']            = sanitize_text_field( $data['status'] );
        if ( isset( $data['budget_range_low'] ) )   $clean['budget_range_low']  = floatval( $data['budget_range_low'] );
        if ( isset( $data['budget_range_high'] ) )  $clean['budget_range_high'] = floatval( $data['budget_range_high'] );
        if ( isset( $data['final_price'] ) )        $clean['final_price']       = floatval( $data['final_price'] );
        if ( isset( $data['notes'] ) )              $clean['notes']             = wp_kses_post( $data['notes'] );

        $clean['updated_at'] = current_time( 'mysql' );

        return $this->core->database->update( 'el_es_projects', $clean, [ 'id' => $id ] );
    }

    public function advance_stage( int $project_id, string $notes = '', string $deadline = '' ): bool {
        $project = $this->get_project( $project_id );
        if ( ! $project || $project->current_stage >= 8 ) {
            return false;
        }

        $db        = $this->core->database;
        $new_stage = $project->current_stage + 1;

        // Record approval of current stage
        $db->insert( 'el_es_stage_history', [
            'project_id' => $project_id,
            'stage'      => $project->current_stage,
            'action'     => 'approved',
            'notes'      => sanitize_text_field( $notes ),
            'acted_by'   => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ] );

        // Record entry into next stage
        $db->insert( 'el_es_stage_history', [
            'project_id' => $project_id,
            'stage'      => $new_stage,
            'action'     => 'entered',
            'notes'      => '',
            'acted_by'   => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ] );

        $update_data = [
            'current_stage' => $new_stage,
            'updated_at'    => current_time( 'mysql' ),
        ];

        // Set deadline for new stage if provided
        if ( $deadline ) {
            $deadline_datetime = date( 'Y-m-d 23:59:59', strtotime( $deadline ) );
            $update_data['deadline'] = $deadline_datetime;
            $update_data['deadline_stage'] = $new_stage;
            
            // Record deadline in deadlines table
            $db->insert( 'el_es_deadlines', [
                'project_id' => $project_id,
                'stage'      => $new_stage,
                'deadline'   => $deadline_datetime,
                'set_by'     => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ] );
        }

        // Lock scope when entering Stage 4
        if ( $new_stage === 4 && ! $project->scope_locked_at ) {
            $update_data['scope_locked_at'] = current_time( 'mysql' );
        }

        $db->update( 'el_es_projects', $update_data, [ 'id' => $project_id ] );
        return true;
    }

    public function add_deliverable( array $data ): int|false {
        return $this->core->database->insert( 'el_es_deliverables', [
            'project_id'    => absint( $data['project_id'] ?? 0 ),
            'stage'         => absint( $data['stage'] ?? 0 ),
            'title'         => sanitize_text_field( $data['title'] ?? '' ),
            'description'   => wp_kses_post( $data['description'] ?? '' ),
            'file_url'      => esc_url_raw( $data['file_url'] ?? '' ),
            'file_type'     => sanitize_text_field( $data['file_type'] ?? '' ),
            'review_status' => 'pending',
            'created_at'    => current_time( 'mysql' ),
        ] );
    }

    public function review_deliverable( int $id, string $status ): int|false {
        $valid = [ 'pending', 'approved', 'needs_revision' ];
        if ( ! in_array( $status, $valid, true ) ) {
            return false;
        }
        return $this->core->database->update( 'el_es_deliverables', [
            'review_status' => $status,
        ], [ 'id' => $id ] );
    }

    public function submit_feedback( array $data ): int|false {
        return $this->core->database->insert( 'el_es_feedback', [
            'project_id'         => absint( $data['project_id'] ?? 0 ),
            'deliverable_id'     => absint( $data['deliverable_id'] ?? 0 ),
            'stage'              => absint( $data['stage'] ?? 0 ),
            'user_id'            => get_current_user_id(),
            'feedback_type'      => sanitize_text_field( $data['feedback_type'] ?? 'revision' ),
            'content'            => wp_kses_post( $data['content'] ?? '' ),
            'status'             => 'pending',
            'is_change_order'    => absint( $data['is_change_order'] ?? 0 ),
            'change_order_price' => floatval( $data['change_order_price'] ?? 0 ),
            'created_at'         => current_time( 'mysql' ),
        ] );
    }

    public function update_feedback_status( int $id, string $status ): int|false {
        $valid = [ 'pending', 'acknowledged', 'resolved', 'deferred' ];
        if ( ! in_array( $status, $valid, true ) ) {
            return false;
        }
        return $this->core->database->update( 'el_es_feedback', [
            'status' => $status,
        ], [ 'id' => $id ] );
    }

    public function add_page( array $data ): int|false {
        return $this->core->database->insert( 'el_es_pages', [
            'project_id' => absint( $data['project_id'] ?? 0 ),
            'page_name'  => sanitize_text_field( $data['page_name'] ?? '' ),
            'page_url'   => esc_url_raw( $data['page_url'] ?? '' ),
            'status'     => 'planned',
            'sort_order' => absint( $data['sort_order'] ?? 0 ),
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    public function update_page( int $id, array $data ): int|false {
        $clean = [];
        if ( isset( $data['page_name'] ) )  $clean['page_name']  = sanitize_text_field( $data['page_name'] );
        if ( isset( $data['page_url'] ) )   $clean['page_url']   = esc_url_raw( $data['page_url'] );
        if ( isset( $data['status'] ) )     $clean['status']      = sanitize_text_field( $data['status'] );
        if ( isset( $data['sort_order'] ) ) $clean['sort_order']  = absint( $data['sort_order'] );

        return $this->core->database->update( 'el_es_pages', $clean, [ 'id' => $id ] );
    }
    
    public function delete_project( int $project_id ): bool {
        $db = $this->core->database;
        
        // Delete all related data
        $db->delete( 'el_es_stakeholders', [ 'project_id' => $project_id ] );
        $db->delete( 'el_es_stage_history', [ 'project_id' => $project_id ] );
        $db->delete( 'el_es_deliverables', [ 'project_id' => $project_id ] );
        $db->delete( 'el_es_feedback', [ 'project_id' => $project_id ] );
        $db->delete( 'el_es_pages', [ 'project_id' => $project_id ] );
        
        // Delete the project itself
        $result = $db->delete( 'el_es_projects', [ 'id' => $project_id ] );
        
        return $result !== false;
    }

    public function add_stakeholder( int $project_id, int $user_id, string $role ): int|false {
        // Validate role
        if ( ! in_array( $role, [ 'decision_maker', 'contributor' ], true ) ) {
            return false;
        }

        // Check if user is already a stakeholder
        $existing = $this->core->database->query( 'el_es_stakeholders', [
            'project_id' => $project_id,
            'user_id'    => $user_id,
        ] );

        if ( ! empty( $existing ) ) {
            return false; // Already exists
        }

        // If adding as decision maker, check if one already exists
        if ( $role === 'decision_maker' ) {
            $project = $this->get_project( $project_id );
            if ( $project && ! $project->decision_maker_id ) {
                // Update project decision_maker_id
                $this->core->database->update( 'el_es_projects', [
                    'decision_maker_id' => $user_id,
                ], [ 'id' => $project_id ] );
            }
        }

        return $this->core->database->insert( 'el_es_stakeholders', [
            'project_id' => $project_id,
            'user_id'    => $user_id,
            'role'       => $role,
            'added_at'   => current_time( 'mysql' ),
        ] );
    }

    public function remove_stakeholder( int $stakeholder_id ): int|false {
        $stakeholder = $this->core->database->get( 'el_es_stakeholders', $stakeholder_id );
        if ( ! $stakeholder ) {
            return false;
        }

        // If removing the decision maker, update project
        if ( $stakeholder->role === 'decision_maker' ) {
            $project = $this->get_project( (int) $stakeholder->project_id );
            if ( $project && (int) $project->decision_maker_id === (int) $stakeholder->user_id ) {
                $this->core->database->update( 'el_es_projects', [
                    'decision_maker_id' => 0,
                ], [ 'id' => $stakeholder->project_id ] );
            }
        }

        return $this->core->database->delete( 'el_es_stakeholders', [ 'id' => $stakeholder_id ] );
    }

    public function change_stakeholder_role( int $stakeholder_id, string $new_role ): int|false {
        if ( ! in_array( $new_role, [ 'decision_maker', 'contributor' ], true ) ) {
            return false;
        }

        $stakeholder = $this->core->database->get( 'el_es_stakeholders', $stakeholder_id );
        if ( ! $stakeholder ) {
            return false;
        }

        // If changing to decision maker, update project
        if ( $new_role === 'decision_maker' ) {
            $this->core->database->update( 'el_es_projects', [
                'decision_maker_id' => $stakeholder->user_id,
            ], [ 'id' => $stakeholder->project_id ] );
        }

        // If changing from decision maker to contributor, clear project DM
        if ( $stakeholder->role === 'decision_maker' && $new_role === 'contributor' ) {
            $project = $this->get_project( (int) $stakeholder->project_id );
            if ( $project && (int) $project->decision_maker_id === (int) $stakeholder->user_id ) {
                $this->core->database->update( 'el_es_projects', [
                    'decision_maker_id' => 0,
                ], [ 'id' => $stakeholder->project_id ] );
            }
        }

        return $this->core->database->update( 'el_es_stakeholders', [
            'role' => $new_role,
        ], [ 'id' => $stakeholder_id ] );
    }

    // ═══════════════════════════════════════════
    // AJAX HANDLERS
    // ═══════════════════════════════════════════

    public function handle_create_project( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $name = sanitize_text_field( $data['name'] ?? '' );
        if ( empty( $name ) ) {
            EL_AJAX_Handler::error( __( 'Project name is required.', 'el-core' ) );
            return;
        }

        $client_name = sanitize_text_field( $data['client_name'] ?? '' );
        if ( empty( $client_name ) ) {
            EL_AJAX_Handler::error( __( 'Client name is required.', 'el-core' ) );
            return;
        }

        $project_id = $this->create_project( $data );

        if ( $project_id ) {
            EL_AJAX_Handler::success( [ 'project_id' => $project_id ], __( 'Project created!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to create project.', 'el-core' ) );
        }
    }

    public function handle_update_project( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['project_id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        $result = $this->update_project( $id, $data );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Project updated!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update project.', 'el-core' ) );
        }
    }
    
    public function handle_delete_project( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        $result = $this->delete_project( $project_id );

        if ( $result ) {
            EL_AJAX_Handler::success( null, __( 'Project deleted!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to delete project.', 'el-core' ) );
        }
    }

    public function handle_advance_stage( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        $notes  = sanitize_text_field( $data['notes'] ?? '' );
        $deadline = sanitize_text_field( $data['deadline'] ?? '' );
        $result = $this->advance_stage( $project_id, $notes, $deadline );

        if ( $result ) {
            $project = $this->get_project( $project_id );
            EL_AJAX_Handler::success( [
                'new_stage'      => $project->current_stage,
                'new_stage_name' => $this->get_stage_name( $project->current_stage ),
            ], __( 'Stage advanced!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Cannot advance stage.', 'el-core' ) );
        }
    }

    public function handle_submit_feedback( array $data ): void {
        if ( ! el_core_can( 'submit_feedback' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $content = wp_kses_post( $data['content'] ?? '' );
        if ( empty( trim( $content ) ) ) {
            EL_AJAX_Handler::error( __( 'Feedback content is required.', 'el-core' ) );
            return;
        }

        $feedback_id = $this->submit_feedback( $data );

        if ( $feedback_id ) {
            EL_AJAX_Handler::success( [ 'feedback_id' => $feedback_id ], __( 'Feedback submitted!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to submit feedback.', 'el-core' ) );
        }
    }

    public function handle_add_deliverable( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $title = sanitize_text_field( $data['title'] ?? '' );
        if ( empty( $title ) ) {
            EL_AJAX_Handler::error( __( 'Deliverable title is required.', 'el-core' ) );
            return;
        }

        $deliverable_id = $this->add_deliverable( $data );

        if ( $deliverable_id ) {
            EL_AJAX_Handler::success( [ 'deliverable_id' => $deliverable_id ], __( 'Deliverable added!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to add deliverable.', 'el-core' ) );
        }
    }

    public function handle_review_deliverable( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id     = absint( $data['deliverable_id'] ?? 0 );
        $status = sanitize_text_field( $data['review_status'] ?? '' );

        if ( ! $id || ! $status ) {
            EL_AJAX_Handler::error( __( 'Invalid parameters.', 'el-core' ) );
            return;
        }

        $result = $this->review_deliverable( $id, $status );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Deliverable status updated!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Invalid review status.', 'el-core' ) );
        }
    }

    public function handle_add_page( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $page_name = sanitize_text_field( $data['page_name'] ?? '' );
        if ( empty( $page_name ) ) {
            EL_AJAX_Handler::error( __( 'Page name is required.', 'el-core' ) );
            return;
        }

        $page_id = $this->add_page( $data );

        if ( $page_id ) {
            EL_AJAX_Handler::success( [ 'page_id' => $page_id ], __( 'Page added!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to add page.', 'el-core' ) );
        }
    }

    public function handle_update_page( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id = absint( $data['page_id'] ?? 0 );
        if ( ! $id ) {
            EL_AJAX_Handler::error( __( 'Invalid page ID.', 'el-core' ) );
            return;
        }

        $result = $this->update_page( $id, $data );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Page updated!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update page.', 'el-core' ) );
        }
    }

    /**
     * Client portal: approve or request revision on a page.
     * Requires view_expand_site; only project clients can review their pages.
     */
    public function handle_client_review_page( array $data ): void {
        if ( ! el_core_can( 'view_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $page_id = absint( $data['page_id'] ?? 0 );
        $status  = sanitize_text_field( $data['status'] ?? '' );

        if ( ! $page_id || ! in_array( $status, [ 'approved', 'needs_revision' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Invalid parameters.', 'el-core' ) );
            return;
        }

        $page = $this->core->database->get( 'el_es_pages', $page_id );
        if ( ! $page ) {
            EL_AJAX_Handler::error( __( 'Page not found.', 'el-core' ), 404 );
            return;
        }

        $project = $this->get_project( (int) $page->project_id );
        if ( ! $project || (int) $project->client_user_id !== get_current_user_id() ) {
            EL_AJAX_Handler::error( __( 'You cannot review this page.', 'el-core' ), 403 );
            return;
        }

        $result = $this->update_page( $page_id, [ 'status' => $status ] );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, $status === 'approved' ? __( 'Page approved!', 'el-core' ) : __( 'Revision requested.', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update page.', 'el-core' ) );
        }
    }

    public function handle_update_feedback( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $id     = absint( $data['feedback_id'] ?? 0 );
        $status = sanitize_text_field( $data['status'] ?? '' );

        if ( ! $id || ! $status ) {
            EL_AJAX_Handler::error( __( 'Invalid parameters.', 'el-core' ) );
            return;
        }

        $result = $this->update_feedback_status( $id, $status );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Feedback status updated!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Invalid feedback status.', 'el-core' ) );
        }
    }

    public function handle_add_stakeholder( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        $user_id    = absint( $data['user_id'] ?? 0 );
        $role       = sanitize_text_field( $data['role'] ?? 'contributor' );

        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        // If no user_id, try to create a new user
        if ( ! $user_id ) {
            $email      = sanitize_email( $data['new_user_email'] ?? '' );
            $first_name = sanitize_text_field( $data['new_user_first_name'] ?? '' );
            $last_name  = sanitize_text_field( $data['new_user_last_name'] ?? '' );

            if ( empty( $email ) || ! is_email( $email ) ) {
                EL_AJAX_Handler::error( __( 'Valid email is required to create a new user.', 'el-core' ) );
                return;
            }

            if ( empty( $first_name ) ) {
                EL_AJAX_Handler::error( __( 'First name is required to create a new user.', 'el-core' ) );
                return;
            }

            if ( empty( $last_name ) ) {
                EL_AJAX_Handler::error( __( 'Last name is required to create a new user.', 'el-core' ) );
                return;
            }

            // Build display name from first and last
            $display_name = trim( $first_name . ' ' . $last_name );

            // Check if user already exists
            $existing_user = get_user_by( 'email', $email );
            if ( $existing_user ) {
                $user_id = $existing_user->ID;
            } else {
                // Create new user with email as username (WordPress supports this)
                // This allows users to login with their email address
                $password = wp_generate_password( 12, true, true );
                
                // Try email as username first (best UX)
                $user_id = wp_create_user( $email, $password, $email );
                
                // If email username exists, add numbers to make unique
                if ( is_wp_error( $user_id ) && $user_id->get_error_code() === 'existing_user_login' ) {
                    $username = sanitize_user( $email, true ) . '_' . rand( 100, 999 );
                    $user_id = wp_create_user( $username, $password, $email );
                }

                if ( is_wp_error( $user_id ) ) {
                    EL_AJAX_Handler::error( __( 'Failed to create user: ', 'el-core' ) . $user_id->get_error_message() );
                    return;
                }

                // Set user meta
                wp_update_user( [
                    'ID'           => $user_id,
                    'display_name' => $display_name,
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                ] );

                // Assign appropriate capability
                $user = get_user_by( 'id', $user_id );
                if ( $role === 'decision_maker' ) {
                    $user->add_cap( 'es_decision_maker' );
                } else {
                    $user->add_cap( 'es_contributor' );
                }

                // Send password reset email (will fail silently without SMTP)
                // Store password temporarily for admin to give to user
                update_user_meta( $user_id, '_temp_initial_password', $password );
                wp_send_new_user_notifications( $user_id, 'user' );
            }
        }

        if ( ! $user_id ) {
            EL_AJAX_Handler::error( __( 'Invalid user ID.', 'el-core' ) );
            return;
        }

        $stakeholder_id = $this->add_stakeholder( $project_id, $user_id, $role );

        if ( $stakeholder_id ) {
            EL_AJAX_Handler::success( [ 'stakeholder_id' => $stakeholder_id ], __( 'Stakeholder added!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to add stakeholder. User may already be a stakeholder.', 'el-core' ) );
        }
    }

    public function handle_remove_stakeholder( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $stakeholder_id = absint( $data['stakeholder_id'] ?? 0 );

        if ( ! $stakeholder_id ) {
            EL_AJAX_Handler::error( __( 'Invalid stakeholder ID.', 'el-core' ) );
            return;
        }

        $result = $this->remove_stakeholder( $stakeholder_id );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Stakeholder removed!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to remove stakeholder.', 'el-core' ) );
        }
    }

    public function handle_change_stakeholder_role( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $stakeholder_id = absint( $data['stakeholder_id'] ?? 0 );
        $new_role       = sanitize_text_field( $data['new_role'] ?? '' );

        if ( ! $stakeholder_id || ! $new_role ) {
            EL_AJAX_Handler::error( __( 'Invalid parameters.', 'el-core' ) );
            return;
        }

        $result = $this->change_stakeholder_role( $stakeholder_id, $new_role );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Stakeholder role updated!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update role.', 'el-core' ) );
        }
    }

    public function handle_search_users( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $search = sanitize_text_field( $data['search'] ?? '' );

        if ( strlen( $search ) < 2 ) {
            EL_AJAX_Handler::error( __( 'Search term too short.', 'el-core' ) );
            return;
        }

        // Search by login, email, display_name (built-in WordPress search)
        $users = get_users( [
            'search'         => '*' . $search . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
            'number'         => 10,
        ] );

        // Also search by first_name and last_name (meta fields)
        $meta_users = get_users( [
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key'     => 'first_name',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => 'last_name',
                    'value'   => $search,
                    'compare' => 'LIKE',
                ],
            ],
            'number' => 10,
        ] );

        // Merge and deduplicate by user ID
        $all_users = array_merge( $users, $meta_users );
        $unique_users = [];
        $seen_ids = [];
        
        foreach ( $all_users as $user ) {
            if ( ! in_array( $user->ID, $seen_ids, true ) ) {
                $unique_users[] = $user;
                $seen_ids[] = $user->ID;
            }
        }

        // Limit to 10 results
        $unique_users = array_slice( $unique_users, 0, 10 );

        $results = [];
        foreach ( $unique_users as $user ) {
            $results[] = [
                'id'    => $user->ID,
                'name'  => $user->display_name,
                'email' => $user->user_email,
            ];
        }

        EL_AJAX_Handler::success( [ 'users' => $results ] );
    }
    
    // ═══════════════════════════════════════════
    // DEADLINE MANAGEMENT
    // ═══════════════════════════════════════════
    
    public function handle_set_deadline( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        $deadline   = sanitize_text_field( $data['deadline'] ?? '' );

        if ( ! $project_id || ! $deadline ) {
            EL_AJAX_Handler::error( __( 'Invalid parameters.', 'el-core' ) );
            return;
        }

        $project = $this->get_project( $project_id );
        if ( ! $project ) {
            EL_AJAX_Handler::error( __( 'Project not found.', 'el-core' ), 404 );
            return;
        }

        $deadline_datetime = date( 'Y-m-d 23:59:59', strtotime( $deadline ) );
        
        $result = $this->core->database->update( 'el_es_projects', [
            'deadline'       => $deadline_datetime,
            'deadline_stage' => $project->current_stage,
            'updated_at'     => current_time( 'mysql' ),
        ], [ 'id' => $project_id ] );

        if ( $result !== false ) {
            // Record in deadlines table
            $this->core->database->insert( 'el_es_deadlines', [
                'project_id' => $project_id,
                'stage'      => $project->current_stage,
                'deadline'   => $deadline_datetime,
                'set_by'     => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ] );

            EL_AJAX_Handler::success( null, __( 'Deadline set!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to set deadline.', 'el-core' ) );
        }
    }

    public function handle_extend_deadline( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        $new_deadline = sanitize_text_field( $data['new_deadline'] ?? '' );

        if ( ! $project_id || ! $new_deadline ) {
            EL_AJAX_Handler::error( __( 'Invalid parameters.', 'el-core' ) );
            return;
        }

        $project = $this->get_project( $project_id );
        if ( ! $project ) {
            EL_AJAX_Handler::error( __( 'Project not found.', 'el-core' ), 404 );
            return;
        }

        $new_deadline_datetime = date( 'Y-m-d 23:59:59', strtotime( $new_deadline ) );
        
        $result = $this->core->database->update( 'el_es_projects', [
            'deadline'   => $new_deadline_datetime,
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $project_id ] );

        if ( $result !== false ) {
            // Update most recent deadline record
            $deadlines = $this->core->database->query( 'el_es_deadlines', [
                'project_id' => $project_id,
                'stage'      => $project->current_stage,
            ], [
                'orderby' => 'created_at',
                'order'   => 'DESC',
                'limit'   => 1,
            ] );

            if ( ! empty( $deadlines ) ) {
                $this->core->database->update( 'el_es_deadlines', [
                    'deadline'    => $new_deadline_datetime,
                    'extended_at' => current_time( 'mysql' ),
                ], [ 'id' => $deadlines[0]->id ] );
            }

            EL_AJAX_Handler::success( null, __( 'Deadline extended!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to extend deadline.', 'el-core' ) );
        }
    }

    public function handle_clear_flag( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );

        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        $result = $this->core->database->update( 'el_es_projects', [
            'flagged_at'  => null,
            'flag_reason' => '',
            'updated_at'  => current_time( 'mysql' ),
        ], [ 'id' => $project_id ] );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Flag cleared!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to clear flag.', 'el-core' ) );
        }
    }
    
    // ═══════════════════════════════════════════
    // USER SWITCHING
    // ═══════════════════════════════════════════
    
    /**
     * Allow admins to switch to another user's account for testing
     */
    public function handle_switch_to_user(): void {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'switch_to_user' ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to switch users.', 'el-core' ) );
        }
        
        $user_id = absint( $_GET['user_id'] ?? 0 );
        $nonce   = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
        
        if ( ! $user_id || ! wp_verify_nonce( $nonce, 'switch_to_user_' . $user_id ) ) {
            wp_die( __( 'Invalid request.', 'el-core' ) );
        }
        
        $user = get_user_by( 'id', $user_id );
        if ( ! $user ) {
            wp_die( __( 'User not found.', 'el-core' ) );
        }
        
        // Store the original admin user ID so we can switch back
        $current_user_id = get_current_user_id();
        update_user_meta( $user_id, '_switched_from_user', $current_user_id );
        update_user_meta( $current_user_id, '_switched_to_user', $user_id );
        
        // Log in as the target user
        wp_clear_auth_cookie();
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );
        
        // Redirect to home page so they see the site as this user
        wp_redirect( home_url( '/' ) );
        exit;
    }
}
