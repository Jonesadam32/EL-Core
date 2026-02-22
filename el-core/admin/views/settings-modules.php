<?php
/**
 * EL Core — Module Manager
 * Toggle modules on/off with dependency resolution
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core       = EL_Core::instance();
$discovered = $core->modules->get_discovered();
$active     = $core->modules->get_active();

// Handle form submission
if ( isset( $_POST['el_save_modules'] ) && check_admin_referer( 'el_core_modules_nonce' ) ) {
    $new_active = $_POST['active_modules'] ?? [];
    $new_active = array_map( 'sanitize_text_field', $new_active );

    // Deactivate modules that were unchecked
    foreach ( $active as $slug ) {
        if ( ! in_array( $slug, $new_active, true ) ) {
            $result = $core->modules->deactivate( $slug );
            if ( ! $result ) {
                $dependents = $core->modules->get_dependents( $slug );
                echo '<div class="notice notice-error"><p>';
                echo 'Cannot deactivate <strong>' . esc_html( $discovered[$slug]['name'] ?? $slug ) . '</strong> — ';
                echo 'required by: ' . esc_html( implode( ', ', $dependents ) );
                echo '</p></div>';
            }
        }
    }

    // Activate newly checked modules
    foreach ( $new_active as $slug ) {
        if ( ! in_array( $slug, $active, true ) ) {
            $result = $core->modules->activate( $slug );
            if ( ! $result ) {
                echo '<div class="notice notice-error"><p>';
                echo 'Failed to activate <strong>' . esc_html( $discovered[$slug]['name'] ?? $slug ) . '</strong>. ';
                echo 'Check error log for details.';
                echo '</p></div>';
            }
        }
    }

    // Refresh
    $active = $core->modules->get_active();
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
