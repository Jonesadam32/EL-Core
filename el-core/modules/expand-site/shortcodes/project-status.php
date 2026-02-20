<?php
/**
 * Shortcode: [el_project_status]
 *
 * Visual 8-step progress bar: completed filled, current highlighted, upcoming muted.
 * If no project_id, auto-detects from logged-in user's client_user_id.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_project_status( $atts ): string {
	$atts = shortcode_atts( [
		'project_id' => 0,
	], $atts, 'el_project_status' );

	$project_id = absint( $atts['project_id'] );

	if ( ! is_user_logged_in() ) {
		return '<div class="el-component el-es-stage-bar">'
			. '<div class="el-notice el-notice-warning">'
			. '<p>' . esc_html__( 'Please log in to view project status.', 'el-core' )
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
		if ( $project && (int) $project->client_user_id !== get_current_user_id() && ! el_core_can( 'manage_expand_site' ) ) {
			$project = null;
		}
	}

	if ( ! $project ) {
		return '<div class="el-component el-es-stage-bar">'
			. '<div class="el-empty-state"><p>' . esc_html__( 'No project found.', 'el-core' ) . '</p></div>'
			. '</div>';
	}

	$project_id    = (int) $project->id;
	$current_stage = (int) $project->current_stage;

	$html = '<div class="el-component el-es-stage-bar" data-project-id="' . esc_attr( $project_id ) . '">';

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
	return $html;
}
