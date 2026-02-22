<?php
/**
 * Admin View: Expand Site — Project Edit Form
 *
 * Full-page edit form for an existing project.
 * Uses EL_Admin_UI components for all rendering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$module     = EL_Expand_Site_Module::instance();
$project_id = absint( $_GET['project'] ?? 0 );
$project    = $module->get_project( $project_id );

if ( ! $project ) {
    echo EL_Admin_UI::wrap(
        EL_Admin_UI::notice( [ 'message' => __( 'Project not found.', 'el-core' ), 'type' => 'error' ] )
    );
    return;
}

$html = '';

// Page header
$html .= EL_Admin_UI::page_header( [
    'title'      => sprintf( __( 'Edit: %s', 'el-core' ), $project->name ),
    'subtitle'   => esc_html( $project->client_name ),
    'back_url'   => admin_url( 'admin.php?page=el-core-projects&project=' . $project_id ),
    'back_label' => __( '← Back to Project', 'el-core' ),
] );

// Edit form
$form = '<form id="edit-project-form">';
$form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';

// Client Info
$form .= EL_Admin_UI::form_section( [
    'title'       => __( 'Client Information', 'el-core' ),
    'description' => __( 'Core project and client details.', 'el-core' ),
] );
$form .= EL_Admin_UI::form_row( [
    'name'     => 'name',
    'label'    => __( 'Project Name', 'el-core' ),
    'value'    => $project->name,
    'required' => true,
] );
$form .= EL_Admin_UI::form_row( [
    'name'     => 'client_name',
    'label'    => __( 'Client / Organization', 'el-core' ),
    'value'    => $project->client_name,
    'required' => true,
] );
$form .= EL_Admin_UI::form_row( [
    'name'   => 'client_user_id',
    'label'  => __( 'Client WordPress User ID', 'el-core' ),
    'type'   => 'number',
    'value'  => $project->client_user_id ?: '',
    'helper' => __( 'WordPress user ID for client portal access. Leave blank if not applicable.', 'el-core' ),
] );

// Status
$form .= EL_Admin_UI::form_section( [
    'title' => __( 'Status', 'el-core' ),
] );
$form .= EL_Admin_UI::form_row( [
    'name'    => 'status',
    'label'   => __( 'Project Status', 'el-core' ),
    'type'    => 'select',
    'value'   => $project->status,
    'options' => [
        'active'    => __( 'Active', 'el-core' ),
        'paused'    => __( 'Paused', 'el-core' ),
        'completed' => __( 'Completed', 'el-core' ),
        'cancelled' => __( 'Cancelled', 'el-core' ),
    ],
] );

// Budget
$form .= EL_Admin_UI::form_section( [
    'title'       => __( 'Budget & Pricing', 'el-core' ),
    'description' => __( 'Budget range is set during Qualification. Final price is locked at Scope Lock (Stage 3).', 'el-core' ),
] );
$form .= EL_Admin_UI::form_row( [
    'name'  => 'budget_range_low',
    'label' => __( 'Budget Range (Low)', 'el-core' ),
    'type'  => 'number',
    'value' => $project->budget_range_low > 0 ? $project->budget_range_low : '',
] );
$form .= EL_Admin_UI::form_row( [
    'name'  => 'budget_range_high',
    'label' => __( 'Budget Range (High)', 'el-core' ),
    'type'  => 'number',
    'value' => $project->budget_range_high > 0 ? $project->budget_range_high : '',
] );
$form .= EL_Admin_UI::form_row( [
    'name'   => 'final_price',
    'label'  => __( 'Final Price', 'el-core' ),
    'type'   => 'number',
    'value'  => $project->final_price > 0 ? $project->final_price : '',
    'helper' => __( 'Set when scope is locked at Stage 3.', 'el-core' ),
] );

// Notes
$form .= EL_Admin_UI::form_section( [ 'title' => __( 'Notes', 'el-core' ) ] );
$form .= EL_Admin_UI::form_row( [
    'name'  => 'notes',
    'label' => __( 'Internal Notes', 'el-core' ),
    'type'  => 'textarea',
    'value' => $project->notes ?? '',
] );

// Submit
$form .= '<div class="el-form-row">';
$form .= EL_Admin_UI::btn( [
    'label'   => __( 'Save Changes', 'el-core' ),
    'variant' => 'primary',
    'icon'    => 'saved',
    'type'    => 'submit',
] );
$form .= ' ';
$form .= EL_Admin_UI::btn( [
    'label'   => __( 'Cancel', 'el-core' ),
    'variant' => 'secondary',
    'url'     => admin_url( 'admin.php?page=el-core-projects&project=' . $project_id ),
] );
$form .= '</div>';
$form .= '</form>';

$html .= EL_Admin_UI::card( [
    'content' => $form,
] );

echo EL_Admin_UI::wrap( $html );
