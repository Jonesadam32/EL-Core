<?php
/**
 * Admin View: Client Profile (Organization Detail)
 *
 * Shows org details, contacts, and linked projects.
 * Uses EL_Admin_UI components for all rendering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core      = EL_Core::instance();
$client_id = absint( $_GET['client_id'] ?? 0 );
$client    = $core->organizations->get_organization( $client_id );

if ( ! $client ) {
    echo EL_Admin_UI::wrap( EL_Admin_UI::notice( [
        'message' => __( 'Client not found.', 'el-core' ),
        'type'    => 'error',
    ] ) );
    return;
}

$contacts = $core->organizations->get_contacts( $client_id );
$projects = $core->organizations->get_projects_for_org( $client_id );

$type_variants = [
    'nonprofit'  => 'info',
    'for_profit' => 'primary',
    'government' => 'warning',
    'education'  => 'success',
];
$status_variants = [
    'prospect' => 'default',
    'active'   => 'success',
    'inactive' => 'error',
];

$html = '';

// Page header
$html .= EL_Admin_UI::page_header( [
    'title'      => $client->name,
    'back_url'   => admin_url( 'admin.php?page=el-core-clients' ),
    'back_label' => __( '← Back to Clients', 'el-core' ),
    'actions'    => [
        [
            'label'   => __( 'Edit Client', 'el-core' ),
            'variant' => 'secondary',
            'icon'    => 'edit',
            'data'    => [ 'modal-open' => 'edit-org-modal' ],
        ],
        [
            'label'   => __( 'Delete', 'el-core' ),
            'variant' => 'danger',
            'icon'    => 'trash',
            'id'      => 'delete-org-btn',
            'data'    => [ 'org-id' => $client->id, 'org-name' => $client->name ],
        ],
    ],
] );

// Badges row
$html .= '<div style="display:flex;gap:8px;margin-bottom:20px;">';
$html .= EL_Admin_UI::badge( [
    'label'   => ucfirst( str_replace( '_', ' ', $client->type ) ),
    'variant' => $type_variants[ $client->type ] ?? 'default',
] );
$html .= EL_Admin_UI::badge( [
    'label'   => ucfirst( $client->status ),
    'variant' => $status_variants[ $client->status ] ?? 'default',
] );
$html .= '</div>';

// Client Details card
$details_html = '';
if ( $client->address ) {
    $details_html .= EL_Admin_UI::detail_row( [
        'label' => __( 'Address', 'el-core' ),
        'value' => nl2br( esc_html( $client->address ) ),
        'icon'  => 'location',
    ] );
}
if ( $client->phone ) {
    $details_html .= EL_Admin_UI::detail_row( [
        'label' => __( 'Phone', 'el-core' ),
        'value' => esc_html( $client->phone ),
        'icon'  => 'phone',
    ] );
}
if ( $client->website ) {
    $details_html .= EL_Admin_UI::detail_row( [
        'label' => __( 'Website', 'el-core' ),
        'value' => '<a href="' . esc_url( $client->website ) . '" target="_blank">' . esc_html( $client->website ) . '</a>',
        'icon'  => 'admin-site',
    ] );
}
$details_html .= EL_Admin_UI::detail_row( [
    'label' => __( 'Client Since', 'el-core' ),
    'value' => date_i18n( 'F j, Y', strtotime( $client->created_at ) ),
    'icon'  => 'calendar',
] );

$html .= EL_Admin_UI::card( [
    'title'   => __( 'Client Details', 'el-core' ),
    'icon'    => 'building',
    'content' => $details_html,
] );

// Contacts card
$contacts_html = '';
if ( empty( $contacts ) ) {
    $contacts_html = '<p style="color:#6b7280;font-style:italic;">'
                   . __( 'No contacts yet. Add the first contact to get started.', 'el-core' )
                   . '</p>';
} else {
    $contact_rows = [];
    foreach ( $contacts as $c ) {
        $name_html = '<strong>' . esc_html( $c->first_name . ' ' . $c->last_name ) . '</strong>';
        if ( $c->is_primary ) {
            $name_html .= ' ' . EL_Admin_UI::badge( [ 'label' => 'Primary', 'variant' => 'primary' ] );
        }
        if ( $c->title ) {
            $name_html .= '<br><small style="color:#6b7280;">' . esc_html( $c->title ) . '</small>';
        }

        $contact_info = '<a href="mailto:' . esc_attr( $c->email ) . '">' . esc_html( $c->email ) . '</a>';
        if ( $c->phone ) {
            $contact_info .= '<br>' . esc_html( $c->phone );
        }

        $portal = $c->user_id ? EL_Admin_UI::badge( [ 'label' => 'Portal Access', 'variant' => 'success' ] ) : '—';

        $actions  = EL_Admin_UI::btn( [
            'label'   => __( 'Edit', 'el-core' ),
            'variant' => 'ghost',
            'icon'    => 'edit',
            'class'   => 'el-edit-contact-btn',
            'data'    => [ 'contact-id' => $c->id ],
        ] );
        $actions .= EL_Admin_UI::btn( [
            'label'   => __( 'Delete', 'el-core' ),
            'variant' => 'ghost',
            'icon'    => 'trash',
            'class'   => 'el-delete-contact-btn',
            'data'    => [ 'contact-id' => $c->id, 'contact-name' => $c->first_name . ' ' . $c->last_name ],
        ] );

        $contact_rows[] = [
            'name'       => $name_html,
            'contact'    => $contact_info,
            'portal'     => $portal,
            '__actions'  => $actions,
        ];
    }

    $contacts_html = EL_Admin_UI::data_table( [
        'columns' => [
            [ 'key' => 'name',    'label' => __( 'Name / Title', 'el-core' ) ],
            [ 'key' => 'contact', 'label' => __( 'Contact', 'el-core' ) ],
            [ 'key' => 'portal',  'label' => __( 'Portal', 'el-core' ) ],
        ],
        'rows' => $contact_rows,
    ] );
}

$html .= EL_Admin_UI::card( [
    'title'   => sprintf( __( 'Contacts (%d)', 'el-core' ), count( $contacts ) ),
    'icon'    => 'groups',
    'actions' => [
        [
            'label'   => __( 'Add Contact', 'el-core' ),
            'variant' => 'primary',
            'icon'    => 'plus-alt',
            'data'    => [ 'modal-open' => 'add-contact-modal' ],
        ],
    ],
    'content' => $contacts_html,
] );

// Projects card
$projects_html = '';
if ( empty( $projects ) ) {
    $projects_html = '<p style="color:#6b7280;font-style:italic;">'
                   . __( 'No projects yet. Projects will appear here once created.', 'el-core' )
                   . '</p>';
} else {
    $stage_names = EL_Expand_Site_Module::STAGES;

    $project_rows = [];
    foreach ( $projects as $p ) {
        $stage_num = (int) ( $p->current_stage ?? 1 );
        $stage_label = $stage_num . '. ' . ( $stage_names[ $stage_num ]['name'] ?? 'Unknown' );

        $project_rows[] = [
            'name'   => '<a href="' . esc_url( admin_url( 'admin.php?page=el-core-projects&project=' . $p->id ) ) . '">'
                       . '<strong>' . esc_html( $p->name ) . '</strong></a>',
            'stage'  => EL_Admin_UI::badge( [
                'label'   => $stage_label,
                'variant' => EL_Expand_Site_Module::get_stage_badge_variant( $stage_num ),
            ] ),
            'status' => EL_Admin_UI::badge( [
                'label'   => ucfirst( $p->status ?? 'active' ),
                'variant' => EL_Expand_Site_Module::get_status_badge_variant( $p->status ?? 'active' ),
            ] ),
            'date'   => date_i18n( 'M j, Y', strtotime( $p->created_at ) ),
        ];
    }

    $projects_html = EL_Admin_UI::data_table( [
        'columns' => [
            [ 'key' => 'name',   'label' => __( 'Project', 'el-core' ) ],
            [ 'key' => 'stage',  'label' => __( 'Stage', 'el-core' ) ],
            [ 'key' => 'status', 'label' => __( 'Status', 'el-core' ) ],
            [ 'key' => 'date',   'label' => __( 'Created', 'el-core' ) ],
        ],
        'rows' => $project_rows,
    ] );
}

$html .= EL_Admin_UI::card( [
    'title'   => sprintf( __( 'Projects (%d)', 'el-core' ), count( $projects ) ),
    'icon'    => 'portfolio',
    'content' => $projects_html,
] );

// ═══════════════════════════════════════════
// MODALS
// ═══════════════════════════════════════════

// Edit Organization Modal
$edit_form  = '<form id="edit-org-form">';
$edit_form .= '<input type="hidden" name="organization_id" value="' . esc_attr( $client->id ) . '">';
$edit_form .= EL_Admin_UI::form_row( [
    'name'     => 'name',
    'label'    => __( 'Organization Name', 'el-core' ),
    'required' => true,
    'value'    => $client->name,
] );
$edit_form .= EL_Admin_UI::form_row( [
    'name'    => 'type',
    'label'   => __( 'Type', 'el-core' ),
    'type'    => 'select',
    'value'   => $client->type,
    'options' => [
        'nonprofit'  => __( 'Nonprofit', 'el-core' ),
        'for_profit' => __( 'For Profit', 'el-core' ),
        'government' => __( 'Government', 'el-core' ),
        'education'  => __( 'Education', 'el-core' ),
    ],
] );
$edit_form .= EL_Admin_UI::form_row( [
    'name'    => 'status',
    'label'   => __( 'Status', 'el-core' ),
    'type'    => 'select',
    'value'   => $client->status,
    'options' => [
        'prospect' => __( 'Prospect', 'el-core' ),
        'active'   => __( 'Active', 'el-core' ),
        'inactive' => __( 'Inactive', 'el-core' ),
    ],
] );
$edit_form .= EL_Admin_UI::form_row( [
    'name'  => 'address',
    'label' => __( 'Address', 'el-core' ),
    'type'  => 'textarea',
    'value' => $client->address ?? '',
] );
$edit_form .= EL_Admin_UI::form_row( [
    'name'  => 'phone',
    'label' => __( 'Phone', 'el-core' ),
    'value' => $client->phone ?? '',
] );
$edit_form .= EL_Admin_UI::form_row( [
    'name'  => 'website',
    'label' => __( 'Website', 'el-core' ),
    'type'  => 'url',
    'value' => $client->website ?? '',
] );
$edit_form .= '<div class="el-form-row">';
$edit_form .= EL_Admin_UI::btn( [
    'label'   => __( 'Update Client', 'el-core' ),
    'variant' => 'primary',
    'type'    => 'submit',
] );
$edit_form .= '</div>';
$edit_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'edit-org-modal',
    'title'   => __( 'Edit Client', 'el-core' ),
    'content' => $edit_form,
] );

// Add Contact Modal
$contact_form  = '<form id="add-contact-form">';
$contact_form .= '<input type="hidden" name="organization_id" value="' . esc_attr( $client->id ) . '">';
$contact_form .= EL_Admin_UI::form_row( [
    'name'        => 'first_name',
    'label'       => __( 'First Name', 'el-core' ),
    'required'    => true,
    'placeholder' => __( 'Jane', 'el-core' ),
] );
$contact_form .= EL_Admin_UI::form_row( [
    'name'        => 'last_name',
    'label'       => __( 'Last Name', 'el-core' ),
    'required'    => true,
    'placeholder' => __( 'Smith', 'el-core' ),
] );
$contact_form .= EL_Admin_UI::form_row( [
    'name'        => 'email',
    'label'       => __( 'Email', 'el-core' ),
    'type'        => 'email',
    'required'    => true,
    'placeholder' => __( 'jane@example.org', 'el-core' ),
] );
$contact_form .= EL_Admin_UI::form_row( [
    'name'        => 'phone',
    'label'       => __( 'Phone', 'el-core' ),
    'placeholder' => __( '(555) 123-4567', 'el-core' ),
] );
$contact_form .= EL_Admin_UI::form_row( [
    'name'        => 'title',
    'label'       => __( 'Title / Role', 'el-core' ),
    'placeholder' => __( 'e.g., Executive Director', 'el-core' ),
    'helper'      => __( 'Their role at the organization.', 'el-core' ),
] );
$contact_form .= EL_Admin_UI::form_row( [
    'name'        => 'is_primary',
    'label'       => __( 'Primary Contact', 'el-core' ),
    'type'        => 'checkbox',
    'placeholder' => __( 'This is the primary contact for the organization', 'el-core' ),
    'helper'      => __( 'Primary contacts automatically receive portal access (a WordPress account will be created).', 'el-core' ),
] );
$contact_form .= EL_Admin_UI::form_row( [
    'name'        => 'create_wp_user',
    'label'       => __( 'Portal Access', 'el-core' ),
    'type'        => 'checkbox',
    'placeholder' => __( 'Create a WordPress account (if not already primary)', 'el-core' ),
    'helper'      => __( 'Check this to grant portal access to a non-primary contact.', 'el-core' ),
] );
$contact_form .= '<div class="el-form-row">';
$contact_form .= EL_Admin_UI::btn( [
    'label'   => __( 'Add Contact', 'el-core' ),
    'variant' => 'primary',
    'icon'    => 'plus-alt',
    'type'    => 'submit',
] );
$contact_form .= '</div>';
$contact_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'add-contact-modal',
    'title'   => __( 'Add Contact', 'el-core' ),
    'content' => $contact_form,
] );

// Edit Contact Modal (populated via JS)
$edit_contact_form  = '<form id="edit-contact-form">';
$edit_contact_form .= '<input type="hidden" name="contact_id" id="edit-contact-id" value="">';
$edit_contact_form .= '<input type="hidden" id="edit-contact-user-id" value="">';
$edit_contact_form .= EL_Admin_UI::form_row( [
    'name'     => 'first_name',
    'label'    => __( 'First Name', 'el-core' ),
    'required' => true,
    'id'       => 'edit-contact-first-name',
] );
$edit_contact_form .= EL_Admin_UI::form_row( [
    'name'     => 'last_name',
    'label'    => __( 'Last Name', 'el-core' ),
    'required' => true,
    'id'       => 'edit-contact-last-name',
] );
$edit_contact_form .= EL_Admin_UI::form_row( [
    'name'     => 'email',
    'label'    => __( 'Email', 'el-core' ),
    'type'     => 'email',
    'required' => true,
    'id'       => 'edit-contact-email',
] );
$edit_contact_form .= EL_Admin_UI::form_row( [
    'name'  => 'phone',
    'label' => __( 'Phone', 'el-core' ),
    'id'    => 'edit-contact-phone',
] );
$edit_contact_form .= EL_Admin_UI::form_row( [
    'name'  => 'title',
    'label' => __( 'Title / Role', 'el-core' ),
    'id'    => 'edit-contact-title',
] );
$edit_contact_form .= EL_Admin_UI::form_row( [
    'name'        => 'is_primary',
    'label'       => __( 'Primary Contact', 'el-core' ),
    'type'        => 'checkbox',
    'placeholder' => __( 'This is the primary contact for the organization', 'el-core' ),
    'id'          => 'edit-contact-is-primary',
] );
// Portal access row — shown/hidden by JS based on whether user_id is set
$edit_contact_form .= '<div id="edit-contact-portal-row" class="el-form-row" style="display:none;">';
$edit_contact_form .= '<label class="el-form-label">' . __( 'Portal Access', 'el-core' ) . '</label>';
$edit_contact_form .= '<div class="el-form-field">';
// "Already has access" notice — shown when user_id > 0
$edit_contact_form .= '<div id="edit-contact-has-portal" style="display:none;">'
    . '<span style="display:inline-flex;align-items:center;gap:6px;color:#16a34a;font-weight:500;">'
    . '<span class="dashicons dashicons-yes-alt"></span>'
    . __( 'This contact already has portal access (WordPress account exists).', 'el-core' )
    . '</span>'
    . '</div>';
// "Grant access" checkbox — shown when user_id = 0
$edit_contact_form .= '<div id="edit-contact-grant-portal" style="display:none;">'
    . '<label class="el-checkbox-label">'
    . '<input type="checkbox" name="grant_portal_access" id="edit-contact-grant-portal-cb" class="el-checkbox" value="1"> '
    . __( 'Create a WordPress account for portal access', 'el-core' )
    . '</label>'
    . '</div>';
$edit_contact_form .= '</div>';
$edit_contact_form .= '</div>';
$edit_contact_form .= '<div class="el-form-row">';
$edit_contact_form .= EL_Admin_UI::btn( [
    'label'   => __( 'Update Contact', 'el-core' ),
    'variant' => 'primary',
    'type'    => 'submit',
] );
$edit_contact_form .= '</div>';
$edit_contact_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'edit-contact-modal',
    'title'   => __( 'Edit Contact', 'el-core' ),
    'content' => $edit_contact_form,
] );

echo EL_Admin_UI::wrap( $html );
