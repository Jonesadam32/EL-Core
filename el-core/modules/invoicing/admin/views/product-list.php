<?php
/**
 * Invoicing — Product Management Admin Page
 *
 * Card grid of products; Add/Edit modal; Delete confirm; Seed default products.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_invoices' ) ) {
    wp_die( __( 'Permission denied.', 'el-core' ) );
}

global $wpdb;
$core   = el_core();
$db     = $core->database;
$table  = $db->get_table_name( 'el_inv_products' );

$filter_category = sanitize_text_field( $_GET['category'] ?? '' );
$filter_status   = sanitize_text_field( $_GET['status'] ?? '' );

$where_clauses = [];
$where_values  = [];
if ( $filter_category ) {
    $where_clauses[] = 'category = %s';
    $where_values[]  = $filter_category;
}
if ( $filter_status === 'active' ) {
    $where_clauses[] = "status = 'active'";
} elseif ( $filter_status === 'inactive' ) {
    $where_clauses[] = "status = 'inactive'";
}

$where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
$sql       = "SELECT * FROM {$table} {$where_sql} ORDER BY name ASC";
if ( $where_values ) {
    $sql = $wpdb->prepare( $sql, ...$where_values );
}
$products = $wpdb->get_results( $sql );

$all_categories = [ 'service', 'subscription', 'contract' ];
$total          = count( $products );
$active_count   = count( array_filter( $products, fn( $p ) => $p->status === 'active' ) );
$base_url       = admin_url( 'admin.php?page=el-core-inv-products' );

echo EL_Admin_UI::wrap(
    EL_Admin_UI::page_header( [
        'title'    => __( 'Products', 'el-core' ),
        'subtitle' => __( 'Products and services used as line items on invoices.', 'el-core' ),
        'actions'  => [
            [
                'label'   => __( 'Add Product', 'el-core' ),
                'variant' => 'primary',
                'icon'    => 'plus',
                'id'      => 'el-inv-btn-add-product',
                'data'    => [ 'modal-open' => 'el-inv-modal-product' ],
            ],
            [
                'label'   => __( 'Seed Default Products', 'el-core' ),
                'variant' => 'secondary',
                'id'      => 'el-inv-btn-seed-products',
            ],
        ],
    ] ) .

    EL_Admin_UI::stats_grid( [
        [ 'icon' => 'cart',    'number' => $total,        'label' => __( 'Total Products', 'el-core' ),  'variant' => 'primary' ],
        [ 'icon' => 'yes-alt', 'number' => $active_count, 'label' => __( 'Active', 'el-core' ),          'variant' => 'success' ],
        [ 'icon' => 'category', 'number' => count( $all_categories ), 'label' => __( 'Categories', 'el-core' ), 'variant' => 'info' ],
    ] ) .

    EL_Admin_UI::filter_bar( [
        'action'      => $base_url,
        'search_name' => '',
        'filters'     => [
            [
                'name'    => 'category',
                'value'   => $filter_category,
                'options' => array_merge(
                    [ '' => __( 'All Categories', 'el-core' ) ],
                    array_combine( $all_categories, array_map( 'ucfirst', $all_categories ) )
                ),
            ],
            [
                'name'    => 'status',
                'value'   => $filter_status,
                'options' => [
                    ''         => __( 'All Statuses', 'el-core' ),
                    'active'   => __( 'Active Only', 'el-core' ),
                    'inactive' => __( 'Inactive Only', 'el-core' ),
                ],
            ],
        ],
        'hidden' => [ 'page' => 'el-core-inv-products' ],
    ] ) .

    ( function() use ( $products, $all_categories ) {
        if ( empty( $products ) ) {
            return EL_Admin_UI::empty_state( [
                'icon'    => 'cart',
                'title'   => __( 'No products yet', 'el-core' ),
                'message' => __( 'Add your first product or seed the default ELS products.', 'el-core' ),
                'action'  => [
                    'label'   => __( 'Add Product', 'el-core' ),
                    'variant' => 'primary',
                    'id'      => 'el-inv-btn-add-product-empty',
                    'data'    => [ 'modal-open' => 'el-inv-modal-product' ],
                ],
            ] );
        }
        $html = '<div class="el-inv-product-grid">';
        foreach ( $products as $p ) {
            $html .= el_inv_render_product_card( $p );
        }
        $html .= '</div>';
        return $html;
    } )() .

    EL_Admin_UI::modal( [
        'id'      => 'el-inv-modal-product',
        'title'   => __( 'Add Product', 'el-core' ),
        'size'    => 'default',
        'content' =>
            '<form id="el-inv-form-product">' .
            '<input type="hidden" name="product_id" id="el-inv-product-id" value="">' .
            EL_Admin_UI::form_row( [
                'name'        => 'name',
                'id'          => 'el-inv-product-name',
                'label'       => __( 'Name', 'el-core' ),
                'placeholder' => __( 'e.g. Professional Development Training', 'el-core' ),
                'required'    => true,
            ] ) .
            EL_Admin_UI::form_row( [
                'name'        => 'slug',
                'id'          => 'el-inv-product-slug',
                'label'       => __( 'Slug', 'el-core' ),
                'placeholder' => __( 'Auto-generated from name', 'el-core' ),
                'helper'      => __( 'URL-safe identifier. Leave blank to auto-generate from name.', 'el-core' ),
            ] ) .
            EL_Admin_UI::form_row( [
                'name'     => 'category',
                'id'       => 'el-inv-product-category',
                'label'    => __( 'Category', 'el-core' ),
                'type'     => 'select',
                'required' => true,
                'options'  => array_merge(
                    [ '' => '— ' . __( 'Select', 'el-core' ) . ' —' ],
                    array_combine( $all_categories, array_map( 'ucfirst', $all_categories ) )
                ),
            ] ) .
            EL_Admin_UI::form_row( [
                'name'  => 'default_price',
                'id'    => 'el-inv-product-default-price',
                'label' => __( 'Default Price', 'el-core' ),
                'type'  => 'number',
                'value' => '0',
            ] ) .
            EL_Admin_UI::form_row( [
                'name'     => 'billing_cycle',
                'id'       => 'el-inv-product-billing-cycle',
                'label'    => __( 'Billing Cycle', 'el-core' ),
                'type'     => 'select',
                'options'  => [
                    'one-time'  => __( 'One-time', 'el-core' ),
                    'monthly'   => __( 'Monthly', 'el-core' ),
                    'quarterly' => __( 'Quarterly', 'el-core' ),
                    'annual'    => __( 'Annual', 'el-core' ),
                ],
            ] ) .
            EL_Admin_UI::form_row( [
                'name'        => 'description',
                'id'          => 'el-inv-product-description',
                'label'       => __( 'Description / Notes', 'el-core' ),
                'type'        => 'textarea',
                'placeholder' => __( 'Internal notes (not shown on invoice)', 'el-core' ),
            ] ) .
            EL_Admin_UI::form_row( [
                'name'        => 'status',
                'id'          => 'el-inv-product-status',
                'label'       => __( 'Status', 'el-core' ),
                'type'        => 'checkbox',
                'value'       => true,
                'placeholder' => __( 'Active (available on invoices)', 'el-core' ),
            ] ) .
            '<div class="el-modal-footer">' .
            EL_Admin_UI::btn( [ 'label' => __( 'Cancel', 'el-core' ), 'variant' => 'secondary', 'type' => 'button', 'data' => [ 'modal-close' => 'el-inv-modal-product' ] ] ) .
            EL_Admin_UI::btn( [ 'label' => __( 'Save Product', 'el-core' ), 'variant' => 'primary', 'type' => 'submit', 'id' => 'el-inv-btn-product-save' ] ) .
            '</div>' .
            '</form>',
    ] ) .

    EL_Admin_UI::modal( [
        'id'      => 'el-inv-modal-delete-product',
        'title'   => __( 'Delete Product', 'el-core' ),
        'content' =>
            '<input type="hidden" id="el-inv-delete-product-id" value="">' .
            '<p>' . __( 'Are you sure you want to delete', 'el-core' ) . ' <strong id="el-inv-delete-product-name"></strong>? ' . __( 'This cannot be undone.', 'el-core' ) . '</p>' .
            '<div class="el-modal-footer">' .
            EL_Admin_UI::btn( [ 'label' => __( 'Cancel', 'el-core' ), 'variant' => 'secondary', 'type' => 'button', 'data' => [ 'modal-close' => 'el-inv-modal-delete-product' ] ] ) .
            EL_Admin_UI::btn( [ 'label' => __( 'Delete', 'el-core' ), 'variant' => 'danger', 'type' => 'button', 'id' => 'el-inv-btn-confirm-delete-product' ] ) .
            '</div>',
    ] )
);

/**
 * Render a single product card for the grid.
 */
