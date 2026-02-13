<?php
/**
 * EL Core — Role Manager
 * Map module capabilities to WordPress roles
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core       = EL_Core::instance();
$roles_data = $core->roles->get_roles_with_caps();
$caps_by_mod = $core->roles->get_capabilities_by_module();

// Handle form submission
if ( isset( $_POST['el_save_roles'] ) && check_admin_referer( 'el_core_roles_nonce' ) ) {
    $role_caps = [];

    foreach ( $roles_data as $role_slug => $role_info ) {
        $role_caps[ $role_slug ] = [];
        foreach ( $role_info['el_caps'] as $cap => $cap_info ) {
            $field_name = "cap_{$role_slug}_{$cap}";
            $role_caps[ $role_slug ][ $cap ] = isset( $_POST[ $field_name ] );
        }
    }

    $core->roles->update_role_capabilities( $role_caps );

    // Refresh data
    $roles_data = $core->roles->get_roles_with_caps();

    echo '<div class="notice notice-success"><p>Role permissions saved!</p></div>';
}
?>

<div class="wrap el-core-admin">
    <h1>Role & Permission Manager</h1>
    <p>Configure which capabilities each role has. Capabilities are defined by active modules.</p>

    <?php if ( empty( $caps_by_mod ) ) : ?>
        <div class="notice notice-warning">
            <p>No capabilities registered. <a href="<?php echo admin_url( 'admin.php?page=el-core-modules' ); ?>">Activate some modules</a> to see their capabilities here.</p>
        </div>
    <?php else : ?>

    <form method="post">
        <?php wp_nonce_field( 'el_core_roles_nonce' ); ?>

        <?php foreach ( $caps_by_mod as $module => $caps ) : ?>
            <h2 style="margin-top: 30px;"><?php echo esc_html( ucwords( str_replace( '-', ' ', $module ) ) ); ?> Module</h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Capability</th>
                        <?php foreach ( $roles_data as $role_slug => $role_info ) : ?>
                            <th style="text-align: center;"><?php echo esc_html( $role_info['name'] ); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $caps as $cap ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $cap ); ?></code></td>
                        <?php foreach ( $roles_data as $role_slug => $role_info ) :
                            $is_granted = $role_info['el_caps'][ $cap ]['granted'] ?? false;
                            $field_name = "cap_{$role_slug}_{$cap}";
                        ?>
                            <td style="text-align: center;">
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr( $field_name ); ?>"
                                    <?php checked( $is_granted ); ?>
                                />
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>

        <p class="submit">
            <input type="submit" name="el_save_roles" class="button-primary" value="Save Permissions" />
        </p>
    </form>

    <?php endif; ?>
</div>
