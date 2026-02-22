<?php
/**
 * Shortcode: [el_expand_site_portal]
 *
 * Client-facing Expand Site project dashboard: current stage, 8-step progress,
 * deliverables for current stage, pending feedback items.
 * If no project_id, auto-detects from logged-in user's stakeholder assignments.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_expand_site_portal( $atts ): string {
	$atts = shortcode_atts( [
		'project_id' => 0,
	], $atts, 'el_expand_site_portal' );

	$project_id = absint( $atts['project_id'] );

	if ( ! is_user_logged_in() ) {
		return '<div class="el-component el-es-portal">'
			. '<div class="el-notice el-notice-warning">'
			. '<p>' . esc_html__( 'Please log in to view your project portal.', 'el-core' )
			. ' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'el-core' ) . '</a></p>'
			. '</div></div>';
	}

	$module = EL_Expand_Site_Module::instance();

	if ( ! $project_id ) {
		// Auto-detect: find first project where user is a stakeholder
		$user_id = get_current_user_id();
		
		// First try: user is client_user_id (legacy single-client model)
		$projects = $module->get_all_projects(
			[ 'client_user_id' => $user_id ],
			[ 'limit' => 1, 'orderby' => 'created_at', 'order' => 'DESC' ]
		);
		
		// If no match, check stakeholders table (new multi-stakeholder model)
		if ( empty( $projects ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'el_es_stakeholders';
			$project_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT project_id FROM {$table} WHERE user_id = %d ORDER BY added_at DESC LIMIT 1",
				$user_id
			) );
			if ( $project_id ) {
				$project = $module->get_project( (int) $project_id );
			} else {
				$project = null;
			}
		} else {
			$project = $projects[0];
		}
	} else {
		$project = $module->get_project( $project_id );
		// Verify user is authorized to view this project
		if ( $project && ! $module->is_stakeholder( $project_id ) && ! el_core_can( 'manage_expand_site' ) ) {
			$project = null;
		}
	}

	if ( ! $project ) {
		return '<div class="el-component el-es-portal">'
			. '<div class="el-empty-state">'
			. '<p>' . esc_html__( 'No project found.', 'el-core' ) . '</p>'
			. '</div></div>';
	}

	$project_id    = (int) $project->id;
	$current_stage = (int) $project->current_stage;
	$stage_name    = $module->get_stage_name( $current_stage );
	$deliverables  = $module->get_deliverables( $project_id, $current_stage );
	$feedback      = $module->get_feedback( $project_id, $current_stage );
	$pending       = array_filter( $feedback, fn( $f ) => $f->status === 'pending' );
	
	// Determine user role in project
	$is_decision_maker = $module->is_decision_maker( $project_id );
	$is_stakeholder    = $module->is_stakeholder( $project_id );
	$can_contribute    = $module->can_contribute( $project_id );

	$html = '<div class="el-component el-es-portal" data-project-id="' . esc_attr( $project_id ) . '">';

	$html .= '<div class="el-es-portal-header">';
	$html .= '<h2 class="el-es-portal-title">' . esc_html( $project->name ) . '</h2>';
	$html .= '<p class="el-es-portal-stage">' . esc_html__( 'Current stage:', 'el-core' ) . ' ' . esc_html( $stage_name ) . '</p>';
	
	// Show role badge
	if ( $is_decision_maker ) {
		$html .= '<p class="el-es-portal-role"><span class="el-es-badge el-es-badge-success">' . esc_html__( 'Decision Maker', 'el-core' ) . '</span></p>';
	} elseif ( $is_stakeholder ) {
		$html .= '<p class="el-es-portal-role"><span class="el-es-badge el-es-badge-info">' . esc_html__( 'Contributor', 'el-core' ) . '</span></p>';
	}
	
	$html .= '</div>';

	// 8-step progress indicator
	$stages = $module->get_stages();
	$html .= '<div class="el-es-stage-bar">';
	foreach ( $stages as $num => $stage ) {
		$state = 'upcoming';
		if ( $num < $current_stage ) $state = 'completed';
		if ( $num === $current_stage ) $state = 'current';
		$html .= '<div class="el-es-stage-step el-es-stage-' . esc_attr( $state ) . '">';
		$html .= '<span class="el-es-stage-number">' . (int) $num . '</span>';
		$html .= '<span class="el-es-stage-label">' . esc_html( $stage['name'] ) . '</span>';
		$html .= '</div>';
	}
	$html .= '</div>';

	// Deliverables for current stage
	$html .= '<div class="el-es-portal-deliverables">';
	$html .= '<h3 class="el-es-section-title">' . esc_html__( 'Current stage deliverables', 'el-core' ) . '</h3>';
	if ( empty( $deliverables ) ) {
		$html .= '<p class="el-es-empty">' . esc_html__( 'No deliverables yet for this stage.', 'el-core' ) . '</p>';
	} else {
		$html .= '<ul class="el-es-deliverable-list">';
		foreach ( $deliverables as $d ) {
			$html .= '<li class="el-es-deliverable-item">';
			$html .= '<span class="el-es-deliverable-title">' . esc_html( $d->title ) . '</span>';
			if ( ! empty( $d->file_url ) ) {
				$html .= ' <a href="' . esc_url( $d->file_url ) . '" target="_blank" rel="noopener" class="el-es-deliverable-link">' . esc_html__( 'View', 'el-core' ) . '</a>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';
	}
	$html .= '</div>';

	// Show permission notice for contributors
	if ( ! $is_decision_maker && $is_stakeholder ) {
		$html .= '<div class="el-es-portal-notice">';
		$html .= '<p>' . esc_html__( 'As a Contributor, you can provide feedback and suggestions. The Decision Maker will review and approve final decisions for this project.', 'el-core' ) . '</p>';
		$html .= '</div>';
	}

	// Pending feedback
	$html .= '<div class="el-es-portal-feedback">';
	$html .= '<h3 class="el-es-section-title">' . esc_html__( 'Pending feedback items', 'el-core' ) . '</h3>';
	if ( empty( $pending ) ) {
		$html .= '<p class="el-es-empty">' . esc_html__( 'No pending feedback.', 'el-core' ) . '</p>';
	} else {
		$html .= '<ul class="el-es-feedback-list">';
		foreach ( $pending as $f ) {
			$html .= '<li class="el-es-feedback-item">' . esc_html( wp_strip_all_tags( $f->content ) ) . '</li>';
		}
		$html .= '</ul>';
	}
	$html .= '</div>';

	$html .= '</div>';
	return $html;
}
