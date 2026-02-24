<?php
/**
 * Expand Site — Template Library Admin Page
 *
 * Manage the style template library used for the Mood Board in the client portal.
 * Templates are displayed as a card grid, grouped by style category.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! el_core_can( 'manage_expand_site' ) ) {
    wp_die( __( 'Permission denied.', 'el-core' ) );
}

global $wpdb;
$table = $wpdb->prefix . 'el_es_templates';

// Filters
$filter_category = sanitize_text_field( $_GET['category'] ?? '' );
$filter_status   = sanitize_text_field( $_GET['status'] ?? '' );

// Fetch templates
$where_clauses = [];
$where_values  = [];

if ( $filter_category ) {
    $where_clauses[] = 'style_category = %s';
    $where_values[]  = $filter_category;
}
if ( $filter_status === 'active' ) {
    $where_clauses[] = 'is_active = 1';
} elseif ( $filter_status === 'inactive' ) {
    $where_clauses[] = 'is_active = 0';
}

$where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
$sql = "SELECT * FROM {$table} {$where_sql} ORDER BY style_category ASC, sort_order ASC, id ASC";

if ( $where_values ) {
    $sql = $wpdb->prepare( $sql, ...$where_values );
}

$templates = $wpdb->get_results( $sql );

// Get distinct categories for filter
$all_categories = $wpdb->get_col( "SELECT DISTINCT style_category FROM {$table} ORDER BY style_category ASC" );

// Define canonical categories
$canonical_categories = [ 'Modern', 'Classic', 'Bold', 'Minimal', 'Playful', 'Professional' ];

// Group templates by category
$grouped = [];
foreach ( $templates as $tpl ) {
    $grouped[ $tpl->style_category ][] = $tpl;
}

$base_url = admin_url( 'admin.php?page=el-core-template-library' );
$total    = count( $templates );
$active   = count( array_filter( $templates, fn( $t ) => $t->is_active ) );

echo EL_Admin_UI::wrap(
    EL_Admin_UI::page_header( [
        'title'    => 'Template Library',
        'subtitle' => 'Manage style templates shown to clients in the Mood Board.',
        'actions'  => [
            [
                'label'   => 'Add Template',
                'variant' => 'primary',
                'icon'    => 'plus',
                'id'      => 'btn-add-template',
            ],
        ],
    ] ) .

    EL_Admin_UI::stats_grid( [
        [ 'icon' => 'images-alt2', 'number' => $total,  'label' => 'Total Templates',  'variant' => 'primary' ],
        [ 'icon' => 'yes-alt',     'number' => $active,  'label' => 'Active Templates', 'variant' => 'success' ],
        [ 'icon' => 'category',   'number' => count( $all_categories ), 'label' => 'Categories', 'variant' => 'info' ],
    ] ) .

    // Filter bar
    EL_Admin_UI::filter_bar( [
        'action'      => $base_url,
        'search_name' => '',
        'placeholder' => '',
        'filters'     => [
            [
                'name'    => 'category',
                'value'   => $filter_category,
                'options' => array_merge(
                    [ '' => 'All Categories' ],
                    array_combine( $all_categories, $all_categories )
                ),
            ],
            [
                'name'    => 'status',
                'value'   => $filter_status,
                'options' => [ '' => 'All Statuses', 'active' => 'Active Only', 'inactive' => 'Inactive Only' ],
            ],
        ],
        'hidden' => [ 'page' => 'el-core-template-library' ],
    ] ) .

    // Template grid, grouped by category
    ( function() use ( $grouped, $canonical_categories, $templates ) {
        if ( empty( $templates ) ) {
            return EL_Admin_UI::empty_state( [
                'icon'    => 'images-alt2',
                'title'   => 'No templates yet',
                'message' => 'Add your first template to start building the mood board library.',
                'action'  => [ 'label' => 'Add Template', 'variant' => 'primary', 'id' => 'btn-add-template-empty' ],
            ] );
        }

        $html = '';

        // Show canonical categories first, then any custom ones
        $sorted_categories = array_unique( array_merge(
            array_intersect( $canonical_categories, array_keys( $grouped ) ),
            array_diff( array_keys( $grouped ), $canonical_categories )
        ) );

        foreach ( $sorted_categories as $category ) {
            $items = $grouped[ $category ] ?? [];
            if ( empty( $items ) ) continue;

            $html .= '<div class="el-tpl-category-group" data-category="' . esc_attr( $category ) . '">';
            $html .= '<div class="el-tpl-category-header">';
            $html .= '<h2 class="el-tpl-category-title">' . esc_html( strtoupper( $category ) ) . '</h2>';
            $html .= '<span class="el-tpl-category-count">' . count( $items ) . ' template' . ( count( $items ) !== 1 ? 's' : '' ) . '</span>';
            $html .= '</div>';

            $html .= '<div class="el-tpl-card-grid">';
            foreach ( $items as $tpl ) {
                $html .= render_template_card( $tpl );
            }
            $html .= '</div>'; // .el-tpl-card-grid
            $html .= '</div>'; // .el-tpl-category-group
        }

        return $html;
    } )() .

    // Add / Edit Modal
    EL_Admin_UI::modal( [
        'id'      => 'modal-template',
        'title'   => 'Add Template',
        'size'    => 'default',
        'content' =>
            '<form id="form-template">' .
            '<input type="hidden" name="template_id" id="tpl-id" value="">' .
            EL_Admin_UI::form_row( [
                'name'        => 'title',
                'id'          => 'tpl-title',
                'label'       => 'Title',
                'placeholder' => 'e.g. Modern Clean',
                'required'    => true,
            ] ) .
            EL_Admin_UI::form_row( [
                'name'     => 'style_category',
                'id'       => 'tpl-category',
                'label'    => 'Style Category',
                'type'     => 'select',
                'required' => true,
                'options'  => [
                    ''             => '— Select Category —',
                    'Modern'       => 'Modern',
                    'Classic'      => 'Classic',
                    'Bold'         => 'Bold',
                    'Minimal'      => 'Minimal',
                    'Playful'      => 'Playful',
                    'Professional' => 'Professional',
                ],
            ] ) .
            EL_Admin_UI::form_row( [
                'name'        => 'description',
                'id'          => 'tpl-description',
                'label'       => 'Description',
                'type'        => 'textarea',
                'placeholder' => '1–2 sentences describing the aesthetic...',
            ] ) .
            EL_Admin_UI::form_row( [
                'name'        => 'image_url',
                'id'          => 'tpl-image-url',
                'label'       => 'Image URL',
                'type'        => 'url',
                'placeholder' => 'https://...',
                'helper'      => 'Paste a URL or use the button below to upload from your Media Library.',
            ] ) .
            '<div class="el-form-row">' .
            '<label class="el-form-label"></label>' .
            '<div class="el-form-field">' .
            '<button type="button" class="el-btn el-btn-secondary" id="btn-tpl-media-upload">' .
            '<span class="dashicons dashicons-upload"></span> Choose from Media Library' .
            '</button>' .
            '<div id="tpl-image-preview" class="el-tpl-upload-preview" style="display:none;">' .
            '<img id="tpl-preview-img" src="" alt="Preview">' .
            '</div>' .
            '</div>' .
            '</div>' .
            EL_Admin_UI::form_row( [
                'name'  => 'is_active',
                'id'    => 'tpl-active',
                'label' => 'Status',
                'type'  => 'checkbox',
                'value' => true,
                'placeholder' => 'Active (visible in client portal mood board)',
            ] ) .
            '<div class="el-modal-footer">' .
            EL_Admin_UI::btn( [ 'label' => 'Cancel', 'variant' => 'secondary', 'id' => 'btn-tpl-cancel' ] ) .
            EL_Admin_UI::btn( [ 'label' => 'Save Template', 'variant' => 'primary', 'type' => 'submit', 'id' => 'btn-tpl-save' ] ) .
            '</div>' .
            '</form>',
    ] ) .

    // Delete confirmation modal
    EL_Admin_UI::modal( [
        'id'      => 'modal-delete-template',
        'title'   => 'Delete Template',
        'content' =>
            '<input type="hidden" id="delete-tpl-id" value="">' .
            '<p>Are you sure you want to delete <strong id="delete-tpl-name"></strong>? This cannot be undone.</p>' .
            '<div class="el-modal-footer">' .
            EL_Admin_UI::btn( [ 'label' => 'Cancel', 'variant' => 'secondary', 'data' => [ 'modal-close' => 'modal-delete-template' ] ] ) .
            EL_Admin_UI::btn( [ 'label' => 'Delete', 'variant' => 'danger', 'id' => 'btn-confirm-delete-tpl' ] ) .
            '</div>',
    ] )
);

/**
 * Render a single template card
 */
