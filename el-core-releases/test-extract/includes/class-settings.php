<?php
/**
 * EL Core Settings Framework
 * 
 * Manages all system configuration through WordPress options table.
 * Settings are organized into groups, each stored as a serialized array
 * in one wp_options row.
 * 
 * Groups:
 *   el_core_brand    — Colors, fonts, logo, org name
 *   el_core_modules  — Which modules are active
 *   el_core_roles    — Role-to-capability mappings
 *   el_core_ai       — API keys, model preferences
 *   el_mod_{slug}    — Per-module settings
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Settings {

    /**
     * In-memory cache of loaded option groups
     * Prevents repeated database reads within a single request
     */
    private array $cache = [];

    /**
     * Default values for core setting groups
     */
    private array $defaults = [
        'brand' => [
            'org_name'        => '',
            'primary_color'   => '#1a1a2e',
            'secondary_color' => '#16213e',
            'accent_color'    => '#e94560',
            'font_heading'    => 'Inter, sans-serif',
            'font_body'       => 'Inter, sans-serif',
            'logo_url'        => '',
        ],
        'ai' => [
            'provider'        => 'anthropic',
            'api_key'         => '',
            'model'           => 'claude-sonnet-4-5-20250929',
            'max_tokens'      => 1024,
        ],
    ];

    public function __construct() {
        // Register settings for WordPress Settings API
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    // ── GET / SET ──

    /**
     * Get a single setting value
     * 
     * @param string $group   Setting group (e.g., 'brand', 'ai', 'mod_lms')
     * @param string $key     Setting key within the group
     * @param mixed  $default Fallback if key doesn't exist
     * @return mixed
     */
    public function get( string $group, string $key, mixed $default = null ): mixed {
        $data = $this->get_group( $group );
        
        if ( isset( $data[ $key ] ) ) {
            return $data[ $key ];
        }

        // Check hardcoded defaults
        if ( isset( $this->defaults[ $group ][ $key ] ) ) {
            return $this->defaults[ $group ][ $key ];
        }

        return $default;
    }

    /**
     * Get an entire setting group
     * 
     * @param string $group Setting group name
     * @return array
     */
    public function get_group( string $group ): array {
        // Check memory cache first
        if ( isset( $this->cache[ $group ] ) ) {
            return $this->cache[ $group ];
        }

        $option_name = $this->group_to_option( $group );
        $data = get_option( $option_name, [] );

        if ( ! is_array( $data ) ) {
            $data = [];
        }

        // Merge with defaults if they exist
        if ( isset( $this->defaults[ $group ] ) ) {
            $data = wp_parse_args( $data, $this->defaults[ $group ] );
        }

        // Cache for this request
        $this->cache[ $group ] = $data;

        return $data;
    }

    /**
     * Set a single setting value
     * 
     * @param string $group Setting group
     * @param string $key   Setting key
     * @param mixed  $value New value
     * @return bool Success
     */
    public function set( string $group, string $key, mixed $value ): bool {
        $data = $this->get_group( $group );
        $data[ $key ] = $value;
        return $this->set_group( $group, $data );
    }

    /**
     * Save an entire setting group
     * 
     * @param string $group Setting group
     * @param array  $data  Complete group data
     * @return bool Success
     */
    public function set_group( string $group, array $data ): bool {
        $option_name = $this->group_to_option( $group );
        $result = update_option( $option_name, $data );

        // Update cache
        $this->cache[ $group ] = $data;

        return $result;
    }

    /**
     * Delete a setting group entirely
     */
    public function delete_group( string $group ): bool {
        $option_name = $this->group_to_option( $group );
        unset( $this->cache[ $group ] );
        return delete_option( $option_name );
    }

    // ── BRAND HELPERS ──

    /**
     * Get all brand settings (commonly needed by theme)
     */
    public function get_brand(): array {
        return $this->get_group( 'brand' );
    }

    /**
     * Get CSS custom properties string from brand settings
     * Used by both plugin and theme to inject brand variables
     */
    public function get_brand_css_variables(): string {
        $brand = $this->get_brand();

        $css = ":root {\n";
        $css .= "    --el-primary: {$brand['primary_color']};\n";
        $css .= "    --el-secondary: {$brand['secondary_color']};\n";
        $css .= "    --el-accent: {$brand['accent_color']};\n";
        $css .= "    --el-font-heading: {$brand['font_heading']};\n";
        $css .= "    --el-font-body: {$brand['font_body']};\n";
        $css .= "}\n";

        return $css;
    }

    // ── MODULE SETTINGS ──

    /**
     * Get a module-specific setting
     */
    public function get_module_setting( string $module_slug, string $key, mixed $default = null ): mixed {
        return $this->get( "mod_{$module_slug}", $key, $default );
    }

    /**
     * Set a module-specific setting
     */
    public function set_module_setting( string $module_slug, string $key, mixed $value ): bool {
        return $this->set( "mod_{$module_slug}", $key, $value );
    }

    // ── ACTIVE MODULES ──

    /**
     * Get list of active module slugs
     */
    public function get_active_modules(): array {
        $modules = get_option( 'el_core_modules', [] );
        return is_array( $modules ) ? $modules : [];
    }

    /**
     * Set active module slugs
     */
    public function set_active_modules( array $slugs ): bool {
        return update_option( 'el_core_modules', $slugs );
    }

    // ── WORDPRESS SETTINGS API ──

    /**
     * Register settings with WordPress (for nonce verification and sanitization)
     */
    public function register_settings(): void {
        register_setting( 'el_core_brand_group', 'el_core_brand', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_brand' ],
        ]);

        register_setting( 'el_core_ai_group', 'el_core_ai', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize_ai' ],
        ]);
    }

    /**
     * Sanitize brand settings before saving
     */
    public function sanitize_brand( $input ): array {
        $clean = [];

        $clean['org_name']        = sanitize_text_field( $input['org_name'] ?? '' );
        $clean['primary_color']   = sanitize_hex_color( $input['primary_color'] ?? '#1a1a2e' ) ?: '#1a1a2e';
        $clean['secondary_color'] = sanitize_hex_color( $input['secondary_color'] ?? '#16213e' ) ?: '#16213e';
        $clean['accent_color']    = sanitize_hex_color( $input['accent_color'] ?? '#e94560' ) ?: '#e94560';
        $clean['font_heading']    = sanitize_text_field( $input['font_heading'] ?? 'Inter, sans-serif' );
        $clean['font_body']       = sanitize_text_field( $input['font_body'] ?? 'Inter, sans-serif' );
        $clean['logo_url']        = esc_url_raw( $input['logo_url'] ?? '' );

        // Clear cache so next read gets fresh data
        unset( $this->cache['brand'] );

        return $clean;
    }

    /**
     * Sanitize AI settings before saving
     */
    public function sanitize_ai( $input ): array {
        $clean = [];

        $clean['provider']   = sanitize_text_field( $input['provider'] ?? 'anthropic' );
        $clean['api_key']    = sanitize_text_field( $input['api_key'] ?? '' );
        $clean['model']      = sanitize_text_field( $input['model'] ?? 'claude-sonnet-4-5-20250929' );
        $clean['max_tokens'] = absint( $input['max_tokens'] ?? 1024 );

        unset( $this->cache['ai'] );

        return $clean;
    }

    // ── INTERNAL ──

    /**
     * Convert a group name to its wp_options key
     */
    private function group_to_option( string $group ): string {
        // Module settings use el_mod_ prefix
        if ( str_starts_with( $group, 'mod_' ) ) {
            return 'el_' . $group;
        }

        return 'el_core_' . $group;
    }
}