function el_inv_render_product_card( object $p ): string {
    $status_badge = $p->status === 'active'
        ? '<span class="el-badge el-badge-success">' . esc_html__( 'Active', 'el-core' ) . '</span>'
        : '<span class="el-badge el-badge-default">' . esc_html__( 'Inactive', 'el-core' ) . '</span>';
    $category_badge = '<span class="el-badge el-badge-info">' . esc_html( ucfirst( $p->category ) ) . '</span>';
    $price          = number_format( (float) $p->default_price, 2 );
    $cycle          = $p->billing_cycle ? esc_html( ucfirst( $p->billing_cycle ) ) : '—';

    return '<div class="el-inv-product-card el-card" data-id="' . esc_attr( $p->id ) . '">' .
        '<div class="el-card-body">' .
        '<div class="el-inv-product-card-header">' .
        '<h3 class="el-inv-product-title">' . esc_html( $p->name ) . '</h3>' .
        $status_badge .
        '</div>' .
        '<div class="el-inv-product-meta">' .
        $category_badge . ' &nbsp; ' . $cycle . ' &nbsp; $' . $price .
        '</div>' .
        ( $p->description ? '<p class="el-inv-product-desc">' . esc_html( wp_trim_words( $p->description, 12 ) ) . '</p>' : '' ) .
        '<div class="el-inv-product-actions">' .
        '<button type="button" class="el-btn el-btn-secondary el-btn-sm el-inv-btn-edit-product" ' .
        'data-id="' . esc_attr( $p->id ) . '" data-name="' . esc_attr( $p->name ) . '" data-slug="' . esc_attr( $p->slug ) . '" ' .
        'data-category="' . esc_attr( $p->category ) . '" data-default-price="' . esc_attr( $p->default_price ) . '" ' .
        'data-billing-cycle="' . esc_attr( $p->billing_cycle ) . '" data-description="' . esc_attr( $p->description ?? '' ) . '" data-status="' . esc_attr( $p->status ) . '">' .
        '<span class="dashicons dashicons-edit"></span> ' . __( 'Edit', 'el-core' ) .
        '</button>' .
        '<button type="button" class="el-btn el-btn-danger el-btn-sm el-inv-btn-delete-product" ' .
        'data-id="' . esc_attr( $p->id ) . '" data-name="' . esc_attr( $p->name ) . '">' .
        '<span class="dashicons dashicons-trash"></span> ' . __( 'Delete', 'el-core' ) .
        '</button>' .
        '</div>' .
        '</div>' .
        '</div>';
}
