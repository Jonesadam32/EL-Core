# ⚠️ STOP — READ BEFORE DOING ANYTHING

**Your previous context is wrong.** Read these two files before writing any code:

1. **`START-HERE-NEXT-SESSION.md`** (repo root) — This is the shared state file between you and Claude. It replaces everything you thought you knew about deployment status and versions.
2. **This file** (`cursor-prompt-expand-site-v2.md`) — This replaces the previous expand-site prompt entirely. It accounts for everything you already built in project-management.

**Key corrections from what you had before:**
- EL Core is deployed on **expandedlearningsolutions.com**, NOT Region 6
- v1.2.7 exists but **v1.3.0 is current**
- You are **NOT building a new module** — you're evolving the existing `project-management` module you already built
- Your admin views must be **rebuilt using EL_Admin_UI framework** (added in v1.3.0, after you built them)
- WordPress MCP is NOT connected — do not use `wp_fs_write` or any MCP tools

**From now on:** Read `START-HERE-NEXT-SESSION.md` at the start of every session, and update it when you finish. This is how Claude and Cursor stay in sync.

**Start with Task 1 in Section 4 below: update module.json.**

---

# Cursor Prompt — Evolve Project Management into Expand Site

> **Purpose:** You (Cursor) already built the `project-management` module. This prompt tells you what needs to change to evolve it into the full Expand Site pipeline with the 8-stage client workflow, client portal shortcodes, and admin views rebuilt using the EL_Admin_UI framework.
>
> **Date:** February 20, 2026
> **Current EL Core Version:** 1.3.0 (in el-core.php)
> **Deployed to:** expandedlearningsolutions.com

---

## 1. WHAT YOU ALREADY BUILT

The `modules/project-management/` directory contains a working module with:

**Files:**
- `module.json` — manifest with 6 database tables, 8 capabilities, Fluent CRM dependency
- `class-project-management-module.php` — singleton module class with project/task/phase CRUD, AJAX handlers, activity logging
- `admin/projects-list.php` — project list page with table/kanban toggle
- `admin/projects-table-content.php` — table view partial
- `admin/projects-kanban-content.php` — kanban board partial
- `admin/project-detail.php` — single project view with phase accordion, tasks, and task modals
- `assets/admin.css` — module-specific admin styles
- `assets/admin.js` — modal and interaction handlers
- `README.md` — API documentation

**Database tables declared in module.json:**
- `el_projects` — main project record (name, type, status, URLs, dates, CRM links)
- `el_phases` — project phases (linked to templates)
- `el_tasks` — tasks within phases (assignments, priorities, due dates)
- `el_files` — file attachments
- `el_activity_log` — audit trail
- `el_phase_templates` — reusable phase templates by project type

**What works:**
- Project CRUD (create, edit, delete via form submissions)
- Phase management (auto-created from templates, status updates)
- Task management (create, edit, delete, toggle completion via AJAX)
- Kanban board with drag-and-drop status changes
- Table view with filters (status, type)
- Activity logging on all actions
- Fluent CRM integration (companies and contacts linked to projects)

---

## 2. WHAT NEEDS TO CHANGE

### Problem 1: Admin views use raw HTML instead of EL_Admin_UI

After you built the project-management module, `class-admin-ui.php` was added to EL Core (v1.3.0). This provides a shared admin UI framework that ALL module admin views must use. Your current admin views use raw HTML with custom CSS classes. They need to be rebuilt using `EL_Admin_UI::*` static methods.

**Rule:** No raw HTML for shared UI patterns. Use the framework methods. Read `includes/class-admin-ui.php` for the full API. Read `admin/views/settings-general.php` for the reference implementation.

### Problem 2: Generic phases need to become the 8-stage pipeline

The current system has generic, free-form phases. Expand Site needs a fixed 8-stage pipeline with specific approval gates, deliverables, and business rules at each stage.

