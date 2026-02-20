<?php
/**
 * Admin View: Expand Site — Project List
 *
 * Uses EL_Admin_UI components for all rendering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$module = EL_Expand_Site_Module::instance();

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

// Apply search filter in PHP (name or client_name)
if ( $search ) {
    $search_lower = strtolower( $search );
    $projects = array_filter( $projects, function( $p ) use ( $search_lower ) {
        return str_contains( strtolower( $p->name ), $search_lower )
            || str_contains( strtolower( $p->client_name ), $search_lower );
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
    'hidden' => [ 'page' => 'el-expand-site' ],
] );

// Project table
$rows = [];
foreach ( $projects as $p ) {
    $stage_num  = (int) $p->current_stage;
    $stage_badge = EL_Admin_UI::badge( [
        'label'   => $stage_num . '. ' . EL_Expand_Site_Module::get_stage_name( $stage_num ),
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

    $actions = EL_Admin_UI::btn( [
        'label'   => __( 'View', 'el-core' ),
        'variant' => 'ghost',
        'icon'    => 'visibility',
        'url'     => admin_url( 'admin.php?page=el-expand-site&project=' . $p->id ),
    ] );

    $rows[] = [
        'name'      => '<strong><a href="' . esc_url( admin_url( 'admin.php?page=el-expand-site&project=' . $p->id ) ) . '">'
                      . esc_html( $p->name ) . '</a></strong>'
                      . '<br><small>' . esc_html( $p->client_name ) . '</small>',
        'stage'     => $stage_badge,
        'budget'    => $budget,
        'status'    => $status_badge,
        'created'   => date_i18n( 'M j, Y', strtotime( $p->created_at ) ),
        '__actions' => $actions,
    ];
}

$html .= EL_Admin_UI::card( [
    'title'   => __( 'All Projects', 'el-core' ),
    'icon'    => 'list-view',
    'content' => EL_Admin_UI::data_table( [
        'columns' => [
            [ 'key' => 'name',    'label' => __( 'Project / Client', 'el-core' ) ],
            [ 'key' => 'stage',   'label' => __( 'Current Stage', 'el-core' ) ],
            [ 'key' => 'budget',  'label' => __( 'Budget', 'el-core' ) ],
            [ 'key' => 'status',  'label' => __( 'Status', 'el-core' ) ],
            [ 'key' => 'created', 'label' => __( 'Created', 'el-core' ) ],
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
$modal_form .= EL_Admin_UI::form_section( [ 'title' => __( 'Client Information', 'el-core' ) ] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'        => 'name',
    'label'       => __( 'Project Name', 'el-core' ),
    'required'    => true,
    'placeholder' => __( 'e.g., Acme Corp Website Redesign', 'el-core' ),
] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'        => 'client_name',
    'label'       => __( 'Client / Organization Name', 'el-core' ),
    'required'    => true,
    'placeholder' => __( 'e.g., Acme Corporation', 'el-core' ),
] );

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
