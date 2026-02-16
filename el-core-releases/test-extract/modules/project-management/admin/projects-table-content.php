<?php
/**
 * Projects Table View Content
 * Included by projects-list.php when view=table
 * 
 * @package EL_Core
 * @subpackage Modules\Project_Management
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="el-projects-table-wrap">
    <table class="wp-list-table widefat fixed striped el-projects-table">
        <thead>
            <tr>
                <th><?php _e('Project Name', 'el-core'); ?></th>
                <th><?php _e('Client', 'el-core'); ?></th>
                <th><?php _e('Type', 'el-core'); ?></th>
                <th><?php _e('Status', 'el-core'); ?></th>
                <th><?php _e('Progress', 'el-core'); ?></th>
                <th><?php _e('Target Date', 'el-core'); ?></th>
                <th><?php _e('Actions', 'el-core'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $project): ?>
                <?php
                $crm = EL_FluentCRM_Integration_Module::instance();
                $db = EL_Core::instance()->database;
                
                // Get progress
                $phases = $db->query('phases', ['where' => ['project_id' => $project->id]]);
                $completed_phases = $db->count('phases', ['project_id' => $project->id, 'status' => 'completed']);
                $phase_count = count($phases);
                $progress = $phase_count > 0 ? round(($completed_phases / $phase_count) * 100) : 0;
                
                // Get company name
                $company_name = __('No Client', 'el-core');
                if ($project->company_id && $crm->is_available()) {
                    $company = $crm->get_company($project->company_id);
                    if ($company) {
                        $company_name = $company->name;
                    }
                }
                ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'el-core-projects', 'project_id' => $project->id], admin_url('admin.php'))); ?>">
                            <strong><?php echo esc_html($project->name); ?></strong>
                        </a>
                    </td>
                    <td><?php echo esc_html($company_name); ?></td>
                    <td>
                        <span class="el-project-type el-type-<?php echo esc_attr($project->type); ?>">
                            <?php echo esc_html($project_types[$project->type] ?? $project->type); ?>
                        </span>
                    </td>
                    <td>
                        <span class="el-project-status el-status-<?php echo esc_attr($project->status); ?>">
                            <?php echo esc_html($status_labels[$project->status] ?? $project->status); ?>
                        </span>
                    </td>
                    <td>
                        <div class="el-progress-bar">
                            <div class="el-progress-fill" style="width: <?php echo esc_attr($progress); ?>%;"></div>
                        </div>
                        <span class="el-progress-text"><?php echo esc_html($completed_phases); ?>/<?php echo esc_html($phase_count); ?> <?php _e('phases', 'el-core'); ?></span>
                    </td>
                    <td>
                        <?php if ($project->target_launch_date): ?>
                            <?php echo esc_html(date('M j, Y', strtotime($project->target_launch_date))); ?>
                        <?php else: ?>
                            <span class="el-text-muted"><?php _e('Not set', 'el-core'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'el-core-projects', 'project_id' => $project->id], admin_url('admin.php'))); ?>" class="button button-small">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <button type="button" class="button button-small edit-project-btn" data-project-id="<?php echo esc_attr($project->id); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
