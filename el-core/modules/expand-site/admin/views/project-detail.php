<?php
/**
 * Admin View: Expand Site — Project Detail
 *
 * Single project view with tabs: Overview, Stages, Deliverables, Pages, Feedback.
 * Uses EL_Admin_UI components for all rendering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$module     = EL_Expand_Site_Module::instance();
$core       = EL_Core::instance();
$project_id = absint( $_GET['project'] ?? 0 );
$project    = $module->get_project( $project_id );

if ( ! $project ) {
    echo EL_Admin_UI::wrap(
        EL_Admin_UI::notice( [ 'message' => __( 'Project not found.', 'el-core' ), 'type' => 'error' ] )
    );
    return;
}

$stage_history  = $module->get_stage_history( $project_id );
$deliverables   = $module->get_deliverables( $project_id );
$feedback       = $module->get_feedback( $project_id );
$pages          = $module->get_pages( $project_id );
$change_orders  = $module->get_change_orders( $project_id );
$stakeholders   = $module->get_stakeholders( $project_id );
$definition     = $module->get_project_definition( $project_id );
$proposals      = $module->get_proposals( $project_id );
$current_stage  = (int) $project->current_stage;

$pending_feedback = count( array_filter( $feedback, fn( $f ) => $f->status === 'pending' ) );

$html = '';

// Page header
$html .= EL_Admin_UI::page_header( [
    'title'      => esc_html( $project->name ),
    'subtitle'   => esc_html( $project->client_name ),
    'back_url'   => admin_url( 'admin.php?page=el-core-projects' ),
    'back_label' => __( '← All Projects', 'el-core' ),
    'actions'    => [
        [
            'label'   => __( 'Edit Project', 'el-core' ),
            'variant' => 'secondary',
            'icon'    => 'edit',
            'url'     => admin_url( 'admin.php?page=el-core-projects&project=' . $project_id . '&action=edit' ),
        ],
        [
            'label'   => __( 'Advance Stage', 'el-core' ),
            'variant' => 'primary',
            'icon'    => 'arrow-right-alt',
            'data'    => [ 'modal-open' => 'advance-stage-modal' ],
        ],
    ],
] );

// Status row
$html .= EL_Admin_UI::stats_grid( [
    [
        'icon'    => 'flag',
        'number'  => $current_stage . '/8',
        'label'   => $module->get_stage_name( $current_stage ),
        'variant' => EL_Expand_Site_Module::get_stage_badge_variant( $current_stage ),
    ],
    [
        'icon'    => 'update',
        'number'  => ucfirst( $project->status ),
        'label'   => __( 'Status', 'el-core' ),
        'variant' => EL_Expand_Site_Module::get_status_badge_variant( $project->status ),
    ],
    [
        'icon'    => 'media-document',
        'number'  => count( $deliverables ),
        'label'   => __( 'Deliverables', 'el-core' ),
        'variant' => 'info',
    ],
    [
        'icon'    => 'format-chat',
        'number'  => $pending_feedback,
        'label'   => __( 'Pending Feedback', 'el-core' ),
        'variant' => $pending_feedback > 0 ? 'warning' : 'success',
    ],
] );

// ── Stage Progress Stepper ──
$stepper_html = '<div class="el-es-stage-stepper">';
foreach ( EL_Expand_Site_Module::STAGES as $stage_num => $stage_info ) {
    $stage_class = 'el-es-stepper-step';
    if ( $stage_num < $current_stage ) {
        $stage_class .= ' el-es-stepper-complete';
    } elseif ( $stage_num === $current_stage ) {
        $stage_class .= ' el-es-stepper-current';
    } else {
        $stage_class .= ' el-es-stepper-upcoming';
    }
    $stepper_html .= '<div class="' . $stage_class . '">';
    $stepper_html .= '<div class="el-es-stepper-circle">';
    if ( $stage_num < $current_stage ) {
        $stepper_html .= '<span class="dashicons dashicons-yes"></span>';
    } else {
        $stepper_html .= '<span>' . $stage_num . '</span>';
    }
    $stepper_html .= '</div>';
    $stepper_html .= '<div class="el-es-stepper-label">' . esc_html( $stage_info['name'] ) . '</div>';
    if ( $stage_num < count( EL_Expand_Site_Module::STAGES ) ) {
        $stepper_html .= '<div class="el-es-stepper-connector"></div>';
    }
    $stepper_html .= '</div>';
}
$stepper_html .= '</div>';
$html .= '<div class="el-card" style="margin-bottom:0;border-radius:8px 8px 0 0;border-bottom:1px solid #e5e7eb;">'
       . '<div class="el-card__body" style="padding:20px 24px;">' . $stepper_html . '</div></div>';

// ── Current Stage Status Card ──
$status_card_items = [];

// Definition review status (relevant in early stages)
if ( $current_stage <= 3 && isset( $definition ) ) {
    $def_status = ( $definition && $definition->locked_at ) ? 'locked' : ( $definition->review_status ?? 'draft' );
    $def_status_labels = [
        'draft'          => __( 'Definition: Draft — not yet sent for review', 'el-core' ),
        'pending_review' => __( 'Definition: Awaiting client review', 'el-core' ),
        'approved'       => __( 'Definition: Client approved — lock required', 'el-core' ),
        'needs_revision' => __( 'Definition: Client requested revisions', 'el-core' ),
        'locked'         => __( 'Definition: Locked ✓', 'el-core' ),
    ];
    $def_status_colors = [
        'draft'          => '#6b7280',
        'pending_review' => '#2563eb',
        'approved'       => '#d97706',
        'needs_revision' => '#dc2626',
        'locked'         => '#059669',
    ];
    $def_status_label = $def_status_labels[ $def_status ] ?? ucfirst( $def_status );
    $def_color = $def_status_colors[ $def_status ] ?? '#6b7280';
    $status_card_items[] = '<div class="el-es-status-item">'
        . '<span class="el-es-status-dot" style="background:' . $def_color . ';"></span>'
        . '<span class="el-es-status-text" style="color:' . $def_color . ';font-weight:600;">' . esc_html( $def_status_label ) . '</span>'
        . '</div>';

    // Active review deadline
    if ( $active_review && $active_review->deadline && $def_status === 'pending_review' ) {
        $deadline_ts = strtotime( $active_review->deadline );
        $now_ts = time();
        $diff_days = ceil( ( $deadline_ts - $now_ts ) / 86400 );
        $deadline_str = $diff_days > 0
            ? sprintf( __( 'Review deadline: %s (%d days remaining)', 'el-core' ), date_i18n( 'M j, Y', $deadline_ts ), $diff_days )
            : sprintf( __( 'Review deadline: %s (OVERDUE)', 'el-core' ), date_i18n( 'M j, Y', $deadline_ts ) );
        $status_card_items[] = '<div class="el-es-status-item">'
            . '<span class="dashicons dashicons-clock" style="font-size:14px;color:#6b7280;"></span>'
            . '<span class="el-es-status-text">' . esc_html( $deadline_str ) . '</span>'
            . '</div>';
    }

    // DM decision note (when needs_revision)
    if ( $def_status === 'needs_revision' && $active_review && $active_review->dm_note ) {
        $dm_user = get_userdata( $active_review->dm_decided_by ?? 0 );
        $dm_name = $dm_user ? $dm_user->display_name : __( 'Decision Maker', 'el-core' );
        $status_card_items[] = '<div class="el-es-status-item el-es-status-dm-note">'
            . '<span class="dashicons dashicons-format-chat" style="font-size:14px;color:#dc2626;"></span>'
            . '<span class="el-es-status-text"><strong>' . esc_html( $dm_name ) . ':</strong> ' . esc_html( $active_review->dm_note ) . '</span>'
            . '</div>';
    }
}

// Project deadline
if ( $project->deadline ) {
    $deadline_ts = strtotime( $project->deadline );
    $now_ts = time();
    if ( $deadline_ts < $now_ts ) {
        $days_over = floor( ( $now_ts - $deadline_ts ) / 86400 );
        $status_card_items[] = '<div class="el-es-status-item">'
            . '<span class="dashicons dashicons-warning" style="font-size:14px;color:#dc2626;"></span>'
            . '<span class="el-es-status-text" style="color:#dc2626;">' . sprintf( __( 'Stage deadline overdue by %d day(s) — %s', 'el-core' ), $days_over, date_i18n( 'M j, Y', $deadline_ts ) ) . '</span>'
            . '</div>';
    } else {
        $diff_days = ceil( ( $deadline_ts - $now_ts ) / 86400 );
        $color = $diff_days <= 2 ? '#d97706' : '#6b7280';
        $status_card_items[] = '<div class="el-es-status-item">'
            . '<span class="dashicons dashicons-calendar-alt" style="font-size:14px;color:' . $color . ';"></span>'
            . '<span class="el-es-status-text" style="color:' . $color . ';">' . sprintf( __( 'Stage deadline: %s (%d days)', 'el-core' ), date_i18n( 'M j, Y', $deadline_ts ), $diff_days ) . '</span>'
            . '</div>';
    }
}

if ( ! empty( $status_card_items ) ) {
    $status_card_html = '<div class="el-es-stage-status-card">'
        . implode( '', $status_card_items )
        . '</div>';
    $html .= '<div class="el-card" style="margin-bottom:20px;border-radius:0 0 8px 8px;border-top:none;">'
           . '<div class="el-card__header" style="padding:10px 24px 0;">'
           . '<h3 class="el-card__title" style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;">' . __( 'Stage Status', 'el-core' ) . '</h3>'
           . '</div>'
           . '<div class="el-card__body" style="padding:12px 24px 16px;">' . $status_card_html . '</div></div>';
}

// ── Determine active tab based on current stage ──
$stage_tab_map = [
    1 => 'transcript',  // Stage 1: Qualification — Discovery tab prominent
    2 => 'transcript',  // Stage 2: Discovery — Definition review prominent
    3 => 'proposals',   // Stage 3: Scope Lock — Proposals prominent
    4 => 'branding',    // Stage 4: Visual Identity — Branding prominent
    5 => 'pages',       // Stage 5: Wireframes — Pages prominent
    6 => 'pages',       // Stage 6: Build — Pages prominent
    7 => 'feedback',    // Stage 7: Review — Feedback prominent
    8 => 'deliverables',// Stage 8: Delivery — Deliverables prominent
];
$active_tab = $stage_tab_map[ $current_stage ] ?? 'overview';

// ── Tabs ──
$html .= EL_Admin_UI::tab_nav( [
    'group' => 'project-tabs',
    'tabs'  => [
        [ 'id' => 'overview',      'label' => __( 'Overview', 'el-core' ),      'icon' => 'dashboard',      'active' => $active_tab === 'overview' ],
        [ 'id' => 'stakeholders',  'label' => __( 'Stakeholders', 'el-core' ),  'icon' => 'groups',         'badge' => count( $stakeholders ), 'active' => $active_tab === 'stakeholders' ],
        [ 'id' => 'transcript',    'label' => __( 'Discovery', 'el-core' ),     'icon' => 'media-text',     'active' => $active_tab === 'transcript' ],
        [ 'id' => 'proposals',     'label' => __( 'Proposals', 'el-core' ),    'icon' => 'media-document', 'badge' => count( $proposals ) ?: null, 'active' => $active_tab === 'proposals' ],
        [ 'id' => 'stages',        'label' => __( 'Stage History', 'el-core' ), 'icon' => 'backup',         'active' => $active_tab === 'stages' ],
        [ 'id' => 'deliverables',  'label' => __( 'Deliverables', 'el-core' ),  'icon' => 'media-document', 'badge' => count( $deliverables ), 'active' => $active_tab === 'deliverables' ],
        [ 'id' => 'pages',         'label' => __( 'Pages', 'el-core' ),         'icon' => 'admin-page',     'badge' => count( $pages ), 'active' => $active_tab === 'pages' ],
        [ 'id' => 'feedback',      'label' => __( 'Feedback', 'el-core' ),      'icon' => 'format-chat',    'badge' => $pending_feedback ?: null, 'active' => $active_tab === 'feedback' ],
        [ 'id' => 'branding',      'label' => __( 'Branding', 'el-core' ),      'icon' => 'art',            'active' => $active_tab === 'branding' ],
    ],
] );

// ── Tab: Overview ──
$overview = '';

$overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Client', 'el-core' ),        'value' => esc_html( $project->client_name ), 'icon' => 'businessperson' ] );
$overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Current Stage', 'el-core' ),  'value' => $current_stage . '. ' . $module->get_stage_name( $current_stage ), 'icon' => 'flag' ] );
$overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Status', 'el-core' ),         'value' => ucfirst( $project->status ), 'icon' => 'marker' ] );

if ( $project->final_price > 0 ) {
    $overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Final Price', 'el-core' ), 'value' => '$' . number_format( $project->final_price, 2 ), 'icon' => 'money-alt' ] );
} elseif ( $project->budget_range_low > 0 || $project->budget_range_high > 0 ) {
    $overview .= EL_Admin_UI::detail_row( [
        'label' => __( 'Budget Range', 'el-core' ),
        'value' => '$' . number_format( $project->budget_range_low, 0 ) . ' – $' . number_format( $project->budget_range_high, 0 ),
        'icon'  => 'money-alt',
    ] );
}

if ( $project->scope_locked_at ) {
    $overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Scope Locked', 'el-core' ), 'value' => date_i18n( 'M j, Y g:i A', strtotime( $project->scope_locked_at ) ), 'icon' => 'lock' ] );
}

$overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Created', 'el-core' ), 'value' => date_i18n( 'M j, Y', strtotime( $project->created_at ) ), 'icon' => 'calendar' ] );

if ( $project->notes ) {
    $overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Notes', 'el-core' ), 'value' => wp_kses_post( nl2br( $project->notes ) ), 'icon' => 'editor-alignleft' ] );
}

// Change orders summary
if ( ! empty( $change_orders ) ) {
    $co_total = array_sum( array_map( fn( $co ) => (float) $co->change_order_price, $change_orders ) );
    $overview .= EL_Admin_UI::notice( [
        'message' => sprintf(
            __( '<strong>%d change order(s)</strong> totaling <strong>$%s</strong>', 'el-core' ),
            count( $change_orders ),
            number_format( $co_total, 2 )
        ),
        'type' => 'warning',
    ] );
}

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'overview',
    'group'   => 'project-tabs',
    'content' => EL_Admin_UI::card( [ 'title' => __( 'Project Details', 'el-core' ), 'icon' => 'info-outline', 'content' => $overview ] ),
    'active'  => $active_tab === 'overview',
] );

// ── Tab: Discovery Transcript ──
$transcript_content = '';

// Definition status and review data
$review_status = ( $definition && isset( $definition->review_status ) ) ? $definition->review_status : 'draft';
$active_review = $module->get_active_definition_review( $project_id );
$reviews       = $module->get_definition_reviews( $project_id );
$comments      = $active_review ? $module->get_definition_comments( (int) $active_review->id ) : [];
$verdicts      = $active_review ? $module->get_definition_verdicts( (int) $active_review->id ) : [];

// Status badge: Draft / Sent for Review / Client Approved / Needs Revision / Locked
$status_labels = [
	'draft'          => __( 'Draft', 'el-core' ),
	'pending_review' => __( 'Sent for Review', 'el-core' ),
	'approved'       => __( 'Client Approved', 'el-core' ),
	'needs_revision' => __( 'Needs Revision', 'el-core' ),
	'locked'         => __( 'Locked', 'el-core' ),
];
$status_variants = [
	'draft'          => 'default',
	'pending_review' => 'info',
	'approved'       => 'success',
	'needs_revision' => 'warning',
	'locked'         => 'success',
];
$effective_status = ( $definition && $definition->locked_at ) ? 'locked' : $review_status;
$transcript_content .= '<div class="el-es-definition-status-row" style="margin-bottom: 16px;">';
$transcript_content .= EL_Admin_UI::badge( [
	'label'   => $status_labels[ $effective_status ] ?? ucfirst( $effective_status ),
	'variant' => $status_variants[ $effective_status ] ?? 'default',
] );
$transcript_content .= '</div>';

// Check if definition is locked
$is_locked = $definition && $definition->locked_at;

// Amber action banner: prompt admin to lock when client has approved
if ( $effective_status === 'approved' && ! $is_locked ) {
    $transcript_content .= '<div class="el-es-lock-action-banner" style="background:#fffbeb;border:2px solid #f59e0b;border-radius:8px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">';
    $transcript_content .= '<span class="dashicons dashicons-warning" style="color:#d97706;font-size:22px;flex-shrink:0;"></span>';
    $transcript_content .= '<div style="flex:1;min-width:200px;">';
    $transcript_content .= '<strong style="color:#92400e;">' . __( 'The client has approved the Project Definition.', 'el-core' ) . '</strong>';
    $transcript_content .= '<p style="margin:4px 0 0;color:#78350f;font-size:13px;">' . __( 'Lock it now to proceed to the next stage.', 'el-core' ) . '</p>';
    $transcript_content .= '</div>';
    $transcript_content .= EL_Admin_UI::btn( [
        'label'   => __( 'Lock Definition', 'el-core' ),
        'variant' => 'primary',
        'icon'    => 'lock',
        'id'      => 'lock-definition-banner-btn',
        'data'    => [
            'project-id'    => $project_id,
            'review-status' => $review_status,
        ],
    ] );
    $transcript_content .= '</div>';
}

// Send for Review button (when not locked, status is draft or needs_revision)
$can_send = ! $is_locked && in_array( $effective_status, [ 'draft', 'needs_revision' ], true );
if ( $can_send ) {
    $transcript_content .= '<div class="el-es-definition-actions-row" style="margin-bottom: 16px;">';
    $transcript_content .= EL_Admin_UI::btn( [
        'label'   => __( 'Send to Client for Review', 'el-core' ),
        'variant' => 'primary',
        'icon'    => 'email',
        'id'      => 'send-definition-review-btn',
        'data'    => [ 'project-id' => $project_id, 'modal-open' => 'send-definition-review-modal' ],
    ] );
    $transcript_content .= '</div>';
}

// DM verdict summary card (when there's an active open review)
if ( $active_review && $active_review->status === 'open' && ! empty( $verdicts ) ) {
    $fields_accepted = 0;
    $fields_revision = 0;
    foreach ( $verdicts as $v ) {
        $rev = $v['needs_revision'] ?? 0;
        $app = $v['approved'] ?? 0;
        if ( $rev > 0 ) {
            $fields_revision++;
        } elseif ( $app > 0 ) {
            $fields_accepted++;
        }
    }
    $transcript_content .= '<div class="el-es-verdict-summary-card el-card" style="margin-bottom: 20px;">';
    $transcript_content .= '<div class="el-card__header"><h3 class="el-card__title">' . esc_html__( 'Stakeholder Verdicts', 'el-core' ) . '</h3></div>';
    $transcript_content .= '<div class="el-card__body">';
    $transcript_content .= '<p>' . sprintf(
        /* translators: %1$d = fields accepted count, %2$d = fields needing revision count */
        __( '%1$d fields accepted, %2$d need revision', 'el-core' ),
        (int) $fields_accepted,
        (int) $fields_revision
    ) . '</p>';
    if ( $active_review->deadline ) {
        $transcript_content .= '<p><small>' . sprintf(
            /* translators: %s = deadline date */
            __( 'Deadline: %s', 'el-core' ),
            date_i18n( 'M j, Y g:i A', strtotime( $active_review->deadline ) )
        ) . '</small></p>';
    }
    $transcript_content .= '</div></div>';
}

