<?php
/**
 * Project Detail Page
 * 
 * @package EL_Core
 * @subpackage Modules\Project_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Project detail variables passed from module class
// $project, $company, $contact, $phases, $phase_templates

$pm = EL_Project_Management_Module::instance();
$db = EL_Core::instance()->database;

// Get tasks grouped by phase
$tasks_by_phase = [];
$all_tasks = $db->query('tasks', [
    'where' => ['phase_id' => ['IN' => array_column($phases, 'id')]],
    'order_by' => 'display_order ASC'
]);
foreach ($all_tasks as $task) {
    if (!isset($tasks_by_phase[$task->phase_id])) {
        $tasks_by_phase[$task->phase_id] = [];
    }
    $tasks_by_phase[$task->phase_id][] = $task;
}

// Get users for assignment
$users = get_users(['fields' => ['ID', 'display_name']]);

// Labels
$project_types = [
    'expand_site' => __('Expand Site', 'el-core'),
    'afterschool_guru' => __('Afterschool Guru', 'el-core'),
    'expand_partners' => __('Expand Partners', 'el-core'),
    'els_consulting' => __('ELS Consulting', 'el-core')
];

$status_labels = [
    'discovery' => __('Discovery', 'el-core'),
    'in_progress' => __('In Progress', 'el-core'),
    'client_review' => __('Client Review', 'el-core'),
    'paused' => __('Paused', 'el-core'),
    'completed' => __('Completed', 'el-core'),
    'cancelled' => __('Cancelled', 'el-core')
];

$phase_status_labels = [
    'not_started' => __('Not Started', 'el-core'),
    'in_progress' => __('In Progress', 'el-core'),
    'completed' => __('Completed', 'el-core')
];

$priority_labels = [
    'low' => __('Low', 'el-core'),
    'normal' => __('Normal', 'el-core'),
    'high' => __('High', 'el-core'),
    'urgent' => __('Urgent', 'el-core')
];

?>

<div class="wrap el-project-detail-wrap">
    <!-- Header -->
    <div class="el-page-header">
        <div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=el-core-projects')); ?>" class="el-back-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span> <?php _e('Back to Projects', 'el-core'); ?>
            </a>
            <h1><?php echo esc_html($project->name); ?></h1>
            <div class="el-project-meta">
                <span class="el-project-type el-type-<?php echo esc_attr($project->type); ?>">
                    <?php echo esc_html($project_types[$project->type] ?? $project->type); ?>
                </span>
                <span class="el-project-status el-status-<?php echo esc_attr($project->status); ?>">
                    <?php echo esc_html($status_labels[$project->status] ?? $project->status); ?>
                </span>
            </div>
        </div>
        <button type="button" class="button button-primary edit-project-btn" data-project-id="<?php echo esc_attr($project->id); ?>">
            <span class="dashicons dashicons-edit"></span> <?php _e('Edit Project', 'el-core'); ?>
        </button>
    </div>
    
    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['message']); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Project Info Card -->
    <div class="el-detail-card">
        <div class="el-card-header">
            <h2><span class="dashicons dashicons-info"></span> <?php _e('Project Information', 'el-core'); ?></h2>
        </div>
        <div class="el-info-grid">
            <?php if ($company): ?>
                <div class="el-info-item">
                    <label><?php _e('Client Company', 'el-core'); ?></label>
                    <value><?php echo esc_html($company->name); ?></value>
                </div>
            <?php endif; ?>
            <?php if ($contact): ?>
                <div class="el-info-item">
                    <label><?php _e('Primary Contact', 'el-core'); ?></label>
                    <value><?php echo esc_html($contact->full_name); ?></value>
                </div>
            <?php endif; ?>
            <?php if ($project->staging_url): ?>
                <div class="el-info-item">
                    <label><?php _e('Staging URL', 'el-core'); ?></label>
                    <value><a href="<?php echo esc_url($project->staging_url); ?>" target="_blank"><?php echo esc_html($project->staging_url); ?></a></value>
                </div>
            <?php endif; ?>
            <?php if ($project->live_url): ?>
                <div class="el-info-item">
                    <label><?php _e('Live URL', 'el-core'); ?></label>
                    <value><a href="<?php echo esc_url($project->live_url); ?>" target="_blank"><?php echo esc_html($project->live_url); ?></a></value>
                </div>
            <?php endif; ?>
            <?php if ($project->start_date): ?>
                <div class="el-info-item">
                    <label><?php _e('Start Date', 'el-core'); ?></label>
                    <value><?php echo esc_html(date('F j, Y', strtotime($project->start_date))); ?></value>
                </div>
            <?php endif; ?>
            <?php if ($project->target_launch_date): ?>
                <div class="el-info-item">
                    <label><?php _e('Target Launch', 'el-core'); ?></label>
                    <value><?php echo esc_html(date('F j, Y', strtotime($project->target_launch_date))); ?></value>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($project->notes): ?>
            <div class="el-project-notes">
                <label><?php _e('Notes', 'el-core'); ?></label>
                <p><?php echo wp_kses_post(nl2br($project->notes)); ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Phases & Tasks -->
    <div class="el-detail-card">
        <div class="el-card-header">
            <h2><span class="dashicons dashicons-editor-ol"></span> <?php _e('Phases & Tasks', 'el-core'); ?></h2>
        </div>
        
        <?php if (empty($phases)): ?>
            <div class="el-empty-state">
                <span class="dashicons dashicons-editor-ol"></span>
                <p><?php _e('No phases have been created for this project.', 'el-core'); ?></p>
            </div>
        <?php else: ?>
            <div class="el-phases-accordion">
                <?php foreach ($phases as $index => $phase): 
                    $phase_tasks = $tasks_by_phase[$phase->id] ?? [];
                    $completed_task_count = count(array_filter($phase_tasks, fn($t) => $t->status === 'completed'));
                    $task_count = count($phase_tasks);
                    $task_progress = $task_count > 0 ? round(($completed_task_count / $task_count) * 100) : 0;
                ?>
                    <div class="el-phase-item el-phase-<?php echo esc_attr($phase->status); ?>">
                        <div class="el-phase-header" onclick="togglePhase(<?php echo esc_attr($phase->id); ?>)">
                            <div class="el-phase-left">
                                <span class="el-phase-number"><?php echo esc_html($index + 1); ?></span>
                                <div>
                                    <h4><?php echo esc_html($phase->name); ?></h4>
                                    <?php if ($task_count > 0): ?>
                                        <span class="el-task-count"><?php echo esc_html($completed_task_count); ?>/<?php echo esc_html($task_count); ?> <?php _e('tasks', 'el-core'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="el-phase-right">
                                <form method="post" action="" onclick="event.stopPropagation();">
                                    <?php wp_nonce_field('el_pm_action'); ?>
                                    <input type="hidden" name="el_pm_action" value="update_phase_status">
                                    <input type="hidden" name="phase_id" value="<?php echo esc_attr($phase->id); ?>">
                                    <input type="hidden" name="project_id" value="<?php echo esc_attr($project->id); ?>">
                                    <select name="status" onchange="this.form.submit()" class="el-phase-status-select">
                                        <?php foreach ($phase_status_labels as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($phase->status, $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <span class="dashicons dashicons-arrow-down-alt2 el-accordion-arrow"></span>
                            </div>
                        </div>
                        
                        <div class="el-phase-content" id="phase-<?php echo esc_attr($phase->id); ?>" style="display:none;">
                            <?php if ($phase->description): ?>
                                <p class="el-phase-description"><?php echo esc_html($phase->description); ?></p>
                            <?php endif; ?>
                            
                            <?php if ($task_count > 0): ?>
                                <div class="el-progress-bar">
                                    <div class="el-progress-fill" style="width: <?php echo esc_attr($task_progress); ?>%;"></div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Tasks -->
                            <div class="el-tasks-section">
                                <div class="el-tasks-header">
                                    <h5><?php _e('Tasks', 'el-core'); ?></h5>
                                    <button type="button" class="button button-small add-task-btn" data-phase-id="<?php echo esc_attr($phase->id); ?>" data-project-id="<?php echo esc_attr($project->id); ?>">
                                        <span class="dashicons dashicons-plus-alt2"></span> <?php _e('Add Task', 'el-core'); ?>
                                    </button>
                                </div>
                                
                                <?php if (empty($phase_tasks)): ?>
                                    <p class="el-no-tasks"><?php _e('No tasks yet. Click "Add Task" to create one.', 'el-core'); ?></p>
                                <?php else: ?>
                                    <div class="el-tasks-list">
                                        <?php foreach ($phase_tasks as $task): 
                                            $assigned_user = $task->assigned_to ? get_user_by('ID', $task->assigned_to) : null;
                                        ?>
                                            <div class="el-task-item el-task-<?php echo esc_attr($task->status); ?>">
                                                <input type="checkbox" 
                                                    class="el-task-checkbox" 
                                                    data-task-id="<?php echo esc_attr($task->id); ?>" 
                                                    data-project-id="<?php echo esc_attr($project->id); ?>"
                                                    <?php checked($task->status, 'completed'); ?>>
                                                <div class="el-task-content">
                                                    <span class="el-task-title <?php echo $task->status === 'completed' ? 'completed' : ''; ?>">
                                                        <?php echo esc_html($task->title); ?>
                                                    </span>
                                                    <?php if ($task->description): ?>
                                                        <p class="el-task-description"><?php echo esc_html($task->description); ?></p>
                                                    <?php endif; ?>
                                                    <div class="el-task-meta">
                                                        <?php if ($assigned_user): ?>
                                                            <span><span class="dashicons dashicons-admin-users"></span> <?php echo esc_html($assigned_user->display_name); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($task->due_date): ?>
                                                            <span><span class="dashicons dashicons-calendar-alt"></span> <?php echo esc_html(date('M j', strtotime($task->due_date))); ?></span>
                                                        <?php endif; ?>
                                                        <span class="el-task-priority el-priority-<?php echo esc_attr($task->priority); ?>">
                                                            <?php echo esc_html($priority_labels[$task->priority] ?? $task->priority); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="el-task-actions">
                                                    <button type="button" class="button button-small edit-task-btn" data-task-id="<?php echo esc_attr($task->id); ?>">
                                                        <span class="dashicons dashicons-edit"></span>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Task Modal -->
<div id="el-task-modal" class="el-modal" style="display:none;">
    <div class="el-modal-content">
        <div class="el-modal-header">
            <h2 id="task-modal-title"><?php _e('Add Task', 'el-core'); ?></h2>
            <button type="button" class="el-modal-close">&times;</button>
        </div>
        <form method="post" action="" id="task-form">
            <?php wp_nonce_field('el_pm_action'); ?>
            <input type="hidden" name="el_pm_action" id="task-action" value="add_task">
            <input type="hidden" name="task_id" id="task-id" value="">
            <input type="hidden" name="phase_id" id="task-phase-id" value="">
            <input type="hidden" name="project_id" id="task-project-id" value="">
            
            <div class="el-form-row">
                <label for="task-title"><?php _e('Task Title', 'el-core'); ?> *</label>
                <input type="text" id="task-title" name="title" required>
            </div>
            
            <div class="el-form-row">
                <label for="task-description"><?php _e('Description', 'el-core'); ?></label>
                <textarea id="task-description" name="description" rows="3"></textarea>
            </div>
            
            <div class="el-form-row">
                <label for="task-assigned-to"><?php _e('Assigned To', 'el-core'); ?></label>
                <select id="task-assigned-to" name="assigned_to">
                    <option value=""><?php _e('Unassigned', 'el-core'); ?></option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="el-form-row">
                <label for="task-priority"><?php _e('Priority', 'el-core'); ?></label>
                <select id="task-priority" name="priority">
                    <?php foreach ($priority_labels as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="el-form-row">
                <label for="task-due-date"><?php _e('Due Date', 'el-core'); ?></label>
                <input type="date" id="task-due-date" name="due_date">
            </div>
            
            <div class="el-modal-footer">
                <button type="button" class="button" onclick="closeTaskModal()"><?php _e('Cancel', 'el-core'); ?></button>
                <button type="submit" class="button button-primary"><?php _e('Save Task', 'el-core'); ?></button>
            </div>
        </form>
    </div>
</div>
