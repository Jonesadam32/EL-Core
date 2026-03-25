<?php
/**
 * EL Core — Module Manager (view only — form processing is in handle_modules_form())
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core       = EL_Core::instance();
$discovered = $core->modules->get_discovered();
$active     = $core->modules->get_active();

// ── Show load errors (from transient — set by load_module() or handle_modules_form()) ──
$load_errors = get_transient( 'el_core_module_errors' );
if ( ! empty( $load_errors ) ) {
    delete_transient( 'el_core_module_errors' );
    foreach ( $load_errors as $err ) {
        echo '<div class="notice notice-error"><p>' . $err . '</p></div>';
    }
}

// ── Show deactivation warnings ──
$module_warnings = get_transient( 'el_core_module_warnings' );
if ( ! empty( $module_warnings ) ) {
    delete_transient( 'el_core_module_warnings' );
    foreach ( $module_warnings as $w ) {
        echo '<div class="notice notice-warning"><p>' . $w . '</p></div>';
    }
}

// ── Show saved confirmation ──
if ( get_transient( 'el_core_module_saved' ) ) {
    delete_transient( 'el_core_module_saved' );
    echo '<div class="notice notice-success"><p>Module configuration saved!</p></div>';
}
?>

<div class="wrap el-core-admin">
    <h1>Module Manager</h1>
    <p>Activate or deactivate feature modules for this installation. Dependencies are resolved automatically.</p>

    <form method="post">
        <?php wp_nonce_field( 'el_core_modules_nonce' ); ?>

        <table class="widefat striped el-modules-table">
            <thead>
                <tr>
                    <th style="width: 40px;">Active</th>
                    <th>Module</th>
                    <th>Version</th>
                    <th>Description</th>
                    <th>Dependencies</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $discovered ) ) : ?>
                    <tr>
                        <td colspan="6">No modules found in the <code>modules/</code> directory.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $discovered as $slug => $manifest ) :
                        $is_active  = in_array( $slug, $active, true );
                        $deps       = $manifest['requires']['modules'] ?? [];
                        $dependents = $core->modules->get_dependents( $slug );
                        $has_dependents = ! empty( $dependents );
                        ?>
                    <tr>
                        <td>
                            <input
                                type="checkbox"
                                name="active_modules[]"
                                value="<?php echo esc_attr( $slug ); ?>"
                                <?php checked( $is_active ); ?>
                                <?php disabled( $is_active && $has_dependents ); ?>
                            />
                        </td>
                        <td>
                            <strong><?php echo esc_html( $manifest['name'] ); ?></strong>
                            <br><code><?php echo esc_html( $slug ); ?></code>
                        </td>
                        <td><?php echo esc_html( $manifest['version'] ?? '1.0.0' ); ?></td>
                        <td><?php echo esc_html( $manifest['description'] ?? '' ); ?></td>
                        <td>
                            <?php if ( ! empty( $deps ) ) : ?>
                                Requires: <?php echo esc_html( implode( ', ', $deps ) ); ?>
                            <?php else : ?>
                                <span style="color: #999;">None</span>
                            <?php endif; ?>
                            <?php if ( $has_dependents ) : ?>
                                <br><em>Required by: <?php echo esc_html( implode( ', ', $dependents ) ); ?></em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $is_active ) : ?>
                                <span style="color: green;">● Active</span>
                            <?php else : ?>
                                <span style="color: #999;">○ Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p class="submit">
            <input type="submit" name="el_save_modules" class="button-primary" value="Save Module Configuration" />
        </p>
    </form>
</div>
