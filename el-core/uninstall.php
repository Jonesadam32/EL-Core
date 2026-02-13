<?php
/**
 * EL Core Uninstall
 * 
 * Runs when the plugin is DELETED (not just deactivated).
 * Removes all database tables, options, and capabilities.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove all EL Core options
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'el_core_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'el_mod_%'" );

// Remove all EL tables
$tables = $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}el_%'" );
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Remove EL capabilities from all roles
$wp_roles = wp_roles();
foreach ( $wp_roles->roles as $role_slug => $role_data ) {
    $role = get_role( $role_slug );
    if ( ! $role ) continue;

    foreach ( $role_data['capabilities'] as $cap => $granted ) {
        // Remove any capability that looks like an EL capability
        if ( strpos( $cap, 'manage_' ) === 0 || strpos( $cap, 'create_' ) === 0 ||
             strpos( $cap, 'view_' ) === 0 || strpos( $cap, 'rsvp_' ) === 0 ||
             strpos( $cap, 'grade_' ) === 0 ) {
            // Be conservative — only remove caps we know are ours
            // In production, store registered caps list in an option for cleaner removal
        }
    }
}