### Problem 3: No client portal shortcodes

The module is admin-only. Clients need frontend shortcodes to view their project status, review deliverables, and submit feedback.

### Problem 4: No deliverable review or feedback system

The current detail view shows phases and tasks but has no structured way for clients to review deliverables and provide feedback with approval/revision/change-order tracking.

---

## 3. THE 8-STAGE PIPELINE

This is the core business process that the module must enforce:

| # | Stage | Name | Purpose | Has Client Gate |
|---|-------|------|---------|-----------------|
| 1 | qualification | Qualification | Filter budget fit, initial range quote | Yes — budget alignment |
| 2 | discovery | Discovery | Capture requirements, workflows, content inventory | Yes — discovery fee paid |
| 3 | scope_lock | Scope Lock | Workflow diagrams, final price, signed agreement | Yes — scope + price approved |
| 4 | visual_identity | Visual Identity | Mood board, palette, fonts, imagery direction | Yes — visual direction approved |
| 5 | wireframes | Wireframes | Page structures, content hierarchy, layout | Yes — wireframes approved |
| 6 | build | Build | Style sheet creation, page assembly, functionality | No — internal only |
| 7 | review | Review | Page-by-page client review with structured feedback | Yes — all pages approved |
| 8 | delivery | Delivery | Final revisions, handoff, training | Yes — final sign-off |

**Business rules:**
- Stages 1–3 decide WHAT to build. Stage 3 is the contractual scope lock.
- Stages 4–5 decide HOW it looks.
- Stages 6–8 are execution and refinement.
- Projects move forward one stage at a time. No skipping.
- Each stage with a client gate requires explicit approval before advancing.
- Stage 6 (Build) has no client-facing gate — it's internal work.
- Anything requested after Stage 3 that wasn't in the approved scope = change order.
- A project can be paused or cancelled from any stage.

---

## 4. WHAT TO BUILD — TASK LIST

### Task 1: Evolve the Database Schema

The current `el_projects` table has a generic `status` enum. It needs a `current_stage` field (1–8) plus a separate `status` field (active, paused, completed, cancelled).

The current `el_phases` table is generic. You have two options:
- **Option A:** Keep `el_phases` but seed it with the fixed 8 stages on project creation, locking the names/order. Add `has_client_gate`, `approved_at`, `approved_by` columns.
- **Option B:** Remove the generic phases concept entirely and add `current_stage` to `el_projects`, with a new `el_es_stage_history` table tracking transitions.

**Recommended: Option B.** The 8 stages are fixed business process, not user-configurable phases. A stage history table is cleaner than pretending stages are phases.

New/modified tables needed:
- `el_projects` — add `current_stage` (tinyint, 1–8), `budget_range_low`, `budget_range_high`, `final_price`, `scope_locked_at`
- `el_es_stage_history` — new table: project_id, stage (1–8), action (entered/approved/rejected), notes, acted_by, created_at
- `el_es_deliverables` — new table: project_id, stage, title, description, file_url, file_type, review_status (pending/approved/needs_revision), created_at
- `el_es_feedback` — new table: project_id, deliverable_id, stage, user_id, feedback_type (revision/approval/question/change_order), content, status (pending/acknowledged/resolved/deferred), is_change_order, change_order_price, created_at
- `el_es_pages` — new table: project_id, page_name, page_url, status (planned/in_progress/review/approved), sort_order, created_at

Keep existing tables that still make sense: `el_tasks`, `el_files`, `el_activity_log`. Evaluate whether `el_phase_templates` should become stage task templates.

**Update `module.json`** with the new schema. Bump database version to trigger migration.

### Task 2: Rebuild Admin Views with EL_Admin_UI

Read these files first:
- `includes/class-admin-ui.php` — full component API
- `admin/views/settings-general.php` — reference implementation
- `admin/css/admin.css` — admin framework styles
- `admin/js/admin.js` — elAdmin namespace (modals, tabs, notices, filters)

