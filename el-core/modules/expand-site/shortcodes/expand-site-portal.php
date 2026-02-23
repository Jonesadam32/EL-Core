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
	$all_deliverables = $module->get_deliverables( $project_id ); // All stages
	$feedback      = $module->get_feedback( $project_id, $current_stage );
	$all_feedback  = $module->get_feedback( $project_id ); // All stages
	$pending       = array_filter( $all_feedback, fn( $f ) => $f->status === 'pending' );
	$stakeholders  = $module->get_stakeholders( $project_id );
	$definition    = $module->get_project_definition( $project_id );
	
	// Determine user role in project
	$is_decision_maker = $module->is_decision_maker( $project_id );
	$is_stakeholder    = $module->is_stakeholder( $project_id );
	$can_contribute    = $module->can_contribute( $project_id );

	$html = '<div class="el-component el-es-portal" data-project-id="' . esc_attr( $project_id ) . '">';

	$html .= '<div class="el-es-portal-header">';
	$html .= '<h2 class="el-es-portal-title">' . esc_html( $project->name ) . '</h2>';
	$html .= '<p class="el-es-portal-subtitle">' . esc_html( $project->client_name ) . '</p>';
	
	// Show role badge
	if ( $is_decision_maker ) {
		$html .= '<span class="el-es-badge el-es-badge-success" style="margin-top: 8px;">' . esc_html__( 'Decision Maker', 'el-core' ) . '</span>';
	} elseif ( $is_stakeholder ) {
		$html .= '<span class="el-es-badge el-es-badge-info" style="margin-top: 8px;">' . esc_html__( 'Contributor', 'el-core' ) . '</span>';
	}
	
	$html .= '</div>';

	// Stats grid
	$html .= '<div class="el-es-stats-grid">';
	
	// Current stage stat
	$html .= '<div class="el-es-stat-card">';
	$html .= '<div class="el-es-stat-icon">📍</div>';
	$html .= '<div class="el-es-stat-content">';
	$html .= '<div class="el-es-stat-number">' . esc_html( $current_stage . '/8' ) . '</div>';
	$html .= '<div class="el-es-stat-label">' . esc_html( $stage_name ) . '</div>';
	$html .= '</div>';
	$html .= '</div>';
	
	// Status stat
	$html .= '<div class="el-es-stat-card">';
	$html .= '<div class="el-es-stat-icon">✓</div>';
	$html .= '<div class="el-es-stat-content">';
	$html .= '<div class="el-es-stat-number">' . esc_html( ucfirst( $project->status ) ) . '</div>';
	$html .= '<div class="el-es-stat-label">' . esc_html__( 'Status', 'el-core' ) . '</div>';
	$html .= '</div>';
	$html .= '</div>';
	
	// Deliverables stat
	$html .= '<div class="el-es-stat-card">';
	$html .= '<div class="el-es-stat-icon">📄</div>';
	$html .= '<div class="el-es-stat-content">';
	$html .= '<div class="el-es-stat-number">' . count( $all_deliverables ) . '</div>';
	$html .= '<div class="el-es-stat-label">' . esc_html__( 'Deliverables', 'el-core' ) . '</div>';
	$html .= '</div>';
	$html .= '</div>';
	
	// Pending feedback stat
	$html .= '<div class="el-es-stat-card">';
	$html .= '<div class="el-es-stat-icon">💬</div>';
	$html .= '<div class="el-es-stat-content">';
	$html .= '<div class="el-es-stat-number">' . count( $pending ) . '</div>';
	$html .= '<div class="el-es-stat-label">' . esc_html__( 'Pending Feedback', 'el-core' ) . '</div>';
	$html .= '</div>';
	$html .= '</div>';
	
	$html .= '</div>'; // end stats grid

	// Project Description/Notes (if present)
	if ( ! empty( $project->notes ) ) {
		$html .= '<div class="el-es-portal-section el-es-description-section">';
		$html .= '<h3 class="el-es-section-title">📝 ' . esc_html__( 'Project Description', 'el-core' ) . '</h3>';
		$html .= '<div class="el-es-description-content">' . wp_kses_post( nl2br( $project->notes ) ) . '</div>';
		$html .= '</div>';
	}

	// Project Definition (if locked)
	if ( $definition && $definition->locked_at ) {
		$html .= '<div class="el-es-portal-section el-es-definition-section">';
		$html .= '<h3 class="el-es-section-title">📋 ' . esc_html__( 'Project Definition', 'el-core' ) . '</h3>';
		$html .= '<p class="el-es-section-intro">' . esc_html__( 'Here\'s what we\'re building for you:', 'el-core' ) . '</p>';
		
		$html .= '<div class="el-es-definition-grid">';
		
		if ( ! empty( $definition->site_description ) ) {
			$html .= '<div class="el-es-definition-item">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Site Description', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->site_description ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->primary_goal ) ) {
			$html .= '<div class="el-es-definition-item">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Primary Goal', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->primary_goal ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->secondary_goals ) ) {
			$html .= '<div class="el-es-definition-item">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Secondary Goals', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . nl2br( esc_html( $definition->secondary_goals ) ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->target_customers ) ) {
			$html .= '<div class="el-es-definition-item">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Target Customers', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->target_customers ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->user_types ) ) {
			$html .= '<div class="el-es-definition-item">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'User Types', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->user_types ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->site_type ) ) {
			$html .= '<div class="el-es-definition-item">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Site Type', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->site_type ) . '</div>';
			$html .= '</div>';
		}
		
		$html .= '</div>'; // end definition grid
		$html .= '</div>'; // end definition section
	}

	// Stakeholder List
	if ( ! empty( $stakeholders ) ) {
		$html .= '<div class="el-es-portal-section el-es-stakeholders-section">';
		$html .= '<h3 class="el-es-section-title">👥 ' . esc_html__( 'Project Team', 'el-core' ) . '</h3>';
		$html .= '<div class="el-es-stakeholder-grid">';
		
		foreach ( $stakeholders as $sh ) {
			$user = get_userdata( $sh->user_id );
			if ( ! $user ) continue;
			
			$html .= '<div class="el-es-stakeholder-card">';
			$html .= '<div class="el-es-stakeholder-avatar">' . get_avatar( $user->ID, 48 ) . '</div>';
			$html .= '<div class="el-es-stakeholder-info">';
			$html .= '<div class="el-es-stakeholder-name">' . esc_html( $user->display_name ) . '</div>';
			$html .= '<div class="el-es-stakeholder-role">';
			if ( $sh->role === 'decision_maker' ) {
				$html .= '<span class="el-es-badge el-es-badge-success">' . esc_html__( 'Decision Maker', 'el-core' ) . '</span>';
			} else {
				$html .= '<span class="el-es-badge el-es-badge-info">' . esc_html__( 'Contributor', 'el-core' ) . '</span>';
			}
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}
		
		$html .= '</div>'; // end stakeholder grid
		$html .= '</div>'; // end stakeholders section
	}

	// 8-step progress indicator
	$stages = $module->get_stages();
	$html .= '<div class="el-es-portal-section">';
	$html .= '<h3 class="el-es-section-title">🚀 ' . esc_html__( 'Project Timeline', 'el-core' ) . '</h3>';
	$html .= '<div class="el-es-stage-bar">';
	foreach ( $stages as $num => $stage ) {
		$state = 'upcoming';
		if ( $num < $current_stage ) $state = 'completed';
		if ( $num === $current_stage ) $state = 'current';
		$html .= '<div class="el-es-stage-step el-es-stage-' . esc_attr( $state ) . '" title="' . esc_attr( $stage['name'] ) . '">';
		$html .= '<div class="el-es-stage-number">' . (int) $num . '</div>';
		$html .= '<div class="el-es-stage-label">' . esc_html( $stage['name'] ) . '</div>';
		$html .= '</div>';
	}
	$html .= '</div>';
	$html .= '</div>'; // end timeline section

	// Deliverables for current stage
	$html .= '<div class="el-es-portal-section el-es-deliverables-section">';
	$html .= '<h3 class="el-es-section-title">📄 ' . esc_html__( 'Current Stage Deliverables', 'el-core' ) . '</h3>';
	if ( empty( $deliverables ) ) {
		$html .= '<p class="el-es-empty">' . esc_html__( 'No deliverables yet for this stage. We\'ll notify you when they\'re ready for review.', 'el-core' ) . '</p>';
	} else {
		$html .= '<div class="el-es-deliverable-grid">';
		foreach ( $deliverables as $d ) {
			$html .= '<div class="el-es-deliverable-card">';
			$html .= '<div class="el-es-deliverable-icon">📎</div>';
			$html .= '<div class="el-es-deliverable-content">';
			$html .= '<div class="el-es-deliverable-title">' . esc_html( $d->title ) . '</div>';
			if ( ! empty( $d->description ) ) {
				$html .= '<div class="el-es-deliverable-desc">' . esc_html( wp_trim_words( $d->description, 20 ) ) . '</div>';
			}
			if ( ! empty( $d->file_url ) ) {
				$html .= '<a href="' . esc_url( $d->file_url ) . '" target="_blank" rel="noopener" class="el-es-btn el-es-btn-secondary">' . esc_html__( 'View File', 'el-core' ) . ' →</a>';
			}
			$html .= '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';
	}
	$html .= '</div>';

	// Show permission notice for contributors
	if ( ! $is_decision_maker && $is_stakeholder ) {
		$html .= '<div class="el-es-portal-notice el-es-notice-info">';
		$html .= '<strong>ℹ️ ' . esc_html__( 'Your Role:', 'el-core' ) . '</strong> ';
		$html .= esc_html__( 'As a Contributor, you can provide feedback and suggestions. The Decision Maker will review and approve final decisions for this project.', 'el-core' );
		$html .= '</div>';
	}

	// Pending feedback (only if user can contribute)
	if ( $can_contribute && ! empty( $pending ) ) {
		$html .= '<div class="el-es-portal-section el-es-feedback-section">';
		$html .= '<h3 class="el-es-section-title">💬 ' . esc_html__( 'Pending Feedback Items', 'el-core' ) . '</h3>';
		$html .= '<div class="el-es-feedback-grid">';
		foreach ( $pending as $f ) {
			$html .= '<div class="el-es-feedback-card">';
			$html .= '<div class="el-es-feedback-content">' . wp_kses_post( $f->content ) . '</div>';
			$html .= '<div class="el-es-feedback-meta">' . date_i18n( 'M j, Y', strtotime( $f->created_at ) ) . '</div>';
			$html .= '</div>';
		}
		$html .= '</div>';
		$html .= '</div>';
	}

	$html .= '</div>';
	return $html;
}
