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
        add_action( 'el_core_ajax_es_save_qualification',        [ $this, 'handle_save_qualification' ] );
        add_action( 'el_core_ajax_es_save_definition',          [ $this, 'handle_save_definition' ] );
        add_action( 'el_core_ajax_es_lock_definition',          [ $this, 'handle_lock_definition' ] );

        // Definition consensus review
        add_action( 'el_core_ajax_es_send_definition_review',   [ $this, 'handle_send_definition_review' ] );
        add_action( 'el_core_ajax_es_get_definition_review',    [ $this, 'handle_get_definition_review' ] );
        add_action( 'el_core_ajax_es_post_definition_comment',  [ $this, 'handle_post_definition_comment' ] );
        add_action( 'el_core_ajax_es_field_verdict',            [ $this, 'handle_field_verdict' ] );
        add_action( 'el_core_ajax_es_dm_decision',              [ $this, 'handle_dm_decision' ] );
        add_action( 'el_core_ajax_es_reset_definition',         [ $this, 'handle_reset_definition' ] );
        add_action( 'el_core_ajax_es_client_edit_definition_field', [ $this, 'handle_client_edit_definition_field' ] );
        // User Journey phase
        add_action( 'el_core_ajax_es_init_user_journeys',    [ $this, 'handle_init_user_journeys' ] );
        add_action( 'el_core_ajax_es_add_user_type',         [ $this, 'handle_add_user_type' ] );
        add_action( 'el_core_ajax_es_rename_user_type',      [ $this, 'handle_rename_user_type' ] );
        add_action( 'el_core_ajax_es_delete_user_type',      [ $this, 'handle_delete_user_type' ] );
        add_action( 'el_core_ajax_es_assign_journey',        [ $this, 'handle_assign_journey' ] );
        add_action( 'el_core_ajax_es_refine_journey',        [ $this, 'handle_refine_journey' ] );
        add_action( 'el_core_ajax_es_send_journey_review',   [ $this, 'handle_send_journey_review' ] );
        add_action( 'el_core_ajax_es_reset_journey_review',  [ $this, 'handle_reset_journey_review' ] );
        add_action( 'el_core_ajax_es_lock_journey',          [ $this, 'handle_lock_journey' ] );
        add_action( 'el_core_ajax_es_approve_journey_list',  [ $this, 'handle_approve_journey_list' ] );
        add_action( 'el_core_ajax_es_retry_journey_ai',      [ $this, 'handle_retry_journey_ai' ] );
        add_action( 'el_core_ajax_nopriv_es_dm_assign_journey',      [ $this, 'handle_dm_assign_journey' ] );
        add_action( 'el_core_ajax_nopriv_es_submit_journey_answers', [ $this, 'handle_submit_journey_answers' ] );
        add_action( 'el_core_ajax_es_post_journey_comment',          [ $this, 'handle_post_journey_comment' ] );
        add_action( 'el_core_ajax_nopriv_es_post_journey_comment',   [ $this, 'handle_post_journey_comment' ] );
        add_action( 'el_core_ajax_es_journey_step_verdict',          [ $this, 'handle_journey_step_verdict' ] );
        add_action( 'el_core_ajax_nopriv_es_journey_step_verdict',   [ $this, 'handle_journey_step_verdict' ] );
        add_action( 'el_core_ajax_es_dm_journey_decision',           [ $this, 'handle_dm_journey_decision' ] );
        add_action( 'el_core_ajax_nopriv_es_dm_journey_decision',    [ $this, 'handle_dm_journey_decision' ] );
        add_action( 'el_core_ajax_es_save_journey_step_edit',        [ $this, 'handle_save_journey_step_edit' ] );
        add_action( 'el_core_ajax_nopriv_es_save_journey_step_edit', [ $this, 'handle_save_journey_step_edit' ] );
        add_action( 'el_core_ajax_es_dm_send_to_admin',              [ $this, 'handle_dm_send_to_admin' ] );
        add_action( 'el_core_ajax_nopriv_es_dm_send_to_admin',       [ $this, 'handle_dm_send_to_admin' ] );
        add_action( 'el_core_ajax_es_generate_journey_ai',           [ $this, 'handle_generate_journey_ai' ] );
        add_action( 'el_core_ajax_es_save_journey_workflow',         [ $this, 'handle_save_journey_workflow' ] );
        add_action( 'el_core_ajax_es_dm_assign_journey',             [ $this, 'handle_dm_assign_journey' ] );
        add_action( 'el_core_ajax_es_submit_journey_answers',        [ $this, 'handle_submit_journey_answers' ] );
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

        // Visual Identity Phase (Phase 5)
        add_action( 'el_core_ajax_es_save_visual_brief',       [ $this, 'handle_save_visual_brief' ] );
        add_action( 'el_core_ajax_nopriv_es_save_visual_brief', [ $this, 'handle_save_visual_brief' ] );
        add_action( 'el_core_ajax_es_submit_visual_brief',     [ $this, 'handle_submit_visual_brief' ] );
        add_action( 'el_core_ajax_nopriv_es_submit_visual_brief', [ $this, 'handle_submit_visual_brief' ] );
        add_action( 'el_core_ajax_es_get_visual_brief',        [ $this, 'handle_get_visual_brief' ] );
        add_action( 'el_core_ajax_nopriv_es_get_visual_brief', [ $this, 'handle_get_visual_brief' ] );
        add_action( 'el_core_ajax_es_generate_visual_brief',   [ $this, 'handle_generate_visual_brief' ] );
        add_action( 'el_core_ajax_es_lock_visual_brief',       [ $this, 'handle_lock_visual_brief' ] );
        add_action( 'el_core_ajax_es_unlock_visual_brief',     [ $this, 'handle_unlock_visual_brief' ] );
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
        // Always enqueue on frontend pages — page builders (Elementor, Divi, etc.)
        // store shortcodes in serialized data that has_shortcode() cannot detect.
        // The JS is inert unless .el-es-portal or related elements exist in the DOM.
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

    public function enqueue_admin_assets( string $hook ): void {
        $our_pages = [ 'el-core-projects' ];
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

        // Phase bar styles (replaces old stepper)
        wp_add_inline_style( 'el-expand-site-admin', '
            /* ── Utility tab group ── */
            .el-es-utility-tabs {
                margin-bottom: 0;
                border-bottom: none;
            }
            .el-es-utility-tabs .el-tab-btn {
                font-size: 13px;
                padding: 8px 14px;
            }

            /* ── Phase bar wrapper ── */
            .el-es-phase-bar-wrap {
                background: #fff;
                border: 1px solid #e5e7eb;
                border-top: none;
                border-radius: 0 0 8px 8px;
                padding: 16px 20px 12px;
                margin-bottom: 20px;
            }
            .el-es-phase-bar-label {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: .06em;
                color: #9ca3af;
                margin-bottom: 10px;
            }
            .el-es-phase-bar {
                display: flex;
                align-items: center;
                gap: 0;
                overflow-x: auto;
                padding-bottom: 2px;
            }
            .el-es-phase-pill {
                position: relative;
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 6px 14px 6px 20px;
                font-size: 12px;
                font-weight: 500;
                border: 1.5px solid #d1d5db;
                background: #f9fafb;
                color: #6b7280;
                cursor: pointer;
                white-space: nowrap;
                transition: all .15s;
                border-radius: 0;
                margin-left: -1px;
                line-height: 1.3;
                text-align: left;
            }
            .el-es-phase-pill:first-child {
                border-radius: 6px 0 0 6px;
                padding-left: 14px;
                margin-left: 0;
            }
            .el-es-phase-pill:last-child {
                border-radius: 0 6px 6px 0;
            }
            .el-es-phase-pill .el-es-phase-num {
                font-size: 10px;
                font-weight: 700;
                opacity: .6;
                min-width: 14px;
            }
            .el-es-phase-pill:hover {
                background: #f3f4f6;
                border-color: #9ca3af;
                color: #374151;
                z-index: 1;
            }
            .el-es-phase-pill.el-es-phase-complete {
                background: #ecfdf5;
                border-color: #6ee7b7;
                color: #065f46;
            }
            .el-es-phase-pill.el-es-phase-complete .dashicons {
                color: #059669;
                font-size: 13px;
                width: 13px;
                height: 13px;
            }
            .el-es-phase-pill.el-es-phase-current {
                background: #4f46e5;
                border-color: #4f46e5;
                color: #fff;
                font-weight: 600;
                z-index: 1;
                box-shadow: 0 1px 6px rgba(79,70,229,.35);
            }
            .el-es-phase-pill.el-es-phase-current .el-es-phase-num {
                opacity: .7;
            }
            .el-es-phase-pill.el-tab-btn[data-tab].active,
            .el-es-phase-pill[aria-selected="true"] {
                outline: 2px solid #4f46e5;
                outline-offset: 2px;
            }
        ' );
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
        3 => [ 'name' => 'Proposal',        'slug' => 'proposal',        'has_client_gate' => true ],
        4 => [ 'name' => 'User Journey',    'slug' => 'user-journey',    'has_client_gate' => true ],
        5 => [ 'name' => 'Visual Identity', 'slug' => 'visual-identity', 'has_client_gate' => true ],
        6 => [ 'name' => 'Wireframes',      'slug' => 'wireframes',      'has_client_gate' => true ],
        7 => [ 'name' => 'Final Design',    'slug' => 'final-design',    'has_client_gate' => true ],
        8 => [ 'name' => 'Build',           'slug' => 'build',           'has_client_gate' => false ],
        9 => [ 'name' => 'Delivery',        'slug' => 'delivery',        'has_client_gate' => true ],
    ];

    /**
     * Default deadline days per stage (from when stage starts)
     * Used as smart defaults in the Advance Stage modal date picker
     */
    public const STAGE_DEADLINE_DAYS = [
        1 => 3,   // Qualification: 3 days
        2 => 7,   // Discovery: 7 days
        3 => 5,   // Proposal: 5 days
        4 => 7,   // User Journey: 7 days
        5 => 10,  // Visual Identity: 10 days
        6 => 10,  // Wireframes: 10 days
        7 => 10,  // Final Design: 10 days
        8 => 14,  // Build: 14 days
        9 => 7,   // Delivery: 7 days
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
        if ( $stage <= 6 ) return 'primary';
        if ( $stage === 7 ) return 'default';
        if ( $stage === 8 ) return 'warning';
        if ( $stage === 9 ) return 'success';
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
        if ( ! $project || $project->current_stage >= 9 ) {
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

        // Lock scope when entering Stage 5 (Visual Identity)
        if ( $new_stage === 5 && ! $project->scope_locked_at ) {
            $update_data['scope_locked_at'] = current_time( 'mysql' );
        }

        // Seed user journey rows when entering Stage 4 (User Journey)
        if ( $new_stage === 4 ) {
            $this->init_user_journeys( $project_id );
        }

        // Initialize Visual Identity brief when entering Stage 5
        if ( $new_stage === 5 ) {
            $this->init_visual_brief( $project_id );
        }

        $db->update( 'el_es_projects', $update_data, [ 'id' => $project_id ] );
        return true;
    }

    /**
     * Seed one el_es_user_journeys row per user type when advancing to Stage 4.
     * Reads user_types from the locked definition; falls back to 'General User' if empty.
     * Safe to call multiple times — skips existing rows for this project.
     */
    public function init_user_journeys( int $project_id ): void {
        global $wpdb;
        $table      = $wpdb->prefix . 'el_es_user_journeys';
        $def_table  = $wpdb->prefix . 'el_es_project_definition';

        // Bail if rows already exist for this project
        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE project_id = %d", $project_id ) );
        if ( $existing > 0 ) {
            return;
        }

        $definition = $wpdb->get_row( $wpdb->prepare( "SELECT user_types FROM {$def_table} WHERE project_id = %d", $project_id ) );
        $user_types = [];

        if ( $definition && ! empty( $definition->user_types ) ) {
            $raw = json_decode( $definition->user_types, true );
            if ( is_array( $raw ) ) {
                // Support both ["Student","Parent"] and [{"name":"Student"},...]
                foreach ( $raw as $item ) {
                    if ( is_string( $item ) && trim( $item ) !== '' ) {
                        $user_types[] = trim( $item );
                    } elseif ( is_array( $item ) && ! empty( $item['name'] ) ) {
                        $user_types[] = trim( $item['name'] );
                    }
                }
            } elseif ( is_string( $definition->user_types ) ) {
                // Comma-separated fallback
                foreach ( explode( ',', $definition->user_types ) as $t ) {
                    $t = trim( $t );
                    if ( $t !== '' ) $user_types[] = $t;
                }
            }
        }

        if ( empty( $user_types ) ) {
            $user_types = [ 'General User' ];
        }

        $now = current_time( 'mysql' );
        foreach ( $user_types as $type ) {
            $wpdb->insert( $table, [
                'project_id' => $project_id,
                'user_type'  => sanitize_text_field( $type ),
                'added_by'   => null,
                'status'     => 'pending_assignment',
                'created_at' => $now,
            ] );
        }
    }

    // ═══════════════════════════════════════════
    // VISUAL IDENTITY PHASE — HELPERS
    // ═══════════════════════════════════════════

    /**
     * Create the el_es_visual_brief row when advancing to Phase 5.
     * Pre-populates pages_needed from locked journey implied_pages.
     */
    public function init_visual_brief( int $project_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_visual_brief';

        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE project_id = %d", $project_id ) );
        if ( $existing ) {
            return;
        }

        // Gather implied_pages from all locked journeys
        $jt = $wpdb->prefix . 'el_es_user_journeys';
        $journeys = $wpdb->get_results( $wpdb->prepare(
            "SELECT admin_workflow, ai_workflow FROM {$jt} WHERE project_id = %d AND status = 'locked'",
            $project_id
        ) );

        $all_pages = [];
        foreach ( $journeys as $j ) {
            $wf = $j->admin_workflow ? json_decode( $j->admin_workflow, true ) : ( $j->ai_workflow ? json_decode( $j->ai_workflow, true ) : null );
            if ( $wf && ! empty( $wf['implied_pages'] ) && is_array( $wf['implied_pages'] ) ) {
                foreach ( $wf['implied_pages'] as $page ) {
                    $page = trim( $page );
                    if ( $page !== '' && ! in_array( $page, $all_pages, true ) ) {
                        $all_pages[] = $page;
                    }
                }
            }
        }

        $wpdb->insert( $table, [
            'project_id'  => $project_id,
            'pages_needed' => wp_json_encode( $all_pages ),
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ] );
    }

    public function get_visual_brief( int $project_id ): ?object {
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_visual_brief';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE project_id = %d", $project_id ) );
    }

    private function generate_visual_brief( int $project_id ): string {
        global $wpdb;

        $brief   = $this->get_visual_brief( $project_id );
        $project = $this->get_project( $project_id );
        if ( ! $brief || ! $project ) {
            return '';
        }

        $def_table = $wpdb->prefix . 'el_es_project_definition';
        $def       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$def_table} WHERE project_id = %d", $project_id ) );

        $date          = date_i18n( 'F j, Y' );
        $client_name   = $project->client_name;
        $project_name  = $project->name;

        $logo_status = '';
        if ( $brief->has_logo ) {
            $logo_status = 'Existing logo provided';
        } elseif ( $brief->logo_needs_creation ) {
            $logo_status = 'Needs to be created by ELS';
        } else {
            $logo_status = 'To be determined';
        }

        $colors_section = '';
        if ( $brief->has_brand_colors ) {
            if ( $brief->color_primary )   $colors_section .= "- Primary: {$brief->color_primary}\n";
            if ( $brief->color_secondary ) $colors_section .= "- Secondary: {$brief->color_secondary}\n";
            if ( $brief->color_accent )    $colors_section .= "- Accent: {$brief->color_accent}\n";
            if ( $brief->color_neutral )   $colors_section .= "- Neutral/Background: {$brief->color_neutral}\n";
            if ( $brief->color_notes )     $colors_section .= "- Notes: {$brief->color_notes}\n";
        } else {
            $colors_section = "No established colors — ELS to propose palette.\n";
            if ( $brief->color_notes )     $colors_section .= "- Direction notes: {$brief->color_notes}\n";
        }

        $font_section = '';
        if ( $brief->has_brand_fonts ) {
            if ( $brief->font_heading ) $font_section .= "- Heading Font: {$brief->font_heading}\n";
            if ( $brief->font_body )    $font_section .= "- Body Font: {$brief->font_body}\n";
            if ( $brief->font_notes )   $font_section .= "- Notes: {$brief->font_notes}\n";
        } else {
            $font_section = "No brand fonts — ELS to select appropriate pairing.\n";
        }

        $materials_section = '';
        if ( $brief->has_existing_materials ) {
            if ( $brief->existing_materials_url )   $materials_section .= "- Reference: {$brief->existing_materials_url}\n";
            if ( $brief->existing_materials_notes ) $materials_section .= "- Notes: {$brief->existing_materials_notes}\n";
        } else {
            $materials_section = "No existing materials — starting fresh.\n";
        }

        $pages_needed = [];
        if ( $brief->pages_needed ) {
            $pages_needed = json_decode( $brief->pages_needed, true ) ?: [];
        }
        $pages_list = '';
        foreach ( $pages_needed as $i => $page ) {
            $pages_list .= ( $i + 1 ) . ". {$page}\n";
        }
        if ( ! $pages_list ) {
            $pages_list = "Not specified — to be determined.\n";
        }

        $photography_section = '';
        if ( $brief->has_photography && $brief->photography_url ) {
            $photography_section .= "- Own photos: Yes\n";
            $photography_section .= "- Photo library: {$brief->photography_url}\n";
        } elseif ( $brief->has_photography ) {
            $photography_section .= "- Own photos: Yes\n";
        }
        if ( $brief->needs_stock_photography ) {
            $photography_section .= "- Stock photography needed: Yes\n";
        }
        if ( $brief->photography_notes ) {
            $photography_section .= "- Notes: {$brief->photography_notes}\n";
        }
        if ( ! $photography_section ) {
            $photography_section = "Photography situation not specified.\n";
        }

        $parent_brand_section = $brief->has_parent_org_brand
            ? ( $brief->parent_org_brand_notes ?: 'Yes — details not specified.' )
            : 'None — no parent organization brand requirements.';

        $accessibility_section = $brief->accessibility_standard ?: 'Not specified — use best practices.';

        $language_section = $brief->multilingual
            ? ( $brief->languages ?: 'Yes — languages not specified.' )
            : 'English only.';

        $other_constraints = $brief->other_constraints ?: 'None specified.';
        $additional_notes  = $brief->additional_notes  ?: 'None.';

        $site_type    = $def ? $def->site_type       : '';
        $primary_goal = $def ? $def->primary_goal    : '';
        $target_aud   = $def ? $def->target_customers : '';

        $md  = "# Brand Brief — {$client_name}\n";
        $md .= "**Project:** {$project_name}\n";
        $md .= "**Generated:** {$date}\n\n";
        $md .= "---\n\n";
        $md .= "## Organization\n\n";
        $md .= "- **Name:** {$client_name}\n";
        if ( $site_type )    $md .= "- **Site Type:** {$site_type}\n";
        if ( $primary_goal ) $md .= "- **Primary Goal:** {$primary_goal}\n";
        if ( $target_aud )   $md .= "- **Target Audience:** {$target_aud}\n";
        $md .= "\n---\n\n";
        $md .= "## Brand Assets\n\n";
        $md .= "### Logo\n";
        $md .= "- Status: {$logo_status}\n";
        if ( $brief->logo_url )   $md .= "- File: {$brief->logo_url}\n";
        if ( $brief->logo_notes ) $md .= "- Notes: {$brief->logo_notes}\n";
        $md .= "\n### Colors\n";
        $md .= $colors_section;
        $md .= "\n### Typography\n";
        $md .= $font_section;
        $md .= "\n### Existing Materials\n";
        $md .= $materials_section;
        $md .= "\n---\n\n";
        $md .= "## Visual Direction\n\n";
        $md .= "### Audience\n";
        $md .= ( $brief->audience_description ?: 'Not provided — ELS to determine.' ) . "\n\n";
        $md .= "### Tone and Feel\n";
        $md .= ( $brief->tone_feel ?: 'Not provided — ELS to determine.' ) . "\n\n";
        $md .= "### Reference Sites (Likes)\n";
        $md .= ( $brief->sites_they_like ?: 'None provided.' ) . "\n\n";
        $md .= "### Sites / Styles to Avoid\n";
        $md .= ( $brief->sites_to_avoid ?: 'None specified.' ) . "\n\n";
        $md .= "---\n\n";
        $md .= "## Site Structure\n\n";
        $md .= "### Pages Required\n";
        $md .= $pages_list;
        $md .= "*Source: Compiled from Phase 4 User Journey implied pages + client additions.*\n\n";
        $md .= "---\n\n";
        $md .= "## Photography\n\n";
        $md .= $photography_section;
        $md .= "\n---\n\n";
        $md .= "## Constraints\n\n";
        $md .= "### Parent Organization Branding\n";
        $md .= $parent_brand_section . "\n\n";
        $md .= "### Accessibility\n";
        $md .= $accessibility_section . "\n\n";
        $md .= "### Language Support\n";
        $md .= $language_section . "\n\n";
        $md .= "### Other\n";
        $md .= $other_constraints . "\n\n";
        $md .= "---\n\n";
        $md .= "## Additional Notes\n\n";
        $md .= $additional_notes . "\n\n";
        $md .= "---\n\n";
        $md .= "*This brief was generated from client intake responses collected in the EL Core Expand Site portal. Use it as the primary design prompt for AI-assisted site building.*\n";

        return $md;
    }

    // ═══════════════════════════════════════════
    // VISUAL IDENTITY PHASE — AJAX HANDLERS
    // ═══════════════════════════════════════════

    public function handle_save_visual_brief( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'Not logged in.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id || ! $this->is_stakeholder( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $brief = $this->get_visual_brief( $project_id );
        if ( ! $brief ) {
            EL_AJAX_Handler::error( __( 'Visual brief not found.', 'el-core' ), 404 );
            return;
        }

        // Only DM can save
        if ( ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Only the Decision Maker can save.', 'el-core' ), 403 );
            return;
        }

        $allowed = [
            'has_logo', 'logo_url', 'logo_needs_creation', 'logo_notes',
            'has_brand_colors', 'color_primary', 'color_secondary', 'color_accent', 'color_neutral', 'color_notes',
            'has_brand_fonts', 'font_heading', 'font_body', 'font_notes',
            'has_existing_materials', 'existing_materials_url', 'existing_materials_notes',
            'audience_description', 'tone_feel', 'sites_they_like', 'sites_to_avoid',
            'pages_needed',
            'has_photography', 'photography_url', 'needs_stock_photography', 'photography_notes',
            'has_parent_org_brand', 'parent_org_brand_notes',
            'accessibility_required', 'accessibility_standard',
            'multilingual', 'languages', 'other_constraints', 'additional_notes',
        ];

        $tinyint_fields = [
            'has_logo', 'logo_needs_creation', 'has_brand_colors', 'has_brand_fonts',
            'has_existing_materials', 'has_photography', 'needs_stock_photography',
            'has_parent_org_brand', 'accessibility_required', 'multilingual',
        ];

        $fields = [ 'updated_at' => current_time( 'mysql' ) ];
        foreach ( $allowed as $key ) {
            if ( ! isset( $data[ $key ] ) ) {
                continue;
            }
            if ( in_array( $key, $tinyint_fields, true ) ) {
                $fields[ $key ] = absint( $data[ $key ] );
            } elseif ( $key === 'pages_needed' ) {
                $raw = $data[ $key ];
                if ( is_string( $raw ) ) {
                    $decoded = json_decode( $raw, true );
                    $fields[ $key ] = is_array( $decoded ) ? wp_json_encode( array_values( array_filter( array_map( 'sanitize_text_field', $decoded ) ) ) ) : wp_json_encode( [] );
                } else {
                    $fields[ $key ] = wp_json_encode( [] );
                }
            } else {
                $fields[ $key ] = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ?? $data[ $key ] ) );
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'el_es_visual_brief';
        $wpdb->update( $table, $fields, [ 'project_id' => $project_id ] );

        EL_AJAX_Handler::success( null, __( 'Saved.', 'el-core' ) );
    }

    public function handle_submit_visual_brief( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'Not logged in.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id || ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $brief = $this->get_visual_brief( $project_id );
        if ( ! $brief ) {
            EL_AJAX_Handler::error( __( 'Visual brief not found.', 'el-core' ), 404 );
            return;
        }

        if ( $brief->portal_submitted_at ) {
            EL_AJAX_Handler::error( __( 'Already submitted.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'el_es_visual_brief';
        $wpdb->update( $table, [
            'portal_submitted_at' => current_time( 'mysql' ),
            'portal_submitted_by' => get_current_user_id(),
            'updated_at'          => current_time( 'mysql' ),
        ], [ 'project_id' => $project_id ] );

        // Notify admin
        $project      = $this->get_project( $project_id );
        $project_name = $project ? $project->name : "Project #{$project_id}";
        $admin_email  = get_option( 'admin_email' );
        $admin_url    = admin_url( "admin.php?page=el-core-projects&project={$project_id}" );
        $submitter    = get_userdata( get_current_user_id() );
        $submitter_name = $submitter ? $submitter->display_name : 'A stakeholder';
        wp_mail(
            $admin_email,
            "[EL Core] Visual Identity intake submitted — {$project_name}",
            "{$submitter_name} has submitted the Visual Identity intake form for {$project_name}.\n\nView it here: {$admin_url}"
        );

        EL_AJAX_Handler::success( null, __( 'Submitted successfully!', 'el-core' ) );
    }

    public function handle_get_visual_brief( array $data ): void {
        if ( ! is_user_logged_in() ) {
            EL_AJAX_Handler::error( __( 'Not logged in.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id || ! $this->is_stakeholder( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $brief = $this->get_visual_brief( $project_id );
        EL_AJAX_Handler::success( [ 'brief' => $brief ] );
    }

    public function handle_generate_visual_brief( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        $md = $this->generate_visual_brief( $project_id );
        if ( ! $md ) {
            EL_AJAX_Handler::error( __( 'Failed to generate brief — missing project or intake data.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'el_es_visual_brief';
        $wpdb->update( $table, [
            'generated_brief' => $md,
            'generated_at'    => current_time( 'mysql' ),
            'updated_at'      => current_time( 'mysql' ),
        ], [ 'project_id' => $project_id ] );

        EL_AJAX_Handler::success( [
            'brief'        => $md,
            'generated_at' => current_time( 'mysql' ),
        ], __( 'Brand Brief generated!', 'el-core' ) );
    }

    public function handle_lock_visual_brief( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'el_es_visual_brief';
        $wpdb->update( $table, [
            'locked_at'  => current_time( 'mysql' ),
            'locked_by'  => get_current_user_id(),
            'updated_at' => current_time( 'mysql' ),
        ], [ 'project_id' => $project_id ] );

        EL_AJAX_Handler::success( null, __( 'Brand Brief locked! Phase 6 is now available.', 'el-core' ) );
    }

    public function handle_unlock_visual_brief( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Invalid project ID.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'el_es_visual_brief';
        $wpdb->update( $table, [
            'locked_at'  => null,
            'locked_by'  => null,
            'updated_at' => current_time( 'mysql' ),
        ], [ 'project_id' => $project_id ] );

        EL_AJAX_Handler::success( null, __( 'Brand Brief unlocked.', 'el-core' ) );
    }
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

        $notes    = sanitize_text_field( $data['notes'] ?? '' );
        $deadline = sanitize_text_field( $data['deadline'] ?? '' );

        // Gate: all journeys must be locked before advancing from Stage 4
        $project = $this->get_project( $project_id );
        if ( $project && (int) $project->current_stage === 4 ) {
            global $wpdb;
            $jt    = $wpdb->prefix . 'el_es_user_journeys';
            $total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jt} WHERE project_id = %d", $project_id ) );
            $locked = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$jt} WHERE project_id = %d AND status = 'locked'", $project_id ) );
            if ( $total > 0 && $locked < $total ) {
                EL_AJAX_Handler::error( sprintf(
                    __( 'All user journeys must be locked before advancing. %d of %d are locked.', 'el-core' ),
                    $locked, $total
                ) );
                return;
            }
        }

        // Gate: Visual Identity brief must be locked before advancing from Stage 5
        if ( $project && (int) $project->current_stage === 5 ) {
            global $wpdb;
            $vbt   = $wpdb->prefix . 'el_es_visual_brief';
            $brief = $wpdb->get_row( $wpdb->prepare( "SELECT locked_at FROM {$vbt} WHERE project_id = %d", $project_id ) );
            if ( ! $brief || ! $brief->locked_at ) {
                EL_AJAX_Handler::error( __( 'Lock the Brand Brief before advancing to Wireframes.', 'el-core' ) );
                return;
            }
        }

        $result = $this->advance_stage( $project_id, $notes, $deadline );
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
        $raw_role   = sanitize_text_field( $data['role'] ?? 'contributor' );
        // Map abbreviated role codes (avoids WAF blocks on 'decision_maker' in POST body)
        $role_map = [
            'dm'           => 'decision_maker',
            'c'            => 'contributor',
            'decision_maker' => 'decision_maker',
            'contributor'    => 'contributor',
        ];
        $role = $role_map[ $raw_role ] ?? 'contributor';

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
        $raw_role       = sanitize_text_field( $data['new_role'] ?? '' );

        // Map abbreviated role codes to full role names (avoids WAF blocks on 'decision_maker' in POST body)
        $role_map = [
            'dm'           => 'decision_maker',
            'c'            => 'contributor',
            'decision_maker' => 'decision_maker',
            'contributor'    => 'contributor',
        ];
        $new_role = $role_map[ $raw_role ] ?? '';

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

    /**
     * Save Phase 1 Qualification intake fields (project_goal, deadline/call date, notes).
     */
    public function handle_save_qualification( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Project ID required.', 'el-core' ) );
            return;
        }

        $project_goal = sanitize_textarea_field( wp_unslash( $_POST['project_goal'] ?? '' ) );
        $deadline_raw = sanitize_text_field( $data['deadline'] ?? '' );
        $deadline     = $deadline_raw ? date( 'Y-m-d H:i:s', strtotime( $deadline_raw ) ) : null;
        $notes        = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );

        $update = [
            'project_goal' => $project_goal,
            'notes'        => $notes,
        ];
        if ( $deadline ) {
            $update['deadline'] = $deadline;
        }

        $result = $this->core->database->update( 'el_es_projects', $update, [ 'id' => $project_id ] );

        if ( $result !== false ) {
            EL_AJAX_Handler::success( [], __( 'Qualification intake saved.', 'el-core' ) );
        } else {
            EL_AJAX_Handler::error( __( 'Failed to save.', 'el-core' ) );
        }
    }

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
     * Build a field-by-field diff between two review snapshots.
     * Returns array keyed by field_key, each value has 'old' and 'new'.
     * Only includes fields that actually changed.
     */
    public function diff_definition_snapshots( string $snapshot_old, string $snapshot_new ): array {
        $old = json_decode( $snapshot_old, true ) ?: [];
        $new = json_decode( $snapshot_new, true ) ?: [];
        $changed = [];
        $all_keys = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );
        foreach ( $all_keys as $key ) {
            $old_val = trim( $old[ $key ] ?? '' );
            $new_val = trim( $new[ $key ] ?? '' );
            if ( $old_val !== $new_val ) {
                $changed[ $key ] = [ 'old' => $old_val, 'new' => $new_val ];
            }
        }
        return $changed;
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
             WHERE c.review_id = %d AND (c.comment != '' OR c.parent_id != 0)
             AND NOT (c.comment = '' AND c.parent_id = 0 AND c.verdict != '')
             ORDER BY c.created_at ASC",
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
     * Only counts dedicated verdict rows (comment='' or any, verdict!='' and parent_id=0).
     * Returns array keyed by field_key => ['approved'=>n, 'needs_revision'=>n, 'total'=>n]
     */
    public function get_definition_verdicts( int $review_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_definition_comments';
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT c.field_key, c.verdict, c.created_at, u.display_name
             FROM {$table} c
             LEFT JOIN {$wpdb->users} u ON u.ID = c.user_id
             WHERE c.review_id = %d AND c.parent_id = 0 AND c.verdict != '' AND c.verdict IS NOT NULL
             ORDER BY c.created_at ASC",
            $review_id
        ) ) ?: [];
        $out = [];
        foreach ( $rows as $r ) {
            if ( ! isset( $out[ $r->field_key ] ) ) {
                $out[ $r->field_key ] = [ 'approved' => 0, 'needs_revision' => 0, 'total' => 0, 'users' => [] ];
            }
            $out[ $r->field_key ][ $r->verdict ]++;
            $out[ $r->field_key ]['total']++;
            $out[ $r->field_key ]['users'][] = [
                'name'    => $r->display_name ?: 'Unknown',
                'verdict' => $r->verdict,
                'date'    => $r->created_at,
            ];
        }
        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // USER JOURNEY PHASE — AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * AJAX: Admin manually triggers journey row seeding (in case auto-seed missed).
     */
    public function handle_init_user_journeys( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Project ID required.', 'el-core' ) );
            return;
        }
        $this->init_user_journeys( $project_id );
        EL_AJAX_Handler::success( [], __( 'User journey rows initialized.', 'el-core' ) );
    }

    /**
     * AJAX: Admin adds a user type manually (not from the definition list).
     */
    public function handle_add_user_type( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $project_id = absint( $data['project_id'] ?? 0 );
        $user_type  = sanitize_text_field( wp_unslash( $data['user_type'] ?? '' ) );
        if ( ! $project_id || ! $user_type ) {
            EL_AJAX_Handler::error( __( 'Project ID and user type are required.', 'el-core' ) );
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_user_journeys';
        $wpdb->insert( $table, [
            'project_id' => $project_id,
            'user_type'  => $user_type,
            'added_by'   => get_current_user_id(),
            'status'     => 'pending_assignment',
            'created_at' => current_time( 'mysql' ),
        ] );
        EL_AJAX_Handler::success( [ 'id' => $wpdb->insert_id, 'user_type' => $user_type ], __( 'User type added.', 'el-core' ) );
    }

    /**
     * AJAX: Admin renames a user type (only allowed before stakeholder has submitted answers).
     */
    public function handle_rename_user_type( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $user_type  = sanitize_text_field( wp_unslash( $data['user_type'] ?? '' ) );
        if ( ! $journey_id || ! $user_type ) {
            EL_AJAX_Handler::error( __( 'Journey ID and user type name are required.', 'el-core' ) );
            return;
        }
        global $wpdb;
        $table   = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $journey_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ) );
            return;
        }
        if ( ! in_array( $journey->status, [ 'pending_assignment', 'awaiting_input' ], true ) ) {
            EL_AJAX_Handler::error( __( 'User type name cannot be changed after the stakeholder has submitted their answers.', 'el-core' ) );
            return;
        }
        $wpdb->update( $table, [ 'user_type' => $user_type ], [ 'id' => $journey_id ] );
        EL_AJAX_Handler::success( [ 'user_type' => $user_type ], __( 'User type renamed.', 'el-core' ) );
    }

    /**
     * AJAX: Admin deletes a user type row (only allowed while still pending_assignment).
     */
    public function handle_delete_user_type( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id = absint( $data['journey_id'] ?? 0 );
        if ( ! $journey_id ) {
            EL_AJAX_Handler::error( __( 'Journey ID required.', 'el-core' ) );
            return;
        }
        global $wpdb;
        $table   = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $journey_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ) );
            return;
        }
        if ( $journey->status !== 'pending_assignment' ) {
            EL_AJAX_Handler::error( __( 'Only unassigned user types can be deleted.', 'el-core' ) );
            return;
        }
        $wpdb->delete( $table, [ 'id' => $journey_id ] );
        EL_AJAX_Handler::success( [], __( 'User type deleted.', 'el-core' ) );
    }

    public function get_user_journeys( int $project_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'el_es_user_journeys';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE project_id = %d ORDER BY id ASC",
            $project_id
        ) ) ?: [];
    }

    /**
     * AJAX: Assign (or reassign) a stakeholder to a journey row.
     * Sets assigned_to and advances status from pending_assignment → awaiting_input.
     */
    public function handle_assign_journey( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $user_id    = absint( $data['assigned_to'] ?? 0 );
        if ( ! $journey_id || ! $user_id ) {
            EL_AJAX_Handler::error( __( 'Journey ID and user ID are required.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $journey_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ) );
            return;
        }

        $new_status = ( $journey->status === 'pending_assignment' ) ? 'awaiting_input' : $journey->status;
        $wpdb->update( $table, [
            'assigned_to' => $user_id,
            'status'      => $new_status,
        ], [ 'id' => $journey_id ] );

        $assigned_user = get_userdata( $user_id );
        EL_AJAX_Handler::success(
            [ 'status' => $new_status, 'assigned_name' => $assigned_user ? $assigned_user->display_name : '' ],
            __( 'Stakeholder assigned.', 'el-core' )
        );
    }

    /**
     * AJAX: Admin runs AI Round 2 — refines workflow using admin notes.
     * Saves result to admin_workflow, advances status to admin_refined.
     */
    public function handle_refine_journey( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id  = absint( $data['journey_id'] ?? 0 );
        $project_id  = absint( $data['project_id'] ?? 0 );
        $admin_notes = sanitize_textarea_field( wp_unslash( $_POST['admin_notes'] ?? '' ) );
        if ( ! $journey_id || ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt      = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d AND project_id = %d", $journey_id, $project_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ), 404 );
            return;
        }
        if ( ! in_array( $journey->status, [ 'ai_generated', 'admin_refined' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Journey must be in ai_generated or admin_refined status to refine.', 'el-core' ) );
            return;
        }

        // Save admin notes first
        $wpdb->update( $jt, [ 'admin_notes' => $admin_notes ], [ 'id' => $journey_id ] );

        $guided_answers = $journey->guided_answers ? json_decode( $journey->guided_answers, true ) : [];
        // Use the most recent refined workflow as context if available; fall back to round 1 output
        $existing_wf    = $journey->admin_workflow ? json_decode( $journey->admin_workflow, true ) : ( $journey->ai_workflow ? json_decode( $journey->ai_workflow, true ) : null );

        $ai_result = $this->run_journey_ai_round2( $project_id, $journey, $guided_answers, $existing_wf, $admin_notes );
        if ( is_wp_error( $ai_result ) ) {
            EL_AJAX_Handler::error( $ai_result->get_error_message() );
            return;
        }

        $wpdb->update( $jt, [
            'admin_workflow' => wp_json_encode( $ai_result ),
            'status'         => 'admin_refined',
        ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success(
            [ 'status' => 'admin_refined', 'workflow' => $ai_result ],
            __( 'Workflow refined successfully.', 'el-core' )
        );
    }

    /**
     * AJAX: Admin sends a journey for stakeholder consensus review.
     * Creates a row in el_es_journey_reviews, sets status → in_review.
     */
    public function handle_send_journey_review( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $deadline   = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );
        if ( ! $journey_id ) {
            EL_AJAX_Handler::error( __( 'Journey ID required.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt       = $wpdb->prefix . 'el_es_user_journeys';
        $jrt      = $wpdb->prefix . 'el_es_journey_reviews';
        $journey  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d", $journey_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ) );
            return;
        }
        if ( ! in_array( $journey->status, [ 'admin_refined' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Journey must be in admin_refined status to send for review.', 'el-core' ) );
            return;
        }

        // Close any existing open review for this journey
        $wpdb->update( $jrt, [ 'status' => 'closed' ], [ 'journey_id' => $journey_id, 'status' => 'open' ] );

        // Next round
        $last_round = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(round) FROM {$jrt} WHERE journey_id = %d",
            $journey_id
        ) );
        $round = $last_round + 1;
        $deadline_dt = $deadline ? date( 'Y-m-d 23:59:59', strtotime( $deadline ) ) : null;

        $wpdb->insert( $jrt, [
            'journey_id'  => $journey_id,
            'round'       => $round,
            'sent_by'     => get_current_user_id(),
            'deadline'    => $deadline_dt,
            'status'      => 'open',
            'created_at'  => current_time( 'mysql' ),
        ] );
        $review_id = $wpdb->insert_id;

        $wpdb->update( $jt, [ 'status' => 'in_review' ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success(
            [ 'review_id' => $review_id, 'round' => $round, 'status' => 'in_review' ],
            sprintf( __( 'Journey sent for review — Round %d.', 'el-core' ), $round )
        );
    }

    /**
     * AJAX: Admin resets an in_review journey back to admin_refined.
     * Cancels the active review round.
     */
    public function handle_reset_journey_review( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id = absint( $data['journey_id'] ?? 0 );
        if ( ! $journey_id ) {
            EL_AJAX_Handler::error( __( 'Journey ID required.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt  = $wpdb->prefix . 'el_es_user_journeys';
        $jrt = $wpdb->prefix . 'el_es_journey_reviews';

        $wpdb->update( $jrt, [ 'status' => 'closed' ], [ 'journey_id' => $journey_id, 'status' => 'open' ] );
        $wpdb->update( $jt,  [ 'status' => 'admin_refined' ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success( [ 'status' => 'admin_refined' ], __( 'Journey reset to draft. Review cancelled.', 'el-core' ) );
    }

    /**
     * AJAX: Admin locks a journey (status must be approved).
     */
    public function handle_lock_journey( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id = absint( $data['journey_id'] ?? 0 );
        if ( ! $journey_id ) {
            EL_AJAX_Handler::error( __( 'Journey ID required.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $journey_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ) );
            return;
        }
        if ( $journey->status !== 'approved' ) {
            EL_AJAX_Handler::error( __( 'Journey must be approved before locking.', 'el-core' ) );
            return;
        }

        $wpdb->update( $table, [
            'status'    => 'locked',
            'locked_at' => current_time( 'mysql' ),
            'locked_by' => get_current_user_id(),
        ], [ 'id' => $journey_id ] );

        // Check if all journeys for the project are now locked
        $project_id   = (int) $journey->project_id;
        $total         = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE project_id = %d", $project_id ) );
        $locked        = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE project_id = %d AND status = 'locked'", $project_id ) );
        $all_locked    = ( $total > 0 && $locked === $total );

        EL_AJAX_Handler::success(
            [ 'status' => 'locked', 'all_locked' => $all_locked, 'locked_count' => $locked, 'total_count' => $total ],
            __( 'Journey locked.', 'el-core' )
        );
    }

    /**
     * AJAX: Post a comment on a journey step (or overall journey if step_key is empty).
     * Callable by any authenticated stakeholder (nopriv variant for logged-in portal users).
     */
    public function handle_post_journey_comment( array $data ): void {
        $user_id    = get_current_user_id();
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $review_id  = absint( $data['review_id'] ?? 0 );
        $step_key   = sanitize_text_field( $data['step_key'] ?? '' );
        $comment    = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );
        $parent_id  = absint( $data['parent_id'] ?? 0 );

        if ( ! $user_id || ! $journey_id || ! $review_id || trim( $comment ) === '' ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt  = $wpdb->prefix . 'el_es_user_journeys';
        $jrt = $wpdb->prefix . 'el_es_journey_reviews';
        $jct = $wpdb->prefix . 'el_es_journey_comments';

        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d", $journey_id ) );
        if ( ! $journey || $journey->status !== 'in_review' ) {
            EL_AJAX_Handler::error( __( 'Journey is not currently in review.', 'el-core' ) );
            return;
        }
        if ( ! $this->is_stakeholder( (int) $journey->project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        $wpdb->insert( $jct, [
            'review_id'  => $review_id,
            'journey_id' => $journey_id,
            'step_key'   => $step_key ?: null,
            'parent_id'  => $parent_id ?: 0,
            'user_id'    => $user_id,
            'comment'    => $comment,
            'created_at' => current_time( 'mysql' ),
        ] );

        $user = get_userdata( $user_id );
        EL_AJAX_Handler::success( [
            'comment_id'   => $wpdb->insert_id,
            'comment'      => $comment,
            'author'       => $user ? $user->display_name : __( 'Unknown', 'el-core' ),
            'created_at'   => current_time( 'mysql' ),
        ], __( 'Comment posted.', 'el-core' ) );
    }

    /**
     * AJAX: Upsert a step verdict for the current user on a journey step.
     */
    public function handle_journey_step_verdict( array $data ): void {
        $user_id    = get_current_user_id();
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $review_id  = absint( $data['review_id'] ?? 0 );
        $step_key   = sanitize_text_field( $data['step_key'] ?? '' );
        $verdict    = sanitize_text_field( $data['verdict'] ?? '' );

        if ( ! $user_id || ! $journey_id || ! $review_id || ! in_array( $verdict, [ 'approved', 'needs_revision' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Invalid verdict data.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt  = $wpdb->prefix . 'el_es_user_journeys';
        $jct = $wpdb->prefix . 'el_es_journey_comments';

        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d", $journey_id ) );
        if ( ! $journey || $journey->status !== 'in_review' ) {
            EL_AJAX_Handler::error( __( 'Journey is not currently in review.', 'el-core' ) );
            return;
        }
        if ( ! $this->is_stakeholder( (int) $journey->project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        // Upsert: update existing verdict comment for this user+step, or insert new one
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$jct} WHERE review_id = %d AND journey_id = %d AND step_key = %s AND user_id = %d AND comment = '__verdict__' LIMIT 1",
            $review_id, $journey_id, $step_key, $user_id
        ) );

        if ( $existing ) {
            $wpdb->update( $jct, [ 'verdict' => $verdict ], [ 'id' => $existing->id ] );
        } else {
            $wpdb->insert( $jct, [
                'review_id'  => $review_id,
                'journey_id' => $journey_id,
                'step_key'   => $step_key,
                'parent_id'  => 0,
                'user_id'    => $user_id,
                'comment'    => '__verdict__',
                'verdict'    => $verdict,
                'created_at' => current_time( 'mysql' ),
            ] );
        }

        EL_AJAX_Handler::success( [ 'verdict' => $verdict ], __( 'Verdict saved.', 'el-core' ) );
    }

    /**
     * AJAX: Stakeholder saves an inline edit to a step (label + description) during in_review.
     * Saves the updated workflow back to admin_workflow on the journey row.
     */
    public function handle_save_journey_step_edit( array $data ): void {
        $user_id    = get_current_user_id();
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $review_id  = absint( $data['review_id'] ?? 0 );
        $step_key   = sanitize_text_field( $data['step_key'] ?? '' );
        $action     = sanitize_text_field( $data['edit_action'] ?? 'update' ); // update | insert_after | remove
        $new_label  = sanitize_text_field( wp_unslash( $_POST['new_label'] ?? '' ) );
        $new_desc   = sanitize_textarea_field( wp_unslash( $_POST['new_desc'] ?? '' ) );

        if ( ! $user_id || ! $journey_id ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt      = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d", $journey_id ) );

        if ( ! $journey || $journey->status !== 'in_review' ) {
            EL_AJAX_Handler::error( __( 'Journey is not currently in review.', 'el-core' ) );
            return;
        }
        if ( ! $this->is_stakeholder( (int) $journey->project_id ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }

        // Work on the most recent workflow
        $wf_raw = $journey->admin_workflow ?: $journey->ai_workflow;
        $wf     = $wf_raw ? json_decode( $wf_raw, true ) : null;
        if ( ! $wf || empty( $wf['steps'] ) ) {
            EL_AJAX_Handler::error( __( 'Workflow not found.', 'el-core' ) );
            return;
        }

        $steps    = $wf['steps'];
        $step_idx = -1;
        foreach ( $steps as $i => $s ) {
            if ( ( $s['id'] ?? '' ) === $step_key ) {
                $step_idx = $i;
                break;
            }
        }

        if ( $action === 'update' ) {
            if ( $step_idx === -1 ) {
                EL_AJAX_Handler::error( __( 'Step not found.', 'el-core' ) );
                return;
            }
            $steps[ $step_idx ]['label']       = $new_label;
            $steps[ $step_idx ]['description'] = $new_desc;

        } elseif ( $action === 'insert_after' ) {
            $new_step = [
                'id'          => 'step_' . ( count( $steps ) + 1 ) . '_' . time(),
                'label'       => $new_label ?: __( 'New step', 'el-core' ),
                'description' => $new_desc ?: '',
                'branch'      => null,
            ];
            if ( $step_idx === -1 ) {
                $steps[] = $new_step;
            } else {
                array_splice( $steps, $step_idx + 1, 0, [ $new_step ] );
            }

        } elseif ( $action === 'remove' ) {
            if ( $step_idx === -1 ) {
                EL_AJAX_Handler::error( __( 'Step not found.', 'el-core' ) );
                return;
            }
            array_splice( $steps, $step_idx, 1 );
        }

        $wf['steps'] = array_values( $steps );
        $wpdb->update( $jt, [ 'admin_workflow' => wp_json_encode( $wf ) ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success( [ 'workflow' => $wf ], __( 'Step updated.', 'el-core' ) );
    }

    /**
     * AJAX: DM submits final decision (approved / needs_revision) on active journey review.
     */
    public function handle_dm_journey_decision( array $data ): void {
        $user_id    = get_current_user_id();
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $review_id  = absint( $data['review_id'] ?? 0 );
        $decision   = sanitize_text_field( $data['decision'] ?? '' );
        $dm_note    = sanitize_textarea_field( wp_unslash( $_POST['dm_note'] ?? '' ) );

        if ( ! $user_id || ! $journey_id || ! $review_id || ! in_array( $decision, [ 'approved', 'needs_revision' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Invalid decision data.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt  = $wpdb->prefix . 'el_es_user_journeys';
        $jrt = $wpdb->prefix . 'el_es_journey_reviews';

        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d", $journey_id ) );
        if ( ! $journey || $journey->status !== 'in_review' ) {
            EL_AJAX_Handler::error( __( 'Journey is not currently in review.', 'el-core' ) );
            return;
        }
        if ( ! $this->is_decision_maker( (int) $journey->project_id ) ) {
            EL_AJAX_Handler::error( __( 'Only the Decision Maker can submit the final decision.', 'el-core' ), 403 );
            return;
        }

        $wpdb->update( $jrt, [
            'dm_decision'   => $decision,
            'dm_note'       => $dm_note,
            'dm_decided_at' => current_time( 'mysql' ),
        ], [ 'id' => $review_id ] );

        if ( $decision === 'approved' ) {
            $wpdb->update( $jt, [ 'status' => 'approved' ], [ 'id' => $journey_id ] );
        }
        // 'needs_revision' keeps status as in_review — admin resets to admin_refined to make changes

        EL_AJAX_Handler::success(
            [ 'decision' => $decision, 'status' => $decision === 'approved' ? 'approved' : 'in_review' ],
            $decision === 'approved' ? __( 'Journey approved!', 'el-core' ) : __( 'Revision requested. The project manager will make changes and re-send.', 'el-core' )
        );
    }

    /**
     * AJAX: DM sends the reviewed answers forward to the admin for AI generation.
     * Status: pending_dm_review → awaiting_ai (admin will trigger AI manually).
     * DM can also attach notes for the admin.
     */
    public function handle_dm_send_to_admin( array $data ): void {
        $user_id    = get_current_user_id();
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $project_id = absint( $data['project_id'] ?? 0 );
        $dm_notes   = sanitize_textarea_field( wp_unslash( $_POST['dm_notes'] ?? '' ) );

        if ( ! $user_id || ! $journey_id || ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt      = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d AND project_id = %d", $journey_id, $project_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ), 404 );
            return;
        }
        if ( $journey->status !== 'pending_dm_review' ) {
            EL_AJAX_Handler::error( __( 'Journey is not awaiting DM review.', 'el-core' ) );
            return;
        }
        if ( ! $this->is_decision_maker( $project_id ) ) {
            EL_AJAX_Handler::error( __( 'Only the Decision Maker can send answers to the project manager.', 'el-core' ), 403 );
            return;
        }

        // If the DM submitted edited answers, save them back to guided_answers
        $questions = [
            1 => 'How does this person first find or arrive at the website?',
            2 => 'Do they need to create an account or log in to use the site — or can they get what they need without one?',
            3 => 'Once they\'re in, what is the first thing they need to do?',
            4 => 'What are the main things this person will do on the site on a regular basis?',
            5 => 'What does success look like for this person — what have they accomplished when they leave the site happy?',
            6 => 'Is there anything this person should NOT be able to do, or any frustration you want to prevent?',
        ];
        $has_edited_answers = false;
        $edited_answers     = [];
        for ( $n = 1; $n <= 6; $n++ ) {
            if ( isset( $_POST[ 'answer_' . $n ] ) ) {
                $has_edited_answers = true;
                $edited_answers[]   = [
                    'question' => $questions[ $n ],
                    'answer'   => sanitize_textarea_field( wp_unslash( $_POST[ 'answer_' . $n ] ) ),
                ];
            }
        }
        if ( $has_edited_answers && ! empty( $edited_answers ) ) {
            $wpdb->update( $jt, [ 'guided_answers' => wp_json_encode( $edited_answers ) ], [ 'id' => $journey_id ] );
        }

        $wpdb->update( $jt, [
            'status'     => 'awaiting_ai',
            'admin_notes' => $dm_notes ?: $journey->admin_notes,
        ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success(
            [ 'status' => 'awaiting_ai' ],
            __( 'Answers sent to the project manager. They will generate the workflow shortly.', 'el-core' )
        );
    }

    /**
     * AJAX: Admin manually triggers Round 1 AI generation.
     * Status: awaiting_ai → ai_generated.
     */
    public function handle_generate_journey_ai( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $journey_id || ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt      = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d AND project_id = %d", $journey_id, $project_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ), 404 );
            return;
        }
        if ( ! in_array( $journey->status, [ 'awaiting_ai', 'ai_generated' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Journey must be in awaiting_ai or ai_generated status to generate.', 'el-core' ) );
            return;
        }

        $guided_answers = $journey->guided_answers ? json_decode( $journey->guided_answers, true ) : [];
        $ai_result      = $this->run_journey_ai_round1( $project_id, $journey, $guided_answers );

        if ( is_wp_error( $ai_result ) ) {
            EL_AJAX_Handler::error( $ai_result->get_error_message() );
            return;
        }

        $wpdb->update( $jt, [
            'ai_workflow' => wp_json_encode( $ai_result ),
            'status'      => 'ai_generated',
        ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success(
            [ 'status' => 'ai_generated', 'workflow' => $ai_result ],
            __( 'Workflow generated successfully.', 'el-core' )
        );
    }

    /**
     * AJAX: Admin saves a manually edited version of the workflow.
     * Accepts a full workflow JSON string and saves to admin_workflow, status → admin_refined.
     */
    public function handle_save_journey_workflow( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $journey_id    = absint( $data['journey_id'] ?? 0 );
        $project_id    = absint( $data['project_id'] ?? 0 );
        $workflow_json = wp_unslash( $_POST['workflow_json'] ?? '' );

        if ( ! $journey_id || ! $project_id || ! $workflow_json ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        $decoded = json_decode( $workflow_json, true );
        if ( ! is_array( $decoded ) || empty( $decoded['steps'] ) ) {
            EL_AJAX_Handler::error( __( 'Invalid workflow JSON.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $jt      = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jt} WHERE id = %d AND project_id = %d", $journey_id, $project_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ), 404 );
            return;
        }
        if ( ! in_array( $journey->status, [ 'ai_generated', 'admin_refined' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Cannot edit workflow in current status.', 'el-core' ) );
            return;
        }

        $wpdb->update( $jt, [
            'admin_workflow' => wp_json_encode( $decoded ),
            'status'         => 'admin_refined',
        ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success(
            [ 'status' => 'admin_refined' ],
            __( 'Workflow saved.', 'el-core' )
        );
    }

    /**
     * AJAX: Admin marks the journey user-type list as ready for the DM to view.
     * Sets journey_list_approved_at on the project.
     */
    public function handle_approve_journey_list( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Project ID required.', 'el-core' ) );
            return;
        }
        global $wpdb;
        $projects_table = $wpdb->prefix . 'el_es_projects';
        $wpdb->update( $projects_table, [
            'journey_list_approved_at' => current_time( 'mysql' ),
        ], [ 'id' => $project_id ] );
        EL_AJAX_Handler::success( [], __( 'Journey list sent to client.', 'el-core' ) );
    }

    /**
     * AJAX: Admin retries AI generation for a journey stuck at awaiting_ai.
     */
    public function handle_retry_journey_ai( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Permission denied.', 'el-core' ), 403 );
            return;
        }
        $project_id = absint( $data['project_id'] ?? 0 );
        $journey_id = absint( $data['journey_id'] ?? 0 );
        if ( ! $project_id || ! $journey_id ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }
        global $wpdb;
        $journeys_table = $wpdb->prefix . 'el_es_user_journeys';
        $journey = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$journeys_table} WHERE id = %d AND project_id = %d", $journey_id, $project_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ), 404 );
            return;
        }
        if ( ! in_array( $journey->status, [ 'awaiting_ai', 'ai_generated' ], true ) ) {
            EL_AJAX_Handler::error( __( 'Journey is not in a retryable state.', 'el-core' ) );
            return;
        }
        $guided_answers = $journey->guided_answers ? json_decode( $journey->guided_answers, true ) : [];
        if ( empty( $guided_answers ) ) {
            EL_AJAX_Handler::error( __( 'No saved answers found to regenerate from.', 'el-core' ) );
            return;
        }

        $ai_result = $this->run_journey_ai_round1( $project_id, $journey, $guided_answers );
        if ( is_wp_error( $ai_result ) ) {
            EL_AJAX_Handler::error( $ai_result->get_error_message() );
            return;
        }

        $wpdb->update( $journeys_table, [
            'ai_workflow' => wp_json_encode( $ai_result ),
            'status'      => 'ai_generated',
        ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success( [], __( 'AI workflow regenerated.', 'el-core' ) );
    }

    /**
     * AJAX: DM assigns (or reassigns) a stakeholder to a journey from the portal.
     * Requires es_decision_maker capability for this project.
     */
    public function handle_dm_assign_journey( array $data ): void {
        $user_id    = get_current_user_id();
        $project_id = absint( $data['project_id'] ?? 0 );
        $journey_id = absint( $data['journey_id'] ?? 0 );
        $assignee   = absint( $data['assigned_to'] ?? 0 );

        if ( ! $user_id || ! $project_id || ! $journey_id || ! $assignee ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        // Verify the caller is the DM (or an admin) for this project
        global $wpdb;
        $projects_table = $wpdb->prefix . 'el_es_projects';
        $project        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$projects_table} WHERE id = %d", $project_id ) );
        if ( ! $project ) {
            EL_AJAX_Handler::error( __( 'Project not found.', 'el-core' ), 404 );
            return;
        }
        $is_admin    = el_core_can( 'manage_expand_site' );
        $is_dm_col   = (int) $project->decision_maker_id === $user_id;
        // Also check stakeholders table — DM may be stored there with role 'decision_maker'
        $sh_table    = $wpdb->prefix . 'el_es_stakeholders';
        $is_dm_row   = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$sh_table} WHERE project_id = %d AND user_id = %d AND role = 'decision_maker' LIMIT 1",
            $project_id, $user_id
        ) );
        $is_dm       = $is_dm_col || $is_dm_row;
        if ( ! $is_dm && ! $is_admin ) {
            EL_AJAX_Handler::error( __( 'Only the Decision Maker can assign journeys.', 'el-core' ), 403 );
            return;
        }

        $journeys_table = $wpdb->prefix . 'el_es_user_journeys';
        $journey        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$journeys_table} WHERE id = %d AND project_id = %d", $journey_id, $project_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ) );
            return;
        }

        // Can only assign when awaiting assignment or awaiting input
        if ( ! in_array( $journey->status, [ 'pending_assignment', 'awaiting_input' ], true ) ) {
            EL_AJAX_Handler::error( __( 'This journey can no longer be reassigned.', 'el-core' ) );
            return;
        }

        $new_status = 'awaiting_input';
        $wpdb->update( $journeys_table, [
            'assigned_to' => $assignee,
            'status'      => $new_status,
        ], [ 'id' => $journey_id ] );

        $assignee_user = get_userdata( $assignee );
        EL_AJAX_Handler::success(
            [ 'status' => $new_status, 'assigned_name' => $assignee_user ? $assignee_user->display_name : '' ],
            __( 'Stakeholder assigned.', 'el-core' )
        );
    }

    /**
     * AJAX: Assigned stakeholder submits 6 guided answers.
     * Saves guided_answers JSON, fires Round 1 AI, advances status to ai_generated.
     */
    public function handle_submit_journey_answers( array $data ): void {
        $user_id    = get_current_user_id();
        $project_id = absint( $data['project_id'] ?? 0 );
        $journey_id = absint( $data['journey_id'] ?? 0 );

        if ( ! $user_id || ! $project_id || ! $journey_id ) {
            EL_AJAX_Handler::error( __( 'Missing required fields.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $journeys_table = $wpdb->prefix . 'el_es_user_journeys';
        $journey        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$journeys_table} WHERE id = %d AND project_id = %d", $journey_id, $project_id ) );
        if ( ! $journey ) {
            EL_AJAX_Handler::error( __( 'Journey not found.', 'el-core' ) );
            return;
        }

        // Only the assigned stakeholder (or an admin) may submit
        $is_assigned = (int) $journey->assigned_to === $user_id;
        $is_admin    = el_core_can( 'manage_expand_site' );
        if ( ! $is_assigned && ! $is_admin ) {
            EL_AJAX_Handler::error( __( 'You are not assigned to this journey.', 'el-core' ), 403 );
            return;
        }
        if ( $journey->status !== 'awaiting_input' ) {
            EL_AJAX_Handler::error( __( 'Answers have already been submitted for this journey.', 'el-core' ) );
            return;
        }

        // Collect and validate the 6 answers
        $questions = [
            1 => 'How does this person first find or arrive at the website?',
            2 => 'Do they need to create an account or log in to use the site — or can they get what they need without one?',
            3 => 'Once they\'re in, what is the first thing they need to do?',
            4 => 'What are the main things this person will do on the site on a regular basis?',
            5 => 'What does success look like for this person — what have they accomplished when they leave the site happy?',
            6 => 'Is there anything this person should NOT be able to do, or any frustration you want to prevent?',
        ];

        $guided_answers = [];
        foreach ( $questions as $n => $q_text ) {
            $answer = sanitize_textarea_field( wp_unslash( $_POST[ 'answer_' . $n ] ?? '' ) );
            if ( trim( $answer ) === '' ) {
                EL_AJAX_Handler::error( sprintf( __( 'Answer %d is required.', 'el-core' ), $n ) );
                return;
            }
            $guided_answers[] = [ 'question' => $q_text, 'answer' => $answer ];
        }

        $guided_answers_json = wp_json_encode( $guided_answers );
        $wpdb->update( $journeys_table, [
            'guided_answers' => $guided_answers_json,
            'status'         => 'pending_dm_review',
        ], [ 'id' => $journey_id ] );

        EL_AJAX_Handler::success(
            [ 'status' => 'pending_dm_review' ],
            __( 'Thank you — your answers have been saved. The Decision Maker will review them before sending to the project manager.', 'el-core' )
        );
    }

    /**
     * Strip markdown fences from an AI response and JSON-decode it.
     * Returns the decoded array on success, or WP_Error on parse failure.
     */
    private function parse_journey_ai_response( string $raw ): array|WP_Error {
        $raw = trim( $raw );
        $raw = preg_replace( '/^```(?:json)?\s*/im', '', $raw );
        $raw = preg_replace( '/\s*```\s*$/im', '', $raw );
        $raw = trim( $raw );

        // If the AI wrapped the object in a "workflow" key, unwrap it
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) && isset( $decoded['workflow'] ) && is_array( $decoded['workflow'] ) ) {
            $decoded = $decoded['workflow'];
        }

        if ( ! is_array( $decoded ) || empty( $decoded['steps'] ) ) {
            return new WP_Error( 'ai_parse_error', sprintf(
                __( 'AI returned an invalid workflow structure. Raw response: %s', 'el-core' ),
                substr( $raw, 0, 500 )
            ) );
        }

        return $decoded;
    }

    /**
     * Run Round 1 AI for a journey: generates structured workflow JSON from 6 guided answers.
     * Returns decoded array on success, WP_Error on failure.
     */
    private function run_journey_ai_round1( int $project_id, object $journey, array $guided_answers ): array|WP_Error {
        if ( ! $this->core->ai->is_configured() ) {
            return new WP_Error( 'ai_not_configured', __( 'AI is not configured.', 'el-core' ) );
        }
        if ( empty( $guided_answers ) ) {
            return new WP_Error( 'no_answers', __( 'No answers have been saved for this journey yet.', 'el-core' ) );
        }

        $definition = $this->get_project_definition( $project_id );
        $project    = $this->get_project( $project_id );

        $site_description = $definition->site_description ?? 'N/A';
        $primary_goal     = $definition->primary_goal ?? 'N/A';
        $site_type        = $definition->site_type ?? 'N/A';
        $user_type        = $journey->user_type;

        $qa_text = '';
        foreach ( $guided_answers as $i => $qa ) {
            $qa_text .= 'Q' . ( $i + 1 ) . ': ' . $qa['question'] . "\n";
            $qa_text .= 'A: ' . $qa['answer'] . "\n\n";
        }

        $prompt  = "OUTPUT REQUIREMENT: Respond with ONLY a single valid JSON object. No markdown, no code fences, no prose before or after the JSON.\n";
        $prompt .= "The JSON must have exactly these keys: summary (string, ONE sentence max), steps (array, 5-10 items), implied_pages (array of strings), open_questions (array of strings).\n";
        $prompt .= "Each step object must have: id (\"step_1\", \"step_2\", ...), label (3-5 words), description (1 sentence), branch (null or object).\n\n";
        $prompt .= "TASK: You are a UX designer. Read the client answers below and produce a structured user journey workflow for the specified user type.\n\n";
        $prompt .= "Project Context:\n";
        $prompt .= "- Site Description: {$site_description}\n";
        $prompt .= "- Primary Goal: {$primary_goal}\n";
        $prompt .= "- Site Type: {$site_type}\n";
        $prompt .= "- User Type: {$user_type}\n\n";
        $prompt .= "Client's Answers:\n{$qa_text}\n";
        $prompt .= "EXAMPLE of the required JSON shape (replace all values with real content):\n";
        $prompt .= '{"summary":"One sentence describing the overall journey.","steps":[{"id":"step_1","label":"Arrive at site","description":"User lands on the homepage via a shared link.","branch":null},{"id":"step_2","label":"Log in or sign up","description":"User chooses to log in or create a new account.","branch":{"condition":"Has account?","yes":"step_3","no":"step_2b"}}],"implied_pages":["Homepage","Login"],"open_questions":[]}' . "\n";
        $prompt .= "Now produce the JSON for the user type above. Remember: summary must be ONE sentence. steps must be a JSON array. Do not write any text outside the JSON object.\n";

        $response = $this->core->ai->complete( [
            'prompt'     => $prompt,
            'max_tokens' => 4096,
        ] );
        if ( empty( $response['success'] ) ) {
            return new WP_Error( 'ai_error', $response['error'] ?? __( 'AI request failed.', 'el-core' ) );
        }

        $decoded = $this->parse_journey_ai_response( $response['content'] ?? '' );
        if ( is_wp_error( $decoded ) ) {
            // Auto-retry once — AI occasionally returns a non-JSON response on first attempt
            $response2 = $this->core->ai->complete( [ 'prompt' => $prompt, 'max_tokens' => 4096 ] );
            if ( ! empty( $response2['success'] ) ) {
                $decoded = $this->parse_journey_ai_response( $response2['content'] ?? '' );
            }
        }
        if ( is_wp_error( $decoded ) ) {
            return $decoded;
        }

        return $decoded;
    }

    /**
     * Run Round 2 AI for a journey: refines workflow using admin notes.
     * Returns decoded array on success, WP_Error on failure.
     */
    private function run_journey_ai_round2( int $project_id, object $journey, array $guided_answers, ?array $existing_wf, string $admin_notes ): array|WP_Error {
        if ( ! $this->core->ai->is_configured() ) {
            return new WP_Error( 'ai_not_configured', __( 'AI is not configured.', 'el-core' ) );
        }

        $definition       = $this->get_project_definition( $project_id );
        $site_description = $definition->site_description ?? 'N/A';
        $primary_goal     = $definition->primary_goal ?? 'N/A';
        $site_type        = $definition->site_type ?? 'N/A';
        $user_type        = $journey->user_type;

        $qa_text = '';
        foreach ( $guided_answers as $i => $qa ) {
            $qa_text .= 'Q' . ( $i + 1 ) . ': ' . $qa['question'] . "\n";
            $qa_text .= 'A: ' . $qa['answer'] . "\n\n";
        }

        $existing_json = $existing_wf ? wp_json_encode( $existing_wf ) : 'N/A';

        $prompt  = "OUTPUT REQUIREMENT: Respond with ONLY a single valid JSON object. No markdown, no code fences, no prose before or after the JSON.\n";
        $prompt .= "The JSON must have exactly these keys: summary (string, ONE sentence max), steps (array, 5-12 items), implied_pages (array of strings), open_questions (array of strings).\n";
        $prompt .= "Each step object must have: id (\"step_1\", \"step_2\", ...), label (3-5 words), description (1 sentence), branch (null or object).\n\n";
        $prompt .= "TASK: You are a UX designer refining a user journey workflow. Incorporate the admin's instructions and resolve open questions where possible.\n\n";
        $prompt .= "Project Context:\n";
        $prompt .= "- Site Description: {$site_description}\n";
        $prompt .= "- Primary Goal: {$primary_goal}\n";
        $prompt .= "- Site Type: {$site_type}\n";
        $prompt .= "- User Type: {$user_type}\n\n";
        $prompt .= "Original Client Answers:\n{$qa_text}\n";
        $prompt .= "Existing Workflow:\n{$existing_json}\n\n";
        $prompt .= "Admin Instructions:\n{$admin_notes}\n\n";
        $prompt .= "EXAMPLE of the required JSON shape (replace all values with real content):\n";
        $prompt .= '{"summary":"One sentence describing the overall journey.","steps":[{"id":"step_1","label":"Arrive at site","description":"User lands on the homepage via a shared link.","branch":null}],"implied_pages":["Homepage"],"open_questions":[]}' . "\n";
        $prompt .= "Now produce the refined JSON. Remember: summary must be ONE sentence. steps must be a JSON array. Do not write any text outside the JSON object.\n";

        $response = $this->core->ai->complete( [
            'prompt'     => $prompt,
            'max_tokens' => 4096,
        ] );
        if ( empty( $response['success'] ) ) {
            return new WP_Error( 'ai_error', $response['error'] ?? __( 'AI request failed.', 'el-core' ) );
        }

        $decoded = $this->parse_journey_ai_response( $response['content'] ?? '' );
        if ( is_wp_error( $decoded ) ) {
            // Auto-retry once — AI occasionally returns a non-JSON response on first attempt
            $response2 = $this->core->ai->complete( [ 'prompt' => $prompt, 'max_tokens' => 4096 ] );
            if ( ! empty( $response2['success'] ) ) {
                $decoded = $this->parse_journey_ai_response( $response2['content'] ?? '' );
            }
        }
        if ( is_wp_error( $decoded ) ) {
            return $decoded;
        }

        return $decoded;
    }

    /**
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

        // Capture snapshot of current definition fields
        $snapshot = wp_json_encode( [
            'site_description'  => $definition->site_description ?? '',
            'primary_goal'      => $definition->primary_goal ?? '',
            'secondary_goals'   => $definition->secondary_goals ?? '',
            'target_customers'  => $definition->target_customers ?? '',
            'user_types'        => $definition->user_types ?? '',
            'site_type'         => $definition->site_type ?? '',
        ] );

        $wpdb->insert( $reviews_table, [
            'project_id' => $project_id,
            'round'      => $round,
            'sent_by'    => get_current_user_id(),
            'sent_at'    => current_time( 'mysql' ),
            'deadline'   => $deadline_dt,
            'status'     => 'open',
            'snapshot'   => $snapshot,
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

        // Get previous round snapshot for "Updated since last round" badges
        $prev_snapshot = null;
        if ( $review && (int) $review->round > 1 ) {
            global $wpdb;
            $rt = $wpdb->prefix . 'el_es_definition_reviews';
            $prev_round = $wpdb->get_row( $wpdb->prepare(
                "SELECT snapshot FROM {$rt} WHERE project_id = %d AND round = %d",
                $project_id, (int) $review->round - 1
            ) );
            if ( $prev_round && ! empty( $prev_round->snapshot ) ) {
                $prev_snapshot = json_decode( $prev_round->snapshot, true );
            }
        }

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
            'prev_snapshot'   => $prev_snapshot,
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
     * AJAX: Post a comment (or reply) on a definition field.
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
     * If needs_revision → review stays open; DM note stored as banner context, contributors keep editing.
     * If accepted → review closed, definition status set to approved.
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

        if ( $decision === 'accepted' ) {
            // Close the review and mark definition as approved
            $wpdb->update( $reviews_table, [
                'status'        => 'closed',
                'dm_decision'   => 'accepted',
                'dm_note'       => $note,
                'dm_decided_at' => $now,
                'dm_decided_by' => get_current_user_id(),
            ], [ 'id' => $review_id ] );

            $this->core->database->update( 'el_es_project_definition', [
                'review_status' => 'approved',
                'updated_at'    => $now,
            ], [ 'project_id' => $project_id ] );

            EL_AJAX_Handler::success(
                [ 'new_status' => 'approved', 'decision' => 'accepted' ],
                __( 'Definition approved! The admin can now lock it and proceed.', 'el-core' )
            );

        } else {
            // needs_revision: keep review open, record the DM note on the review row
            $wpdb->update( $reviews_table, [
                'dm_decision'   => 'needs_revision',
                'dm_note'       => $note,
                'dm_decided_at' => $now,
                'dm_decided_by' => get_current_user_id(),
            ], [ 'id' => $review_id ] );

            // Definition stays pending_review so the consensus UI remains active
            EL_AJAX_Handler::success(
                [ 'new_status' => 'pending_review', 'decision' => 'needs_revision', 'dm_note' => $note ],
                __( 'Your note has been posted. The team can continue editing.', 'el-core' )
            );
        }
    }

    /**
     * Admin escape hatch: cancel active review and reset definition to draft.
     */
    public function handle_reset_definition( array $data ): void {
        if ( ! el_core_can( 'manage_expand_site' ) ) {
            EL_AJAX_Handler::error( __( 'Insufficient permissions.', 'el-core' ), 403 );
            return;
        }

        $project_id = absint( $data['project_id'] ?? 0 );
        if ( ! $project_id ) {
            EL_AJAX_Handler::error( __( 'Missing project ID.', 'el-core' ) );
            return;
        }

        global $wpdb;
        $reviews_table = $wpdb->prefix . 'el_es_definition_reviews';
        $now = current_time( 'mysql' );

        // Close any open review rounds for this project
        $wpdb->update( $reviews_table, [
            'status'        => 'closed',
            'dm_decision'   => 'reset',
            'dm_decided_at' => $now,
            'dm_decided_by' => get_current_user_id(),
        ], [ 'project_id' => $project_id, 'status' => 'open' ] );

        // Return definition to draft
        $this->core->database->update( 'el_es_project_definition', [
            'review_status' => 'draft',
            'updated_at'    => $now,
        ], [ 'project_id' => $project_id ] );

        EL_AJAX_Handler::success( [], __( 'Definition reset to draft. You can now edit and re-send.', 'el-core' ) );
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
            'scope_description'      => sanitize_textarea_field( wp_unslash( $_POST['scope_description'] ?? '' ) ) ?: sanitize_textarea_field( $proposal->scope_description ),
            'goals_objectives'       => sanitize_textarea_field( wp_unslash( $_POST['goals_objectives'] ?? '' ) ) ?: sanitize_textarea_field( $proposal->goals_objectives ),
            'activities_description' => sanitize_textarea_field( wp_unslash( $_POST['activities_description'] ?? '' ) ) ?: sanitize_textarea_field( $proposal->activities_description ),
            'deliverables_text'      => sanitize_textarea_field( wp_unslash( $_POST['deliverables_text'] ?? '' ) ) ?: sanitize_textarea_field( $proposal->deliverables_text ),
            'section_situation'      => sanitize_textarea_field( wp_unslash( $_POST['section_situation'] ?? '' ) ) ?: ( $proposal->section_situation ?? '' ),
            'section_what_we_build'  => sanitize_textarea_field( wp_unslash( $_POST['section_what_we_build'] ?? '' ) ) ?: ( $proposal->section_what_we_build ?? '' ),
            'section_why_els'        => sanitize_textarea_field( wp_unslash( $_POST['section_why_els'] ?? '' ) ) ?: ( $proposal->section_why_els ?? '' ),
            'section_investment'     => sanitize_textarea_field( wp_unslash( $_POST['section_investment'] ?? '' ) ) ?: ( $proposal->section_investment ?? '' ),
            'section_next_steps'     => sanitize_textarea_field( wp_unslash( $_POST['section_next_steps'] ?? '' ) ) ?: ( $proposal->section_next_steps ?? '' ),
            'budget_low'             => floatval( $data['budget_low'] ?? $proposal->budget_low ),
            'budget_high'            => floatval( $data['budget_high'] ?? $proposal->budget_high ),
            'final_price'            => floatval( $data['final_price'] ?? $proposal->final_price ),
            'annual_platform_fee'    => floatval( $data['annual_platform_fee'] ?? $proposal->annual_platform_fee ?? 0 ),
            'first_payment_amount'   => floatval( $data['first_payment_amount'] ?? $proposal->first_payment_amount ?? 0 ),
            'final_payment_amount'   => floatval( $data['final_payment_amount'] ?? $proposal->final_payment_amount ?? 0 ),
            'terms_conditions'       => sanitize_textarea_field( wp_unslash( $_POST['terms_conditions'] ?? '' ) ) ?: sanitize_textarea_field( $proposal->terms_conditions ),
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

        // Pull pricing from most recent proposal for this project (if any), fallback to project budget
        $proposals = $this->get_proposals( $project_id );
        $current_proposal = ! empty( $proposals ) ? $proposals[0] : null;
        $final_price = $current_proposal ? (float) $current_proposal->final_price : (float) $project->budget_range_low;
        $annual_fee  = $current_proposal ? (float) ( $current_proposal->annual_platform_fee ?? 0 ) : 0;

        $first_payment = $final_price > 0 ? $final_price * 0.25 : 0;
        $final_payment = $final_price > 0 ? $final_price * 0.75 : 0;

        $price_str   = $final_price > 0 ? '$' . number_format( $final_price, 0 ) : 'TBD';
        $first_str   = $first_payment > 0 ? '$' . number_format( $first_payment, 0 ) : 'TBD';
        $final_str   = $final_payment > 0 ? '$' . number_format( $final_payment, 0 ) : 'TBD';
        $annual_str  = $annual_fee > 0 ? '$' . number_format( $annual_fee, 0 ) . '/year ($' . number_format( $annual_fee / 12, 0 ) . '/month)' : 'to be quoted separately';

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
        $prompt .= "- Development Investment: {$price_str}\n";
        $prompt .= "- First Payment (25%, due upon wireframe approval): {$first_str}\n";
        $prompt .= "- Final Payment (75%, due upon delivery): {$final_str}\n";
        $prompt .= "- Annual Platform Fee: {$annual_str}\n";
        if ( $transcript_excerpt ) {
            $prompt .= "- Discovery Transcript: " . $transcript_excerpt . "\n";
        }
        $prompt .= "\nWrite exactly these 5 sections and return them as JSON with these exact keys:\n\n";
        $prompt .= "{\n";
        $prompt .= '  "situation": "2-3 sentences that mirror the client\'s specific problem back to them. Start with their organization name. Reference specific details from the transcript. Do not use generic language. Make them feel understood.",' . "\n\n";
        $prompt .= '  "what_we_are_building": "3-4 sentences describing what the platform will do, organized by who benefits. For each user type identified, write one sentence describing what they will be able to do and what outcome that enables. Focus on capabilities and outcomes, not features or technical details.",' . "\n\n";
        $prompt .= '  "why_els": "2-3 sentences explaining why Expanded Learning Solutions is the right partner. Reference that ELS has built platforms for organizations similar to theirs. Mention that this is a custom platform built on ELS\'s proprietary EL Core system, not off-the-shelf software stitched together.",' . "\n\n";
        $prompt .= '  "investment": "Write this as a single paragraph. State the platform development investment (' . $price_str . '). Then describe the payment schedule: first payment of ' . $first_str . ' (25%) is due upon wireframe approval, and the final payment of ' . $final_str . ' (75%) is due upon delivery. Then state the annual platform fee (' . $annual_str . ' — this covers hosting, maintenance, security updates, and support) and express the monthly cost if possible. Then write one sentence comparing this to the cost of a full-time program coordinator salary or an off-the-shelf enterprise platform subscription. Make the ROI feel obvious without being salesy.",' . "\n\n";
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
    // USER SWITCHING
    // ═══════════════════════════════════════════

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