Rebuild all admin views using `EL_Admin_UI::*` methods:

**projects-list.php** → Use:
- `EL_Admin_UI::wrap()` for outer container
- `EL_Admin_UI::page_header()` with "New Project" action button
- `EL_Admin_UI::stats_grid()` for project count metrics
- `EL_Admin_UI::filter_bar()` for status/type filters
- `EL_Admin_UI::data_table()` for project list
- `EL_Admin_UI::modal()` for create/edit form
- `EL_Admin_UI::empty_state()` when no projects

**project-detail.php** → Use:
- `EL_Admin_UI::page_header()` with back link and edit button
- `EL_Admin_UI::card()` for information sections
- `EL_Admin_UI::detail_row()` for label/value pairs
- `EL_Admin_UI::badge()` for stage and status indicators
- `EL_Admin_UI::tab_nav()` + `tab_panel()` for sections (Overview, Stages, Tasks, Files, Activity)
- `EL_Admin_UI::btn()` for actions

**project-form.php** (new or within modal) → Use:
- `EL_Admin_UI::form_section()` for grouping
- `EL_Admin_UI::form_row()` for each field
- `EL_Admin_UI::btn()` for submit

**Stage advancement controls** — Custom UI within detail view showing the 8-stage progress with approve/advance buttons.

### Task 3: Update Module Class for 8-Stage Logic

Add to `class-project-management-module.php`:

**Stage constants:**
```php
public const STAGES = [
    1 => ['name' => 'Qualification', 'slug' => 'qualification', 'has_client_gate' => true],
    2 => ['name' => 'Discovery', 'slug' => 'discovery', 'has_client_gate' => true],
    3 => ['name' => 'Scope Lock', 'slug' => 'scope_lock', 'has_client_gate' => true],
    4 => ['name' => 'Visual Identity', 'slug' => 'visual_identity', 'has_client_gate' => true],
    5 => ['name' => 'Wireframes', 'slug' => 'wireframes', 'has_client_gate' => true],
    6 => ['name' => 'Build', 'slug' => 'build', 'has_client_gate' => false],
    7 => ['name' => 'Review', 'slug' => 'review', 'has_client_gate' => true],
    8 => ['name' => 'Delivery', 'slug' => 'delivery', 'has_client_gate' => true],
];
```

**Stage transition methods:**
- `advance_stage(int $project_id): bool` — moves to next stage, validates current gate is approved
- `approve_gate(int $project_id, string $notes = ''): bool` — marks current stage's client gate as approved
- `reject_gate(int $project_id, string $notes = ''): bool` — marks current gate as rejected (stays on same stage)
- `can_advance(int $project_id): bool` — checks if current stage's gate is approved (or stage 6 which has no gate)
- `get_stage_history(int $project_id): array` — returns all stage transitions

**Business rule enforcement:**
- `advance_stage()` MUST check `can_advance()` before moving forward
- Stage 3 approval sets `scope_locked_at` timestamp on the project
- Any feedback submitted after `scope_locked_at` that requests new features should be flagged as a potential change order

### Task 4: Add Client Portal Shortcodes

These go in `modules/project-management/shortcodes/`. Remember the naming convention:
- Tag `el_project_portal` → file `project-portal.php` → function `el_shortcode_project_portal`
- Tag `el_project_status` → file `project-status.php` → function `el_shortcode_project_status`
- Tag `el_page_review` → file `page-review.php` → function `el_shortcode_page_review`
- Tag `el_feedback_form` → file `feedback-form.php` → function `el_shortcode_feedback_form`

**Register them in module.json** under the `shortcodes` array.

**`[el_project_portal]`** — Client dashboard
- Shows current stage with visual progress indicator (8 dots/steps)
- Lists deliverables for the current stage
- Shows pending feedback requests
- Login required — matches client to project via `client_user_id` on the project record

