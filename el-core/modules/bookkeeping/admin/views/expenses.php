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
$cat_grouped   = EL_Bookkeeping_Module::get_expense_categories_grouped();
$predefined_accounts = EL_Bookkeeping_Module::get_bank_accounts();
$db_accounts   = array_unique( array_filter( array_map( fn( $t ) => $t->bank_account ?? '', $transactions ) ) );
$bank_accounts = array_unique( array_merge( $predefined_accounts, $db_accounts ) );
sort( $bank_accounts );

// Separate split children from top-level rows
$children_map = [];
$top_level    = [];
foreach ( $transactions as $t ) {
    if ( ! empty( $t->parent_id ) ) {
        $children_map[ (int) $t->parent_id ][] = $t;
    } else {
        $top_level[] = $t;
    }
}

// ── Build category totals for summary bar ────────────────────────────────────
$category_totals    = [];
$total_classified   = 0.0;
$total_business     = 0.0;
$total_personal     = 0.0;
foreach ( $transactions as $t ) {
    if ( ! empty( $t->category ) ) {
        $category_totals[ $t->category ] = ( $category_totals[ $t->category ] ?? 0.0 ) + (float) $t->amount;
        $total_classified += (float) $t->amount;
        if ( EL_Bookkeeping_Module::get_category_type( $t->category ) === 'personal' ) {
            $total_personal += (float) $t->amount;
        } else {
            $total_business += (float) $t->amount;
        }
    }
}
arsort( $category_totals );

$total_all = array_sum( array_map( fn( $t ) => $t->status === 'split' ? 0.0 : (float) $t->amount, $transactions ) );

