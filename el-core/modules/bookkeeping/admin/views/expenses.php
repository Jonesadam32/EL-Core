<?php
/**
 * Bookkeeping — Expenses Tab
 *
 * Displays expense transactions for the selected tax year.
 * Phase 2 will add CSV upload, inline editing, and filters.
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$tax_year     = $tax_year ?? $module->get_tax_year();
$transactions = $module->get_transactions( [ 'type' => 'expense', 'tax_year' => $tax_year ] );
$categories   = EL_Bookkeeping_Module::get_expense_categories();
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Expenses — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
    <div class="el-bk-tab-header-right">
        <button class="el-btn el-btn-primary el-bk-upload-csv-btn" data-type="expense">
            <?php esc_html_e( 'Upload CSV', 'el-core' ); ?>
        </button>
    </div>
</div>

<?php echo EL_Admin_UI::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    __( 'CSV upload, inline editing, and auto-classification will be available in Phase 2.', 'el-core' ),
    'info'
); ?>

<?php if ( empty( $transactions ) ) : ?>
    <?php echo EL_Admin_UI::notice( __( 'No expense transactions found for this tax year. Upload a CSV to get started.', 'el-core' ), 'info' ); // phpcs:ignore ?>
<?php else : ?>

<div class="el-bk-bulk-actions">
    <button class="el-btn el-btn-outline el-bk-confirm-all-btn" data-scope="all">
        <?php esc_html_e( 'Confirm All Suggestions', 'el-core' ); ?>
    </button>
    <button class="el-btn el-btn-outline el-bk-confirm-all-btn" data-scope="travel">
        <?php esc_html_e( 'Confirm Travel Suggestions', 'el-core' ); ?>
    </button>
    <button class="el-btn el-btn-outline el-bk-export-btn" data-format="csv">
        <?php esc_html_e( 'Download CSV', 'el-core' ); ?>
    </button>
</div>

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
                $travel_badge  = $t->travel_period_id ? ' ✈️' : '';
                $receipt_badge = $t->receipt_id       ? ' 📎' : '';
            ?>
            <tr class="el-bk-transaction-row <?php echo esc_attr( $row_class ); ?>" data-id="<?php echo esc_attr( $t->id ); ?>">
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
                    <?php echo esc_html( $travel_badge ); ?>
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
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php endif; ?>
