# Project Management Module - API Documentation

## Overview

The Project Management module provides a complete project management system with phases, tasks, kanban boards, file attachments, activity logging, and team collaboration. It integrates with Fluent CRM for client management.

**Version:** 1.0.0  
**Dependencies:** EL Core 1.0.0+, Fluent CRM Integration module

---

## Features

- **Project Management**: Create, edit, and track projects with multiple statuses
- **Phase Templates**: Auto-create phases based on project type
- **Task Management**: Create tasks within phases with assignments, priorities, and due dates
- **Kanban Board**: Drag-and-drop interface for visual project management
- **Progress Tracking**: Automatic calculation of phase and project completion
- **Activity Logging**: Complete audit trail of all project actions
- **Fluent CRM Integration**: Link projects to companies and contacts

---

## Database Tables

### `el_projects`
Stores project information.

**Columns:**
- `id` (bigint, PK, auto_increment)
- `company_id` (bigint, nullable) - Fluent CRM company ID
- `contact_id` (bigint, nullable) - Fluent CRM contact ID
- `name` (varchar 255) - Project name
- `type` (varchar 50) - Project type (expand_site, afterschool_guru, etc.)
- `status` (varchar 50) - Project status (discovery, in_progress, client_review, paused, completed, cancelled)
- `staging_url` (text, nullable)
- `live_url` (text, nullable)
- `start_date` (date, nullable)
- `target_launch_date` (date, nullable)
- `actual_launch_date` (date, nullable)
- `notes` (longtext, nullable)
- `created_at` (datetime)
- `updated_at` (datetime)

### `el_phases`
Stores project phases.

**Columns:**
- `id` (bigint, PK, auto_increment)
- `project_id` (bigint, FK to el_projects)
- `template_id` (bigint, nullable, FK to el_phase_templates)
- `name` (varchar 255)
- `description` (text, nullable)
- `status` (varchar 50) - not_started, in_progress, completed
- `display_order` (int, default 0)
- `started_at` (datetime, nullable)
- `completed_at` (datetime, nullable)
- `created_at` (datetime)
- `updated_at` (datetime)

### `el_tasks`
Stores tasks within phases.

**Columns:**
- `id` (bigint, PK, auto_increment)
- `phase_id` (bigint, FK to el_phases)
- `title` (varchar 255)
- `description` (text, nullable)
- `assigned_to` (bigint, nullable, FK to wp_users)
- `status` (varchar 50) - todo, in_progress, completed
- `priority` (varchar 20) - low, normal, high, urgent
- `due_date` (date, nullable)
- `display_order` (int, default 0)
- `completed_at` (datetime, nullable)
- `created_at` (datetime)
- `updated_at` (datetime)

### `el_files`
Stores file attachments for projects.

**Columns:**
- `id` (bigint, PK, auto_increment)
- `project_id` (bigint, FK to el_projects)
- `uploaded_by` (bigint, FK to wp_users)
- `file_name` (varchar 255)
- `file_path` (text)
- `file_size` (int)
- `file_type` (varchar 100)
- `description` (text, nullable)
- `created_at` (datetime)

### `el_activity_log`
Stores activity history for audit trail.

**Columns:**
- `id` (bigint, PK, auto_increment)
- `project_id` (bigint, FK to el_projects)
- `user_id` (bigint, FK to wp_users)
- `action` (varchar 100)
- `entity_type` (varchar 50) - project, phase, task, file
- `entity_id` (bigint)
- `description` (text)
- `ip_address` (varchar 45, nullable)
- `created_at` (datetime)

### `el_phase_templates`
Stores reusable phase templates for different project types.

**Columns:**
- `id` (bigint, PK, auto_increment)
- `project_type` (varchar 50)
- `name` (varchar 255)
- `description` (text, nullable)
- `display_order` (int, default 0)
- `is_active` (boolean, default true)
- `created_at` (datetime)
- `updated_at` (datetime)

---

## Capabilities

The module defines the following capabilities:

- `manage_projects` - Full project management access
- `create_projects` - Create new projects
- `edit_projects` - Edit existing projects
- `delete_projects` - Delete projects
- `view_projects` - View projects (read-only)
- `manage_tasks` - Create, edit, and delete tasks
- `assign_tasks` - Assign tasks to users
- `update_project_status` - Update project status (for kanban)

