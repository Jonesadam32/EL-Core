<?php
/**
 * Shortcode: [el_expand_site_portal]
 *
 * Client-facing Expand Site project dashboard with stage navigation and progressive disclosure.
 * Features: Stage-based navigation, filtered content, SVG icons, Modern Tech color palette.
 * If no project_id, auto-detects from logged-in user's stakeholder assignments.
 * 
 * @version 1.14.3
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_expand_site_portal( $atts ): string {
	$atts = shortcode_atts( [
		'project_id' => 0,
	], $atts, 'el_expand_site_portal' );

	$project_id = absint( $atts['project_id'] );

	// Also accept project_id from URL query string (e.g. ?project_id=X from the client dashboard)
	if ( ! $project_id && ! empty( $_GET['project_id'] ) ) {
		$project_id = absint( $_GET['project_id'] );
	}

	if ( ! is_user_logged_in() ) {
		return '<div class="el-component el-es-portal">'
			. '<div class="el-notice el-notice-warning">'
			. el_es_icon( 'alert-triangle' )
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

		// Third fallback: user is the designated decision_maker_id on a project
		if ( ! $project ) {
			global $wpdb;
			$projects_table = $wpdb->prefix . 'el_es_projects';
			$dm_project_id  = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$projects_table} WHERE decision_maker_id = %d ORDER BY created_at DESC LIMIT 1",
				$user_id
			) );
			if ( $dm_project_id ) {
				$project = $module->get_project( (int) $dm_project_id );
			}
		}
	} else {
		$project = $module->get_project( $project_id );
		// Verify user is authorized to view this project
		if ( $project && ! $module->is_stakeholder( $project_id ) && ! $module->is_decision_maker( $project_id ) && ! el_core_can( 'manage_expand_site' ) ) {
			$project = null;
		}
	}

	if ( ! $project ) {
		return '<div class="el-component el-es-portal">'
			. '<div class="el-empty-state">'
			. el_es_icon( 'alert-circle' )
			. '<p>' . esc_html__( 'No project found.', 'el-core' ) . '</p>'
			. '</div></div>';
	}

	$project_id    = (int) $project->id;
	$current_stage = (int) $project->current_stage;
	$stage_name    = $module->get_stage_name( $current_stage );
	$stages        = $module->get_stages();
	$stakeholders  = $module->get_stakeholders( $project_id );
	$definition    = $module->get_project_definition( $project_id );
	
	// Determine user role in project
	$is_decision_maker = $module->is_decision_maker( $project_id );
	$is_stakeholder    = $module->is_stakeholder( $project_id );
	$can_contribute    = $module->can_contribute( $project_id );

	// Find the dashboard page URL for the back button
	$dashboard_page_url = null;
	global $wpdb;
	$_dash_like = '%[el_client_dashboard%';
	$_dash_page = $wpdb->get_row( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s LIMIT 1",
		$_dash_like
	) );
	if ( $_dash_page ) {
		$dashboard_page_url = get_permalink( $_dash_page->ID );
	}

	// ═══════════════════════════════════════════
	// PORTAL HEADER
	// ═══════════════════════════════════════════

	$html = '<div class="el-component el-es-portal" data-project-id="' . esc_attr( $project_id ) . '" data-current-stage="' . esc_attr( $current_stage ) . '">';

	// Back to dashboard link (always shown when dashboard page exists)
	if ( $dashboard_page_url ) {
		$html .= '<div class="el-es-back-to-dashboard">';
		$html .= '<a href="' . esc_url( $dashboard_page_url ) . '" class="el-es-back-link">';
		$html .= el_es_icon( 'arrow-left', 16 );
		$html .= esc_html__( 'Back to Dashboard', 'el-core' );
		$html .= '</a>';
		$html .= '</div>';
	}

	$html .= '<div class="el-es-portal-header">';
	$html .= '<div class="el-es-header-content">';
	$html .= '<h1 class="el-es-portal-title">' . esc_html( $project->name ) . '</h1>';
	$html .= '<p class="el-es-portal-subtitle">' . esc_html( $project->client_name ) . '</p>';
	$html .= '</div>';
	
	// Show role badge
	if ( $is_decision_maker ) {
		$html .= '<div class="el-es-header-badge">';
		$html .= '<span class="el-es-badge el-es-badge-decision-maker">';
		$html .= el_es_icon( 'check-circle' );
		$html .= esc_html__( 'Decision Maker', 'el-core' );
		$html .= '</span>';
		$html .= '</div>';
	} elseif ( $is_stakeholder ) {
		$html .= '<div class="el-es-header-badge">';
		$html .= '<span class="el-es-badge el-es-badge-contributor">';
		$html .= el_es_icon( 'user' );
		$html .= esc_html__( 'Contributor', 'el-core' );
		$html .= '</span>';
		$html .= '</div>';
	}
	
	$html .= '</div>'; // end header

	// ═══════════════════════════════════════════
	// STAGE NAVIGATION (Primary Element)
	// ═══════════════════════════════════════════
	
	$html .= '<div class="el-es-stage-navigation">';
	$html .= '<div class="el-es-stage-nav-inner">';
	
	foreach ( $stages as $num => $stage ) {
		$state = 'upcoming';
		if ( $num < $current_stage ) $state = 'completed';
		if ( $num === $current_stage ) $state = 'current';
		
		$clickable = ( $state === 'completed' || $state === 'current' );
		$classes = [
			'el-es-stage-btn',
			'el-es-stage-' . $state,
		];
		if ( ! $clickable ) $classes[] = 'el-es-stage-disabled';
		
		$html .= '<button type="button" class="' . esc_attr( implode( ' ', $classes ) ) . '" data-stage="' . esc_attr( $num ) . '" ' . ( ! $clickable ? 'disabled' : '' ) . ' aria-label="' . esc_attr( sprintf( __( 'Stage %d: %s', 'el-core' ), $num, $stage['name'] ) ) . '">';
		
		// Icon
		$html .= '<div class="el-es-stage-icon">';
		if ( $state === 'completed' ) {
			$html .= el_es_icon( 'check-circle' );
		} else {
			$html .= '<span class="el-es-stage-number">' . (int) $num . '</span>';
		}
		$html .= '</div>';
		
		// Label
		$html .= '<div class="el-es-stage-name">' . esc_html( $stage['name'] ) . '</div>';
		
		$html .= '</button>';
	}
	
	$html .= '</div>'; // end stage-nav-inner
	$html .= '</div>'; // end stage navigation

	// ═══════════════════════════════════════════
	// STAGE CONTENT AREAS (Progressive Disclosure)
	// ═══════════════════════════════════════════
	
	$html .= '<div class="el-es-stage-content-wrapper">';
	
	// Generate content for each stage
	foreach ( $stages as $num => $stage ) {
		$is_current = ( $num === $current_stage );
		$is_completed = ( $num < $current_stage );
		$is_accessible = $is_current || $is_completed;
		
		// Only show accessible stages (current + completed)
		if ( ! $is_accessible ) continue;
		
		$stage_deliverables = $module->get_deliverables( $project_id, $num );
		$stage_feedback = $module->get_feedback( $project_id, $num );
		$pending_feedback = array_filter( $stage_feedback, fn( $f ) => $f->status === 'pending' );
		
		$html .= '<div class="el-es-stage-content" data-stage="' . esc_attr( $num ) . '" ' . ( ! $is_current ? 'style="display:none;"' : '' ) . '>';
		
		// Stage content cards
		$html .= '<div class="el-es-stage-cards">';
		
		// Deliverables card
		$deliverable_count = count( $stage_deliverables );
		$html .= '<button type="button" class="el-es-info-card el-es-modal-trigger" data-modal="deliverables-' . esc_attr( $num ) . '">';
		$html .= '<div class="el-es-info-card-icon">' . el_es_icon( 'file-text', 24 ) . '</div>';
		$html .= '<div class="el-es-info-card-content">';
		$html .= '<div class="el-es-info-card-title">' . esc_html__( 'Deliverables', 'el-core' ) . '</div>';
		if ( $deliverable_count > 0 ) {
			$html .= '<div class="el-es-info-card-count">' . sprintf( esc_html( _n( '%d item', '%d items', $deliverable_count, 'el-core' ) ), $deliverable_count ) . '</div>';
		} else {
			$html .= '<div class="el-es-info-card-empty">' . esc_html__( 'None yet', 'el-core' ) . '</div>';
		}
		$html .= '</div>';
		$html .= '<div class="el-es-info-card-arrow">' . el_es_icon( 'chevron-right' ) . '</div>';
		$html .= '</button>';
		
		// Deliverables Modal
		$html .= '<div class="el-es-modal" id="deliverables-' . esc_attr( $num ) . '" aria-hidden="true">';
		$html .= '<div class="el-es-modal-overlay" data-close-modal="deliverables-' . esc_attr( $num ) . '"></div>';
		$html .= '<div class="el-es-modal-container">';
		$html .= '<div class="el-es-modal-header">';
		$html .= '<h3 class="el-es-modal-title">';
		$html .= el_es_icon( 'file-text' );
		$html .= esc_html__( 'Deliverables', 'el-core' );
		$html .= '</h3>';
		$html .= '<button type="button" class="el-es-modal-close" data-close-modal="deliverables-' . esc_attr( $num ) . '" aria-label="' . esc_attr__( 'Close', 'el-core' ) . '">';
		$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
		$html .= '</button>';
		$html .= '</div>'; // end modal header
		$html .= '<div class="el-es-modal-body">';
		
		if ( empty( $stage_deliverables ) ) {
			$html .= '<div class="el-es-modal-empty">';
			$html .= el_es_icon( 'file-text', 48 );
			$html .= '<p>' . esc_html__( 'No deliverables yet for this stage.', 'el-core' ) . '</p>';
			$html .= '</div>';
		} else {
			$html .= '<div class="el-es-deliverable-grid">';
			foreach ( $stage_deliverables as $d ) {
				$html .= '<div class="el-es-deliverable-card">';
				$html .= '<div class="el-es-deliverable-header">';
				$html .= '<div class="el-es-deliverable-icon">' . el_es_icon( 'file' ) . '</div>';
				$html .= '<div class="el-es-deliverable-title">' . esc_html( $d->title ) . '</div>';
				$html .= '</div>';
				if ( ! empty( $d->description ) ) {
					$html .= '<div class="el-es-deliverable-desc">' . esc_html( $d->description ) . '</div>';
				}
				if ( ! empty( $d->file_url ) ) {
					$html .= '<div class="el-es-deliverable-actions">';
					$html .= '<a href="' . esc_url( $d->file_url ) . '" target="_blank" rel="noopener" class="el-es-btn el-es-btn-primary">';
					$html .= el_es_icon( 'external-link' );
					$html .= esc_html__( 'View File', 'el-core' );
					$html .= '</a>';
					$html .= '</div>';
				}
				$html .= '</div>';
			}
			$html .= '</div>';
		}
		
		$html .= '</div>'; // end modal body
		$html .= '</div>'; // end modal container
		$html .= '</div>'; // end modal
		
		// Feedback card (if user can contribute)
		if ( $can_contribute ) {
			$feedback_count = count( $stage_feedback );
			$pending_count = count( $pending_feedback );
			$html .= '<button type="button" class="el-es-info-card el-es-modal-trigger" data-modal="feedback-' . esc_attr( $num ) . '">';
			$html .= '<div class="el-es-info-card-icon">' . el_es_icon( 'message-circle', 24 ) . '</div>';
			$html .= '<div class="el-es-info-card-content">';
			$html .= '<div class="el-es-info-card-title">' . esc_html__( 'Feedback', 'el-core' ) . '</div>';
			if ( $feedback_count > 0 ) {
				$html .= '<div class="el-es-info-card-count">';
				$html .= sprintf( esc_html( _n( '%d comment', '%d comments', $feedback_count, 'el-core' ) ), $feedback_count );
				if ( $pending_count > 0 ) {
					$html .= ' <span class="el-es-badge el-es-badge-pending">' . (int) $pending_count . ' ' . esc_html__( 'pending', 'el-core' ) . '</span>';
				}
				$html .= '</div>';
			} else {
				$html .= '<div class="el-es-info-card-empty">' . esc_html__( 'None yet', 'el-core' ) . '</div>';
			}
			$html .= '</div>';
			$html .= '<div class="el-es-info-card-arrow">' . el_es_icon( 'chevron-right' ) . '</div>';
			$html .= '</button>';
		}
		
		// Project Definition card (when definition exists — locked opens modal, else scrolls to review section)
		if ( $definition ) {
			if ( $definition->locked_at ) {
				$html .= '<button type="button" class="el-es-info-card el-es-modal-trigger" data-modal="project-definition">';
			} else {
				$html .= '<a href="#el-es-definition-review" class="el-es-info-card el-es-definition-scroll-trigger">';
			}
			$html .= '<div class="el-es-info-card-icon el-es-info-card-icon-accent">' . el_es_icon( 'clipboard', 24 ) . '</div>';
			$html .= '<div class="el-es-info-card-content">';
			$html .= '<div class="el-es-info-card-title">' . esc_html__( 'Project Definition', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-info-card-desc">' . esc_html__( 'What we\'re building', 'el-core' ) . '</div>';
			$html .= '</div>';
			$html .= '<div class="el-es-info-card-arrow">' . el_es_icon( 'chevron-right' ) . '</div>';
			$html .= ( $definition->locked_at ? '</button>' : '</a>' );
		}
		
		$html .= '</div>'; // end stage cards
		
		// Feedback Modal (outside stage cards, inside stage content)
		if ( $can_contribute ) {
			$html .= '<div class="el-es-modal" id="feedback-' . esc_attr( $num ) . '" aria-hidden="true">';
			$html .= '<div class="el-es-modal-overlay" data-close-modal="feedback-' . esc_attr( $num ) . '"></div>';
			$html .= '<div class="el-es-modal-container">';
			$html .= '<div class="el-es-modal-header">';
			$html .= '<h3 class="el-es-modal-title">';
			$html .= el_es_icon( 'message-circle' );
			$html .= esc_html__( 'Feedback', 'el-core' );
			$html .= '</h3>';
			$html .= '<button type="button" class="el-es-modal-close" data-close-modal="feedback-' . esc_attr( $num ) . '" aria-label="' . esc_attr__( 'Close', 'el-core' ) . '">';
			$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
			$html .= '</button>';
			$html .= '</div>'; // end modal header
			$html .= '<div class="el-es-modal-body">';
			
			if ( empty( $stage_feedback ) ) {
				$html .= '<div class="el-es-modal-empty">';
				$html .= el_es_icon( 'message-circle', 48 );
				$html .= '<p>' . esc_html__( 'No feedback for this stage yet.', 'el-core' ) . '</p>';
				$html .= '</div>';
			} else {
				$html .= '<div class="el-es-feedback-list">';
				foreach ( $stage_feedback as $f ) {
					$html .= '<div class="el-es-feedback-item el-es-feedback-' . esc_attr( $f->status ) . '">';
					$html .= '<div class="el-es-feedback-content">' . wp_kses_post( $f->content ) . '</div>';
					$html .= '<div class="el-es-feedback-meta">';
					$html .= el_es_icon( 'calendar' );
					$html .= date_i18n( get_option( 'date_format' ), strtotime( $f->created_at ) );
					$html .= ' <span class="el-es-feedback-status-badge el-es-badge el-es-badge-' . esc_attr( $f->status ) . '">' . esc_html( ucfirst( $f->status ) ) . '</span>';
					$html .= '</div>';
					$html .= '</div>';
				}
				$html .= '</div>';
			}
			
			$html .= '</div>'; // end modal body
			$html .= '</div>'; // end modal container
			$html .= '</div>'; // end modal
		}
		
		$html .= '</div>'; // end stage content
	}
	
	$html .= '</div>'; // end stage content wrapper

	// ═══════════════════════════════════════════
	// GLOBAL INFORMATION SECTIONS
	// ═══════════════════════════════════════════
	
	$html .= '<div class="el-es-global-sections">';
	
	// Project Definition — consensus review or locked display
	$def_review_status = $definition && isset( $definition->review_status ) ? $definition->review_status : '';
	$def_reviews       = $definition ? $module->get_definition_reviews( $project_id ) : [];
	$last_closed       = null;
	foreach ( array_reverse( $def_reviews ) as $dr ) {
		if ( $dr->status === 'closed' ) {
			$last_closed = $dr;
			break;
		}
	}

	if ( $definition ) {
		// Pending review: full consensus UI (JS loads and renders)
		if ( $def_review_status === 'pending_review' ) {
			$html .= '<div class="el-es-global-section el-es-definition-review-section" id="el-es-definition-review" data-project-id="' . esc_attr( $project_id ) . '">';
			$html .= '<h3 class="el-es-section-title">';
			$html .= el_es_icon( 'clipboard' );
			$html .= esc_html__( 'Project Definition — Review', 'el-core' );
			$html .= '</h3>';
			$html .= '<div class="el-es-definition-review-loading">' . esc_html__( 'Loading…', 'el-core' ) . '</div>';
			$html .= '</div>';
		}

		// Approved (DM approved, not yet locked): approved banner + definition
		if ( $def_review_status === 'approved' ) {
			$html .= '<div class="el-es-global-section el-es-definition-review-section" id="el-es-definition-review">';
			$html .= '<h3 class="el-es-section-title">';
			$html .= el_es_icon( 'clipboard' );
			$html .= esc_html__( 'Project Definition', 'el-core' );
			$html .= '</h3>';
			$html .= '<div class="el-es-review-approved-banner">';
			$html .= el_es_icon( 'check-circle', 20 );
			$html .= '<strong>' . esc_html__( 'Definition approved!', 'el-core' ) . '</strong> ';
			$html .= esc_html__( 'The agency can now lock it and proceed.', 'el-core' );
			$html .= '</div>';
			$html .= '<div class="el-es-definition-grid">';
			if ( ! empty( $definition->site_description ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Site Description', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->site_description ) . '</div></div>';
			}
			if ( ! empty( $definition->primary_goal ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Primary Goal', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->primary_goal ) . '</div></div>';
			}
			if ( ! empty( $definition->secondary_goals ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Secondary Goals', 'el-core' ) . '</div><div class="el-es-definition-value">' . nl2br( esc_html( $definition->secondary_goals ) ) . '</div></div>';
			}
			if ( ! empty( $definition->target_customers ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Target Customers', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->target_customers ) . '</div></div>';
			}
			if ( ! empty( $definition->user_types ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'User Types', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->user_types ) . '</div></div>';
			}
			if ( ! empty( $definition->site_type ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Site Type', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->site_type ) . '</div></div>';
			}
			$html .= '</div></div>';
		}

		// Needs revision: banner with DM note + definition
		if ( $def_review_status === 'needs_revision' ) {
			$dm_note = $last_closed && ! empty( $last_closed->dm_note ) ? $last_closed->dm_note : '';
			$html .= '<div class="el-es-global-section el-es-definition-review-section" id="el-es-definition-review">';
			$html .= '<h3 class="el-es-section-title">';
			$html .= el_es_icon( 'clipboard' );
			$html .= esc_html__( 'Project Definition', 'el-core' );
			$html .= '</h3>';
			$html .= '<div class="el-es-review-needs-revision-banner">';
			$html .= el_es_icon( 'edit', 20 );
			$html .= '<strong>' . esc_html__( 'Needs revision', 'el-core' ) . '</strong>';
			if ( $dm_note ) {
				$html .= '<p class="el-es-dm-note">' . nl2br( esc_html( $dm_note ) ) . '</p>';
			}
			$html .= '</div>';
			$html .= '<div class="el-es-definition-grid">';
			if ( ! empty( $definition->site_description ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Site Description', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->site_description ) . '</div></div>';
			}
			if ( ! empty( $definition->primary_goal ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Primary Goal', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->primary_goal ) . '</div></div>';
			}
			if ( ! empty( $definition->secondary_goals ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Secondary Goals', 'el-core' ) . '</div><div class="el-es-definition-value">' . nl2br( esc_html( $definition->secondary_goals ) ) . '</div></div>';
			}
			if ( ! empty( $definition->target_customers ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Target Customers', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->target_customers ) . '</div></div>';
			}
			if ( ! empty( $definition->user_types ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'User Types', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->user_types ) . '</div></div>';
			}
			if ( ! empty( $definition->site_type ) ) {
				$html .= '<div class="el-es-definition-card"><div class="el-es-definition-label">' . esc_html__( 'Site Type', 'el-core' ) . '</div><div class="el-es-definition-value">' . esc_html( $definition->site_type ) . '</div></div>';
			}
			$html .= '</div></div>';
		}
	}

	// Project Definition Modal (locked — read-only)
	if ( $definition && $definition->locked_at ) {
		$html .= '<div class="el-es-modal" id="project-definition" aria-hidden="true">';
		$html .= '<div class="el-es-modal-overlay" data-close-modal="project-definition"></div>';
		$html .= '<div class="el-es-modal-container">';
		$html .= '<div class="el-es-modal-header">';
		$html .= '<h3 class="el-es-modal-title">';
		$html .= el_es_icon( 'clipboard' );
		$html .= esc_html__( 'Project Definition', 'el-core' );
		$html .= '</h3>';
		$html .= '<button type="button" class="el-es-modal-close" data-close-modal="project-definition" aria-label="' . esc_attr__( 'Close', 'el-core' ) . '">';
		$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
		$html .= '</button>';
		$html .= '</div>'; // end modal header
		$html .= '<div class="el-es-modal-body">';
		
		$html .= '<p class="el-es-modal-intro">' . esc_html__( 'Here\'s what we\'re building for you:', 'el-core' ) . '</p>';
		
		$html .= '<div class="el-es-definition-grid">';
		
		if ( ! empty( $definition->site_description ) ) {
			$html .= '<div class="el-es-definition-card">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Site Description', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->site_description ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->primary_goal ) ) {
			$html .= '<div class="el-es-definition-card">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Primary Goal', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->primary_goal ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->secondary_goals ) ) {
			$html .= '<div class="el-es-definition-card">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Secondary Goals', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . nl2br( esc_html( $definition->secondary_goals ) ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->target_customers ) ) {
			$html .= '<div class="el-es-definition-card">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Target Customers', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->target_customers ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->user_types ) ) {
			$html .= '<div class="el-es-definition-card">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'User Types', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->user_types ) . '</div>';
			$html .= '</div>';
		}
		
		if ( ! empty( $definition->site_type ) ) {
			$html .= '<div class="el-es-definition-card">';
			$html .= '<div class="el-es-definition-label">' . esc_html__( 'Site Type', 'el-core' ) . '</div>';
			$html .= '<div class="el-es-definition-value">' . esc_html( $definition->site_type ) . '</div>';
			$html .= '</div>';
		}
		
		$html .= '</div>'; // end definition grid
		
		$html .= '</div>'; // end modal body
		$html .= '</div>'; // end modal container
		$html .= '</div>'; // end modal
	}

	// ═══════════════════════════════════════════
	// BRANDING TAB — MOOD BOARD
	// ═══════════════════════════════════════════

	$review_items       = $module->get_review_items( $project_id, 'mood_board' );
	$open_review        = null;
	$closed_review      = null;
	foreach ( $review_items as $ri ) {
		if ( $ri->status === 'open' && ! $open_review ) {
			$open_review = $ri;
		} elseif ( $ri->status === 'closed' && ! $closed_review ) {
			$closed_review = $ri;
		}
	}

	$active_review = $open_review ?: $closed_review;

	if ( $active_review ) {
		$dm_decision      = $active_review->dm_decision ? json_decode( $active_review->dm_decision, true ) : [];
		$selected_ids     = $dm_decision['selected_template_ids'] ?? [];
		$confirmed_ids    = $dm_decision['confirmed_template_ids'] ?? [];
		$is_closed        = ( $active_review->status === 'closed' );

		$html .= '<div class="el-es-global-section el-es-branding-section" id="el-es-mood-board" data-review-item-id="' . esc_attr( $active_review->id ) . '" data-project-id="' . esc_attr( $project_id ) . '">';
		$html .= '<h3 class="el-es-section-title">';
		$html .= el_es_icon( 'activity' );
		$html .= esc_html__( 'Step 1: Choose Your Style Direction', 'el-core' );
		if ( $is_closed ) {
			$html .= ' <span class="el-es-badge el-es-badge-decision-maker">';
			$html .= el_es_icon( 'check-circle' );
			$html .= esc_html__( 'Confirmed', 'el-core' );
			$html .= '</span>';
		}
		$html .= '</h3>';

		// Closed banner
		if ( $is_closed && ! empty( $confirmed_ids ) ) {
			// Load confirmed template names
			global $wpdb;
			$tbl   = $wpdb->prefix . 'el_es_templates';
			$ph    = implode( ',', array_fill( 0, count( $confirmed_ids ), '%d' ) );
			$confirmed_templates = $wpdb->get_results( $wpdb->prepare(
				"SELECT title, style_category FROM {$tbl} WHERE id IN ({$ph})",
				...$confirmed_ids
			) );
			$confirmed_labels = array_map( fn( $t ) => esc_html( $t->style_category . ' / ' . $t->title ), $confirmed_templates );
			$html .= '<div class="el-es-review-confirmed-banner">';
			$html .= el_es_icon( 'check-circle', 20 );
			$html .= '<strong>' . esc_html__( 'Style Direction Confirmed:', 'el-core' ) . '</strong> ';
			$html .= implode( ', ', $confirmed_labels );
			$html .= '</div>';
		}

		if ( ! $is_closed ) {
			// Intro + progress tracker
			$all_votes    = $module->get_review_votes( (int) $active_review->id );
			$voted_ids    = array_map( fn( $v ) => (int) $v->user_id, $all_votes );
			$stakeholders = $module->get_stakeholders( $project_id );
			$total_sh     = count( $stakeholders );
			$responded    = count( array_filter( $stakeholders, fn( $s ) => in_array( (int) $s->user_id, $voted_ids, true ) ) );
			$pct          = $total_sh > 0 ? round( ( $responded / $total_sh ) * 100 ) : 0;

			$html .= '<p class="el-es-review-intro">';
			$html .= esc_html__( 'Browse the examples below. Mark anything you like ♥ or dislike ✕. Preferences save automatically.', 'el-core' );
			$html .= '</p>';

			// Deadline banner
			if ( $active_review->deadline ) {
				$deadline_ts   = strtotime( $active_review->deadline );
				$now_ts        = current_time( 'timestamp' );
				$diff_days     = ceil( ( $deadline_ts - $now_ts ) / DAY_IN_SECONDS );
				$deadline_str  = date_i18n( get_option( 'date_format' ), $deadline_ts );

				$html .= '<div class="el-es-review-deadline-banner">';
				$html .= el_es_icon( 'calendar' );
				if ( $diff_days > 0 ) {
					/* translators: %1$s: formatted date, %2$d: days remaining */
					$html .= sprintf(
						esc_html__( 'Share preferences by %1$s (%2$d days remaining)', 'el-core' ),
						'<strong>' . $deadline_str . '</strong>',
						$diff_days
					);
				} else {
					$html .= sprintf( esc_html__( 'Deadline was %s', 'el-core' ), '<strong>' . $deadline_str . '</strong>' );
				}
				$html .= '</div>';
			}

			// Progress bar
			$html .= '<div class="el-es-review-progress" data-review-item-id="' . esc_attr( $active_review->id ) . '">';
			$html .= '<div class="el-es-review-progress-label">';
			/* translators: %1$d: responded count, %2$d: total */
			$html .= sprintf( esc_html__( '%1$d of %2$d team members responded', 'el-core' ), $responded, $total_sh );
			$html .= '</div>';
			$html .= '<div class="el-es-review-progress-bar"><div class="el-es-review-progress-fill" style="width:' . $pct . '%"></div></div>';
			$html .= '</div>';

			// Load templates if we have selected IDs
			if ( ! empty( $selected_ids ) ) {
				global $wpdb;
				$tbl          = $wpdb->prefix . 'el_es_templates';
				$ph           = implode( ',', array_fill( 0, count( $selected_ids ), '%d' ) );
				$templates    = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$tbl} WHERE id IN ({$ph}) ORDER BY style_category, sort_order",
					...$selected_ids
				) );

				// Get current user's vote
				$user_id    = get_current_user_id();
				$user_vote  = $module->get_user_vote( (int) $active_review->id, $user_id );
				$vote_prefs = $user_vote ? ( json_decode( $user_vote->vote_data, true )['preferences'] ?? [] ) : [];

				// Group templates by category
				$by_category = [];
				foreach ( $templates as $t ) {
					$by_category[ $t->style_category ][] = $t;
				}

				foreach ( $by_category as $category => $cat_templates ) {
					$html .= '<div class="el-es-mood-board-category">';
					$html .= '<h4 class="el-es-mood-board-category-label">' . esc_html( strtoupper( $category ) ) . '</h4>';
					$html .= '<div class="el-es-mood-board-grid">';

					foreach ( $cat_templates as $t ) {
						$pref     = $vote_prefs[ $t->id ] ?? 'neutral';
						$img_url  = esc_url( $t->image_url );

						$html .= '<div class="el-es-mood-board-card" data-template-id="' . esc_attr( $t->id ) . '">';

						// Image — click to lightbox
						$html .= '<div class="el-es-mood-board-image-wrap">';
						if ( $img_url ) {
							$html .= '<button type="button" class="el-es-lightbox-trigger" data-src="' . $img_url . '" data-caption="' . esc_attr( $t->title ) . '" aria-label="' . esc_attr__( 'View full image', 'el-core' ) . '">';
							$html .= '<img src="' . $img_url . '" alt="' . esc_attr( $t->title ) . '" loading="lazy">';
							$html .= '<div class="el-es-mood-board-zoom-hint">' . el_es_icon( 'external-link', 16 ) . '</div>';
							$html .= '</button>';
						} else {
							$html .= '<div class="el-es-mood-board-no-image">' . el_es_icon( 'file', 32 ) . '</div>';
						}
						$html .= '</div>';

						// Category badge + title
						$html .= '<div class="el-es-mood-board-meta">';
						$html .= '<span class="el-es-mood-board-category-badge">' . esc_html( $t->style_category ) . '</span>';
						$html .= '<div class="el-es-mood-board-title">' . esc_html( $t->title ) . '</div>';
						if ( $t->description ) {
							$html .= '<div class="el-es-mood-board-desc">' . esc_html( $t->description ) . '</div>';
						}
						$html .= '</div>';

						// Vote strip
						$html .= '<div class="el-es-mood-board-vote-strip">';
						foreach ( [ 'liked' => '♥', 'neutral' => '—', 'disliked' => '✕' ] as $vote_val => $vote_label ) {
							$active_class = ( $pref === $vote_val ) ? ' el-es-vote-active el-es-vote-' . $vote_val : '';
							$html .= '<button type="button" class="el-es-vote-btn' . $active_class . '" data-preference="' . esc_attr( $vote_val ) . '" aria-label="' . esc_attr( ucfirst( $vote_val ) ) . '" aria-pressed="' . ( $pref === $vote_val ? 'true' : 'false' ) . '">';
							$html .= '<span class="el-es-vote-icon">' . $vote_label . '</span>';
							$html .= '<span class="el-es-vote-label">' . esc_html( ucfirst( $vote_val ) ) . '</span>';
							$html .= '</button>';
						}
						$html .= '</div>'; // end vote strip
						$html .= '</div>'; // end mood-board-card
					}

					$html .= '</div>'; // end mood-board-grid
					$html .= '</div>'; // end category
				}
			} else {
				$html .= '<div class="el-es-mood-board-empty">';
				$html .= el_es_icon( 'activity', 48 );
				$html .= '<p>' . esc_html__( 'Style examples are being prepared for your review.', 'el-core' ) . '</p>';
				$html .= '</div>';
			}

			// DM: View Results button (shown after all voted or deadline passed)
			if ( $is_decision_maker ) {
				$deadline_passed = $active_review->deadline && strtotime( $active_review->deadline ) < current_time( 'timestamp' );
				$all_voted       = ( $total_sh > 0 && $responded >= $total_sh );

				if ( $all_voted || $deadline_passed ) {
					$html .= '<div class="el-es-mood-board-dm-actions" id="el-es-dm-mood-board-actions">';
					$html .= '<button type="button" class="el-es-btn el-es-btn-secondary el-es-view-results-btn" data-review-item-id="' . esc_attr( $active_review->id ) . '">';
					$html .= el_es_icon( 'activity' );
					$html .= esc_html__( 'View Results', 'el-core' );
					$html .= '</button>';
					$html .= '</div>';
				} else {
					// Still waiting — show a note
					$html .= '<div class="el-es-mood-board-dm-actions el-es-dm-waiting" id="el-es-dm-mood-board-actions">';
					$html .= '<p class="el-es-dm-waiting-note">';
					$html .= el_es_icon( 'info' );
					/* translators: %d: number remaining */
					$html .= sprintf(
						esc_html__( 'Waiting for %d more team member(s) to respond before results are available.', 'el-core' ),
						$total_sh - $responded
					);
					$html .= '</p>';
					$html .= '</div>';
				}
			}
		}

		$html .= '</div>'; // end branding section

		// Results modal (DM only, rendered hidden)
		if ( $is_decision_maker && ! $is_closed && ! empty( $selected_ids ) ) {
			$html .= '<div class="el-es-modal" id="mood-board-results" aria-hidden="true">';
			$html .= '<div class="el-es-modal-overlay" data-close-modal="mood-board-results"></div>';
			$html .= '<div class="el-es-modal-container el-es-modal-large">';
			$html .= '<div class="el-es-modal-header">';
			$html .= '<h3 class="el-es-modal-title">';
			$html .= el_es_icon( 'activity' );
			$html .= esc_html__( 'Style Preference Results', 'el-core' );
			$html .= '</h3>';
			$html .= '<button type="button" class="el-es-modal-close" data-close-modal="mood-board-results" aria-label="' . esc_attr__( 'Close', 'el-core' ) . '">';
			$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
			$html .= '</button>';
			$html .= '</div>';
			$html .= '<div class="el-es-modal-body" id="mood-board-results-body">';
			$html .= '<div class="el-es-loading-spinner">' . el_es_icon( 'activity', 24 ) . ' ' . esc_html__( 'Loading results…', 'el-core' ) . '</div>';
			$html .= '</div>';
			$html .= '<div class="el-es-modal-footer" id="mood-board-results-footer"></div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		// Lightbox
		$html .= '<div class="el-es-lightbox" id="el-es-lightbox" aria-hidden="true">';
		$html .= '<div class="el-es-lightbox-overlay"></div>';
		$html .= '<div class="el-es-lightbox-content">';
		$html .= '<button class="el-es-lightbox-close" aria-label="' . esc_attr__( 'Close', 'el-core' ) . '">';
		$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
		$html .= '</button>';
		$html .= '<img src="" alt="" id="el-es-lightbox-img">';
		$html .= '<div class="el-es-lightbox-caption" id="el-es-lightbox-caption"></div>';
		$html .= '</div>';
		$html .= '</div>';
	}

	// Proposal Section (when sent or accepted)
	$sent_proposal = null;
	$all_proposals = $module->get_proposals( $project_id );
	foreach ( $all_proposals as $p ) {
		if ( $p->status === 'sent' || $p->status === 'accepted' ) {
			$sent_proposal = $p;
			break;
		}
	}
	
	if ( $sent_proposal ) {
		$is_accepted = ( $sent_proposal->status === 'accepted' );
		
		$html .= '<div class="el-es-global-section el-es-proposal-section">';
		$html .= '<h3 class="el-es-section-title">';
		$html .= el_es_icon( 'file-text' );
		$html .= esc_html__( 'Scope of Service Proposal', 'el-core' );
		if ( $is_accepted ) {
			$html .= ' <span class="el-es-badge el-es-badge-decision-maker">' . esc_html__( 'Accepted', 'el-core' ) . '</span>';
		}
		$html .= '</h3>';
		
		$html .= '<div class="el-es-proposal-document">';

		// Letterhead
		$html .= '<div class="el-es-proposal-header">';
		$html .= '<div class="el-es-proposal-logo">Expanded Learning Solutions</div>';
		$html .= '<div class="el-es-proposal-meta">';
		$html .= '<div>' . esc_html( $sent_proposal->proposal_number ) . '</div>';
		$html .= '<div>' . date_i18n( get_option( 'date_format' ), strtotime( $sent_proposal->created_at ) ) . '</div>';
		$html .= '<div>' . sprintf( esc_html__( 'Prepared for: %s', 'el-core' ), esc_html( $sent_proposal->client_organization ?: $sent_proposal->client_name ) ) . '</div>';
		$html .= '</div>';
		$html .= '</div>';

		// Proposal Title
		if ( $sent_proposal->proposal_title ) {
			$html .= '<h1 class="el-es-proposal-title">' . esc_html( $sent_proposal->proposal_title ) . '</h1>';
		}

		// Narrative sections — use new columns, fall back to old columns for pre-migration proposals
		$situation = $sent_proposal->section_situation ?? $sent_proposal->scope_description ?? '';
		$what_we_build = $sent_proposal->section_what_we_build ?? $sent_proposal->goals_objectives ?? '';
		$why_els = $sent_proposal->section_why_els ?? $sent_proposal->activities_description ?? '';
		$investment_text = $sent_proposal->section_investment ?? '';
		$next_steps = $sent_proposal->section_next_steps ?? $sent_proposal->deliverables_text ?? '';

		if ( $situation ) {
			$html .= '<div class="el-es-proposal-section">';
			$html .= '<h2>' . esc_html__( 'The Situation', 'el-core' ) . '</h2>';
			$html .= '<p>' . nl2br( esc_html( $situation ) ) . '</p>';
			$html .= '</div>';
		}

		if ( $what_we_build ) {
			$html .= '<div class="el-es-proposal-section">';
			$html .= '<h2>' . esc_html__( 'What We\'re Building', 'el-core' ) . '</h2>';
			$html .= '<p>' . nl2br( esc_html( $what_we_build ) ) . '</p>';
			$html .= '</div>';
		}

		if ( $why_els ) {
			$html .= '<div class="el-es-proposal-section">';
			$html .= '<h2>' . esc_html__( 'Why Expanded Learning Solutions', 'el-core' ) . '</h2>';
			$html .= '<p>' . nl2br( esc_html( $why_els ) ) . '</p>';
			$html .= '</div>';
		}

		// Investment section with pricing box
		if ( $investment_text || $sent_proposal->final_price > 0 || $sent_proposal->budget_low > 0 ) {
			$html .= '<div class="el-es-proposal-section">';
			$html .= '<h2>' . esc_html__( 'Your Investment', 'el-core' ) . '</h2>';
			if ( $investment_text ) {
				$html .= '<p>' . nl2br( esc_html( $investment_text ) ) . '</p>';
			}
			$html .= '<div class="el-es-proposal-pricing">';
			$price_display = '';
			if ( $sent_proposal->final_price > 0 ) {
				$price_display = '$' . number_format( (float) $sent_proposal->final_price, 2 );
			} elseif ( $sent_proposal->budget_low > 0 ) {
				$price_display = '$' . number_format( (float) $sent_proposal->budget_low, 0 ) . ' – $' . number_format( (float) $sent_proposal->budget_high, 0 );
			}
			if ( $price_display ) {
				$html .= '<div class="el-es-pricing-line">';
				$html .= '<span>' . esc_html__( 'Platform Development', 'el-core' ) . '</span>';
				$html .= '<span>' . $price_display . '</span>';
				$html .= '</div>';
			}
			$html .= '<div class="el-es-pricing-line el-es-pricing-annual">';
			$html .= '<span>' . esc_html__( 'Annual Platform Fee', 'el-core' ) . '</span>';
			$html .= '<span>' . esc_html__( 'Contact us for details', 'el-core' ) . '</span>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}

		if ( $next_steps ) {
			$html .= '<div class="el-es-proposal-section">';
			$html .= '<h2>' . esc_html__( 'What Happens Next', 'el-core' ) . '</h2>';
			$html .= '<p>' . nl2br( esc_html( $next_steps ) ) . '</p>';
			$html .= '</div>';
		}

		// Social Proof
		$html .= '<div class="el-es-proposal-social-proof">';
		$html .= '<p class="el-es-social-proof-label">' . esc_html__( 'Trusted by organizations including:', 'el-core' ) . '</p>';
		$html .= '<div class="el-es-social-proof-logos">';
		$html .= '<div class="el-es-logo-placeholder">NYC Department of Education</div>';
		$html .= '<div class="el-es-logo-placeholder">California Department of Education</div>';
		$html .= '</div>';
		$html .= '</div>';

		// Terms & Conditions (collapsed)
		if ( $sent_proposal->terms_conditions ) {
			$html .= '<div class="el-es-proposal-terms">';
			$html .= '<details>';
			$html .= '<summary>' . esc_html__( 'Terms & Conditions', 'el-core' ) . '</summary>';
			$html .= '<p>' . nl2br( esc_html( $sent_proposal->terms_conditions ) ) . '</p>';
			$html .= '</details>';
			$html .= '</div>';
		}

		$html .= '</div>'; // end proposal-document
		
		// Accept / Decline buttons (DM only, sent proposals only)
		if ( $is_decision_maker && $sent_proposal->status === 'sent' ) {
			$html .= '<div class="el-es-proposal-actions">';
			$html .= '<button type="button" class="el-es-btn el-es-btn-primary el-es-accept-proposal-btn" data-proposal-id="' . esc_attr( $sent_proposal->id ) . '">';
			$html .= el_es_icon( 'check-circle' );
			$html .= esc_html__( 'Accept Proposal', 'el-core' );
			$html .= '</button>';
			$html .= '<button type="button" class="el-es-btn el-es-btn-secondary el-es-decline-proposal-btn" data-proposal-id="' . esc_attr( $sent_proposal->id ) . '">';
			$html .= esc_html__( 'Decline', 'el-core' );
			$html .= '</button>';
			$html .= '</div>';
		} elseif ( $is_accepted ) {
			$accepted_date = $sent_proposal->accepted_at ? date_i18n( get_option( 'date_format' ), strtotime( $sent_proposal->accepted_at ) ) : '';
			$html .= '<div class="el-es-proposal-accepted-notice">';
			$html .= el_es_icon( 'check-circle' );
			$html .= sprintf( esc_html__( 'Accepted on %s', 'el-core' ), $accepted_date );
			$html .= '</div>';
		}
		
		$html .= '</div>'; // end proposal section
	}

	// Project Team (Stakeholders)
	if ( ! empty( $stakeholders ) ) {
		$html .= '<div class="el-es-global-section el-es-team-section">';
		$html .= '<h3 class="el-es-section-title">';
		$html .= el_es_icon( 'users' );
		$html .= esc_html__( 'Project Team', 'el-core' );
		$html .= '</h3>';
		$html .= '<div class="el-es-team-grid">';
		
		foreach ( $stakeholders as $sh ) {
			$user = get_userdata( $sh->user_id );
			if ( ! $user ) continue;
			
			$html .= '<div class="el-es-team-card">';
			$html .= '<div class="el-es-team-info">';
			$html .= '<div class="el-es-team-name">' . esc_html( $user->display_name ) . '</div>';
			$html .= '<div class="el-es-team-email">' . esc_html( $user->user_email ) . '</div>';
			$html .= '<div class="el-es-team-role">';
			if ( $sh->role === 'decision_maker' ) {
				$html .= '<span class="el-es-badge el-es-badge-decision-maker">';
				$html .= el_es_icon( 'check-circle' );
				$html .= esc_html__( 'Decision Maker', 'el-core' );
				$html .= '</span>';
			} else {
				$html .= '<span class="el-es-badge el-es-badge-contributor">';
				$html .= el_es_icon( 'user' );
				$html .= esc_html__( 'Contributor', 'el-core' );
				$html .= '</span>';
			}
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';
		}
		
		$html .= '</div>'; // end team grid
		$html .= '</div>'; // end team section
	}

	// Project Notes/Description (if present)
	if ( ! empty( $project->notes ) ) {
		$html .= '<div class="el-es-global-section el-es-notes-section">';
		$html .= '<h3 class="el-es-section-title">';
		$html .= el_es_icon( 'info' );
		$html .= esc_html__( 'Project Notes', 'el-core' );
		$html .= '</h3>';
		$html .= '<div class="el-es-notes-content">' . wp_kses_post( nl2br( $project->notes ) ) . '</div>';
		$html .= '</div>';
	}
	
	$html .= '</div>'; // end global sections
	
	// Contributor permission notice
	if ( ! $is_decision_maker && $is_stakeholder ) {
		$html .= '<div class="el-es-notice el-es-notice-info">';
		$html .= '<div class="el-es-notice-icon">' . el_es_icon( 'info' ) . '</div>';
		$html .= '<div class="el-es-notice-content">';
		$html .= '<strong>' . esc_html__( 'Your Role:', 'el-core' ) . '</strong> ';
		$html .= esc_html__( 'As a Contributor, you can provide feedback and suggestions. The Decision Maker will review and approve final decisions for this project.', 'el-core' );
		$html .= '</div>';
		$html .= '</div>';
	}

	$html .= '</div>'; // end portal
	return $html;
}

/**
 * Helper function: Generate inline SVG icon
 * Using Feather Icons set (https://feathericons.com/)
 *
 * @param string $name Icon name
 * @param int $size Icon size in pixels
 * @return string SVG HTML
 */
function el_es_icon( string $name, int $size = 20 ): string {
	$icons = [
		'check-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
		'circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle></svg>',
		'file-text' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>',
		'file' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>',
		'message-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>',
		'users' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
		'user' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
		'clipboard' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>',
		'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
		'activity' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>',
		'info' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
		'alert-triangle' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
		'alert-circle' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
		'external-link' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>',
		'chevron-right' => '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>',
	];
	
	return $icons[ $name ] ?? '';
}
