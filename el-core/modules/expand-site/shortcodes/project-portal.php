<?php
/**
 * Shortcode: [el_project_portal]
 *
 * Client-facing project dashboard: current stage, 8-step progress,
 * deliverables for current stage, pending feedback items.
 * If no project_id, auto-detects from logged-in user's client_user_id.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_project_portal( $atts ): string {
	$atts = shortcode_atts( [
		'project_id' => 0,
	], $atts, 'el_project_portal' );

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
		$projects = $module->get_all_projects(
			[ 'client_user_id' => get_current_user_id() ],
			[ 'limit' => 1, 'orderby' => 'created_at', 'order' => 'DESC' ]
		);
		$project = $projects[0] ?? null;
	} else {
		$project = $module->get_project( $project_id );
		// Verify user is client
		if ( $project && (int) $project->client_user_id !== get_current_user_id() && ! el_core_can( 'manage_expand_site' ) ) {
			$project = null;
		}
	}

	if ( ! $project ) {
		return '<div class="el-component el-es-portal">'
			. '<div class="el-empty-state">'
			. '<p>' . esc_html__( 'No project found.', 'el-core' ) . '</p>'
			. '</div></div>';
	}

	$project_id   = (int) $project->id;
	$current_stage = (int) $project->current_stage;
	$stage_name   = EL_Expand_Site_Module::get_stage_name( $current_stage );
	$deliverables = $module->get_deliverables( $project_id, $current_stage );
	$feedback     = $module->get_feedback( $project_id, $current_stage );
	$pending      = array_filter( $feedback, fn( $f ) => $f->status === 'pending' );

	$html = '<div class="el-component el-es-portal" data-project-id="' . esc_attr( $project_id ) . '">';

	$html .= '<div class="el-es-portal-header">';
	$html .= '<h2 class="el-es-portal-title">' . esc_html( $project->name ) . '</h2>';
	$html .= '<p class="el-es-portal-stage">' . esc_html__( 'Current stage:', 'el-core' ) . ' ' . esc_html( $stage_name ) . '</p>';
	$html .= '</div>';

	// 8-step progress indicator
	$html .= '<div class="el-es-stage-bar">';
	foreach ( EL_Expand_Site_Module::STAGES as $num => $stage ) {
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
