<?php
/**
 * Projects Kanban Board Content
 * Included by projects-list.php when view=kanban
 * 
 * @package EL_Core
 * @subpackage Modules\Project_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Kanban columns configuration
$kanban_columns = [
    'discovery' => ['label' => __('Discovery', 'el-core'), 'color' => '#9333ea', 'icon' => 'search'],
    'in_progress' => ['label' => __('In Progress', 'el-core'), 'color' => '#2563eb', 'icon' => 'admin-tools'],
    'client_review' => ['label' => __('Client Review', 'el-core'), 'color' => '#f59e0b', 'icon' => 'visibility'],
    'paused' => ['label' => __('Paused', 'el-core'), 'color' => '#6b7280', 'icon' => 'controls-pause'],
    'completed' => ['label' => __('Completed', 'el-core'), 'color' => '#10b981', 'icon' => 'yes-alt']
];

// Group projects by status
$projects_by_status = [];
foreach ($kanban_columns as $status_key => $col) {
    $projects_by_status[$status_key] = [];
}
foreach ($projects as $project) {
    if (isset($projects_by_status[$project->status])) {
        $projects_by_status[$project->status][] = $project;
    }
}

?>

<div class="el-kanban-board">
    <?php foreach ($kanban_columns as $status_key => $column): ?>
        <div class="el-kanban-column" data-status="<?php echo esc_attr($status_key); ?>">
            <div class="el-kanban-column-header" style="border-top-color: <?php echo esc_attr($column['color']); ?>;">
                <span class="dashicons dashicons-<?php echo esc_attr($column['icon']); ?>" style="color: <?php echo esc_attr($column['color']); ?>;"></span>
                <h3><?php echo esc_html($column['label']); ?></h3>
                <span class="el-kanban-count"><?php echo count($projects_by_status[$status_key]); ?></span>
            </div>
            <div class="el-kanban-cards" data-status="<?php echo esc_attr($status_key); ?>">
                <?php foreach ($projects_by_status[$status_key] as $project): ?>
                    <?php
                    // Get phases for progress calculation
                    $phases = $db->query('phases', ['where' => ['project_id' => $project->id]]);
                    $completed_phases = $db->count('phases', ['project_id' => $project->id, 'status' => 'completed']);
                    $phase_count = count($phases);
                    $progress = $phase_count > 0 ? round(($completed_phases / $phase_count) * 100) : 0;
                    
                    // Get company name from Fluent CRM
                    $company_name = __('No Client', 'el-core');
                    if ($project->company_id && $crm->is_available()) {
                        $company = $crm->get_company($project->company_id);
                        if ($company) {
                            $company_name = $company->name;
                        }
                    }
                    ?>
                    <div class="el-kanban-card" data-project-id="<?php echo esc_attr($project->id); ?>" draggable="true">
                        <div class="el-kanban-card-header">
                            <span class="el-project-type el-type-<?php echo esc_attr($project->type); ?>">
                                <?php echo esc_html($project_types[$project->type] ?? $project->type); ?>
                            </span>
                        </div>
                        <a href="<?php echo esc_url(add_query_arg(['page' => 'el-core-projects', 'project_id' => $project->id], admin_url('admin.php'))); ?>" class="el-kanban-card-title">
                            <?php echo esc_html($project->name); ?>
                        </a>
                        <div class="el-kanban-card-client">
                            <span class="dashicons dashicons-building"></span>
                            <?php echo esc_html($company_name); ?>
                        </div>
                        <div class="el-kanban-card-progress">
                            <div class="el-progress-bar">
                                <div class="el-progress-fill" style="width: <?php echo esc_attr($progress); ?>%;"></div>
                            </div>
                            <span class="el-progress-text"><?php echo esc_html($progress); ?>%</span>
                        </div>
                        <?php if ($project->target_launch_date): ?>
                            <?php
                            $target_date = strtotime($project->target_launch_date);
                            $days_remaining = ceil(($target_date - time()) / 86400);
                            $is_overdue = $days_remaining < 0;
                            ?>
                            <div class="el-kanban-card-date <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <?php if ($is_overdue): ?>
                                    <span class="el-overdue-text"><?php echo abs($days_remaining); ?> <?php _e('days overdue', 'el-core'); ?></span>
                                <?php elseif ($days_remaining <= 7): ?>
                                    <span class="el-urgent-text"><?php echo $days_remaining; ?> <?php _e('days left', 'el-core'); ?></span>
                                <?php else: ?>
                                    <?php echo esc_html(date('M j, Y', $target_date)); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <?php if (empty($projects_by_status[$status_key])): ?>
                    <div class="el-kanban-empty">
                        <span class="dashicons dashicons-portfolio"></span>
                        <p><?php _e('No projects', 'el-core'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
