<?php
/**
 * Project Management Module
 * 
 * Complete project management system with phases, tasks, kanban boards,
 * file attachments, activity logging, and team collaboration.
 * 
 * @package EL_Core
 * @subpackage Modules
 */

if (!defined('ABSPATH')) {
    exit;
}

class EL_Project_Management_Module {
    
    private static ?EL_Project_Management_Module $instance = null;
    private EL_Core $core;
    private ?EL_FluentCRM_Integration_Module $crm = null;
    
    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->core = EL_Core::instance();
        $this->crm = EL_FluentCRM_Integration_Module::instance();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks(): void {
        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        
        // Handle form submissions
        add_action('admin_init', [$this, 'handle_form_submissions']);
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // Register AJAX handlers
        add_action('el_core_ajax_get_project', [$this, 'ajax_get_project']);
        add_action('el_core_ajax_get_task', [$this, 'ajax_get_task']);
        add_action('el_core_ajax_toggle_task_status', [$this, 'ajax_toggle_task_status']);
        add_action('el_core_ajax_update_project_status', [$this, 'ajax_update_project_status']);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook): void {
        // Only load on project management pages
        if (strpos($hook, 'el-core-projects') === false) {
            return;
        }
        
        $module_url = EL_CORE_URL . 'modules/project-management/';
        $version = $this->core->get_version();
        
        // Enqueue CSS
        wp_enqueue_style(
            'el-project-management-admin',
            $module_url . 'assets/admin.css',
            [],
            $version
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'el-project-management-admin',
            $module_url . 'assets/admin.js',
            ['jquery'],
            $version,
            true
        );
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'el-core',
            __('Projects', 'el-core'),
            __('Projects', 'el-core'),
            'view_projects',
            'el-core-projects',
            [$this, 'render_projects_page']
        );
    }
    
    /**
     * Handle form submissions
     */
    public function handle_form_submissions(): void {
        if (!isset($_POST['el_pm_action']) || !check_admin_referer('el_pm_action')) {
            return;
        }
        
        $action = sanitize_text_field($_POST['el_pm_action']);
        
        switch ($action) {
            case 'add_project':
                $this->handle_add_project();
                break;
            case 'edit_project':
                $this->handle_edit_project();
                break;
            case 'delete_project':
                $this->handle_delete_project();
                break;
            case 'add_task':
                $this->handle_add_task();
                break;
            case 'edit_task':
                $this->handle_edit_task();
                break;
            case 'delete_task':
                $this->handle_delete_task();
                break;
            case 'update_phase_status':
                $this->handle_update_phase_status();
                break;
        }
    }
    
    /**
     * Render projects page (list or detail)
     */
    public function render_projects_page(): void {
        // Check if viewing a specific project
        if (isset($_GET['project_id'])) {
            $this->render_project_detail(intval($_GET['project_id']));
            return;
        }
        
        // Check view mode (table or kanban)
        $view_mode = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'table';
        
        if ($view_mode === 'kanban') {
            include dirname(__FILE__) . '/admin/projects-kanban.php';
        } else {
            include dirname(__FILE__) . '/admin/projects-list.php';
        }
    }
    
    /**
     * Render project detail page
     */
    private function render_project_detail(int $project_id): void {
        $db = $this->core->database;
        
        // Get project
        $project = $db->get('projects', ['id' => $project_id]);
        if (!$project) {
            wp_die(__('Project not found', 'el-core'));
        }
        
        // Get company/contact info from Fluent CRM
        $company = null;
        $contact = null;
        if ($this->crm->is_available()) {
            if ($project->company_id) {
                $company = $this->crm->get_company($project->company_id);
            }
            if ($project->contact_id) {
                $contact = $this->crm->get_contact($project->contact_id);
            }
        }
        
        // Get phases
        $phases = $db->query('phases', [
            'where' => ['project_id' => $project_id],
            'order_by' => 'display_order ASC'
        ]);
        
        // Get phase templates for this project type
        $phase_templates = $db->query('phase_templates', [
            'where' => [
                'project_type' => $project->type,
                'is_active' => 1
            ],
            'order_by' => 'display_order ASC'
        ]);
        
        include dirname(__FILE__) . '/admin/project-detail.php';
    }
    
    // ==========================================
    // PROJECT CRUD HANDLERS
    // ==========================================
    
    /**
     * Handle add project form submission
     */
    private function handle_add_project(): void {
        if (!current_user_can('create_projects')) {
            wp_die(__('Insufficient permissions', 'el-core'));
        }
        
        $db = $this->core->database;
        
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'company_id' => !empty($_POST['company_id']) ? absint($_POST['company_id']) : null,
            'contact_id' => !empty($_POST['contact_id']) ? absint($_POST['contact_id']) : null,
            'type' => sanitize_text_field($_POST['type']),
            'status' => sanitize_text_field($_POST['status']),
            'staging_url' => esc_url_raw($_POST['staging_url'] ?? ''),
            'live_url' => esc_url_raw($_POST['live_url'] ?? ''),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'target_launch_date' => sanitize_text_field($_POST['target_launch_date'] ?? ''),
            'notes' => wp_kses_post($_POST['notes'] ?? '')
        ];
        
        $project_id = $db->insert('projects', $data);
        
        if ($project_id) {
            // Auto-create phases from templates if enabled
            $auto_create = $this->core->settings->get('mod_project_management', 'auto_create_phases', true);
            if ($auto_create) {
                $this->create_phases_from_template($project_id, $data['type']);
            }
            
            // Log activity
            $this->log_activity($project_id, 'project_created', 'project', $project_id, 
                sprintf(__('Created project: %s', 'el-core'), $data['name']));
            
            wp_redirect(add_query_arg(['page' => 'el-core-projects', 'message' => 'project_added'], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Handle edit project form submission
     */
    private function handle_edit_project(): void {
        if (!current_user_can('edit_projects')) {
            wp_die(__('Insufficient permissions', 'el-core'));
        }
        
        $project_id = absint($_POST['project_id']);
        $db = $this->core->database;
        
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'company_id' => !empty($_POST['company_id']) ? absint($_POST['company_id']) : null,
            'contact_id' => !empty($_POST['contact_id']) ? absint($_POST['contact_id']) : null,
            'type' => sanitize_text_field($_POST['type']),
            'status' => sanitize_text_field($_POST['status']),
            'staging_url' => esc_url_raw($_POST['staging_url'] ?? ''),
            'live_url' => esc_url_raw($_POST['live_url'] ?? ''),
            'start_date' => sanitize_text_field($_POST['start_date'] ?? ''),
            'target_launch_date' => sanitize_text_field($_POST['target_launch_date'] ?? ''),
            'actual_launch_date' => sanitize_text_field($_POST['actual_launch_date'] ?? ''),
            'notes' => wp_kses_post($_POST['notes'] ?? '')
        ];
        
        $updated = $db->update('projects', $data, ['id' => $project_id]);
        
        if ($updated) {
            $this->log_activity($project_id, 'project_updated', 'project', $project_id,
                sprintf(__('Updated project: %s', 'el-core'), $data['name']));
            
            wp_redirect(add_query_arg(['page' => 'el-core-projects', 'project_id' => $project_id, 'message' => 'project_updated'], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Handle delete project form submission
     */
    private function handle_delete_project(): void {
        if (!current_user_can('delete_projects')) {
            wp_die(__('Insufficient permissions', 'el-core'));
        }
        
        $project_id = absint($_POST['project_id']);
        $db = $this->core->database;
        
        // Delete related data
        $db->delete('tasks', ['phase_id' => ['IN' => $db->query('phases', ['where' => ['project_id' => $project_id], 'select' => 'id'])]]);
        $db->delete('phases', ['project_id' => $project_id]);
        $db->delete('files', ['project_id' => $project_id]);
        $db->delete('activity_log', ['project_id' => $project_id]);
        
        // Delete project
        $deleted = $db->delete('projects', ['id' => $project_id]);
        
        if ($deleted) {
            wp_redirect(add_query_arg(['page' => 'el-core-projects', 'message' => 'project_deleted'], admin_url('admin.php')));
            exit;
        }
    }
    
    // ==========================================
    // TASK CRUD HANDLERS
    // ==========================================
    
    /**
     * Handle add task form submission
     */
    private function handle_add_task(): void {
        if (!current_user_can('manage_tasks')) {
            wp_die(__('Insufficient permissions', 'el-core'));
        }
        
        $db = $this->core->database;
        $phase_id = absint($_POST['phase_id']);
        $project_id = absint($_POST['project_id']);
        
        // Get max display order for this phase
        $max_order = $db->query('tasks', [
            'where' => ['phase_id' => $phase_id],
            'select' => 'MAX(display_order) as max_order'
        ]);
        $display_order = ($max_order[0]->max_order ?? 0) + 1;
        
        $data = [
            'phase_id' => $phase_id,
            'title' => sanitize_text_field($_POST['title']),
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'assigned_to' => !empty($_POST['assigned_to']) ? absint($_POST['assigned_to']) : null,
            'status' => 'todo',
            'priority' => sanitize_text_field($_POST['priority'] ?? 'normal'),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? ''),
            'display_order' => $display_order
        ];
        
        $task_id = $db->insert('tasks', $data);
        
        if ($task_id) {
            $this->log_activity($project_id, 'task_created', 'task', $task_id,
                sprintf(__('Created task: %s', 'el-core'), $data['title']));
            
            wp_redirect(add_query_arg(['page' => 'el-core-projects', 'project_id' => $project_id, 'message' => 'task_added'], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Handle edit task form submission
     */
    private function handle_edit_task(): void {
        if (!current_user_can('manage_tasks')) {
            wp_die(__('Insufficient permissions', 'el-core'));
        }
        
        $db = $this->core->database;
        $task_id = absint($_POST['task_id']);
        $project_id = absint($_POST['project_id']);
        
        $data = [
            'title' => sanitize_text_field($_POST['title']),
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'assigned_to' => !empty($_POST['assigned_to']) ? absint($_POST['assigned_to']) : null,
            'status' => sanitize_text_field($_POST['status']),
            'priority' => sanitize_text_field($_POST['priority']),
            'due_date' => sanitize_text_field($_POST['due_date'] ?? '')
        ];
        
        // Set completed_at timestamp if status changed to completed
        if ($data['status'] === 'completed') {
            $data['completed_at'] = current_time('mysql');
        }
        
        $updated = $db->update('tasks', $data, ['id' => $task_id]);
        
        if ($updated) {
            $this->log_activity($project_id, 'task_updated', 'task', $task_id,
                sprintf(__('Updated task: %s', 'el-core'), $data['title']));
            
            wp_redirect(add_query_arg(['page' => 'el-core-projects', 'project_id' => $project_id, 'message' => 'task_updated'], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Handle delete task form submission
     */
    private function handle_delete_task(): void {
        if (!current_user_can('manage_tasks')) {
            wp_die(__('Insufficient permissions', 'el-core'));
        }
        
        $db = $this->core->database;
        $task_id = absint($_POST['task_id']);
        $project_id = absint($_POST['project_id']);
        
        $deleted = $db->delete('tasks', ['id' => $task_id]);
        
        if ($deleted) {
            $this->log_activity($project_id, 'task_deleted', 'task', $task_id,
                __('Deleted task', 'el-core'));
            
            wp_redirect(add_query_arg(['page' => 'el-core-projects', 'project_id' => $project_id, 'message' => 'task_deleted'], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Handle update phase status
     */
    private function handle_update_phase_status(): void {
        if (!current_user_can('edit_projects')) {
            wp_die(__('Insufficient permissions', 'el-core'));
        }
        
        $db = $this->core->database;
        $phase_id = absint($_POST['phase_id']);
        $project_id = absint($_POST['project_id']);
        $status = sanitize_text_field($_POST['status']);
        
        $data = ['status' => $status];
        
        // Set timestamps based on status
        if ($status === 'in_progress') {
            $data['started_at'] = current_time('mysql');
        } elseif ($status === 'completed') {
            $data['completed_at'] = current_time('mysql');
        }
        
        $updated = $db->update('phases', $data, ['id' => $phase_id]);
        
        if ($updated) {
            $phase = $db->get('phases', ['id' => $phase_id]);
            $this->log_activity($project_id, 'phase_status_changed', 'phase', $phase_id,
                sprintf(__('Phase "%s" status changed to %s', 'el-core'), $phase->name, $status));
            
            wp_redirect(add_query_arg(['page' => 'el-core-projects', 'project_id' => $project_id, 'message' => 'phase_updated'], admin_url('admin.php')));
            exit;
        }
    }
    
    // ==========================================
    // AJAX HANDLERS
    // ==========================================
    
    /**
     * AJAX: Get project data for edit modal
     */
    public function ajax_get_project(): void {
        if (!current_user_can('view_projects')) {
            EL_AJAX_Handler::error(__('Insufficient permissions', 'el-core'));
            return;
        }
        
        $project_id = absint($_POST['project_id'] ?? 0);
        if (!$project_id) {
            EL_AJAX_Handler::error(__('Invalid project ID', 'el-core'));
            return;
        }
        
        $db = $this->core->database;
        $project = $db->get('projects', ['id' => $project_id]);
        
        if (!$project) {
            EL_AJAX_Handler::error(__('Project not found', 'el-core'));
            return;
        }
        
        EL_AJAX_Handler::success(['project' => $project]);
    }
    
    /**
     * AJAX: Get task data for edit modal
     */
    public function ajax_get_task(): void {
        if (!current_user_can('view_projects')) {
            EL_AJAX_Handler::error(__('Insufficient permissions', 'el-core'));
            return;
        }
        
        $task_id = absint($_POST['task_id'] ?? 0);
        if (!$task_id) {
            EL_AJAX_Handler::error(__('Invalid task ID', 'el-core'));
            return;
        }
        
        $db = $this->core->database;
        $task = $db->get('tasks', ['id' => $task_id]);
        
        if (!$task) {
            EL_AJAX_Handler::error(__('Task not found', 'el-core'));
            return;
        }
        
        EL_AJAX_Handler::success(['task' => $task]);
    }
    
    /**
     * AJAX: Toggle task status (completed <-> todo)
     */
    public function ajax_toggle_task_status(): void {
        if (!current_user_can('manage_tasks')) {
            EL_AJAX_Handler::error(__('Insufficient permissions', 'el-core'));
            return;
        }
        
        $task_id = absint($_POST['task_id'] ?? 0);
        $project_id = absint($_POST['project_id'] ?? 0);
        
        if (!$task_id || !$project_id) {
            EL_AJAX_Handler::error(__('Invalid parameters', 'el-core'));
            return;
        }
        
        $db = $this->core->database;
        $task = $db->get('tasks', ['id' => $task_id]);
        
        if (!$task) {
            EL_AJAX_Handler::error(__('Task not found', 'el-core'));
            return;
        }
        
        // Toggle status
        $new_status = ($task->status === 'completed') ? 'todo' : 'completed';
        $data = ['status' => $new_status];
        
        if ($new_status === 'completed') {
            $data['completed_at'] = current_time('mysql');
        } else {
            $data['completed_at'] = null;
        }
        
        $updated = $db->update('tasks', $data, ['id' => $task_id]);
        
        if ($updated) {
            $this->log_activity($project_id, 'task_status_changed', 'task', $task_id,
                sprintf(__('Task "%s" marked as %s', 'el-core'), $task->title, $new_status));
            
            EL_AJAX_Handler::success([
                'status' => $new_status,
                'message' => __('Task status updated', 'el-core')
            ]);
        } else {
            EL_AJAX_Handler::error(__('Failed to update task', 'el-core'));
        }
    }
    
    /**
     * AJAX: Update project status (for kanban drag-and-drop)
     */
    public function ajax_update_project_status(): void {
        if (!current_user_can('update_project_status')) {
            EL_AJAX_Handler::error(__('Insufficient permissions', 'el-core'));
            return;
        }
        
        $project_id = absint($_POST['project_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        
        if (!$project_id || !$new_status) {
            EL_AJAX_Handler::error(__('Invalid parameters', 'el-core'));
            return;
        }
        
        $db = $this->core->database;
        $updated = $db->update('projects', ['status' => $new_status], ['id' => $project_id]);
        
        if ($updated) {
            $project = $db->get('projects', ['id' => $project_id]);
            $this->log_activity($project_id, 'project_status_changed', 'project', $project_id,
                sprintf(__('Project "%s" moved to %s', 'el-core'), $project->name, $new_status));
            
            EL_AJAX_Handler::success(['message' => __('Project status updated', 'el-core')]);
        } else {
            EL_AJAX_Handler::error(__('Failed to update project', 'el-core'));
        }
    }
    
    // ==========================================
    // HELPER METHODS
    // ==========================================
    
    /**
     * Create phases from templates for a new project
     */
    private function create_phases_from_template(int $project_id, string $project_type): void {
        $db = $this->core->database;
        
        $templates = $db->query('phase_templates', [
            'where' => [
                'project_type' => $project_type,
                'is_active' => 1
            ],
            'order_by' => 'display_order ASC'
        ]);
        
        foreach ($templates as $template) {
            $db->insert('phases', [
                'project_id' => $project_id,
                'template_id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'display_order' => $template->display_order,
                'status' => 'not_started'
            ]);
        }
    }
    
    /**
     * Log activity for audit trail
     */
    private function log_activity(int $project_id, string $action, string $entity_type, int $entity_id, string $description): void {
        $db = $this->core->database;
        
        $db->insert('activity_log', [
            'project_id' => $project_id,
            'user_id' => get_current_user_id(),
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    }
    
    /**
     * Get all projects
     */
    public function get_projects(array $args = []): array {
        $db = $this->core->database;
        return $db->query('projects', $args);
    }
    
    /**
     * Get a single project
     */
    public function get_project(int $project_id): ?object {
        $db = $this->core->database;
        return $db->get('projects', ['id' => $project_id]);
    }
    
    /**
     * Get phases for a project
     */
    public function get_project_phases(int $project_id): array {
        $db = $this->core->database;
        return $db->query('phases', [
            'where' => ['project_id' => $project_id],
            'order_by' => 'display_order ASC'
        ]);
    }
    
    /**
     * Get tasks for a phase
     */
    public function get_phase_tasks(int $phase_id): array {
        $db = $this->core->database;
        return $db->query('tasks', [
            'where' => ['phase_id' => $phase_id],
            'order_by' => 'display_order ASC'
        ]);
    }
}