**`[el_project_status]`** — Visual 8-stage progress bar
- Param: `project_id` (optional — auto-detects from logged-in user if omitted)
- Shows all 8 stages with completed/current/upcoming states
- Compact display suitable for embedding anywhere

**`[el_page_review]`** — Deliverable review interface (Stage 7)
- Lists all pages in the project with their review status
- Each page has approve/request-revision buttons
- Links to the actual page URL for viewing

**`[el_feedback_form]`** — Structured feedback submission
- Param: `project_id`, `stage` (optional — defaults to current)
- Text input for feedback content
- Dropdown for feedback type: revision, approval, question, change order
- Submits via AJAX using `ELCore.ajax()`

**Critical shortcode rules:**
- All shortcodes return HTML strings, never echo
- Use `el-component` wrapper class on outermost element
- Use brand CSS variables: `var(--el-primary)`, `var(--el-accent)`, etc.
- Use core form classes: `el-form`, `el-field`, `el-label`, `el-input`, `el-btn`, `el-btn-primary`
- Register AJAX handlers for both `el_core_ajax_{action}` AND `el_core_ajax_nopriv_{action}` if guests might use them (though these shortcodes likely require login)

### Task 5: Add Frontend Assets

**`assets/css/expand-site.css`** — Use `el-es-` class prefix for all Expand Site-specific components. Use brand variables for colors. Don't duplicate styles already in `assets/css/el-core.css`.

**`assets/js/expand-site.js`** — Vanilla JS only (no jQuery on frontend). Use `ELCore.ajax()` for all AJAX calls. Use event delegation on `document`.

### Task 6: AJAX Handlers for Client Actions

Add to the module class:
- `handle_submit_feedback` — client submits feedback on a deliverable
- `handle_approve_deliverable` — client approves a specific deliverable
- `handle_approve_page` — client approves a specific page (Stage 7)

Register both priv and nopriv hooks if needed (though clients should be logged in).

---

## 5. MODULE NAMING DECISION

The module currently lives at `modules/project-management/`. You have two options:

**Option A: Keep the name `project-management`.** The Expand Site 8-stage pipeline is the primary project type, but the module also handles other project types (afterschool_guru, expand_partners, els_consulting). "Project Management" is the broader category.

**Option B: Rename to `expand-site`.** This would mean renaming the directory, class file, class name, all references, and updating module.json slug.

**Recommended: Keep `project-management`.** It's already deployed, the database tables exist with `el_` prefix (not `el_es_`), and renaming introduces migration risk for no functional benefit. The 8-stage pipeline is a feature OF project management, not a replacement for it.

However, new tables specific to the Expand Site workflow (stage_history, deliverables, feedback, pages) should use the `el_es_` prefix to distinguish them from the generic project management tables.

---

## 6. ADMIN UI FRAMEWORK QUICK REFERENCE

Every method is `static`, takes an `$args` array, and **returns an HTML string** (never echoes).

### Layout & Structure
```php
EL_Admin_UI::wrap($content)                    // Outermost .el-admin-wrap container
EL_Admin_UI::page_header([                     // Page title bar
    'title' => 'Projects',
    'subtitle' => '12 total',
    'actions' => '<button>New Project</button>'
])
EL_Admin_UI::card([                            // Content card
    'title' => 'Card Title',
    'icon' => 'dashicons-admin-site',
    'body' => $html_content,
    'footer' => $footer_html                   // optional
])
```

