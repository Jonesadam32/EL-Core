<?php
/**
 * Bookkeeping — Expenses Tab
 *
 * @var EL_Bookkeeping_Module $module
 * @var int                   $tax_year
 * @var array                 $prefetch_expenses
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$transactions  = $prefetch_expenses;
$categories    = EL_Bookkeeping_Module::get_expense_categories();
$bank_accounts = array_unique( array_filter( array_map( fn( $t ) => $t->bank_account ?? '', $transactions ) ) );
sort( $bank_accounts );

// ── Build category totals for summary bar ────────────────────────────────────
$category_totals = [];
$total_classified = 0.0;
foreach ( $transactions as $t ) {
    if ( ! empty( $t->category ) ) {
        $category_totals[ $t->category ] = ( $category_totals[ $t->category ] ?? 0.0 ) + (float) $t->amount;
        $total_classified += (float) $t->amount;
    }
}
arsort( $category_totals );

$total_all = array_sum( array_map( fn( $t ) => (float) $t->amount, $transactions ) );
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Expenses — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
    <div class="el-bk-tab-header-right">
        <button class="el-btn el-btn-outline el-bk-export-btn" data-format="csv">
            <?php esc_html_e( 'Download CSV', 'el-core' ); ?>
        </button>
        <button class="el-btn el-btn-outline" id="el-bk-import-ledger-btn">
            <?php esc_html_e( 'Import Ledger Tab', 'el-core' ); ?>
        </button>
    </div>
</div>

<?php if ( ! empty( $category_totals ) ) : ?>
<div class="el-bk-summary-bar">
    <div class="el-bk-summary-bar-header">
        <span class="el-bk-summary-bar-title">
            <?php echo esc_html( sprintf( __( 'Business — %s', 'el-core' ), $module->get_business_name() ) ); ?>
        </span>
        <span class="el-bk-summary-bar-total">
            <?php echo esc_html( sprintf( __( 'Estimated Business Expenses (TENTATIVE): $%s', 'el-core' ), number_format( $total_classified, 2 ) ) ); ?>
        </span>
    </div>
    <div class="el-bk-summary-grid">
        <?php foreach ( $category_totals as $cat => $amount ) : ?>
        <div class="el-bk-summary-item">
            <span class="el-bk-summary-item-label"><?php echo esc_html( $cat ); ?>:</span>
            <span class="el-bk-summary-item-amount">$<?php echo esc_html( number_format( $amount, 2 ) ); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="el-bk-action-row">
    <button class="el-btn el-btn-primary" id="el-bk-reclassify-btn">
        <?php esc_html_e( 'Re-Classify Expenses', 'el-core' ); ?>
    </button>
    <button class="el-btn el-btn-outline el-bk-confirm-all-btn" data-scope="all">
        <?php esc_html_e( 'Confirm All Suggestions', 'el-core' ); ?>
    </button>
</div>

<div class="el-bk-filter-bar">
    <div class="el-bk-filter-bar-row">
        <div class="el-bk-filter-field">
            <label for="el-bk-exp-search"><?php esc_html_e( 'Search', 'el-core' ); ?></label>
            <input type="text" id="el-bk-exp-search" placeholder="<?php esc_attr_e( 'Merchant, business, comments…', 'el-core' ); ?>">
        </div>
        <div class="el-bk-filter-field">
            <label for="el-bk-exp-cat-filter"><?php esc_html_e( 'Category', 'el-core' ); ?></label>
            <select id="el-bk-exp-cat-filter">
                <option value=""><?php esc_html_e( '— All Categories —', 'el-core' ); ?></option>
                <option value="__unclassified__"><?php esc_html_e( '— Unclassified —', 'el-core' ); ?></option>
                <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="el-bk-filter-field">
            <label for="el-bk-exp-bank-filter"><?php esc_html_e( 'Bank Account', 'el-core' ); ?></label>
            <select id="el-bk-exp-bank-filter">
                <option value=""><?php esc_html_e( '— All Accounts —', 'el-core' ); ?></option>
                <?php foreach ( $bank_accounts as $acct ) : ?>
                    <option value="<?php echo esc_attr( $acct ); ?>"><?php echo esc_html( $acct ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="el-bk-filter-field">
            <label for="el-bk-exp-status-filter"><?php esc_html_e( 'Status', 'el-core' ); ?></label>
            <select id="el-bk-exp-status-filter">
                <option value=""><?php esc_html_e( '— All —', 'el-core' ); ?></option>
                <option value="classified"><?php esc_html_e( 'Classified (locked)', 'el-core' ); ?></option>
                <option value="suggested"><?php esc_html_e( 'Suggested', 'el-core' ); ?></option>
                <option value="unclassified"><?php esc_html_e( 'Unclassified', 'el-core' ); ?></option>
                <option value="rejected"><?php esc_html_e( 'Rejected', 'el-core' ); ?></option>
            </select>
        </div>
    </div>
    <div class="el-bk-filter-bar-row">
        <div class="el-bk-filter-field">
            <label for="el-bk-exp-from"><?php esc_html_e( 'From', 'el-core' ); ?></label>
            <input type="date" id="el-bk-exp-from" value="<?php echo esc_attr( $tax_year . '-01-01' ); ?>">
        </div>
        <div class="el-bk-filter-field">
            <label for="el-bk-exp-to"><?php esc_html_e( 'To', 'el-core' ); ?></label>
            <input type="date" id="el-bk-exp-to" value="<?php echo esc_attr( $tax_year . '-12-31' ); ?>">
        </div>
        <div class="el-bk-filter-field el-bk-filter-actions">
            <button class="el-btn el-btn-outline" id="el-bk-exp-clear-filters"><?php esc_html_e( 'Clear Filters', 'el-core' ); ?></button>
        </div>
        <div class="el-bk-filter-field el-bk-filter-count">
            <span id="el-bk-exp-filter-count"></span>
        </div>
    </div>
</div>

<div class="el-bk-legend">
    <span class="el-bk-legend-item">
        <span class="el-bk-legend-swatch el-bk-legend-swatch--classified"></span>
        <?php esc_html_e( 'Classified (🔒 locked — safe from Re-Classify)', 'el-core' ); ?>
    </span>
    <span class="el-bk-legend-item">
        <span class="el-bk-legend-swatch el-bk-legend-swatch--suggested"></span>
        <?php esc_html_e( 'Suggestions not yet applied', 'el-core' ); ?>
    </span>
    <span class="el-bk-legend-item">
        <span class="el-bk-legend-swatch el-bk-legend-swatch--rejected"></span>
        <?php esc_html_e( 'Rejected Suggestions', 'el-core' ); ?>
    </span>
</div>

<?php if ( empty( $transactions ) ) : ?>
    <?php echo EL_Admin_UI::notice( [ 'message' => __( 'No expense transactions found for this tax year. Go to the Income & Deposits tab and upload your bank statements — expenses will be auto-sorted here.', 'el-core' ), 'type' => 'info' ] ); // phpcs:ignore ?>
<?php else : ?>

<div class="el-bk-table-wrap">
    <table class="el-bk-transactions-table widefat">
        <thead>
            <tr>
                <th>#</th>
                <th><?php esc_html_e( 'Category', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Business', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Merchant', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Date', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Bank Account', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Receipt', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Comments', 'el-core' ); ?></th>
                <th class="el-bk-col-actions"><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $transactions as $i => $t ) :
                $row_class = match ( $t->status ) {
                    'classified' => 'el-bk-row--classified',
                    'suggested'  => 'el-bk-row--suggested',
                    'rejected'   => 'el-bk-row--rejected',
                    default      => '',
                };
                $travel_badge  = $t->travel_period_id ? ' ✈' : '';
                $receipt_badge = $t->receipt_id       ? ' 📎' : '';
            ?>
            <tr class="el-bk-transaction-row <?php echo esc_attr( $row_class ); ?>"
                data-id="<?php echo esc_attr( $t->id ); ?>"
                data-merchant="<?php echo esc_attr( strtolower( $t->merchant ) ); ?>"
                data-business="<?php echo esc_attr( strtolower( $t->business ?? '' ) ); ?>"
                data-comments="<?php echo esc_attr( strtolower( $t->comments ?? '' ) ); ?>"
                data-category="<?php echo esc_attr( strtolower( $t->category ?? '' ) ); ?>"
                data-bank="<?php echo esc_attr( $t->bank_account ?? '' ); ?>"
                data-date="<?php echo esc_attr( $t->date ); ?>"
                data-status="<?php echo esc_attr( $t->status ); ?>"
            >
                <td><?php echo esc_html( $i + 1 ); ?></td>
                <td>
                    <select class="el-bk-inline-select" data-field="category" data-id="<?php echo esc_attr( $t->id ); ?>">
                        <option value=""><?php esc_html_e( '— Unclassified —', 'el-core' ); ?></option>
                        <?php foreach ( $categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $t->category, $cat ); ?>>
                                <?php echo esc_html( $cat ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( $t->status === 'classified' ) echo '<span class="el-bk-lock-badge" title="' . esc_attr__( 'Locked — won\'t change on Re-Classify', 'el-core' ) . '">🔒</span>'; ?>
                    <?php if ( $travel_badge ) echo '<span title="' . esc_attr__( 'Travel period', 'el-core' ) . '">✈</span>'; ?>
                </td>
                <td><?php echo esc_html( $t->business ); ?></td>
                <td class="el-bk-amount">$<?php echo esc_html( number_format( (float) $t->amount, 2 ) ); ?></td>
                <td><?php echo esc_html( $t->merchant ); ?></td>
                <td><?php echo esc_html( $t->date ); ?></td>
                <td><?php echo esc_html( $t->bank_account ); ?></td>
                <td><?php echo esc_html( $receipt_badge ?: '—' ); ?></td>
                <td>
                    <input type="text" class="el-bk-inline-input" data-field="comments" data-id="<?php echo esc_attr( $t->id ); ?>"
                        value="<?php echo esc_attr( $t->comments ); ?>" placeholder="<?php esc_attr_e( 'Add note…', 'el-core' ); ?>">
                </td>
                <td class="el-bk-col-actions">
                    <?php if ( in_array( $t->status, [ 'suggested', 'classified' ], true ) ) : ?>
                        <button class="el-bk-reject-btn" data-id="<?php echo esc_attr( $t->id ); ?>" title="<?php esc_attr_e( 'Reject — clear category and mark rejected', 'el-core' ); ?>">✕</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="el-bk-total-row">
                <td colspan="3"><strong><?php esc_html_e( 'Total', 'el-core' ); ?></strong></td>
                <td class="el-bk-amount"><strong>$<?php echo esc_html( number_format( $total_all, 2 ) ); ?></strong></td>
                <td colspan="6"></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php endif; ?>

<!-- Ledger Tab Import Modal -->
<div id="el-bk-ledger-modal" class="el-bk-modal" style="display:none;">
    <div class="el-bk-modal-backdrop"></div>
    <div class="el-bk-modal-content" style="max-width:560px;">
        <h3><?php esc_html_e( 'Import Ledger Tab (Single Category)', 'el-core' ); ?></h3>
        <p class="description"><?php esc_html_e( 'Upload a CSV that represents one expense category. Each row is a transaction. You\'ll pick the category and map the columns.', 'el-core' ); ?></p>

        <!-- Step 1: File upload -->
        <div id="el-bk-ledger-step1">
            <label><strong><?php esc_html_e( 'CSV File', 'el-core' ); ?></strong></label>
            <input type="file" id="el-bk-ledger-file" accept=".csv" style="display:block; margin:8px 0 16px;">
            <button class="el-btn el-btn-primary" id="el-bk-ledger-upload-btn"><?php esc_html_e( 'Upload & Detect Columns', 'el-core' ); ?></button>
            <button class="el-btn el-btn-outline el-bk-ledger-cancel"><?php esc_html_e( 'Cancel', 'el-core' ); ?></button>
            <div id="el-bk-ledger-status" style="margin-top:10px;"></div>
        </div>

        <!-- Step 2: Column mapping + category -->
        <div id="el-bk-ledger-step2" style="display:none;">
            <table class="widefat" style="margin-bottom:12px;">
                <tr>
                    <td><strong><?php esc_html_e( 'Category', 'el-core' ); ?></strong></td>
                    <td><select id="el-bk-ledger-category"></select></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Date Column', 'el-core' ); ?></strong></td>
                    <td><select id="el-bk-ledger-date-col"></select></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Description Column', 'el-core' ); ?></strong></td>
                    <td><select id="el-bk-ledger-merchant-col"></select></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Amount Column', 'el-core' ); ?></strong></td>
                    <td><select id="el-bk-ledger-amount-col"></select></td>
                </tr>
            </table>
            <button class="el-btn el-btn-primary" id="el-bk-ledger-import-btn"><?php esc_html_e( 'Import Transactions', 'el-core' ); ?></button>
            <button class="el-btn el-btn-outline el-bk-ledger-cancel"><?php esc_html_e( 'Cancel', 'el-core' ); ?></button>
            <div id="el-bk-ledger-result" style="margin-top:10px;"></div>
        </div>
    </div>
</div>
