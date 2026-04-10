<?php
/**
 * Bookkeeping — Income & Deposits Tab
 *
 * @var EL_Bookkeeping_Module $module
 * @var int                   $tax_year
 * @var array                 $prefetch_income
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$transactions = $prefetch_income;
$categories   = EL_Bookkeeping_Module::get_income_categories();
$bank_accounts = EL_Bookkeeping_Module::get_bank_accounts();

$excluded      = [ 'Other', 'Bank Transfer', 'Ignore', 'Refund', 'Travel Credit' ];
$taxable       = array_filter( $transactions, fn( $t ) => ! in_array( $t->category, $excluded, true ) );
$total_all     = array_sum( array_map( fn( $t ) => (float) $t->amount, $transactions ) );
$total_taxable = array_sum( array_map( fn( $t ) => (float) $t->amount, $taxable ) );
$business_name = $module->get_business_name();
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Income & Deposits — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
    <div class="el-bk-tab-header-right">
        <button class="el-btn el-btn-outline el-bk-export-btn" data-format="csv" data-type="income">
            <?php esc_html_e( 'Download CSV', 'el-core' ); ?>
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

<div class="el-bk-action-row">
    <div class="el-bk-date-range">
        <label><?php esc_html_e( 'From', 'el-core' ); ?>
            <input type="date" id="el-bk-inc-from" value="<?php echo esc_attr( $tax_year . '-01-01' ); ?>">
        </label>
        <label><?php esc_html_e( 'To', 'el-core' ); ?>
            <input type="date" id="el-bk-inc-to" value="<?php echo esc_attr( $tax_year . '-12-31' ); ?>">
        </label>
        <button class="el-btn el-btn-outline" id="el-bk-inc-filter-btn"><?php esc_html_e( 'Filter', 'el-core' ); ?></button>
    </div>
</div>

<?php if ( empty( $transactions ) ) : ?>
    <?php echo EL_Admin_UI::notice( [ 'message' => __( 'No income transactions found for this tax year. Upload a CSV to get started.', 'el-core' ), 'type' => 'info' ] ); // phpcs:ignore ?>
<?php else : ?>

<div class="el-bk-table-wrap">
    <table class="el-bk-transactions-table widefat">
        <thead>
            <tr>
                <th>#</th>
                <th><?php esc_html_e( 'Category', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Merchant / Description', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Date', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Bank Account', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Comments', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $transactions as $i => $t ) : ?>
            <tr class="el-bk-transaction-row el-bk-row--classified" data-id="<?php echo esc_attr( $t->id ); ?>">
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
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="el-bk-total-row">
                <td colspan="2"><strong><?php esc_html_e( 'Total (all)', 'el-core' ); ?></strong></td>
                <td class="el-bk-amount"><strong>$<?php echo esc_html( number_format( $total_all, 2 ) ); ?></strong></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php endif; ?>