// Per-field stakeholder comments panel (admin sees all comments)
if ( $active_review && ! empty( $comments ) ) {
    $def_field_labels = [
        'site_description'  => __( 'Site Description', 'el-core' ),
        'primary_goal'       => __( 'Primary Goal', 'el-core' ),
        'secondary_goals'    => __( 'Secondary Goals', 'el-core' ),
        'target_customers'   => __( 'Target Customers', 'el-core' ),
        'user_types'         => __( 'User Types', 'el-core' ),
        'site_type'          => __( 'Site Type', 'el-core' ),
        'overall'            => __( 'Overall', 'el-core' ),
    ];
    $transcript_content .= '<div class="el-es-definition-comments-panel el-card" style="margin-bottom: 20px;">';
    $transcript_content .= '<div class="el-card__header"><h3 class="el-card__title">' . esc_html__( 'Stakeholder Comments', 'el-core' ) . '</h3></div>';
    $transcript_content .= '<div class="el-card__body el-es-comments-list">';
    foreach ( $comments as $field_key => $field_comments ) {
        $label = $def_field_labels[ $field_key ] ?? $field_key;
        $transcript_content .= '<div class="el-es-comments-field" data-field-key="' . esc_attr( $field_key ) . '">';
        $transcript_content .= '<h4 class="el-es-comments-field-title">' . esc_html( $label ) . '</h4>';
        foreach ( $field_comments as $c ) {
            $transcript_content .= '<div class="el-es-comment-item' . ( $c->parent_id ? ' el-es-comment-reply' : '' ) . '">';
            $transcript_content .= '<div class="el-es-comment-meta">' . esc_html( $c->display_name ?? 'Unknown' );
            if ( $c->verdict ) {
                $transcript_content .= ' <span class="el-es-comment-verdict el-es-verdict-' . esc_attr( $c->verdict ) . '">' . ( $c->verdict === 'approved' ? '✓ Looks good' : 'Needs revision' ) . '</span>';
            }
            $transcript_content .= ' <span class="el-es-comment-date">' . esc_html( date_i18n( 'M j, g:i A', strtotime( $c->created_at ) ) ) . '</span></div>';
            $transcript_content .= '<div class="el-es-comment-text">' . nl2br( esc_html( $c->comment ) ) . '</div>';
            $transcript_content .= '</div>';
            foreach ( $c->replies ?? [] as $r ) {
                $transcript_content .= '<div class="el-es-comment-item el-es-comment-reply">';
                $transcript_content .= '<div class="el-es-comment-meta">' . esc_html( $r->display_name ?? 'Unknown' );
                if ( ! empty( $r->verdict ) ) {
                    $transcript_content .= ' <span class="el-es-comment-verdict el-es-verdict-' . esc_attr( $r->verdict ) . '">' . ( $r->verdict === 'approved' ? '✓ Looks good' : 'Needs revision' ) . '</span>';
                }
                $transcript_content .= ' <span class="el-es-comment-date">' . esc_html( date_i18n( 'M j, g:i A', strtotime( $r->created_at ) ) ) . '</span></div>';
                $transcript_content .= '<div class="el-es-comment-text">' . nl2br( esc_html( $r->comment ) ) . '</div>';
                $transcript_content .= '</div>';
            }
        }
        $transcript_content .= '</div>';
    }
    $transcript_content .= '</div></div>';
}

