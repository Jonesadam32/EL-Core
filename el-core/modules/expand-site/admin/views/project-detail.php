<?php
/**
 * Admin View: Expand Site — Project Detail
 *
 * Single project view with tabs: Overview, Stages, Deliverables, Pages, Feedback.
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

$stage_history  = $module->get_stage_history( $project_id );
$deliverables   = $module->get_deliverables( $project_id );
$feedback       = $module->get_feedback( $project_id );
$pages          = $module->get_pages( $project_id );
$change_orders  = $module->get_change_orders( $project_id );
$current_stage  = (int) $project->current_stage;

$pending_feedback = count( array_filter( $feedback, fn( $f ) => $f->status === 'pending' ) );

$html = '';

// Page header
$html .= EL_Admin_UI::page_header( [
    'title'      => esc_html( $project->name ),
    'subtitle'   => esc_html( $project->client_name ),
    'back_url'   => admin_url( 'admin.php?page=el-expand-site' ),
    'back_label' => __( '← All Projects', 'el-core' ),
    'actions'    => [
        [
            'label'   => __( 'Edit Project', 'el-core' ),
            'variant' => 'secondary',
            'icon'    => 'edit',
            'url'     => admin_url( 'admin.php?page=el-expand-site&project=' . $project_id . '&action=edit' ),
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
        'label'   => EL_Expand_Site_Module::get_stage_name( $current_stage ),
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

// ── Stage Progress Bar ──
$progress_html = '<div class="el-es-stage-progress">';
foreach ( EL_Expand_Site_Module::STAGES as $num => $stage ) {
    $state = 'upcoming';
    if ( $num < $current_stage ) $state = 'completed';
    if ( $num === $current_stage ) $state = 'current';

    $progress_html .= '<div class="el-es-stage-step el-es-stage-' . esc_attr( $state ) . '">';
    $progress_html .= '<div class="el-es-stage-number">' . $num . '</div>';
    $progress_html .= '<div class="el-es-stage-label">' . esc_html( $stage['name'] ) . '</div>';
    $progress_html .= '</div>';
}
$progress_html .= '</div>';

$html .= EL_Admin_UI::card( [
    'title'   => __( 'Pipeline Progress', 'el-core' ),
    'icon'    => 'editor-ol',
    'content' => $progress_html,
] );

// ── Tabs ──
$html .= EL_Admin_UI::tab_nav( [
    'group' => 'project-tabs',
    'tabs'  => [
        [ 'id' => 'overview',     'label' => __( 'Overview', 'el-core' ),      'icon' => 'dashboard',      'active' => true ],
        [ 'id' => 'stages',       'label' => __( 'Stage History', 'el-core' ), 'icon' => 'backup' ],
        [ 'id' => 'deliverables', 'label' => __( 'Deliverables', 'el-core' ),  'icon' => 'media-document',  'badge' => count( $deliverables ) ],
        [ 'id' => 'pages',        'label' => __( 'Pages', 'el-core' ),         'icon' => 'admin-page',      'badge' => count( $pages ) ],
        [ 'id' => 'feedback',     'label' => __( 'Feedback', 'el-core' ),      'icon' => 'format-chat',     'badge' => $pending_feedback ?: null ],
    ],
] );

// ── Tab: Overview ──
$overview = '';

$overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Client', 'el-core' ),        'value' => esc_html( $project->client_name ), 'icon' => 'businessperson' ] );
$overview .= EL_Admin_UI::detail_row( [ 'label' => __( 'Current Stage', 'el-core' ),  'value' => $current_stage . '. ' . EL_Expand_Site_Module::get_stage_name( $current_stage ), 'icon' => 'flag' ] );
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
    'active'  => true,
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
            'label'   => $entry->stage . '. ' . EL_Expand_Site_Module::get_stage_name( (int) $entry->stage ),
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
            'label'   => (int) $fb->stage . '. ' . EL_Expand_Site_Module::get_stage_name( (int) $fb->stage ),
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
] );

// ═══════════════════════════════════════════
// MODALS
// ═══════════════════════════════════════════

// Advance Stage modal
$advance_form  = '<form id="advance-stage-form">';
$advance_form .= '<input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">';
$advance_form .= EL_Admin_UI::notice( [
    'message' => sprintf(
        __( 'This will approve <strong>Stage %d (%s)</strong> and advance to <strong>Stage %d (%s)</strong>.', 'el-core' ),
        $current_stage,
        EL_Expand_Site_Module::get_stage_name( $current_stage ),
        min( $current_stage + 1, 8 ),
        EL_Expand_Site_Module::get_stage_name( min( $current_stage + 1, 8 ) )
    ),
    'type' => 'info',
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

echo EL_Admin_UI::wrap( $html );
