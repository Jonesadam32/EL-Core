<?php
/**
 * EL Core — General Settings (Dashboard Overview)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$core    = EL_Core::instance();
$brand   = $core->settings->get_brand();
$modules = $core->modules->get_discovered();
$active  = $core->modules->get_active();
?>

<div class="wrap el-core-admin">
    <h1>EL Core Dashboard</h1>

    <div class="el-admin-grid">
        <!-- System Status -->
        <div class="el-admin-card">
            <h2>System Status</h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <td><strong>EL Core Version</strong></td>
                        <td><?php echo esc_html( EL_CORE_VERSION ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Organization</strong></td>
                        <td><?php echo esc_html( $brand['org_name'] ?: '(Not set)' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Active Modules</strong></td>
                        <td><?php echo count( $active ); ?> of <?php echo count( $modules ); ?> available</td>
                    </tr>
                    <tr>
                        <td><strong>PHP Version</strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong>WordPress Version</strong></td>
                        <td><?php echo get_bloginfo( 'version' ); ?></td>
                    </tr>
                    <tr>
                        <td><strong>AI Configured</strong></td>
                        <td><?php echo $core->ai->is_configured() ? '✅ Yes' : '❌ No — <a href="' . admin_url('admin.php?page=el-core-brand') . '">Configure</a>'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Quick Links -->
        <div class="el-admin-card">
            <h2>Quick Setup</h2>
            <p>Get started by configuring your installation:</p>
            <ol>
                <li><a href="<?php echo admin_url( 'admin.php?page=el-core-brand' ); ?>"><strong>Brand Settings</strong></a> — Set your colors, logo, and fonts</li>
                <li><a href="<?php echo admin_url( 'admin.php?page=el-core-modules' ); ?>"><strong>Modules</strong></a> — Activate the features you need</li>
                <li><a href="<?php echo admin_url( 'admin.php?page=el-core-roles' ); ?>"><strong>Roles</strong></a> — Configure permissions for your team</li>
            </ol>
        </div>

        <!-- Active Modules -->
        <div class="el-admin-card el-admin-card-full">
            <h2>Active Modules</h2>
            <?php if ( empty( $active ) ) : ?>
                <p>No modules activated yet. <a href="<?php echo admin_url( 'admin.php?page=el-core-modules' ); ?>">Activate modules →</a></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th>Version</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $active as $slug ) :
                            $mod = $modules[ $slug ] ?? null;
                            if ( ! $mod ) continue;
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html( $mod['name'] ); ?></strong></td>
                            <td><?php echo esc_html( $mod['version'] ?? '1.0.0' ); ?></td>
                            <td><?php echo esc_html( $mod['description'] ?? '' ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