// Version History collapsible (show when there are 2+ review rounds with snapshots)
$reviews_with_snapshots = array_filter( $reviews, fn( $r ) => ! empty( $r->snapshot ) );
if ( count( $reviews_with_snapshots ) >= 2 ) {
    $history_reviews = array_values( $reviews_with_snapshots );
    $def_field_labels = [
        'site_description'  => __( 'Site Description', 'el-core' ),
        'primary_goal'       => __( 'Primary Goal', 'el-core' ),
        'secondary_goals'    => __( 'Secondary Goals', 'el-core' ),
        'target_customers'   => __( 'Target Customers', 'el-core' ),
        'user_types'         => __( 'User Types', 'el-core' ),
        'site_type'          => __( 'Site Type', 'el-core' ),
    ];

    $history_html = '<details class="el-es-version-history-details" style="margin-bottom:20px;">';
    $history_html .= '<summary style="cursor:pointer;font-weight:600;color:#4f46e5;padding:12px 16px;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;list-style:none;display:flex;align-items:center;gap:8px;">';
    $history_html .= '<span class="dashicons dashicons-backup" style="font-size:16px;"></span>';
    $history_html .= esc_html__( 'Version History', 'el-core' );
    $history_html .= ' <span style="font-size:12px;font-weight:400;color:#6b7280;">' . sprintf( __( '(%d rounds)', 'el-core' ), count( $history_reviews ) ) . '</span>';
    $history_html .= '</summary>';
    $history_html .= '<div style="border:1px solid #ddd6fe;border-top:none;border-radius:0 0 6px 6px;padding:16px;">';

    foreach ( array_reverse( $history_reviews ) as $i => $rev ) {
        $rev_index = array_search( $rev, $history_reviews );
        $prev_rev = $rev_index > 0 ? $history_reviews[ $rev_index - 1 ] : null;

        $sent_by_user = get_userdata( $rev->sent_by );
        $sent_by_name = $sent_by_user ? $sent_by_user->display_name : __( 'Unknown', 'el-core' );

        $history_html .= '<div class="el-es-version-round" style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #f3f4f6;">';
        $history_html .= '<h4 style="margin:0 0 6px;font-size:14px;color:#111827;">';
        $history_html .= sprintf( __( 'Round %d', 'el-core' ), (int) $rev->round );
        $history_html .= ' <span style="font-size:12px;font-weight:400;color:#6b7280;">— ' . sprintf( __( 'Sent by %s on %s', 'el-core' ), esc_html( $sent_by_name ), date_i18n( 'M j, Y g:i A', strtotime( $rev->sent_at ) ) ) . '</span>';
        $history_html .= '</h4>';

        // DM decision
        if ( $rev->dm_decision ) {
            $dm_user = get_userdata( $rev->dm_decided_by );
            $dm_name = $dm_user ? $dm_user->display_name : __( 'Decision Maker', 'el-core' );
            $decision_color = $rev->dm_decision === 'accepted' ? '#059669' : '#dc2626';
            $decision_label = $rev->dm_decision === 'accepted' ? __( 'Accepted', 'el-core' ) : __( 'Needs Revision', 'el-core' );
            $history_html .= '<p style="font-size:13px;margin:0 0 6px;">';
            $history_html .= '<strong style="color:' . $decision_color . ';">' . esc_html( $decision_label ) . '</strong>';
            $history_html .= ' — ' . esc_html( $dm_name );
            if ( $rev->dm_decided_at ) {
                $history_html .= ' (' . date_i18n( 'M j, Y', strtotime( $rev->dm_decided_at ) ) . ')';
            }
            if ( $rev->dm_note ) {
                $history_html .= '<br><em style="color:#6b7280;">' . esc_html( $rev->dm_note ) . '</em>';
            }
            $history_html .= '</p>';
        }

        // Field diff vs previous round
        if ( $prev_rev && ! empty( $prev_rev->snapshot ) ) {
            $diffs = $module->diff_definition_snapshots( $prev_rev->snapshot, $rev->snapshot );
            if ( ! empty( $diffs ) ) {
                $history_html .= '<div style="font-size:12px;color:#6b7280;margin-top:8px;">' . __( 'Changed from previous round:', 'el-core' ) . '</div>';
                $history_html .= '<div style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">';
                foreach ( $diffs as $field_key => $diff ) {
                    $label = $def_field_labels[ $field_key ] ?? $field_key;
                    $history_html .= '<div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;padding:8px 10px;">';
                    $history_html .= '<strong style="font-size:12px;color:#374151;">' . esc_html( $label ) . '</strong><br>';
                    if ( $diff['old'] !== '' ) {
                        $history_html .= '<span style="font-size:12px;color:#dc2626;text-decoration:line-through;">' . esc_html( mb_strimwidth( $diff['old'], 0, 200, '…' ) ) . '</span><br>';
                    }
                    $history_html .= '<span style="font-size:12px;color:#059669;">' . esc_html( mb_strimwidth( $diff['new'], 0, 200, '…' ) ) . '</span>';
                    $history_html .= '</div>';
                }
                $history_html .= '</div>';
            } else {
                $history_html .= '<p style="font-size:12px;color:#9ca3af;margin:4px 0 0;">' . __( 'No field changes from previous round.', 'el-core' ) . '</p>';
            }
        }
        $history_html .= '</div>';
    }

    $history_html .= '</div></details>';
    $transcript_content .= $history_html;
} elseif ( count( $reviews_with_snapshots ) === 1 ) {
    // Just one round with a snapshot — show a notice that history will appear after round 2
    $transcript_content .= '<p style="font-size:12px;color:#9ca3af;margin-bottom:12px;">' . __( 'Version history will appear after a second review round is sent.', 'el-core' ) . '</p>';
}

if ( $is_locked ) {
    $locked_by = get_userdata( $definition->locked_by );
    $transcript_content .= EL_Admin_UI::notice( [
        'message' => sprintf(
            __( '<strong>Definition Locked</strong> — Locked by %s on %s. Changes cannot be made.', 'el-core' ),
            $locked_by ? esc_html( $locked_by->display_name ) : 'Unknown',
            date_i18n( 'M j, Y g:i A', strtotime( $definition->locked_at ) )
        ),
        'type' => 'success',
    ] );
}

// Transcript input section
if ( ! $definition || ! $definition->locked_at ) {
    $transcript_value = esc_textarea( wp_unslash( $project->discovery_transcript ?? '' ) );
    $has_transcript = ! empty( $project->discovery_transcript );
    
    $transcript_content .= '<div class="el-card" style="margin-bottom: 20px;">';
    $transcript_content .= '<div class="el-card__header">';
    $transcript_content .= '<h3 class="el-card__title">' . __( 'Meeting Transcript', 'el-core' ) . '</h3>';
    $transcript_content .= '</div>';
    $transcript_content .= '<div class="el-card__body">';
    $transcript_content .= EL_Admin_UI::notice( [
        'message' => __( 'Paste your Fathom meeting summary or any discovery call transcript. The AI will extract project requirements and pre-fill the definition below.', 'el-core' ),
        'type' => 'info',
    ] );
    $transcript_content .= '<textarea id="discovery-transcript" rows="12" style="width: 100%; font-family: monospace; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' . $transcript_value . '</textarea>';
    $transcript_content .= '<div style="margin-top: 15px;">';
    $transcript_content .= EL_Admin_UI::btn( [
        'label'   => __( 'Process with AI', 'el-core' ),
        'variant' => 'primary',
        'icon'    => 'admin-generic',
        'id'      => 'process-transcript-btn',
        'data'    => [ 'project-id' => $project_id ],
    ] );
    if ( $has_transcript ) {
        $transcript_content .= ' <span style="color: #666; font-size: 13px;">' . sprintf(
            __( 'Last processed: %s', 'el-core' ),
            $project->discovery_extracted_at ? date_i18n( 'M j, Y g:i A', strtotime( $project->discovery_extracted_at ) ) : 'Never'
        ) . '</span>';
    }
    $transcript_content .= '</div>';
    $transcript_content .= '</div>';
    $transcript_content .= '</div>';
}