function render_template_card( object $tpl ): string {
    $active_badge = $tpl->is_active
        ? '<span class="el-tpl-status-badge el-tpl-status-active">Active</span>'
        : '<span class="el-tpl-status-badge el-tpl-status-inactive">Inactive</span>';

    $image_html = $tpl->image_url
        ? '<div class="el-tpl-card-image"><img src="' . esc_url( $tpl->image_url ) . '" alt="' . esc_attr( $tpl->title ) . '" loading="lazy"></div>'
        : '<div class="el-tpl-card-image el-tpl-card-no-image"><span class="dashicons dashicons-format-image"></span><span>No image</span></div>';

    $desc_html = $tpl->description
        ? '<p class="el-tpl-card-desc">' . esc_html( $tpl->description ) . '</p>'
        : '';

    return '<div class="el-tpl-card" data-id="' . esc_attr( $tpl->id ) . '" data-sort="' . esc_attr( $tpl->sort_order ) . '">' .
        $image_html .
        '<div class="el-tpl-card-body">' .
        '<div class="el-tpl-card-header-row">' .
        '<h3 class="el-tpl-card-title">' . esc_html( $tpl->title ) . '</h3>' .
        $active_badge .
        '</div>' .
        $desc_html .
        '<div class="el-tpl-card-actions">' .
        '<button type="button" class="el-btn el-btn-secondary el-btn-sm btn-edit-template"
            data-id="' . esc_attr( $tpl->id ) . '"
            data-title="' . esc_attr( $tpl->title ) . '"
            data-category="' . esc_attr( $tpl->style_category ) . '"
            data-description="' . esc_attr( $tpl->description ) . '"
            data-image-url="' . esc_attr( $tpl->image_url ) . '"
            data-active="' . esc_attr( $tpl->is_active ) . '"
            data-sort="' . esc_attr( $tpl->sort_order ) . '">' .
        '<span class="dashicons dashicons-edit"></span> Edit' .
        '</button>' .
        '<button type="button" class="el-btn el-btn-danger el-btn-sm btn-delete-template"
            data-id="' . esc_attr( $tpl->id ) . '"
            data-title="' . esc_attr( $tpl->title ) . '">' .
        '<span class="dashicons dashicons-trash"></span> Delete' .
        '</button>' .
        '</div>' .
        '</div>' . // .el-tpl-card-body
        '</div>'; // .el-tpl-card
}
