<?php
/**
 * Invoicing — Invoice List Admin Page
 *
 * Stats, filters, table of invoices. Create Invoice → invoice editor.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'create_invoices' ) ) {
    wp_die( __( 'Permission denied.', 'el-core' ) );
}

$core   = el_core();
$db     = $core->database;
$inv_table = $db->get_table_name( 'el_inv_invoices' );
$org_table = $db->get_table_name( 'el_organizations' );

// Overdue automation: mark sent/viewed/partial as overdue when due_date passed and balance_due > 0
global $wpdb;
$today = gmdate( 'Y-m-d' );
$wpdb->query( $wpdb->prepare(
    "UPDATE {$inv_table} SET status = 'overdue', updated_at = %s
     WHERE status IN ('sent', 'viewed', 'partial') AND due_date IS NOT NULL AND due_date < %s AND balance_due > 0",
    current_time( 'mysql' ),
    $today
) );

$filter_status = sanitize_text_field( $_GET['status'] ?? '' );
$filter_date_from = sanitize_text_field( $_GET['date_from'] ?? '' );
$filter_date_to   = sanitize_text_field( $_GET['date_to'] ?? '' );
$filter_org       = absint( $_GET['organization_id'] ?? 0 );

$where_clauses = [ "1=1" ];
$where_values  = [];
if ( $filter_status && in_array( $filter_status, [ 'draft', 'sent', 'viewed', 'paid', 'partial', 'overdue', 'cancelled' ], true ) ) {
    $where_clauses[] = 'i.status = %s';
    $where_values[]  = $filter_status;
}
if ( $filter_date_from ) {
    $where_clauses[] = 'i.issue_date >= %s';
    $where_values[]  = $filter_date_from;
}
if ( $filter_date_to ) {
    $where_clauses[] = 'i.issue_date <= %s';
    $where_values[]  = $filter_date_to;
}
if ( $filter_org ) {
    $where_clauses[] = 'i.organization_id = %d';
    $where_values[]  = $filter_org;
}

$where_sql = implode( ' AND ', $where_clauses );
$sql = "SELECT i.*, o.name AS org_name FROM {$inv_table} i
        LEFT JOIN {$org_table} o ON o.id = i.organization_id
        WHERE {$where_sql} ORDER BY i.created_at DESC";
if ( $where_values ) {
    $sql = $wpdb->prepare( $sql, ...$where_values );
}
$invoices = $wpdb->get_results( $sql );

// Stats (global, not filtered)
$this_month = gmdate( 'Y-m' );
$this_year  = gmdate( 'Y' );
$outstanding = (float) $wpdb->get_var( "SELECT COALESCE(SUM(balance_due), 0) FROM {$inv_table} WHERE status != 'cancelled'" );
$overdue_sum = (float) $wpdb->get_var( "SELECT COALESCE(SUM(balance_due), 0) FROM {$inv_table} WHERE status = 'overdue'" );
$payments_table = $db->get_table_name( 'el_inv_payments' );
$collected_month  = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM {$payments_table} WHERE DATE(payment_date) >= %s AND DATE(payment_date) < %s",
    $this_month . '-01',
    gmdate( 'Y-m-d', strtotime( $this_month . '-01 +1 month' ) )
) );
$collected_year = (float) $wpdb->get_var( $wpdb->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM {$payments_table} WHERE YEAR(payment_date) = %s",
    $this_year
) );

$status_options = [
    ''          => __( 'All Statuses', 'el-core' ),
    'draft'     => __( 'Draft', 'el-core' ),
    'sent'      => __( 'Sent', 'el-core' ),
    'viewed'    => __( 'Viewed', 'el-core' ),
    'partial'   => __( 'Partial', 'el-core' ),
    'paid'      => __( 'Paid', 'el-core' ),
    'overdue'   => __( 'Overdue', 'el-core' ),
    'cancelled' => __( 'Cancelled', 'el-core' ),
];

$base_url = admin_url( 'admin.php?page=el-core-invoices' );
$list_actions = [
    [
        'label'   => __( 'Create Invoice', 'el-core' ),
        'variant' => 'primary',
        'icon'    => 'plus',
        'url'     => add_query_arg( 'new', '1', $base_url ),
    ],
];

$rows = [];
foreach ( $invoices as $inv ) {
    $status_variant = 'default';
    if ( $inv->status === 'paid' ) {
        $status_variant = 'success';
    } elseif ( $inv->status === 'overdue' ) {
        $status_variant = 'error';
    } elseif ( in_array( $inv->status, [ 'sent', 'viewed' ], true ) ) {
        $status_variant = 'info';
    } elseif ( $inv->status === 'partial' ) {
        $status_variant = 'warning';
    } elseif ( $inv->status === 'cancelled' ) {
        $status_variant = 'default';
    }
    $status_badge = EL_Admin_UI::badge( [
        'label'   => ucfirst( $inv->status ),
        'variant' => $status_variant,
    ] );

    $edit_url = add_query_arg( 'invoice_id', $inv->id, $base_url );
    $view_url = home_url( '/?el_invoice_view=1&id=' . (int) $inv->id );
    $actions  = '<a href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener" class="el-btn el-btn-ghost" title="' . esc_attr__( 'View invoice (customer view)', 'el-core' ) . '"><span class="dashicons dashicons-visibility"></span>' . esc_html__( 'View', 'el-core' ) . '</a>';
    $actions .= EL_Admin_UI::btn( [
        'label'   => __( 'Edit', 'el-core' ),
        'variant' => 'ghost',
        'icon'    => 'edit',
        'url'     => $edit_url,
    ] );
    $actions .= EL_Admin_UI::btn( [
        'label'   => __( 'Duplicate', 'el-core' ),
        'variant' => 'ghost',
        'icon'    => 'admin-page',
        'class'   => 'el-inv-btn-duplicate',
        'data'    => [ 'invoice-id' => $inv->id ],
    ] );
    if ( $inv->status !== 'cancelled' && (float) $inv->balance_due > 0 ) {
        $actions .= EL_Admin_UI::btn( [
            'label'   => __( 'Record Payment', 'el-core' ),
            'variant' => 'ghost',
            'icon'    => 'money-alt',
            'class'   => 'el-inv-btn-record-payment',
            'data'    => [
                'invoice-id'     => $inv->id,
                'balance-due'    => number_format( (float) $inv->balance_due, 2, '.', '' ),
                'invoice-number' => $inv->invoice_number,
            ],
        ] );
    }
    $actions .= EL_Admin_UI::btn( [
        'label'   => __( 'Delete', 'el-core' ),
        'variant' => 'ghost',
        'icon'    => 'trash',
        'class'   => 'el-inv-btn-delete-invoice',
        'data'    => [ 'invoice-id' => $inv->id ],
    ] );

    $issue_date = $inv->issue_date ? date_i18n( 'M j, Y', strtotime( $inv->issue_date ) ) : '—';
    $due_date   = $inv->due_date ? date_i18n( 'M j, Y', strtotime( $inv->due_date ) ) : '—';
    $client_name = $inv->org_name ? esc_html( $inv->org_name ) : '—';

    $rows[] = [
        'invoice_number' => '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $inv->invoice_number ) . '</a>',
        'client'         => $client_name,
        'amount'          => '$' . number_format( (float) $inv->total, 2 ),
        'status'          => $status_badge,
        'issue_date'      => $issue_date,
        'due_date'        => $due_date,
        'balance_due'     => '$' . number_format( (float) $inv->balance_due, 2 ),
        '__actions'       => $actions,
    ];
}

echo EL_Admin_UI::wrap(
    EL_Admin_UI::page_header( [
        'title'    => __( 'Invoices', 'el-core' ),
        'subtitle' => __( 'Create and manage invoices.', 'el-core' ),
        'actions'  => $list_actions,
    ] ) .

    EL_Admin_UI::stats_grid( [
        [ 'icon' => 'money-alt', 'number' => '$' . number_format( $outstanding, 0 ), 'label' => __( 'Total Outstanding', 'el-core' ), 'variant' => 'primary' ],
        [ 'icon' => 'warning',   'number' => '$' . number_format( $overdue_sum, 0 ), 'label' => __( 'Total Overdue', 'el-core' ),   'variant' => 'error' ],
        [ 'icon' => 'chart-line', 'number' => '$' . number_format( $collected_month, 0 ), 'label' => __( 'Collected This Month', 'el-core' ), 'variant' => 'success' ],
        [ 'icon' => 'chart-area', 'number' => '$' . number_format( $collected_year, 0 ), 'label' => __( 'Collected This Year', 'el-core' ), 'variant' => 'info' ],
    ] ) .

    EL_Admin_UI::filter_bar( [
        'action'      => $base_url,
        'search_name' => 's',
        'filters'     => [
            [
                'name'    => 'status',
                'value'   => $filter_status,
                'options' => $status_options,
            ],
        ],
        'hidden' => [ 'page' => 'el-core-invoices' ],
    ] ) .

    EL_Admin_UI::card( [
        'title'   => __( 'All Invoices', 'el-core' ),
        'icon'    => 'list-view',
        'content' => EL_Admin_UI::data_table( [
            'columns' => [
                [ 'key' => 'invoice_number', 'label' => __( 'Invoice #', 'el-core' ) ],
                [ 'key' => 'client',         'label' => __( 'Client', 'el-core' ) ],
                [ 'key' => 'amount',         'label' => __( 'Amount', 'el-core' ) ],
                [ 'key' => 'status',         'label' => __( 'Status', 'el-core' ) ],
                [ 'key' => 'issue_date',     'label' => __( 'Issue Date', 'el-core' ) ],
                [ 'key' => 'due_date',       'label' => __( 'Due Date', 'el-core' ) ],
                [ 'key' => 'balance_due',    'label' => __( 'Balance Due', 'el-core' ) ],
            ],
            'rows'   => $rows,
            'empty'  => [
                'icon'    => 'media-document',
                'title'   => __( 'No invoices yet', 'el-core' ),
                'message' => __( 'Create your first invoice to get started.', 'el-core' ),
                'action'  => [
                    'label'   => __( 'Create Invoice', 'el-core' ),
                    'variant' => 'primary',
                    'url'     => add_query_arg( 'new', '1', $base_url ),
                ],
            ],
        ] ),
    ] ) .

    EL_Admin_UI::modal( [
        'id'      => 'el-inv-modal-payment',
        'title'   => __( 'Record Payment', 'el-core' ),
        'size'    => 'default',
        'content' =>
            '<form id="el-inv-form-payment">' .
            '<input type="hidden" name="invoice_id" id="el-inv-payment-invoice-id" value="">' .
            EL_Admin_UI::form_row( [
                'name'     => 'amount',
                'id'       => 'el-inv-payment-amount',
                'label'    => __( 'Amount', 'el-core' ),
                'type'     => 'number',
                'value'    => '0',
                'required' => true,
            ] ) .
            EL_Admin_UI::form_row( [
                'name'     => 'payment_method',
                'id'       => 'el-inv-payment-method',
                'label'    => __( 'Payment method', 'el-core' ),
                'type'     => 'select',
                'options'  => [
                    'check' => __( 'Check', 'el-core' ),
                    'ach'   => __( 'ACH', 'el-core' ),
                    'wire'  => __( 'Wire', 'el-core' ),
                    'zelle' => __( 'Zelle', 'el-core' ),
                    'other' => __( 'Other', 'el-core' ),
                ],
            ] ) .
            EL_Admin_UI::form_row( [
                'name'  => 'payment_date',
                'id'    => 'el-inv-payment-date',
                'label' => __( 'Payment date', 'el-core' ),
                'type'  => 'date',
            ] ) .
            EL_Admin_UI::form_row( [
                'name'        => 'reference_number',
                'id'          => 'el-inv-payment-reference',
                'label'       => __( 'Reference (e.g. check #)', 'el-core' ),
                'placeholder' => __( 'Optional', 'el-core' ),
            ] ) .
            EL_Admin_UI::form_row( [
                'name'        => 'notes',
                'id'          => 'el-inv-payment-notes',
                'label'       => __( 'Notes', 'el-core' ),
                'type'        => 'textarea',
                'placeholder' => __( 'Optional', 'el-core' ),
            ] ) .
            '<div class="el-modal-footer">' .
            EL_Admin_UI::btn( [ 'label' => __( 'Cancel', 'el-core' ), 'variant' => 'secondary', 'type' => 'button', 'data' => [ 'modal-close' => 'el-inv-modal-payment' ] ] ) .
            EL_Admin_UI::btn( [ 'label' => __( 'Record Payment', 'el-core' ), 'variant' => 'primary', 'type' => 'submit', 'id' => 'el-inv-btn-payment-save' ] ) .
            '</div>' .
            '</form>',
    ] )
);