// Definition form section
$def_form = '<form id="project-definition-form">';
$def_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';

$def_form .= EL_Admin_UI::form_row( [
    'name'     => 'site_description',
    'label'    => __( 'Site Description', 'el-core' ),
    'type'     => 'textarea',
    'value'    => $definition?->site_description ?? '',
    'readonly' => $is_locked,
    'help'     => __( 'A brief overview of what this website will be.', 'el-core' ),
] );

$def_form .= EL_Admin_UI::form_row( [
    'name'     => 'primary_goal',
    'label'    => __( 'Primary Goal', 'el-core' ),
    'type'     => 'textarea',
    'value'    => $definition?->primary_goal ?? '',
    'readonly' => $is_locked,
    'help'     => __( 'The main objective this website should achieve.', 'el-core' ),
] );

$def_form .= EL_Admin_UI::form_row( [
    'name'     => 'secondary_goals',
    'label'    => __( 'Secondary Goals', 'el-core' ),
    'type'     => 'textarea',
    'value'    => $definition?->secondary_goals ?? '',
    'readonly' => $is_locked,
    'help'     => __( 'Additional objectives (one per line or comma-separated).', 'el-core' ),
] );

$def_form .= EL_Admin_UI::form_row( [
    'name'     => 'target_customers',
    'label'    => __( 'Target Customers', 'el-core' ),
    'type'     => 'textarea',
    'value'    => $definition?->target_customers ?? '',
    'readonly' => $is_locked,
    'help'     => __( 'Who is this site designed to reach?', 'el-core' ),
] );

$def_form .= EL_Admin_UI::form_row( [
    'name'     => 'user_types',
    'label'    => __( 'User Types', 'el-core' ),
    'type'     => 'textarea',
    'value'    => $definition?->user_types ?? '',
    'readonly' => $is_locked,
    'help'     => __( 'Different types of users and their roles (e.g., "Students", "Teachers", "Administrators").', 'el-core' ),
] );

$def_form .= EL_Admin_UI::form_row( [
    'name'     => 'site_type',
    'label'    => __( 'Site Type', 'el-core' ),
    'type'     => 'text',
    'value'    => $definition?->site_type ?? '',
    'readonly' => $is_locked,
    'help'     => __( 'e.g., "E-commerce", "Educational Portal", "Corporate Website"', 'el-core' ),
] );

if ( ! $is_locked ) {
    $def_form .= '<div class="el-form-row">';
    $def_form .= EL_Admin_UI::btn( [
        'label'   => __( 'Save Definition', 'el-core' ),
        'variant' => 'secondary',
        'icon'    => 'saved',
        'type'    => 'submit',
    ] );
    $def_form .= ' ';
    $def_form .= EL_Admin_UI::btn( [
        'label'   => __( 'Confirm & Lock Definition', 'el-core' ),
        'variant' => 'primary',
        'icon'    => 'lock',
        'id'      => 'lock-definition-btn',
        'data'    => [
            'project-id'     => $project_id,
            'review-status'  => $review_status,
        ],
    ] );
    $def_form .= '</div>';
}

$def_form .= '</form>';

$transcript_content .= EL_Admin_UI::card( [
    'title'   => __( 'Project Definition', 'el-core' ),
    'icon'    => 'edit-page',
    'content' => $def_form,
] );

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'transcript',
    'group'   => 'project-tabs',
    'content' => $transcript_content,
    'active'  => $active_tab === 'transcript',
] );

// ── Tab: Proposals ──
$proposals_content = '';

$accepted_proposal = $module->get_accepted_proposal( $project_id );
if ( $accepted_proposal ) {
    $accepted_by_user = get_userdata( $accepted_proposal->accepted_by );
    $proposals_content .= EL_Admin_UI::notice( [
        'message' => sprintf(
            __( '<strong>Proposal Accepted</strong> — %s accepted on %s. Final price: $%s', 'el-core' ),
            $accepted_by_user ? esc_html( $accepted_by_user->display_name ) : 'Client',
            date_i18n( 'M j, Y g:i A', strtotime( $accepted_proposal->accepted_at ) ),
            number_format( (float) $accepted_proposal->final_price, 2 )
        ),
        'type' => 'success',
    ] );
}

$proposal_rows = [];
foreach ( $proposals as $prop ) {
    $status_variant = match ( $prop->status ) {
        'draft'    => 'default',
        'sent'     => 'info',
        'accepted' => 'success',
        'declined' => 'error',
        'revised'  => 'warning',
        default    => 'default',
    };

    $prop_actions = '';
    if ( $prop->status === 'draft' ) {
        $prop_actions .= EL_Admin_UI::btn( [
            'label'   => __( 'Edit', 'el-core' ),
            'variant' => 'ghost',
            'icon'    => 'edit',
            'class'   => 'el-es-edit-proposal-btn',
            'data'    => [ 'proposal-id' => $prop->id ],
        ] );
        $prop_actions .= EL_Admin_UI::btn( [
            'label'   => __( 'Send', 'el-core' ),
            'variant' => 'ghost',
            'icon'    => 'email',
            'class'   => 'el-es-send-proposal-btn',
            'data'    => [ 'proposal-id' => $prop->id ],
        ] );
        $prop_actions .= EL_Admin_UI::btn( [
            'label'   => __( 'Delete', 'el-core' ),
            'variant' => 'ghost',
            'icon'    => 'trash',
            'class'   => 'el-es-delete-proposal-btn',
            'data'    => [ 'proposal-id' => $prop->id ],
        ] );
    } elseif ( $prop->status === 'sent' ) {
        $prop_actions .= EL_Admin_UI::btn( [
            'label'   => __( 'Edit', 'el-core' ),
            'variant' => 'ghost',
            'icon'    => 'edit',
            'class'   => 'el-es-edit-proposal-btn',
            'data'    => [ 'proposal-id' => $prop->id ],
        ] );
    }

    $proposal_rows[] = [
        'number' => '<strong>' . esc_html( $prop->proposal_number ) . '</strong>',
        'title'  => esc_html( $prop->proposal_title ?: '—' ),
        'price'  => $prop->final_price > 0 ? '$' . number_format( (float) $prop->final_price, 2 ) : ( $prop->budget_low > 0 ? '$' . number_format( (float) $prop->budget_low, 0 ) . '–$' . number_format( (float) $prop->budget_high, 0 ) : '—' ),
        'status' => EL_Admin_UI::badge( [ 'label' => ucfirst( $prop->status ), 'variant' => $status_variant ] ),
        'date'   => date_i18n( 'M j, Y', strtotime( $prop->created_at ) ),
        '__actions' => $prop_actions,
    ];
}

$proposals_content .= EL_Admin_UI::data_table( [
    'columns' => [
        [ 'key' => 'number', 'label' => __( '#', 'el-core' ) ],
        [ 'key' => 'title',  'label' => __( 'Title', 'el-core' ) ],
        [ 'key' => 'price',  'label' => __( 'Price', 'el-core' ) ],
        [ 'key' => 'status', 'label' => __( 'Status', 'el-core' ) ],
        [ 'key' => 'date',   'label' => __( 'Created', 'el-core' ) ],
    ],
    'rows'  => $proposal_rows,
    'empty' => [
        'icon'    => 'media-document',
        'title'   => __( 'No proposals yet', 'el-core' ),
        'message' => __( 'Create a proposal to send to the client for approval.', 'el-core' ),
        'action'  => [ 'label' => __( 'New Proposal', 'el-core' ), 'variant' => 'primary', 'data' => [ 'modal-open' => 'create-proposal-modal' ] ],
    ],
] );

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'proposals',
    'group'   => 'project-tabs',
    'content' => EL_Admin_UI::card( [
        'title'   => __( 'Scope of Service Proposals', 'el-core' ),
        'icon'    => 'media-document',
        'content' => $proposals_content,
        'actions' => [
            [ 'label' => __( 'New Proposal', 'el-core' ), 'variant' => 'secondary', 'icon' => 'plus-alt', 'class' => 'el-es-new-proposal-btn', 'data' => [ 'project-id' => $project_id ] ],
        ],
    ] ),
    'active'  => $active_tab === 'proposals',
] );

// ── Proposal Edit Modal ──
$edit_proposal_form  = '<form id="edit-proposal-form">';
$edit_proposal_form .= '<input type="hidden" name="proposal_id" id="edit-proposal-id" value="">';
$edit_proposal_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';

$edit_proposal_form .= '<div style="display:flex; gap:10px; margin-bottom:15px;">';
$edit_proposal_form .= EL_Admin_UI::btn( [
    'label'   => __( 'Generate with AI', 'el-core' ),
    'variant' => 'secondary',
    'icon'    => 'admin-generic',
    'id'      => 'generate-proposal-ai-btn',
    'data'    => [ 'project-id' => $project_id ],
] );
$edit_proposal_form .= '<span id="ai-proposal-status" style="line-height:36px; color:#666;"></span>';
$edit_proposal_form .= '</div>';

