<?php
/**
 * EL Core — Dashboard Overview
 *
 * Rebuilt using EL_Admin_UI framework components.
 *
 * @package EL_Core
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core    = EL_Core::instance();
$brand   = $core->settings->get_brand();
$modules = $core->modules->get_discovered();
$active  = $core->modules->get_active();

// ── Data ──────────────────────────────────────────────────────────────────────

$org_name    = $brand['org_name'] ?: '(Not set)';
$ai_ok       = $core->ai->is_configured();
$module_count = count( $active ) . ' of ' . count( $modules );

// ── Page ──────────────────────────────────────────────────────────────────────

$output = '';

// Page header
$output .= EL_Admin_UI::page_header( [
    'title'    => 'EL Core',
    'subtitle' => 'v' . EL_CORE_VERSION . ' — ' . $org_name,
    'actions'  => [
        [
            'label'   => 'Brand Settings',
            'variant' => 'secondary',
            'icon'    => 'admin-appearance',
            'url'     => admin_url( 'admin.php?page=el-core-brand' ),
        ],
        [
            'label'   => 'Manage Modules',
            'variant' => 'primary',
            'icon'    => 'plugins',
            'url'     => admin_url( 'admin.php?page=el-core-modules' ),
        ],
    ],
] );

// AI warning notice (only if not configured)
if ( ! $ai_ok ) {
    $output .= EL_Admin_UI::notice( [
        'type'    => 'warning',
        'message' => 'AI is not configured. <a href="' . admin_url( 'admin.php?page=el-core-brand' ) . '">Add your API key →</a>',
    ] );
}

// Stats grid
$output .= EL_Admin_UI::stats_grid( [
    [
        'icon'    => 'plugins',
        'number'  => count( $active ),
        'label'   => 'Active Modules',
        'variant' => 'primary',
        'url'     => admin_url( 'admin.php?page=el-core-modules' ),
    ],
    [
        'icon'    => 'admin-users',
        'number'  => count( get_editable_roles() ),
        'label'   => 'User Roles',
        'variant' => 'info',
        'url'     => admin_url( 'admin.php?page=el-core-roles' ),
    ],
    [
        'icon'    => 'superhero',
        'number'  => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        'label'   => 'PHP Version',
        'variant' => 'success',
    ],
    [
        'icon'    => 'wordpress-alt',
        'number'  => get_bloginfo( 'version' ),
        'label'   => 'WordPress Version',
        'variant' => 'success',
    ],
] );

// ── System status card ────────────────────────────────────────────────────────

$status_rows  = '';
$status_rows .= EL_Admin_UI::detail_row( [
    'icon'  => 'admin-settings',
    'label' => 'EL Core Version',
    'value' => esc_html( EL_CORE_VERSION ),
] );
$status_rows .= EL_Admin_UI::detail_row( [
    'icon'  => 'building',
    'label' => 'Organization',
    'value' => esc_html( $org_name ),
] );
$status_rows .= EL_Admin_UI::detail_row( [
    'icon'  => 'plugins',
    'label' => 'Active Modules',
    'value' => esc_html( $module_count ),
] );
$status_rows .= EL_Admin_UI::detail_row( [
    'icon'  => 'art',
    'label' => 'AI Integration',
    'value' => $ai_ok
        ? '<span style="color:#10B981;">✓ Configured</span>'
        : '<span style="color:#EF4444;">✗ Not configured</span> — <a href="' . admin_url( 'admin.php?page=el-core-brand' ) . '">Configure</a>',
] );

$output .= EL_Admin_UI::card( [
    'title'   => 'System Status',
    'icon'    => 'admin-tools',
    'content' => $status_rows,
] );

// ── Active modules card ───────────────────────────────────────────────────────

if ( empty( $active ) ) {
    $modules_content = EL_Admin_UI::empty_state( [
        'icon'    => 'plugins',
        'title'   => 'No modules activated',
        'message' => 'Activate modules to enable features for this installation.',
        'action'  => [
            'label'   => 'Go to Modules',
            'variant' => 'primary',
            'url'     => admin_url( 'admin.php?page=el-core-modules' ),
        ],
    ] );
} else {
    $rows    = [];
    $columns = [
        [ 'key' => 'name',        'label' => 'Module' ],
        [ 'key' => 'version',     'label' => 'Version' ],
        [ 'key' => 'description', 'label' => 'Description' ],
    ];

    foreach ( $active as $slug ) {
        $mod = $modules[ $slug ] ?? null;
        if ( ! $mod ) continue;
        $rows[] = [
            'name'        => '<strong>' . esc_html( $mod['name'] ) . '</strong>',
            'version'     => esc_html( $mod['version'] ?? '1.0.0' ),
            'description' => esc_html( $mod['description'] ?? '' ),
        ];
    }

    $modules_content = EL_Admin_UI::data_table( [
        'columns' => $columns,
        'rows'    => $rows,
    ] );
}

$output .= EL_Admin_UI::card( [
    'title'   => 'Active Modules',
    'icon'    => 'plugins',
    'content' => $modules_content,
    'actions' => [
        [
            'label'   => 'Manage',
            'variant' => 'ghost',
            'url'     => admin_url( 'admin.php?page=el-core-modules' ),
        ],
    ],
] );

// ── Quick setup card (only shown when org_name or AI not set) ─────────────────

if ( ! $brand['org_name'] || ! $ai_ok ) {
    $setup_content  = '<p style="margin-top:0; color:var(--el-admin-gray);">Complete these steps to finish setting up EL Core:</p>';
    $setup_content .= '<ol style="margin:0; color:var(--el-admin-navy); line-height:2;">';
    if ( ! $brand['org_name'] ) {
        $setup_content .= '<li><a href="' . admin_url( 'admin.php?page=el-core-brand' ) . '"><strong>Set your organization name</strong></a> — Brand Settings</li>';
    }
    if ( ! $ai_ok ) {
        $setup_content .= '<li><a href="' . admin_url( 'admin.php?page=el-core-brand' ) . '"><strong>Add your AI API key</strong></a> — Brand Settings</li>';
    }
    $setup_content .= '<li><a href="' . admin_url( 'admin.php?page=el-core-roles' ) . '"><strong>Configure role permissions</strong></a> — Role Manager</li>';
    $setup_content .= '</ol>';

    $output .= EL_Admin_UI::card( [
        'title'   => 'Quick Setup',
        'icon'    => 'flag',
        'content' => $setup_content,
    ] );
}

// ── Render ────────────────────────────────────────────────────────────────────

echo EL_Admin_UI::wrap( $output );
