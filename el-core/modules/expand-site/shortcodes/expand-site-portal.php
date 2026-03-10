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

		// Stage 1 (Qualification) — friendly context message
		if ( $num === 1 && $is_current ) {
			$html .= '<div class="el-es-stage-intro-banner">';
			$html .= el_es_icon( 'info', 20 );
			$html .= '<div>';
			$html .= '<strong>' . esc_html__( 'Your project is in the early qualification stage.', 'el-core' ) . '</strong> ';
			$html .= esc_html__( 'We\'re getting to know your goals and confirming this project is a great fit. You\'ll hear from us shortly to schedule a discovery call.', 'el-core' );
			$html .= '</div>';
			$html .= '</div>';
		}

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

		// Proposal card (Stage 3+): always show, greyed out if no sent/accepted proposal
		if ( $num >= 3 ) {
			$stage_proposal = null;
			foreach ( $module->get_proposals( $project_id ) as $p ) {
				if ( $p->status === 'sent' || $p->status === 'accepted' ) { $stage_proposal = $p; break; }
			}
			if ( $stage_proposal ) {
				$prop_badge = $stage_proposal->status === 'accepted' ? esc_html__( 'Accepted', 'el-core' ) : esc_html__( 'Pending', 'el-core' );
				$html .= '<button type="button" class="el-es-info-card el-es-modal-trigger" data-modal="proposal-modal-' . esc_attr( $num ) . '">';
				$html .= '<div class="el-es-info-card-icon">' . el_es_icon( 'media-document', 24 ) . '</div>';
				$html .= '<div class="el-es-info-card-content">';
				$html .= '<div class="el-es-info-card-title">' . esc_html__( 'Proposal', 'el-core' ) . '</div>';
				$html .= '<div class="el-es-info-card-count">' . $prop_badge . '</div>';
				$html .= '</div>';
				$html .= '<div class="el-es-info-card-arrow">' . el_es_icon( 'chevron-right' ) . '</div>';
				$html .= '</button>';
			} else {
				$html .= '<div class="el-es-info-card el-es-info-card-disabled">';
				$html .= '<div class="el-es-info-card-icon">' . el_es_icon( 'media-document', 24 ) . '</div>';
				$html .= '<div class="el-es-info-card-content">';
				$html .= '<div class="el-es-info-card-title">' . esc_html__( 'Proposal', 'el-core' ) . '</div>';
				$html .= '<div class="el-es-info-card-empty">' . esc_html__( 'Not yet sent', 'el-core' ) . '</div>';
				$html .= '</div>';
				$html .= '</div>';
			}
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

		// Proposal Modal (Stage 3+)
		if ( $num >= 3 ) {
			$stage_proposal_modal = null;
			foreach ( $module->get_proposals( $project_id ) as $p ) {
				if ( $p->status === 'sent' || $p->status === 'accepted' ) { $stage_proposal_modal = $p; break; }
			}
			if ( $stage_proposal_modal ) {
				$modal_id = 'proposal-modal-' . $num;
				$html .= '<div class="el-es-modal el-es-proposal-modal" id="' . esc_attr( $modal_id ) . '" aria-hidden="true">';
				$html .= '<div class="el-es-modal-overlay" data-close-modal="' . esc_attr( $modal_id ) . '"></div>';
				$html .= '<div class="el-es-modal-container el-es-modal-container-large">';
				$html .= '<div class="el-es-modal-header">';
				$html .= '<h3 class="el-es-modal-title">' . el_es_icon( 'media-document' ) . esc_html__( 'Scope of Service Proposal', 'el-core' ) . '</h3>';
				$html .= '<button type="button" class="el-es-modal-close" data-close-modal="' . esc_attr( $modal_id ) . '" aria-label="' . esc_attr__( 'Close', 'el-core' ) . '">';
				$html .= '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
				$html .= '</button>';
				$html .= '</div>';
				$html .= '<div class="el-es-modal-body">';
				$html .= el_es_render_proposal_document( $stage_proposal_modal );
				$html .= '</div>';
				$html .= '</div>';
				$html .= '</div>';
			}
		}

		// Stage 4 — User Journey: DM Assignment + Stakeholder Questions
		if ( $num === 4 ) {
			global $wpdb;
			$journeys_table = $wpdb->prefix . 'el_es_user_journeys';
			$journeys = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$journeys_table} WHERE project_id = %d ORDER BY created_at ASC",
				$project_id
			) );

			$current_user_id       = get_current_user_id();
			$journey_list_approved = ! empty( $project->journey_list_approved_at );

			$html .= '<div class="el-es-stage-section el-es-journey-stage">';
			$html .= '<h4 class="el-es-stage-section-title">' . el_es_icon( 'users', 18 ) . esc_html__( 'User Journeys', 'el-core' ) . '</h4>';
			$html .= '<p class="el-es-journey-stage-intro">' . esc_html__( 'In this phase, each member of your team will describe how a specific type of user moves through your website. This helps us design a site that works for everyone who uses it.', 'el-core' ) . '</p>';

			if ( ! $journey_list_approved ) {
				// Admin hasn't sent the list yet
				$html .= '<div class="el-es-placeholder-notice">';
				$html .= el_es_icon( 'clock', 20 );
				$html .= '<p>' . esc_html__( 'Your project manager is reviewing the list of user types and will notify you when it\'s ready for your team\'s input.', 'el-core' ) . '</p>';
				$html .= '</div>';
			} elseif ( empty( $journeys ) ) {
				$html .= '<div class="el-es-placeholder-notice">';
				$html .= el_es_icon( 'clock', 20 );
				$html .= '<p>' . esc_html__( 'Your project manager is finalizing the user type list. Check back soon.', 'el-core' ) . '</p>';
				$html .= '</div>';
			} else {
				// Build stakeholder options for DM assignment dropdown
				$stakeholder_options = '<option value="">' . esc_html__( '— Select a team member —', 'el-core' ) . '</option>';
				foreach ( $stakeholders as $sh ) {
					$su = get_userdata( (int) $sh->user_id );
					if ( $su ) {
						$stakeholder_options .= '<option value="' . esc_attr( $sh->user_id ) . '">' . esc_html( $su->display_name ) . '</option>';
					}
				}

				$html .= '<div class="el-es-journey-list" id="el-es-journey-list" data-project-id="' . esc_attr( $project_id ) . '">';

				foreach ( $journeys as $j ) {
					$jid         = (int) $j->id;
					$jstatus     = $j->status;
					$assigned_to = (int) $j->assigned_to;
					$is_assigned_to_me = ( $assigned_to === $current_user_id );

					$assigned_name = '';
					if ( $assigned_to ) {
						$au = get_userdata( $assigned_to );
						if ( $au ) $assigned_name = $au->display_name;
					}

					$status_labels = [
						'pending_assignment' => __( 'Awaiting Assignment', 'el-core' ),
						'awaiting_input'     => __( 'Awaiting Input', 'el-core' ),
						'awaiting_ai'        => __( 'Processing', 'el-core' ),
						'ai_generated'       => __( 'In Review', 'el-core' ),
						'admin_refined'      => __( 'In Review', 'el-core' ),
						'in_review'          => __( 'Under Review', 'el-core' ),
						'approved'           => __( 'Approved', 'el-core' ),
						'locked'             => __( 'Complete', 'el-core' ),
					];
					$status_label = $status_labels[ $jstatus ] ?? ucfirst( str_replace( '_', ' ', $jstatus ) );

					$html .= '<div class="el-es-journey-card el-es-journey-card--' . esc_attr( $jstatus ) . '" data-journey-id="' . esc_attr( $jid ) . '">';

					// Card header
					$html .= '<div class="el-es-journey-card-header">';
					$html .= '<div class="el-es-journey-card-title">';
					$html .= '<span class="el-es-journey-user-type">' . esc_html( $j->user_type ) . '</span>';
					if ( $assigned_name ) {
						$html .= '<span class="el-es-journey-assignee">' . esc_html( $assigned_name ) . '</span>';
					} else {
						$html .= '<span class="el-es-journey-assignee el-es-journey-assignee--empty">' . esc_html__( 'Unassigned', 'el-core' ) . '</span>';
					}
					$html .= '</div>';
					$html .= '<span class="el-es-badge el-es-badge-' . esc_attr( $jstatus ) . '">' . esc_html( $status_label ) . '</span>';
					$html .= '</div>'; // .el-es-journey-card-header

					// Card body — varies by status and viewer
					$html .= '<div class="el-es-journey-card-body">';

					if ( $jstatus === 'pending_assignment' ) {
						if ( $is_decision_maker ) {
							$html .= '<p class="el-es-journey-card-info">' . esc_html__( 'Assign a team member to describe this user\'s journey through the site.', 'el-core' ) . '</p>';
							$html .= '<div class="el-es-journey-assign-row" data-journey-id="' . esc_attr( $jid ) . '">';
							$html .= '<select class="el-es-journey-assign-select" data-journey-id="' . esc_attr( $jid ) . '">' . $stakeholder_options . '</select>';
							$html .= '<button class="el-btn el-btn-primary el-es-journey-assign-btn" data-journey-id="' . esc_attr( $jid ) . '" data-project-id="' . esc_attr( $project_id ) . '">' . esc_html__( 'Assign', 'el-core' ) . '</button>';
							$html .= '</div>';
						} else {
							$html .= '<p class="el-es-journey-card-info">' . esc_html__( 'Waiting for the Decision Maker to assign a team member to this journey.', 'el-core' ) . '</p>';
						}

					} elseif ( $jstatus === 'awaiting_input' ) {
						if ( $is_assigned_to_me ) {
							// Full 5-question form
							$html .= '<p class="el-es-journey-form-intro">';
							$html .= esc_html__( 'You\'ve been asked to describe the journey for: ', 'el-core' );
							$html .= '<strong>' . esc_html( $j->user_type ) . '</strong>.';
							$html .= ' ' . esc_html__( 'Answer each question in your own words — there are no wrong answers.', 'el-core' );
							$html .= '</p>';

						$questions = [
							1 => [
								'q' => __( 'How does this person first find or arrive at the website?', 'el-core' ),
								'eg' => __( 'They search Google for our services and click a result, or a teacher sends them a link, or they scan a QR code from a flyer.', 'el-core' ),
							],
							2 => [
								'q' => __( 'Do they need to create an account or log in to use the site — or can they get what they need without one?', 'el-core' ),
								'eg' => __( 'They can browse without an account but need to register to enroll. They always need to log in first because the content is private. Or they were given a login code with a pre-assigned profile and user type.', 'el-core' ),
							],
							3 => [
								'q' => __( 'Once they\'re in, what is the first thing they need to do?', 'el-core' ),
								'eg' => __( 'Find out what programs are available, contact someone for more information, or go straight to a dashboard.', 'el-core' ),
							],
							4 => [
								'q' => __( 'What are the main things this person will do on the site on a regular basis?', 'el-core' ),
								'eg' => __( 'Take courses, complete quizzes or exams, read articles, submit assignments, track their progress, or message other users.', 'el-core' ),
							],
							5 => [
								'q' => __( 'What does success look like for this person — what have they accomplished when they leave the site happy?', 'el-core' ),
								'eg' => __( 'They completed a course, passed an exam, found the schedule they needed, or submitted a contact form and got a confirmation.', 'el-core' ),
							],
							6 => [
								'q' => __( 'Is there anything this person should NOT be able to do, or any frustration you want to prevent?', 'el-core' ),
								'eg' => __( 'They should not be able to see other users\' information. They should not get lost trying to find the registration button.', 'el-core' ),
							],
						];

							$html .= '<form class="el-es-journey-form" data-journey-id="' . esc_attr( $jid ) . '" data-project-id="' . esc_attr( $project_id ) . '" id="el-es-journey-form-' . esc_attr( $jid ) . '">';
							foreach ( $questions as $n => $qdata ) {
								$html .= '<div class="el-es-journey-question">';
								$html .= '<label class="el-es-journey-question-label"><strong>' . esc_html( $qdata['q'] ) . '</strong></label>';
								$html .= '<p class="el-es-journey-question-example"><em>' . esc_html__( 'For example:', 'el-core' ) . '</em> ' . esc_html( $qdata['eg'] ) . '</p>';
								$html .= '<textarea class="el-es-journey-answer" name="answer_' . $n . '" rows="3" placeholder="' . esc_attr__( 'Your answer…', 'el-core' ) . '" required></textarea>';
								$html .= '</div>';
							}
							$html .= '<div class="el-es-journey-form-footer">';
							$html .= '<button type="submit" class="el-btn el-btn-primary el-es-journey-submit-btn" disabled data-journey-id="' . esc_attr( $jid ) . '">' . esc_html__( 'Submit My Input', 'el-core' ) . '</button>';
							$html .= '<span class="el-es-journey-submit-status" style="display:none;"></span>';
							$html .= '</div>';
							$html .= '</form>';

							// Reassign link for DM who is also the assigned person
							if ( $is_decision_maker ) {
								$html .= '<div class="el-es-journey-reassign-section" style="margin-top:12px;">';
								$html .= '<a href="#" class="el-es-journey-reassign-toggle" data-journey-id="' . esc_attr( $jid ) . '">' . esc_html__( 'Reassign to a different team member', 'el-core' ) . '</a>';
								$html .= '<div class="el-es-journey-reassign-form" data-journey-id="' . esc_attr( $jid ) . '" style="display:none;margin-top:8px;">';
								$html .= '<select class="el-es-journey-assign-select" data-journey-id="' . esc_attr( $jid ) . '">' . $stakeholder_options . '</select>';
								$html .= '<button class="el-btn el-btn-secondary el-es-journey-assign-btn" data-journey-id="' . esc_attr( $jid ) . '" data-project-id="' . esc_attr( $project_id ) . '" style="margin-top:6px;">' . esc_html__( 'Reassign', 'el-core' ) . '</button>';
								$html .= '</div>';
								$html .= '</div>';
							}

						} else {
							// Not the assigned person
							$waiting_name = $assigned_name ?: esc_html__( 'a team member', 'el-core' );
							$html .= '<p class="el-es-journey-card-info">';
							$html .= sprintf( esc_html__( 'Waiting for %s to complete this journey.', 'el-core' ), '<strong>' . esc_html( $waiting_name ) . '</strong>' );
							$html .= '</p>';

							if ( $is_decision_maker ) {
								$html .= '<div class="el-es-journey-reassign-section">';
								$html .= '<a href="#" class="el-es-journey-reassign-toggle" data-journey-id="' . esc_attr( $jid ) . '">' . esc_html__( 'Reassign to a different team member', 'el-core' ) . '</a>';
								$html .= '<div class="el-es-journey-reassign-form" data-journey-id="' . esc_attr( $jid ) . '" style="display:none;margin-top:8px;">';
								$html .= '<select class="el-es-journey-assign-select" data-journey-id="' . esc_attr( $jid ) . '">' . $stakeholder_options . '</select>';
								$html .= '<button class="el-btn el-btn-secondary el-es-journey-assign-btn" data-journey-id="' . esc_attr( $jid ) . '" data-project-id="' . esc_attr( $project_id ) . '" style="margin-top:6px;">' . esc_html__( 'Reassign', 'el-core' ) . '</button>';
								$html .= '</div>';
								$html .= '</div>';
							}
						}

					} elseif ( $jstatus === 'pending_dm_review' ) {
						// Load the submitted answers
						$pdm_answers = $j->guided_answers ? json_decode( $j->guided_answers, true ) : [];

						$html .= '<div class="el-es-journey-pdm-wrapper">';
						$html .= '<h5 class="el-es-journey-pdm-heading">' . esc_html__( 'Submitted answers — awaiting Decision Maker review', 'el-core' ) . '</h5>';

					if ( ! empty( $pdm_answers ) ) {
						$html .= '<ol class="el-es-journey-pdm-answers">';
						foreach ( $pdm_answers as $n => $qa ) {
							$q_index = $n + 1;
							$html .= '<li class="el-es-journey-pdm-answer">';
							$html .= '<p class="el-es-journey-pdm-q"><strong>' . esc_html( $qa['question'] ?? '' ) . '</strong></p>';
							if ( $is_decision_maker ) {
								$html .= '<textarea class="el-es-journey-pdm-answer-edit" name="answer_' . esc_attr( $q_index ) . '" data-question-index="' . esc_attr( $q_index ) . '" rows="3">' . esc_textarea( $qa['answer'] ?? '' ) . '</textarea>';
							} else {
								$html .= '<p class="el-es-journey-pdm-a">' . esc_html( $qa['answer'] ?? '' ) . '</p>';
							}
							$html .= '</li>';
						}
						$html .= '</ol>';
					}

						// DM send-to-admin section
						if ( $is_decision_maker ) {
							$html .= '<div class="el-es-journey-pdm-dm-section">';
							$html .= '<p class="el-es-journey-pdm-dm-intro">' . esc_html__( 'Review the answers above. Add any notes for the project manager, then send them forward to generate the workflow.', 'el-core' ) . '</p>';
							$html .= '<label class="el-es-journey-pdm-dm-label">' . esc_html__( 'Notes for the project manager (optional):', 'el-core' ) . '</label>';
							$html .= '<textarea class="el-es-journey-pdm-dm-notes" data-journey-id="' . esc_attr( $jid ) . '" rows="3" placeholder="' . esc_attr__( 'e.g. The contributor forgot to mention the login step. Please add a branch for new vs returning users.', 'el-core' ) . '"></textarea>';
							$html .= '<button type="button" class="el-btn el-btn-primary el-es-journey-pdm-send-btn" data-journey-id="' . esc_attr( $jid ) . '" data-project-id="' . esc_attr( $project_id ) . '">';
							$html .= esc_html__( 'Send to Project Manager', 'el-core' );
							$html .= '</button>';
							$html .= '</div>';
						} else {
							$html .= '<p class="el-es-journey-card-info">' . esc_html__( 'Waiting for the Decision Maker to review these answers and send them to the project manager.', 'el-core' ) . '</p>';
						}

						$html .= '</div>'; // .el-es-journey-pdm-wrapper

					} elseif ( $jstatus === 'awaiting_ai' ) {
						$html .= '<p class="el-es-journey-card-info el-es-journey-card-info--processing">';
						$html .= el_es_icon( 'update', 16 );
						$html .= ' ' . esc_html__( 'The project manager is generating the workflow. Check back soon.', 'el-core' );
						$html .= '</p>';

					} elseif ( in_array( $jstatus, [ 'ai_generated', 'admin_refined' ], true ) ) {
						$html .= '<p class="el-es-journey-card-info el-es-journey-card-info--processing">';
						$html .= el_es_icon( 'update', 16 );
						$html .= ' ' . esc_html__( 'Our team is reviewing and building out this workflow. Check back soon.', 'el-core' );
						$html .= '</p>';

					} elseif ( in_array( $jstatus, [ 'in_review', 'approved', 'locked' ], true ) ) {

						// Fetch active review row
						$jreviews_table  = $wpdb->prefix . 'el_es_journey_reviews';
						$jcomments_table = $wpdb->prefix . 'el_es_journey_comments';
						$active_review   = $wpdb->get_row( $wpdb->prepare(
							"SELECT * FROM {$jreviews_table} WHERE journey_id = %d ORDER BY id DESC LIMIT 1",
							$jid
						) );
						$review_id = $active_review ? (int) $active_review->id : 0;

						// Workflow to display — prefer admin_workflow, fall back to ai_workflow
						$wf_raw = $j->admin_workflow ?: $j->ai_workflow;
						$wf     = $wf_raw ? json_decode( $wf_raw, true ) : null;

						// DM revision banner
						if ( $jstatus === 'in_review' && $active_review && $active_review->dm_decision === 'needs_revision' ) {
							$html .= '<div class="el-es-journey-revision-banner">';
							$html .= el_es_icon( 'flag', 16 );
							$html .= ' <strong>' . esc_html__( 'Revision Requested', 'el-core' ) . '</strong>';
							if ( $active_review->dm_note ) {
								$html .= '<p class="el-es-journey-revision-note">' . esc_html( $active_review->dm_note ) . '</p>';
							}
							$html .= '</div>';
						}

						if ( $wf ) {
							$html .= '<div class="el-es-journey-review-content" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '">';

							// Summary
							if ( ! empty( $wf['summary'] ) ) {
								$html .= '<p class="el-es-journey-review-summary">' . esc_html( $wf['summary'] ) . '</p>';
							}

							// Steps with per-step comments + verdicts
							if ( ! empty( $wf['steps'] ) ) {
								// Load all comments for this review
								$all_comments = $review_id ? $wpdb->get_results( $wpdb->prepare(
									"SELECT jc.*, u.display_name FROM {$jcomments_table} jc
									 LEFT JOIN {$wpdb->users} u ON u.ID = jc.user_id
									 WHERE jc.review_id = %d AND jc.comment != '__verdict__'
									 ORDER BY jc.created_at ASC",
									$review_id
								) ) : [];

							// Load this user's verdicts
							$my_verdicts = [];
							if ( $review_id && $current_user_id ) {
								$vrows = $wpdb->get_results( $wpdb->prepare(
									"SELECT step_key, verdict FROM {$jcomments_table}
									 WHERE review_id = %d AND journey_id = %d AND user_id = %d AND comment = '__verdict__'",
									$review_id, $jid, $current_user_id
								) );
								foreach ( $vrows as $vr ) {
									$my_verdicts[ $vr->step_key ] = $vr->verdict;
								}
							}

							// Load ALL verdicts for every stakeholder (for team consensus display)
							$all_verdicts = [];
							if ( $review_id ) {
								$avrows = $wpdb->get_results( $wpdb->prepare(
									"SELECT jc.step_key, jc.verdict, jc.created_at, u.display_name
									 FROM {$jcomments_table} jc
									 LEFT JOIN {$wpdb->users} u ON u.ID = jc.user_id
									 WHERE jc.review_id = %d AND jc.journey_id = %d AND jc.comment = '__verdict__'
									 ORDER BY jc.created_at ASC",
									$review_id, $jid
								) );
								foreach ( $avrows as $av ) {
									$all_verdicts[ $av->step_key ][] = $av;
								}
							}

							$html .= '<ol class="el-es-journey-review-steps" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '">';
							foreach ( $wf['steps'] as $sidx => $step ) {
								$sk            = $step['id'] ?? ( 'step_' . ( $sidx + 1 ) );
								$my_verdict    = $my_verdicts[ $sk ] ?? '';
								$step_verdicts = $all_verdicts[ $sk ] ?? [];
								$step_comments = array_filter( $all_comments, fn( $c ) => $c->step_key === $sk && ! $c->parent_id );

								$html .= '<li class="el-es-journey-review-step" data-step-key="' . esc_attr( $sk ) . '" data-step-index="' . esc_attr( $sidx ) . '">';

								// ── Step header (label + description, editable for in_review) ──
								$html .= '<div class="el-es-journey-step-header">';
								if ( $jstatus === 'in_review' ) {
									$html .= '<div class="el-es-journey-step-content" data-step-key="' . esc_attr( $sk ) . '">';
									$html .= '<div class="el-es-journey-step-view">';
									$html .= '<span class="el-es-journey-step-label"><strong>' . esc_html( $step['label'] ?? '' ) . '</strong></span>';
									$html .= '<span class="el-es-journey-step-desc">' . esc_html( $step['description'] ?? '' ) . '</span>';
									$html .= '<button type="button" class="el-es-journey-step-edit-toggle" data-step-key="' . esc_attr( $sk ) . '" style="margin-left:8px;font-size:12px;color:#6366F1;background:none;border:none;cursor:pointer;text-decoration:underline;">' . esc_html__( 'Edit', 'el-core' ) . '</button>';
									$html .= '</div>'; // .step-view
									$html .= '<div class="el-es-journey-step-edit-form" data-step-key="' . esc_attr( $sk ) . '" style="display:none;margin-top:8px;">';
									$html .= '<input type="text" class="el-es-journey-step-edit-label" value="' . esc_attr( $step['label'] ?? '' ) . '" placeholder="' . esc_attr__( 'Step label (3–5 words)', 'el-core' ) . '" style="width:100%;margin-bottom:6px;padding:6px 8px;border:1px solid #D1D5DB;border-radius:4px;">';
									$html .= '<textarea class="el-es-journey-step-edit-desc" rows="2" placeholder="' . esc_attr__( 'Step description (1 sentence)', 'el-core' ) . '" style="width:100%;resize:both;padding:6px 8px;border:1px solid #D1D5DB;border-radius:4px;">' . esc_textarea( $step['description'] ?? '' ) . '</textarea>';
									$html .= '<div style="margin-top:6px;display:flex;gap:8px;">';
									$html .= '<button type="button" class="el-btn el-btn-primary el-es-journey-step-edit-save" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" data-step-key="' . esc_attr( $sk ) . '" style="font-size:13px;padding:4px 12px;">' . esc_html__( 'Save edit', 'el-core' ) . '</button>';
									$html .= '<button type="button" class="el-es-journey-step-edit-cancel" data-step-key="' . esc_attr( $sk ) . '" style="font-size:13px;background:none;border:none;cursor:pointer;color:#6B7280;text-decoration:underline;">' . esc_html__( 'Cancel', 'el-core' ) . '</button>';
									$html .= '</div>';
									$html .= '</div>'; // .step-edit-form
									$html .= '</div>'; // .step-content
								} else {
									$html .= '<span class="el-es-journey-step-label"><strong>' . esc_html( $step['label'] ?? '' ) . '</strong></span>';
									$html .= '<span class="el-es-journey-step-desc">' . esc_html( $step['description'] ?? '' ) . '</span>';
								}

								// ── Verdict buttons ──
								if ( $jstatus === 'in_review' ) {
									$html .= '<div class="el-es-journey-verdict-row">';
									$html .= '<button type="button" class="el-es-journey-verdict-btn' . ( $my_verdict === 'approved' ? ' el-es-journey-verdict-btn--active' : '' ) . '" data-verdict="approved" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" data-step-key="' . esc_attr( $sk ) . '">';
									$html .= '&#10003; ' . esc_html__( 'Looks good', 'el-core' );
									$html .= '</button>';
									$html .= '<button type="button" class="el-es-journey-verdict-btn el-es-journey-verdict-btn--flag' . ( $my_verdict === 'needs_revision' ? ' el-es-journey-verdict-btn--active' : '' ) . '" data-verdict="needs_revision" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" data-step-key="' . esc_attr( $sk ) . '">';
									$html .= '&#9872; ' . esc_html__( 'Flag for changes', 'el-core' );
									$html .= '</button>';
									$html .= '</div>';

									// ── My verdict banner ──
									if ( $my_verdict ) {
										$html .= '<div class="el-es-journey-my-verdict-banner el-es-journey-my-verdict-banner--' . esc_attr( $my_verdict ) . '" data-step-key="' . esc_attr( $sk ) . '">';
										if ( $my_verdict === 'approved' ) {
											$html .= '&#10003; ' . sprintf(
												/* translators: date and time */
												esc_html__( 'You marked this step "Looks good"', 'el-core' )
											);
										} else {
											$html .= '&#9872; ' . esc_html__( 'You flagged this step for changes', 'el-core' );
										}
										$html .= '</div>';
									}

									// ── Team consensus badges ──
									if ( ! empty( $step_verdicts ) ) {
										$html .= '<div class="el-es-journey-team-verdicts">';
										$html .= '<span class="el-es-journey-team-verdicts-label">' . esc_html__( 'Team:', 'el-core' ) . '</span>';
										foreach ( $step_verdicts as $tv ) {
											$tv_class = $tv->verdict === 'approved' ? 'el-es-journey-team-verdict--approved' : 'el-es-journey-team-verdict--flag';
											$tv_icon  = $tv->verdict === 'approved' ? '&#10003;' : '&#9872;';
											$tv_time  = wp_date( 'M j g:i a', strtotime( $tv->created_at ) );
											$html .= '<span class="el-es-journey-team-verdict ' . esc_attr( $tv_class ) . '" title="' . esc_attr( $tv->display_name . ' — ' . $tv_time ) . '">';
											$html .= $tv_icon . ' ' . esc_html( $tv->display_name );
											$html .= '</span>';
										}
										$html .= '</div>';
									}
								}
								$html .= '</div>'; // .step-header

								// ── Step comments ──
								if ( ! empty( $step_comments ) ) {
									$html .= '<ul class="el-es-journey-step-comments">';
									foreach ( $step_comments as $sc ) {
										$html .= '<li class="el-es-journey-comment" data-comment-id="' . esc_attr( $sc->id ) . '">';
										$html .= '<span class="el-es-journey-comment-author">' . esc_html( $sc->display_name ) . '</span>';
										$html .= '<span class="el-es-journey-comment-text">' . esc_html( $sc->comment ) . '</span>';
										$replies = array_filter( $all_comments, fn( $r ) => (int) $r->parent_id === (int) $sc->id );
										if ( ! empty( $replies ) ) {
											$html .= '<ul class="el-es-journey-comment-replies">';
											foreach ( $replies as $reply ) {
												$html .= '<li><span class="el-es-journey-comment-author">' . esc_html( $reply->display_name ) . '</span> ';
												$html .= '<span class="el-es-journey-comment-text">' . esc_html( $reply->comment ) . '</span></li>';
											}
											$html .= '</ul>';
										}
										if ( $jstatus === 'in_review' ) {
											$html .= '<button type="button" class="el-es-journey-reply-toggle" data-comment-id="' . esc_attr( $sc->id ) . '">' . esc_html__( 'Reply', 'el-core' ) . '</button>';
											$html .= '<div class="el-es-journey-reply-form" data-comment-id="' . esc_attr( $sc->id ) . '" style="display:none;">';
											$html .= '<textarea class="el-es-journey-reply-input" rows="2" placeholder="' . esc_attr__( 'Reply…', 'el-core' ) . '"></textarea>';
											$html .= '<button type="button" class="el-btn el-btn-secondary el-es-journey-reply-submit" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" data-step-key="' . esc_attr( $sk ) . '" data-parent-id="' . esc_attr( $sc->id ) . '">' . esc_html__( 'Post', 'el-core' ) . '</button>';
											$html .= '</div>';
										}
										$html .= '</li>';
									}
									$html .= '</ul>';
								}

								// ── Add comment + Insert/Remove step controls (in_review only) ──
								if ( $jstatus === 'in_review' ) {
									$html .= '<div class="el-es-journey-add-comment">';
									$html .= '<button type="button" class="el-es-journey-comment-toggle" data-journey-id="' . esc_attr( $jid ) . '" data-step-key="' . esc_attr( $sk ) . '">' . esc_html__( '+ Add comment', 'el-core' ) . '</button>';
									$html .= '<div class="el-es-journey-comment-form" data-step-key="' . esc_attr( $sk ) . '" style="display:none;">';
									$html .= '<textarea class="el-es-journey-comment-input" rows="2" placeholder="' . esc_attr__( 'Your comment…', 'el-core' ) . '"></textarea>';
									$html .= '<button type="button" class="el-btn el-btn-primary el-es-journey-comment-submit" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" data-step-key="' . esc_attr( $sk ) . '">' . esc_html__( 'Post', 'el-core' ) . '</button>';
									$html .= '</div>';
									$html .= '</div>';
									// Insert step below + Remove step
									$html .= '<div class="el-es-journey-step-actions">';
									$html .= '<button type="button" class="el-es-portal-insert-step-btn" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" style="font-size:12px;color:#6366F1;background:none;border:1px dashed #6366F1;border-radius:4px;padding:3px 10px;cursor:pointer;margin-right:8px;">+ ' . esc_html__( 'Insert step below', 'el-core' ) . '</button>';
									$html .= '<button type="button" class="el-es-portal-remove-step-btn" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" style="font-size:12px;color:#EF4444;background:none;border:none;cursor:pointer;">✕ ' . esc_html__( 'Remove step', 'el-core' ) . '</button>';
									$html .= '</div>';
								}

								$html .= '</li>';
							}
							$html .= '</ol>';
							}

							// Implied pages
							if ( ! empty( $wf['implied_pages'] ) ) {
								$html .= '<p class="el-es-journey-implied-pages"><strong>' . esc_html__( 'Implied pages:', 'el-core' ) . '</strong> ' . esc_html( implode( ', ', $wf['implied_pages'] ) ) . '</p>';
							}

							$html .= '</div>'; // .el-es-journey-review-content
						}

						// Overall comment box + DM decision (in_review only)
						if ( $jstatus === 'in_review' ) {
							// Overall comment
							$overall_comments = $review_id ? $wpdb->get_results( $wpdb->prepare(
								"SELECT jc.*, u.display_name FROM {$jcomments_table} jc
								 LEFT JOIN {$wpdb->users} u ON u.ID = jc.user_id
								 WHERE jc.review_id = %d AND jc.step_key IS NULL AND jc.comment != '__verdict__'
								 ORDER BY jc.created_at ASC",
								$review_id
							) ) : [];

							$html .= '<div class="el-es-journey-overall-comments">';
							$html .= '<h5>' . esc_html__( 'Overall comments', 'el-core' ) . '</h5>';
							if ( ! empty( $overall_comments ) ) {
								$html .= '<ul class="el-es-journey-step-comments">';
								foreach ( $overall_comments as $oc ) {
									$html .= '<li><span class="el-es-journey-comment-author">' . esc_html( $oc->display_name ) . '</span> <span class="el-es-journey-comment-text">' . esc_html( $oc->comment ) . '</span></li>';
								}
								$html .= '</ul>';
							}
							$html .= '<textarea class="el-es-journey-comment-input el-es-journey-overall-comment-input" rows="2" placeholder="' . esc_attr__( 'Add an overall comment…', 'el-core' ) . '"></textarea>';
							$html .= '<button type="button" class="el-btn el-btn-secondary el-es-journey-comment-submit" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" data-step-key="">' . esc_html__( 'Post Comment', 'el-core' ) . '</button>';
							$html .= '</div>';

							// DM decision section
							if ( $is_decision_maker ) {
								$dm_decided   = $active_review && $active_review->dm_decision;
								$html .= '<div class="el-es-journey-dm-decision">';
								$html .= '<h5>' . esc_html__( 'Your Review &amp; Decision', 'el-core' ) . '</h5>';
								if ( $dm_decided ) {
									$html .= '<p class="el-es-journey-dm-decided">' . ( $active_review->dm_decision === 'approved'
										? esc_html__( 'You approved this journey.', 'el-core' )
										: esc_html__( 'You requested revisions.', 'el-core' ) ) . '</p>';
								} else {
									// DM edit section — write suggested changes before deciding
									$html .= '<p class="el-es-journey-dm-edit-intro">' . esc_html__( 'If you have changes or corrections to suggest, write them below before making your decision. The project manager will incorporate your notes before sending a revised version.', 'el-core' ) . '</p>';
									$html .= '<label class="el-es-journey-dm-edit-label">' . esc_html__( 'Your suggested edits (optional):', 'el-core' ) . '</label>';
									$html .= '<textarea class="el-es-journey-dm-edit-notes" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '" rows="4" placeholder="' . esc_attr__( 'e.g. Step 3 should come before step 2. Add a step for email verification. The summary is missing the enrollment step.', 'el-core' ) . '"></textarea>';
									$html .= '<p class="el-es-journey-dm-decision-intro">' . esc_html__( 'When you\'re ready, choose your decision:', 'el-core' ) . '</p>';
									$html .= '<div class="el-es-journey-dm-btns">';
									$html .= '<button type="button" class="el-btn el-btn-primary el-es-journey-dm-decision-btn" data-decision="approved" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '">' . esc_html__( '✓ Accept this workflow', 'el-core' ) . '</button>';
									$html .= '<button type="button" class="el-btn el-btn-danger el-es-journey-dm-decision-btn" data-decision="needs_revision" data-journey-id="' . esc_attr( $jid ) . '" data-review-id="' . esc_attr( $review_id ) . '">' . esc_html__( '⚑ Request changes', 'el-core' ) . '</button>';
									$html .= '</div>';
									$html .= '<p class="el-es-journey-dm-decision-hint">' . esc_html__( '"Accept" locks in this workflow for approval. "Request changes" sends your notes back to the project manager.', 'el-core' ) . '</p>';
								}
								$html .= '</div>';
							}
						}

						// Approved / locked read-only banner
						if ( $jstatus === 'approved' ) {
							$html .= '<div class="el-es-journey-approved-banner">';
							$html .= el_es_icon( 'yes-alt', 18 );
							$html .= ' <strong>' . esc_html__( 'This journey has been approved by the team.', 'el-core' ) . '</strong>';
							$html .= '</div>';
						} elseif ( $jstatus === 'locked' ) {
							$html .= '<div class="el-es-journey-locked-banner">';
							$html .= el_es_icon( 'lock', 18 );
							$html .= ' <strong>' . esc_html__( 'This journey has been finalized.', 'el-core' ) . '</strong>';
							$html .= '</div>';
						}

					}

					$html .= '</div>'; // .el-es-journey-card-body
					$html .= '</div>'; // .el-es-journey-card
				}

				$html .= '</div>'; // .el-es-journey-list
			}

			$html .= '</div>'; // .el-es-journey-stage
		}

		$html .= '</div>'; // end stage content
	}
	
	$html .= '</div>'; // end stage content wrapper
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

		// When accepted and project has moved past Stage 3, show only a brief confirmation.
		// The full proposal is accessible via the Proposal info card modal in the stage cards row.
		if ( $is_accepted && $current_stage > 3 ) {
			$accepted_date = $sent_proposal->accepted_at ? date_i18n( get_option( 'date_format' ), strtotime( $sent_proposal->accepted_at ) ) : '';
			$accepted_by   = '';
			if ( $sent_proposal->accepted_by ) {
				$acc_user = get_userdata( (int) $sent_proposal->accepted_by );
				if ( $acc_user ) $accepted_by = $acc_user->display_name;
			}
			$html .= '<div class="el-es-global-section el-es-proposal-section el-es-proposal-accepted-summary">';
			$html .= '<h3 class="el-es-section-title">' . el_es_icon( 'check-circle' ) . esc_html__( 'Proposal', 'el-core' ) . ' <span class="el-es-badge el-es-badge-decision-maker">' . esc_html__( 'Accepted', 'el-core' ) . '</span></h3>';
			$html .= '<p class="el-es-proposal-accepted-line">';
			if ( $accepted_by ) {
				$html .= sprintf( esc_html__( 'Accepted on %1$s by %2$s.', 'el-core' ), '<strong>' . esc_html( $accepted_date ) . '</strong>', '<strong>' . esc_html( $accepted_by ) . '</strong>' );
			} else {
				$html .= sprintf( esc_html__( 'Accepted on %s.', 'el-core' ), '<strong>' . esc_html( $accepted_date ) . '</strong>' );
			}
			$html .= ' ' . esc_html__( 'View the full proposal using the Proposal card above.', 'el-core' );
			$html .= '</p>';
			$html .= '</div>';
		} else {
			// Stage 3 (or pending): show full inline proposal document
			$html .= '<div class="el-es-global-section el-es-proposal-section">';
			$html .= '<h3 class="el-es-section-title">';
			$html .= el_es_icon( 'file-text' );
			$html .= esc_html__( 'Scope of Service Proposal', 'el-core' );
			if ( $is_accepted ) {
				$html .= ' <span class="el-es-badge el-es-badge-decision-maker">' . esc_html__( 'Accepted', 'el-core' ) . '</span>';
			}
			$html .= '</h3>';

			$html .= el_es_render_proposal_document( $sent_proposal );

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
 * Helper: render a proposal document as HTML (used in portal proposal modal and global section).
 */
function el_es_render_proposal_document( $proposal ): string {
	$html = '';

	$situation     = $proposal->section_situation ?? $proposal->scope_description ?? '';
	$what_we_build = $proposal->section_what_we_build ?? $proposal->goals_objectives ?? '';
	$why_els       = $proposal->section_why_els ?? $proposal->activities_description ?? '';
	$investment    = $proposal->section_investment ?? '';
	$next_steps    = $proposal->section_next_steps ?? $proposal->deliverables_text ?? '';

	$html .= '<div class="el-es-proposal-document">';

	// Print button
	$html .= '<div class="el-es-proposal-toolbar no-print">';
	$html .= '<button type="button" class="el-es-btn el-es-proposal-print-btn" onclick="window.print()">';
	$html .= el_es_icon( 'download' );
	$html .= esc_html__( 'Download PDF', 'el-core' );
	$html .= '</button>';
	$html .= '</div>';

	// Letterhead
	$html .= '<div class="el-es-proposal-header">';
	$html .= '<div class="el-es-proposal-logo">Expanded Learning Solutions</div>';
	$html .= '<div class="el-es-proposal-meta">';
	$html .= '<div>' . esc_html( $proposal->proposal_number ) . '</div>';
	$html .= '<div>' . date_i18n( get_option( 'date_format' ), strtotime( $proposal->created_at ) ) . '</div>';
	$html .= '<div>' . sprintf( esc_html__( 'Prepared for: %s', 'el-core' ), esc_html( $proposal->client_organization ?: $proposal->client_name ) ) . '</div>';
	$html .= '</div>';
	$html .= '</div>';

	if ( $proposal->proposal_title ) {
		$html .= '<h1 class="el-es-proposal-title">' . esc_html( $proposal->proposal_title ) . '</h1>';
	}

	if ( $situation ) {
		$html .= '<div class="el-es-proposal-section"><h2>' . esc_html__( 'The Situation', 'el-core' ) . '</h2>';
		$html .= '<p>' . nl2br( esc_html( $situation ) ) . '</p></div>';
	}

	if ( $what_we_build ) {
		$html .= '<div class="el-es-proposal-section"><h2>' . esc_html__( 'What We\'re Building', 'el-core' ) . '</h2>';
		$html .= '<p>' . nl2br( esc_html( $what_we_build ) ) . '</p></div>';
	}

	if ( $why_els ) {
		$html .= '<div class="el-es-proposal-section"><h2>' . esc_html__( 'Why Expanded Learning Solutions', 'el-core' ) . '</h2>';
		$html .= '<p>' . nl2br( esc_html( $why_els ) ) . '</p></div>';
	}

	// Investment section
	if ( $investment || $proposal->final_price > 0 || $proposal->budget_low > 0 ) {
		$html .= '<div class="el-es-proposal-section"><h2>' . esc_html__( 'Your Investment', 'el-core' ) . '</h2>';
		if ( $investment ) {
			$html .= '<p>' . nl2br( esc_html( $investment ) ) . '</p>';
		}
		$html .= '<div class="el-es-proposal-pricing">';
		$final_price = (float) $proposal->final_price;
		$annual_fee  = (float) ( $proposal->annual_platform_fee ?? 0 );
		$first_pay   = (float) ( $proposal->first_payment_amount ?? 0 );
		$final_pay   = (float) ( $proposal->final_payment_amount ?? 0 );
		if ( $first_pay === 0.0 && $final_price > 0 ) $first_pay = $final_price * 0.25;
		if ( $final_pay === 0.0 && $final_price > 0 ) $final_pay = $final_price * 0.75;

		if ( $final_price > 0 ) {
			$html .= '<div class="el-es-pricing-line el-es-pricing-total"><span>' . esc_html__( 'Development Investment', 'el-core' ) . '</span><span>$' . number_format( $final_price, 0 ) . '</span></div>';
			if ( $first_pay > 0 ) {
				$html .= '<div class="el-es-pricing-line el-es-pricing-sub"><span>' . esc_html__( 'First Payment (25%) — due upon wireframe approval', 'el-core' ) . '</span><span>$' . number_format( $first_pay, 0 ) . '</span></div>';
			}
			if ( $final_pay > 0 ) {
				$html .= '<div class="el-es-pricing-line el-es-pricing-sub"><span>' . esc_html__( 'Final Payment (75%) — due upon delivery', 'el-core' ) . '</span><span>$' . number_format( $final_pay, 0 ) . '</span></div>';
			}
		} elseif ( $proposal->budget_low > 0 ) {
			$html .= '<div class="el-es-pricing-line"><span>' . esc_html__( 'Platform Development', 'el-core' ) . '</span><span>$' . number_format( (float) $proposal->budget_low, 0 ) . ' – $' . number_format( (float) $proposal->budget_high, 0 ) . '</span></div>';
		}
		$html .= '<div class="el-es-pricing-line el-es-pricing-annual"><span>' . esc_html__( 'Annual Platform Fee', 'el-core' ) . '</span>';
		$html .= $annual_fee > 0 ? '<span>$' . number_format( $annual_fee, 0 ) . '/year</span>' : '<span>' . esc_html__( 'Contact us for details', 'el-core' ) . '</span>';
		$html .= '</div>';
		$html .= '</div></div>';
	}

	if ( $next_steps ) {
		$html .= '<div class="el-es-proposal-section"><h2>' . esc_html__( 'What Happens Next', 'el-core' ) . '</h2>';
		$html .= '<p>' . nl2br( esc_html( $next_steps ) ) . '</p></div>';
	}

	// T&C
	if ( $proposal->terms_conditions ) {
		$html .= '<div class="el-es-proposal-terms"><details>';
		$html .= '<summary>' . esc_html__( 'Terms & Conditions', 'el-core' ) . '</summary>';
		$html .= '<div class="el-es-proposal-terms-body">';
		$paragraphs = preg_split( '/\n{2,}/', trim( $proposal->terms_conditions ) );
		foreach ( $paragraphs as $para ) {
			$para = trim( $para );
			if ( empty( $para ) ) continue;
			// Split on first newline: line 0 = heading candidate, rest = body
			$lines = explode( "\n", $para, 2 );
			$first_line = trim( $lines[0] );
			$rest       = isset( $lines[1] ) ? trim( $lines[1] ) : '';
			if ( preg_match( '/^\d+\./', $first_line ) ) {
				$html .= '<div class="el-es-tc-section"><p class="el-es-tc-heading">' . esc_html( $first_line ) . '</p>';
				if ( $rest ) $html .= '<p class="el-es-tc-body">' . nl2br( esc_html( $rest ) ) . '</p>';
				$html .= '</div>';
			} else {
				$html .= '<p class="el-es-tc-para">' . nl2br( esc_html( $para ) ) . '</p>';
			}
		}
		$html .= '</div></details></div>';
	}

	$html .= '</div>'; // end proposal-document
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
