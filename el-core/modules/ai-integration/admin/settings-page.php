<?php
/**
 * AI Integration Settings Page
 * 
 * @package EL_Core
 * @subpackage Modules\AI_Integration
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap el-settings-wrap">
    <h1>
        <span class="dashicons dashicons-admin-settings"></span>
        <?php _e('AI Integration Settings', 'el-core'); ?>
    </h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('el_save_ai_settings'); ?>
        
        <div class="el-settings-section">
            <h2>
                <span class="dashicons dashicons-cloud"></span>
                <?php _e('OpenAI Configuration', 'el-core'); ?>
            </h2>
            <p class="description">
                <?php _e('Connect to OpenAI to enable AI-powered features like transcript analysis and content generation.', 'el-core'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="openai_api_key"><?php _e('OpenAI API Key', 'el-core'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="openai_api_key" 
                               name="openai_api_key" 
                               value="<?php echo esc_attr($api_key_display); ?>" 
                               class="regular-text" 
                               placeholder="sk-...">
                        <p class="description">
                            <?php printf(
                                __('Get your API key from %s. Your key is stored encrypted in the database.', 'el-core'),
                                '<a href="https://platform.openai.com/api-keys" target="_blank">' . __('OpenAI Platform', 'el-core') . '</a>'
                            ); ?>
                        </p>
                        <?php if (!empty($api_key_encrypted)): ?>
                            <p class="el-api-status el-api-connected">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php _e('API key configured', 'el-core'); ?>
                            </p>
                        <?php else: ?>
                            <p class="el-api-status el-api-not-connected">
                                <span class="dashicons dashicons-warning"></span>
                                <?php _e('No API key configured', 'el-core'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="ai_model"><?php _e('AI Model', 'el-core'); ?></label>
                    </th>
                    <td>
                        <select id="ai_model" name="ai_model">
                            <option value="gpt-4o-mini" <?php selected($ai_model, 'gpt-4o-mini'); ?>>
                                <?php _e('GPT-4o Mini (Fast & Affordable)', 'el-core'); ?>
                            </option>
                            <option value="gpt-4o" <?php selected($ai_model, 'gpt-4o'); ?>>
                                <?php _e('GPT-4o (Most Capable)', 'el-core'); ?>
                            </option>
                            <option value="gpt-4-turbo" <?php selected($ai_model, 'gpt-4-turbo'); ?>>
                                <?php _e('GPT-4 Turbo', 'el-core'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('GPT-4o Mini is recommended for cost-effective operations (~$0.01 per analysis).', 'el-core'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php if (!empty($api_key_encrypted)): ?>
            <div class="el-test-connection">
                <button type="button" class="button" id="test-connection-btn">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Test Connection', 'el-core'); ?>
                </button>
                <span id="connection-test-result"></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="el-settings-section">
            <h2>
                <span class="dashicons dashicons-info"></span>
                <?php _e('About AI Features', 'el-core'); ?>
            </h2>
            <div class="el-info-box">
                <p><strong><?php _e('Available AI-Powered Features:', 'el-core'); ?></strong></p>
                <ul>
                    <li><?php _e('Transcript Analysis - Extract key information from meeting transcripts', 'el-core'); ?></li>
                    <li><?php _e('Content Generation - Generate proposals, descriptions, and documents', 'el-core'); ?></li>
                    <li><?php _e('Smart Recommendations - Get AI-powered suggestions for your content', 'el-core'); ?></li>
                </ul>
                <p><strong><?php _e('Cost:', 'el-core'); ?></strong> <?php _e('Each AI operation costs approximately $0.01-0.05 depending on complexity.', 'el-core'); ?></p>
            </div>
        </div>
        
        <p class="submit">
            <button type="submit" name="el_save_ai_settings" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Save Settings', 'el-core'); ?>
            </button>
        </p>
    </form>
</div>

<style>
.el-settings-wrap {
    max-width: 800px;
}
.el-settings-section {
    background: #fff;
    padding: 20px 25px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}
.el-settings-section h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    display: flex;
    align-items: center;
    gap: 8px;
}
.el-settings-section h2 .dashicons {
    color: var(--el-primary, #001E4E);
}
.el-api-status {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.el-api-connected {
    background: #d4edda;
    color: #155724;
}
.el-api-not-connected {
    background: #fff3cd;
    color: #856404;
}
.el-test-connection {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}
.el-test-connection .dashicons {
    margin-right: 5px;
}
#connection-test-result {
    margin-left: 15px;
}
.el-info-box {
    background: #f0f7ff;
    padding: 15px 20px;
    border-radius: 4px;
    border-left: 4px solid var(--el-accent, #00A8B5);
}
.el-info-box ul {
    margin: 10px 0 10px 20px;
}
.el-info-box li {
    margin-bottom: 5px;
}
.submit .dashicons {
    margin-right: 5px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#test-connection-btn').on('click', function() {
        var resultSpan = $('#connection-test-result');
        resultSpan.html('<span style="color:#666;"><?php _e('Testing...', 'el-core'); ?></span>');
        
        $.ajax({
            url: elCore.ajaxUrl,
            type: 'POST',
            data: {
                el_action: 'test_openai_connection',
                nonce: elCore.nonce
            },
            success: function(response) {
                if (response.success) {
                    resultSpan.html('<span style="color:#155724;">✓ ' + response.data.message + '</span>');
                } else {
                    resultSpan.html('<span style="color:#721c24;">✗ ' + response.data.message + '</span>');
                }
            },
            error: function() {
                resultSpan.html('<span style="color:#721c24;">✗ <?php _e('Connection failed', 'el-core'); ?></span>');
            }
        });
    });
});
</script>
