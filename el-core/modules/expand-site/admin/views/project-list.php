<?php
/**
 * Admin View: Expand Site — Project List
 *
 * Uses EL_Admin_UI components for all rendering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$module = EL_Expand_Site_Module::instance();

// Get deadline warning threshold from settings
$warning_days = $module->core->settings->get( 'mod_expand-site', 'deadline_warning_days', 2 );

// Filters
$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
$stage_filter  = absint( $_GET['stage'] ?? 0 );
$search        = sanitize_text_field( $_GET['s'] ?? '' );

$where = [];
if ( $status_filter ) {
    $where['status'] = $status_filter;
}
if ( $stage_filter ) {
    $where['current_stage'] = $stage_filter;
}

$projects = $module->get_all_projects( $where );

// Auto-flag projects with expired deadlines
$now = current_time( 'mysql' );
foreach ( $projects as $project ) {
    if ( $project->deadline && $project->deadline < $now && ! $project->flagged_at ) {
        // Auto-flag expired projects
        $module->core->database->update( 'el_es_projects', [
            'flagged_at'  => $now,
            'flag_reason' => 'Deadline expired',
        ], [ 'id' => $project->id ] );
        $project->flagged_at = $now;
        $project->flag_reason = 'Deadline expired';
    }
}

// Apply search filter in PHP (name or client_name)
if ( $search ) {
    $search_lower = strtolower( $search );
    $projects = array_filter( $projects, function( $p ) use ( $search_lower ) {
        return strpos( strtolower( $p->name ), $search_lower ) !== false
            || strpos( strtolower( $p->client_name ), $search_lower ) !== false;
    } );
}

$html = '';

// Page header
$html .= EL_Admin_UI::page_header( [
    'title'    => __( 'Expand Site Projects', 'el-core' ),
    'subtitle' => sprintf( _n( '%d project', '%d projects', count( $projects ), 'el-core' ), count( $projects ) ),
    'actions'  => [
        [
            'label'   => __( 'New Project', 'el-core' ),
            'variant' => 'primary',
            'icon'    => 'plus-alt',
            'data'    => [ 'modal-open' => 'create-project-modal' ],
        ],
    ],
] );

// Stats
$total     = $module->count_projects();
$active    = $module->count_projects( [ 'status' => 'active' ] );
$in_review = count( array_filter( $projects, fn( $p ) => (int) $p->current_stage === 7 ) );
$completed = $module->count_projects( [ 'status' => 'completed' ] );

$html .= EL_Admin_UI::stats_grid( [
    [ 'icon' => 'portfolio',  'number' => $total,     'label' => __( 'Total Projects', 'el-core' ), 'variant' => 'primary' ],
    [ 'icon' => 'update',     'number' => $active,    'label' => __( 'Active', 'el-core' ),         'variant' => 'info' ],
    [ 'icon' => 'visibility', 'number' => $in_review, 'label' => __( 'In Review', 'el-core' ),      'variant' => 'warning' ],
    [ 'icon' => 'yes-alt',    'number' => $completed, 'label' => __( 'Completed', 'el-core' ),      'variant' => 'success' ],
] );

// Filter bar
$stage_options = [ '' => __( 'All Stages', 'el-core' ) ];
foreach ( EL_Expand_Site_Module::STAGES as $num => $stage ) {
    $stage_options[ $num ] = $num . '. ' . $stage['name'];
}

$html .= EL_Admin_UI::filter_bar( [
    'action'       => admin_url( 'admin.php' ),
    'search_value' => $search,
    'placeholder'  => __( 'Search projects...', 'el-core' ),
    'filters'      => [
        [
            'name'    => 'stage',
            'value'   => $stage_filter ?: '',
            'options' => $stage_options,
        ],
        [
            'name'    => 'status',
            'value'   => $status_filter,
            'options' => [
                ''          => __( 'All Statuses', 'el-core' ),
                'active'    => __( 'Active', 'el-core' ),
                'paused'    => __( 'Paused', 'el-core' ),
                'completed' => __( 'Completed', 'el-core' ),
                'cancelled' => __( 'Cancelled', 'el-core' ),
            ],
        ],
    ],
    'hidden' => [ 'page' => 'el-core-projects' ],
] );

// Batch-fetch definition review statuses for all projects
global $wpdb;
$def_statuses = [];
if ( ! empty( $projects ) ) {
    $project_ids = array_map( fn( $p ) => (int) $p->id, $projects );
    $placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );
    $def_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT project_id, review_status FROM {$wpdb->prefix}el_es_project_definition WHERE project_id IN ($placeholders)",
            ...$project_ids
        )
    );
    foreach ( $def_rows as $row ) {
        $def_statuses[ (int) $row->project_id ] = $row->review_status;
    }
}

// Separate projects needing attention (flagged, deadline warning, or definition action required)
$needs_attention = [];
$regular_projects = [];

foreach ( $projects as $p ) {
    $needs_attention_flag = false;
    $def_review_status    = $def_statuses[ (int) $p->id ] ?? '';

    // Check if flagged
    if ( $p->flagged_at ) {
        $needs_attention_flag = true;
    }

    // Check if deadline is approaching (within warning days)
    if ( $p->deadline && ! $p->flagged_at ) {
        $deadline_time = strtotime( $p->deadline );
        $warning_time = strtotime( "+{$warning_days} days" );
        if ( $deadline_time <= $warning_time ) {
            $needs_attention_flag = true;
        }
    }

    // Check definition status: admin needs to act on approved or needs_revision
    if ( in_array( $def_review_status, [ 'approved', 'needs_revision' ], true ) ) {
        $needs_attention_flag = true;
    }

    // Attach for use in row rendering
    $p->_def_review_status = $def_review_status;

    if ( $needs_attention_flag ) {
        $needs_attention[] = $p;
    } else {
        $regular_projects[] = $p;
    }
}

// Build "Projects Needing Attention" section
$attention_html = '';
if ( ! empty( $needs_attention ) ) {
    $attention_rows = [];
    foreach ( $needs_attention as $p ) {
        $stage_num  = (int) $p->current_stage;
        $stage_badge = EL_Admin_UI::badge( [
            'label'   => $stage_num . '. ' . $module->get_stage_name( $stage_num ),
            'variant' => EL_Expand_Site_Module::get_stage_badge_variant( $stage_num ),
        ] );
        
        $status_badges = '';
        
        // Flagged badge
        if ( $p->flagged_at ) {
            $status_badges .= EL_Admin_UI::badge( [
                'label'   => 'HELD UP',
                'variant' => 'error',
            ] );
            $status_badges .= ' ';
        }

        // Definition review status badge (action required)
        if ( isset( $p->_def_review_status ) ) {
            if ( $p->_def_review_status === 'approved' ) {
                $status_badges .= EL_Admin_UI::badge( [
                    'label'   => __( 'Lock Required', 'el-core' ),
                    'variant' => 'warning',
                ] );
                $status_badges .= ' ';
            } elseif ( $p->_def_review_status === 'needs_revision' ) {
                $status_badges .= EL_Admin_UI::badge( [
                    'label'   => __( 'Needs Revision', 'el-core' ),
                    'variant' => 'warning',
                ] );
                $status_badges .= ' ';
            }
        }

        // Deadline warning/overdue badge
        if ( $p->deadline ) {
            $deadline_time = strtotime( $p->deadline );
            $now_time = time();
            
            if ( $deadline_time < $now_time ) {
                $days_overdue = floor( ( $now_time - $deadline_time ) / 86400 );
                $status_badges .= EL_Admin_UI::badge( [
                    'label'   => $days_overdue . 'd OVERDUE',
                    'variant' => 'error',
                ] );
            } else {
                $warning_time = strtotime( "+{$warning_days} days" );
                if ( $deadline_time <= $warning_time ) {
                    $days_left = ceil( ( $deadline_time - $now_time ) / 86400 );
                    $status_badges .= EL_Admin_UI::badge( [
                        'label'   => $days_left . 'd left',
                        'variant' => 'warning',
                    ] );
                }
            }
        }
        
        $actions = EL_Admin_UI::btn( [
            'label'   => __( 'View', 'el-core' ),
            'variant' => 'primary',
            'icon'    => 'visibility',
            'url'     => admin_url( 'admin.php?page=el-core-projects&project=' . $p->id ),
        ] );
        
        $attn_client = esc_html( $p->client_name );
        if ( ! empty( $p->organization_id ) && (int) $p->organization_id > 0 ) {
            $attn_client = '<a href="' . esc_url( admin_url( 'admin.php?page=el-core-clients&client_id=' . $p->organization_id ) ) . '">'
                         . esc_html( $p->client_name ) . '</a>';
        }

        $attention_rows[] = [
            'name'      => '<strong><a href="' . esc_url( admin_url( 'admin.php?page=el-core-projects&project=' . $p->id ) ) . '">'
                          . esc_html( $p->name ) . '</a></strong>'
                          . '<br><small>' . $attn_client . '</small>',
            'stage'     => $stage_badge,
            'status'    => $status_badges,
            'deadline'  => $p->deadline ? date_i18n( 'M j, Y', strtotime( $p->deadline ) ) : '—',
            '__actions' => $actions,
        ];
    }
    
    $attention_html = EL_Admin_UI::card( [
        'title'   => __( 'Projects Needing Attention', 'el-core' ),
        'icon'    => 'warning',
        'variant' => 'warning',
        'content' => EL_Admin_UI::data_table( [
            'columns' => [
                [ 'key' => 'name',     'label' => __( 'Project / Client', 'el-core' ) ],
                [ 'key' => 'stage',    'label' => __( 'Stage', 'el-core' ) ],
                [ 'key' => 'status',   'label' => __( 'Status', 'el-core' ) ],
                [ 'key' => 'deadline', 'label' => __( 'Deadline', 'el-core' ) ],
            ],
            'rows'  => $attention_rows,
        ] ),
    ] );
}

// Project table (regular projects)
$rows = [];
foreach ( $regular_projects as $p ) {
    $stage_num  = (int) $p->current_stage;
    $stage_badge = EL_Admin_UI::badge( [
        'label'   => $stage_num . '. ' . $module->get_stage_name( $stage_num ),
        'variant' => EL_Expand_Site_Module::get_stage_badge_variant( $stage_num ),
    ] );
    $status_badge = EL_Admin_UI::badge( [
        'label'   => ucfirst( $p->status ),
        'variant' => EL_Expand_Site_Module::get_status_badge_variant( $p->status ),
    ] );

    $budget = '';
    if ( $p->final_price > 0 ) {
        $budget = '$' . number_format( $p->final_price, 0 );
    } elseif ( $p->budget_range_low > 0 || $p->budget_range_high > 0 ) {
        $budget = '$' . number_format( $p->budget_range_low, 0 )
                . ' – $' . number_format( $p->budget_range_high, 0 );
    }
    
    // Get stakeholder count
    $stakeholders = $module->get_stakeholders( (int) $p->id );
    $stakeholder_count = count( $stakeholders );
    
    // Deadline column with warning badge if approaching
    $deadline_display = '—';
    if ( $p->deadline ) {
        $deadline_time = strtotime( $p->deadline );
        $now_time = time();
        $deadline_display = date_i18n( 'M j, Y', $deadline_time );
        
        // Check if deadline is approaching
        $warning_time = strtotime( "+{$warning_days} days" );
        if ( $deadline_time <= $warning_time && $deadline_time > $now_time ) {
            $days_left = ceil( ( $deadline_time - $now_time ) / 86400 );
            $deadline_display .= ' ' . EL_Admin_UI::badge( [
                'label'   => $days_left . 'd left',
                'variant' => 'warning',
            ] );
        }
    }

    $actions = EL_Admin_UI::btn( [
        'label'   => __( 'View', 'el-core' ),
        'variant' => 'ghost',
        'icon'    => 'visibility',
        'url'     => admin_url( 'admin.php?page=el-core-projects&project=' . $p->id ),
    ] );
    
    $actions .= EL_Admin_UI::btn( [
        'label'   => __( 'Delete', 'el-core' ),
        'variant' => 'ghost',
        'icon'    => 'trash',
        'class'   => 'el-es-delete-project-btn',
        'data'    => [ 'project-id' => $p->id, 'project-name' => $p->name ],
    ] );

    // Build client name with link to client profile if org is linked
    $client_display = esc_html( $p->client_name );
    if ( ! empty( $p->organization_id ) && (int) $p->organization_id > 0 ) {
        $client_display = '<a href="' . esc_url( admin_url( 'admin.php?page=el-core-clients&client_id=' . $p->organization_id ) ) . '">'
                        . esc_html( $p->client_name ) . '</a>';
    }

    $rows[] = [
        'name'      => '<strong><a href="' . esc_url( admin_url( 'admin.php?page=el-core-projects&project=' . $p->id ) ) . '">'
                      . esc_html( $p->name ) . '</a></strong>'
                      . '<br><small>' . $client_display . '</small>',
        'stage'     => $stage_badge,
        'users'     => $stakeholder_count > 0 ? $stakeholder_count : '—',
        'deadline'  => $deadline_display,
        'budget'    => $budget,
        'status'    => $status_badge,
        'created'   => date_i18n( 'M j, Y', strtotime( $p->created_at ) ),
        '__actions' => $actions,
    ];
}

$html .= $attention_html;

$html .= EL_Admin_UI::card( [
    'title'   => __( 'All Projects', 'el-core' ),
    'icon'    => 'list-view',
    'content' => EL_Admin_UI::data_table( [
        'columns' => [
            [ 'key' => 'name',     'label' => __( 'Project / Client', 'el-core' ) ],
            [ 'key' => 'stage',    'label' => __( 'Current Stage', 'el-core' ) ],
            [ 'key' => 'users',    'label' => __( 'Users', 'el-core' ) ],
            [ 'key' => 'deadline', 'label' => __( 'Deadline', 'el-core' ) ],
            [ 'key' => 'budget',   'label' => __( 'Budget', 'el-core' ) ],
            [ 'key' => 'status',   'label' => __( 'Status', 'el-core' ) ],
            [ 'key' => 'created',  'label' => __( 'Created', 'el-core' ) ],
        ],
        'rows'  => $rows,
        'empty' => [
            'icon'    => 'portfolio',
            'title'   => __( 'No projects yet', 'el-core' ),
            'message' => __( 'Create your first client project to get started.', 'el-core' ),
            'action'  => [
                'label'   => __( 'New Project', 'el-core' ),
                'variant' => 'primary',
                'data'    => [ 'modal-open' => 'create-project-modal' ],
            ],
        ],
    ] ),
] );

// Create project modal
$modal_form  = '<form id="create-project-form">';
$modal_form .= '<input type="hidden" name="organization_id" id="selected-org-id" value="0">';
$modal_form .= EL_Admin_UI::form_section( [ 'title' => __( 'Client Information', 'el-core' ) ] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'        => 'name',
    'label'       => __( 'Project Name', 'el-core' ),
    'required'    => true,
    'placeholder' => __( 'e.g., Acme Corp Website Redesign', 'el-core' ),
] );
$modal_form .= '<div class="el-form-row">';
$modal_form .= '<label for="org-search-input" class="el-form-label">'
             . __( 'Client / Organization', 'el-core' )
             . ' <span class="el-required" aria-hidden="true">*</span></label>';
$modal_form .= '<div class="el-form-field">';
$modal_form .= '<input type="text" id="org-search-input" name="client_name" class="el-input" '
             . 'placeholder="' . esc_attr__( 'Type to search or enter new client name...', 'el-core' ) . '" '
             . 'autocomplete="off" required>';
$modal_form .= '<div id="org-search-results" style="display:none;background:#f9fafb;border:1px solid #d1d5db;border-radius:6px;padding:8px;margin-top:4px;max-height:200px;overflow-y:auto;"></div>';
$modal_form .= '<p class="el-form-helper">'
             . __( 'Select an existing client or type a new name to create one.', 'el-core' )
             . '</p>';
$modal_form .= '</div></div>';

$modal_form .= EL_Admin_UI::form_section( [ 'title' => __( 'Budget', 'el-core' ) ] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'        => 'budget_range_low',
    'label'       => __( 'Budget Range (Low)', 'el-core' ),
    'type'        => 'number',
    'placeholder' => '3000',
] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'        => 'budget_range_high',
    'label'       => __( 'Budget Range (High)', 'el-core' ),
    'type'        => 'number',
    'placeholder' => '10000',
] );

$modal_form .= EL_Admin_UI::form_section( [ 'title' => __( 'Notes', 'el-core' ) ] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'  => 'notes',
    'label' => __( 'Internal Notes', 'el-core' ),
    'type'  => 'textarea',
] );

$modal_form .= '<div class="el-form-row">';
$modal_form .= EL_Admin_UI::btn( [
    'label'   => __( 'Create Project', 'el-core' ),
    'variant' => 'primary',
    'icon'    => 'plus-alt',
    'type'    => 'submit',
] );
$modal_form .= '</div>';
$modal_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'create-project-modal',
    'title'   => __( 'Create New Project', 'el-core' ),
    'size'    => 'large',
    'content' => $modal_form,
] );

echo EL_Admin_UI::wrap( $html );