$edit_proposal_form .= '<div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">';
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'proposal_title', 'label' => __( 'Proposal Title', 'el-core' ), 'required' => true, 'id' => 'prop-title' ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'client_name', 'label' => __( 'Client Name', 'el-core' ), 'id' => 'prop-client-name' ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'client_organization', 'label' => __( 'Organization', 'el-core' ), 'id' => 'prop-client-org' ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'client_email', 'label' => __( 'Client Email', 'el-core' ), 'type' => 'email', 'id' => 'prop-client-email' ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'project_dates', 'label' => __( 'Project Dates', 'el-core' ), 'placeholder' => 'e.g., March–May 2026', 'id' => 'prop-dates' ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'project_location', 'label' => __( 'Location', 'el-core' ), 'id' => 'prop-location' ] );
$edit_proposal_form .= '</div>';

$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'section_situation', 'label' => __( 'Situation', 'el-core' ), 'type' => 'textarea', 'id' => 'prop-situation', 'help' => __( 'Mirror the client\'s specific problem back to them. Reference their organization and real details from the transcript.', 'el-core' ) ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'section_what_we_build', 'label' => __( 'What We\'re Building', 'el-core' ), 'type' => 'textarea', 'id' => 'prop-what-we-build', 'help' => __( 'Describe capabilities by user type. One sentence per user type, focused on what they can do and what outcome that enables.', 'el-core' ) ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'section_why_els', 'label' => __( 'Why ELS', 'el-core' ), 'type' => 'textarea', 'id' => 'prop-why-els', 'help' => __( 'Why Expanded Learning Solutions is the right partner. Reference similar organizations served.', 'el-core' ) ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'section_investment', 'label' => __( 'Investment', 'el-core' ), 'type' => 'textarea', 'id' => 'prop-investment', 'help' => __( 'Development cost + annual platform fee as monthly number + ROI comparison. No bullet points — write as a paragraph.', 'el-core' ) ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'section_next_steps', 'label' => __( 'Next Steps', 'el-core' ), 'type' => 'textarea', 'id' => 'prop-next-steps', 'help' => __( 'Specific steps that happen after acceptance. Be concrete about timeline.', 'el-core' ) ] );

$edit_proposal_form .= '<div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">';
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'budget_low', 'label' => __( 'Budget Low ($)', 'el-core' ), 'type' => 'number', 'id' => 'prop-budget-low' ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'budget_high', 'label' => __( 'Budget High ($)', 'el-core' ), 'type' => 'number', 'id' => 'prop-budget-high' ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'final_price', 'label' => __( 'Final Price ($)', 'el-core' ), 'type' => 'number', 'id' => 'prop-final-price' ] );
$edit_proposal_form .= '</div>';

$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'payment_terms', 'label' => __( 'Payment Terms', 'el-core' ), 'type' => 'textarea', 'id' => 'prop-payment' ] );
$edit_proposal_form .= EL_Admin_UI::form_row( [ 'name' => 'terms_conditions', 'label' => __( 'Terms & Conditions', 'el-core' ), 'type' => 'textarea', 'id' => 'prop-terms' ] );

$edit_proposal_form .= '<div class="el-form-row">';
$edit_proposal_form .= EL_Admin_UI::btn( [ 'label' => __( 'Save Proposal', 'el-core' ), 'variant' => 'primary', 'icon' => 'saved', 'type' => 'submit' ] );
$edit_proposal_form .= '</div>';
$edit_proposal_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'edit-proposal-modal',
    'title'   => __( 'Edit Proposal', 'el-core' ),
    'content' => $edit_proposal_form,
    'size'    => 'large',
] );

// Expose proposal data as JSON for JS to populate modal
$proposals_json = [];
foreach ( $proposals as $prop ) {
    $proposals_json[ $prop->id ] = [
        'id'                     => $prop->id,
        'proposal_title'         => $prop->proposal_title,
        'client_name'            => $prop->client_name,
        'client_organization'    => $prop->client_organization,
        'client_email'           => $prop->client_email,
        'project_dates'          => $prop->project_dates,
        'project_location'       => $prop->project_location,
        'scope_description'      => $prop->scope_description,
        'goals_objectives'       => $prop->goals_objectives,
        'activities_description' => $prop->activities_description,
        'deliverables_text'      => $prop->deliverables_text,
        'section_situation'      => $prop->section_situation ?? '',
        'section_what_we_build'  => $prop->section_what_we_build ?? '',
        'section_why_els'        => $prop->section_why_els ?? '',
        'section_investment'     => $prop->section_investment ?? '',
        'section_next_steps'     => $prop->section_next_steps ?? '',
        'budget_low'             => $prop->budget_low,
        'budget_high'            => $prop->budget_high,
        'final_price'            => $prop->final_price,
        'payment_terms'          => $prop->payment_terms,
        'terms_conditions'       => $prop->terms_conditions,
        'status'                 => $prop->status,
    ];
}
$html .= '<script>var elProposalsData = ' . wp_json_encode( $proposals_json ) . ';</script>';

// ── Tab: Stakeholders ──
$stakeholder_rows = [];
foreach ( $stakeholders as $sh ) {
    $user = get_userdata( $sh->user_id );
    if ( ! $user ) continue;

    // Check if this user is linked to a contact record (required for Client Dashboard to show projects)
    $has_contact_record = false;
    global $wpdb;
    $contacts_table = $wpdb->prefix . 'el_contacts';
    $ct_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$contacts_table}'" );
    if ( $ct_exists ) {
        $has_contact_record = (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$contacts_table} WHERE user_id = %d",
            $sh->user_id
        ) );
    }

    $role_badge = EL_Admin_UI::badge( [
        'label'   => $sh->role === 'decision_maker' ? __( 'Decision Maker', 'el-core' ) : __( 'Contributor', 'el-core' ),
        'variant' => $sh->role === 'decision_maker' ? 'success' : 'info',
    ] );

    $actions = '';
    $dm_count = count( array_filter( $stakeholders, fn( $s ) => $s->role === 'decision_maker' ) );
    $stakeholder_count = count( $stakeholders );
    
    // Change Role button
    $new_role = $sh->role === 'decision_maker' ? 'contributor' : 'decision_maker';
    $btn_label = $sh->role === 'decision_maker' ? __( 'Make Contributor', 'el-core' ) : __( 'Make Decision Maker', 'el-core' );
    
    // Disable if they're the only DM (need to promote someone else first)
    $is_only_dm = ( $sh->role === 'decision_maker' && $dm_count === 1 );
    
    $actions .= EL_Admin_UI::btn( [
        'label'   => $btn_label,
        'variant' => 'ghost',
        'icon'    => 'update',
        'class'   => 'el-es-change-role-btn' . ( $is_only_dm ? ' disabled' : '' ),
        'data'    => [ 
            'stakeholder-id' => $sh->id, 
            'new-role' => $new_role,
            'disabled-msg' => $is_only_dm ? __( 'Promote another stakeholder to Decision Maker first', 'el-core' ) : '',
        ],
    ] );
    
    // Remove button - always show but disable if they're the last stakeholder or only DM
    $cannot_remove = ( $stakeholder_count === 1 ) || ( $sh->role === 'decision_maker' && $dm_count === 1 );
    $remove_msg = '';
    if ( $stakeholder_count === 1 ) {
        $remove_msg = __( 'Cannot remove the only stakeholder', 'el-core' );
    } elseif ( $sh->role === 'decision_maker' && $dm_count === 1 ) {
        $remove_msg = __( 'Promote another stakeholder to Decision Maker first', 'el-core' );
    }
    
    $actions .= EL_Admin_UI::btn( [
        'label'   => __( 'Remove', 'el-core' ),
        'variant' => 'ghost',
        'icon'    => 'no',
        'class'   => 'el-es-remove-stakeholder-btn' . ( $cannot_remove ? ' disabled' : '' ),
        'data'    => [ 
            'stakeholder-id' => $sh->id,
            'disabled-msg' => $remove_msg,
        ],
    ] );
    
    // Login As button (admin only)
    if ( current_user_can( 'manage_options' ) ) {
        $switch_url = add_query_arg( [
            'action' => 'switch_to_user',
            'user_id' => $user->ID,
            '_wpnonce' => wp_create_nonce( 'switch_to_user_' . $user->ID ),
        ], admin_url( 'admin.php' ) );
        
        $actions .= EL_Admin_UI::btn( [
            'label'   => __( 'Login As', 'el-core' ),
            'variant' => 'ghost',
            'icon'    => 'admin-users',
            'url'     => $switch_url,
        ] );
    }

    $stakeholder_rows[] = [
        'user'   => '<strong>' . esc_html( $user->display_name ) . '</strong><br><small>' . esc_html( $user->user_email ) . '</small>'
            . ( ! $has_contact_record ? '<br><span style="color:#d97706;font-size:0.75rem;">⚠ Not linked to a contact — won\'t see Client Dashboard. Add them as a contact in EL Core → Clients.</span>' : '' ),
        'role'   => $role_badge,
        'added'  => date_i18n( 'M j, Y', strtotime( $sh->added_at ) ),
        '__actions' => $actions,
    ];
}

$stakeholders_content = EL_Admin_UI::data_table( [
    'columns' => [
        [ 'key' => 'user',  'label' => __( 'User', 'el-core' ) ],
        [ 'key' => 'role',  'label' => __( 'Role', 'el-core' ) ],
        [ 'key' => 'added', 'label' => __( 'Added', 'el-core' ) ],
    ],
    'rows'  => $stakeholder_rows,
    'empty' => [
        'icon'    => 'groups',
        'title'   => __( 'No stakeholders yet', 'el-core' ),
        'message' => __( 'Add stakeholders to give clients access to this project.', 'el-core' ),
        'action'  => [ 'label' => __( 'Add Stakeholder', 'el-core' ), 'variant' => 'primary', 'data' => [ 'modal-open' => 'add-stakeholder-modal' ] ],
    ],
] );

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'stakeholders',
    'group'   => 'project-tabs',
    'content' => EL_Admin_UI::card( [
        'title'   => __( 'Project Stakeholders', 'el-core' ),
        'icon'    => 'groups',
        'content' => $stakeholders_content,
        'actions' => [
            [ 'label' => __( 'Add Stakeholder', 'el-core' ), 'variant' => 'secondary', 'icon' => 'plus-alt', 'data' => [ 'modal-open' => 'add-stakeholder-modal' ] ],
        ],
    ] ),
    'active'  => $active_tab === 'stakeholders',
] );

