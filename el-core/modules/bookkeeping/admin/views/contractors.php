<?php
/**
 * Bookkeeping — Contractors Tab
 *
 * @var EL_Bookkeeping_Module $module
 * @var int                   $tax_year
 * @var array                 $prefetch_contractors
 * @var array                 $prefetch_contract_labor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$contractors    = $prefetch_contractors;
$contract_labor = $prefetch_contract_labor;

// ── Build contractor totals ──────────────────────────────────────────────────
$contractor_totals = [];
foreach ( $contractors as $c ) {
    $contractor_totals[ $c->id ] = 0.0;
}
foreach ( $contract_labor as $t ) {
    if ( $t->contractor_id && isset( $contractor_totals[ $t->contractor_id ] ) ) {
        $contractor_totals[ $t->contractor_id ] += (float) $t->amount;
    }
}

// ── Business totals (sum all contract labor by business) ─────────────────────
$business_totals = [];
foreach ( $contract_labor as $t ) {
    $biz = $t->business ?: __( '(Unassigned)', 'el-core' );
    $business_totals[ $biz ] = ( $business_totals[ $biz ] ?? 0.0 ) + (float) $t->amount;
}
arsort( $business_totals );
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Contractors — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
</div>

<!-- Two-panel summary bar -->
<div class="el-bk-contractors-summary">
    <div class="el-bk-contractors-summary-panel">
        <h4>
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e( 'Contractor Totals', 'el-core' ); ?>
        </h4>
        <?php if ( empty( $contractors ) ) : ?>
            <p style="font-size:13px;color:#666;margin:0;"><?php esc_html_e( 'No contractors added yet.', 'el-core' ); ?></p>
        <?php else : ?>
            <?php foreach ( $contractors as $c ) :
                $total = $contractor_totals[ $c->id ] ?? 0.0;
            ?>
            <div class="el-bk-contractor-total-row">
                <span><?php echo esc_html( $c->name ); ?></span>
                <span class="el-bk-contractor-total-amount">
                    $<?php echo esc_html( number_format( $total, 2 ) ); ?>
                </span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="el-bk-contractors-summary-panel">
        <h4>
            <span class="dashicons dashicons-building"></span>
            <?php esc_html_e( 'Business Totals', 'el-core' ); ?>
        </h4>
        <?php if ( empty( $business_totals ) ) : ?>
            <p style="font-size:13px;color:#666;margin:0;"><?php esc_html_e( 'No contract labor transactions found.', 'el-core' ); ?></p>
        <?php else : ?>
            <?php foreach ( $business_totals as $biz => $total ) : ?>
            <div class="el-bk-contractor-total-row">
                <span><?php echo esc_html( $biz ); ?></span>
                <span class="el-bk-contractor-total-amount">
                    $<?php echo esc_html( number_format( $total, 2 ) ); ?>
                </span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Action row -->
<div class="el-bk-action-row" style="margin-bottom:20px;">
    <button class="el-btn el-btn-primary" id="el-bk-add-contractor-btn">
        <?php esc_html_e( 'Manage Contractors', 'el-core' ); ?>
    </button>
    <button class="el-btn el-btn-outline el-bk-export-btn" data-format="csv" data-type="contractors">
        <?php esc_html_e( 'Download to Spreadsheet', 'el-core' ); ?>
    </button>
    <span class="el-bk-action-row-divider"></span>
    <input type="text" id="el-bk-contractor-search" class="el-input" placeholder="<?php esc_attr_e( 'Search transactions…', 'el-core' ); ?>" style="max-width:240px;">
</div>

<!-- Add/Edit Contractor Form (hidden) -->
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

<?php if ( ! empty( $contractors ) ) : ?>
<!-- Contractors list -->
<h3 class="el-bk-section-title"><?php esc_html_e( 'Contractor Directory', 'el-core' ); ?></h3>
<table class="el-bk-contractors-table widefat" style="margin-bottom:28px;">
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
<h3 class="el-bk-section-title">
    <?php echo esc_html( sprintf( __( 'Contract Labor Transactions — %d', 'el-core' ), $tax_year ) ); ?>
</h3>

<?php if ( empty( $contract_labor ) ) : ?>
    <?php echo EL_Admin_UI::notice( __( 'No Contract Labor transactions found for this tax year. Classify expense transactions as "Contract Labor" in the Expenses tab to see them here.', 'el-core' ), 'info' ); // phpcs:ignore ?>
<?php else : ?>
<div class="el-bk-table-wrap">
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
                        <option value=""><?php esc_html_e( '— Select Contractor —', 'el-core' ); ?></option>
                        <?php foreach ( $contractors as $c ) : ?>
                            <option value="<?php echo esc_attr( $c->id ); ?>" <?php selected( $t->contractor_id ?? '', $c->id ); ?>>
                                <?php echo esc_html( $c->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
