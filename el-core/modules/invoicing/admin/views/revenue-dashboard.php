<?php
/**
 * Invoicing — Revenue Dashboard Admin Page
 *
 * Metrics, revenue by product/client/time, CSV export.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_invoices' ) ) {
    wp_die( __( 'Permission denied.', 'el-core' ) );
}

$module = EL_Invoicing_Module::instance();
$data   = $module->get_revenue_data();

$ajax_url = admin_url( 'admin-ajax.php' );
$nonce    = wp_create_nonce( 'el_core_nonce' );
$this_ym  = gmdate( 'Y-m' );
$this_y   = gmdate( 'Y' );

$prior_pct = $data['prior_year_pct_change'];
$prior_str = $prior_pct !== null
    ? ( $prior_pct >= 0 ? '+' . $prior_pct : $prior_pct ) . '% vs prior year'
    : '';

$stats = [
    [ 'icon' => 'money-alt', 'number' => '$' . number_format( $data['revenue_month'], 0 ), 'label' => __( 'This Month', 'el-core' ), 'variant' => 'primary' ],
    [ 'icon' => 'chart-line', 'number' => '$' . number_format( $data['revenue_quarter'], 0 ), 'label' => __( 'This Quarter', 'el-core' ), 'variant' => 'info' ],
    [ 'icon' => 'chart-area', 'number' => '$' . number_format( $data['revenue_year'], 0 ), 'label' => __( 'This Year', 'el-core' ) . ( $prior_str ? ' (' . $prior_str . ')' : '' ), 'variant' => 'success' ],
    [ 'icon' => 'warning', 'number' => '$' . number_format( $data['total_outstanding'], 0 ), 'label' => __( 'Outstanding', 'el-core' ), 'variant' => 'warning' ],
    [ 'icon' => 'flag', 'number' => '$' . number_format( $data['total_overdue'], 0 ), 'label' => __( 'Overdue', 'el-core' ), 'variant' => 'error' ],
    [ 'icon' => 'clock', 'number' => $data['avg_days_to_payment'] !== null ? (int) $data['avg_days_to_payment'] . ' days' : '—', 'label' => __( 'Avg Days to Payment', 'el-core' ), 'variant' => 'default' ],
];

$product_rows = [];
foreach ( $data['by_product'] as $p ) {
    $max = 0;
    foreach ( $data['by_product'] as $x ) {
        if ( (float) $x->total_invoiced > $max ) {
            $max = (float) $x->total_invoiced;
        }
    }
    $bar_pct = $max > 0 ? min( 100, ( (float) $p->total_invoiced / $max ) * 100 ) : 0;
    $product_rows[] = [
        'name'   => esc_html( $p->name ?? '' ),
        'amount' => '$' . number_format( (float) $p->total_invoiced, 2 ),
        'pct'    => (float) ( $p->pct ?? 0 ) . '%',
        'bar'    => '<div class="el-inv-rev-bar-wrap"><div class="el-inv-rev-bar" style="width:' . esc_attr( $bar_pct ) . '%;"></div></div>',
    ];
}

$client_rows = [];
$top_count = 0;
foreach ( $data['by_client'] as $i => $c ) {
    $is_top = $i < 10;
    $client_rows[] = [
        'org'         => ( $is_top ? '<strong>' : '' ) . esc_html( $c->org_name ?? '' ) . ( $is_top ? '</strong>' : '' ),
        'invoiced'    => '$' . number_format( (float) $c->total_invoiced, 2 ),
        'paid'        => '$' . number_format( (float) $c->total_paid, 2 ),
        'outstanding' => '$' . number_format( (float) $c->outstanding, 2 ),
    ];
}

$month_rows = [];
foreach ( $data['by_month'] as $m ) {
    $month_rows[] = [
        'month'     => esc_html( $m['label'] ?? $m['month'] ),
        'invoiced'  => '$' . number_format( (float) $m['invoiced'], 2 ),
        'collected' => '$' . number_format( (float) $m['collected'], 2 ),
    ];
}

$q_start = ( (int) gmdate( 'n' ) - 1 ) / 3 * 3 + 1;
$quarter_start_ym = $this_y . '-' . str_pad( (string) (int) $q_start, 2, '0', STR_PAD_LEFT );

$export_forms = '<form method="post" action="' . esc_url( $ajax_url ) . '" target="_blank" class="el-inv-export-form" style="display:inline;">
    <input type="hidden" name="action" value="el_core_action">
    <input type="hidden" name="el_action" value="inv_export_csv">
    <input type="hidden" name="nonce" value="' . esc_attr( $nonce ) . '">
    <input type="hidden" name="period" value="month">
    <input type="hidden" name="start_date" value="' . esc_attr( $this_ym ) . '">
    <input type="hidden" name="end_date" value="' . esc_attr( $this_ym ) . '">
    <button type="submit" class="el-btn el-btn-secondary"><span class="dashicons dashicons-download"></span>' . esc_html__( 'Export This Month', 'el-core' ) . '</button>
</form>
<form method="post" action="' . esc_url( $ajax_url ) . '" target="_blank" class="el-inv-export-form" style="display:inline;">
    <input type="hidden" name="action" value="el_core_action">
    <input type="hidden" name="el_action" value="inv_export_csv">
    <input type="hidden" name="nonce" value="' . esc_attr( $nonce ) . '">
    <input type="hidden" name="period" value="quarter">
    <input type="hidden" name="start_date" value="' . esc_attr( $quarter_start_ym ) . '">
    <input type="hidden" name="end_date" value="">
    <button type="submit" class="el-btn el-btn-secondary"><span class="dashicons dashicons-download"></span>' . esc_html__( 'Export Quarter', 'el-core' ) . '</button>
</form>
<form method="post" action="' . esc_url( $ajax_url ) . '" target="_blank" class="el-inv-export-form" style="display:inline;">
    <input type="hidden" name="action" value="el_core_action">
    <input type="hidden" name="el_action" value="inv_export_csv">
    <input type="hidden" name="nonce" value="' . esc_attr( $nonce ) . '">
    <input type="hidden" name="period" value="year">
    <input type="hidden" name="start_date" value="' . esc_attr( $this_y ) . '">
    <input type="hidden" name="end_date" value="">
    <button type="submit" class="el-btn el-btn-secondary"><span class="dashicons dashicons-download"></span>' . esc_html__( 'Export Year', 'el-core' ) . '</button>
</form>';

$html = EL_Admin_UI::page_header( [
    'title'    => __( 'Revenue', 'el-core' ),
    'subtitle' => __( 'Revenue metrics and export for bookkeeper.', 'el-core' ),
] );
$html .= '<div class="el-inv-export-buttons" style="margin-bottom:1.5rem;display:flex;gap:0.5rem;flex-wrap:wrap;">' . $export_forms . '</div>';

$html .= EL_Admin_UI::stats_grid( $stats );

$html .= EL_Admin_UI::card( [
    'title'   => __( 'Revenue by Product', 'el-core' ),
    'icon'    => 'cart',
    'content' => empty( $product_rows )
        ? '<p class="el-inv-placeholder">' . esc_html__( 'No product revenue yet.', 'el-core' ) . '</p>'
        : EL_Admin_UI::data_table( [
            'columns' => [
                [ 'key' => 'name', 'label' => __( 'Product', 'el-core' ) ],
                [ 'key' => 'amount', 'label' => __( 'Invoiced', 'el-core' ) ],
                [ 'key' => 'pct', 'label' => __( '% of Total', 'el-core' ) ],
                [ 'key' => 'bar', 'label' => '', 'class' => 'el-inv-rev-bar-col' ],
            ],
            'rows' => $product_rows,
        ] ),
] );

$html .= EL_Admin_UI::card( [
    'title'   => __( 'Revenue by Client', 'el-core' ),
    'icon'    => 'groups',
    'content' => empty( $client_rows )
        ? '<p class="el-inv-placeholder">' . esc_html__( 'No client revenue yet.', 'el-core' ) . '</p>'
        : EL_Admin_UI::data_table( [
            'columns' => [
                [ 'key' => 'org', 'label' => __( 'Client', 'el-core' ) ],
                [ 'key' => 'invoiced', 'label' => __( 'Invoiced', 'el-core' ) ],
                [ 'key' => 'paid', 'label' => __( 'Paid', 'el-core' ) ],
                [ 'key' => 'outstanding', 'label' => __( 'Outstanding', 'el-core' ) ],
            ],
            'rows' => $client_rows,
        ] ),
] );

$html .= EL_Admin_UI::card( [
    'title'   => __( 'Revenue by Month (Last 12 Months)', 'el-core' ),
    'icon'    => 'calendar-alt',
    'content' => EL_Admin_UI::data_table( [
        'columns' => [
            [ 'key' => 'month', 'label' => __( 'Month', 'el-core' ) ],
            [ 'key' => 'invoiced', 'label' => __( 'Invoiced', 'el-core' ) ],
            [ 'key' => 'collected', 'label' => __( 'Collected', 'el-core' ) ],
        ],
        'rows' => $month_rows,
    ] ),
] );

echo EL_Admin_UI::wrap( $html );
