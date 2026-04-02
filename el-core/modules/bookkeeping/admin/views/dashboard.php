<?php
/**
 * Bookkeeping — Dashboard Tab
 *
 * Landing page: stat cards + quick-access module grid.
 *
 * @var EL_Bookkeeping_Module $module
 * @var int                   $tax_year
 * @var array                 $prefetch_expenses
 * @var array                 $prefetch_income
 * @var array                 $prefetch_receipts
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$base_url = admin_url( 'admin.php?page=els-bookkeeping&year=' . $tax_year );

// ── Compute stats from pre-fetched data ───────────────────────────────────────
$total_expenses   = array_sum( array_map( fn( $t ) => (float) $t->amount, $prefetch_expenses ) );
$total_income_raw = array_map( fn( $t ) => (float) $t->amount, $prefetch_income );
$total_income     = array_sum( $total_income_raw );
$net_profit       = $total_income - $total_expenses;
$unreviewed       = count( $prefetch_receipts );

$profit_class = $net_profit >= 0 ? 'el-stat-success' : 'el-stat-danger';

// ── Stat cards ────────────────────────────────────────────────────────────────
echo EL_Admin_UI::stats_grid( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    [
        'icon'    => 'money-alt',
        'number'  => '$' . number_format( $total_expenses, 2 ),
        'label'   => sprintf( __( 'Total Expenses — %d', 'el-core' ), $tax_year ),
        'variant' => 'danger',
        'url'     => esc_url( $base_url . '&tab=expenses' ),
    ],
    [
        'icon'    => 'chart-bar',
        'number'  => '$' . number_format( $total_income, 2 ),
        'label'   => sprintf( __( 'Total Income — %d', 'el-core' ), $tax_year ),
        'variant' => 'success',
        'url'     => esc_url( $base_url . '&tab=income' ),
    ],
    [
        'icon'    => 'calculator',
        'number'  => ( $net_profit < 0 ? '-$' : '$' ) . number_format( abs( $net_profit ), 2 ),
        'label'   => sprintf( __( 'Net Profit / Loss — %d', 'el-core' ), $tax_year ),
        'variant' => $net_profit >= 0 ? 'success' : 'danger',
        'url'     => esc_url( $base_url . '&tab=profit-loss' ),
    ],
    [
        'icon'    => 'media-document',
        'number'  => $unreviewed,
        'label'   => __( 'Unreviewed Receipts', 'el-core' ),
        'variant' => $unreviewed > 0 ? 'warning' : 'primary',
        'url'     => esc_url( $base_url . '&tab=receipts' ),
    ],
] );
?>

<p class="el-bk-dashboard-section-title"><?php esc_html_e( 'Quick Access', 'el-core' ); ?></p>

<div class="el-bk-quick-grid">

    <?php
    $modules = [
        [
            'slug'  => 'expenses',
            'icon'  => 'money-alt',
            'title' => __( 'Expenses', 'el-core' ),
            'desc'  => __( 'Upload CSV and classify business expense transactions.', 'el-core' ),
            'link'  => __( 'Open Expenses →', 'el-core' ),
        ],
        [
            'slug'  => 'income',
            'icon'  => 'chart-bar',
            'title' => __( 'Income & Deposits', 'el-core' ),
            'desc'  => __( 'Track and categorize income and bank deposits.', 'el-core' ),
            'link'  => __( 'Open Income →', 'el-core' ),
        ],
        [
            'slug'  => 'profit-loss',
            'icon'  => 'analytics',
            'title' => __( 'Profit & Loss', 'el-core' ),
            'desc'  => __( 'View your Schedule C-ready P&L report for any date range.', 'el-core' ),
            'link'  => __( 'View Report →', 'el-core' ),
        ],
        [
            'slug'  => 'contractors',
            'icon'  => 'groups',
            'title' => __( 'Contractors', 'el-core' ),
            'desc'  => __( 'Assign Contract Labor transactions and manage 1099 info.', 'el-core' ),
            'link'  => __( 'Open Contractors →', 'el-core' ),
        ],
        [
            'slug'  => 'known-expenses',
            'icon'  => 'admin-generic',
            'title' => __( 'Known Expenses', 'el-core' ),
            'desc'  => __( 'Train AI auto-classification rules on your pre-existing expenses.', 'el-core' ),
            'link'  => __( 'Manage Rules →', 'el-core' ),
        ],
        [
            'slug'  => 'travel-dates',
            'icon'  => 'airplane',
            'title' => __( 'Travel Dates', 'el-core' ),
            'desc'  => __( 'Set business travel periods — expenses within are auto-categorized.', 'el-core' ),
            'link'  => __( 'Manage Travel →', 'el-core' ),
        ],
        [
            'slug'  => 'receipts',
            'icon'  => 'media-document',
            'title' => __( 'Receipts', 'el-core' ),
            'desc'  => __( 'Upload and AI-match receipt images to transactions.', 'el-core' ),
            'link'  => __( 'Upload Receipts →', 'el-core' ),
        ],
        [
            'slug'  => 'settings',
            'icon'  => 'admin-settings',
            'title' => __( 'Settings', 'el-core' ),
            'desc'  => __( 'Business info, default tax year, and Schedule C configuration.', 'el-core' ),
            'link'  => __( 'Open Settings →', 'el-core' ),
        ],
    ];

    foreach ( $modules as $m ) :
        if ( $m['slug'] === 'settings' && ! el_core_can( 'manage_bookkeeping_settings' ) ) continue;
        $href = esc_url( $base_url . '&tab=' . $m['slug'] );
    ?>
    <a href="<?php echo $href; ?>" class="el-bk-quick-card">
        <div class="el-bk-quick-card-icon">
            <span class="dashicons dashicons-<?php echo esc_attr( $m['icon'] ); ?>"></span>
        </div>
        <p class="el-bk-quick-card-title"><?php echo esc_html( $m['title'] ); ?></p>
        <p class="el-bk-quick-card-desc"><?php echo esc_html( $m['desc'] ); ?></p>
        <span class="el-bk-quick-card-link"><?php echo esc_html( $m['link'] ); ?></span>
    </a>
    <?php endforeach; ?>

</div>
