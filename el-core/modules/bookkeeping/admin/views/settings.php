<?php
/**
 * Bookkeeping — Settings Tab (Phase C.1)
 *
 * @var EL_Bookkeeping_Module $module
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! el_core_can( 'manage_bookkeeping_settings' ) ) {
    echo EL_Admin_UI::notice( [ 'message' => __( 'You do not have permission to manage bookkeeping settings.', 'el-core' ), 'type' => 'error' ] ); // phpcs:ignore
    return;
}

// ── Load all settings ──────────────────────────────────────────────────────────

// Business Profile
$business_name       = $module->get_setting( 'business_name',       'Expanded Learning Solutions LLC' );
$owner_legal_name    = $module->get_setting( 'owner_legal_name',    '' );
$ein                 = $module->get_setting( 'ein',                 '' );
$business_address    = $module->get_setting( 'business_address',    '' );
$business_type       = $module->get_setting( 'business_type',       'sole_proprietor' );
$business_start_date = $module->get_setting( 'business_start_date', '' );
$accounting_method   = $module->get_setting( 'accounting_method',   'cash' );
$naics_code          = $module->get_setting( 'naics_code',          '' );
$tax_year            = $module->get_setting( 'tax_year',            (int) gmdate( 'Y' ) );

// Home Office
$home_enabled        = (bool) $module->get_setting( 'home_office_enabled',    false );
$home_method         = $module->get_setting( 'home_office_method',   'simplified' );
$home_office_sqft    = (float) $module->get_setting( 'home_office_sqft',     0 );
$home_total_sqft     = (float) $module->get_setting( 'home_total_sqft',      0 );
$home_mortgage_rent  = (float) $module->get_setting( 'home_mortgage_rent',   0 );
$home_re_taxes       = (float) $module->get_setting( 'home_real_estate_taxes', 0 );
$home_utilities      = (float) $module->get_setting( 'home_utilities',       0 );
$home_insurance      = (float) $module->get_setting( 'home_insurance',       0 );
$home_repairs        = (float) $module->get_setting( 'home_repairs',         0 );
$home_depreciation   = (float) $module->get_setting( 'home_depreciation',    0 );

// Vehicle
$vehicle_enabled      = (bool) $module->get_setting( 'vehicle_enabled',         false );
$vehicle_description  = $module->get_setting( 'vehicle_description',  '' );
$vehicle_service_date = $module->get_setting( 'vehicle_service_date', '' );
$vehicle_method       = $module->get_setting( 'vehicle_method',        'standard' );
$vehicle_total_miles  = (float) $module->get_setting( 'vehicle_total_miles',    0 );
$vehicle_biz_miles    = (float) $module->get_setting( 'vehicle_business_miles', 0 );
$vehicle_rate         = (float) $module->get_setting( 'vehicle_mileage_rate',   0.70 );
$vehicle_gas          = (float) $module->get_setting( 'vehicle_gas',            0 );
$vehicle_insurance    = (float) $module->get_setting( 'vehicle_insurance',      0 );
$vehicle_repairs      = (float) $module->get_setting( 'vehicle_repairs',        0 );
$vehicle_registration = (float) $module->get_setting( 'vehicle_registration',   0 );
$vehicle_lease        = (float) $module->get_setting( 'vehicle_lease',          0 );
$vehicle_depreciation = (float) $module->get_setting( 'vehicle_depreciation',   0 );

// Other Deductions
$health_insurance    = (float) $module->get_setting( 'health_insurance_premium',  0 );
$sep_ira             = (float) $module->get_setting( 'retirement_sep_ira',        0 );
$solo_401k           = (float) $module->get_setting( 'retirement_solo_401k',      0 );
$simple_ira          = (float) $module->get_setting( 'retirement_simple_ira',     0 );
$prof_licenses       = (float) $module->get_setting( 'professional_licenses',     0 );
$prof_memberships    = (float) $module->get_setting( 'professional_memberships',  0 );
$cont_education      = (float) $module->get_setting( 'continuing_education',      0 );
$biz_insurance       = (float) $module->get_setting( 'business_insurance',        0 );
$bank_merchant_fees  = (float) $module->get_setting( 'bank_merchant_fees',        0 );
$software_subs       = (float) $module->get_setting( 'software_subscriptions',    0 );

// Gmail
$gmail_client_id     = $module->get_setting( 'gmail_client_id',     '' );
$gmail_client_secret = $module->get_setting( 'gmail_client_secret', '' );

$upload_dir  = wp_upload_dir();
$receipt_dir = $upload_dir['basedir'] . '/els-bookkeeping/receipts';

// ── Handle POST save ───────────────────────────────────────────────────────────
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['el_bk_settings_nonce'] ) ) {
    if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['el_bk_settings_nonce'] ) ), 'el_bk_settings_save' ) ) {
        $s = $module->core->settings;

        // Business Profile
        $s->set( 'mod_bookkeeping', 'business_name',       sanitize_text_field( wp_unslash( $_POST['business_name']       ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'owner_legal_name',    sanitize_text_field( wp_unslash( $_POST['owner_legal_name']    ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'ein',                 sanitize_text_field( wp_unslash( $_POST['ein']                 ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'business_address',    sanitize_textarea_field( wp_unslash( $_POST['business_address'] ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'business_type',       sanitize_key( $_POST['business_type']       ?? 'sole_proprietor' ) );
        $s->set( 'mod_bookkeeping', 'business_start_date', sanitize_text_field( wp_unslash( $_POST['business_start_date'] ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'accounting_method',   sanitize_key( $_POST['accounting_method']   ?? 'cash' ) );
        $s->set( 'mod_bookkeeping', 'naics_code',          sanitize_text_field( wp_unslash( $_POST['naics_code']          ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'tax_year',            absint( $_POST['tax_year'] ?? gmdate( 'Y' ) ) );

        // Home Office
        $s->set( 'mod_bookkeeping', 'home_office_enabled',     isset( $_POST['home_office_enabled'] ) ? 1 : 0 );
        $s->set( 'mod_bookkeeping', 'home_office_method',      sanitize_key( $_POST['home_office_method']   ?? 'simplified' ) );
        $s->set( 'mod_bookkeeping', 'home_office_sqft',        (float) ( $_POST['home_office_sqft']         ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'home_total_sqft',         (float) ( $_POST['home_total_sqft']          ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'home_mortgage_rent',      (float) ( $_POST['home_mortgage_rent']       ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'home_real_estate_taxes',  (float) ( $_POST['home_real_estate_taxes']   ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'home_utilities',          (float) ( $_POST['home_utilities']           ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'home_insurance',          (float) ( $_POST['home_insurance']           ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'home_repairs',            (float) ( $_POST['home_repairs']             ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'home_depreciation',       (float) ( $_POST['home_depreciation']        ?? 0 ) );

        // Vehicle
        $s->set( 'mod_bookkeeping', 'vehicle_enabled',         isset( $_POST['vehicle_enabled'] ) ? 1 : 0 );
        $s->set( 'mod_bookkeeping', 'vehicle_description',     sanitize_text_field( wp_unslash( $_POST['vehicle_description']  ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'vehicle_service_date',    sanitize_text_field( wp_unslash( $_POST['vehicle_service_date'] ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'vehicle_method',          sanitize_key( $_POST['vehicle_method']         ?? 'standard' ) );
        $s->set( 'mod_bookkeeping', 'vehicle_total_miles',     (float) ( $_POST['vehicle_total_miles']         ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'vehicle_business_miles',  (float) ( $_POST['vehicle_business_miles']      ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'vehicle_mileage_rate',    (float) ( $_POST['vehicle_mileage_rate']        ?? 0.70 ) );
        $s->set( 'mod_bookkeeping', 'vehicle_gas',             (float) ( $_POST['vehicle_gas']                 ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'vehicle_insurance',       (float) ( $_POST['vehicle_insurance']           ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'vehicle_repairs',         (float) ( $_POST['vehicle_repairs']             ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'vehicle_registration',    (float) ( $_POST['vehicle_registration']        ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'vehicle_lease',           (float) ( $_POST['vehicle_lease']               ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'vehicle_depreciation',    (float) ( $_POST['vehicle_depreciation']        ?? 0 ) );

        // Other Deductions
        $s->set( 'mod_bookkeeping', 'health_insurance_premium', (float) ( $_POST['health_insurance_premium'] ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'retirement_sep_ira',       (float) ( $_POST['retirement_sep_ira']       ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'retirement_solo_401k',     (float) ( $_POST['retirement_solo_401k']     ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'retirement_simple_ira',    (float) ( $_POST['retirement_simple_ira']    ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'professional_licenses',    (float) ( $_POST['professional_licenses']    ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'professional_memberships', (float) ( $_POST['professional_memberships'] ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'continuing_education',     (float) ( $_POST['continuing_education']     ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'business_insurance',       (float) ( $_POST['business_insurance']       ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'bank_merchant_fees',       (float) ( $_POST['bank_merchant_fees']       ?? 0 ) );
        $s->set( 'mod_bookkeeping', 'software_subscriptions',   (float) ( $_POST['software_subscriptions']   ?? 0 ) );

        // Gmail Receipt Scanner
        $s->set( 'mod_bookkeeping', 'gmail_client_id',     sanitize_text_field( wp_unslash( $_POST['gmail_client_id']     ?? '' ) ) );
        $s->set( 'mod_bookkeeping', 'gmail_client_secret', sanitize_text_field( wp_unslash( $_POST['gmail_client_secret'] ?? '' ) ) );

        echo EL_Admin_UI::notice( [ 'message' => __( 'Settings saved.', 'el-core' ), 'type' => 'success' ] ); // phpcs:ignore

        // Refresh all values after save
        $business_name       = $module->get_setting( 'business_name',       'Expanded Learning Solutions LLC' );
        $owner_legal_name    = $module->get_setting( 'owner_legal_name',    '' );
        $ein                 = $module->get_setting( 'ein',                 '' );
        $business_address    = $module->get_setting( 'business_address',    '' );
        $business_type       = $module->get_setting( 'business_type',       'sole_proprietor' );
        $business_start_date = $module->get_setting( 'business_start_date', '' );
        $accounting_method   = $module->get_setting( 'accounting_method',   'cash' );
        $naics_code          = $module->get_setting( 'naics_code',          '' );
        $tax_year            = $module->get_setting( 'tax_year',            (int) gmdate( 'Y' ) );

        $home_enabled        = (bool) $module->get_setting( 'home_office_enabled',    false );
        $home_method         = $module->get_setting( 'home_office_method',   'simplified' );
        $home_office_sqft    = (float) $module->get_setting( 'home_office_sqft',     0 );
        $home_total_sqft     = (float) $module->get_setting( 'home_total_sqft',      0 );
        $home_mortgage_rent  = (float) $module->get_setting( 'home_mortgage_rent',   0 );
        $home_re_taxes       = (float) $module->get_setting( 'home_real_estate_taxes', 0 );
        $home_utilities      = (float) $module->get_setting( 'home_utilities',       0 );
        $home_insurance      = (float) $module->get_setting( 'home_insurance',       0 );
        $home_repairs        = (float) $module->get_setting( 'home_repairs',         0 );
        $home_depreciation   = (float) $module->get_setting( 'home_depreciation',    0 );

        $vehicle_enabled      = (bool) $module->get_setting( 'vehicle_enabled',         false );
        $vehicle_description  = $module->get_setting( 'vehicle_description',  '' );
        $vehicle_service_date = $module->get_setting( 'vehicle_service_date', '' );
        $vehicle_method       = $module->get_setting( 'vehicle_method',        'standard' );
        $vehicle_total_miles  = (float) $module->get_setting( 'vehicle_total_miles',    0 );
        $vehicle_biz_miles    = (float) $module->get_setting( 'vehicle_business_miles', 0 );
        $vehicle_rate         = (float) $module->get_setting( 'vehicle_mileage_rate',   0.70 );
        $vehicle_gas          = (float) $module->get_setting( 'vehicle_gas',            0 );
        $vehicle_insurance    = (float) $module->get_setting( 'vehicle_insurance',      0 );
        $vehicle_repairs      = (float) $module->get_setting( 'vehicle_repairs',        0 );
        $vehicle_registration = (float) $module->get_setting( 'vehicle_registration',   0 );
        $vehicle_lease        = (float) $module->get_setting( 'vehicle_lease',          0 );
        $vehicle_depreciation = (float) $module->get_setting( 'vehicle_depreciation',   0 );

        $health_insurance    = (float) $module->get_setting( 'health_insurance_premium',  0 );
        $sep_ira             = (float) $module->get_setting( 'retirement_sep_ira',        0 );
        $solo_401k           = (float) $module->get_setting( 'retirement_solo_401k',      0 );
        $simple_ira          = (float) $module->get_setting( 'retirement_simple_ira',     0 );
        $prof_licenses       = (float) $module->get_setting( 'professional_licenses',     0 );
        $prof_memberships    = (float) $module->get_setting( 'professional_memberships',  0 );
        $cont_education      = (float) $module->get_setting( 'continuing_education',      0 );
        $biz_insurance       = (float) $module->get_setting( 'business_insurance',        0 );
        $bank_merchant_fees  = (float) $module->get_setting( 'bank_merchant_fees',        0 );
        $software_subs       = (float) $module->get_setting( 'software_subscriptions',    0 );

        $gmail_client_id     = $module->get_setting( 'gmail_client_id',     '' );
        $gmail_client_secret = $module->get_setting( 'gmail_client_secret', '' );
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

    <?php /* ── Business Profile ─────────────────────────────────────────────── */ ?>
    <div class="el-bk-settings-section">
        <button type="button" class="el-bk-settings-toggle" data-target="el-bk-section-profile">
            <span class="el-bk-settings-toggle-icon">&#9660;</span>
            <?php esc_html_e( 'Business Profile', 'el-core' ); ?>
        </button>
        <div id="el-bk-section-profile" class="el-bk-settings-body">
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field">
                    <label for="el-bk-business-name"><?php esc_html_e( 'Business Name', 'el-core' ); ?></label>
                    <input type="text" id="el-bk-business-name" name="business_name" class="el-input el-input--wide" value="<?php echo esc_attr( $business_name ); ?>">
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-owner-legal-name"><?php esc_html_e( 'Owner Legal Name', 'el-core' ); ?></label>
                    <input type="text" id="el-bk-owner-legal-name" name="owner_legal_name" class="el-input el-input--wide" value="<?php echo esc_attr( $owner_legal_name ); ?>">
                    <span class="el-bk-field-help"><?php esc_html_e( 'Your name as it appears on your tax return', 'el-core' ); ?></span>
                </div>
            </div>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field">
                    <label for="el-bk-ein"><?php esc_html_e( 'EIN', 'el-core' ); ?></label>
                    <input type="text" id="el-bk-ein" name="ein" class="el-input" value="<?php echo esc_attr( $ein ); ?>" placeholder="XX-XXXXXXX">
                    <span class="el-bk-field-help"><?php esc_html_e( 'Enter "SSN" if filing with your social security number', 'el-core' ); ?></span>
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-business-type"><?php esc_html_e( 'Business Type', 'el-core' ); ?></label>
                    <select id="el-bk-business-type" name="business_type" class="el-input">
                        <option value="sole_proprietor"    <?php selected( $business_type, 'sole_proprietor' ); ?>><?php esc_html_e( 'Sole Proprietor', 'el-core' ); ?></option>
                        <option value="single_member_llc"  <?php selected( $business_type, 'single_member_llc' ); ?>><?php esc_html_e( 'Single-Member LLC', 'el-core' ); ?></option>
                        <option value="partnership"        <?php selected( $business_type, 'partnership' ); ?>><?php esc_html_e( 'Partnership', 'el-core' ); ?></option>
                        <option value="s_corp"             <?php selected( $business_type, 's_corp' ); ?>><?php esc_html_e( 'S-Corp', 'el-core' ); ?></option>
                    </select>
                </div>
            </div>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field el-bk-settings-field--full">
                    <label for="el-bk-business-address"><?php esc_html_e( 'Business Address', 'el-core' ); ?></label>
                    <textarea id="el-bk-business-address" name="business_address" class="el-input el-input--textarea" rows="2"><?php echo esc_textarea( $business_address ); ?></textarea>
                </div>
            </div>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field">
                    <label for="el-bk-business-start-date"><?php esc_html_e( 'Business Start Date', 'el-core' ); ?></label>
                    <input type="date" id="el-bk-business-start-date" name="business_start_date" class="el-input" value="<?php echo esc_attr( $business_start_date ); ?>">
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-accounting-method"><?php esc_html_e( 'Accounting Method', 'el-core' ); ?></label>
                    <select id="el-bk-accounting-method" name="accounting_method" class="el-input">
                        <option value="cash"    <?php selected( $accounting_method, 'cash' ); ?>><?php esc_html_e( 'Cash', 'el-core' ); ?></option>
                        <option value="accrual" <?php selected( $accounting_method, 'accrual' ); ?>><?php esc_html_e( 'Accrual', 'el-core' ); ?></option>
                    </select>
                </div>
            </div>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field">
                    <label for="el-bk-naics-code"><?php esc_html_e( 'Principal Business Code (NAICS)', 'el-core' ); ?></label>
                    <input type="text" id="el-bk-naics-code" name="naics_code" class="el-input" value="<?php echo esc_attr( $naics_code ); ?>" placeholder="e.g. 611710">
                    <span class="el-bk-field-help"><?php esc_html_e( '611710 = Educational Support Services', 'el-core' ); ?></span>
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-tax-year"><?php esc_html_e( 'Default Tax Year', 'el-core' ); ?></label>
                    <input type="number" id="el-bk-tax-year" name="tax_year" class="el-input el-input--small" value="<?php echo esc_attr( $tax_year ); ?>" min="2000" max="2099">
                </div>
            </div>
        </div>
    </div>

    <?php /* ── Home Office Deduction ──────────────────────────────────────────── */ ?>
    <div class="el-bk-settings-section">
        <button type="button" class="el-bk-settings-toggle" data-target="el-bk-section-home">
            <span class="el-bk-settings-toggle-icon">&#9660;</span>
            <?php esc_html_e( 'Home Office Deduction', 'el-core' ); ?>
        </button>
        <div id="el-bk-section-home" class="el-bk-settings-body">
            <label class="el-bk-checkbox-label">
                <input type="checkbox" id="el-bk-home-office-enabled" name="home_office_enabled" value="1" <?php checked( $home_enabled ); ?>>
                <?php esc_html_e( 'Claim Home Office Deduction', 'el-core' ); ?>
            </label>

            <div id="el-bk-home-office-fields" <?php echo $home_enabled ? '' : 'style="display:none"'; ?>>
                <div class="el-bk-settings-row el-bk-settings-row--top-gap">
                    <div class="el-bk-settings-field">
                        <label><?php esc_html_e( 'Calculation Method', 'el-core' ); ?></label>
                        <div class="el-bk-radio-group">
                            <label class="el-bk-radio-label">
                                <input type="radio" name="home_office_method" value="simplified" <?php checked( $home_method, 'simplified' ); ?>>
                                <?php esc_html_e( 'Simplified ($5/sq ft, max 300 sq ft)', 'el-core' ); ?>
                            </label>
                            <label class="el-bk-radio-label">
                                <input type="radio" name="home_office_method" value="actual" <?php checked( $home_method, 'actual' ); ?>>
                                <?php esc_html_e( 'Actual Expenses', 'el-core' ); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="el-bk-settings-row">
                    <div class="el-bk-settings-field">
                        <label for="el-bk-home-office-sqft"><?php esc_html_e( 'Office Square Footage', 'el-core' ); ?></label>
                        <input type="number" id="el-bk-home-office-sqft" name="home_office_sqft" class="el-input el-input--small" value="<?php echo esc_attr( $home_office_sqft ); ?>" min="0" step="1">
                    </div>
                    <div class="el-bk-settings-field">
                        <label for="el-bk-home-total-sqft"><?php esc_html_e( 'Total Home Square Footage', 'el-core' ); ?></label>
                        <input type="number" id="el-bk-home-total-sqft" name="home_total_sqft" class="el-input el-input--small" value="<?php echo esc_attr( $home_total_sqft ); ?>" min="0" step="1">
                    </div>
                    <div class="el-bk-settings-field">
                        <label><?php esc_html_e( 'Business Use %', 'el-core' ); ?></label>
                        <div class="el-bk-calc-badge" id="el-bk-home-office-pct">—</div>
                    </div>
                </div>

                <div id="el-bk-home-office-actual-fields" <?php echo $home_method === 'actual' ? '' : 'style="display:none"'; ?>>
                    <p class="el-bk-settings-subsection-title"><?php esc_html_e( 'Annual Home Expenses', 'el-core' ); ?></p>
                    <div class="el-bk-settings-row">
                        <div class="el-bk-settings-field">
                            <label for="el-bk-home-mortgage-rent"><?php esc_html_e( 'Mortgage Interest / Rent', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-home-mortgage-rent" name="home_mortgage_rent" class="el-input" value="<?php echo esc_attr( $home_mortgage_rent ); ?>" min="0" step="0.01"></div>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-home-real-estate-taxes"><?php esc_html_e( 'Real Estate Taxes', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-home-real-estate-taxes" name="home_real_estate_taxes" class="el-input" value="<?php echo esc_attr( $home_re_taxes ); ?>" min="0" step="0.01"></div>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-home-utilities"><?php esc_html_e( 'Utilities', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-home-utilities" name="home_utilities" class="el-input" value="<?php echo esc_attr( $home_utilities ); ?>" min="0" step="0.01"></div>
                        </div>
                    </div>
                    <div class="el-bk-settings-row">
                        <div class="el-bk-settings-field">
                            <label for="el-bk-home-insurance"><?php esc_html_e( 'Homeowners Insurance', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-home-insurance" name="home_insurance" class="el-input" value="<?php echo esc_attr( $home_insurance ); ?>" min="0" step="0.01"></div>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-home-repairs"><?php esc_html_e( 'Repairs & Maintenance', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-home-repairs" name="home_repairs" class="el-input" value="<?php echo esc_attr( $home_repairs ); ?>" min="0" step="0.01"></div>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-home-depreciation"><?php esc_html_e( 'Depreciation', 'el-core' ); ?><span class="el-bk-field-help"><?php esc_html_e( '(if owned)', 'el-core' ); ?></span></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-home-depreciation" name="home_depreciation" class="el-input" value="<?php echo esc_attr( $home_depreciation ); ?>" min="0" step="0.01"></div>
                        </div>
                    </div>
                </div>

                <div class="el-bk-calc-result-box" id="el-bk-home-calc-result">—</div>
            </div>
        </div>
    </div>

    <?php /* ── Vehicle / Mileage ───────────────────────────────────────────────── */ ?>
    <div class="el-bk-settings-section">
        <button type="button" class="el-bk-settings-toggle" data-target="el-bk-section-vehicle">
            <span class="el-bk-settings-toggle-icon">&#9660;</span>
            <?php esc_html_e( 'Vehicle / Mileage', 'el-core' ); ?>
        </button>
        <div id="el-bk-section-vehicle" class="el-bk-settings-body">
            <label class="el-bk-checkbox-label">
                <input type="checkbox" id="el-bk-vehicle-enabled" name="vehicle_enabled" value="1" <?php checked( $vehicle_enabled ); ?>>
                <?php esc_html_e( 'Claim Vehicle Deduction', 'el-core' ); ?>
            </label>

            <div id="el-bk-vehicle-fields" <?php echo $vehicle_enabled ? '' : 'style="display:none"'; ?>>
                <div class="el-bk-settings-row el-bk-settings-row--top-gap">
                    <div class="el-bk-settings-field">
                        <label for="el-bk-vehicle-description"><?php esc_html_e( 'Vehicle Description', 'el-core' ); ?></label>
                        <input type="text" id="el-bk-vehicle-description" name="vehicle_description" class="el-input el-input--wide" value="<?php echo esc_attr( $vehicle_description ); ?>" placeholder="e.g. 2020 Toyota Camry">
                    </div>
                    <div class="el-bk-settings-field">
                        <label for="el-bk-vehicle-service-date"><?php esc_html_e( 'Date Placed in Service', 'el-core' ); ?></label>
                        <input type="date" id="el-bk-vehicle-service-date" name="vehicle_service_date" class="el-input" value="<?php echo esc_attr( $vehicle_service_date ); ?>">
                    </div>
                </div>

                <div class="el-bk-settings-row">
                    <div class="el-bk-settings-field">
                        <label><?php esc_html_e( 'Calculation Method', 'el-core' ); ?></label>
                        <div class="el-bk-radio-group">
                            <label class="el-bk-radio-label">
                                <input type="radio" name="vehicle_method" value="standard" <?php checked( $vehicle_method, 'standard' ); ?>>
                                <?php esc_html_e( 'Standard Mileage', 'el-core' ); ?>
                            </label>
                            <label class="el-bk-radio-label">
                                <input type="radio" name="vehicle_method" value="actual" <?php checked( $vehicle_method, 'actual' ); ?>>
                                <?php esc_html_e( 'Actual Expenses', 'el-core' ); ?>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="el-bk-vehicle-standard-fields" <?php echo $vehicle_method === 'actual' ? 'style="display:none"' : ''; ?>>
                    <div class="el-bk-settings-row">
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-total-miles"><?php esc_html_e( 'Total Miles Driven (Year)', 'el-core' ); ?></label>
                            <input type="number" id="el-bk-vehicle-total-miles" name="vehicle_total_miles" class="el-input" value="<?php echo esc_attr( $vehicle_total_miles ); ?>" min="0" step="1" <?php echo $vehicle_method === 'actual' ? 'disabled' : ''; ?>>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-business-miles"><?php esc_html_e( 'Business Miles Driven', 'el-core' ); ?></label>
                            <input type="number" id="el-bk-vehicle-business-miles" name="vehicle_business_miles" class="el-input" value="<?php echo esc_attr( $vehicle_biz_miles ); ?>" min="0" step="1" <?php echo $vehicle_method === 'actual' ? 'disabled' : ''; ?>>
                        </div>
                        <div class="el-bk-settings-field">
                            <label><?php esc_html_e( 'Business Use %', 'el-core' ); ?></label>
                            <div class="el-bk-calc-badge" id="el-bk-vehicle-pct">—</div>
                        </div>
                    </div>
                    <div class="el-bk-settings-row">
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-mileage-rate"><?php esc_html_e( 'Mileage Rate ($/mile)', 'el-core' ); ?></label>
                            <input type="number" id="el-bk-vehicle-mileage-rate" name="vehicle_mileage_rate" class="el-input el-input--small" value="<?php echo esc_attr( $vehicle_rate ); ?>" min="0" step="0.001">
                            <span class="el-bk-field-help"><?php esc_html_e( 'IRS rate: 2024 = $0.67 &bull; 2025 = $0.70. Update each tax year.', 'el-core' ); ?></span>
                        </div>
                    </div>
                </div>

                <div id="el-bk-vehicle-actual-fields" <?php echo $vehicle_method === 'actual' ? '' : 'style="display:none"'; ?>>
                    <p class="el-bk-settings-subsection-title"><?php esc_html_e( 'Annual Vehicle Expenses', 'el-core' ); ?></p>
                    <div class="el-bk-settings-row">
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-gas"><?php esc_html_e( 'Gas & Oil', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-vehicle-gas" name="vehicle_gas" class="el-input" value="<?php echo esc_attr( $vehicle_gas ); ?>" min="0" step="0.01"></div>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-insurance"><?php esc_html_e( 'Auto Insurance', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-vehicle-insurance" name="vehicle_insurance" class="el-input" value="<?php echo esc_attr( $vehicle_insurance ); ?>" min="0" step="0.01"></div>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-repairs"><?php esc_html_e( 'Repairs & Maintenance', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-vehicle-repairs" name="vehicle_repairs" class="el-input" value="<?php echo esc_attr( $vehicle_repairs ); ?>" min="0" step="0.01"></div>
                        </div>
                    </div>
                    <div class="el-bk-settings-row">
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-registration"><?php esc_html_e( 'Registration & Licenses', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-vehicle-registration" name="vehicle_registration" class="el-input" value="<?php echo esc_attr( $vehicle_registration ); ?>" min="0" step="0.01"></div>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-lease"><?php esc_html_e( 'Lease Payments', 'el-core' ); ?></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-vehicle-lease" name="vehicle_lease" class="el-input" value="<?php echo esc_attr( $vehicle_lease ); ?>" min="0" step="0.01"></div>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-depreciation"><?php esc_html_e( 'Depreciation', 'el-core' ); ?><span class="el-bk-field-help"><?php esc_html_e( '(if owned)', 'el-core' ); ?></span></label>
                            <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-vehicle-depreciation" name="vehicle_depreciation" class="el-input" value="<?php echo esc_attr( $vehicle_depreciation ); ?>" min="0" step="0.01"></div>
                        </div>
                    </div>
                    <div class="el-bk-settings-row">
                        <div class="el-bk-settings-field">
                            <label><?php esc_html_e( 'Business Use %', 'el-core' ); ?></label>
                            <div class="el-bk-calc-badge" id="el-bk-vehicle-actual-pct">—</div>
                            <span class="el-bk-field-help"><?php esc_html_e( 'Enter Total Miles and Business Miles above to calculate', 'el-core' ); ?></span>
                        </div>
                    </div>
                    <div class="el-bk-settings-row">
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-actual-total-miles"><?php esc_html_e( 'Total Miles Driven (Year)', 'el-core' ); ?></label>
                            <input type="number" id="el-bk-vehicle-actual-total-miles" name="vehicle_total_miles" class="el-input" value="<?php echo esc_attr( $vehicle_total_miles ); ?>" min="0" step="1" <?php echo $vehicle_method !== 'actual' ? 'disabled' : ''; ?>>
                        </div>
                        <div class="el-bk-settings-field">
                            <label for="el-bk-vehicle-actual-business-miles"><?php esc_html_e( 'Business Miles Driven', 'el-core' ); ?></label>
                            <input type="number" id="el-bk-vehicle-actual-business-miles" name="vehicle_business_miles" class="el-input" value="<?php echo esc_attr( $vehicle_biz_miles ); ?>" min="0" step="1" <?php echo $vehicle_method !== 'actual' ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                </div>

                <div class="el-bk-calc-result-box" id="el-bk-vehicle-calc-result">—</div>
            </div>
        </div>
    </div>

    <?php /* ── Other Deductions ────────────────────────────────────────────────── */ ?>
    <div class="el-bk-settings-section">
        <button type="button" class="el-bk-settings-toggle" data-target="el-bk-section-other">
            <span class="el-bk-settings-toggle-icon">&#9660;</span>
            <?php esc_html_e( 'Other Deductions', 'el-core' ); ?>
        </button>
        <div id="el-bk-section-other" class="el-bk-settings-body">

            <p class="el-bk-settings-subsection-title"><?php esc_html_e( 'Health Insurance', 'el-core' ); ?></p>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field">
                    <label for="el-bk-health-insurance-premium"><?php esc_html_e( 'Self-Employed Health Insurance', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-health-insurance-premium" name="health_insurance_premium" class="el-input" value="<?php echo esc_attr( $health_insurance ); ?>" min="0" step="0.01"></div>
                    <span class="el-bk-field-help"><?php esc_html_e( 'Premiums paid for you, spouse, and dependents', 'el-core' ); ?></span>
                </div>
            </div>

            <p class="el-bk-settings-subsection-title"><?php esc_html_e( 'Retirement Contributions', 'el-core' ); ?></p>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field">
                    <label for="el-bk-retirement-sep-ira"><?php esc_html_e( 'SEP-IRA', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-retirement-sep-ira" name="retirement_sep_ira" class="el-input" value="<?php echo esc_attr( $sep_ira ); ?>" min="0" step="0.01"></div>
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-retirement-solo-401k"><?php esc_html_e( 'Solo 401(k)', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-retirement-solo-401k" name="retirement_solo_401k" class="el-input" value="<?php echo esc_attr( $solo_401k ); ?>" min="0" step="0.01"></div>
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-retirement-simple-ira"><?php esc_html_e( 'SIMPLE IRA', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-retirement-simple-ira" name="retirement_simple_ira" class="el-input" value="<?php echo esc_attr( $simple_ira ); ?>" min="0" step="0.01"></div>
                </div>
            </div>

            <p class="el-bk-settings-subsection-title"><?php esc_html_e( 'Professional Expenses', 'el-core' ); ?></p>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field">
                    <label for="el-bk-professional-licenses"><?php esc_html_e( 'Professional Licenses', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-professional-licenses" name="professional_licenses" class="el-input" value="<?php echo esc_attr( $prof_licenses ); ?>" min="0" step="0.01"></div>
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-professional-memberships"><?php esc_html_e( 'Professional Memberships', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-professional-memberships" name="professional_memberships" class="el-input" value="<?php echo esc_attr( $prof_memberships ); ?>" min="0" step="0.01"></div>
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-continuing-education"><?php esc_html_e( 'Continuing Education', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-continuing-education" name="continuing_education" class="el-input" value="<?php echo esc_attr( $cont_education ); ?>" min="0" step="0.01"></div>
                </div>
            </div>

            <p class="el-bk-settings-subsection-title"><?php esc_html_e( 'Insurance & Fees', 'el-core' ); ?></p>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field">
                    <label for="el-bk-business-insurance"><?php esc_html_e( 'Business Insurance', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-business-insurance" name="business_insurance" class="el-input" value="<?php echo esc_attr( $biz_insurance ); ?>" min="0" step="0.01"></div>
                    <span class="el-bk-field-help"><?php esc_html_e( 'E&O, general liability, etc.', 'el-core' ); ?></span>
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-bank-merchant-fees"><?php esc_html_e( 'Bank / Merchant Fees', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-bank-merchant-fees" name="bank_merchant_fees" class="el-input" value="<?php echo esc_attr( $bank_merchant_fees ); ?>" min="0" step="0.01"></div>
                    <span class="el-bk-field-help"><?php esc_html_e( 'Only if not already tracked as expenses', 'el-core' ); ?></span>
                </div>
                <div class="el-bk-settings-field">
                    <label for="el-bk-software-subscriptions"><?php esc_html_e( 'Software Subscriptions', 'el-core' ); ?></label>
                    <div class="el-bk-currency-wrap"><span>$</span><input type="number" id="el-bk-software-subscriptions" name="software_subscriptions" class="el-input" value="<?php echo esc_attr( $software_subs ); ?>" min="0" step="0.01"></div>
                    <span class="el-bk-field-help"><?php esc_html_e( 'Only if not already tracked as expenses', 'el-core' ); ?></span>
                </div>
            </div>

        </div>
    </div>

    <?php /* ── Receipt Storage (collapsed by default) ───────────────────────────── */ ?>
    <div class="el-bk-settings-section">
        <button type="button" class="el-bk-settings-toggle el-bk-settings-toggle--collapsed" data-target="el-bk-section-receipts">
            <span class="el-bk-settings-toggle-icon">&#9654;</span>
            <?php esc_html_e( 'Receipt Storage', 'el-core' ); ?>
        </button>
        <div id="el-bk-section-receipts" class="el-bk-settings-body" style="display:none">
            <p class="el-bk-hint">
                <?php esc_html_e( 'Receipts are stored at:', 'el-core' ); ?>
                <code><?php echo esc_html( $receipt_dir ); ?></code>
            </p>
            <p class="el-bk-hint">
                <?php esc_html_e( 'Note: The Anthropic API key for AI receipt scanning is managed in EL Core → Brand → AI Configuration.', 'el-core' ); ?>
            </p>
        </div>
    </div>

    <?php /* ── Gmail Receipt Scanner ─────────────────────────────────────────────── */ ?>

    <?php if ( isset( $_GET['gmail_connected'] ) ) : ?>
        <div class="el-bk-notice el-bk-notice--success" style="margin-bottom:16px;">
            <?php esc_html_e( '✓ Gmail account connected successfully.', 'el-core' ); ?>
        </div>
    <?php endif; ?>
    <?php if ( isset( $_GET['gmail_error'] ) ) : ?>
        <div class="el-bk-notice el-bk-notice--error" style="margin-bottom:16px;">
            <?php esc_html_e( 'Gmail connection failed. Please try again.', 'el-core' ); ?>
            <code><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['gmail_error'] ) ) ); ?></code>
        </div>
    <?php endif; ?>

    <div class="el-bk-settings-section">
        <button type="button" class="el-bk-settings-toggle el-bk-settings-toggle--collapsed" data-target="el-bk-section-gmail">
            <span class="el-bk-settings-toggle-icon">&#9654;</span>
            <?php esc_html_e( 'Gmail Receipt Scanner', 'el-core' ); ?>
        </button>
        <div id="el-bk-section-gmail" class="el-bk-settings-body" style="display:none">

            <p class="el-bk-hint" style="margin-bottom:12px;">
                <?php esc_html_e( 'Connect Gmail accounts to scan for receipt emails. Requires a Google Cloud project with Gmail API enabled and OAuth 2.0 credentials.', 'el-core' ); ?>
            </p>

            <p class="el-bk-settings-subsection-title"><?php esc_html_e( 'Google OAuth Credentials', 'el-core' ); ?></p>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field el-bk-settings-field--full">
                    <label for="el-bk-gmail-client-id"><?php esc_html_e( 'Client ID', 'el-core' ); ?></label>
                    <input type="text" id="el-bk-gmail-client-id" name="gmail_client_id" class="el-input el-input--wide" value="<?php echo esc_attr( $gmail_client_id ); ?>" placeholder="xxxxxx.apps.googleusercontent.com">
                    <span class="el-bk-field-help"><?php esc_html_e( 'From Google Cloud Console → APIs & Services → Credentials', 'el-core' ); ?></span>
                </div>
            </div>
            <div class="el-bk-settings-row">
                <div class="el-bk-settings-field el-bk-settings-field--full">
                    <label for="el-bk-gmail-client-secret"><?php esc_html_e( 'Client Secret', 'el-core' ); ?></label>
                    <input type="text" id="el-bk-gmail-client-secret" name="gmail_client_secret" class="el-input el-input--wide" value="<?php echo esc_attr( $gmail_client_secret ); ?>">
                    <span class="el-bk-field-help">
                        <?php esc_html_e( 'Authorized Redirect URI to add in Google Cloud Console:', 'el-core' ); ?>
                        <code><?php echo esc_html( admin_url( 'admin-ajax.php?action=el_core_action&el_action=bk_gmail_oauth_callback' ) ); ?></code>
                    </span>
                </div>
            </div>

            <p class="el-bk-settings-subsection-title" style="margin-top:20px;"><?php esc_html_e( 'Connected Accounts', 'el-core' ); ?></p>
            <div id="el-bk-gmail-accounts-list">
                <p class="el-bk-hint"><?php esc_html_e( 'Loading…', 'el-core' ); ?></p>
            </div>

            <div style="margin-top:12px;">
                <button type="button" class="el-btn el-btn-primary" id="el-bk-gmail-connect-btn">
                    <?php esc_html_e( 'Connect Gmail Account', 'el-core' ); ?>
                </button>
            </div>

        </div>
    </div>

    <div class="el-bk-form-actions">
        <button type="submit" class="el-btn el-btn-primary"><?php esc_html_e( 'Save Settings', 'el-core' ); ?></button>
    </div>
</form>
