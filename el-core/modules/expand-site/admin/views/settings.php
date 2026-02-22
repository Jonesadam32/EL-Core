<?php
/**
 * Expand Site Module Settings Page
 * 
 * Proprietary module - stage names and AI features are hardcoded.
 * Only operational settings (deadlines, budgets) are configurable.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$module = EL_Expand_Site_Module::instance();
$settings = $module->core->settings;

// Save settings
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['el_es_settings_nonce'] ) ) {
    if ( ! wp_verify_nonce( $_POST['el_es_settings_nonce'], 'el_es_settings' ) ) {
        wp_die( __( 'Security check failed.', 'el-core' ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Permission denied.', 'el-core' ) );
    }

    // Save each setting
    $setting_keys = [
        'deadline_warning_days',
        'default_budget_low', 'default_budget_high', 'enable_client_portal'
    ];

    foreach ( $setting_keys as $key ) {
        $value = $_POST[ $key ] ?? '';
        
        // Convert checkboxes to boolean
        if ( $key === 'enable_client_portal' ) {
            $value = ! empty( $value ) ? 1 : 0;
        }
        
        // Convert numbers
        if ( in_array( $key, [ 'deadline_warning_days', 'default_budget_low', 'default_budget_high' ] ) ) {
            $value = absint( $value );
        }
        
        $settings->set( 'mod_expand-site', $key, sanitize_text_field( $value ) );
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Settings saved!', 'el-core' ) . '</p></div>';
}

// Load current values
$values = [];
$setting_keys = [
    'deadline_warning_days' => 2,
    'default_budget_low' => 3000,
    'default_budget_high' => 10000,
    'enable_client_portal' => true,
];

foreach ( $setting_keys as $key => $default ) {
    $values[ $key ] = $settings->get( 'mod_expand-site', $key, $default );
}

?>

<div class="wrap el-admin-wrap">
    <?php echo EL_Admin_UI::page_header( [
        'title' => __( 'Expand Site Settings', 'el-core' ),
        'icon'  => 'admin-generic',
    ] ); ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'el_es_settings', 'el_es_settings_nonce' ); ?>

        <div class="el-admin-section">
            <h2><?php _e( 'Deadlines & Escalation', 'el-core' ); ?></h2>
            <p class="description"><?php _e( 'Stage-specific deadline defaults are hardcoded per your workflow. Set actual deadlines when advancing stages.', 'el-core' ); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="deadline_warning_days"><?php _e( 'Deadline Warning (days before)', 'el-core' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="deadline_warning_days" name="deadline_warning_days" 
                               value="<?php echo esc_attr( $values['deadline_warning_days'] ); ?>" 
                               min="1" max="30" class="small-text">
                        <p class="description"><?php _e( 'Show warning badge when deadline is within this many days.', 'el-core' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="el-admin-section">
            <h2><?php _e( 'Project Defaults', 'el-core' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="default_budget_low"><?php _e( 'Default Budget Range (Low)', 'el-core' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="default_budget_low" name="default_budget_low" 
                               value="<?php echo esc_attr( $values['default_budget_low'] ); ?>" 
                               min="0" step="100" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_budget_high"><?php _e( 'Default Budget Range (High)', 'el-core' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="default_budget_high" name="default_budget_high" 
                               value="<?php echo esc_attr( $values['default_budget_high'] ); ?>" 
                               min="0" step="100" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Client Portal Shortcodes', 'el-core' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_client_portal" value="1" 
                                   <?php checked( $values['enable_client_portal'], 1 ); ?>>
                            <?php _e( 'Enable client-facing portal shortcodes', 'el-core' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( __( 'Save Settings', 'el-core' ) ); ?>
    </form>
</div>
