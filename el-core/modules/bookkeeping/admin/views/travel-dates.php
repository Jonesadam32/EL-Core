<?php
/**
 * Bookkeeping — Travel Dates Tab
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$tax_year = $tax_year ?? $module->get_tax_year();
$periods  = $module->get_travel_periods( $tax_year );
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Travel Dates — %d', 'el-core' ), $tax_year ) ); ?></h2>
        <p class="el-bk-tab-desc"><?php esc_html_e( 'Record business travel periods for IRS documentation purposes.', 'el-core' ); ?></p>
    </div>
    <div class="el-bk-tab-header-right">
        <button class="el-btn el-btn-primary" id="el-bk-add-period-btn">
            <?php esc_html_e( 'Add Travel Period', 'el-core' ); ?>
        </button>
    </div>
</div>

<!-- Add/Edit Travel Period Form -->
<div id="el-bk-period-form" class="el-bk-card" style="display:none;">
    <h3><?php esc_html_e( 'Travel Period', 'el-core' ); ?></h3>
    <input type="hidden" id="el-bk-period-id" value="">
    <div class="el-bk-form-row">
        <label><?php esc_html_e( 'Label', 'el-core' ); ?>
            <input type="text" id="el-bk-period-label" class="el-input"
                placeholder="<?php esc_attr_e( 'e.g. NYC Trip — TARC Conference', 'el-core' ); ?>">
        </label>
        <label><?php esc_html_e( 'Start Date', 'el-core' ); ?>
            <input type="date" id="el-bk-period-start" class="el-input">
        </label>
        <label><?php esc_html_e( 'End Date', 'el-core' ); ?>
            <input type="date" id="el-bk-period-end" class="el-input">
        </label>
    </div>
    <div class="el-bk-form-row">
        <label><?php esc_html_e( 'Purpose / Notes (for IRS documentation)', 'el-core' ); ?>
            <textarea id="el-bk-period-purpose" class="el-textarea" rows="2"></textarea>
        </label>
    </div>
    <div class="el-bk-form-actions">
        <button class="el-btn el-btn-primary" id="el-bk-save-period-btn"><?php esc_html_e( 'Save Period', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline" id="el-bk-cancel-period-btn"><?php esc_html_e( 'Cancel', 'el-core' ); ?></button>
    </div>
</div>

<!-- Travel Periods Table -->
<?php if ( empty( $periods ) ) : ?>
    <?php echo EL_Admin_UI::notice( [ 'message' => sprintf( __( 'No travel periods found for %d.', 'el-core' ), $tax_year ), 'type' => 'info' ] ); // phpcs:ignore ?>
<?php else : ?>
<table class="el-bk-travel-table widefat">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Label', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Start Date', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'End Date', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Purpose', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Transactions Tagged', 'el-core' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'el-core' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $periods as $p ) :
            global $wpdb;
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}el_bk_transactions WHERE travel_period_id = %d",
                $p->id
            ) );
        ?>
        <tr>
            <td><?php echo esc_html( $p->label ?: __( '(No label)', 'el-core' ) ); ?></td>
            <td><?php echo esc_html( $p->start_date ); ?></td>
            <td><?php echo esc_html( $p->end_date ); ?></td>
            <td><?php echo esc_html( $p->purpose ); ?></td>
            <td><?php echo esc_html( $count ); ?></td>
            <td>
                <button class="el-btn el-btn-outline el-bk-edit-period-btn"
                    data-id="<?php echo esc_attr( $p->id ); ?>"
                    data-label="<?php echo esc_attr( $p->label ); ?>"
                    data-start="<?php echo esc_attr( $p->start_date ); ?>"
                    data-end="<?php echo esc_attr( $p->end_date ); ?>"
                    data-purpose="<?php echo esc_attr( $p->purpose ); ?>">
                    <?php esc_html_e( 'Edit', 'el-core' ); ?>
                </button>
                <button class="el-btn el-btn-outline el-bk-delete-period-btn" data-id="<?php echo esc_attr( $p->id ); ?>">
                    <?php esc_html_e( 'Delete', 'el-core' ); ?>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
