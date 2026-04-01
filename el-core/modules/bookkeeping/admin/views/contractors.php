<?php
/**
 * Bookkeeping — Contractors Tab
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$tax_year    = $module->get_tax_year();
$contractors = $module->get_contractors();

$contract_labor = $module->get_transactions( [
    'type'     => 'expense',
    'tax_year' => $tax_year,
    'category' => 'Contract Labor',
] );
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Contractors — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
    <div class="el-bk-tab-header-right">
        <button class="el-btn el-btn-primary" id="el-bk-add-contractor-btn">
            <?php esc_html_e( 'Add Contractor', 'el-core' ); ?>
        </button>
    </div>
</div>

<?php echo EL_Admin_UI::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    __( 'Contractor assignment and 1099 export will be fully implemented in Phase 9.', 'el-core' ),
    'info'
); ?>

<!-- Add/Edit Contractor Form -->
<div id="el-bk-contractor-form" class="el-bk-card" style="display:none;">
    <h3><?php esc_html_e( 'Contractor Details', 'el-core' ); ?></h3>
    <input type="hidden" id="el-bk-contractor-id" value="">
    <div class="el-bk-form-row">
        <label><?php esc_html_e( 'Name', 'el-core' ); ?> <input type="text" id="el-bk-contractor-name" class="el-input"></label>
        <label><?php esc_html_e( 'Email', 'el-core' ); ?> <input type="email" id="el-bk-contractor-email" class="el-input"></label>
    </div>
    <div class="el-bk-form-row">
        <label><?php esc_html_e( 'Address', 'el-core' ); ?>
            <textarea id="el-bk-contractor-address" class="el-textarea" rows="3"></textarea>
        </label>
    </div>
    <div class="el-bk-form-actions">
        <button class="el-btn el-btn-primary" id="el-bk-save-contractor-btn"><?php esc_html_e( 'Save Contractor', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline" id="el-bk-cancel-contractor-btn"><?php esc_html_e( 'Cancel', 'el-core' ); ?></button>
    </div>
</div>

<!-- Contractors List -->
<?php if ( empty( $contractors ) ) : ?>
    <?php echo EL_Admin_UI::notice( __( 'No contractors added yet.', 'el-core' ), 'info' ); // phpcs:ignore ?>
<?php else : ?>
<table class="el-bk-contractors-table widefat">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Name', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Email', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Address', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $contractors as $c ) : ?>
        <tr>
            <td><?php echo esc_html( $c->name ); ?></td>
            <td><?php echo esc_html( $c->email ); ?></td>
            <td><?php echo esc_html( $c->address ); ?></td>
            <td>
                <button class="el-btn el-btn-outline el-bk-edit-contractor-btn"
                    data-id="<?php echo esc_attr( $c->id ); ?>"
                    data-name="<?php echo esc_attr( $c->name ); ?>"
                    data-email="<?php echo esc_attr( $c->email ); ?>"
                    data-address="<?php echo esc_attr( $c->address ); ?>">
                    <?php esc_html_e( 'Edit', 'el-core' ); ?>
                </button>
                <button class="el-btn el-btn-outline el-bk-delete-contractor-btn" data-id="<?php echo esc_attr( $c->id ); ?>">
                    <?php esc_html_e( 'Delete', 'el-core' ); ?>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- Contract Labor Transactions -->
<h3><?php echo esc_html( sprintf( __( 'Contract Labor Transactions — %d', 'el-core' ), $tax_year ) ); ?></h3>

<?php if ( empty( $contract_labor ) ) : ?>
    <?php echo EL_Admin_UI::notice( __( 'No Contract Labor transactions found for this tax year.', 'el-core' ), 'info' ); // phpcs:ignore ?>
<?php else : ?>
<table class="el-bk-transactions-table widefat">
    <thead>
        <tr>
            <th>#</th>
            <th><?php esc_html_e( 'Date', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Description', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Bank Account', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Business', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Amount', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Assign to Contractor', 'el-core' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $contract_labor as $i => $t ) : ?>
        <tr data-id="<?php echo esc_attr( $t->id ); ?>">
            <td><?php echo esc_html( $i + 1 ); ?></td>
            <td><?php echo esc_html( $t->date ); ?></td>
            <td><?php echo esc_html( $t->merchant ); ?></td>
            <td><?php echo esc_html( $t->bank_account ); ?></td>
            <td><?php echo esc_html( $t->business ); ?></td>
            <td class="el-bk-amount">$<?php echo esc_html( number_format( (float) $t->amount, 2 ) ); ?></td>
            <td>
                <select class="el-bk-assign-contractor" data-transaction-id="<?php echo esc_attr( $t->id ); ?>">
                    <option value=""><?php esc_html_e( '— Unassigned —', 'el-core' ); ?></option>
                    <?php foreach ( $contractors as $c ) : ?>
                        <option value="<?php echo esc_attr( $c->id ); ?>">
                            <?php echo esc_html( $c->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
