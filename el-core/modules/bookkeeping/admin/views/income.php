<?php
/**
 * Bookkeeping — Income & Deposits Tab
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$tax_year     = $module->get_tax_year();
$transactions = $module->get_transactions( [ 'type' => 'income', 'tax_year' => $tax_year ] );
$categories   = EL_Bookkeeping_Module::get_income_categories();

$total = array_sum( array_map( fn( $t ) => (float) $t->amount, $transactions ) );

// Excluded from tax total
$excluded = [ 'Other', 'Bank Transfer', 'Ignore' ];
$taxable  = array_filter( $transactions, fn( $t ) => ! in_array( $t->category, $excluded, true ) );
$taxable_total = array_sum( array_map( fn( $t ) => (float) $t->amount, $taxable ) );
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Income & Deposits — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
    <div class="el-bk-tab-header-right">
        <button class="el-btn el-btn-primary el-bk-upload-csv-btn" data-type="income">
            <?php esc_html_e( 'Upload CSV', 'el-core' ); ?>
        </button>
    </div>
</div>

<?php if ( ! empty( $transactions ) ) : ?>
<div class="el-bk-income-total-bar">
    <span class="el-bk-income-total-label"><?php esc_html_e( 'Business Total Income:', 'el-core' ); ?></span>
    <span class="el-bk-income-total-amount">$<?php echo esc_html( number_format( $taxable_total, 2 ) ); ?></span>
</div>
<?php endif; ?>

<?php echo EL_Admin_UI::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    __( 'Transactions marked Other, Bank Transfer, and Ignore have no effect on your taxes.', 'el-core' ),
    'info'
); ?>

<?php echo EL_Admin_UI::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    __( 'CSV upload and manual entry will be available in Phase 8.', 'el-core' ),
    'info'
); ?>

<?php if ( empty( $transactions ) ) : ?>
    <?php echo EL_Admin_UI::notice( __( 'No income transactions found for this tax year.', 'el-core' ), 'info' ); // phpcs:ignore ?>
<?php else : ?>

<div class="el-bk-table-wrap">
    <table class="el-bk-transactions-table widefat">
        <thead>
            <tr>
                <th>#</th>
                <th><?php esc_html_e( 'Category', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Description', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Date', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Bank Account', 'el-core' ); ?></th>
                <th><?php esc_html_e( 'Comments', 'el-core' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $transactions as $i => $t ) : ?>
            <tr class="el-bk-transaction-row" data-id="<?php echo esc_attr( $t->id ); ?>">
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
                <td><?php echo esc_html( $t->bank_account ); ?></td>
                <td>
                    <input type="text" class="el-bk-inline-input" data-field="comments" data-id="<?php echo esc_attr( $t->id ); ?>"
                        value="<?php echo esc_attr( $t->comments ); ?>" placeholder="<?php esc_attr_e( 'Add note…', 'el-core' ); ?>">
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="el-bk-total-row">
                <td colspan="2"><strong><?php esc_html_e( 'Total', 'el-core' ); ?></strong></td>
                <td class="el-bk-amount"><strong>$<?php echo esc_html( number_format( $total, 2 ) ); ?></strong></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php endif; ?>
