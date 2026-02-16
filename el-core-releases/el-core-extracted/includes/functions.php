<?php
/**
 * EL Core Global Helper Functions
 * 
 * These functions provide convenient access to the core system
 * from anywhere: themes, modules, templates.
 * 
 * They are the API boundary between EL Core and the outside world.
 * The theme should use THESE functions, not reach into class internals.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ═══════════════════════════════════════════
// BRAND & SETTINGS
// ═══════════════════════════════════════════

/**
 * Get all brand settings
 */
function el_core_get_brand(): array {
    return el_core()->settings->get_brand();
}

/**
 * Get brand colors as an associative array
 */
function el_core_get_brand_colors(): array {
    $brand = el_core()->settings->get_brand();
    return [
        'primary'   => $brand['primary_color'],
        'secondary' => $brand['secondary_color'],
        'accent'    => $brand['accent_color'],
    ];
}

/**
 * Get the organization name
 */
function el_core_get_org_name(): string {
    return el_core()->settings->get( 'brand', 'org_name', get_bloginfo( 'name' ) );
}

/**
 * Get the logo URL
 */
function el_core_get_logo_url(): string {
    return el_core()->settings->get( 'brand', 'logo_url', '' );
}

/**
 * Get font family for headings
 */
function el_core_get_font_heading(): string {
    return el_core()->settings->get( 'brand', 'font_heading', 'Inter, sans-serif' );
}

/**
 * Get font family for body text
 */
function el_core_get_font_body(): string {
    return el_core()->settings->get( 'brand', 'font_body', 'Inter, sans-serif' );
}

// ═══════════════════════════════════════════
// MODULES
// ═══════════════════════════════════════════

/**
 * Check if a module is active
 */
function el_core_module_active( string $slug ): bool {
    return el_core()->modules->is_active( $slug );
}

/**
 * Get active module slugs
 */
function el_core_get_active_modules(): array {
    return el_core()->modules->get_active();
}

// ═══════════════════════════════════════════
// PERMISSIONS
// ═══════════════════════════════════════════

/**
 * Check if current user has an EL capability
 * Use this instead of current_user_can() for EL-specific capabilities
 */
function el_core_can( string $capability ): bool {
    return current_user_can( $capability );
}

/**
 * Check if a specific user has an EL capability
 */
function el_core_user_can( int $user_id, string $capability ): bool {
    return user_can( $user_id, $capability );
}

// ═══════════════════════════════════════════
// DATABASE
// ═══════════════════════════════════════════

/**
 * Get the database instance (for modules that need direct access)
 */
function el_core_db(): EL_Database {
    return el_core()->database;
}

// ═══════════════════════════════════════════
// AI
// ═══════════════════════════════════════════

/**
 * Quick AI completion (convenience wrapper)
 */
function el_core_ai_complete( string $prompt, string $system = '', array $options = [] ): array {
    return el_core()->ai->complete( array_merge( $options, [
        'prompt' => $prompt,
        'system' => $system,
    ]));
}
