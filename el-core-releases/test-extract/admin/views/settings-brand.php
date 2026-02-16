<?php
/**
 * EL Core — Brand Settings
 * Colors, fonts, logo, organization name
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core  = EL_Core::instance();
$brand = $core->settings->get_brand();

// Handle form submission
if ( isset( $_POST['el_save_brand'] ) && check_admin_referer( 'el_core_brand_nonce' ) ) {
    $new_brand = [
        'org_name'        => sanitize_text_field( $_POST['org_name'] ?? '' ),
        'primary_color'   => sanitize_hex_color( $_POST['primary_color'] ?? '#1a1a2e' ) ?: '#1a1a2e',
        'secondary_color' => sanitize_hex_color( $_POST['secondary_color'] ?? '#16213e' ) ?: '#16213e',
        'accent_color'    => sanitize_hex_color( $_POST['accent_color'] ?? '#e94560' ) ?: '#e94560',
        'font_heading'    => sanitize_text_field( $_POST['font_heading'] ?? 'Inter, sans-serif' ),
        'font_body'       => sanitize_text_field( $_POST['font_body'] ?? 'Inter, sans-serif' ),
        'logo_url'        => esc_url_raw( $_POST['logo_url'] ?? '' ),
    ];

    $core->settings->set_group( 'brand', $new_brand );
    $brand = $new_brand;

    echo '<div class="notice notice-success"><p>Brand settings saved!</p></div>';
}

// Handle AI settings save
if ( isset( $_POST['el_save_ai'] ) && check_admin_referer( 'el_core_ai_nonce' ) ) {
    $ai_settings = [
        'provider'   => sanitize_text_field( $_POST['ai_provider'] ?? 'anthropic' ),
        'api_key'    => sanitize_text_field( $_POST['ai_api_key'] ?? '' ),
        'model'      => sanitize_text_field( $_POST['ai_model'] ?? 'claude-sonnet-4-5-20250929' ),
        'max_tokens' => absint( $_POST['ai_max_tokens'] ?? 1024 ),
    ];

    $core->settings->set_group( 'ai', $ai_settings );

    echo '<div class="notice notice-success"><p>AI settings saved!</p></div>';
}

$ai = $core->settings->get_group( 'ai' );
?>

<div class="wrap el-core-admin">
    <h1>Brand & Configuration</h1>

    <!-- Brand Settings -->
    <div class="el-admin-card el-admin-card-full">
        <h2>Brand Identity</h2>
        <p>These settings define your organization's visual identity across the entire system.</p>

        <form method="post">
            <?php wp_nonce_field( 'el_core_brand_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="org_name">Organization Name</label></th>
                    <td><input type="text" id="org_name" name="org_name" value="<?php echo esc_attr( $brand['org_name'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="logo_url">Logo URL</label></th>
                    <td>
                        <input type="url" id="logo_url" name="logo_url" value="<?php echo esc_attr( $brand['logo_url'] ); ?>" class="regular-text" />
                        <button type="button" class="button" id="el-upload-logo">Upload Logo</button>
                        <?php if ( $brand['logo_url'] ) : ?>
                            <br><img src="<?php echo esc_url( $brand['logo_url'] ); ?>" style="max-height: 60px; margin-top: 10px;" />
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Brand Colors</th>
                    <td>
                        <fieldset>
                            <label>
                                Primary:
                                <input type="color" name="primary_color" value="<?php echo esc_attr( $brand['primary_color'] ); ?>" />
                                <code><?php echo esc_html( $brand['primary_color'] ); ?></code>
                            </label>
                            <br><br>
                            <label>
                                Secondary:
                                <input type="color" name="secondary_color" value="<?php echo esc_attr( $brand['secondary_color'] ); ?>" />
                                <code><?php echo esc_html( $brand['secondary_color'] ); ?></code>
                            </label>
                            <br><br>
                            <label>
                                Accent:
                                <input type="color" name="accent_color" value="<?php echo esc_attr( $brand['accent_color'] ); ?>" />
                                <code><?php echo esc_html( $brand['accent_color'] ); ?></code>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="font_heading">Heading Font</label></th>
                    <td>
                        <select id="font_heading" name="font_heading">
                            <?php
                            $fonts = [
                                'Inter, sans-serif',
                                'Arial, sans-serif',
                                'Georgia, serif',
                                'Roboto, sans-serif',
                                'Open Sans, sans-serif',
                                'Montserrat, sans-serif',
                                'Lato, sans-serif',
                                'Poppins, sans-serif',
                            ];
                            foreach ( $fonts as $font ) :
                            ?>
                                <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $brand['font_heading'], $font ); ?>><?php echo esc_html( explode(',', $font)[0] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="font_body">Body Font</label></th>
                    <td>
                        <select id="font_body" name="font_body">
                            <?php foreach ( $fonts as $font ) : ?>
                                <option value="<?php echo esc_attr( $font ); ?>" <?php selected( $brand['font_body'], $font ); ?>><?php echo esc_html( explode(',', $font)[0] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="el_save_brand" class="button-primary" value="Save Brand Settings" />
            </p>
        </form>
    </div>

    <!-- AI Settings -->
    <div class="el-admin-card el-admin-card-full">
        <h2>AI Configuration</h2>
        <p>Configure the AI provider for intelligent features across all modules.</p>

        <form method="post">
            <?php wp_nonce_field( 'el_core_ai_nonce' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="ai_provider">AI Provider</label></th>
                    <td>
                        <select id="ai_provider" name="ai_provider">
                            <option value="anthropic" <?php selected( $ai['provider'], 'anthropic' ); ?>>Anthropic (Claude)</option>
                            <option value="openai" <?php selected( $ai['provider'], 'openai' ); ?>>OpenAI (GPT)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_api_key">API Key</label></th>
                    <td>
                        <input type="password" id="ai_api_key" name="ai_api_key" value="<?php echo esc_attr( $ai['api_key'] ); ?>" class="regular-text" />
                        <p class="description">Your API key is stored securely in the WordPress database.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_model">Model</label></th>
                    <td>
                        <input type="text" id="ai_model" name="ai_model" value="<?php echo esc_attr( $ai['model'] ); ?>" class="regular-text" />
                        <p class="description">Anthropic: claude-sonnet-4-5-20250929 | OpenAI: gpt-4o</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ai_max_tokens">Default Max Tokens</label></th>
                    <td>
                        <input type="number" id="ai_max_tokens" name="ai_max_tokens" value="<?php echo esc_attr( $ai['max_tokens'] ); ?>" min="100" max="8192" />
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="el_save_ai" class="button-primary" value="Save AI Settings" />
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Media uploader for logo
    $('#el-upload-logo').on('click', function(e) {
        e.preventDefault();
        var uploader = wp.media({
            title: 'Select Logo',
            button: { text: 'Use as Logo' },
            multiple: false
        });
        uploader.on('select', function() {
            var attachment = uploader.state().get('selection').first().toJSON();
            $('#logo_url').val(attachment.url);
        });
        uploader.open();
    });
});
</script>
