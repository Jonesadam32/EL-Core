<?php
/**
 * EL Core Module Loader
 * 
 * Discovers modules in the modules/ directory, reads their manifests,
 * validates requirements, resolves dependencies, and manages activation.
 * 
 * Each module must have a module.json manifest and a main class file.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Module_Loader {

    private EL_Core $core;

    /**
     * All discovered modules (slug => manifest data)
     */
    private array $discovered = [];

    /**
     * Active module slugs
     */
    private array $active = [];

    /**
     * Loaded module instances (slug => object)
     */
    private array $instances = [];

    public function __construct( EL_Core $core ) {
        $this->core = $core;
        $this->active = $this->core->settings->get_active_modules();

        $this->discover();
        $this->load_active_modules();
    }

    // ═══════════════════════════════════════════
    // DISCOVERY
    // ═══════════════════════════════════════════

    /**
     * Scan modules/ directory for module.json manifests
     */
    private function discover(): void {
        $modules_dir = EL_CORE_MODULES_DIR;

        if ( ! is_dir( $modules_dir ) ) {
            return;
        }

        foreach ( scandir( $modules_dir ) as $folder ) {
            if ( $folder === '.' || $folder === '..' ) continue;

            $manifest_path = $modules_dir . $folder . '/module.json';

            if ( ! file_exists( $manifest_path ) ) continue;

            $manifest = json_decode( file_get_contents( $manifest_path ), true );

            if ( ! $manifest || ! isset( $manifest['slug'] ) ) {
                error_log( "EL Core: Invalid module.json in {$folder}/" );
                continue;
            }

            $this->discovered[ $manifest['slug'] ] = $manifest;
        }
    }

    // ═══════════════════════════════════════════
    // ACTIVATION / LOADING
    // ═══════════════════════════════════════════

    /**
     * Load all active modules
     */
    private function load_active_modules(): void {
        foreach ( $this->active as $slug ) {
            if ( ! isset( $this->discovered[ $slug ] ) ) {
                continue; // Module files missing but was marked active
            }

            $this->load_module( $slug );
        }
    }

    /**
     * Load a single module
     */
    private function load_module( string $slug ): bool {
        if ( isset( $this->instances[ $slug ] ) ) {
            return true; // Already loaded
        }

        $manifest = $this->discovered[ $slug ];

        // Check requirements
        if ( ! $this->check_requirements( $manifest ) ) {
            error_log( "EL Core: Module '{$slug}' requirements not met." );
            return false;
        }

        try {
            // Load dependencies first
            $required_modules = $manifest['requires']['modules'] ?? [];
            foreach ( $required_modules as $dep ) {
                if ( ! isset( $this->instances[ $dep ] ) ) {
                    $this->load_module( $dep );
                }
            }

            // Process database schema (creates tables / runs migrations)
            if ( isset( $manifest['database'] ) ) {
                $this->core->database->process_module_schema( $slug, $manifest['database'] );
            }

            // Register capabilities and apply default mappings
            // (apply_default_mappings only adds caps that don't already exist,
            // so it's safe to call on every load — not just first activation)
            if ( isset( $manifest['capabilities'] ) ) {
                $this->core->roles->register_module_capabilities( $slug, $manifest );
            }
            if ( isset( $manifest['default_role_mapping'] ) ) {
                $this->core->roles->apply_default_mappings( $manifest['default_role_mapping'] );
            }

            // Register shortcodes
            if ( isset( $manifest['shortcodes'] ) ) {
                $this->register_shortcodes( $slug, $manifest['shortcodes'] );
            }

            // Load the main module class
            $class_file = EL_CORE_MODULES_DIR . "{$slug}/class-{$slug}-module.php";
            if ( ! file_exists( $class_file ) ) {
                error_log( "EL Core: Module class file missing: {$class_file}" );
                throw new \Exception( "Module class file not found: class-{$slug}-module.php" );
            }

            require_once $class_file;

            // Convert slug to class name: events → EL_Events_Module
            $class_name = 'EL_' . str_replace( '-', '_', ucwords( $slug, '-' ) ) . '_Module';

            if ( ! class_exists( $class_name ) ) {
                error_log( "EL Core: Module class '{$class_name}' not found after loading {$class_file}" );
                throw new \Exception( "Module class '{$class_name}' not found" );
            }

            $this->instances[ $slug ] = $class_name::instance( $this->core );
        } catch ( \Throwable $e ) {
            error_log( "EL Core: Module '{$slug}' failed to load: " . $e->getMessage() );
            error_log( "EL Core: Stack trace: " . $e->getTraceAsString() );
            // Deactivate the broken module so it doesn't crash the site again
            $this->active = array_values( array_diff( $this->active, [ $slug ] ) );
            $this->core->settings->set_active_modules( $this->active );
            
            if ( is_admin() ) {
                add_action( 'admin_notices', function() use ( $slug, $e ) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>EL Core:</strong> Module "' . esc_html( $slug ) . '" was automatically deactivated due to an error: ';
                    echo esc_html( $e->getMessage() );
                    echo '<br><small>File: ' . esc_html( $e->getFile() ) . ' (line ' . $e->getLine() . ')</small>';
                    echo '</p></div>';
                });
            }
            return false;
        }

        return true;
    }

    /**
     * Check if a module's requirements are met
     */
    private function check_requirements( array $manifest ): bool {
        $requires = $manifest['requires'] ?? [];

        // Check PHP version
        if ( isset( $requires['php'] ) && version_compare( PHP_VERSION, $requires['php'], '<' ) ) {
            return false;
        }

        // Check EL Core version
        if ( isset( $requires['el_core'] ) && version_compare( EL_CORE_VERSION, $requires['el_core'], '<' ) ) {
            return false;
        }

        return true;
    }

    /**
     * Register shortcodes declared in a module's manifest
     */
    private function register_shortcodes( string $slug, array $shortcodes ): void {
        foreach ( $shortcodes as $sc ) {
            $tag  = $sc['tag'];
            $file = EL_CORE_MODULES_DIR . "{$slug}/" . $sc['file'];

            if ( ! file_exists( $file ) ) {
                error_log( "EL Core: Shortcode file missing: {$file}" );
                continue;
            }

            // Load the shortcode file (it should define a function named el_shortcode_{tag})
            require_once $file;

            $function_name = 'el_shortcode_' . str_replace( '-', '_', str_replace( 'el_', '', $tag ) );

            if ( function_exists( $function_name ) ) {
                add_shortcode( $tag, $function_name );
            } else {
                error_log( "EL Core: Shortcode function '{$function_name}' not found for tag '{$tag}'" );
            }
        }
    }

    // ═══════════════════════════════════════════
    // MODULE MANAGEMENT (Admin UI calls these)
    // ═══════════════════════════════════════════

    /**
     * Activate a module
     */
    public function activate( string $slug ): bool {
        if ( ! isset( $this->discovered[ $slug ] ) ) {
            return false;
        }

        if ( in_array( $slug, $this->active, true ) ) {
            return true; // Already active
        }

        $manifest = $this->discovered[ $slug ];

        // Auto-activate dependencies
        $required_modules = $manifest['requires']['modules'] ?? [];
        foreach ( $required_modules as $dep ) {
            $this->activate( $dep );
        }

        // Add to active list
        $this->active[] = $slug;
        $this->core->settings->set_active_modules( $this->active );

        // Load it now
        $this->load_module( $slug );

        // Apply default role mappings on first activation
        if ( isset( $manifest['capabilities'] ) ) {
            $this->core->roles->register_module_capabilities( $slug, $manifest );
        }
        
        if ( isset( $manifest['default_role_mapping'] ) ) {
            $this->core->roles->apply_default_mappings( $manifest['default_role_mapping'] );
        }

        do_action( 'el_core_module_activated', $slug, $manifest );

        return true;
    }

    /**
     * Deactivate a module
     */
    public function deactivate( string $slug ): bool {
        // Check if other active modules depend on this one
        foreach ( $this->active as $active_slug ) {
            if ( $active_slug === $slug ) continue;
            $manifest = $this->discovered[ $active_slug ] ?? [];
            $deps = $manifest['requires']['modules'] ?? [];
            if ( in_array( $slug, $deps, true ) ) {
                // Can't deactivate — something depends on it
                return false;
            }
        }

        $this->active = array_values( array_diff( $this->active, [ $slug ] ) );
        $this->core->settings->set_active_modules( $this->active );

        unset( $this->instances[ $slug ] );

        do_action( 'el_core_module_deactivated', $slug );

        return true;
    }

    // ═══════════════════════════════════════════
    // GETTERS
    // ═══════════════════════════════════════════

    /**
     * Get all discovered modules with their manifests
     */
    public function get_discovered(): array {
        return $this->discovered;
    }

    /**
     * Get active module slugs
     */
    public function get_active(): array {
        return $this->active;
    }

    /**
     * Check if a specific module is active
     */
    public function is_active( string $slug ): bool {
        return in_array( $slug, $this->active, true );
    }

    /**
     * Get a loaded module instance
     */
    public function get_instance( string $slug ): ?object {
        return $this->instances[ $slug ] ?? null;
    }

    /**
     * Get modules that depend on a given module
     */
    public function get_dependents( string $slug ): array {
        $dependents = [];
        foreach ( $this->active as $active_slug ) {
            $manifest = $this->discovered[ $active_slug ] ?? [];
            $deps = $manifest['requires']['modules'] ?? [];
            if ( in_array( $slug, $deps, true ) ) {
                $dependents[] = $active_slug;
            }
        }
        return $dependents;
    }
}
