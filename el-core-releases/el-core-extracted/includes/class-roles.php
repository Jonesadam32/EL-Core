<?php
/**
 * EL Core Roles & Capabilities Engine
 * 
 * Manages the flexible role system. Modules declare capabilities,
 * administrators map them to roles through the admin UI.
 * 
 * Key principle: code checks CAPABILITIES, not role names.
 * Each installation defines its own roles and maps capabilities to them.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Roles {

    private EL_Settings $settings;

    /**
     * All registered capabilities from active modules
     * Format: ['manage_courses' => 'lms', 'view_events' => 'events', ...]
     */
    private array $registered_caps = [];

    public function __construct( EL_Settings $settings ) {
        $this->settings = $settings;
    }

    // ═══════════════════════════════════════════
    // CAPABILITY REGISTRATION
    // ═══════════════════════════════════════════

    /**
     * Register capabilities from a module manifest
     * Called by Module Loader during module loading
     */
    public function register_module_capabilities( string $slug, array $manifest ): void {
        $caps = $manifest['capabilities'] ?? [];

        foreach ( $caps as $cap ) {
            $this->registered_caps[ $cap ] = $slug;
        }
    }

    /**
     * Apply default role mappings (first-time module activation)
     * Only applies mappings that don't already exist
     */
    public function apply_default_mappings( array $mappings ): void {
        foreach ( $mappings as $role_name => $caps ) {
            $role = get_role( $role_name );
            if ( ! $role ) continue;

            foreach ( $caps as $cap ) {
                if ( ! $role->has_cap( $cap ) ) {
                    $role->add_cap( $cap );
                }
            }
        }
    }

    // ═══════════════════════════════════════════
    // CAPABILITY CHECKING
    // ═══════════════════════════════════════════

    /**
     * Check if the current user has a capability
     */
    public function current_user_can( string $capability ): bool {
        return current_user_can( $capability );
    }

    /**
     * Check if a specific user has a capability
     */
    public function user_can( int $user_id, string $capability ): bool {
        return user_can( $user_id, $capability );
    }

    // ═══════════════════════════════════════════
    // ROLE MANAGEMENT (Admin UI)
    // ═══════════════════════════════════════════

    /**
     * Get all WordPress roles with their EL capabilities
     */
    public function get_roles_with_caps(): array {
        $wp_roles = wp_roles();
        $result   = [];

        foreach ( $wp_roles->roles as $role_slug => $role_data ) {
            $el_caps = [];
            foreach ( $this->registered_caps as $cap => $module ) {
                $el_caps[ $cap ] = [
                    'module'  => $module,
                    'granted' => isset( $role_data['capabilities'][ $cap ] ) && $role_data['capabilities'][ $cap ],
                ];
            }

            $result[ $role_slug ] = [
                'name'    => $role_data['name'],
                'el_caps' => $el_caps,
            ];
        }

        return $result;
    }

    /**
     * Get all registered EL capabilities grouped by module
     */
    public function get_capabilities_by_module(): array {
        $grouped = [];
        foreach ( $this->registered_caps as $cap => $module ) {
            $grouped[ $module ][] = $cap;
        }
        return $grouped;
    }

    /**
     * Update role capabilities from admin form submission
     * 
     * @param array $role_caps Format: ['administrator' => ['manage_courses' => true, 'view_events' => false], ...]
     */
    public function update_role_capabilities( array $role_caps ): void {
        foreach ( $role_caps as $role_slug => $caps ) {
            $role = get_role( $role_slug );
            if ( ! $role ) continue;

            // Only touch EL-registered capabilities (never modify WordPress core caps)
            foreach ( $this->registered_caps as $cap => $module ) {
                if ( isset( $caps[ $cap ] ) && $caps[ $cap ] ) {
                    $role->add_cap( $cap );
                } else {
                    $role->remove_cap( $cap );
                }
            }
        }
    }

    /**
     * Create a custom role
     * Used when clients need roles beyond WordPress defaults
     */
    public function create_role( string $slug, string $display_name, array $capabilities = [] ): ?\WP_Role {
        // Build capability array (WordPress expects ['cap_name' => true])
        $caps = [];
        foreach ( $capabilities as $cap ) {
            $caps[ $cap ] = true;
        }

        // Always include basic WordPress caps for logged-in access
        $caps['read'] = true;

        return add_role( $slug, $display_name, $caps );
    }

    /**
     * Remove a custom role
     */
    public function remove_role( string $slug ): void {
        remove_role( $slug );
    }

    /**
     * Remove all EL capabilities from all roles (for uninstall)
     */
    public function cleanup_all_capabilities(): void {
        $wp_roles = wp_roles();

        foreach ( $wp_roles->roles as $role_slug => $role_data ) {
            $role = get_role( $role_slug );
            if ( ! $role ) continue;

            foreach ( $this->registered_caps as $cap => $module ) {
                $role->remove_cap( $cap );
            }
        }
    }
}
