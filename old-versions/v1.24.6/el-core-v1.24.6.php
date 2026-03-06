<?php
/**
 * Plugin Name: EL Core
 * Plugin URI: https://expandedlearningsolutions.com
 * Description: Modular platform for educational organizations. Provides LMS, events, certificates, analytics, and more — all configurable per installation.
 * Version: 1.24.6
 * Author: Expanded Learning Solutions LLC
 * Author URI: https://expandedlearningsolutions.com
 * License: GPL v2 or later
 * Text Domain: el-core
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Plugin Constants ──
define( 'EL_CORE_VERSION', '1.24.6' );
define( 'EL_CORE_FILE', __FILE__ );
define( 'EL_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'EL_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'EL_CORE_MODULES_DIR', EL_CORE_DIR . 'modules/' );

// ── Safety: Clear stale module list during activation test ──
// WordPress defines WP_SANDBOX_SCRAPING when testing if a plugin crashes
if ( defined( 'WP_SANDBOX_SCRAPING' ) && WP_SANDBOX_SCRAPING ) {
    update_option( 'el_core_modules', [] );
    update_option( 'el_core_schema_versions', [] );
}

// ── PHP Version Check ──
if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>EL Core</strong> requires PHP 8.0 or higher. ';
        echo 'You are running PHP ' . PHP_VERSION . '. Please upgrade.';
        echo '</p></div>';
    });
    return;
}

// ── Load Core ──
require_once EL_CORE_DIR . 'includes/functions.php';
require_once EL_CORE_DIR . 'includes/class-el-core.php';

// ── Boot ──
function el_core() {
    try {
        return EL_Core::instance();
    } catch ( \Throwable $e ) {
        error_log( 'EL Core boot error: ' . $e->getMessage() );
        add_action( 'admin_notices', function() use ( $e ) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>EL Core Error:</strong> ' . esc_html( $e->getMessage() );
            echo '</p></div>';
        });
        return null;
    }
}

// Initialize on plugins_loaded (priority 10 — early enough for other plugins to hook in)
add_action( 'plugins_loaded', 'el_core', 10 );

// ── Activation / Deactivation ──
register_activation_hook( __FILE__, function() {
    // Ensure core class is available
    require_once EL_CORE_DIR . 'includes/functions.php';
    require_once EL_CORE_DIR . 'includes/class-el-core.php';

    // Reset modules to empty on activation to prevent stale module references
    update_option( 'el_core_modules', [] );
    update_option( 'el_core_schema_versions', [] );

    // Set default settings on first activation
    if ( ! get_option( 'el_core_brand' ) ) {
        update_option( 'el_core_brand', [
            'org_name'        => get_bloginfo( 'name' ),
            'primary_color'   => '#1a1a2e',
            'secondary_color' => '#16213e',
            'accent_color'    => '#e94560',
            'font_heading'    => 'Inter, sans-serif',
            'font_body'       => 'Inter, sans-serif',
            'logo_url'        => '',
        ]);
    }

    // Flush rewrite rules
    flush_rewrite_rules();
});

register_deactivation_hook( __FILE__, function() {
    flush_rewrite_rules();
});
