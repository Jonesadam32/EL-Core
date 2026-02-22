<?php
/**
 * Shortcode: [el_feedback_form]
 *
 * Structured feedback submission: textarea, type dropdown (revision, approval, question, change_order).
 * Submits via AJAX: es_submit_feedback.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_feedback_form( $atts ): string {
	$atts = shortcode_atts( [
		'project_id'    => 0,
		'deliverable_id' => 0,
	], $atts, 'el_feedback_form' );

	$project_id     = absint( $atts['project_id'] );
	$deliverable_id = absint( $atts['deliverable_id'] );

	if ( ! is_user_logged_in() ) {
		return '<div class="el-component el-es-feedback-form">'
			. '<div class="el-notice el-notice-warning">'
			. '<p>' . esc_html__( 'Please log in to submit feedback.', 'el-core' )
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
		return '<div class="el-component el-es-feedback-form">'
			. '<div class="el-empty-state"><p>' . esc_html__( 'No project found.', 'el-core' ) . '</p></div>'
			. '</div>';
	}

	$project_id     = (int) $project->id;
	$current_stage  = (int) $project->current_stage;

	$html = '<div class="el-component el-es-feedback-form" data-project-id="' . esc_attr( $project_id ) . '" data-deliverable-id="' . esc_attr( $deliverable_id ) . '">';

	$html .= '<form class="el-es-feedback-form-inner" method="post" action="">';
	$html .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';
	$html .= '<input type="hidden" name="stage" value="' . esc_attr( $current_stage ) . '">';
	if ( $deliverable_id ) {
		$html .= '<input type="hidden" name="deliverable_id" value="' . esc_attr( $deliverable_id ) . '">';
	}

	$html .= '<div class="el-field">';
	$html .= '<label for="el-es-feedback-type">' . esc_html__( 'Feedback type', 'el-core' ) . '</label>';
	$html .= '<select id="el-es-feedback-type" name="feedback_type" class="el-es-feedback-type">';
	$html .= '<option value="revision">' . esc_html__( 'Revision', 'el-core' ) . '</option>';
	$html .= '<option value="approval">' . esc_html__( 'Approval', 'el-core' ) . '</option>';
	$html .= '<option value="question">' . esc_html__( 'Question', 'el-core' ) . '</option>';
	$html .= '<option value="change_order">' . esc_html__( 'Change order', 'el-core' ) . '</option>';
	$html .= '</select>';
	$html .= '</div>';

	$html .= '<div class="el-field">';
	$html .= '<label for="el-es-feedback-content">' . esc_html__( 'Feedback', 'el-core' ) . ' <span class="el-required">*</span></label>';
	$html .= '<textarea id="el-es-feedback-content" name="content" rows="6" required placeholder="' . esc_attr__( 'Describe your feedback...', 'el-core' ) . '"></textarea>';
	$html .= '</div>';

	$html .= '<button type="submit" class="el-btn el-btn-primary el-es-feedback-submit">' . esc_html__( 'Submit feedback', 'el-core' ) . '</button>';
	$html .= '</form>';

	$html .= '<div class="el-es-feedback-status" style="display:none;"></div>';
	$html .= '</div>';
	return $html;
}
