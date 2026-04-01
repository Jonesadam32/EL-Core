<?php
/**
 * Bookkeeping — Profit & Loss Tab
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$tax_year     = $module->get_tax_year();
$business     = $module->get_business_name();
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php echo esc_html( sprintf( __( 'Profit & Loss — %d', 'el-core' ), $tax_year ) ); ?></h2>
    </div>
    <div class="el-bk-tab-header-right">
        <button class="el-btn el-btn-primary el-bk-generate-pl-btn">
            <?php esc_html_e( 'Generate Report', 'el-core' ); ?>
        </button>
    </div>
</div>

<div class="el-bk-pl-controls">
    <label><?php esc_html_e( 'From', 'el-core' ); ?>
        <input type="date" id="el-bk-pl-from" value="<?php echo esc_attr( $tax_year . '-01-01' ); ?>">
    </label>
    <label><?php esc_html_e( 'To', 'el-core' ); ?>
        <input type="date" id="el-bk-pl-to" value="<?php echo esc_attr( $tax_year . '-12-31' ); ?>">
    </label>
    <div class="el-bk-pl-presets">
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="this-year"><?php esc_html_e( 'This Year', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="last-year"><?php esc_html_e( 'Last Year', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="q1"><?php esc_html_e( 'Q1', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="q2"><?php esc_html_e( 'Q2', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="q3"><?php esc_html_e( 'Q3', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-preset-btn" data-preset="q4"><?php esc_html_e( 'Q4', 'el-core' ); ?></button>
    </div>
</div>

<?php echo EL_Admin_UI::notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    __( 'P&L report generation and export will be available in Phase 7.', 'el-core' ),
    'info'
); ?>

<div id="el-bk-pl-report" class="el-bk-pl-report" style="display:none;">
    <div class="el-bk-pl-header">
        <h3><?php echo esc_html( $business ); ?></h3>
        <p class="el-bk-pl-date-range"></p>
    </div>
    <table class="el-bk-pl-table widefat">
        <tbody id="el-bk-pl-body"></tbody>
    </table>
    <div class="el-bk-pl-export-row">
        <button class="el-btn el-btn-outline el-bk-export-pl-btn" data-format="csv"><?php esc_html_e( 'Download CSV', 'el-core' ); ?></button>
        <button class="el-btn el-btn-outline el-bk-export-pl-btn" data-format="pdf"><?php esc_html_e( 'Download PDF', 'el-core' ); ?></button>
    </div>
</div>
