<?php
/**
 * Bookkeeping — Income & Deposits Tab
 *
 * @var EL_Bookkeeping_Module $module
 * @var int                   $tax_year
 * @var array                 $prefetch_income
 * @var array                 $prefetch_clients
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$transactions = $prefetch_income;
$categories   = EL_Bookkeeping_Module::get_income_categories();
$bank_accounts = EL_Bookkeeping_Module::get_bank_accounts();
$exp_cat_grouped = EL_Bookkeeping_Module::get_expense_categories_grouped();

$excluded      = [ 'Other', 'Bank Transfer', 'Ignore', 'Refund', 'Travel Credit' ];
$taxable       = array_filter( $transactions, fn( $t ) => ! in_array( $t->category, $excluded, true ) );
$total_all     = array_sum( array_map( fn( $t ) => (float) $t->amount, $transactions ) );
$total_taxable = array_sum( array_map( fn( $t ) => (float) $t->amount, $taxable ) );
$business_name = $module->get_business_name();

// Audit panel data.
$excluded_txns   = array_filter( $transactions, fn( $t ) => in_array( $t->category, $excluded, true ) );
$client_assigned = array_filter( $transactions, fn( $t ) => ! empty( $t->client_id ) && (int) $t->client_id > 0 );
$count_all       = count( $transactions );
$count_excluded  = count( $excluded_txns );
$count_assigned  = count( $client_assigned );
$total_excluded  = array_sum( array_map( fn( $t ) => (float) $t->amount, $excluded_txns ) );
$total_assigned  = array_sum( array_map( fn( $t ) => (float) $t->amount, $client_assigned ) );

// Build client lookup map: id => display name
$client_map = [];
if ( ! empty( $prefetch_clients ) ) {
    foreach ( $prefetch_clients as $c ) {
        $client_map[ (int) $c->id ] = $c->short_name ?: $c->client_name;
    }
}

// Build map of transaction_id → linked invoice count (for multi-invoice deposits).
$linked_invoice_counts = [];
if ( $transactions ) {
    global $wpdb;
    $txn_ids = implode( ',', array_map( 'intval', array_column( (array) $transactions, 'id' ) ) );
    if ( $txn_ids ) {
        $tbl_inv = $wpdb->prefix . 'el_bk_invoices';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT transaction_id, COUNT(*) AS cnt FROM {$tbl_inv} WHERE transaction_id IN ({$txn_ids}) GROUP BY transaction_id"
        ) ?: [];
        foreach ( $rows as $r ) {
            $linked_invoice_counts[ (int) $r->transaction_id ] = (int) $r->cnt;
        }
    }
}
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Income & Deposits — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
    <div class="el-bk-tab-header-right">
        <button class="el-btn el-btn-outline el-bk-export-btn" data-format="csv" data-type="income">
            <?php esc_html_e( 'Download CSV', 'el-core' ); ?>
        </button>
        <button class="el-btn el-btn-danger el-bk-clear-income-btn" data-tax-year="<?php echo esc_attr( $tax_year ); ?>">
            <?php esc_html_e( 'Clear All Income', 'el-core' ); ?>
        </button>
        <button class="el-btn el-btn-primary el-bk-upload-csv-btn">
            <?php esc_html_e( 'Upload Bank Statement', 'el-core' ); ?>
        </button>
    </div>
</div>

<div class="el-bk-income-header-bar">
    <div class="el-bk-income-notices">
        <?php echo EL_Admin_UI::notice( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'message' => __( 'Transactions marked "Other", "Bank Transfer", and "Ignore" will have no effect on your business books or your taxes. As long as it is not income it doesn\'t matter.', 'el-core' ),
            'type'    => 'info',
        ] ); ?>
        <?php echo EL_Admin_UI::notice( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'message' => __( 'The Total Income on the right of this sheet should equal your declared business total income. Please include all business income.', 'el-core' ),
            'type'    => 'info',
        ] ); ?>
    </div>
    <div class="el-bk-income-total-card">
        <div class="el-bk-income-total-card-label">
            <?php echo esc_html( $business_name ); ?><br>
            <?php esc_html_e( 'Income:', 'el-core' ); ?>
        </div>
        <div class="el-bk-income-total-card-amount">
            $<?php echo esc_html( number_format( $total_taxable, 2 ) ); ?>
        </div>
    </div>
</div>

<div class="el-bk-income-audit-panel">
    <h4><?php echo esc_html( sprintf( __( 'Deposits Audit — %d', 'el-core' ), $tax_year ) ); ?></h4>
    <div class="el-bk-income-audit-grid">
        <div class="el-bk-income-audit-cell">
            <div class="el-bk-income-audit-label"><?php esc_html_e( 'Total Deposits Imported', 'el-core' ); ?></div>
            <div class="el-bk-income-audit-count"><?php echo esc_html( $count_all ); ?> <?php esc_html_e( 'deposits', 'el-core' ); ?></div>
            <div class="el-bk-income-audit-amount">$<?php echo esc_html( number_format( $total_all, 2 ) ); ?></div>
        </div>
        <div class="el-bk-income-audit-cell">
            <div class="el-bk-income-audit-label"><?php esc_html_e( 'Matched to Client', 'el-core' ); ?></div>
            <div class="el-bk-income-audit-count"><?php echo esc_html( $count_assigned ); ?> <?php esc_html_e( 'deposits', 'el-core' ); ?></div>
            <div class="el-bk-income-audit-amount">$<?php echo esc_html( number_format( $total_assigned, 2 ) ); ?></div>
        </div>
        <div class="el-bk-income-audit-cell el-bk-income-audit-cell--excluded">
            <div class="el-bk-income-audit-label"><?php esc_html_e( 'Excluded (Bank Transfer / Ignore / etc.)', 'el-core' ); ?></div>
            <div class="el-bk-income-audit-count"><?php echo esc_html( $count_excluded ); ?> <?php esc_html_e( 'deposits', 'el-core' ); ?></div>
            <div class="el-bk-income-audit-amount">$<?php echo esc_html( number_format( $total_excluded, 2 ) ); ?></div>
        </div>
        <div class="el-bk-income-audit-cell el-bk-income-audit-cell--taxable">
            <div class="el-bk-income-audit-label"><?php esc_html_e( 'Net Taxable Income', 'el-core' ); ?></div>
            <div class="el-bk-income-audit-count"><?php echo esc_html( count( $taxable ) ); ?> <?php esc_html_e( 'deposits', 'el-core' ); ?></div>
            <div class="el-bk-income-audit-amount el-bk-income-audit-amount--highlight">$<?php echo esc_html( number_format( $total_taxable, 2 ) ); ?></div>
        </div>
    </div>
</div>

<?php if ( ! empty( $client_map ) ) : ?>
<div class="el-bk-income-summary-widget">
    <h4><?php echo esc_html( sprintf( __( 'Income Reconciliation: %d', 'el-core' ), $tax_year ) ); ?></h4>
    <div class="el-bk-income-summary-row">
        <span><?php esc_html_e( 'Clients Reconciled:', 'el-core' ); ?> <strong id="el-bk-income-reconciled-count"><?php esc_html_e( '…', 'el-core' ); ?></strong></span>
        <div class="el-bk-income-progress-bar">
            <div class="el-bk-income-progress-fill" id="el-bk-income-progress-bar" style="width:0%"></div>
        </div>
    </div>
    <div class="el-bk-income-summary-row">
        <span><?php esc_html_e( 'Unassigned Deposits:', 'el-core' ); ?> <strong id="el-bk-income-unassigned"><?php esc_html_e( '…', 'el-core' ); ?></strong></span>
        <a href="?page=els-bookkeeping&tab=clients"><?php esc_html_e( 'View All Clients →', 'el-core' ); ?></a>
    </div>
</div>
<?php endif; ?>

<div class="el-bk-filter-bar">
    <div class="el-bk-filter-bar-row">
        <div class="el-bk-filter-field">
            <label for="el-bk-inc-search"><?php esc_html_e( 'Search', 'el-core' ); ?></label>
            <input type="text" id="el-bk-inc-search" placeholder="<?php esc_attr_e( 'Merchant / description…', 'el-core' ); ?>">
        </div>
        <div class="el-bk-filter-field">
            <label for="el-bk-inc-cat-filter"><?php esc_html_e( 'Category', 'el-core' ); ?></label>
            <select id="el-bk-inc-cat-filter">
                <option value=""><?php esc_html_e( '— All Categories —', 'el-core' ); ?></option>
                <option value="__unclassified__"><?php esc_html_e( '— Unclassified —', 'el-core' ); ?></option>
                <?php foreach ( $categories as $cat ) : ?>
                    <option value="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="el-bk-filter-field">
            <label for="el-bk-inc-from"><?php esc_html_e( 'From', 'el-core' ); ?></label>
            <input type="date" id="el-bk-inc-from" value="<?php echo esc_attr( $tax_year . '-01-01' ); ?>">
        </div>
        <div class="el-bk-filter-field">
            <label for="el-bk-inc-to"><?php esc_html_e( 'To', 'el-core' ); ?></label>
            <input type="date" id="el-bk-inc-to" value="<?php echo esc_attr( $tax_year . '-12-31' ); ?>">
        </div>
        <div class="el-bk-filter-field el-bk-filter-actions">
            <button class="el-btn el-btn-outline" id="el-bk-inc-filter-btn"><?php esc_html_e( 'Filter', 'el-core' ); ?></button>
            <button class="el-btn el-btn-outline" id="el-bk-inc-clear-filters"><?php esc_html_e( 'Clear', 'el-core' ); ?></button>
        </div>
        <div class="el-bk-filter-field el-bk-filter-count">
            <span id="el-bk-inc-filter-count"></span>
        </div>
    </div>
</div>

<?php if ( empty( $transactions ) ) : ?>
    <?php echo EL_Admin_UI::notice( [ 'message' => __( 'No income transactions found for this tax year. Upload a CSV to get started.', 'el-core' ), 'type' => 'info' ] ); // phpcs:ignore ?>
<?php else : ?>

<div class="el-bk-table-wrap">
    <table class="el-bk-transactions-table widefat" id="el-bk-inc-table">
        <thead>
            <tr>
                <th>#</th>
                <th id="el-bk-inc-cat-th" style="cursor:pointer;user-select:none;" title="Click to sort by category">
                    <?php esc_html_e( 'Category', 'el-core' ); ?> <span id="el-bk-inc-cat-sort-icon" style="font-size:11px;"></span>
                </th>
                <th><?php esc_html_e( 'Client', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
                <th id="el-bk-inc-merchant-th" style="cursor:pointer;user-select:none;" title="Click to sort by merchant">
                    <?php esc_html_e( 'Merchant / Description', 'el-core' ); ?> <span id="el-bk-inc-merchant-sort-icon" style="font-size:11px;"></span>
                </th>
                <th id="el-bk-inc-date-th" style="cursor:pointer;user-select:none;" title="Click to sort by date">
                    <?php esc_html_e( 'Date', 'el-core' ); ?> <span id="el-bk-inc-date-sort-icon" style="font-size:11px;">▼</span>
                </th>
                <th><?php esc_html_e( 'Bank Account', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Comments', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Invoice', 'el-core' ); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $transactions as $i => $t ) :
                $assigned_client_id   = (int) ( $t->client_id ?? 0 );
                $assigned_client_name = $assigned_client_id ? ( $client_map[ $assigned_client_id ] ?? __( 'Unknown', 'el-core' ) ) : '';
            ?>
            <tr class="el-bk-transaction-row el-bk-row--classified" data-id="<?php echo esc_attr( $t->id ); ?>" data-date="<?php echo esc_attr( $t->date ); ?>" data-merchant="<?php echo esc_attr( strtolower( $t->merchant ?? '' ) ); ?>" data-category="<?php echo esc_attr( strtolower( $t->category ?? '' ) ); ?>">
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
                </td>
                <td class="el-bk-client-cell">
                    <?php if ( $assigned_client_id && $assigned_client_name ) : ?>
                        <span class="el-bk-client-badge">
                            <?php echo esc_html( $assigned_client_name ); ?>
                            <button class="el-bk-unassign-client" data-transaction-id="<?php echo esc_attr( $t->id ); ?>">×</button>
                        </span>
                    <?php elseif ( ! empty( $prefetch_clients ) ) : ?>
                        <select class="el-bk-assign-client-select" data-transaction-id="<?php echo esc_attr( $t->id ); ?>">
                            <option value=""><?php esc_html_e( '— Assign —', 'el-core' ); ?></option>
                            <?php foreach ( $prefetch_clients as $c ) : ?>
                                <option value="<?php echo esc_attr( $c->id ); ?>">
                                    <?php echo esc_html( $c->short_name ?: $c->client_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </td>
                <td class="el-bk-amount">$<?php echo esc_html( number_format( (float) $t->amount, 2 ) ); ?></td>
                <td><?php echo esc_html( $t->merchant ); ?></td>
                <td><?php echo esc_html( $t->date ); ?></td>
                <td>
                    <select class="el-bk-inline-select" data-field="bank_account" data-id="<?php echo esc_attr( $t->id ); ?>">
                        <option value=""><?php esc_html_e( '— Select —', 'el-core' ); ?></option>
                        <?php foreach ( $bank_accounts as $acct ) : ?>
                            <option value="<?php echo esc_attr( $acct ); ?>" <?php selected( $t->bank_account, $acct ); ?>>
                                <?php echo esc_html( $acct ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="text" class="el-bk-inline-input" data-field="comments" data-id="<?php echo esc_attr( $t->id ); ?>"
                        value="<?php echo esc_attr( $t->comments ); ?>" placeholder="<?php esc_attr_e( 'Add note…', 'el-core' ); ?>">
                </td>
                <td>
                    <?php
                    $linked_count = $linked_invoice_counts[ (int) $t->id ] ?? 0;
                    if ( $linked_count > 0 ) : ?>
                        <span class="el-bk-invoice-linked-badge">
                            <?php echo esc_html( $linked_count === 1
                                ? __( 'Invoice Linked', 'el-core' )
                                : sprintf( __( '%d Invoices Linked', 'el-core' ), $linked_count )
                            ); ?>
                        </span>
                        <button class="el-btn el-btn-outline el-btn-sm el-bk-link-invoice-btn"
                            data-transaction-id="<?php echo esc_attr( $t->id ); ?>"
                            style="margin-top:4px;">
                            <?php esc_html_e( '+ Link Another', 'el-core' ); ?>
                        </button>
                    <?php else : ?>
                        <button class="el-btn el-btn-outline el-btn-sm el-bk-link-invoice-btn"
                            data-transaction-id="<?php echo esc_attr( $t->id ); ?>">
                            <?php esc_html_e( 'Link Invoice', 'el-core' ); ?>
                        </button>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;white-space:nowrap;">
                    <button class="el-bk-convert-refund-btn"
                        data-id="<?php echo esc_attr( $t->id ); ?>"
                        data-merchant="<?php echo esc_attr( $t->merchant ); ?>"
                        data-amount="<?php echo esc_attr( number_format( (float) $t->amount, 2 ) ); ?>"
                        data-date="<?php echo esc_attr( $t->date ); ?>"
                        title="<?php esc_attr_e( 'Convert to Expense Offset — creates a negative expense entry and marks this deposit as Refund', 'el-core' ); ?>"
                        style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 5px;color:#7c3aed;opacity:0.7;line-height:1;">↩</button>
                    <button class="el-bk-delete-income-btn"
                        data-id="<?php echo esc_attr( $t->id ); ?>"
                        data-merchant="<?php echo esc_attr( $t->merchant ); ?>"
                        title="<?php esc_attr_e( 'Delete this deposit permanently', 'el-core' ); ?>"
                        style="background:none;border:none;cursor:pointer;font-size:14px;padding:2px 5px;color:#dc2626;opacity:0.6;line-height:1;">&#128465;</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="el-bk-total-row">
                <td colspan="3"><strong id="el-bk-inc-total-label"><?php esc_html_e( 'Total', 'el-core' ); ?></strong></td>
                <td class="el-bk-amount" id="el-bk-inc-total-cell"><strong>$<?php echo esc_html( number_format( $total_all, 2 ) ); ?></strong></td>
                <td colspan="6"></td>
    </table>
</div>

<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     INVOICE-DEPOSIT MATCH MODAL (Phase A.9)
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="el-bk-match-modal" class="el-bk-modal" style="display:none;">
    <div class="el-bk-modal-backdrop"></div>
    <div class="el-bk-modal-content">
        <div class="el-bk-modal-header">
            <h3 id="el-bk-match-modal-title"><?php esc_html_e( 'Match Invoice to Deposit', 'el-core' ); ?></h3>
            <button class="el-bk-modal-close">×</button>
        </div>
        <div class="el-bk-modal-body">
            <div id="el-bk-match-source" class="el-bk-match-source"></div>
            <div class="el-bk-match-search-row">
                <input type="text" id="el-bk-match-search" class="el-input"
                    placeholder="<?php esc_attr_e( 'Search by amount, date, or description…', 'el-core' ); ?>">
            </div>
            <div id="el-bk-match-suggestions" class="el-bk-match-suggestions">
                <p class="el-bk-loading"><?php esc_html_e( 'Loading suggestions…', 'el-core' ); ?></p>
            </div>
            <div id="el-bk-match-withholding" class="el-bk-match-withholding" style="display:none;">
                <h4><?php esc_html_e( 'Tax Withholding', 'el-core' ); ?></h4>
                <p class="el-bk-hint"><?php esc_html_e( 'The deposit is less than the invoice. Enter withholding amount if applicable.', 'el-core' ); ?></p>
                <div class="el-bk-match-withholding-row">
                    <label>
                        <?php esc_html_e( 'Withholding Amount ($)', 'el-core' ); ?>
                        <input type="text" id="el-bk-match-withholding-amount" class="el-input"
                            placeholder="0.00" inputmode="decimal">
                    </label>
                    <label>
                        <?php esc_html_e( 'Type', 'el-core' ); ?>
                        <select id="el-bk-match-withholding-type" class="el-select">
                            <option value=""><?php esc_html_e( '— None —', 'el-core' ); ?></option>
                            <option value="CA Withholding" selected><?php esc_html_e( 'CA Withholding (7%)', 'el-core' ); ?></option>
                            <option value="Federal"><?php esc_html_e( 'Federal', 'el-core' ); ?></option>
                            <option value="Other"><?php esc_html_e( 'Other', 'el-core' ); ?></option>
                        </select>
                    </label>
                </div>
                <p id="el-bk-match-calculation" class="el-bk-match-calculation"></p>
            </div>
        </div>
        <div class="el-bk-modal-footer">
            <button class="el-btn el-btn-primary" id="el-bk-match-confirm-btn" disabled>
                <?php esc_html_e( 'Confirm Match', 'el-core' ); ?>
            </button>
            <button class="el-btn el-btn-outline el-bk-modal-cancel"><?php esc_html_e( 'Cancel', 'el-core' ); ?></button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     REFUND OFFSET MODAL
     ═══════════════════════════════════════════════════════════════════════════ -->
<div id="el-bk-refund-offset-modal" class="el-bk-modal" style="display:none;">
    <div class="el-bk-modal-backdrop"></div>
    <div class="el-bk-modal-content" style="max-width:480px;">
        <div class="el-bk-modal-header">
            <h3><?php esc_html_e( 'Convert to Expense Offset', 'el-core' ); ?></h3>
            <button class="el-bk-modal-close">×</button>
        </div>
        <div class="el-bk-modal-body">
            <div id="el-bk-ro-info"
                style="background:#f3f4f6;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px;line-height:1.6;">
            </div>
            <p style="font-size:13px;color:#6b7280;margin:0 0 14px;">
                <?php esc_html_e( 'This will create a negative expense entry for this amount and mark the deposit as Refund (excluded from taxable income).', 'el-core' ); ?>
            </p>
            <label style="font-size:13px;font-weight:500;display:block;margin-bottom:12px;">
                <?php esc_html_e( 'Expense Category', 'el-core' ); ?>
                <select id="el-bk-ro-category" class="el-select"
                    style="display:block;width:100%;margin-top:5px;">
                    <optgroup label="<?php esc_attr_e( 'Business', 'el-core' ); ?>">
                        <?php foreach ( $exp_cat_grouped['business'] as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>"
                                <?php selected( $cat, 'Travel Expense' ); ?>>
                                <?php echo esc_html( $cat ); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="<?php esc_attr_e( 'Personal', 'el-core' ); ?>">
                        <?php foreach ( $exp_cat_grouped['personal'] as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>">
                                <?php echo esc_html( $cat ); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </label>
            <label style="font-size:13px;font-weight:500;display:block;">
                <?php esc_html_e( 'Comments / Notes', 'el-core' ); ?>
                <input type="text" id="el-bk-ro-comments" class="el-input el-bk-voice-input"
                    style="display:block;width:100%;margin-top:5px;box-sizing:border-box;"
                    placeholder="<?php esc_attr_e( 'e.g. Refund offset — cancelled flight', 'el-core' ); ?>">
            </label>
            <div id="el-bk-ro-status" style="min-height:18px;font-size:13px;margin-top:10px;"></div>
        </div>
        <div class="el-bk-modal-footer">
            <button class="el-btn el-btn-primary" id="el-bk-ro-confirm-btn">
                <?php esc_html_e( 'Create Offset', 'el-core' ); ?>
            </button>
            <button class="el-btn el-btn-outline el-bk-modal-cancel"><?php esc_html_e( 'Cancel', 'el-core' ); ?></button>
        </div>
    </div>
</div>