### Data Display
```php
EL_Admin_UI::stats_grid([                      // Metric cards grid
    ['icon' => 'dashicons-portfolio', 'value' => 12, 'label' => 'Active Projects'],
    ['icon' => 'dashicons-yes-alt', 'value' => 8, 'label' => 'Completed'],
])
EL_Admin_UI::badge('Active', 'success')        // Status pill (default|success|warning|error|info|primary)
EL_Admin_UI::detail_row('Client', 'Acme Corp') // Label/value pair
EL_Admin_UI::data_table([                      // HTML table
    'columns' => ['Name', 'Status', 'Stage', 'Actions'],
    'rows' => [
        ['Acme Website', EL_Admin_UI::badge('Active','success'), 'Build', $action_buttons],
    ]
])
EL_Admin_UI::empty_state([                     // No records message
    'icon' => 'dashicons-portfolio',
    'title' => 'No Projects Yet',
    'message' => 'Create your first project.',
    'action_label' => 'New Project',
    'action_url' => '#'
])
```

### Forms
```php
EL_Admin_UI::form_section('Project Details')   // Section heading
EL_Admin_UI::form_row([                        // Form field
    'label' => 'Project Name',
    'name' => 'name',
    'type' => 'text',                          // text|email|url|number|date|time|textarea|select|checkbox
    'required' => true,
    'value' => $project->name ?? ''
])
EL_Admin_UI::form_row([                        // Select dropdown
    'label' => 'Status',
    'name' => 'status',
    'type' => 'select',
    'options' => ['active' => 'Active', 'paused' => 'Paused'],
    'value' => $project->status ?? 'active'
])
```

### Navigation & Interactive
```php
EL_Admin_UI::tab_nav([                         // Tab navigation
    'overview' => 'Overview',
    'stages' => 'Stages',
    'tasks' => 'Tasks'
], 'overview', 'project-tabs')                 // active tab, group ID

EL_Admin_UI::tab_panel('overview', $content, 'project-tabs', true)  // id, content, group, active
EL_Admin_UI::tab_panel('stages', $content, 'project-tabs', false)

EL_Admin_UI::filter_bar([                      // Search + filters
    'search' => true,
    'search_name' => 's',
    'search_value' => $_GET['s'] ?? '',
    'filters' => [
        ['name' => 'status', 'options' => $status_options, 'value' => $_GET['status'] ?? '']
    ]
])

EL_Admin_UI::modal([                           // Dialog (hidden by default)
    'id' => 'project-modal',
    'title' => 'New Project',
    'body' => $form_html,
    'footer' => EL_Admin_UI::btn(['label'=>'Save','type'=>'primary','attrs'=>'type="submit"'])
])

EL_Admin_UI::btn([                             // Button
    'label' => 'New Project',
    'type' => 'primary',                       // primary|secondary|danger|outline
    'icon' => 'dashicons-plus-alt',
    'attrs' => 'id="new-project-btn"'
])
```

### Admin JS (elAdmin namespace)
```javascript
elAdmin.openModal('project-modal')
elAdmin.closeModal('project-modal')
elAdmin.switchTab('stages', 'project-tabs')
elAdmin.flashNotice('Project saved!', 'success', 3000)
```

---

## 7. CODING CONVENTIONS — FOLLOW EXACTLY

### PHP
- No namespaces. `EL_` prefix for all classes.
- Singleton pattern: `ClassName::instance()` with private constructor.
- PHP 8.0+ typed parameters, return types, nullable types.
- Sanitize ALL input: `sanitize_text_field()`, `absint()`, `wp_kses_post()`, `esc_url_raw()`
- Nonce verification on all form submissions and AJAX.
- Text domain: `el-core`
- Check capabilities, never role names: `el_core_can('manage_projects')` or `current_user_can('manage_projects')`

### CSS
- Module-specific class prefix: `el-es-` for Expand Site components
- Brand variables: `var(--el-primary)`, `var(--el-accent)`, `var(--el-text)`, etc.
- Core form classes (already styled in el-core.css): `el-form`, `el-field`, `el-label`, `el-input`, `el-select`, `el-textarea`, `el-btn`, `el-btn-primary`
- Component wrapper: `el-component` on outermost shortcode element

