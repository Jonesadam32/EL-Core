<?php
/**
 * Bookkeeping — Profit & Loss Tab
 *
 * Server-rendered P&L report built from classified transactions.
 *
 * @var EL_Bookkeeping_Module $module
 * @var int                   $tax_year
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Date range from GET, default to full tax year.
$from    = sanitize_text_field( $_GET['pl_from'] ?? ( $tax_year . '-01-01' ) );
$to      = sanitize_text_field( $_GET['pl_to']   ?? ( $tax_year . '-12-31' ) );
$pl_view = sanitize_text_field( $_GET['pl_view'] ?? 'business' );
if ( ! in_array( $pl_view, [ 'business', 'all' ], true ) ) {
    $pl_view = 'business';
}

$business_name = $module->get_business_name();
$personal_cats = EL_Bookkeeping_Module::get_expense_categories_grouped()['personal'];

// Fetch all transactions in the date range.
global $wpdb;
$tbl = $wpdb->prefix . 'el_bk_transactions';
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$all_rows = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$tbl} WHERE date BETWEEN %s AND %s ORDER BY date ASC",
    $from,
    $to
) ) ?: [];

// Split income vs expense; exclude split-parent rows from expense totals.
$income_rows  = array_filter( $all_rows, fn( $t ) => $t->type === 'income' );
$expense_rows = array_filter( $all_rows, fn( $t ) => $t->type === 'expense' && $t->status !== 'split' );

// Excluded income categories.
$income_excluded = [ 'Other', 'Bank Transfer', 'Ignore', 'Distributions', 'Shareholder Loan', 'Refund', 'Travel Credit' ];
$taxable_income  = array_filter( $income_rows, fn( $t ) => ! in_array( $t->category, $income_excluded, true ) );
$distributions   = array_filter( $income_rows, fn( $t ) => $t->category === 'Distributions' );

$total_income       = array_sum( array_map( fn( $t ) => (float) $t->amount, $taxable_income ) );
$total_distribution = array_sum( array_map( fn( $t ) => (float) $t->amount, $distributions ) );

// Build expense totals by category, respecting view mode.
$expense_cats = [];
foreach ( $expense_rows as $t ) {
    if ( $pl_view === 'business' && in_array( $t->category, $personal_cats, true ) ) {
        continue;
    }
    $cat = $t->category ?: __( 'Unclassified', 'el-core' );
    $expense_cats[ $cat ] = ( $expense_cats[ $cat ] ?? 0.0 ) + (float) $t->amount;
}
arsort( $expense_cats );
$total_expenses = array_sum( $expense_cats );
$net_income     = $total_income - $total_distribution - $total_expenses;
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Profit & Loss — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
</div>

<div class="el-bk-pl-controls">
    <label><?php esc_html_e( 'From', 'el-core' ); ?>
        <input type="date" id="el-bk-pl-from" value="<?php echo esc_attr( $from ); ?>">
    </label>
    <label><?php esc_html_e( 'To', 'el-core' ); ?>
        <input type="date" id="el-bk-pl-to" value="<?php echo esc_attr( $to ); ?>">
    </label>
    <button class="el-btn el-btn-primary" id="el-bk-pl-filter-btn"><?php esc_html_e( 'Filter', 'el-core' ); ?></button>
    <div class="el-bk-pl-presets">
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="this-year"><?php esc_html_e( 'This Year', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="last-year"><?php esc_html_e( 'Last Year', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="q1"><?php esc_html_e( 'Q1', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="q2"><?php esc_html_e( 'Q2', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="q3"><?php esc_html_e( 'Q3', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="q4"><?php esc_html_e( 'Q4', 'el-core' ); ?></button>
    </div>
    <div class="el-bk-pl-view-toggle">
        <button class="el-btn el-btn-toggle <?php echo $pl_view === 'business' ? 'el-btn-toggle--active' : ''; ?>"
                data-view="business">
            <?php esc_html_e( 'Business Only', 'el-core' ); ?>
        </button>
        <button class="el-btn el-btn-toggle <?php echo $pl_view === 'all' ? 'el-btn-toggle--active' : ''; ?>"
                data-view="all">
            <?php esc_html_e( 'All Transactions', 'el-core' ); ?>
        </button>
    </div>
</div>

<div class="el-bk-pl-report-wrap">

    <div class="el-bk-pl-report-header">
        <h3><?php echo esc_html( $business_name ); ?></h3>
        <p>
            <?php echo esc_html( sprintf(
                __( 'Profit and Loss: %s TO %s', 'el-core' ),
                date_i18n( 'Y-m-d', strtotime( $from ) ),
                date_i18n( 'Y-m-d', strtotime( $to ) )
            ) ); ?>
        </p>
    </div>

    <!-- Summary row -->
    <div class="el-bk-pl-summary-row">
        <div class="el-bk-pl-summary-cell">
            <div class="el-bk-pl-summary-cell-label"><?php esc_html_e( 'Revenue', 'el-core' ); ?></div>
            <div class="el-bk-pl-summary-cell-amount el-bk-pl-summary-cell-amount--income">
                $<?php echo esc_html( number_format( $total_income, 2 ) ); ?>
            </div>
        </div>
        <div class="el-bk-pl-summary-cell">
            <div class="el-bk-pl-summary-cell-label"><?php esc_html_e( 'Expenses', 'el-core' ); ?></div>
            <div class="el-bk-pl-summary-cell-amount el-bk-pl-summary-cell-amount--expense">
                $<?php echo esc_html( number_format( $total_expenses, 2 ) ); ?>
            </div>
        </div>
        <div class="el-bk-pl-summary-cell">
            <div class="el-bk-pl-summary-cell-label"><?php esc_html_e( 'Net Income', 'el-core' ); ?></div>
            <div class="el-bk-pl-summary-cell-amount <?php echo $net_income >= 0 ? 'el-bk-pl-summary-cell-amount--profit' : 'el-bk-pl-summary-cell-amount--loss'; ?>">
                <?php echo $net_income < 0 ? '-' : ''; ?>$<?php echo esc_html( number_format( abs( $net_income ), 2 ) ); ?>
            </div>
        </div>
    </div>

    <!-- View mode label -->
    <div class="el-bk-pl-view-label">
        <?php if ( $pl_view === 'business' ) : ?>
            <span class="el-bk-pl-view-label--business">
                <?php esc_html_e( 'Showing business expenses only (Schedule C)', 'el-core' ); ?>
            </span>
        <?php else : ?>
            <span class="el-bk-pl-view-label--all">
                <?php esc_html_e( 'Showing all transactions including personal', 'el-core' ); ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- Income section -->
    <div class="el-bk-pl-expense-section">
        <h4><?php esc_html_e( 'Income', 'el-core' ); ?></h4>
        <table class="el-bk-pl-table widefat">
            <tbody>
                <tr>
                    <td><?php esc_html_e( 'Income Total', 'el-core' ); ?></td>
                    <td>$<?php echo esc_html( number_format( $total_income, 2 ) ); ?></td>
                </tr>
                <?php if ( $total_distribution > 0 ) : ?>
                <tr>
                    <td><?php esc_html_e( 'Distributions total from Expenses', 'el-core' ); ?></td>
                    <td>$<?php echo esc_html( number_format( $total_distribution, 2 ) ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Net Owner Funding (Contributions − Distributions)', 'el-core' ); ?></td>
                    <td>-$<?php echo esc_html( number_format( $total_distribution, 2 ) ); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h4><?php esc_html_e( 'Expenses', 'el-core' ); ?></h4>
        <?php if ( empty( $expense_cats ) ) : ?>
            <p style="color:#666;font-size:13px;"><?php esc_html_e( 'No classified expense transactions in this date range.', 'el-core' ); ?></p>
        <?php else : ?>
        <table class="el-bk-pl-table widefat">
            <tbody>
                <?php foreach ( $expense_cats as $cat => $amount ) : ?>
                <tr>
                    <td><?php echo esc_html( $cat ); ?></td>
                    <td>$<?php echo esc_html( number_format( $amount, 2 ) ); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="el-bk-pl-subtotal-row">
                    <td><?php esc_html_e( 'Expenses Total', 'el-core' ); ?></td>
                    <td>$<?php echo esc_html( number_format( $total_expenses, 2 ) ); ?></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Net Income footer -->
    <table class="el-bk-pl-table" style="width:100%;">
        <tbody>
            <tr class="el-bk-pl-net-income-row">
                <td><?php esc_html_e( 'NET INCOME', 'el-core' ); ?></td>
                <td style="text-align:right;">
                    <?php echo $net_income < 0 ? '-' : ''; ?>$<?php echo esc_html( number_format( abs( $net_income ), 2 ) ); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <div class="el-bk-pl-export-row">
        <button class="el-btn el-btn-outline el-bk-export-pl-btn" data-format="csv"><?php esc_html_e( 'Download CSV', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-export-pl-btn" data-format="pdf"><?php esc_html_e( 'Download PDF', 'el-core' ); ?></button>
    </div>

    <div class="el-bk-export-section">
        <h3><?php esc_html_e( 'Export for Accountant', 'el-core' ); ?></h3>
        <p class="el-bk-hint">
            <?php esc_html_e( 'Generate a complete Excel workbook with income, expenses, deductions, travel log, contractors, home office and vehicle worksheets — everything your accountant needs for Schedule C.', 'el-core' ); ?>
        </p>
        <div class="el-bk-export-section-actions">
            <button class="el-btn el-btn-primary" id="el-bk-export-accountant-btn">
                <?php esc_html_e( 'Download Tax Export (.xlsx)', 'el-core' ); ?>
            </button>
            <span id="el-bk-export-status"></span>
        </div>
    </div>

</div>
