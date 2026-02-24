<?php
/**
 * Admin View: Client List (Organizations)
 *
 * Card grid showing all client organizations with contact/project counts.
 * Uses EL_Admin_UI components for all rendering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core = EL_Core::instance();
$orgs = $core->organizations->get_all_organizations();

$html = '';

// Page header
$html .= EL_Admin_UI::page_header( [
    'title'    => __( 'Clients', 'el-core' ),
    'subtitle' => sprintf( _n( '%d organization', '%d organizations', count( $orgs ), 'el-core' ), count( $orgs ) ),
    'actions'  => [
        [
            'label'   => __( 'Add Client', 'el-core' ),
            'variant' => 'primary',
            'icon'    => 'plus-alt',
            'data'    => [ 'modal-open' => 'add-org-modal' ],
        ],
    ],
] );

// Client cards
if ( empty( $orgs ) ) {
    $html .= EL_Admin_UI::empty_state( [
        'icon'    => 'building',
        'title'   => __( 'No Clients Yet', 'el-core' ),
        'message' => __( 'Get started by adding your first client organization.', 'el-core' ),
        'action'  => [
            'label'   => __( 'Add Your First Client', 'el-core' ),
            'variant' => 'primary',
            'data'    => [ 'modal-open' => 'add-org-modal' ],
        ],
    ] );
} else {
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

    $cards = [];
    foreach ( $orgs as $org ) {
        $badges = [
            [
                'label'   => ucfirst( str_replace( '_', ' ', $org->type ) ),
                'variant' => $type_variants[ $org->type ] ?? 'default',
            ],
            [
                'label'   => ucfirst( $org->status ),
                'variant' => $status_variants[ $org->status ] ?? 'default',
            ],
        ];

        $meta = [];
        if ( ! empty( $org->address ) ) {
            $meta[] = [ 'icon' => 'location', 'text' => wp_trim_words( $org->address, 8 ) ];
        }

        $footer = [
            [ 'icon' => 'groups', 'text' => $org->contact_count . ' Contact' . ( $org->contact_count != 1 ? 's' : '' ) ],
            [ 'icon' => 'portfolio', 'text' => $org->project_count . ' Project' . ( $org->project_count != 1 ? 's' : '' ) ],
        ];

        $cards[] = [
            'title'   => $org->name,
            'url'     => admin_url( 'admin.php?page=el-core-clients&client_id=' . $org->id ),
            'badges'  => $badges,
            'meta'    => $meta,
            'footer'  => $footer,
        ];
    }

    $html .= EL_Admin_UI::record_grid( $cards );
}

// Add Organization Modal
$modal_form  = '<form id="add-org-form">';
$modal_form .= EL_Admin_UI::form_row( [
    'name'        => 'name',
    'label'       => __( 'Organization Name', 'el-core' ),
    'required'    => true,
    'placeholder' => __( 'e.g., Youth Development Alliance', 'el-core' ),
] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'    => 'type',
    'label'   => __( 'Type', 'el-core' ),
    'type'    => 'select',
    'value'   => 'nonprofit',
    'options' => [
        'nonprofit'  => __( 'Nonprofit', 'el-core' ),
        'for_profit' => __( 'For Profit', 'el-core' ),
        'government' => __( 'Government', 'el-core' ),
        'education'  => __( 'Education', 'el-core' ),
    ],
] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'    => 'status',
    'label'   => __( 'Status', 'el-core' ),
    'type'    => 'select',
    'value'   => 'prospect',
    'options' => [
        'prospect' => __( 'Prospect', 'el-core' ),
        'active'   => __( 'Active', 'el-core' ),
        'inactive' => __( 'Inactive', 'el-core' ),
    ],
] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'  => 'address',
    'label' => __( 'Address', 'el-core' ),
    'type'  => 'textarea',
] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'        => 'phone',
    'label'       => __( 'Phone', 'el-core' ),
    'placeholder' => __( '(555) 123-4567', 'el-core' ),
] );
$modal_form .= EL_Admin_UI::form_row( [
    'name'        => 'website',
    'label'       => __( 'Website', 'el-core' ),
    'type'        => 'url',
    'placeholder' => __( 'https://example.org', 'el-core' ),
] );
$modal_form .= '<div class="el-form-row">';
$modal_form .= EL_Admin_UI::btn( [
    'label'   => __( 'Add Client', 'el-core' ),
    'variant' => 'primary',
    'icon'    => 'plus-alt',
    'type'    => 'submit',
] );
$modal_form .= '</div>';
$modal_form .= '</form>';

$html .= EL_Admin_UI::modal( [
    'id'      => 'add-org-modal',
    'title'   => __( 'Add New Client', 'el-core' ),
    'content' => $modal_form,
] );

echo EL_Admin_UI::wrap( $html );