### JavaScript
- Frontend: Vanilla JS, no jQuery. Use `ELCore.ajax()` for AJAX.
- Admin: jQuery available. Use `elAdmin` namespace for modal/tab/notice operations.
- Event delegation on `document` for dynamically rendered content.

### Shortcodes
- Tag format: `el_{component_name}`
- Function format: `el_shortcode_{component_name}` (module loader strips `el_` prefix and prepends `el_shortcode_`)
- Each shortcode returns HTML string (never echo)
- One focused component per shortcode
- Register in `module.json` shortcodes array

### AJAX
- All actions go through unified `el_core_action` endpoint
- Register hooks: `add_action('el_core_ajax_{action}', [$this, 'handler'])`
- For guest access also: `add_action('el_core_ajax_nopriv_{action}', [$this, 'handler'])`
- Use `EL_AJAX_Handler::success($data, $message)` and `EL_AJAX_Handler::error($message, $code)`

---

## 8. CRITICAL RULES

1. **CSS class names must match across PHP, CSS, and JS.** If the shortcode outputs `el-es-stage-bar`, the CSS must style `el-es-stage-bar`, and the JS must select `el-es-stage-bar`. Three files, one set of names.

2. **Shortcode function names are derived automatically.** Tag `el_project_portal` → function `el_shortcode_project_portal`. Get this wrong and the shortcode silently fails.

3. **Module settings key format:** `el_mod_project-management`. Access via `$this->core->settings->get('mod_project-management', 'key', 'default')`.

4. **Admin views use EL_Admin_UI exclusively.** No raw `<table>`, `<div class="wrap">`, or hand-rolled cards. Use the framework.

5. **Table names in module.json are written WITHOUT the WordPress prefix.** Write `el_projects`, not `wp_el_projects`. Core adds the prefix automatically.

6. **AJAX handlers receive a $data array**, not raw $_POST. The AJAX handler class pre-processes the request.
   - CORRECTION: Looking at the existing module class, form submissions use `$_POST` directly via `handle_form_submissions()`. AJAX handlers (the `ajax_*` methods) access `$_POST` directly too. Follow the existing pattern.

7. **Modules never do infrastructure work.** No `add_shortcode()`, no `CREATE TABLE`, no capability registration. Declare in `module.json`, core handles it.

8. **Both priv and nopriv AJAX hooks** needed for any action accessible to non-admin users.

---

## 9. BUILD ORDER

1. **Update `module.json`** — Add new tables (el_es_stage_history, el_es_deliverables, el_es_feedback, el_es_pages), add shortcode declarations, bump database version
2. **Update `class-project-management-module.php`** — Add STAGES constant, stage transition methods, new AJAX handlers, shortcode file loading
3. **Rebuild `admin/projects-list.php`** — Using EL_Admin_UI framework
4. **Rebuild `admin/project-detail.php`** — Using EL_Admin_UI, add 8-stage progress display, stage advancement controls
5. **Create `admin/project-form.php`** — Dedicated create/edit form view using EL_Admin_UI
6. **Create shortcode files** — project-portal.php, project-status.php, page-review.php, feedback-form.php
7. **Create `assets/css/expand-site.css`** — Frontend styles for client portal
8. **Create `assets/js/expand-site.js`** — Frontend JS for client interactions
9. **Update `assets/admin.css`** — Replace custom styles with EL_Admin_UI framework integration
10. **Update `assets/admin.js`** — Use `elAdmin` namespace, remove duplicate modal/handler code

---

## 10. DEPLOYMENT FACTS

- **EL Core v1.3.0** is deployed and active on **expandedlearningsolutions.com**
- The local repo at `C:\Github\EL Core\el-core\` is the source of truth
- Deploy by running `build-zip.ps1` in repo root, then upload ZIP through WordPress Admin → Plugins → Add New → Upload Plugin
- WordPress MCP tools are NOT connected. Do not suggest using `wp_fs_write` or any MCP tools.
- There is no staging site. Test on the live site.
