<?php
/**
 * AI Integration Module
 * 
 * Provides OpenAI API integration for AI-powered features
 * like transcript analysis and content generation.
 * 
 * @package EL_Core
 * @subpackage Modules
 */

if (!defined('ABSPATH')) {
    exit;
}

class EL_AI_Integration_Module {
    
    private static ?EL_AI_Integration_Module $instance = null;
    private EL_Core $core;
    
    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->core = EL_Core::instance();
        $this->init_hooks();
    }
    
    private function init_hooks(): void {
        // Register AJAX handlers
        add_action('el_core_ajax_test_openai_connection', [$this, 'ajax_test_connection']);
        
        // Add settings page to admin menu
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
    }
    
    /**
     * Add AI Integration settings page to admin menu
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'el-core',
            __('AI Integration', 'el-core'),
            __('AI Integration', 'el-core'),
            'manage_ai_settings',
            'el-core-ai-integration',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Render the AI Integration settings page
     */
    public function render_settings_page(): void {
        // Check user capabilities
        if (!current_user_can('manage_ai_settings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'el-core'));
        }
        
        // Handle form submission
        if (isset($_POST['el_save_ai_settings']) && check_admin_referer('el_save_ai_settings')) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'el-core') . '</p></div>';
        }
        
        // Get current settings
        $api_key_encrypted = $this->core->settings->get('mod_ai_integration', 'openai_api_key', '');
        $api_key_display = '';
        if (!empty($api_key_encrypted)) {
            $api_key = base64_decode($api_key_encrypted);
            // Show masked version
            $api_key_display = '••••••••' . substr($api_key, -4);
        }
        $ai_model = $this->core->settings->get('mod_ai_integration', 'ai_model', 'gpt-4o-mini');
        
        // Render the settings page
        include dirname(__FILE__) . '/admin/settings-page.php';
    }
    
    /**
     * Save settings from form submission
     */
    private function save_settings(): void {
        // Save OpenAI API key (encrypted)
        if (isset($_POST['openai_api_key'])) {
            $api_key = sanitize_text_field($_POST['openai_api_key']);
            if (!empty($api_key)) {
                // Only update if a new key was entered (not the masked placeholder)
                if (strpos($api_key, '••••') === false) {
                    $this->core->settings->set('mod_ai_integration', 'openai_api_key', base64_encode($api_key));
                }
            } else {
                $this->core->settings->delete('mod_ai_integration', 'openai_api_key');
            }
        }
        
        // Save AI model preference
        if (isset($_POST['ai_model'])) {
            $ai_model = sanitize_text_field($_POST['ai_model']);
            $this->core->settings->set('mod_ai_integration', 'ai_model', $ai_model);
        }
    }
    
    /**
     * AJAX: Test OpenAI connection
     */
    public function ajax_test_connection(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'el_core_nonce')) {
            EL_AJAX_Handler::error(__('Security check failed', 'el-core'));
            return;
        }
        
        // Check capability
        if (!current_user_can('manage_ai_settings')) {
            EL_AJAX_Handler::error(__('Insufficient permissions', 'el-core'));
            return;
        }
        
        $api_key_encrypted = $this->core->settings->get('mod_ai_integration', 'openai_api_key', '');
        if (empty($api_key_encrypted)) {
            EL_AJAX_Handler::error(__('No API key configured', 'el-core'));
            return;
        }
        
        $api_key = base64_decode($api_key_encrypted);
        
        // Test with a simple API call
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'user', 'content' => 'Say "connected" in one word.']
                ],
                'max_tokens' => 10
            ])
        ]);
        
        if (is_wp_error($response)) {
            EL_AJAX_Handler::error(__('Connection failed: ', 'el-core') . $response->get_error_message());
            return;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($code === 200) {
            EL_AJAX_Handler::success([
                'message' => __('Connected successfully!', 'el-core')
            ]);
        } elseif ($code === 401) {
            EL_AJAX_Handler::error(__('Invalid API key', 'el-core'));
        } elseif ($code === 429) {
            EL_AJAX_Handler::error(__('Rate limited - try again later', 'el-core'));
        } else {
            $error_msg = isset($body['error']['message']) ? $body['error']['message'] : __('Unknown error', 'el-core');
            EL_AJAX_Handler::error($error_msg);
        }
    }
    
    /**
     * Get the configured OpenAI API key (decrypted)
     * 
     * @return string|null The API key or null if not configured
     */
    public function get_api_key(): ?string {
        $api_key_encrypted = $this->core->settings->get('mod_ai_integration', 'openai_api_key', '');
        if (empty($api_key_encrypted)) {
            return null;
        }
        return base64_decode($api_key_encrypted);
    }
    
    /**
     * Get the configured AI model
     * 
     * @return string The AI model name
     */
    public function get_ai_model(): string {
        return $this->core->settings->get('mod_ai_integration', 'ai_model', 'gpt-4o-mini');
    }
    
    /**
     * Check if OpenAI is configured
     * 
     * @return bool True if API key is configured
     */
    public function is_configured(): bool {
        return !empty($this->get_api_key());
    }
}