**Default Role Mapping:**
- **Administrator**: All capabilities
- **Editor**: create_projects, edit_projects, view_projects, manage_tasks, assign_tasks

---

## Module Settings

- `default_project_type` (string, default: "expand_site") - Default project type for new projects
- `enable_kanban_view` (boolean, default: true) - Enable/disable kanban board view
- `auto_create_phases` (boolean, default: true) - Auto-create phases from templates when creating projects

---

## Public API

### Get Module Instance

```php
$pm = EL_Project_Management_Module::instance();
```

### Project Methods

#### `get_projects(array $args = []): array`
Get all projects with optional filters.

**Parameters:**
- `$args` (array) - Query arguments
  - `where` (array) - WHERE conditions
  - `order_by` (string) - ORDER BY clause
  - `limit` (int) - LIMIT clause
  - `offset` (int) - OFFSET clause

**Returns:** Array of project objects

**Example:**
```php
// Get all active projects
$active_projects = $pm->get_projects([
    'where' => [
        'status' => ['IN' => ['discovery', 'in_progress']]
    ],
    'order_by' => 'created_at DESC'
]);

// Get projects for a specific company
$company_projects = $pm->get_projects([
    'where' => ['company_id' => 123]
]);
```

#### `get_project(int $project_id): ?object`
Get a single project by ID.

**Parameters:**
- `$project_id` (int) - Project ID

**Returns:** Project object or null if not found

**Example:**
```php
$project = $pm->get_project(15);
if ($project) {
    echo "Project: " . $project->name;
}
```

### Phase Methods

#### `get_project_phases(int $project_id): array`
Get all phases for a project, ordered by display_order.

**Parameters:**
- `$project_id` (int) - Project ID

**Returns:** Array of phase objects

**Example:**
```php
$phases = $pm->get_project_phases(15);
foreach ($phases as $phase) {
    echo $phase->name . " - " . $phase->status;
}
```

### Task Methods

#### `get_phase_tasks(int $phase_id): array`
Get all tasks for a phase, ordered by display_order.

**Parameters:**
- `$phase_id` (int) - Phase ID

**Returns:** Array of task objects

**Example:**
```php
$tasks = $pm->get_phase_tasks(42);
foreach ($tasks as $task) {
    echo $task->title . " - " . $task->status;
}
```

---

## AJAX Handlers

All AJAX handlers use the standard EL Core AJAX format with `el_core_ajax_*` actions.

### `el_core_ajax_get_project`
Get project data for editing.

**Request:**
```javascript
$.ajax({
    url: elCore.ajaxUrl,
    type: 'POST',
    data: {
        action: 'el_core_ajax_get_project',
        nonce: elCore.nonce,
        project_id: 15
    }
});
```

**Response:**
```json
{
    "success": true,
    "data": {
        "project": {
            "id": 15,
            "name": "ABC Nonprofit Website",
            "company_id": 123,
            "type": "expand_site",
            "status": "in_progress",
            ...
        }
    }
}
```

### `el_core_ajax_get_task`
Get task data for editing.

**Request:**
```javascript
$.ajax({
    url: elCore.ajaxUrl,
    type: 'POST',
    data: {
        action: 'el_core_ajax_get_task',
        nonce: elCore.nonce,
        task_id: 87
    }
});
```

**Response:**
```json
{
    "success": true,
    "data": {
        "task": {
            "id": 87,
            "phase_id": 42,
            "title": "Design homepage mockup",
            "status": "todo",
            "priority": "high",
            ...
        }
    }
}
```

### `el_core_ajax_toggle_task_status`
Toggle task status between completed and todo.

**Request:**
```javascript
$.ajax({
    url: elCore.ajaxUrl,
    type: 'POST',
    data: {
        action: 'el_core_ajax_toggle_task_status',
        nonce: elCore.nonce,
        task_id: 87,
        project_id: 15
    }
});
```

**Response:**
```json
{
    "success": true,
    "data": {
        "status": "completed",
        "message": "Task status updated"
    }
}
```

