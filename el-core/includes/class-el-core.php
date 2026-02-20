<?php
/**
 * EL Core Orchestrator
 * 
 * Central class that initializes all subsystems in the correct order
 * and provides a single access point for the entire system.
 * 
 * Usage: $core = EL_Core::instance();
 *        $core->settings->get('brand', 'primary_color');
 *        $core->modules->is_active('lms');
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Core {

    private static ?EL_Core $instance = null;

    // ── Subsystems (public for easy access) ──
    public ?EL_Settings      $settings     = null;
    public ?EL_Database      $database     = null;
    public ?EL_Roles         $roles        = null;
    public ?EL_Module_Loader $modules      = null;
    public ?EL_Asset_Loader  $assets       = null;
    public ?EL_AJAX_Handler  $ajax         = null;
    public ?EL_AI_Client     $ai           = null;

    /**
     * Get the single instance
     */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Boot sequence — order matters!
     */
    private function __construct() {
        $this->load_dependencies();
        $this->boot();
    }

    /**
     * Load all core class files
     */
    private function load_dependencies(): void {
        $includes = EL_CORE_DIR . 'includes/';

        require_once $includes . 'class-admin-ui.php';
        require_once $includes . 'class-settings.php';
        require_once $includes . 'class-database.php';
        require_once $includes . 'class-roles.php';
        require_once $includes . 'class-module-loader.php';
        require_once $includes . 'class-asset-loader.php';
        require_once $includes . 'class-ajax-handler.php';
        require_once $includes . 'class-ai-client.php';
    }

    /**
     * Initialize subsystems in dependency order
     * 
     * Order:
     * 1. Settings    — everything reads config
     * 2. Database    — modules need schema management
     * 3. Roles       — modules need capability checking
     * 4. Modules     — discovers and activates feature modules
     * 5. Assets      — CSS/JS with brand variables
     * 6. AJAX        — request handling
     * 7. AI Client   — shared AI integration
     */
    private function boot(): void {
        // 1. Settings first — everything depends on configuration
        $this->settings = new EL_Settings();

        // 2. Database — modules need tables before they can run
        $this->database = new EL_Database();

        // 3. Roles — modules need capability checking
        $this->roles = new EL_Roles( $this->settings );

        // 4. Module Loader — discovers and activates modules
        $this->modules = new EL_Module_Loader( $this );

        // 5. Asset Loader — CSS/JS with brand injection
        $this->assets = new EL_Asset_Loader( $this->settings );

        // 6. AJAX Handler — standardized request processing
        $this->ajax = new EL_AJAX_Handler();

        // 7. AI Client — shared API wrapper
        $this->ai = new EL_AI_Client( $this->settings );

        // Hook into WordPress admin
        if ( is_admin() ) {
            $this->init_admin();
        }
    }

    /**
     * Initialize admin-side functionality
     */
    private function init_admin(): void {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Register the EL Core admin menu and subpages
     */
    public function register_admin_menu(): void {
        // Main menu
        add_menu_page(
            'EL Core',                          // Page title
            'EL Core',                          // Menu title
            'manage_options',                   // Capability required
            'el-core',                          // Menu slug
            [ $this, 'render_admin_page' ],     // Callback
            'dashicons-building',               // Icon
            3                                   // Position
        );

        // Subpages
        add_submenu_page( 'el-core', 'Brand Settings', 'Brand', 'manage_options', 'el-core-brand', [ $this, 'render_brand_page' ] );
        add_submenu_page( 'el-core', 'Module Manager', 'Modules', 'manage_options', 'el-core-modules', [ $this, 'render_modules_page' ] );
        add_submenu_page( 'el-core', 'Role Manager', 'Roles', 'manage_options', 'el-core-roles', [ $this, 'render_roles_page' ] );
    }

    /**
     * Enqueue admin CSS/JS
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Only load on our pages
        if ( strpos( $hook, 'el-core' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'el-core-admin',
            EL_CORE_URL . 'admin/css/admin.css',
            [],
            EL_CORE_VERSION
        );

        wp_enqueue_script(
            'el-core-admin',
            EL_CORE_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            EL_CORE_VERSION,
            true
        );
    }

    /**
     * Admin page renderers — load view files
     */
    public function render_admin_page(): void {
        include EL_CORE_DIR . 'admin/views/settings-general.php';
    }

    public function render_brand_page(): void {
        include EL_CORE_DIR . 'admin/views/settings-brand.php';
    }

    public function render_modules_page(): void {
        include EL_CORE_DIR . 'admin/views/settings-modules.php';
    }

    public function render_roles_page(): void {
        include EL_CORE_DIR . 'admin/views/settings-roles.php';
    }

    /**
     * Prevent cloning and unserialization
     */
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
}