// ── Tab: Stage History ──
$history_rows = [];
foreach ( $stage_history as $entry ) {
    $action_badge = EL_Admin_UI::badge( [
        'label'   => ucfirst( $entry->action ),
        'variant' => match ( $entry->action ) {
            'approved' => 'success',
            'rejected' => 'error',
            default    => 'info',
        },
    ] );

    $actor = $entry->acted_by ? get_userdata( $entry->acted_by ) : null;

    $history_rows[] = [
        'stage'   => EL_Admin_UI::badge( [
            'label'   => $entry->stage . '. ' . $module->get_stage_name( (int) $entry->stage ),
            'variant' => EL_Expand_Site_Module::get_stage_badge_variant( (int) $entry->stage ),
        ] ),
        'action'  => $action_badge,
        'notes'   => esc_html( $entry->notes ?: '—' ),
        'by'      => $actor ? esc_html( $actor->display_name ) : '—',
        'date'    => date_i18n( 'M j, Y g:i A', strtotime( $entry->created_at ) ),
    ];
}

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'stages',
    'group'   => 'project-tabs',
    'content' => EL_Admin_UI::card( [
        'title'   => __( 'Stage History', 'el-core' ),
        'icon'    => 'backup',
        'content' => EL_Admin_UI::data_table( [
            'columns' => [
                [ 'key' => 'stage',  'label' => __( 'Stage', 'el-core' ) ],
                [ 'key' => 'action', 'label' => __( 'Action', 'el-core' ) ],
                [ 'key' => 'notes',  'label' => __( 'Notes', 'el-core' ) ],
                [ 'key' => 'by',     'label' => __( 'By', 'el-core' ) ],
                [ 'key' => 'date',   'label' => __( 'Date', 'el-core' ) ],
            ],
            'rows'  => $history_rows,
            'empty' => [ 'icon' => 'backup', 'title' => __( 'No stage history yet', 'el-core' ) ],
        ] ),
    ] ),
    'active'  => $active_tab === 'stages',
] );

// ── Tab: Deliverables ──
$del_rows = [];
foreach ( $deliverables as $d ) {
    $review_badge = EL_Admin_UI::badge( [
        'label'   => ucfirst( str_replace( '_', ' ', $d->review_status ) ),
        'variant' => match ( $d->review_status ) {
            'approved'       => 'success',
            'needs_revision' => 'warning',
            default          => 'default',
        },
    ] );

    $file_link = $d->file_url
        ? '<a href="' . esc_url( $d->file_url ) . '" target="_blank">' . esc_html( $d->file_type ?: __( 'View', 'el-core' ) ) . '</a>'
        : '—';

    $del_actions  = EL_Admin_UI::btn( [ 'label' => __( 'Approve', 'el-core' ),  'variant' => 'ghost', 'icon' => 'yes',     'class' => 'el-es-review-btn', 'data' => [ 'id' => $d->id, 'status' => 'approved' ] ] );
    $del_actions .= EL_Admin_UI::btn( [ 'label' => __( 'Revise', 'el-core' ),   'variant' => 'ghost', 'icon' => 'edit',     'class' => 'el-es-review-btn', 'data' => [ 'id' => $d->id, 'status' => 'needs_revision' ] ] );

    $del_rows[] = [
        'title'   => '<strong>' . esc_html( $d->title ) . '</strong>'
                   . ( $d->description ? '<br><small>' . esc_html( wp_trim_words( $d->description, 15 ) ) . '</small>' : '' ),
        'stage'   => EL_Admin_UI::badge( [
            'label'   => (int) $d->stage . '. ' . EL_Expand_Site_Module::get_stage_name( (int) $d->stage ),
            'variant' => EL_Expand_Site_Module::get_stage_badge_variant( (int) $d->stage ),
        ] ),
        'file'    => $file_link,
        'status'  => $review_badge,
        '__actions' => $del_actions,
    ];
}

$deliverables_content  = EL_Admin_UI::data_table( [
    'columns' => [
        [ 'key' => 'title',  'label' => __( 'Deliverable', 'el-core' ) ],
        [ 'key' => 'stage',  'label' => __( 'Stage', 'el-core' ) ],
        [ 'key' => 'file',   'label' => __( 'File', 'el-core' ) ],
        [ 'key' => 'status', 'label' => __( 'Review', 'el-core' ) ],
    ],
    'rows'  => $del_rows,
    'empty' => [
        'icon'    => 'media-document',
        'title'   => __( 'No deliverables yet', 'el-core' ),
        'message' => __( 'Add deliverables for the client to review.', 'el-core' ),
        'action'  => [ 'label' => __( 'Add Deliverable', 'el-core' ), 'variant' => 'primary', 'data' => [ 'modal-open' => 'add-deliverable-modal' ] ],
    ],
] );

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'deliverables',
    'group'   => 'project-tabs',
    'content' => EL_Admin_UI::card( [
        'title'   => __( 'Deliverables', 'el-core' ),
        'icon'    => 'media-document',
        'content' => $deliverables_content,
        'actions' => [
            [ 'label' => __( 'Add Deliverable', 'el-core' ), 'variant' => 'secondary', 'icon' => 'plus-alt', 'data' => [ 'modal-open' => 'add-deliverable-modal' ] ],
        ],
    ] ),
    'active'  => $active_tab === 'deliverables',
] );

// ── Tab: Pages ──
$page_rows = [];
foreach ( $pages as $pg ) {
    $pg_status_badge = EL_Admin_UI::badge( [
        'label'   => ucfirst( str_replace( '_', ' ', $pg->status ) ),
        'variant' => match ( $pg->status ) {
            'approved'    => 'success',
            'review'      => 'warning',
            'in_progress' => 'info',
            default       => 'default',
        },
    ] );

    $pg_url = $pg->page_url
        ? '<a href="' . esc_url( $pg->page_url ) . '" target="_blank">' . esc_html( $pg->page_url ) . '</a>'
        : '—';

    $page_rows[] = [
        'name'   => '<strong>' . esc_html( $pg->page_name ) . '</strong>',
        'url'    => $pg_url,
        'status' => $pg_status_badge,
    ];
}

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'pages',
    'group'   => 'project-tabs',
    'content' => EL_Admin_UI::card( [
        'title'   => __( 'Pages', 'el-core' ),
        'icon'    => 'admin-page',
        'content' => EL_Admin_UI::data_table( [
            'columns' => [
                [ 'key' => 'name',   'label' => __( 'Page Name', 'el-core' ) ],
                [ 'key' => 'url',    'label' => __( 'URL', 'el-core' ) ],
                [ 'key' => 'status', 'label' => __( 'Status', 'el-core' ) ],
            ],
            'rows'  => $page_rows,
            'empty' => [
                'icon'    => 'admin-page',
                'title'   => __( 'No pages yet', 'el-core' ),
                'message' => __( 'Add pages being built for this project.', 'el-core' ),
                'action'  => [ 'label' => __( 'Add Page', 'el-core' ), 'variant' => 'primary', 'data' => [ 'modal-open' => 'add-page-modal' ] ],
            ],
        ] ),
        'actions' => [
            [ 'label' => __( 'Add Page', 'el-core' ), 'variant' => 'secondary', 'icon' => 'plus-alt', 'data' => [ 'modal-open' => 'add-page-modal' ] ],
        ],
    ] ),
    'active'  => $active_tab === 'pages',
] );

// ── Tab: Feedback ──
$fb_rows = [];
foreach ( $feedback as $fb ) {
    $fb_user  = get_userdata( $fb->user_id );
    $fb_type  = EL_Admin_UI::badge( [
        'label'   => ucfirst( str_replace( '_', ' ', $fb->feedback_type ) ),
        'variant' => match ( $fb->feedback_type ) {
            'approval'     => 'success',
            'change_order' => 'error',
            'question'     => 'info',
            default        => 'default',
        },
    ] );

    $fb_status_badge = EL_Admin_UI::badge( [
        'label'   => ucfirst( $fb->status ),
        'variant' => match ( $fb->status ) {
            'resolved'     => 'success',
            'acknowledged' => 'info',
            'deferred'     => 'warning',
            default        => 'default',
        },
    ] );

    $co_flag = '';
    if ( $fb->is_change_order ) {
        $co_flag = ' ' . EL_Admin_UI::badge( [ 'label' => '$' . number_format( $fb->change_order_price, 0 ), 'variant' => 'error' ] );
    }

    $fb_actions = '';
    if ( $fb->status === 'pending' ) {
        $fb_actions .= EL_Admin_UI::btn( [ 'label' => __( 'Ack', 'el-core' ),     'variant' => 'ghost', 'class' => 'el-es-feedback-btn', 'data' => [ 'id' => $fb->id, 'status' => 'acknowledged' ] ] );
        $fb_actions .= EL_Admin_UI::btn( [ 'label' => __( 'Resolve', 'el-core' ),  'variant' => 'ghost', 'class' => 'el-es-feedback-btn', 'data' => [ 'id' => $fb->id, 'status' => 'resolved' ] ] );
    }

    $fb_rows[] = [
        'content' => '<div>' . wp_kses_post( wp_trim_words( $fb->content, 25 ) ) . '</div>' . $co_flag,
        'type'    => $fb_type,
        'stage'   => EL_Admin_UI::badge( [
            'label'   => (int) $fb->stage . '. ' . $module->get_stage_name( (int) $fb->stage ),
            'variant' => EL_Expand_Site_Module::get_stage_badge_variant( (int) $fb->stage ),
        ] ),
        'by'      => $fb_user ? esc_html( $fb_user->display_name ) : '—',
        'status'  => $fb_status_badge,
        'date'    => date_i18n( 'M j, Y', strtotime( $fb->created_at ) ),
        '__actions' => $fb_actions,
    ];
}

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'feedback',
    'group'   => 'project-tabs',
    'content' => EL_Admin_UI::card( [
        'title'   => __( 'Client Feedback', 'el-core' ),
        'icon'    => 'format-chat',
        'content' => EL_Admin_UI::data_table( [
            'columns' => [
                [ 'key' => 'content', 'label' => __( 'Feedback', 'el-core' ) ],
                [ 'key' => 'type',    'label' => __( 'Type', 'el-core' ) ],
                [ 'key' => 'stage',   'label' => __( 'Stage', 'el-core' ) ],
                [ 'key' => 'by',      'label' => __( 'From', 'el-core' ) ],
                [ 'key' => 'status',  'label' => __( 'Status', 'el-core' ) ],
                [ 'key' => 'date',    'label' => __( 'Date', 'el-core' ) ],
            ],
            'rows'  => $fb_rows,
            'empty' => [ 'icon' => 'format-chat', 'title' => __( 'No feedback yet', 'el-core' ) ],
        ] ),
    ] ),
    'active'  => $active_tab === 'feedback',
] );

