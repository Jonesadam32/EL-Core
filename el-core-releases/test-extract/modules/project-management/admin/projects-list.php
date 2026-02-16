<?php
/**
 * Projects List Page - Table and Kanban Views
 * 
 * @package EL_Core
 * @subpackage Modules\Project_Management
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get module instance
$pm = EL_Project_Management_Module::instance();
$crm = EL_FluentCRM_Integration_Module::instance();
$db = EL_Core::instance()->database;

// Get view mode
$view_mode = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'table';

// Get filter values
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

// Build query
$where = [];
if ($filter_status) {
    $where['status'] = $filter_status;
}
if ($filter_type) {
    $where['type'] = $filter_type;
}

// Get projects
$projects = $db->query('projects', [
    'where' => $where,
    'order_by' => 'created_at DESC'
]);

// Get Fluent CRM companies for dropdowns
$companies = [];
if ($crm->is_available()) {
    $companies = $crm->get_companies(['limit' => 500]);
}

// Project type and status labels
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

?>

<div class="wrap el-projects-wrap">
    <div class="el-page-header">
        <div class="el-page-header-left">
            <h1><?php _e('Projects', 'el-core'); ?></h1>
            <div class="el-view-toggle">
                <a href="<?php echo esc_url(add_query_arg(['page' => 'el-core-projects', 'view' => 'table'], admin_url('admin.php'))); ?>" 
                   class="el-view-btn <?php echo $view_mode === 'table' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span>
                </a>
                <a href="<?php echo esc_url(add_query_arg(['page' => 'el-core-projects', 'view' => 'kanban'], admin_url('admin.php'))); ?>" 
                   class="el-view-btn <?php echo $view_mode === 'kanban' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-columns"></span>
                </a>
            </div>
        </div>
        <button type="button" class="button button-primary" id="add-project-btn">
            <span class="dashicons dashicons-plus-alt"></span> <?php _e('New Project', 'el-core'); ?>
        </button>
    </div>
    
    <?php if (isset($_GET['message'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['message']); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="el-filters-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="el-core-projects">
            <input type="hidden" name="view" value="<?php echo esc_attr($view_mode); ?>">
            <select name="status">
                <option value=""><?php _e('All Statuses', 'el-core'); ?></option>
                <?php foreach ($status_labels as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filter_status, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type">
                <option value=""><?php _e('All Types', 'el-core'); ?></option>
                <?php foreach ($project_types as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($filter_type, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php _e('Filter', 'el-core'); ?></button>
        </form>
    </div>
    
    <?php if (empty($projects)): ?>
        <div class="el-empty-state">
            <span class="dashicons dashicons-admin-site-alt3"></span>
            <h3><?php _e('No Projects Yet', 'el-core'); ?></h3>
            <p><?php _e('Get started by creating your first project.', 'el-core'); ?></p>
            <button type="button" class="button button-primary button-hero" id="add-first-project-btn">
                <?php _e('Create Your First Project', 'el-core'); ?>
            </button>
        </div>
    <?php elseif ($view_mode === 'kanban'): ?>
        <?php include dirname(__FILE__) . '/projects-kanban-content.php'; ?>
    <?php else: ?>
        <?php include dirname(__FILE__) . '/projects-table-content.php'; ?>
    <?php endif; ?>
</div>

<!-- Add/Edit Project Modal -->
<div id="el-project-modal" class="el-modal" style="display:none;">
    <div class="el-modal-content">
        <div class="el-modal-header">
            <h2 id="project-modal-title"><?php _e('Create New Project', 'el-core'); ?></h2>
            <button type="button" class="el-modal-close">&times;</button>
        </div>
        <form method="post" action="" id="project-form">
            <?php wp_nonce_field('el_pm_action'); ?>
            <input type="hidden" name="el_pm_action" id="project-action" value="add_project">
            <input type="hidden" name="project_id" id="project-id" value="">
            
            <div class="el-form-row">
                <label for="project-name"><?php _e('Project Name', 'el-core'); ?> *</label>
                <input type="text" id="project-name" name="name" required>
            </div>
            
            <div class="el-form-row">
                <label for="project-company"><?php _e('Client Company', 'el-core'); ?></label>
                <select id="project-company" name="company_id">
                    <option value=""><?php _e('Select a company...', 'el-core'); ?></option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo esc_attr($company['id']); ?>"><?php echo esc_html($company['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="el-form-row">
                <label for="project-type"><?php _e('Project Type', 'el-core'); ?> *</label>
                <select id="project-type" name="type" required>
                    <?php foreach ($project_types as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="el-form-row">
                <label for="project-status"><?php _e('Status', 'el-core'); ?> *</label>
                <select id="project-status" name="status" required>
                    <?php foreach ($status_labels as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="el-form-row">
                <label for="project-staging-url"><?php _e('Staging URL', 'el-core'); ?></label>
                <input type="url" id="project-staging-url" name="staging_url">
            </div>
            
            <div class="el-form-row">
                <label for="project-live-url"><?php _e('Live URL', 'el-core'); ?></label>
                <input type="url" id="project-live-url" name="live_url">
            </div>
            
            <div class="el-form-row">
                <label for="project-start-date"><?php _e('Start Date', 'el-core'); ?></label>
                <input type="date" id="project-start-date" name="start_date">
            </div>
            
            <div class="el-form-row">
                <label for="project-target-date"><?php _e('Target Launch Date', 'el-core'); ?></label>
                <input type="date" id="project-target-date" name="target_launch_date">
            </div>
            
            <div class="el-form-row">
                <label for="project-notes"><?php _e('Notes', 'el-core'); ?></label>
                <textarea id="project-notes" name="notes" rows="4"></textarea>
            </div>
            
            <div class="el-modal-footer">
                <button type="button" class="button" onclick="closeProjectModal()"><?php _e('Cancel', 'el-core'); ?></button>
                <button type="submit" class="button button-primary"><?php _e('Save Project', 'el-core'); ?></button>
            </div>
        </form>
    </div>
</div>
