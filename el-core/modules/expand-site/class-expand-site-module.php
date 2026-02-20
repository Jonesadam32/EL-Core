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
    private EL_Core $core;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->core = EL_Core::instance();
        $this->init_hooks();
    }

    private function init_hooks(): void {
        // AJAX handlers (authenticated users only — clients will be logged in)
        add_action( 'el_core_ajax_es_create_project',      [ $this, 'handle_create_project' ] );
        add_action( 'el_core_ajax_es_update_project',      [ $this, 'handle_update_project' ] );
        add_action( 'el_core_ajax_es_advance_stage',       [ $this, 'handle_advance_stage' ] );
        add_action( 'el_core_ajax_es_submit_feedback',     [ $this, 'handle_submit_feedback' ] );
        add_action( 'el_core_ajax_es_add_deliverable',     [ $this, 'handle_add_deliverable' ] );
        add_action( 'el_core_ajax_es_review_deliverable',  [ $this, 'handle_review_deliverable' ] );
        add_action( 'el_core_ajax_es_add_page',            [ $this, 'handle_add_page' ] );
        add_action( 'el_core_ajax_es_update_page',         [ $this, 'handle_update_page' ] );
        add_action( 'el_core_ajax_es_update_feedback',     [ $this, 'handle_update_feedback' ] );
        add_action( 'el_core_ajax_es_client_review_page',  [ $this, 'handle_client_review_page' ] );

        add_action( 'admin_menu', [ $this, 'register_admin_pages' ] );
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
            'manage_expand_site',
            'el-expand-site',
            [ $this, 'render_admin_page' ]
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

    // ═══════════════════════════════════════════
    // ASSET ENQUEUING
    // ═══════════════════════════════════════════

    public function enqueue_frontend_assets(): void {
        global $post;
        if ( ! $post ) return;

        $shortcodes = [ 'el_project_portal', 'el_project_status', 'el_page_review', 'el_feedback_form' ];
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
        if ( strpos( $hook, 'el-expand-site' ) === false ) return;

        wp_enqueue_style(
            'el-expand-site-admin',
            EL_CORE_URL . 'modules/expand-site/assets/css/expand-site.css',
            [ 'el-admin' ],
            EL_CORE_VERSION
        );
        wp_enqueue_script(
            'el-expand-site-admin',
            EL_CORE_URL . 'modules/expand-site/assets/js/expand-site.js',
            [ 'jquery' ],
            EL_CORE_VERSION,
            true
        );
    }

    // ═══════════════════════════════════════════
    // STAGE DEFINITIONS
    // ═══════════════════════════════════════════

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

    public static function get_stage_name( int $stage ): string {
        return self::STAGES[ $stage ]['name'] ?? 'Unknown';
    }

    public static function get_stage_badge_variant( int $stage ): string {
        return match ( true ) {
            $stage <= 2 => 'info',
            $stage === 3 => 'warning',
            $stage <= 5 => 'primary',
            $stage === 6 => 'default',
            $stage === 7 => 'warning',
            $stage === 8 => 'success',
            default      => 'default',
        };
    }

    public static function get_status_badge_variant( string $status ): string {
        return match ( $status ) {
            'active'    => 'success',
            'paused'    => 'warning',
            'completed' => 'info',
            'cancelled' => 'error',
            default     => 'default',
        };
    }

    // ═══════════════════════════════════════════
    // QUERIES
    // ═══════════════════════════════════════════

    public function get_all_projects( array $where = [], array $options = [] ): array {
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

    public function advance_stage( int $project_id, string $notes = '' ): bool {
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

        // Lock scope when entering Stage 4
        if ( $new_stage === 4 && ! $project->scope_locked_at ) {
            $update_data['scope_locked_at'] = current_time( 'mysql' );
        }

        // Complete project when stage 8 is approved
        // (handled separately via complete_project if needed)

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
        $result = $this->advance_stage( $project_id, $notes );

        if ( $result ) {
            $project = $this->get_project( $project_id );
            EL_AJAX_Handler::success( [
                'new_stage'      => $project->current_stage,
                'new_stage_name' => self::get_stage_name( $project->current_stage ),
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
}