// ═══════════════════════════════════════════
// MODALS
// ═══════════════════════════════════════════

// Advance Stage modal
$next_stage = min( $current_stage + 1, 8 );
$default_deadline_days = $module->get_stage_deadline_days( $next_stage );
$default_deadline = date( 'Y-m-d', strtotime( "+{$default_deadline_days} days" ) );

$advance_form  = '<form id="advance-stage-form">';
$advance_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';
$advance_form .= EL_Admin_UI::notice( [
    'message' => sprintf(
        __( 'This will approve <strong>Stage %d (%s)</strong> and advance to <strong>Stage %d (%s)</strong>.', 'el-core' ),
        $current_stage,
        $module->get_stage_name( $current_stage ),
        $next_stage,
        $module->get_stage_name( $next_stage )
    ),
    'type' => 'info',
] );
$advance_form .= EL_Admin_UI::form_row( [
    'name'        => 'deadline',
    'label'       => __( 'Set Deadline for Next Stage', 'el-core' ),
    'type'        => 'date',
    'value'       => $default_deadline,
    'help'        => sprintf( __( 'Default: %d days from today', 'el-core' ), $default_deadline_days ),
] );
$advance_form .= EL_Admin_UI::form_row( [
    'name'        => 'notes',
    'label'       => __( 'Approval Notes', 'el-core' ),
    'type'        => 'textarea',
    'placeholder' => __( 'Optional notes about this stage approval...', 'el-core' ),
] );
$advance_form .= '<div class="el-form-row">';
$advance_form .= EL_Admin_UI::btn( [ 'label' => __( 'Approve & Advance', 'el-core' ), 'variant' => 'primary', 'icon' => 'yes', 'type' => 'submit' ] );
$advance_form .= '</div>';
$advance_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'advance-stage-modal',
    'title'   => __( 'Advance to Next Stage', 'el-core' ),
    'content' => $advance_form,
] );

// Send Definition for Review modal
$default_review_deadline = date( 'Y-m-d', strtotime( '+7 days' ) );
$send_review_form  = '<form id="send-definition-review-form">';
$send_review_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';
$send_review_form .= EL_Admin_UI::form_row( [
    'name'        => 'deadline',
    'label'       => __( 'Response Deadline', 'el-core' ),
    'type'        => 'date',
    'value'       => $default_review_deadline,
    'helper'      => __( 'Stakeholders must respond by this date. Decision Maker can decide after deadline even if others haven\'t responded.', 'el-core' ),
] );
$send_review_form .= '<div class="el-form-row">';
$send_review_form .= EL_Admin_UI::btn( [ 'label' => __( 'Send for Review', 'el-core' ), 'variant' => 'primary', 'icon' => 'email', 'type' => 'submit' ] );
$send_review_form .= '</div>';
$send_review_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'send-definition-review-modal',
    'title'   => __( 'Send Definition to Client for Review', 'el-core' ),
    'content' => $send_review_form,
] );

// Add Deliverable modal
$del_form  = '<form id="add-deliverable-form">';
$del_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';
$del_form .= '<input type="hidden" name="stage" value="' . esc_attr( $current_stage ) . '">';
$del_form .= EL_Admin_UI::form_row( [ 'name' => 'title', 'label' => __( 'Title', 'el-core' ), 'required' => true, 'placeholder' => __( 'e.g., Homepage Wireframe', 'el-core' ) ] );
$del_form .= EL_Admin_UI::form_row( [ 'name' => 'description', 'label' => __( 'Description', 'el-core' ), 'type' => 'textarea' ] );
$del_form .= EL_Admin_UI::form_row( [ 'name' => 'file_url', 'label' => __( 'File URL', 'el-core' ), 'type' => 'url', 'placeholder' => 'https://' ] );
$del_form .= EL_Admin_UI::form_row( [
    'name'    => 'file_type',
    'label'   => __( 'File Type', 'el-core' ),
    'type'    => 'select',
    'options' => [
        ''         => __( 'Select type...', 'el-core' ),
        'pdf'      => 'PDF',
        'image'    => __( 'Image', 'el-core' ),
        'link'     => __( 'Link', 'el-core' ),
        'document' => __( 'Document', 'el-core' ),
    ],
] );
$del_form .= '<div class="el-form-row">';
$del_form .= EL_Admin_UI::btn( [ 'label' => __( 'Add Deliverable', 'el-core' ), 'variant' => 'primary', 'icon' => 'plus-alt', 'type' => 'submit' ] );
$del_form .= '</div>';
$del_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'add-deliverable-modal',
    'title'   => __( 'Add Deliverable', 'el-core' ),
    'content' => $del_form,
] );

// Add Page modal
$page_form  = '<form id="add-page-form">';
$page_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';
$page_form .= EL_Admin_UI::form_row( [ 'name' => 'page_name', 'label' => __( 'Page Name', 'el-core' ), 'required' => true, 'placeholder' => __( 'e.g., Homepage, About Us', 'el-core' ) ] );
$page_form .= EL_Admin_UI::form_row( [ 'name' => 'page_url', 'label' => __( 'Page URL', 'el-core' ), 'type' => 'url', 'placeholder' => 'https://' ] );
$page_form .= EL_Admin_UI::form_row( [ 'name' => 'sort_order', 'label' => __( 'Sort Order', 'el-core' ), 'type' => 'number', 'value' => '0' ] );
$page_form .= '<div class="el-form-row">';
$page_form .= EL_Admin_UI::btn( [ 'label' => __( 'Add Page', 'el-core' ), 'variant' => 'primary', 'icon' => 'plus-alt', 'type' => 'submit' ] );
$page_form .= '</div>';
$page_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'add-page-modal',
    'title'   => __( 'Add Page', 'el-core' ),
    'content' => $page_form,
] );

// Add Stakeholder modal
// Build org contacts quick-add section
$org_contacts_html = '';
$org_id = absint( $project->organization_id ?? 0 );
if ( $org_id && isset( $core ) && $core->organizations ) {
    $org_contacts = $core->organizations->get_contacts( $org_id );
    $existing_stakeholder_user_ids = array_map( fn( $s ) => (int) $s->user_id, $stakeholders );

    $available_contacts = array_filter( $org_contacts, fn( $c ) =>
        $c->user_id > 0 && ! in_array( (int) $c->user_id, $existing_stakeholder_user_ids, true )
    );

    if ( ! empty( $available_contacts ) ) {
        $org_contacts_html .= '<div style="margin-bottom:18px;">';
        $org_contacts_html .= '<p style="font-weight:600;margin:0 0 8px;font-size:13px;color:#374151;">'
            . __( 'Contacts from this organization:', 'el-core' ) . '</p>';
        $org_contacts_html .= '<div style="display:flex;flex-direction:column;gap:6px;">';
        foreach ( $available_contacts as $oc ) {
            $role_default = $oc->is_primary ? 'decision_maker' : 'contributor';
            $badge = $oc->is_primary
                ? ' <span style="font-size:10px;background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:9px;font-weight:600;">Primary</span>'
                : '';
            $org_contacts_html .= '<div style="display:flex;align-items:center;justify-content:space-between;'
                . 'padding:8px 10px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;">';
            $org_contacts_html .= '<div>';
            $org_contacts_html .= '<strong style="font-size:13px;">' . esc_html( $oc->first_name . ' ' . $oc->last_name ) . '</strong>'
                . $badge;
            if ( $oc->title ) {
                $org_contacts_html .= '<br><span style="font-size:12px;color:#6b7280;">' . esc_html( $oc->title ) . '</span>';
            }
            $org_contacts_html .= '</div>';
            $org_contacts_html .= '<button type="button" class="el-btn el-btn-secondary el-quick-add-stakeholder-btn" '
                . 'style="font-size:12px;padding:4px 10px;" '
                . 'data-user-id="' . esc_attr( $oc->user_id ) . '" '
                . 'data-name="' . esc_attr( $oc->first_name . ' ' . $oc->last_name ) . '" '
                . 'data-role="' . esc_attr( $role_default ) . '" '
                . 'data-project-id="' . esc_attr( $project_id ) . '">'
                . __( 'Add', 'el-core' )
                . '</button>';
            $org_contacts_html .= '</div>';
        }
        $org_contacts_html .= '</div>';
        $org_contacts_html .= '<hr style="margin:14px 0;border:none;border-top:1px solid #e5e7eb;">';
        $org_contacts_html .= '</div>';
    }
}

