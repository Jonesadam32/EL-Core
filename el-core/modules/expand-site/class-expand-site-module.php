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
        $this->seed_default_settings();
        $this->migrate_projects_to_organizations();
        $this->init_hooks();
    }

    private function seed_default_settings(): void {
        if ( ! $this->core || ! $this->core->settings ) {
            return;
        }

        $existing_pt = $this->core->settings->get( 'mod_expand-site', 'default_payment_terms', '' );
        if ( empty( $existing_pt ) ) {
            $this->core->settings->set( 'mod_expand-site', 'default_payment_terms', implode( "\n\n", [
                "Payment Schedule\n\nThis project will be invoiced in two payments:",
                "First Payment (25%) is due upon client approval of the wireframes. Approval is recorded when the authorized Decision Maker formally accepts the wireframe deliverable through the project portal. An invoice will be issued automatically at that time.",
                "Final Payment (75%) is due upon delivery and client review of the completed website. An invoice will be issued when the project reaches final delivery.",
                "Accepted Payment Methods\n\nPayment may be made by check or ACH bank transfer. Invoices are due within 30 days of issuance unless a separate payment schedule has been established with your organization's procurement department.",
                "Late Payments\n\nInvoices not paid within 30 days of the due date are subject to a 1.5% monthly finance charge. Expanded Learning Solutions reserves the right to pause work on any project with an outstanding balance of 30 days or more.",
                "Project Inactivity\n\nIf a project is delayed due to lack of client response or action for 90 or more consecutive days, Expanded Learning Solutions reserves the right to formally close the project. In this case, an invoice will be issued for all work completed to date, calculated as a proportional share of the total project investment. The project may be reopened by mutual agreement, which may require a new proposal depending on the scope of time elapsed.",
            ] ) );
        }

        $existing_tc = $this->core->settings->get( 'mod_expand-site', 'default_terms_conditions', '' );
        if ( empty( $existing_tc ) ) {
            $this->core->settings->set( 'mod_expand-site', 'default_terms_conditions', implode( "\n\n", [
                "1. Scope of Work\nThis proposal defines the agreed-upon scope of work. Requests that fall outside this scope will be discussed and quoted separately before any additional work begins.",
                "2. Client Responsibilities\nThe client agrees to provide timely feedback, required content (text, images, logos, documents), and decisions necessary to keep the project on schedule. Delays caused by the client may result in revised project timelines.",
                "3. Intellectual Property\nUpon receipt of final payment, the client receives full ownership of all custom deliverables created specifically for this project, including website pages, written content, and custom graphics. Expanded Learning Solutions retains ownership of any proprietary tools, frameworks, code libraries, or platform infrastructure used to build the project. Third-party tools, plugins, or licensed assets remain subject to their respective license terms.",
                "4. Confidentiality\nBoth parties agree to keep confidential any proprietary information, data, or materials shared during the course of this project. This obligation survives the completion or termination of the agreement.",
                "5. Platform & Hosting\nUnless otherwise specified in the scope, ongoing hosting, maintenance, and platform licensing are not included in this proposal. A separate service agreement will be provided for any ongoing services.",
                "6. Limitation of Liability\nExpanded Learning Solutions' total liability under this agreement shall not exceed the total amount paid by the client for the project. ELS is not liable for indirect, incidental, or consequential damages of any kind.",
                "7. Termination\nEither party may terminate this agreement with 14 days written notice. Upon termination, the client is responsible for payment of all work completed to the date of termination, invoiced as a proportional share of the total project investment.",
                "8. Governing Law\nThis agreement is governed by the laws of the State of Georgia. Any disputes shall be resolved through good-faith negotiation, and if necessary, binding arbitration.",
                "9. Entire Agreement\nThis proposal, once accepted, constitutes the entire agreement between the parties and supersedes all prior discussions or representations.",
            ] ) );
        }
    }

    /**
     * One-time migration: create organizations from existing project client_name values.
     * Runs once after the v5 schema migration adds organization_id to el_es_projects.
     */
    private function migrate_projects_to_organizations(): void {
        if ( ! $this->core || ! $this->core->database || ! $this->core->organizations ) {
            return;
        }
        if ( ! is_admin() ) {
            return;
        }

        $migration_done = get_option( 'el_es_org_migration_done', false );
        if ( $migration_done ) {
            return;
        }

        global $wpdb;
        $projects_table = $this->core->database->get_table_name( 'el_es_projects' );

        // Check if organization_id column exists yet
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$projects_table} LIKE 'organization_id'" );
        if ( empty( $col ) ) {
            return;
        }

        $projects = $wpdb->get_results(
            "SELECT id, client_name FROM {$projects_table} WHERE organization_id = 0 AND client_name != ''"
        );

        $org_table = $this->core->database->get_table_name( 'el_organizations' );

        foreach ( $projects as $project ) {
            $name = trim( $project->client_name );
            if ( empty( $name ) ) continue;

            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$org_table} WHERE name = %s LIMIT 1",
                $name
            ) );

            if ( $existing ) {
                $org_id = (int) $existing;
            } else {
                $org_id = $this->core->organizations->create_organization( [
                    'name'   => $name,
                    'type'   => 'nonprofit',
                    'status' => 'active',
                ] );
            }

            if ( $org_id ) {
                $wpdb->update( $projects_table, [ 'organization_id' => $org_id ], [ 'id' => $project->id ] );
            }
        }

        update_option( 'el_es_org_migration_done', true );
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
        
        // Discovery transcript and definition
        add_action( 'el_core_ajax_es_process_transcript',       [ $this, 'handle_process_transcript' ] );
        add_action( 'el_core_ajax_es_save_definition',          [ $this, 'handle_save_definition' ] );
        add_action( 'el_core_ajax_es_lock_definition',          [ $this, 'handle_lock_definition' ] );

        // Definition consensus review
        add_action( 'el_core_ajax_es_send_definition_review',   [ $this, 'handle_send_definition_review' ] );
        add_action( 'el_core_ajax_es_get_definition_review',    [ $this, 'handle_get_definition_review' ] );
        add_action( 'el_core_ajax_es_post_definition_comment',  [ $this, 'handle_post_definition_comment' ] );
        add_action( 'el_core_ajax_es_field_verdict',            [ $this, 'handle_field_verdict' ] );
        add_action( 'el_core_ajax_es_dm_decision',              [ $this, 'handle_dm_decision' ] );
        add_action( 'el_core_ajax_es_client_edit_definition_field', [ $this, 'handle_client_edit_definition_field' ] );
        // Guest (portal) access for stakeholders
        add_action( 'el_core_ajax_nopriv_es_get_definition_review',   [ $this, 'handle_get_definition_review' ] );
        add_action( 'el_core_ajax_nopriv_es_post_definition_comment', [ $this, 'handle_post_definition_comment' ] );
        add_action( 'el_core_ajax_nopriv_es_field_verdict',           [ $this, 'handle_field_verdict' ] );
        add_action( 'el_core_ajax_nopriv_es_dm_decision',             [ $this, 'handle_dm_decision' ] );
        add_action( 'el_core_ajax_nopriv_es_client_edit_definition_field', [ $this, 'handle_client_edit_definition_field' ] );
        
        // Proposals
        add_action( 'el_core_ajax_es_create_proposal',       [ $this, 'handle_create_proposal' ] );
        add_action( 'el_core_ajax_es_save_proposal',         [ $this, 'handle_save_proposal' ] );
        add_action( 'el_core_ajax_es_generate_proposal_ai',  [ $this, 'handle_generate_proposal_ai' ] );
        add_action( 'el_core_ajax_es_send_proposal',         [ $this, 'handle_send_proposal' ] );
        add_action( 'el_core_ajax_es_delete_proposal',       [ $this, 'handle_delete_proposal' ] );
        add_action( 'el_core_ajax_es_accept_proposal',       [ $this, 'handle_accept_proposal' ] );
        add_action( 'el_core_ajax_es_decline_proposal',      [ $this, 'handle_decline_proposal' ] );
        add_action( 'el_core_ajax_nopriv_es_accept_proposal', [ $this, 'handle_accept_proposal' ] );
        add_action( 'el_core_ajax_nopriv_es_decline_proposal', [ $this, 'handle_decline_proposal' ] );
        
        // Template library (admin only)
        add_action( 'el_core_ajax_es_save_template',     [ $this, 'handle_save_template' ] );
        add_action( 'el_core_ajax_es_delete_template',   [ $this, 'handle_delete_template' ] );
        add_action( 'el_core_ajax_es_reorder_templates', [ $this, 'handle_reorder_templates' ] );

        // Review system — portal (stakeholders)
        add_action( 'el_core_ajax_es_get_mood_board',       [ $this, 'handle_get_mood_board' ] );
        add_action( 'el_core_ajax_es_save_template_vote',   [ $this, 'handle_save_template_vote' ] );
        add_action( 'el_core_ajax_es_get_review_status',    [ $this, 'handle_get_review_status' ] );
        add_action( 'el_core_ajax_es_get_review_results',   [ $this, 'handle_get_review_results' ] );
        add_action( 'el_core_ajax_es_close_review',         [ $this, 'handle_close_review' ] );

        // Review system — admin
        add_action( 'el_core_ajax_es_create_review_item',   [ $this, 'handle_create_review_item' ] );
        add_action( 'el_core_ajax_es_set_review_deadline',  [ $this, 'handle_set_review_deadline' ] );

        // User switching
        add_action( 'admin_init', [ $this, 'handle_switch_to_user' ] );
        add_action( 'admin_init', [ $this, 'handle_switch_back_user' ] );
        add_action( 'admin_bar_menu', [ $this, 'add_switch_back_admin_bar_button' ], 100 );

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
            __( 'Template Library', 'el-core' ),
            __( 'Template Library', 'el-core' ),
            'manage_options',
            'el-core-template-library',
            [ $this, 'render_template_library_page' ]
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

    public function render_template_library_page(): void {
        require_once __DIR__ . '/admin/views/template-library.php';
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
        $our_pages = [ 'el-core-projects', 'el-core-template-library' ];
        $on_our_page = false;
        foreach ( $our_pages as $page ) {
            if ( strpos( $hook, $page ) !== false ) {
                $on_our_page = true;
                break;
            }
        }
        if ( ! $on_our_page ) return;

        wp_enqueue_style(
            'el-expand-site-admin',
            EL_CORE_URL . 'modules/expand-site/assets/css/expand-site.css',
            [ 'el-admin' ],
            EL_CORE_VERSION
        );
        wp_enqueue_media();

        wp_enqueue_script(
            'el-expand-site-admin',
            EL_CORE_URL . 'modules/expand-site/assets/js/expand-site-admin.js',
            [ 'jquery', 'media-upload', 'thickbox' ],
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

        // Check legacy decision_maker_id column on the project
        if ( (int) $project->decision_maker_id === $user_id && el_core_can( 'es_decision_maker' ) ) {
            return true;
        }

        // Check stakeholders table for decision_maker role row
        $rows = $this->core->database->query( 'el_es_stakeholders', [
            'project_id' => $project_id,
            'user_id'    => $user_id,
            'role'       => 'decision_maker',
        ] );

        return ! empty( $rows );
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

    public function get_project_definition( int $project_id ): ?object {
        $results = $this->core->database->query( 'el_es_project_definition', [
            'project_id' => $project_id,
        ], [
            'limit' => 1,
        ] );
        return ! empty( $results ) ? $results[0] : null;
    }

    public function get_proposals( int $project_id ): array {
        return $this->core->database->query( 'el_es_proposals', [
            'project_id' => $project_id,
        ], [
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ] );
    }

    public function get_proposal( int $id ): ?object {
        return $this->core->database->get( 'el_es_proposals', $id );
    }

    public function get_accepted_proposal( int $project_id ): ?object {
        $results = $this->core->database->query( 'el_es_proposals', [
            'project_id' => $project_id,
            'status'     => 'accepted',
        ], [
            'limit' => 1,
        ] );
        return ! empty( $results ) ? $results[0] : null;
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
        $db  = $this->core->database;
        $org = $this->core->organizations;

        $organization_id = absint( $data['organization_id'] ?? 0 );
        $client_name     = sanitize_text_field( $data['client_name'] ?? '' );

        // Resolve organization: look up existing or create new
        if ( $organization_id > 0 ) {
            $org_record = $org->get_organization( $organization_id );
            if ( $org_record ) {
                $client_name = $org_record->name;
            }
        } elseif ( ! empty( $client_name ) ) {
            $search = $org->search_organizations( $client_name );
            $exact  = null;
            foreach ( $search as $s ) {
                if ( strtolower( $s->name ) === strtolower( $client_name ) ) {
                    $exact = $s;
                    break;
                }
            }

            if ( $exact ) {
                $organization_id = (int) $exact->id;
            } else {
                $organization_id = $org->create_organization( [
                    'name'   => $client_name,
                    'type'   => 'nonprofit',
                    'status' => 'active',
                ] );
                if ( ! $organization_id ) {
                    $organization_id = 0;
                }
            }
        }

        $project_id = $db->insert( 'el_es_projects', [
            'name'              => sanitize_text_field( $data['name'] ?? '' ),
            'client_name'       => $client_name,
            'client_user_id'    => absint( $data['client_user_id'] ?? 0 ),
            'organization_id'   => $organization_id,
            'current_stage'     => 1,
            'status'            => 'active',
            'budget_range_low'  => floatval( $data['budget_range_low'] ?? 0 ),
            'budget_range_high' => floatval( $data['budget_range_high'] ?? 0 ),
            'notes'             => wp_kses_post( $data['notes'] ?? '' ),
            'created_by'        => get_current_user_id(),
            'created_at'        => current_time( 'mysql' ),
            'updated_at'        => current_time( 'mysql' ),
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

            // Auto-add primary contact as Decision Maker stakeholder
            if ( $organization_id > 0 ) {
                $primary = $org->get_primary_contact( $organization_id );
                if ( $primary && $primary->user_id ) {
                    $this->add_stakeholder( $project_id, (int) $primary->user_id, 'decision_maker' );
                }
            }
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
        $db->delete( 'el_es_proposals', [ 'project_id' => $project_id ] );
        
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
    // DISCOVERY TRANSCRIPT & PROJECT DEFINITION
    // ═══════════════════════════════════════════
    
    public function handle_process_transcript( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        // Read transcript directly from $_POST to avoid sanitize_text_field() stripping newlines/slashes
        $transcript = sanitize_textarea_field( wp_unslash( $_POST['transcript'] ?? '' ) );

        if ( ! $project_id || ! $transcript ) {
            EL_AJAX_Handler::error( __( 'Project ID and transcript are required.', 'el-core' ) );
            return;
        }

        $project = $this->get_project( $project_id );
        if ( ! $project ) {
            EL_AJAX_Handler::error( __( 'Project not found.', 'el-core' ), 404 );
            return;
        }

        // Check if AI is configured
        if ( ! $this->core->ai->is_configured() ) {
            EL_AJAX_Handler::error( __( 'AI is not configured. Go to EL Core → Brand → AI Settings to add your API key.', 'el-core' ) );
            return;
        }

        // Save transcript to project
        $this->core->database->update( 'el_es_projects', [
            'discovery_transcript'    => $transcript,
            'discovery_extracted_at'  => current_time( 'mysql' ),
            'updated_at'              => current_time( 'mysql' ),
        ], [ 'id' => $project_id ] );

        // Build AI prompt to extract project requirements
        $prompt = "You are a project manager analyzing a discovery call transcript. Extract the following information from the transcript and return it as a JSON object. If information is not found, use empty string or null.\n\n";
        $prompt .= "Required fields:\n";
        $prompt .= "- site_description: A brief overview of what this website will be (1-2 sentences)\n";
        $prompt .= "- primary_goal: The main objective this website should achieve (1 sentence)\n";
        $prompt .= "- secondary_goals: Additional objectives as a comma-separated list or bullet points\n";
        $prompt .= "- target_customers: Who is this site designed to reach? (description of the audience)\n";
        $prompt .= "- user_types: Different types of users and their roles (comma-separated or JSON array)\n";
        $prompt .= "- site_type: Category of website (e.g., 'E-commerce', 'Educational Portal', 'Corporate Website', 'Blog', etc.)\n\n";
        $prompt .= "Transcript:\n{$transcript}\n\n";
        $prompt .= "Return ONLY valid JSON with these exact keys: site_description, primary_goal, secondary_goals, target_customers, user_types, site_type";

        // Call AI API (uses configured provider and model from settings)
        $ai_response = el_core_ai_complete( $prompt, '', [
            'max_tokens'  => 1000,
        ] );

        // Check if AI call succeeded
        if ( ! $ai_response['success'] ) {
            $error_msg = $ai_response['error'] ?? 'Unknown AI error';
            EL_AJAX_Handler::error( __( 'AI processing failed: ', 'el-core' ) . $error_msg );
            return;
        }

        $ai_content = $ai_response['content'] ?? '';
        if ( empty( $ai_content ) ) {
            EL_AJAX_Handler::error( __( 'AI returned empty response. Please try again or enter data manually.', 'el-core' ) );
            return;
        }

        // Extract JSON from AI response (handles markdown code blocks and extra text)
        $json_string = $this->extract_json_from_ai_response( $ai_content );
        if ( ! $json_string ) {
            error_log( 'EL Expand Site: Could not extract JSON from AI response: ' . $ai_content );
            EL_AJAX_Handler::error( __( 'AI response format was unexpected. Please try again or enter data manually.', 'el-core' ) );
            return;
        }

        // Parse JSON response
        $extracted = json_decode( $json_string, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'EL Expand Site: Failed to parse extracted JSON: ' . $json_string );
            error_log( 'EL Expand Site: Full AI response was: ' . $ai_content );
            EL_AJAX_Handler::error( __( 'Failed to parse AI response. Please try again or enter data manually.', 'el-core' ) );
            return;
        }

        // Ensure user_types is a string (convert array if needed)
        if ( isset( $extracted['user_types'] ) && is_array( $extracted['user_types'] ) ) {
            $extracted['user_types'] = implode( ', ', $extracted['user_types'] );
        }

        // Save or update project definition
        $definition = $this->get_project_definition( $project_id );
        
        $definition_data = [
            'site_description'  => sanitize_textarea_field( $extracted['site_description'] ?? '' ),
            'primary_goal'      => sanitize_textarea_field( $extracted['primary_goal'] ?? '' ),
            'secondary_goals'   => sanitize_textarea_field( $extracted['secondary_goals'] ?? '' ),
            'target_customers'  => sanitize_textarea_field( $extracted['target_customers'] ?? '' ),
            'user_types'        => sanitize_textarea_field( $extracted['user_types'] ?? '' ),
            'site_type'         => sanitize_text_field( $extracted['site_type'] ?? '' ),
            'updated_at'        => current_time( 'mysql' ),
        ];

        if ( $definition ) {
            // Update existing definition
            $this->core->database->update( 'el_es_project_definition', $definition_data, [
                'project_id' => $project_id,
            ] );
        } else {
            // Create new definition
            $definition_data['project_id'] = $project_id;
            $definition_data['created_at'] = current_time( 'mysql' );
            $this->core->database->insert( 'el_es_project_definition', $definition_data );
        }

        EL_AJAX_Handler::success( [
            'definition' => $definition_data,
        ], __( 'Transcript processed successfully!', 'el-core' ) );
    }

    public function handle_save_definition( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );

        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Project ID is required.', 'el-core' ) );
            return;
        }

        $project = $this->get_project( $project_id );
        if ( ! $project ) {
            EL_AJAX_Handler::error( __( 'Project not found.', 'el-core' ), 404 );
            return;
        }

        // Check if definition is locked
        $definition = $this->get_project_definition( $project_id );
        if ( $definition && $definition->locked_at ) {
            EL_AJAX_Handler::error( __( 'Definition is locked and cannot be edited.', 'el-core' ), 403 );
            return;
        }

        // Read textarea fields directly from $_POST with wp_unslash() to prevent double-escaping
        $definition_data = [
            'site_description'  => sanitize_textarea_field( wp_unslash( $_POST['site_description'] ?? '' ) ),
            'primary_goal'      => sanitize_textarea_field( wp_unslash( $_POST['primary_goal'] ?? '' ) ),
            'secondary_goals'   => sanitize_textarea_field( wp_unslash( $_POST['secondary_goals'] ?? '' ) ),
            'target_customers'  => sanitize_textarea_field( wp_unslash( $_POST['target_customers'] ?? '' ) ),
            'user_types'        => sanitize_textarea_field( wp_unslash( $_POST['user_types'] ?? '' ) ),
            'site_type'         => substr( sanitize_text_field( wp_unslash( $_POST['site_type'] ?? '' ) ), 0, 50 ),
            'updated_at'        => current_time( 'mysql' ),
        ];

        if ( $definition ) {
            // Update existing definition
            $result = $this->core->database->update( 'el_es_project_definition', $definition_data, [
                'project_id' => $project_id,
            ] );
        } else {
            // Create new definition
            $definition_data['project_id'] = $project_id;
            $definition_data['created_at'] = current_time( 'mysql' );
            $result = $this->core->database->insert( 'el_es_project_definition', $definition_data );
        }

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Definition saved!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to save definition.', 'el-core' ) );
        }
    }

    public function handle_lock_definition( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );

        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Project ID is required.', 'el-core' ) );
            return;
        }

        $definition = $this->get_project_definition( $project_id );
        if ( ! $definition ) {
            EL_AJAX_Handler::error( __( 'No definition found to lock.', 'el-core' ), 404 );
            return;
        }

        if ( $definition->locked_at ) {
            EL_AJAX_Handler::error( __( 'Definition is already locked.', 'el-core' ) );
            return;
        }

        $result = $this->core->database->update( 'el_es_project_definition', [
            'locked_at'      => current_time( 'mysql' ),
            'locked_by'     => get_current_user_id(),
            'review_status' => 'locked',
        ], [ 'project_id' => $project_id ] );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Definition locked successfully!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to lock definition.', 'el-core' ) );
        }
    }

    // ═══════════════════════════════════════════
    // DEFINITION CONSENSUS REVIEW
    // ═══════════════════════════════════════════

    /**
     * Get the active (most recent open) review for a project definition.
     */
    public function get_active_definition_review( int $project_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_definition_reviews';
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE project_id = %d AND status = 'open' ORDER BY round DESC LIMIT 1",
            $project_id
        ) );
    }

    /**
     * Get all reviews for a project definition, ordered by round.
     */
    public function get_definition_reviews( int $project_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_definition_reviews';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE project_id = %d ORDER BY round ASC",
            $project_id
        ) ) ?: [];
    }

    /**
     * Get all top-level comments for a review, with their replies nested.
     * Returns array keyed by field_key, each value is array of comment objects with ->replies.
     */
    public function get_definition_comments( int $review_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_definition_comments';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, u.display_name, u.user_email FROM {$table} c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id
             WHERE c.review_id = %d ORDER BY c.created_at ASC",
            $review_id
        ) ) ?: [];

        // Build tree: top-level keyed by field_key, replies nested under parent
        $by_id    = [];
        $by_field = [];
        foreach ( $rows as $row ) {
            $row->replies = [];
            $by_id[ $row->id ] = $row;
        }
        foreach ( $by_id as $id => $row ) {
            if ( $row->parent_id && isset( $by_id[ $row->parent_id ] ) ) {
                $by_id[ $row->parent_id ]->replies[] = $row;
            } else {
                $by_field[ $row->field_key ][] = $row;
            }
        }
        return $by_field;
    }

    /**
     * Get per-field verdict tallies for a review.
     * Returns array keyed by field_key => ['approved'=>n, 'needs_revision'=>n, 'total'=>n]
     */
    public function get_definition_verdicts( int $review_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_definition_comments';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT field_key, verdict, COUNT(*) as cnt FROM {$table}
             WHERE review_id = %d AND parent_id = 0 AND verdict != ''
             GROUP BY field_key, verdict",
            $review_id
        ) ) ?: [];
        $out = [];
        foreach ( $rows as $r ) {
            if ( ! isset( $out[ $r->field_key ] ) ) {
                $out[ $r->field_key ] = [ 'approved' => 0, 'needs_revision' => 0, 'total' => 0 ];
            }
            $out[ $r->field_key ][ $r->verdict ] = (int) $r->cnt;
            $out[ $r->field_key ]['total'] += (int) $r->cnt;
        }
        return $out;
    }

    /**
     * AJAX: Admin sends definition for stakeholder review.
     * Creates a new review round, sets definition status to pending_review.
     */
    public function handle_send_definition_review( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        $deadline   = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );

        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Project ID required.', 'el-core' ) );
            return;
        }

        $definition = $this->get_project_definition( $project_id );
        if ( ! $definition ) {
            EL_AJAX_Handler::error( __( 'Save the definition before sending for review.', 'el-core' ) );
            return;
        }
        if ( $definition->locked_at ) {
            EL_AJAX_Handler::error( __( 'Definition is locked and cannot be sent for review.', 'el-core' ) );
            return;
        }

        // Close any existing open review
        global $wpdb;
        $reviews_table = $wpdb->prefix . 'el_es_definition_reviews';
        $wpdb->update( $reviews_table, [ 'status' => 'superseded' ], [ 'project_id' => $project_id, 'status' => 'open' ] );

        // Determine next round number
        $last_round = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(round) FROM {$reviews_table} WHERE project_id = %d",
            $project_id
        ) );
        $round = $last_round + 1;

        // Create new review
        $deadline_dt = $deadline ? date( 'Y-m-d 23:59:59', strtotime( $deadline ) ) : null;
        $wpdb->insert( $reviews_table, [
            'project_id' => $project_id,
            'round'      => $round,
            'sent_by'    => get_current_user_id(),
            'sent_at'    => current_time( 'mysql' ),
            'deadline'   => $deadline_dt,
            'status'     => 'open',
        ] );
        $review_id = $wpdb->insert_id;

        // Update definition status
        $this->core->database->update( 'el_es_project_definition', [
            'review_status' => 'pending_review',
            'updated_at'    => current_time( 'mysql' ),
        ], [ 'project_id' => $project_id ] );

        EL_AJAX_Handler::success( [
            'review_id' => $review_id,
            'round'     => $round,
        ], sprintf( __( 'Sent for review — Round %d. Stakeholders can now comment.', 'el-core' ), $round ) );
    }

    /**
     * AJAX: Get full review data for the portal (definition fields + comments + verdicts + timer).
     * Accessible to logged-in stakeholders (nopriv handled separately).
     */
    public function handle_get_definition_review( array $data ): void {
        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Project ID required.', 'el-core' ) );
            return;
        }

        $definition = $this->get_project_definition( $project_id );
        if ( ! $definition ) {
            EL_AJAX_Handler::error( __( 'No definition found.', 'el-core' ), 404 );
            return;
        }

        $review   = $this->get_active_definition_review( $project_id );
        $comments = $review ? $this->get_definition_comments( (int) $review->id ) : [];
        $verdicts = $review ? $this->get_definition_verdicts( (int) $review->id ) : [];

        // Deadline info
        $deadline_ts      = $review && $review->deadline ? strtotime( $review->deadline ) : null;
        $deadline_passed  = $deadline_ts && $deadline_ts < time();

        // Current user's existing verdicts per field
        $user_id       = get_current_user_id();
        $user_verdicts = [];
        if ( $review ) {
            global $wpdb;
            $ct = $wpdb->prefix . 'el_es_definition_comments';
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT field_key, verdict FROM {$ct} WHERE review_id=%d AND user_id=%d AND parent_id=0 AND verdict!=''",
                $review->id, $user_id
            ) ) ?: [];
            foreach ( $rows as $r ) {
                $user_verdicts[ $r->field_key ] = $r->verdict;
            }
        }

        EL_AJAX_Handler::success( [
            'definition'      => [
                'site_description'  => $definition->site_description,
                'primary_goal'      => $definition->primary_goal,
                'secondary_goals'   => $definition->secondary_goals,
                'target_customers'  => $definition->target_customers,
                'user_types'        => $definition->user_types,
                'site_type'         => $definition->site_type,
                'review_status'     => $definition->review_status ?? 'draft',
                'locked_at'         => $definition->locked_at,
            ],
            'review'          => $review,
            'comments'        => $comments,
            'verdicts'        => $verdicts,
            'user_verdicts'   => $user_verdicts,
            'deadline_ts'     => $deadline_ts,
            'deadline_passed' => $deadline_passed,
            'is_dm'           => $this->is_decision_maker( $project_id ),
        ] );
    }

    /**
     * AJAX: Allow a stakeholder/DM to edit a single definition field value during pending_review.
     */
    public function handle_client_edit_definition_field( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'You must be logged in.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        $field_key  = sanitize_key( $data['field_key'] ?? '' );

        $allowed_fields = [ 'site_description', 'primary_goal', 'secondary_goals', 'target_customers', 'user_types', 'site_type' ];
        if ( ! $project_id || ! in_array( $field_key, $allowed_fields, true ) ) {
            EL_AJAX_Handler::error( __( 'Invalid request.', 'el-core' ) );
            return;
        }

        if ( ! $this->can_contribute( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $definition = $this->get_project_definition( $project_id );
        if ( ! $definition ) {
            EL_AJAX_Handler::error( __( 'No definition found.', 'el-core' ), 404 );
            return;
        }

        if ( ( $definition->review_status ?? '' ) !== 'pending_review' ) {
            EL_AJAX_Handler::error( __( 'Definition is not currently in review.', 'el-core' ), 403 );
            return;
        }

        if ( $definition->locked_at ) {
            EL_AJAX_Handler::error( __( 'Definition is locked.', 'el-core' ), 403 );
            return;
        }

        $new_value = $field_key === 'site_type'
            ? substr( sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ), 0, 100 )
            : sanitize_textarea_field( wp_unslash( $_POST['value'] ?? '' ) );

        global $wpdb;
        $table = $wpdb->prefix . 'el_es_project_definition';
        $result = $wpdb->update(
            $table,
            [ $field_key => $new_value, 'updated_at' => current_time( 'mysql' ) ],
            [ 'project_id' => $project_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( [ 'value' => $new_value ], __( 'Field updated.', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update field.', 'el-core' ) );
        }
    }

    /**
     */
    public function handle_post_definition_comment( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'You must be logged in to comment.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        $review_id  = absint( $data['review_id'] ?? 0 );
        $field_key  = sanitize_key( $data['field_key'] ?? '' );
        $parent_id  = absint( $data['parent_id'] ?? 0 );
        $comment    = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

        $allowed_fields = [ 'site_description', 'primary_goal', 'secondary_goals', 'target_customers', 'user_types', 'site_type', 'overall' ];
        if ( ! $project_id || ! $review_id || ! in_array( $field_key, $allowed_fields, true ) || ! $comment ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        // Verify review is still open
        global $wpdb;
        $reviews_table  = $wpdb->prefix . 'el_es_definition_reviews';
        $comments_table = $wpdb->prefix . 'el_es_definition_comments';
        $review = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reviews_table} WHERE id=%d", $review_id ) );
        if ( ! $review || $review->status !== 'open' ) {
            EL_AJAX_Handler::error( __( 'This review is no longer open for comments.', 'el-core' ) );
            return;
        }

        $now = current_time( 'mysql' );
        $wpdb->insert( $comments_table, [
            'review_id'  => $review_id,
            'project_id' => $project_id,
            'field_key'  => $field_key,
            'parent_id'  => $parent_id,
            'user_id'    => get_current_user_id(),
            'comment'    => $comment,
            'verdict'    => '',
            'created_at' => $now,
            'updated_at' => $now,
        ] );
        $comment_id = $wpdb->insert_id;

        $user = get_userdata( get_current_user_id() );
        EL_AJAX_Handler::success( [
            'id'           => $comment_id,
            'comment'      => $comment,
            'display_name' => $user ? $user->display_name : 'Unknown',
            'created_at'   => $now,
            'parent_id'    => $parent_id,
            'field_key'    => $field_key,
        ], __( 'Comment posted.', 'el-core' ) );
    }

    /**
     * AJAX: Contributor sets per-field verdict (approved / needs_revision) + optional comment.
     * One verdict per user per field per review — upsert.
     */
    public function handle_field_verdict( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'You must be logged in.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        $review_id  = absint( $data['review_id'] ?? 0 );
        $field_key  = sanitize_key( $data['field_key'] ?? '' );
        $verdict    = sanitize_text_field( $data['verdict'] ?? '' );
        $comment    = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

        $allowed_verdicts = [ 'approved', 'needs_revision' ];
        $allowed_fields   = [ 'site_description', 'primary_goal', 'secondary_goals', 'target_customers', 'user_types', 'site_type', 'overall' ];

        if ( ! $project_id || ! $review_id || ! in_array( $field_key, $allowed_fields, true ) || ! in_array( $verdict, $allowed_verdicts, true ) ) {
            EL_AJAX_Handler::error( __( 'Invalid verdict data.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $reviews_table  = $wpdb->prefix . 'el_es_definition_reviews';
        $comments_table = $wpdb->prefix . 'el_es_definition_comments';

        $review = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reviews_table} WHERE id=%d", $review_id ) );
        if ( ! $review || $review->status !== 'open' ) {
            EL_AJAX_Handler::error( __( 'This review is closed.', 'el-core' ) );
            return;
        }

        // Block non-DM verdicts after deadline
        if ( $review->deadline && strtotime( $review->deadline ) < time() && ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'The review deadline has passed. Only the Decision Maker can act now.', 'el-core' ) );
            return;
        }

        $user_id = get_current_user_id();
        $now     = current_time( 'mysql' );

        // Check for existing verdict row from this user for this field
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$comments_table} WHERE review_id=%d AND user_id=%d AND field_key=%s AND parent_id=0 AND verdict!=''",
            $review_id, $user_id, $field_key
        ) );

        if ( $existing ) {
            $wpdb->update( $comments_table, [
                'verdict'    => $verdict,
                'comment'    => $comment,
                'updated_at' => $now,
            ], [ 'id' => $existing->id ] );
            $comment_id = $existing->id;
        } else {
            $wpdb->insert( $comments_table, [
                'review_id'  => $review_id,
                'project_id' => $project_id,
                'field_key'  => $field_key,
                'parent_id'  => 0,
                'user_id'    => $user_id,
                'comment'    => $comment,
                'verdict'    => $verdict,
                'created_at' => $now,
                'updated_at' => $now,
            ] );
            $comment_id = $wpdb->insert_id;
        }

        EL_AJAX_Handler::success( [
            'id'        => $comment_id,
            'verdict'   => $verdict,
            'field_key' => $field_key,
        ], __( 'Your feedback has been recorded.', 'el-core' ) );
    }

    /**
     * AJAX: Decision Maker submits final decision on the review.
     * Verdict: accepted | needs_revision
     * If accepted → definition status → approved (admin can then lock).
     * If needs_revision → review closed, admin edits and re-sends.
     */
    public function handle_dm_decision( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'You must be logged in.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        $review_id  = absint( $data['review_id'] ?? 0 );
        $decision   = sanitize_text_field( $data['decision'] ?? '' );
        $note       = sanitize_textarea_field( wp_unslash( $_POST['dm_note'] ?? '' ) );

        if ( ! in_array( $decision, [ 'accepted', 'needs_revision' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Invalid decision.', 'el-core' ) );
            return;
        }
        if ( ! $this->is_decision_maker( $project_id ) && ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Only the Decision Maker can submit the final decision.', 'el-core' ), 403 );
            return;
        }

        global $wpdb;
        $reviews_table = $wpdb->prefix . 'el_es_definition_reviews';
        $review = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reviews_table} WHERE id=%d AND project_id=%d", $review_id, $project_id ) );
        if ( ! $review || $review->status !== 'open' ) {
            EL_AJAX_Handler::error( __( 'Review not found or already closed.', 'el-core' ) );
            return;
        }

        $now = current_time( 'mysql' );

        // Close the review with DM decision
        $wpdb->update( $reviews_table, [
            'status'        => 'closed',
            'dm_decision'   => $decision,
            'dm_note'       => $note,
            'dm_decided_at' => $now,
            'dm_decided_by' => get_current_user_id(),
        ], [ 'id' => $review_id ] );

        // Update definition review_status
        $new_status = $decision === 'accepted' ? 'approved' : 'needs_revision';
        $this->core->database->update( 'el_es_project_definition', [
            'review_status' => $new_status,
            'updated_at'    => $now,
        ], [ 'project_id' => $project_id ] );

        $message = $decision === 'accepted'
            ? __( 'Definition approved! The admin can now lock it and proceed.', 'el-core' )
            : __( 'Sent back for revision. The admin will update and re-send.', 'el-core' );

        EL_AJAX_Handler::success( [ 'new_status' => $new_status ], $message );
    }

    /**
     * Extract JSON from AI response (handles markdown code blocks and extra text)
     * 
     * @param string $response Raw AI response
     * @return string|false JSON string if found, false otherwise
     */
    private function extract_json_from_ai_response( string $response ) {
        // Try to extract from markdown code blocks first
        // Pattern 1: ```json ... ```
        if ( preg_match( '/```json\s*(\{[\s\S]*?\})\s*```/', $response, $matches ) ) {
            return trim( $matches[1] );
        }
        
        // Pattern 2: ``` ... ``` (without json tag)
        if ( preg_match( '/```\s*(\{[\s\S]*?\})\s*```/', $response, $matches ) ) {
            return trim( $matches[1] );
        }
        
        // Pattern 3: Find first { to last } (handles text before/after JSON)
        if ( preg_match( '/(\{[\s\S]*\})/', $response, $matches ) ) {
            return trim( $matches[1] );
        }
        
        // No JSON found
        return false;
    }
    
    // ═══════════════════════════════════════════
    // PROPOSALS
    // ═══════════════════════════════════════════

    public function handle_create_proposal( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        $project = $this->get_project( $project_id );
        if ( ! $project ) {
            EL_AJAX_Handler::error( __( 'Project not found.', 'el-core' ), 404 );
            return;
        }

        // Generate proposal number
        $existing = $this->get_proposals( $project_id );
        $count = count( $existing ) + 1;
        $proposal_number = 'PROP-' . $project_id . '-' . $count;

        // Pre-populate from project definition and stakeholders
        $definition = $this->get_project_definition( $project_id );
        $stakeholders = $this->get_stakeholders( $project_id );
        
        $client_name = $project->client_name;
        $client_email = '';
        foreach ( $stakeholders as $sh ) {
            if ( $sh->role === 'decision_maker' ) {
                $user = get_userdata( $sh->user_id );
                if ( $user ) {
                    $client_name = $user->display_name;
                    $client_email = $user->user_email;
                }
                break;
            }
        }

        $payment_terms    = $this->core->settings->get( 'mod_expand-site', 'default_payment_terms', '' );
        $terms_conditions = $this->core->settings->get( 'mod_expand-site', 'default_terms_conditions', '' );

        $proposal_data = [
            'project_id'             => $project_id,
            'proposal_number'        => $proposal_number,
            'status'                 => 'draft',
            'client_name'            => $client_name,
            'client_organization'    => $project->client_name,
            'client_email'           => $client_email,
            'proposal_title'         => $project->name,
            'scope_description'      => $definition->site_description ?? '',
            'goals_objectives'       => $definition->primary_goal ?? '',
            'budget_low'             => (float) $project->budget_range_low,
            'budget_high'            => (float) $project->budget_range_high,
            'payment_terms'          => $payment_terms,
            'terms_conditions'       => $terms_conditions,
            'created_by'             => get_current_user_id(),
            'created_at'             => current_time( 'mysql' ),
            'updated_at'             => current_time( 'mysql' ),
        ];

        $proposal_id = $this->core->database->insert( 'el_es_proposals', $proposal_data );

        if ( $proposal_id ) {
            EL_AJAX_Handler::success( [ 'proposal_id' => $proposal_id ], __( 'Proposal created!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to create proposal.', 'el-core' ) );
        }
    }

    public function handle_save_proposal( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $proposal_id = absint( $data['proposal_id'] ?? 0 );
        if ( ! $proposal_id ) {
            EL_AJAX_Handler::error( __( 'Invalid proposal ID.', 'el-core' ) );
            return;
        }

        $proposal = $this->get_proposal( $proposal_id );
        if ( ! $proposal ) {
            EL_AJAX_Handler::error( __( 'Proposal not found.', 'el-core' ), 404 );
            return;
        }

        if ( $proposal->status === 'accepted' ) {
            EL_AJAX_Handler::error( __( 'Cannot edit an accepted proposal.', 'el-core' ) );
            return;
        }

        $update = [
            'client_name'            => sanitize_text_field( $data['client_name'] ?? $proposal->client_name ),
            'client_organization'    => sanitize_text_field( $data['client_organization'] ?? $proposal->client_organization ),
            'client_email'           => sanitize_email( $data['client_email'] ?? $proposal->client_email ),
            'proposal_title'         => sanitize_text_field( $data['proposal_title'] ?? $proposal->proposal_title ),
            'project_dates'          => sanitize_text_field( $data['project_dates'] ?? $proposal->project_dates ),
            'project_location'       => sanitize_text_field( $data['project_location'] ?? $proposal->project_location ),
            'scope_description'      => sanitize_textarea_field( $data['scope_description'] ?? $proposal->scope_description ),
            'goals_objectives'       => sanitize_textarea_field( $data['goals_objectives'] ?? $proposal->goals_objectives ),
            'activities_description' => sanitize_textarea_field( $data['activities_description'] ?? $proposal->activities_description ),
            'deliverables_text'      => sanitize_textarea_field( $data['deliverables_text'] ?? $proposal->deliverables_text ),
            'section_situation'      => wp_kses_post( $data['section_situation'] ?? $proposal->section_situation ?? '' ),
            'section_what_we_build'  => wp_kses_post( $data['section_what_we_build'] ?? $proposal->section_what_we_build ?? '' ),
            'section_why_els'        => wp_kses_post( $data['section_why_els'] ?? $proposal->section_why_els ?? '' ),
            'section_investment'     => wp_kses_post( $data['section_investment'] ?? $proposal->section_investment ?? '' ),
            'section_next_steps'     => wp_kses_post( $data['section_next_steps'] ?? $proposal->section_next_steps ?? '' ),
            'budget_low'             => floatval( $data['budget_low'] ?? $proposal->budget_low ),
            'budget_high'            => floatval( $data['budget_high'] ?? $proposal->budget_high ),
            'final_price'            => floatval( $data['final_price'] ?? $proposal->final_price ),
            'payment_terms'          => sanitize_textarea_field( $data['payment_terms'] ?? $proposal->payment_terms ),
            'terms_conditions'       => sanitize_textarea_field( $data['terms_conditions'] ?? $proposal->terms_conditions ),
            'updated_at'             => current_time( 'mysql' ),
        ];

        $result = $this->core->database->update( 'el_es_proposals', $update, [ 'id' => $proposal_id ] );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Proposal saved!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to save proposal.', 'el-core' ) );
        }
    }

    public function handle_generate_proposal_ai( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        $project = $this->get_project( $project_id );
        if ( ! $project ) {
            EL_AJAX_Handler::error( __( 'Project not found.', 'el-core' ), 404 );
            return;
        }

        if ( ! $this->core->ai->is_configured() ) {
            EL_AJAX_Handler::error( __( 'AI is not configured. Go to EL Core settings to add your API key.', 'el-core' ) );
            return;
        }

        $definition = $this->get_project_definition( $project_id );
        if ( ! $definition ) {
            EL_AJAX_Handler::error( __( 'No project definition found. Process a discovery transcript first.', 'el-core' ) );
            return;
        }

        if ( ! $definition->locked_at ) {
            EL_AJAX_Handler::error( __( 'Lock the project definition before generating a proposal.', 'el-core' ) );
            return;
        }

        $transcript = $project->discovery_transcript ?? '';
        $transcript_excerpt = $transcript ? mb_substr( $transcript, 0, 1500 ) : '';
        $client_org = $project->client_name;
        $budget_low = number_format( (float) $project->budget_range_low, 0 );
        $budget_high = number_format( (float) $project->budget_range_high, 0 );

        $prompt  = "You are writing a proposal for a web platform development project for Expanded Learning Solutions LLC.\n";
        $prompt .= "This proposal will be sent directly to a client decision-maker (typically a district administrator or nonprofit executive director) ";
        $prompt .= "who will share it with a board or leadership team. It must read like a custom document written specifically for this client, not a filled-out template.\n\n";
        $prompt .= "Write the proposal as flowing, professional prose. No bullet points. No labeled lists. No headers inside sections. Just paragraphs that a human would write.\n\n";
        $prompt .= "Use the following source data:\n";
        $prompt .= "- Project Name: " . ( $project->name ?? 'N/A' ) . "\n";
        $prompt .= "- Client Organization: " . $client_org . "\n";
        $prompt .= "- Site Description: " . ( $definition->site_description ?? 'N/A' ) . "\n";
        $prompt .= "- Primary Goal: " . ( $definition->primary_goal ?? 'N/A' ) . "\n";
        $prompt .= "- Secondary Goals: " . ( $definition->secondary_goals ?? 'N/A' ) . "\n";
        $prompt .= "- Target Customers: " . ( $definition->target_customers ?? 'N/A' ) . "\n";
        $prompt .= "- User Types: " . ( $definition->user_types ?? 'N/A' ) . "\n";
        $prompt .= "- Site Type: " . ( $definition->site_type ?? 'N/A' ) . "\n";
        $prompt .= "- Budget Range: \${$budget_low} – \${$budget_high}\n";
        if ( $transcript_excerpt ) {
            $prompt .= "- Discovery Transcript: " . $transcript_excerpt . "\n";
        }
        $prompt .= "\nWrite exactly these 5 sections and return them as JSON with these exact keys:\n\n";
        $prompt .= "{\n";
        $prompt .= '  "situation": "2-3 sentences that mirror the client\'s specific problem back to them. Start with their organization name. Reference specific details from the transcript. Do not use generic language. Make them feel understood.",' . "\n\n";
        $prompt .= '  "what_we_are_building": "3-4 sentences describing what the platform will do, organized by who benefits. For each user type identified, write one sentence describing what they will be able to do and what outcome that enables. Focus on capabilities and outcomes, not features or technical details.",' . "\n\n";
        $prompt .= '  "why_els": "2-3 sentences explaining why Expanded Learning Solutions is the right partner. Reference that ELS has built platforms for organizations similar to theirs. Mention that this is a custom platform built on ELS\'s proprietary EL Core system, not off-the-shelf software stitched together.",' . "\n\n";
        $prompt .= '  "investment": "Write this as a single paragraph. State the platform development investment (use the budget range or final price). Then state the annual platform fee (hosting, maintenance, security updates, support) and express it as a monthly equivalent. Then write one sentence comparing this to the cost of a full-time program coordinator salary or an off-the-shelf enterprise platform subscription. Make the ROI feel obvious without being salesy.",' . "\n\n";
        $prompt .= '  "next_steps": "3-4 sentences describing exactly what happens after they accept. Be specific: You will receive a welcome email with a link to your client portal. We will schedule a kickoff call within 5 business days. You will be introduced to your project team and we will review your timeline together. Concrete, not vague."' . "\n";
        $prompt .= "}\n\n";
        $prompt .= "Return only valid JSON. No markdown. No explanation. No preamble.";

        $ai_response = el_core_ai_complete( $prompt, '', [
            'max_tokens' => 2000,
        ] );

        if ( ! $ai_response['success'] ) {
            EL_AJAX_Handler::error( __( 'AI processing failed: ', 'el-core' ) . ( $ai_response['error'] ?? 'Unknown error' ) );
            return;
        }

        $ai_content = $ai_response['content'] ?? '';
        if ( empty( $ai_content ) ) {
            EL_AJAX_Handler::error( __( 'AI returned empty response.', 'el-core' ) );
            return;
        }

        $json_string = $this->extract_json_from_ai_response( $ai_content );
        if ( ! $json_string ) {
            EL_AJAX_Handler::error( __( 'Could not parse AI response. Try again.', 'el-core' ) );
            return;
        }

        $extracted = json_decode( $json_string, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            EL_AJAX_Handler::error( __( 'Failed to parse AI JSON. Try again.', 'el-core' ) );
            return;
        }

        EL_AJAX_Handler::success( [
            'situation'            => $extracted['situation'] ?? '',
            'what_we_are_building' => $extracted['what_we_are_building'] ?? '',
            'why_els'              => $extracted['why_els'] ?? '',
            'investment'           => $extracted['investment'] ?? '',
            'next_steps'           => $extracted['next_steps'] ?? '',
        ], __( 'Proposal content generated!', 'el-core' ) );
    }

    public function handle_send_proposal( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $proposal_id = absint( $data['proposal_id'] ?? 0 );
        if ( ! $proposal_id ) {
            EL_AJAX_Handler::error( __( 'Invalid proposal ID.', 'el-core' ) );
            return;
        }

        $proposal = $this->get_proposal( $proposal_id );
        if ( ! $proposal ) {
            EL_AJAX_Handler::error( __( 'Proposal not found.', 'el-core' ), 404 );
            return;
        }

        $result = $this->core->database->update( 'el_es_proposals', [
            'status'     => 'sent',
            'sent_at'    => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $proposal_id ] );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Proposal marked as sent!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update proposal status.', 'el-core' ) );
        }
    }

    public function handle_delete_proposal( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $proposal_id = absint( $data['proposal_id'] ?? 0 );
        if ( ! $proposal_id ) {
            EL_AJAX_Handler::error( __( 'Invalid proposal ID.', 'el-core' ) );
            return;
        }

        $proposal = $this->get_proposal( $proposal_id );
        if ( ! $proposal ) {
            EL_AJAX_Handler::error( __( 'Proposal not found.', 'el-core' ), 404 );
            return;
        }

        if ( $proposal->status === 'accepted' ) {
            EL_AJAX_Handler::error( __( 'Cannot delete an accepted proposal.', 'el-core' ) );
            return;
        }

        $result = $this->core->database->delete( 'el_es_proposals', [ 'id' => $proposal_id ] );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Proposal deleted!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to delete proposal.', 'el-core' ) );
        }
    }

    public function handle_accept_proposal( array $data ): void {
        $proposal_id = absint( $data['proposal_id'] ?? 0 );
        if ( ! $proposal_id ) {
            EL_AJAX_Handler::error( __( 'Invalid proposal ID.', 'el-core' ) );
            return;
        }

        $proposal = $this->get_proposal( $proposal_id );
        if ( ! $proposal ) {
            EL_AJAX_Handler::error( __( 'Proposal not found.', 'el-core' ), 404 );
            return;
        }

        if ( $proposal->status !== 'sent' ) {
            EL_AJAX_Handler::error( __( 'Only sent proposals can be accepted.', 'el-core' ) );
            return;
        }

        $project_id = (int) $proposal->project_id;
        $user_id = get_current_user_id();

        // Verify user is a decision maker or admin
        if ( ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Only the decision maker can accept proposals.', 'el-core' ), 403 );
            return;
        }

        // Accept the proposal
        $this->core->database->update( 'el_es_proposals', [
            'status'      => 'accepted',
            'accepted_at' => current_time( 'mysql' ),
            'accepted_by' => $user_id,
            'updated_at'  => current_time( 'mysql' ),
        ], [ 'id' => $proposal_id ] );

        // Lock scope and advance to Stage 4 if currently at Stage 3
        $project = $this->get_project( $project_id );
        if ( $project && (int) $project->current_stage === 3 ) {
            $this->advance_stage( $project_id, 'Proposal accepted by client' );
        }

        // TODO: Invoice trigger — Phase 2F-E
        // When wireframe stage is approved by DM, flag Invoice 1 (25%) as due.
        // When project reaches final delivery, flag Invoice 2 (75%) as due.
        // Hooks into stage advancement which is already tracked.

        // Set final price from proposal if provided
        if ( $proposal->final_price > 0 ) {
            $this->core->database->update( 'el_es_projects', [
                'final_price' => $proposal->final_price,
                'updated_at'  => current_time( 'mysql' ),
            ], [ 'id' => $project_id ] );
        }

        EL_AJAX_Handler::success( null, __( 'Proposal accepted! Project advancing to next stage.', 'el-core' ) );
    }

    public function handle_decline_proposal( array $data ): void {
        $proposal_id = absint( $data['proposal_id'] ?? 0 );
        if ( ! $proposal_id ) {
            EL_AJAX_Handler::error( __( 'Invalid proposal ID.', 'el-core' ) );
            return;
        }

        $proposal = $this->get_proposal( $proposal_id );
        if ( ! $proposal ) {
            EL_AJAX_Handler::error( __( 'Proposal not found.', 'el-core' ), 404 );
            return;
        }

        if ( $proposal->status !== 'sent' ) {
            EL_AJAX_Handler::error( __( 'Only sent proposals can be declined.', 'el-core' ) );
            return;
        }

        $project_id = (int) $proposal->project_id;

        if ( ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Only the decision maker can decline proposals.', 'el-core' ), 403 );
            return;
        }

        $this->core->database->update( 'el_es_proposals', [
            'status'      => 'declined',
            'declined_at' => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ], [ 'id' => $proposal_id ] );

        EL_AJAX_Handler::success( null, __( 'Proposal declined.', 'el-core' ) );
    }

    // ═══════════════════════════════════════════
    // TEMPLATE LIBRARY
    // ═══════════════════════════════════════════

    public function get_templates( array $where = [] ): array {
        return $this->core->database->query( 'el_es_templates', $where, [
            'orderby' => 'sort_order',
            'order'   => 'ASC',
        ] );
    }

    public function handle_save_template( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $title    = sanitize_text_field( $data['title'] ?? '' );
        $category = sanitize_text_field( $data['style_category'] ?? '' );

        if ( empty( $title ) ) {
            EL_AJAX_Handler::error( __( 'Title is required.', 'el-core' ) );
            return;
        }
        if ( empty( $category ) ) {
            EL_AJAX_Handler::error( __( 'Style category is required.', 'el-core' ) );
            return;
        }

        $template_id = absint( $data['template_id'] ?? 0 );

        $fields = [
            'title'          => $title,
            'style_category' => $category,
            'description'    => sanitize_textarea_field( $data['description'] ?? '' ),
            'image_url'      => esc_url_raw( $data['image_url'] ?? '' ),
            'is_active'      => absint( $data['is_active'] ?? 0 ),
        ];

        if ( $template_id ) {
            $result = $this->core->database->update( 'el_es_templates', $fields, [ 'id' => $template_id ] );
            if ( $result !== false ) {
                EL_AJAX_Handler::success( [ 'template_id' => $template_id ], __( 'Template updated!', 'el-core' ) );
            } else {
                EL_AJAX_Handler::error( __( 'Failed to update template.', 'el-core' ) );
            }
        } else {
            // Get next sort_order within this category
            global $wpdb;
            $table = $wpdb->prefix . 'el_es_templates';
            $max_sort = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(sort_order) FROM {$table} WHERE style_category = %s",
                $category
            ) );
            $fields['sort_order'] = $max_sort + 1;
            $fields['created_at'] = current_time( 'mysql' );

            $new_id = $this->core->database->insert( 'el_es_templates', $fields );
            if ( $new_id ) {
                EL_AJAX_Handler::success( [ 'template_id' => $new_id ], __( 'Template added!', 'el-core' ) );
            } else {
                EL_AJAX_Handler::error( __( 'Failed to add template.', 'el-core' ) );
            }
        }
    }

    public function handle_delete_template( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $template_id = absint( $data['template_id'] ?? 0 );
        if ( ! $template_id ) {
            EL_AJAX_Handler::error( __( 'Invalid template ID.', 'el-core' ) );
            return;
        }

        $result = $this->core->database->delete( 'el_es_templates', [ 'id' => $template_id ] );
        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Template deleted!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to delete template.', 'el-core' ) );
        }
    }

    public function handle_reorder_templates( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        // order is a JSON array of { id, sort_order } objects
        $order = json_decode( sanitize_text_field( $data['order'] ?? '[]' ), true );
        if ( ! is_array( $order ) ) {
            EL_AJAX_Handler::error( __( 'Invalid order data.', 'el-core' ) );
            return;
        }

        foreach ( $order as $item ) {
            $id         = absint( $item['id'] ?? 0 );
            $sort_order = absint( $item['sort_order'] ?? 0 );
            if ( $id ) {
                $this->core->database->update( 'el_es_templates', [ 'sort_order' => $sort_order ], [ 'id' => $id ] );
            }
        }

        EL_AJAX_Handler::success( null, __( 'Order saved!', 'el-core' ) );
    }

    // ═══════════════════════════════════════════
    // REVIEW SYSTEM — QUERIES
    // ═══════════════════════════════════════════

    public function get_review_items( int $project_id, string $review_type = '' ): array {
        $where = [ 'project_id' => $project_id ];
        if ( $review_type ) {
            $where['review_type'] = $review_type;
        }
        return $this->core->database->query( 'el_es_review_items', $where, [
            'orderby' => 'created_at',
            'order'   => 'DESC',
        ] );
    }

    public function get_review_item( int $id ): ?object {
        return $this->core->database->get( 'el_es_review_items', $id );
    }

    public function get_review_votes( int $review_item_id ): array {
        return $this->core->database->query( 'el_es_review_votes', [
            'review_item_id' => $review_item_id,
        ] );
    }

    public function get_user_vote( int $review_item_id, int $user_id ): ?object {
        $rows = $this->core->database->query( 'el_es_review_votes', [
            'review_item_id' => $review_item_id,
            'user_id'        => $user_id,
        ], [ 'limit' => 1 ] );
        return ! empty( $rows ) ? $rows[0] : null;
    }

    // ═══════════════════════════════════════════
    // REVIEW SYSTEM — AJAX HANDLERS
    // ═══════════════════════════════════════════

    /**
     * Load the mood board: active review session + templates + current user's votes.
     */
    public function handle_get_mood_board( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'Not logged in.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id || ! $this->is_stakeholder( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $review_items = $this->get_review_items( $project_id, 'mood_board' );
        $open_item    = null;
        foreach ( $review_items as $item ) {
            if ( $item->status === 'open' ) {
                $open_item = $item;
                break;
            }
        }

        if ( ! $open_item ) {
            EL_AJAX_Handler::success( [ 'status' => 'no_session' ] );
            return;
        }

        // Get selected template IDs from dm_decision
        $dm_decision        = $open_item->dm_decision ? json_decode( $open_item->dm_decision, true ) : [];
        $selected_ids       = $dm_decision['selected_template_ids'] ?? [];

        if ( empty( $selected_ids ) ) {
            EL_AJAX_Handler::success( [ 'status' => 'no_templates', 'review_item_id' => $open_item->id ] );
            return;
        }

        // Load templates
        global $wpdb;
        $table       = $wpdb->prefix . 'el_es_templates';
        $placeholders = implode( ',', array_fill( 0, count( $selected_ids ), '%d' ) );
        $templates   = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id IN ({$placeholders}) ORDER BY style_category, sort_order",
            ...$selected_ids
        ) );

        // Get current user's vote
        $user_id    = get_current_user_id();
        $user_vote  = $this->get_user_vote( (int) $open_item->id, $user_id );
        $vote_data  = $user_vote ? json_decode( $user_vote->vote_data, true ) : [ 'preferences' => [] ];

        // Get all stakeholder votes for progress tracker
        $all_votes  = $this->get_review_votes( (int) $open_item->id );
        $voted_ids  = array_map( fn( $v ) => (int) $v->user_id, $all_votes );
        $stakeholders = $this->get_stakeholders( $project_id );
        $total       = count( $stakeholders );
        $responded   = count( array_filter( $stakeholders, fn( $s ) => in_array( (int) $s->user_id, $voted_ids, true ) ) );

        EL_AJAX_Handler::success( [
            'status'          => 'open',
            'review_item_id'  => (int) $open_item->id,
            'deadline'        => $open_item->deadline,
            'templates'       => $templates,
            'vote_data'       => $vote_data,
            'responded'       => $responded,
            'total'           => $total,
        ] );
    }

    /**
     * Save / update a stakeholder's template preference vote.
     */
    public function handle_save_template_vote( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'Not logged in.', 'el-core' ), 403 );
            return;
        }

        $review_item_id = absint( $data['review_item_id'] ?? 0 );
        $template_id    = absint( $data['template_id'] ?? 0 );
        $preference     = sanitize_text_field( $data['preference'] ?? 'neutral' );

        if ( ! in_array( $preference, [ 'liked', 'neutral', 'disliked' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Invalid preference value.', 'el-core' ) );
            return;
        }

        $review_item = $this->get_review_item( $review_item_id );
        if ( ! $review_item || $review_item->status !== 'open' ) {
            EL_AJAX_Handler::error( __( 'Review session is not open.', 'el-core' ) );
            return;
        }

        $project_id = (int) $review_item->project_id;
        if ( ! $this->is_stakeholder( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $user_id    = get_current_user_id();
        $existing   = $this->get_user_vote( $review_item_id, $user_id );

        if ( $existing ) {
            $vote_data = json_decode( $existing->vote_data, true );
            $vote_data['preferences'][ $template_id ] = $preference;
            $this->core->database->update( 'el_es_review_votes', [
                'vote_data'  => wp_json_encode( $vote_data ),
                'updated_at' => current_time( 'mysql' ),
            ], [ 'id' => (int) $existing->id ] );
        } else {
            $vote_data = [ 'preferences' => [ $template_id => $preference ] ];
            $this->core->database->insert( 'el_es_review_votes', [
                'review_item_id' => $review_item_id,
                'user_id'        => $user_id,
                'vote_data'      => wp_json_encode( $vote_data ),
                'submitted_at'   => current_time( 'mysql' ),
                'updated_at'     => current_time( 'mysql' ),
            ] );
        }

        do_action( 'el_review_vote_submitted', $review_item_id, $user_id, $vote_data );

        // Recalculate progress
        $all_votes    = $this->get_review_votes( $review_item_id );
        $voted_ids    = array_map( fn( $v ) => (int) $v->user_id, $all_votes );
        $stakeholders = $this->get_stakeholders( $project_id );
        $total        = count( $stakeholders );
        $responded    = count( array_filter( $stakeholders, fn( $s ) => in_array( (int) $s->user_id, $voted_ids, true ) ) );

        EL_AJAX_Handler::success( [
            'responded' => $responded,
            'total'     => $total,
        ], __( 'Vote saved!', 'el-core' ) );
    }

    /**
     * Get review status: who has/hasn't voted. DM only.
     */
    public function handle_get_review_status( array $data ): void {
        $review_item_id = absint( $data['review_item_id'] ?? 0 );
        if ( ! $review_item_id ) {
            EL_AJAX_Handler::error( __( 'Invalid review item ID.', 'el-core' ) );
            return;
        }

        $review_item = $this->get_review_item( $review_item_id );
        if ( ! $review_item ) {
            EL_AJAX_Handler::error( __( 'Review not found.', 'el-core' ), 404 );
            return;
        }

        $project_id = (int) $review_item->project_id;
        if ( ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $all_votes    = $this->get_review_votes( $review_item_id );
        $voted_ids    = array_map( fn( $v ) => (int) $v->user_id, $all_votes );
        $stakeholders = $this->get_stakeholders( $project_id );

        $status = [];
        foreach ( $stakeholders as $sh ) {
            $user = get_userdata( (int) $sh->user_id );
            $status[] = [
                'user_id'   => (int) $sh->user_id,
                'name'      => $user ? $user->display_name : 'Unknown',
                'responded' => in_array( (int) $sh->user_id, $voted_ids, true ),
            ];
        }

        EL_AJAX_Handler::success( [ 'stakeholders' => $status ] );
    }

    /**
     * Get full vote breakdown. DM only, shown after all voted or deadline passed.
     */
    public function handle_get_review_results( array $data ): void {
        $review_item_id = absint( $data['review_item_id'] ?? 0 );
        if ( ! $review_item_id ) {
            EL_AJAX_Handler::error( __( 'Invalid review item ID.', 'el-core' ) );
            return;
        }

        $review_item = $this->get_review_item( $review_item_id );
        if ( ! $review_item ) {
            EL_AJAX_Handler::error( __( 'Review not found.', 'el-core' ), 404 );
            return;
        }

        $project_id = (int) $review_item->project_id;
        if ( ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $all_votes = $this->get_review_votes( $review_item_id );

        // Build results: per-template tallies + per-stakeholder breakdown
        $dm_decision      = $review_item->dm_decision ? json_decode( $review_item->dm_decision, true ) : [];
        $selected_ids     = $dm_decision['selected_template_ids'] ?? [];
        $results_by_template = [];

        foreach ( $selected_ids as $tid ) {
            $results_by_template[ $tid ] = [ 'liked' => 0, 'neutral' => 0, 'disliked' => 0, 'voters' => [] ];
        }

        foreach ( $all_votes as $vote ) {
            $vote_data = json_decode( $vote->vote_data, true );
            $prefs     = $vote_data['preferences'] ?? [];
            $user      = get_userdata( (int) $vote->user_id );
            $name      = $user ? $user->display_name : 'Unknown';
            foreach ( $prefs as $tid => $pref ) {
                if ( isset( $results_by_template[ $tid ] ) ) {
                    $results_by_template[ $tid ][ $pref ]++;
                    $results_by_template[ $tid ]['voters'][] = [ 'name' => $name, 'pref' => $pref ];
                }
            }
        }

        EL_AJAX_Handler::success( [
            'review_item'   => $review_item,
            'results'       => $results_by_template,
            'total_voters'  => count( $all_votes ),
        ] );
    }

    /**
     * DM closes a review and records their final style direction choice.
     */
    public function handle_close_review( array $data ): void {
        $review_item_id = absint( $data['review_item_id'] ?? 0 );
        if ( ! $review_item_id ) {
            EL_AJAX_Handler::error( __( 'Invalid review item ID.', 'el-core' ) );
            return;
        }

        $review_item = $this->get_review_item( $review_item_id );
        if ( ! $review_item ) {
            EL_AJAX_Handler::error( __( 'Review not found.', 'el-core' ), 404 );
            return;
        }

        $project_id = (int) $review_item->project_id;
        if ( ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        if ( $review_item->status === 'closed' ) {
            EL_AJAX_Handler::error( __( 'Review is already closed.', 'el-core' ) );
            return;
        }

        // confirmed_template_ids = array of selected template IDs from DM
        $confirmed_ids = array_map( 'absint', (array) ( $data['confirmed_template_ids'] ?? [] ) );
        $existing_dm   = $review_item->dm_decision ? json_decode( $review_item->dm_decision, true ) : [];
        $existing_dm['confirmed_template_ids'] = $confirmed_ids;

        $user_id = get_current_user_id();

        $this->core->database->update( 'el_es_review_items', [
            'status'      => 'closed',
            'closed_by'   => $user_id,
            'closed_at'   => current_time( 'mysql' ),
            'dm_decision' => wp_json_encode( $existing_dm ),
        ], [ 'id' => $review_item_id ] );

        do_action( 'el_review_closed', $review_item_id, $project_id, $existing_dm );

        EL_AJAX_Handler::success( null, __( 'Style direction confirmed!', 'el-core' ) );
    }

    /**
     * Admin creates a new review session for a project with selected templates.
     */
    public function handle_create_review_item( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id   = absint( $data['project_id'] ?? 0 );
        $review_type  = sanitize_text_field( $data['review_type'] ?? 'mood_board' );
        $title        = sanitize_text_field( $data['title'] ?? '' );
        $template_ids = array_map( 'absint', (array) ( $data['template_ids'] ?? [] ) );
        $deadline     = sanitize_text_field( $data['deadline'] ?? '' );

        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        if ( empty( $template_ids ) ) {
            EL_AJAX_Handler::error( __( 'Select at least one template.', 'el-core' ) );
            return;
        }

        $dm_decision = wp_json_encode( [ 'selected_template_ids' => $template_ids ] );

        $insert = [
            'project_id'  => $project_id,
            'review_type' => $review_type,
            'title'       => $title ?: __( 'Style Direction', 'el-core' ),
            'status'      => 'open',
            'dm_decision' => $dm_decision,
            'created_at'  => current_time( 'mysql' ),
        ];

        if ( $deadline ) {
            $insert['deadline'] = date( 'Y-m-d 23:59:59', strtotime( $deadline ) );
        }

        $review_item_id = $this->core->database->insert( 'el_es_review_items', $insert );

        if ( $review_item_id ) {
            do_action( 'el_review_item_created', $review_item_id, $project_id, $insert['deadline'] ?? null );
            EL_AJAX_Handler::success( [ 'review_item_id' => $review_item_id ], __( 'Review session created!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to create review session.', 'el-core' ) );
        }
    }

    /**
     * Admin sets or updates the deadline on a review item.
     */
    public function handle_set_review_deadline( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $review_item_id = absint( $data['review_item_id'] ?? 0 );
        $deadline       = sanitize_text_field( $data['deadline'] ?? '' );

        if ( ! $review_item_id || ! $deadline ) {
            EL_AJAX_Handler::error( __( 'Invalid parameters.', 'el-core' ) );
            return;
        }

        $result = $this->core->database->update( 'el_es_review_items', [
            'deadline' => date( 'Y-m-d 23:59:59', strtotime( $deadline ) ),
        ], [ 'id' => $review_item_id ] );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( null, __( 'Deadline updated!', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to update deadline.', 'el-core' ) );
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

    /**
     * Handle the switch-back-to-admin request.
     * Triggered by ?action=switch_back_user&_wpnonce=... on any admin page.
     */
    public function handle_switch_back_user(): void {
        if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'switch_back_user' ) {
            return;
        }

        $nonce = sanitize_text_field( $_GET['_wpnonce'] ?? '' );
        if ( ! wp_verify_nonce( $nonce, 'switch_back_user' ) ) {
            wp_die( __( 'Invalid request.', 'el-core' ) );
        }

        $current_user_id   = get_current_user_id();
        $original_admin_id = (int) get_user_meta( $current_user_id, '_switched_from_user', true );

        if ( ! $original_admin_id ) {
            wp_die( __( 'No original session found.', 'el-core' ) );
        }

        $admin_user = get_user_by( 'id', $original_admin_id );
        if ( ! $admin_user ) {
            wp_die( __( 'Original admin user not found.', 'el-core' ) );
        }

        // Clean up meta
        delete_user_meta( $current_user_id, '_switched_from_user' );
        delete_user_meta( $original_admin_id, '_switched_to_user' );

        // Switch back to the admin
        wp_clear_auth_cookie();
        wp_set_current_user( $original_admin_id );
        wp_set_auth_cookie( $original_admin_id );

        wp_redirect( admin_url( 'admin.php?page=el-core-clients' ) );
        exit;
    }

    /**
     * Add a red "Switch back to [Admin]" button to the WP admin bar
     * whenever the current session was initiated via "Log in as".
     */
    public function add_switch_back_admin_bar_button( \WP_Admin_Bar $wp_admin_bar ): void {
        $current_user_id   = get_current_user_id();
        $original_admin_id = (int) get_user_meta( $current_user_id, '_switched_from_user', true );

        if ( ! $original_admin_id ) {
            return;
        }

        $admin_user = get_user_by( 'id', $original_admin_id );
        if ( ! $admin_user ) {
            return;
        }

        $switch_back_url = add_query_arg( [
            'action'   => 'switch_back_user',
            '_wpnonce' => wp_create_nonce( 'switch_back_user' ),
        ], admin_url( 'admin.php' ) );

        $wp_admin_bar->add_node( [
            'id'    => 'el-switch-back',
            'title' => '<span style="color:#fff;background:#dc2626;padding:2px 10px;border-radius:4px;font-weight:600;">'
                . sprintf(
                    /* translators: %s: admin display name */
                    esc_html__( 'Switch back to %s', 'el-core' ),
                    esc_html( $admin_user->display_name )
                )
                . '</span>',
            'href'  => esc_url( $switch_back_url ),
            'meta'  => [ 'title' => __( 'Return to your admin account', 'el-core' ) ],
        ] );
    }
}