// Build a map of receipt data for all transactions that have one attached
$_receipt_ids = array_values( array_unique( array_filter( array_map(
    fn( $t ) => (int) ( $t->receipt_id ?? 0 ),
    $transactions
) ) ) );
$_receipt_map = [];
if ( ! empty( $_receipt_ids ) ) {
    global $wpdb;
    $_ids_in  = implode( ',', $_receipt_ids );
    $_rcpts   = $wpdb->get_results(
        "SELECT id, ai_extracted_merchant, ai_extracted_date, ai_extracted_amount,
                ai_extracted_category, location, file_url, file_type
         FROM {$wpdb->prefix}el_bk_receipts WHERE id IN ($_ids_in)"
    );
    foreach ( $_rcpts as $_r ) {
        $_receipt_map[ (int) $_r->id ] = $_r;
    }
}
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
        <button class="el-btn el-btn-danger el-bk-clear-expenses-btn" data-tax-year="<?php echo esc_attr( $tax_year ); ?>">
            <?php esc_html_e( 'Clear All Expenses', 'el-core' ); ?>
        </button>
        <button class="el-btn el-btn-outline" id="el-bk-lock-period-btn">
            <?php esc_html_e( '🔒 Lock Period', 'el-core' ); ?>
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
            <?php echo esc_html( sprintf( __( 'Business Expenses: $%s', 'el-core' ), number_format( $total_business, 2 ) ) ); ?>
            &nbsp;|&nbsp;
            <?php echo esc_html( sprintf( __( 'Personal Expenses: $%s', 'el-core' ), number_format( $total_personal, 2 ) ) ); ?>
            &nbsp;|&nbsp;
            <?php echo esc_html( sprintf( __( 'Total: $%s', 'el-core' ), number_format( $total_classified, 2 ) ) ); ?>
        </span>
    </div>
    <div class="el-bk-summary-grid">
        <?php foreach ( $category_totals as $cat => $amount ) :
            $cat_type = EL_Bookkeeping_Module::get_category_type( $cat );
        ?>
        <div class="el-bk-summary-item">
            <span class="el-bk-summary-item-label">
                <span class="el-bk-type-badge el-bk-type-badge--<?php echo esc_attr( $cat_type ); ?>"><?php echo $cat_type === 'business' ? 'B' : 'P'; ?></span>
                <?php echo esc_html( $cat ); ?>:
            </span>
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
    <button class="el-btn el-btn-outline" id="el-bk-reclassify-range-btn">
        <?php esc_html_e( 'Re-Classify Range…', 'el-core' ); ?>
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
        <div class="el-bk-filter-field">
            <label for="el-bk-exp-type-filter"><?php esc_html_e( 'Expense Type', 'el-core' ); ?></label>
            <select id="el-bk-exp-type-filter">
                <option value=""><?php esc_html_e( '— All —', 'el-core' ); ?></option>
                <option value="business"><?php esc_html_e( 'Business', 'el-core' ); ?></option>
                <option value="personal"><?php esc_html_e( 'Personal', 'el-core' ); ?></option>
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
                <th id="el-bk-exp-date-th" style="cursor:pointer;user-select:none;" title="Click to toggle sort order">
                    <?php esc_html_e( 'Date', 'el-core' ); ?> <span id="el-bk-exp-date-sort-icon">▼</span>
                </th>
                <th><?php esc_html_e( 'Bank Account', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Receipt', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Comments', 'el-core' ); ?></th>
                <th class="el-bk-col-actions"><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $top_level as $i => $t ) :
                $row_class = match ( $t->status ) {
                    'classified' => 'el-bk-row--classified',
                    'suggested'  => 'el-bk-row--suggested',
                    'rejected'   => 'el-bk-row--rejected',
                    'split'      => 'el-bk-row--split',
                    default      => '',
                };
                $travel_badge  = $t->travel_period_id ? ' ✈' : '';
                $receipt_badge = $t->receipt_id       ? ' 📎' : '';
                $expense_type  = ! empty( $t->category ) ? EL_Bookkeeping_Module::get_category_type( $t->category ) : '';
                $is_split      = $t->status === 'split';
            ?>
            <tr class="el-bk-transaction-row <?php echo esc_attr( $row_class ); ?>"
                data-id="<?php echo esc_attr( $t->id ); ?>"
                data-receipt-id="<?php echo esc_attr( $t->receipt_id ?? 0 ); ?>"
                data-merchant="<?php echo esc_attr( strtolower( $t->merchant ) ); ?>"
                data-merchant-raw="<?php echo esc_attr( $t->merchant ); ?>"
                data-business="<?php echo esc_attr( strtolower( $t->business ?? '' ) ); ?>"
                data-comments="<?php echo esc_attr( strtolower( $t->comments ?? '' ) ); ?>"
                data-category="<?php echo esc_attr( strtolower( $t->category ?? '' ) ); ?>"
                data-bank="<?php echo esc_attr( $t->bank_account ?? '' ); ?>"
                data-date="<?php echo esc_attr( $t->date ); ?>"
                data-status="<?php echo esc_attr( $t->status ); ?>"
                data-expense-type="<?php echo esc_attr( $expense_type ); ?>"
                data-amount="<?php echo esc_attr( $t->amount ); ?>"
            >
                <td><?php echo esc_html( $i + 1 ); ?></td>
                <td>
                    <?php if ( $is_split ) : ?>
                        <span class="el-bk-split-badge">
                            <?php esc_html_e( 'Split', 'el-core' ); ?>
                            <button class="el-bk-unsplit-btn"
                                data-id="<?php echo esc_attr( $t->id ); ?>"
                                title="<?php esc_attr_e( 'Remove split — restore to single transaction', 'el-core' ); ?>">×</button>
                        </span>
                    <?php else : ?>
                        <select class="el-bk-inline-select" data-field="category" data-id="<?php echo esc_attr( $t->id ); ?>">
                            <option value=""><?php esc_html_e( '— Unclassified —', 'el-core' ); ?></option>
                            <optgroup label="<?php esc_attr_e( 'Business', 'el-core' ); ?>">
                                <?php foreach ( $cat_grouped['business'] as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $t->category, $cat ); ?>>
                                        <?php echo esc_html( $cat ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e( 'Personal', 'el-core' ); ?>">
                                <?php foreach ( $cat_grouped['personal'] as $cat ) : ?>
                                    <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $t->category, $cat ); ?>>
                                        <?php echo esc_html( $cat ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <?php if ( $t->status === 'classified' ) echo '<span class="el-bk-lock-badge" title="' . esc_attr__( 'Locked — won\'t change on Re-Classify', 'el-core' ) . '">🔒</span>'; ?>
                        <?php if ( $travel_badge ) echo '<span title="' . esc_attr__( 'Travel period', 'el-core' ) . '">✈</span>'; ?>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( $t->business ); ?></td>
                <td class="el-bk-amount">$<?php echo esc_html( number_format( (float) $t->amount, 2 ) ); ?></td>
                <td><?php echo esc_html( $t->merchant ); ?></td>
                <td><?php echo esc_html( $t->date ); ?></td>
                <td>
                    <select class="el-bk-inline-select" data-field="bank_account" data-id="<?php echo esc_attr( $t->id ); ?>">
                        <option value=""><?php esc_html_e( '— Select —', 'el-core' ); ?></option>
                        <?php foreach ( $predefined_accounts as $acct ) : ?>
                            <option value="<?php echo esc_attr( $acct ); ?>" <?php selected( $t->bank_account, $acct ); ?>>
                                <?php echo esc_html( $acct ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="el-bk-receipt-col">
                    <?php if ( $t->receipt_id ) : ?>
                        <button class="el-bk-receipt-badge-btn"
                                data-receipt-id="<?php echo esc_attr( $t->receipt_id ); ?>"
                                title="<?php esc_attr_e( 'View attached receipt', 'el-core' ); ?>">📎</button>
                    <?php else : ?>
                        <span class="el-bk-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <input type="text" class="el-bk-inline-input" data-field="comments" data-id="<?php echo esc_attr( $t->id ); ?>"
                        value="<?php echo esc_attr( $t->comments ); ?>" placeholder="<?php esc_attr_e( 'Add note…', 'el-core' ); ?>">
                </td>
                <td class="el-bk-col-actions">
                    <?php if ( ! $is_split ) : ?>
                        <button class="el-bk-split-btn el-btn el-btn-outline"
                            data-id="<?php echo esc_attr( $t->id ); ?>"
                            data-amount="<?php echo esc_attr( $t->amount ); ?>"
                            data-merchant="<?php echo esc_attr( $t->merchant ); ?>"
                            data-date="<?php echo esc_attr( $t->date ); ?>"
                            title="<?php esc_attr_e( 'Split this expense into multiple categories', 'el-core' ); ?>">
                            <?php esc_html_e( 'Split', 'el-core' ); ?>
                        </button>
                        <button class="el-bk-make-rule-btn el-btn el-btn-outline"
                            data-id="<?php echo esc_attr( $t->id ); ?>"
                            data-merchant="<?php echo esc_attr( $t->merchant ); ?>"
                            data-category="<?php echo esc_attr( $t->category ?? '' ); ?>"
                            title="<?php esc_attr_e( 'Create a Known Expense rule from this merchant', 'el-core' ); ?>">
                            <?php esc_html_e( '+ Rule', 'el-core' ); ?>
                        </button>
                        <button class="el-bk-row-lock-btn"
                            data-id="<?php echo esc_attr( $t->id ); ?>"
                            data-locked="<?php echo $t->status === 'classified' ? '1' : '0'; ?>"
                            title="<?php echo $t->status === 'classified' ? esc_attr__( 'Unlock this transaction', 'el-core' ) : esc_attr__( 'Lock this transaction', 'el-core' ); ?>"
                            style="background:none;border:none;cursor:pointer;font-size:15px;padding:2px 4px;opacity:<?php echo $t->status === 'classified' ? '1' : '0.35'; ?>;">
                            <?php echo $t->status === 'classified' ? '🔒' : '🔓'; ?>
                        </button>
                        <?php if ( in_array( $t->status, [ 'suggested', 'classified' ], true ) ) : ?>
                            <button class="el-bk-reject-btn" data-id="<?php echo esc_attr( $t->id ); ?>" title="<?php esc_attr_e( 'Reject — clear category and mark rejected', 'el-core' ); ?>">✕</button>
                        <?php endif; ?>
                    <?php endif; ?>
                    <button class="el-bk-delete-expense-btn"
                        data-id="<?php echo esc_attr( $t->id ); ?>"
                        data-merchant="<?php echo esc_attr( $t->merchant ); ?>"
                        title="<?php esc_attr_e( 'Delete this expense permanently', 'el-core' ); ?>"
                        style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 5px;color:#dc2626;opacity:0.6;line-height:1;">&#128465;</button>
                </td>
            </tr>
            <?php
            // Render split children beneath the parent
            if ( $is_split && ! empty( $children_map[ (int) $t->id ] ) ) :
                foreach ( $children_map[ (int) $t->id ] as $child ) :
                    $child_type = ! empty( $child->category ) ? EL_Bookkeeping_Module::get_category_type( $child->category ) : '';
            ?>
            <tr class="el-bk-split-piece-row"
                data-id="<?php echo esc_attr( $child->id ); ?>"
                data-parent-id="<?php echo esc_attr( $t->id ); ?>"
                data-merchant="<?php echo esc_attr( strtolower( $child->merchant ) ); ?>"
                data-merchant-raw="<?php echo esc_attr( $child->merchant ); ?>"
                data-category="<?php echo esc_attr( strtolower( $child->category ?? '' ) ); ?>"
                data-bank="<?php echo esc_attr( $child->bank_account ?? '' ); ?>"
                data-date="<?php echo esc_attr( $child->date ); ?>"
                data-status="<?php echo esc_attr( $child->status ); ?>"
                data-expense-type="<?php echo esc_attr( $child_type ); ?>"
                data-comments="<?php echo esc_attr( strtolower( $child->comments ?? '' ) ); ?>"
            >
                <td></td>
                <td>
                    <select class="el-bk-inline-select" data-field="category" data-id="<?php echo esc_attr( $child->id ); ?>">
                        <option value=""><?php esc_html_e( '— Unclassified —', 'el-core' ); ?></option>
                        <optgroup label="<?php esc_attr_e( 'Business', 'el-core' ); ?>">
                            <?php foreach ( $cat_grouped['business'] as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $child->category, $cat ); ?>>
                                    <?php echo esc_html( $cat ); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="<?php esc_attr_e( 'Personal', 'el-core' ); ?>">
                            <?php foreach ( $cat_grouped['personal'] as $cat ) : ?>
                                <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $child->category, $cat ); ?>>
                                    <?php echo esc_html( $cat ); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </td>
                <td><?php echo esc_html( $child->business ); ?></td>
                <td class="el-bk-amount">$<?php echo esc_html( number_format( (float) $child->amount, 2 ) ); ?></td>
                <td><?php echo esc_html( $child->merchant ); ?></td>
                <td><?php echo esc_html( $child->date ); ?></td>
                <td><?php echo esc_html( $child->bank_account ); ?></td>
                <td></td>
                <td>
                    <input type="text" class="el-bk-inline-input" data-field="comments" data-id="<?php echo esc_attr( $child->id ); ?>"
                        value="<?php echo esc_attr( $child->comments ); ?>" placeholder="<?php esc_attr_e( 'Add note…', 'el-core' ); ?>">
                </td>
                <td class="el-bk-col-actions"></td>
            </tr>
            <?php endforeach; endif; ?>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="el-bk-total-row">
                <td colspan="3"><strong id="el-bk-exp-total-label"><?php esc_html_e( 'Total', 'el-core' ); ?></strong></td>
                <td class="el-bk-amount" id="el-bk-exp-total-cell"><strong>$<?php echo esc_html( number_format( $total_all, 2 ) ); ?></strong></td>
                <td colspan="6"></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php endif; ?>

<!-- Receipt data for the inline receipt panel (keyed by receipt_id) -->
<script>
var elBkReceiptMap = <?php echo wp_json_encode( $_receipt_map ); ?>;
</script>

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

<!-- ── Make Rule Popover ────────────────────────────────────────────────── --><div id="el-bk-make-rule-popover" style="display:none;position:fixed;z-index:99999;background:#fff;border:1px solid #ddd;border-radius:8px;padding:18px 20px;width:360px;box-shadow:0 6px 24px rgba(0,0,0,.18);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
        <strong style="font-size:14px;"><?php esc_html_e( 'Make a Known Expense Rule', 'el-core' ); ?></strong>
        <button id="el-bk-make-rule-close" style="background:none;border:none;cursor:pointer;font-size:20px;line-height:1;color:#666;">&times;</button>
    </div>
    <div id="el-bk-make-rule-conflict" style="display:none;margin-bottom:12px;padding:9px 12px;background:#fff8e1;border:1px solid #f6c600;border-radius:4px;font-size:12px;color:#7a5c00;"></div>
    <label style="display:block;margin-bottom:10px;font-size:13px;font-weight:500;">
        <?php esc_html_e( 'Keyword (what to match in the merchant name)', 'el-core' ); ?>
        <input type="text" id="el-bk-make-rule-keyword" class="el-input" style="display:block;width:100%;margin-top:5px;box-sizing:border-box;">
    </label>
    <label style="display:block;margin-bottom:14px;font-size:13px;font-weight:500;">
        <?php esc_html_e( 'Category', 'el-core' ); ?>
        <select id="el-bk-make-rule-category" class="el-select" style="display:block;width:100%;margin-top:5px;">
            <option value=""><?php esc_html_e( '— Select —', 'el-core' ); ?></option>
            <optgroup label="<?php esc_attr_e( 'Business', 'el-core' ); ?>">
                <?php foreach ( $cat_grouped['business'] as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="<?php esc_attr_e( 'Personal', 'el-core' ); ?>">
                <?php foreach ( $cat_grouped['personal'] as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </optgroup>
        </select>
    </label>
    <div style="display:flex;gap:8px;">
        <button id="el-bk-make-rule-save" class="el-btn el-btn-primary" style="flex:1;"><?php esc_html_e( 'Save Rule', 'el-core' ); ?></button>
        <button id="el-bk-make-rule-cancel" class="el-btn el-btn-outline"><?php esc_html_e( 'Cancel', 'el-core' ); ?></button>
    </div>
    <input type="hidden" id="el-bk-make-rule-txn-id" value="">
</div>

<!-- ── Split Transaction Modal ───────────────────────────────────────────── -->
<div id="el-bk-split-modal" class="el-bk-modal" style="display:none;">
    <div class="el-bk-modal-backdrop el-bk-split-modal-close"></div>
    <div class="el-bk-modal-content" style="max-width:540px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;" id="el-bk-split-modal-title"><?php esc_html_e( 'Split Expense', 'el-core' ); ?></h3>
            <button class="el-bk-split-modal-close" style="background:none;border:none;cursor:pointer;font-size:22px;line-height:1;color:#666;">&times;</button>
        </div>

        <div id="el-bk-split-info" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:12px 14px;margin-bottom:18px;font-size:13px;"></div>

        <div id="el-bk-split-pieces">
            <!-- piece rows injected by JS -->
        </div>

        <button type="button" id="el-bk-split-add-piece" class="el-btn el-btn-outline" style="margin-bottom:16px;width:100%;">
            <?php esc_html_e( '+ Add Another Piece', 'el-core' ); ?>
        </button>

        <div id="el-bk-split-tally" class="el-bk-split-tally"></div>

        <div style="display:flex;gap:10px;margin-top:16px;">
            <button id="el-bk-split-confirm-btn" class="el-btn el-btn-primary" style="flex:1;" disabled>
                <?php esc_html_e( 'Confirm Split', 'el-core' ); ?>
            </button>
            <button class="el-btn el-btn-outline el-bk-split-modal-close" style="flex:0 0 auto;">
                <?php esc_html_e( 'Cancel', 'el-core' ); ?>
            </button>
        </div>

        <!-- Category options template for JS to clone -->
        <select id="el-bk-split-cat-template" style="display:none;">
            <option value=""><?php esc_html_e( '— Unclassified —', 'el-core' ); ?></option>
            <optgroup label="<?php esc_attr_e( 'Business', 'el-core' ); ?>">
                <?php foreach ( $cat_grouped['business'] as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </optgroup>
            <optgroup label="<?php esc_attr_e( 'Personal', 'el-core' ); ?>">
                <?php foreach ( $cat_grouped['personal'] as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </optgroup>
        </select>
    </div>
</div>

<!-- ── Re-Classify Range Modal ───────────────────────────────────────────── -->
<div id="el-bk-reclassify-range-modal" class="el-bk-modal" style="display:none;">
    <div class="el-bk-modal-backdrop el-bk-reclassify-range-close"></div>
    <div class="el-bk-modal-content" style="max-width:480px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;"><?php esc_html_e( 'Re-Classify a Date Range', 'el-core' ); ?></h3>
            <button class="el-bk-reclassify-range-close" style="background:none;border:none;cursor:pointer;font-size:22px;line-height:1;color:#666;">&times;</button>
        </div>
        <p style="margin:0 0 16px;font-size:13px;color:#555;line-height:1.5;">
            <?php esc_html_e( 'Runs your Known Expense rules against unclassified and suggested transactions within the selected dates only. Locked (🔒) transactions are always skipped.', 'el-core' ); ?>
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
            <label style="font-size:13px;font-weight:500;">
                <?php esc_html_e( 'From Date', 'el-core' ); ?>
                <input type="date" id="el-bk-reclassify-range-from" class="el-input" style="display:block;width:100%;margin-top:5px;box-sizing:border-box;"
                    value="<?php echo esc_attr( $tax_year . '-01-01' ); ?>">
            </label>
            <label style="font-size:13px;font-weight:500;">
                <?php esc_html_e( 'To Date', 'el-core' ); ?>
                <input type="date" id="el-bk-reclassify-range-to" class="el-input" style="display:block;width:100%;margin-top:5px;box-sizing:border-box;"
                    value="<?php echo esc_attr( $tax_year . '-12-31' ); ?>">
            </label>
        </div>
        <div id="el-bk-reclassify-range-result" style="margin-bottom:14px;display:none;padding:10px 14px;border-radius:5px;font-size:13px;"></div>
        <div style="display:flex;gap:10px;">
            <button id="el-bk-reclassify-range-confirm-btn" class="el-btn el-btn-primary" style="flex:1;">
                <?php esc_html_e( 'Re-Classify Range', 'el-core' ); ?>
            </button>
            <button class="el-btn el-btn-outline el-bk-reclassify-range-close" style="flex:0 0 auto;">
                <?php esc_html_e( 'Cancel', 'el-core' ); ?>
            </button>
        </div>
    </div>
</div>

<!-- ── Lock / Unlock Period Modal ──────────────────────────────────────────── -->
<div id="el-bk-lock-period-modal" class="el-bk-modal" style="display:none;">
    <div class="el-bk-modal-backdrop el-bk-lock-period-close"></div>
    <div class="el-bk-modal-content" style="max-width:480px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;"><?php esc_html_e( '🔒 Lock / Unlock Period', 'el-core' ); ?></h3>
            <button class="el-bk-lock-period-close" style="background:none;border:none;cursor:pointer;font-size:22px;line-height:1;color:#666;">&times;</button>
        </div>
        <p style="margin:0 0 16px;font-size:13px;color:#555;line-height:1.5;">
            <strong><?php esc_html_e( 'Lock', 'el-core' ); ?></strong> — <?php esc_html_e( 'marks all expenses in the range as Classified (🔒) so Re-Classify won\'t touch them.', 'el-core' ); ?><br>
            <strong><?php esc_html_e( 'Unlock', 'el-core' ); ?></strong> — <?php esc_html_e( 'removes the lock, restoring classified rows to Suggested (if categorised) so they can be edited or re-run.', 'el-core' ); ?>
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
            <label style="font-size:13px;font-weight:500;">
                <?php esc_html_e( 'From Date', 'el-core' ); ?>
                <input type="date" id="el-bk-lock-from" class="el-input" style="display:block;width:100%;margin-top:5px;box-sizing:border-box;"
                    value="<?php echo esc_attr( $tax_year . '-01-01' ); ?>">
            </label>
            <label style="font-size:13px;font-weight:500;">
                <?php esc_html_e( 'To Date', 'el-core' ); ?>
                <input type="date" id="el-bk-lock-to" class="el-input" style="display:block;width:100%;margin-top:5px;box-sizing:border-box;"
                    value="<?php echo esc_attr( $tax_year . '-12-31' ); ?>">
            </label>
        </div>
        <div id="el-bk-lock-result" style="margin-bottom:14px;display:none;padding:10px 14px;border-radius:5px;font-size:13px;"></div>
        <div style="display:flex;gap:10px;">
            <button id="el-bk-lock-confirm-btn" class="el-btn el-btn-primary" style="flex:1;">
                <?php esc_html_e( '🔒 Lock Period', 'el-core' ); ?>
            </button>
            <button id="el-bk-unlock-confirm-btn" class="el-btn el-btn-outline" style="flex:1;">
                <?php esc_html_e( '🔓 Unlock Period', 'el-core' ); ?>
            </button>
            <button class="el-btn el-btn-outline el-bk-lock-period-close" style="flex:0 0 auto;">
                <?php esc_html_e( 'Close', 'el-core' ); ?>
            </button>
        </div>
    </div>
</div>