### `el_core_ajax_update_project_status`
Update project status (used by kanban drag-and-drop).

**Request:**
```javascript
$.ajax({
    url: elCore.ajaxUrl,
    type: 'POST',
    data: {
        action: 'el_core_ajax_update_project_status',
        nonce: elCore.nonce,
        project_id: 15,
        status: 'client_review'
    }
});
```

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Project status updated"
    }
}
```

---

## Admin Pages

### Projects List
**URL:** `wp-admin/admin.php?page=el-core-projects`

**Views:**
- **Table View** (default): `?page=el-core-projects&view=table`
- **Kanban View**: `?page=el-core-projects&view=kanban`

**Filters:**
- `status` - Filter by project status
- `type` - Filter by project type

### Project Detail
**URL:** `wp-admin/admin.php?page=el-core-projects&project_id={id}`

**Sections:**
- Project information (company, contact, URLs, dates, notes)
- Phases & Tasks (collapsible accordion with inline task management)

---

## Integration with Fluent CRM

The Project Management module integrates with the Fluent CRM Integration module to link projects to companies and contacts.

**Example:**
```php
$pm = EL_Project_Management_Module::instance();
$crm = EL_FluentCRM_Integration_Module::instance();

$project = $pm->get_project(15);

if ($project->company_id && $crm->is_available()) {
    $company = $crm->get_company($project->company_id);
    echo "Client: " . $company->name;
}

if ($project->contact_id && $crm->is_available()) {
    $contact = $crm->get_contact($project->contact_id);
    echo "Contact: " . $contact->full_name;
}
```

---

## Hooks & Filters

### Actions

**`el_pm_project_created`**
Fired when a new project is created.
```php
do_action('el_pm_project_created', $project_id, $project_data);
```

**`el_pm_project_updated`**
Fired when a project is updated.
```php
do_action('el_pm_project_updated', $project_id, $project_data);
```

**`el_pm_task_status_changed`**
Fired when a task status changes.
```php
do_action('el_pm_task_status_changed', $task_id, $old_status, $new_status);
```

### Filters

**`el_pm_project_types`**
Filter the available project types.
```php
$project_types = apply_filters('el_pm_project_types', [
    'expand_site' => 'Expand Site',
    'afterschool_guru' => 'Afterschool Guru',
    'expand_partners' => 'Expand Partners',
    'els_consulting' => 'ELS Consulting'
]);
```

**`el_pm_task_priorities`**
Filter the available task priorities.
```php
$priorities = apply_filters('el_pm_task_priorities', [
    'low' => 'Low',
    'normal' => 'Normal',
    'high' => 'High',
    'urgent' => 'Urgent'
]);
```

---

## JavaScript API

The module includes JavaScript for interactive features.

### Modal Functions

**`closeProjectModal()`**
Close the project edit/add modal.

**`closeTaskModal()`**
Close the task edit/add modal.

**`togglePhase(phaseId)`**
Toggle the visibility of a phase's content.

### Kanban Drag & Drop

The kanban board supports native HTML5 drag-and-drop for moving projects between status columns. Status updates are automatically saved via AJAX.

---

## Styling

The module uses CSS custom properties for theming, integrating with EL Core's brand CSS variables:

```css
:root {
    --el-pm-primary: var(--el-primary, #00A8B5);
    --el-pm-secondary: var(--el-secondary, #001E4E);
    --el-pm-success: #10B981;
    --el-pm-warning: #F59E0B;
    --el-pm-error: #EF4444;
}
```

All styles are contained in `assets/admin.css` and only load on project management pages.

---

## Future Enhancements

Potential features for future versions:

1. **File Attachments** - Upload and attach files to projects
2. **Time Tracking** - Track time spent on tasks
3. **Gantt Charts** - Visual timeline view of projects
4. **Project Templates** - Save and reuse entire project structures
5. **Email Notifications** - Notify team members of task assignments and updates
6. **Client Portal** - Allow clients to view project progress via shortcode
7. **Advanced Reporting** - Dashboard widgets and detailed reports
8. **Calendar View** - View tasks and deadlines in calendar format

---

## Support

For questions or issues with the Project Management module, please refer to the main EL Core documentation or contact Expanded Learning Solutions.
