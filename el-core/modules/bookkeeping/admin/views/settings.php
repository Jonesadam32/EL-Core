<?php
/**
 * Bookkeeping — Settings Tab
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! el_core_can( 'manage_bookkeeping_settings' ) ) {
    echo EL_Admin_UI::notice( [ 'message' => __( 'You do not have permission to manage bookkeeping settings.', 'el-core' ), 'type' => 'error' ] ); // phpcs:ignore
    return;
}

$business_name   = $module->get_setting( 'business_name',        'Expanded Learning Solutions LLC' );
$tax_year        = $module->get_setting( 'tax_year',              (int) gmdate( 'Y' ) );
$home_office_pct = $module->get_setting( 'home_office_pct',       0 );
$mileage_rate    = $module->get_setting( 'vehicle_mileage_rate',  0.67 );

$upload_dir  = wp_upload_dir();
$receipt_dir = $upload_dir['basedir'] . '/els-bookkeeping/receipts';

// Handle settings save
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['el_bk_settings_nonce'] ) ) {
    if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['el_bk_settings_nonce'] ) ), 'el_bk_settings_save' ) ) {
        $module->core->settings->set( 'mod_bookkeeping', 'business_name',        sanitize_text_field( wp_unslash( $_POST['business_name']        ?? '' ) ) );
        $module->core->settings->set( 'mod_bookkeeping', 'tax_year',             absint( $_POST['tax_year']             ?? gmdate( 'Y' ) ) );
        $module->core->settings->set( 'mod_bookkeeping', 'home_office_pct',      (float) ( $_POST['home_office_pct']    ?? 0 ) );
        $module->core->settings->set( 'mod_bookkeeping', 'vehicle_mileage_rate', (float) ( $_POST['vehicle_mileage_rate'] ?? 0.67 ) );

        echo EL_Admin_UI::notice( [ 'message' => __( 'Settings saved.', 'el-core' ), 'type' => 'success' ] ); // phpcs:ignore

        // Refresh values
        $business_name   = $module->get_setting( 'business_name',        'Expanded Learning Solutions LLC' );
        $tax_year        = $module->get_setting( 'tax_year',              (int) gmdate( 'Y' ) );
        $home_office_pct = $module->get_setting( 'home_office_pct',       0 );
        $mileage_rate    = $module->get_setting( 'vehicle_mileage_rate',  0.67 );
    }
}
?>

<div class="el-bk-tab-header">
    <div class="el-bk-tab-header-left">
        <h2><?php esc_html_e( 'Settings', 'el-core' ); ?></h2>
    </div>
</div>

<form method="post" class="el-bk-settings-form">
    <?php wp_nonce_field( 'el_bk_settings_save', 'el_bk_settings_nonce' ); ?>

    <div class="el-bk-card">
        <h3><?php esc_html_e( 'Business Info', 'el-core' ); ?></h3>

        <?php echo EL_Admin_UI::form_row( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            'label' => __( 'Business Name', 'el-core' ),
            'field' => '<input type="text" name="business_name" class="el-input el-input--wide" value="' . esc_attr( $business_name ) . '">',
        ] ); ?>

        <?php echo EL_Admin_UI::form_row( [ // phpcs:ignore
            'label' => __( 'Default Tax Year', 'el-core' ),
            'field' => '<input type="number" name="tax_year" class="el-input el-input--small" value="' . esc_attr( $tax_year ) . '" min="2000" max="2099">',
        ] ); ?>
    </div>

    <div class="el-bk-card">
        <h3><?php esc_html_e( 'Schedule C Settings', 'el-core' ); ?></h3>

        <?php echo EL_Admin_UI::form_row( [ // phpcs:ignore
            'label'   => __( 'Home Office % (Indirect)', 'el-core' ),
            'field'   => '<input type="number" name="home_office_pct" class="el-input el-input--small" value="' . esc_attr( $home_office_pct ) . '" min="0" max="100" step="0.1">',
            'help'    => __( 'Percentage of home used for business. Applied to utilities and similar indirect costs.', 'el-core' ),
        ] ); ?>

        <?php echo EL_Admin_UI::form_row( [ // phpcs:ignore
            'label'   => __( 'Vehicle Mileage Rate ($/mile)', 'el-core' ),
            'field'   => '<input type="number" name="vehicle_mileage_rate" class="el-input el-input--small" value="' . esc_attr( $mileage_rate ) . '" min="0" step="0.001">',
            'help'    => __( 'IRS standard mileage rate. Default: 0.67 (2024). Update each tax year.', 'el-core' ),
        ] ); ?>
    </div>

    <div class="el-bk-card">
        <h3><?php esc_html_e( 'Receipt Storage', 'el-core' ); ?></h3>
        <p class="el-bk-hint">
            <?php esc_html_e( 'Receipts are stored at:', 'el-core' ); ?>
            <code><?php echo esc_html( $receipt_dir ); ?></code>
        </p>
        <p class="el-bk-hint">
            <?php esc_html_e( 'Note: The Anthropic API key for AI receipt scanning is managed in EL Core → Brand → AI Configuration.', 'el-core' ); ?>
        </p>
    </div>

    <div class="el-bk-form-actions">
        <button type="submit" class="el-btn el-btn-primary"><?php esc_html_e( 'Save Settings', 'el-core' ); ?></button>
    </div>
</form>