$stakeholder_form  = '<form id="add-stakeholder-form">';
$stakeholder_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';
if ( $org_contacts_html ) {
    $stakeholder_form .= $org_contacts_html;
}
$stakeholder_form .= EL_Admin_UI::notice( [
    'message' => __( 'Search for an existing WordPress user or enter an email to create a new user account.', 'el-core' ),
    'type'    => 'info',
] );
$stakeholder_form .= '<div class="el-form-row">';
$stakeholder_form .= '<label for="stakeholder-user-search" class="el-form-label">' . __( 'Search User', 'el-core' ) . '</label>';
$stakeholder_form .= '<div class="el-form-field">';
$stakeholder_form .= '<input type="text" id="stakeholder-user-search" name="user_search" class="el-input" placeholder="' . esc_attr__( 'Start typing name or email...', 'el-core' ) . '">';
$stakeholder_form .= '</div>';
$stakeholder_form .= '</div>';
$stakeholder_form .= '<div id="user-search-results" style="display:none; margin-bottom: 15px; padding: 10px; background: #f5f5f5; border-radius: 4px;"></div>';
$stakeholder_form .= '<input type="hidden" name="user_id" id="selected-user-id">';
$stakeholder_form .= EL_Admin_UI::form_row( [
    'name'     => 'new_user_email',
    'label'    => __( 'Or Create New User (Email)', 'el-core' ),
    'type'     => 'email',
    'placeholder' => __( 'email@example.com', 'el-core' ),
] );
$stakeholder_form .= EL_Admin_UI::form_row( [
    'name'     => 'new_user_first_name',
    'label'    => __( 'First Name', 'el-core' ),
    'type'     => 'text',
    'placeholder' => __( 'John', 'el-core' ),
] );
$stakeholder_form .= EL_Admin_UI::form_row( [
    'name'     => 'new_user_last_name',
    'label'    => __( 'Last Name', 'el-core' ),
    'type'     => 'text',
    'placeholder' => __( 'Doe', 'el-core' ),
] );
$stakeholder_form .= EL_Admin_UI::form_row( [
    'name'    => 'role',
    'label'   => __( 'Role', 'el-core' ),
    'type'    => 'select',
    'options' => [
        'contributor'     => __( 'Contributor (can provide input)', 'el-core' ),
        'decision_maker'  => __( 'Decision Maker (can approve/lock)', 'el-core' ),
    ],
] );
$stakeholder_form .= '<div class="el-form-row">';
$stakeholder_form .= EL_Admin_UI::btn( [ 'label' => __( 'Add Stakeholder', 'el-core' ), 'variant' => 'primary', 'icon' => 'plus-alt', 'type' => 'submit' ] );
$stakeholder_form .= '</div>';
$stakeholder_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'add-stakeholder-modal',
    'title'   => __( 'Add Stakeholder', 'el-core' ),
    'content' => $stakeholder_form,
] );

// ── Tab: Branding — Review Management ──
$branding_content = '';
$review_items     = $module->get_review_items( $project_id, 'mood_board' );

$branding_content .= '<div id="es-branding-tab" data-project-id="' . esc_attr( $project_id ) . '">';

if ( empty( $review_items ) ) {
    $branding_content .= EL_Admin_UI::notice( [
        'message' => __( 'No mood board review sessions for this project yet. Create one to let your team vote on style direction.', 'el-core' ),
        'type'    => 'info',
    ] );
} else {
    foreach ( $review_items as $ri ) {
        $dm_dec       = $ri->dm_decision ? json_decode( $ri->dm_decision, true ) : [];
        $sel_ids      = $dm_dec['selected_template_ids'] ?? [];
        $conf_ids     = $dm_dec['confirmed_template_ids'] ?? [];
        $is_closed    = ( $ri->status === 'closed' );
        $badge_class  = $is_closed ? 'el-badge el-badge-success' : 'el-badge el-badge-warning';
        $badge_label  = $is_closed ? __( 'Closed', 'el-core' ) : __( 'Open', 'el-core' );

        $all_votes    = $module->get_review_votes( (int) $ri->id );
        $voted_ids    = array_map( fn( $v ) => (int) $v->user_id, $all_votes );
        $sh_all       = $module->get_stakeholders( $project_id );
        $total_sh     = count( $sh_all );
        $responded    = count( array_filter( $sh_all, fn( $s ) => in_array( (int) $s->user_id, $voted_ids, true ) ) );

        $branding_content .= '<div class="el-card" style="margin-bottom:20px;">';
        $branding_content .= '<div class="el-card__header" style="display:flex;justify-content:space-between;align-items:center;">';
        $branding_content .= '<h3 class="el-card__title">' . esc_html( $ri->title ) . '</h3>';
        $branding_content .= '<span class="' . $badge_class . '">' . $badge_label . '</span>';
        $branding_content .= '</div>';
        $branding_content .= '<div class="el-card__body">';

        // Review info rows
        $branding_content .= EL_Admin_UI::detail_row( [ 'label' => __( 'Templates selected', 'el-core' ), 'value' => count( $sel_ids ), 'icon' => 'images-alt2' ] );
        $branding_content .= EL_Admin_UI::detail_row( [
            'label' => __( 'Responses', 'el-core' ),
            'value' => sprintf( __( '%1$d of %2$d team members', 'el-core' ), $responded, $total_sh ),
            'icon'  => 'groups',
        ] );

        if ( $ri->deadline ) {
            $branding_content .= EL_Admin_UI::detail_row( [
                'label' => __( 'Deadline', 'el-core' ),
                'value' => date_i18n( 'M j, Y', strtotime( $ri->deadline ) ),
                'icon'  => 'calendar-alt',
            ] );
        }

        if ( $is_closed && ! empty( $conf_ids ) ) {
            global $wpdb;
            $tbl  = $wpdb->prefix . 'el_es_templates';
            $ph   = implode( ',', array_fill( 0, count( $conf_ids ), '%d' ) );
            $ctpl = $wpdb->get_results( $wpdb->prepare( "SELECT title FROM {$tbl} WHERE id IN ({$ph})", ...$conf_ids ) );
            $labels = implode( ', ', array_map( fn( $t ) => esc_html( $t->title ), $ctpl ) );
            $branding_content .= EL_Admin_UI::detail_row( [ 'label' => __( 'Confirmed Direction', 'el-core' ), 'value' => $labels, 'icon' => 'yes-alt' ] );
        }

        // Actions
        $branding_content .= '<div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">';

        if ( ! $is_closed ) {
            // Set / extend deadline
            $branding_content .= EL_Admin_UI::btn( [
                'label'   => $ri->deadline ? __( 'Extend Deadline', 'el-core' ) : __( 'Set Deadline', 'el-core' ),
                'variant' => 'secondary',
                'icon'    => 'calendar-alt',
                'data'    => [
                    'action'          => 'set-review-deadline',
                    'review-item-id'  => $ri->id,
                ],
            ] );
        }

        $branding_content .= '</div>';
        $branding_content .= '</div>'; // end card body
        $branding_content .= '</div>'; // end card
    }
}

// "Create Review Session" button
$branding_content .= EL_Admin_UI::btn( [
    'label'   => __( 'Create Mood Board Session', 'el-core' ),
    'variant' => 'primary',
    'icon'    => 'plus-alt',
    'data'    => [ 'modal-open' => 'create-review-modal' ],
] );

$branding_content .= '</div>';

$html .= EL_Admin_UI::tab_panel( [
    'id'      => 'branding',
    'group'   => 'project-tabs',
    'content' => EL_Admin_UI::card( [ 'title' => __( 'Branding Review Management', 'el-core' ), 'icon' => 'art', 'content' => $branding_content ] ),
    'active'  => $active_tab === 'branding',
] );

// ── Modal: Create Review Session ──
$active_templates = $module->get_templates( [ 'is_active' => 1 ] );

$create_review_form  = '<form id="create-review-form">';
$create_review_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';
$create_review_form .= '<input type="hidden" name="review_type" value="mood_board">';
$create_review_form .= EL_Admin_UI::form_row( [
    'name'        => 'title',
    'label'       => __( 'Session Title', 'el-core' ),
    'type'        => 'text',
    'value'       => __( 'Style Direction', 'el-core' ),
    'placeholder' => __( 'e.g. Style Direction', 'el-core' ),
] );
$create_review_form .= EL_Admin_UI::form_row( [
    'name'  => 'deadline',
    'label' => __( 'Response Deadline (optional)', 'el-core' ),
    'type'  => 'date',
] );
$create_review_form .= '<div class="el-form-row">';
$create_review_form .= '<label class="el-form-label">' . __( 'Select Templates for This Client', 'el-core' ) . '</label>';
$create_review_form .= '<div class="el-form-field">';

if ( empty( $active_templates ) ) {
    $create_review_form .= '<p style="color:#666;">' . __( 'No active templates found. Add some in the Template Library first.', 'el-core' ) . '</p>';
} else {
    // Group by category
    $tpl_by_cat = [];
    foreach ( $active_templates as $tpl ) {
        $tpl_by_cat[ $tpl->style_category ][] = $tpl;
    }

    $create_review_form .= '<div class="es-template-picker">';
    foreach ( $tpl_by_cat as $cat => $cat_tpls ) {
        $create_review_form .= '<div class="es-template-picker-category">';
        $create_review_form .= '<div class="es-template-picker-cat-label">' . esc_html( $cat ) . '</div>';
        $create_review_form .= '<div class="es-template-picker-grid">';
        foreach ( $cat_tpls as $tpl ) {
            $img = esc_url( $tpl->image_url );
            $create_review_form .= '<label class="es-template-picker-card">';
            $create_review_form .= '<input type="checkbox" name="template_ids[]" value="' . esc_attr( $tpl->id ) . '">';
            if ( $img ) {
                $create_review_form .= '<img src="' . $img . '" alt="' . esc_attr( $tpl->title ) . '">';
            } else {
                $create_review_form .= '<div class="es-template-picker-no-img">No Image</div>';
            }
            $create_review_form .= '<div class="es-template-picker-title">' . esc_html( $tpl->title ) . '</div>';
            $create_review_form .= '</label>';
        }
        $create_review_form .= '</div>';
        $create_review_form .= '</div>';
    }
    $create_review_form .= '</div>'; // end es-template-picker
}

$create_review_form .= '</div>'; // end el-form-field
$create_review_form .= '</div>'; // end el-form-row

$create_review_form .= '<div class="el-form-row">';
$create_review_form .= EL_Admin_UI::btn( [ 'label' => __( 'Create Review Session', 'el-core' ), 'variant' => 'primary', 'icon' => 'plus-alt', 'type' => 'submit' ] );
$create_review_form .= '</div>';
$create_review_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'create-review-modal',
    'title'   => __( 'Create Mood Board Review Session', 'el-core' ),
    'content' => $create_review_form,
] );

echo EL_Admin_UI::wrap( $html );
