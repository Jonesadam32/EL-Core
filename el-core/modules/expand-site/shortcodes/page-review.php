<?php
/**
 * Shortcode: [el_page_review]
 *
 * Page-by-page deliverable review: list pages, Approve / Request Revision buttons.
 * Submits via AJAX: es_client_review_page with page_id and status.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_page_review( $atts ): string {
	$atts = shortcode_atts( [
		'project_id' => 0,
	], $atts, 'el_page_review' );

	$project_id = absint( $atts['project_id'] );

	if ( ! is_user_logged_in() ) {
		return '<div class="el-component el-es-page-review">'
			. '<div class="el-notice el-notice-warning">'
			. '<p>' . esc_html__( 'Please log in to review pages.', 'el-core' )
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
		return '<div class="el-component el-es-page-review">'
			. '<div class="el-empty-state"><p>' . esc_html__( 'No project found.', 'el-core' ) . '</p></div>'
			. '</div>';
	}

	$project_id = (int) $project->id;
	$pages      = $module->get_pages( $project_id );

	$html = '<div class="el-component el-es-page-review" data-project-id="' . esc_attr( $project_id ) . '">';

	$html .= '<h3 class="el-es-section-title">' . esc_html__( 'Pages to review', 'el-core' ) . '</h3>';

	if ( empty( $pages ) ) {
		$html .= '<p class="el-es-empty">' . esc_html__( 'No pages to review yet.', 'el-core' ) . '</p>';
	} else {
		$html .= '<div class="el-es-page-list">';
		foreach ( $pages as $page ) {
			$status = sanitize_html_class( $page->status );
			$html .= '<div class="el-es-page-row" data-page-id="' . esc_attr( $page->id ) . '">';
			$html .= '<div class="el-es-page-info">';
			$html .= '<span class="el-es-page-name">' . esc_html( $page->page_name ) . '</span>';
			if ( ! empty( $page->page_url ) ) {
				$html .= ' <a href="' . esc_url( $page->page_url ) . '" target="_blank" rel="noopener" class="el-es-page-link">' . esc_html__( 'View', 'el-core' ) . '</a>';
			}
			$html .= ' <span class="el-es-page-status el-es-status-' . esc_attr( $status ) . '">' . esc_html( ucfirst( $page->status ) ) . '</span>';
			$html .= '</div>';
			$html .= '<div class="el-es-page-actions">';
			$html .= '<button type="button" class="el-btn el-btn-primary el-es-approve-btn" data-page-id="' . esc_attr( $page->id ) . '" data-status="approved">' . esc_html__( 'Approve', 'el-core' ) . '</button>';
			$html .= ' <button type="button" class="el-btn el-btn-outline el-es-revision-btn" data-page-id="' . esc_attr( $page->id ) . '" data-status="needs_revision">' . esc_html__( 'Request Revision', 'el-core' ) . '</button>';
			$html .= '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';
	}

	$html .= '<div class="el-es-page-status-msg" style="display:none;"></div>';
	$html .= '</div>';
	return $html;
}
