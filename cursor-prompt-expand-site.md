# CURSOR PROMPT: Build the Expand Site Module for EL Core

> **IMPORTANT:** Open the entire `C:\Github\EL Core\` folder in Cursor before starting. The EL Core plugin lives at `C:\Github\EL Core\el-core\`. You'll build the Expand Site module directly into `C:\Github\EL Core\el-core\modules\expand-site\`.

---

## YOUR MISSION

Build the **Expand Site** module for EL Core — a WordPress plugin that powers an 8-stage client site-building pipeline. This module manages projects, stages, deliverables, client feedback, and change orders for a web design agency. It includes both an admin dashboard (internal) and client-facing portal shortcodes.

This module is a **Business Operations** module — it only runs on the Expanded Learning Solutions website, never on client learning platforms.

---

## CRITICAL: READ THESE FILES FIRST

Before writing any code, read these existing files to understand the patterns:

1. `el-core/includes/class-module-loader.php` — How modules are discovered, loaded, and activated
2. `el-core/includes/class-admin-ui.php` — The admin UI framework (all admin pages MUST use these components)
3. `el-core/includes/class-ajax-handler.php` — How AJAX works (unified endpoint)
4. `el-core/includes/class-database.php` — How database queries work
5. `el-core/modules/events/module.json` — Example module manifest
6. `el-core/modules/events/class-events-module.php` — Example module class (business logic only)
7. `el-core/modules/events/shortcodes/event-list.php` — Example shortcode file
8. `el-core/assets/js/el-core.js` — Frontend JS patterns (ELCore.ajax helper)
9. `el-core/admin/js/admin.js` — Admin JS patterns (elAdmin namespace: modals, tabs, flash notices)
10. `el-core/el-core-cursor-handoff.md` — Full architecture reference and lessons learned

---

## HOW THE MODULE SYSTEM WORKS

**Modules declare WHAT they need. Core handles HOW.**

You create a `module.json` manifest that declares tables, capabilities, shortcodes, and settings. The core automatically:
- Creates database tables and runs migrations via `dbDelta()`
- Registers capabilities with WordPress
- Registers shortcodes by loading the file and finding the function
- Stores settings in `wp_options` as `el_mod_{slug}`

**Your module class contains ONLY business logic.** Never write:
- `CREATE TABLE` SQL — declare tables in module.json
- `add_shortcode()` — declare shortcodes in module.json
- Settings page HTML — core renders from module.json settings
- Capability registration — declare in module.json

### Module Loading Flow

1. Core scans `modules/` directory for `module.json` files
2. Validates PHP and EL Core version requirements
3. Resolves dependencies (loads required modules first)
4. Runs `process_module_schema()` to create/migrate tables
5. Registers capabilities from manifest
6. Loads shortcode files and registers them
7. Loads `class-{slug}-module.php` and calls `ClassName::instance()`

### Class Name Derivation

The module loader converts slug to class name:
- `expand-site` → `EL_Expand_Site_Module`
- File: `class-expand-site-module.php`

### Shortcode Function Name Derivation

The module loader strips the `el_` prefix from the tag and prepends `el_shortcode_`:
- Tag `el_project_portal` → function `el_shortcode_project_portal`
- Tag `el_project_status` → function `el_shortcode_project_status`
- Tag `el_page_review` → function `el_shortcode_page_review`
- Tag `el_feedback_form` → function `el_shortcode_feedback_form`

**If the function name doesn't match this convention, the shortcode WILL NOT register.** You'll see an error in the PHP error log.

---

## ADMIN UI FRAMEWORK — USE THIS FOR ALL ADMIN PAGES

The module's admin pages MUST use `EL_Admin_UI` static methods. Never write raw HTML for admin interfaces. Every method returns an HTML string.

### Available Components

```php
// Page wrapper — outermost container
EL_Admin_UI::wrap( string $content ): string

// Page header with title, subtitle, action buttons
EL_Admin_UI::page_header([
    'title'    => 'Projects',
    'subtitle' => 'Manage client projects',
    'actions'  => [
        ['label' => 'New Project', 'variant' => 'primary', 'icon' => 'plus-alt', 'data' => ['modal-open' => 'create-project']]
    ]
])

// Content card with header and body
EL_Admin_UI::card([
    'title'   => 'Card Title',
    'icon'    => 'dashicon-name',
    'content' => '<p>Card body HTML</p>',
    'actions' => [ /* button args */ ]
])

// Stat metric card (icon + number + label)
EL_Admin_UI::stat_card([
    'icon'    => 'groups',
    'number'  => 42,
    'label'   => 'Active Projects',
    'variant' => 'primary',  // primary, success, warning, info
    'url'     => '#'         // makes it clickable
])

// Grid of stat cards
EL_Admin_UI::stats_grid([ /* array of stat_card args */ ])

// Status badge pill
EL_Admin_UI::badge([
    'label'   => 'In Review',
    'variant' => 'warning'  // default, success, warning, error, info, primary
])

// Empty state (when no records exist)
EL_Admin_UI::empty_state([
    'icon'    => 'portfolio',
    'title'   => 'No projects yet',
    'message' => 'Create your first client project.',
    'action'  => ['label' => 'New Project', 'variant' => 'primary']
])

// Inline notice/alert
EL_Admin_UI::notice([
    'message'     => 'Project saved.',
    'type'        => 'success',  // success, warning, error, info
    'dismissible' => true
])

// Label/value detail row (for detail pages)
EL_Admin_UI::detail_row([
    'label' => 'Client',
    'value' => 'Acme Corp',
    'icon'  => 'businessperson'
])

// Tab navigation + panels
EL_Admin_UI::tab_nav([
    'group' => 'project-tabs',
    'tabs'  => [
        ['id' => 'overview', 'label' => 'Overview', 'icon' => 'dashboard', 'active' => true],
        ['id' => 'stages',   'label' => 'Stages',   'icon' => 'editor-ol'],
        ['id' => 'feedback', 'label' => 'Feedback',  'icon' => 'format-chat', 'badge' => 3],
    ]
])
EL_Admin_UI::tab_panel([
    'id'      => 'overview',
    'group'   => 'project-tabs',
    'content' => '<p>Tab content here</p>',
    'active'  => true
])

// Form section heading
EL_Admin_UI::form_section([
    'title'       => 'Client Information',
    'description' => 'Basic project details'
])

// Form field row (text, email, url, number, date, time, textarea, select, checkbox)
EL_Admin_UI::form_row([
    'name'        => 'project_name',
    'label'       => 'Project Name',
    'type'        => 'text',
    'value'       => '',
    'placeholder' => 'e.g., Acme Corp Website Redesign',
    'required'    => true,
    'helper'      => 'Internal project name for tracking'
])

// Search + filter bar
EL_Admin_UI::filter_bar([
    'action'       => admin_url('admin.php?page=el-expand-site'),
    'search_value' => $_GET['s'] ?? '',
    'filters'      => [
        ['name' => 'stage', 'value' => $_GET['stage'] ?? '', 'options' => ['' => 'All Stages', 'qualification' => 'Qualification']],
        ['name' => 'status', 'value' => $_GET['status'] ?? '', 'options' => ['' => 'All', 'active' => 'Active', 'completed' => 'Completed']]
    ],
    'hidden' => ['page' => 'el-expand-site']
])

// Modal dialog (starts hidden, opened via JS)
EL_Admin_UI::modal([
    'id'      => 'create-project',
    'title'   => 'Create New Project',
    'content' => '<form>...</form>',
    'size'    => 'large'  // default (600px) or large (900px)
])

// Button
EL_Admin_UI::btn([
    'label'   => 'Save',
    'variant' => 'primary',  // primary, secondary, danger, ghost
    'icon'    => 'saved',
    'url'     => '',  // if set, renders as <a> instead of <button>
    'data'    => ['modal-open' => 'some-modal']
])

// Data table
EL_Admin_UI::data_table([
    'columns' => [
        ['key' => 'name',   'label' => 'Project'],
        ['key' => 'client', 'label' => 'Client'],
        ['key' => 'stage',  'label' => 'Stage'],
        ['key' => 'status', 'label' => 'Status'],
    ],
    'rows' => [
        ['name' => 'Acme Website', 'client' => 'Acme Corp', 'stage' => $badge_html, 'status' => $badge_html, '__actions' => $action_buttons],
    ],
    'empty' => ['title' => 'No projects found', 'icon' => 'portfolio']
])

// Record card (for grid layouts)
EL_Admin_UI::record_card([
    'title'    => 'Acme Corp Website',
    'url'      => admin_url('admin.php?page=el-expand-site&project=1'),
    'subtitle' => 'Acme Corporation',
    'badges'   => [['label' => 'Stage 4', 'variant' => 'info']],
    'meta'     => [['icon' => 'calendar', 'text' => 'Started Jan 15']],
    'footer'   => [['icon' => 'media-document', 'text' => '12 pages']]
])

// Grid of record cards
EL_Admin_UI::record_grid([ /* array of record_card args */ ])
```

### Admin JS API (from `admin.js`)

```javascript
// Open/close modals
elAdmin.openModal('modal-id');
elAdmin.closeModal('modal-id');

// Switch tabs
elAdmin.switchTab('tab-id', 'group-id');

// Show flash notice (AJAX feedback)
elAdmin.flashNotice('Project saved!', 'success', 4000);
elAdmin.flashNotice('Error saving.', 'error', 0); // 0 = no auto-dismiss

// Modals also open/close via data attributes:
// <button data-modal-open="create-project">
// <button data-modal-close="create-project">
```

---

## THE 8-STAGE PIPELINE

Every client project flows through these stages in order:

| # | Stage | Purpose | Approval Gate |
|---|-------|---------|---------------|
| 1 | Qualification | Filter budget fit | Budget alignment |
| 2 | Discovery | Capture requirements, workflows, content inventory | Discovery fee paid |
| 3 | Scope Lock | Workflow diagrams, final price, signed agreement | Scope + price approved |
| 4 | Visual Identity | Mood board, palette, fonts, imagery direction | Visual direction approved |
| 5 | Wireframes | Page structures, content hierarchy, layout | Wireframes approved |
| 6 | Build | Style sheet creation, page assembly, functionality | None (internal) |
| 7 | Review | Page-by-page client review with structured feedback | All pages approved |
| 8 | Delivery | Final revisions, handoff, training | Final sign-off |

**Key rules:**
- Stages 1-3: Deciding WHAT to build. Stage 3 is the contractual scope lock.
- Stages 4-5: Deciding HOW it looks.
- Stages 6-8: Execution and refinement.
- Anything requested after Stage 3 that wasn't in approved workflow diagrams = change order.
- Stage 6 (Build) has no client-facing approval gate — it's internal.

---

## FILE STRUCTURE TO CREATE

```
modules/expand-site/
├── module.json                          # Module manifest
├── class-expand-site-module.php         # Business logic, AJAX handlers
├── shortcodes/
│   ├── project-portal.php               # [el_project_portal] — Client dashboard
│   ├── project-status.php               # [el_project_status] — Visual stage progress
│   ├── page-review.php                  # [el_page_review] — Deliverable review interface
│   └── feedback-form.php                # [el_feedback_form] — Structured feedback submission
├── admin/
│   └── views/
│       ├── project-list.php             # Admin project list page
│       ├── project-detail.php           # Admin single project view
│       └── project-form.php             # Admin create/edit project form
└── assets/
    ├── css/
    │   └── expand-site.css              # Module-specific styles (el-es- prefix)
    └── js/
        └── expand-site.js               # Module-specific JS
```

---

## DATABASE SCHEMA

Design these tables in `module.json`. All tables auto-get the `{wp_prefix}` prepended by the core.

### el_es_projects
The main project record.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | |
| name | VARCHAR(255) NOT NULL | Internal project name |
| client_name | VARCHAR(255) NOT NULL | Client/organization name |
| client_user_id | BIGINT UNSIGNED DEFAULT 0 | WordPress user ID of client |
| current_stage | TINYINT UNSIGNED DEFAULT 1 | Current pipeline stage (1-8) |
| status | VARCHAR(20) DEFAULT 'active' | active, paused, completed, cancelled |
| budget_range_low | DECIMAL(10,2) DEFAULT 0 | Quote range low end |
| budget_range_high | DECIMAL(10,2) DEFAULT 0 | Quote range high end |
| final_price | DECIMAL(10,2) DEFAULT 0 | Locked price after Stage 3 |
| scope_locked_at | DATETIME NULL | When Stage 3 was approved |
| notes | LONGTEXT | Internal notes |
| created_by | BIGINT UNSIGNED DEFAULT 0 | |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |
| updated_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### el_es_stage_history
Tracks when each stage was entered and completed.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | |
| project_id | BIGINT UNSIGNED NOT NULL | |
| stage | TINYINT UNSIGNED NOT NULL | Stage number (1-8) |
| action | VARCHAR(20) DEFAULT 'entered' | entered, approved, rejected |
| notes | TEXT | Approval notes or rejection reason |
| acted_by | BIGINT UNSIGNED DEFAULT 0 | Who performed the action |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### el_es_deliverables
Files and items delivered to the client at each stage.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | |
| project_id | BIGINT UNSIGNED NOT NULL | |
| stage | TINYINT UNSIGNED NOT NULL | Which stage this deliverable belongs to |
| title | VARCHAR(255) NOT NULL | e.g., "Homepage Wireframe" |
| description | TEXT | |
| file_url | VARCHAR(500) DEFAULT '' | Link to file (WP media or external) |
| file_type | VARCHAR(50) DEFAULT '' | pdf, image, link, document |
| review_status | VARCHAR(20) DEFAULT 'pending' | pending, approved, needs_revision |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### el_es_feedback
Client feedback on deliverables.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | |
| project_id | BIGINT UNSIGNED NOT NULL | |
| deliverable_id | BIGINT UNSIGNED DEFAULT 0 | Specific deliverable, or 0 for general |
| stage | TINYINT UNSIGNED NOT NULL | |
| user_id | BIGINT UNSIGNED NOT NULL | Who submitted it |
| feedback_type | VARCHAR(20) DEFAULT 'revision' | revision, approval, question, change_order |
| content | LONGTEXT NOT NULL | The feedback text |
| status | VARCHAR(20) DEFAULT 'pending' | pending, acknowledged, resolved, deferred |
| is_change_order | TINYINT(1) DEFAULT 0 | Flagged as out-of-scope |
| change_order_price | DECIMAL(10,2) DEFAULT 0 | If it's a change order, the additional cost |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### el_es_pages
Tracks individual pages being built for the project.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | |
| project_id | BIGINT UNSIGNED NOT NULL | |
| page_name | VARCHAR(255) NOT NULL | e.g., "Homepage", "About Us" |
| page_url | VARCHAR(500) DEFAULT '' | Live URL once built |
| status | VARCHAR(20) DEFAULT 'planned' | planned, in_progress, review, approved |
| sort_order | INT UNSIGNED DEFAULT 0 | Display order |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

---

## CODING CONVENTIONS — FOLLOW EXACTLY

### PHP
- **No namespaces.** Use `EL_` prefix for all classes.
- **Singleton pattern** on the module class (see example below).
- **PHP 8.0+** with typed parameters and return types.
- **All user input sanitized:** `sanitize_text_field()`, `absint()`, `wp_kses_post()`, `esc_url_raw()`
- **Nonce verification** on all AJAX.
- **Text domain:** `el-core`

### CSS
- **Class prefix:** `el-es-` for all Expand Site components (e.g., `el-es-project-card`, `el-es-stage-progress`)
- Use brand CSS variables: `var(--el-primary)`, `var(--el-accent)`, etc.
- Component wrapper: `el-component` class on outermost shortcode element
- Frontend form elements use core classes: `el-form`, `el-field`, `el-label`, `el-input`, `el-select`, `el-textarea`, `el-btn`, `el-btn-primary`

### JavaScript
- **Frontend:** Vanilla JS only (no jQuery). Use `ELCore.ajax()` for all AJAX calls.
- **Admin:** jQuery is available. Use `elAdmin` namespace functions for modals, tabs, notices.
- Event delegation on `document` for dynamically rendered components.

### Shortcodes
- **Return** HTML strings. Never echo.
- Each shortcode renders ONE focused component.
- Tag format: `el_{component_name}`
- Function format: `el_shortcode_{component_name}`

---

## MODULE CLASS PATTERN — FOLLOW THIS EXACTLY

```php
<?php
/**
 * Expand Site Module
 *
 * Business logic for the 8-stage client site-building pipeline.
 * Manages projects, stages, deliverables, feedback, and change orders.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class EL_Expand_Site_Module {

    private static ?EL_Expand_Site_Module $instance = null;
    private EL_Core $core;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->core = EL_Core::instance();
        $this->init_hooks();
    }

    private function init_hooks(): void {
        // AJAX handlers (authenticated users only for this module)
        add_action( 'el_core_ajax_es_create_project', [ $this, 'handle_create_project' ] );
        add_action( 'el_core_ajax_es_update_project', [ $this, 'handle_update_project' ] );
        add_action( 'el_core_ajax_es_advance_stage', [ $this, 'handle_advance_stage' ] );
        add_action( 'el_core_ajax_es_submit_feedback', [ $this, 'handle_submit_feedback' ] );
        add_action( 'el_core_ajax_es_add_deliverable', [ $this, 'handle_add_deliverable' ] );
        add_action( 'el_core_ajax_es_review_deliverable', [ $this, 'handle_review_deliverable' ] );

        // Client-facing AJAX (needs nopriv if clients aren't logged in, but they WILL be logged in)
        // So only priv hooks needed here.

        // Admin menu (sub-page under EL Core)
        add_action( 'admin_menu', [ $this, 'register_admin_pages' ] );

        // Enqueue module-specific assets
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Register admin pages under the EL Core menu.
     */
    public function register_admin_pages(): void {
        add_submenu_page(
            'el-core',                    // Parent slug (EL Core menu)
            'Expand Site',                // Page title
            'Expand Site',                // Menu title
            'manage_expand_site',         // Capability required
            'el-expand-site',             // Menu slug
            [ $this, 'render_admin_page' ] // Callback
        );
    }

    /**
     * Route admin page rendering based on query params.
     */
    public function render_admin_page(): void {
        $project_id = absint( $_GET['project'] ?? 0 );
        $action     = sanitize_text_field( $_GET['action'] ?? '' );

        if ( $project_id && $action === 'edit' ) {
            require_once __DIR__ . '/admin/views/project-form.php';
        } elseif ( $project_id ) {
            require_once __DIR__ . '/admin/views/project-detail.php';
        } else {
            require_once __DIR__ . '/admin/views/project-list.php';
        }
    }

    /**
     * Enqueue frontend CSS/JS only when our shortcodes are present.
     */
    public function enqueue_frontend_assets(): void {
        global $post;
        if ( ! $post ) return;

        $shortcodes = ['el_project_portal', 'el_project_status', 'el_page_review', 'el_feedback_form'];
        $has_shortcode = false;
        foreach ( $shortcodes as $sc ) {
            if ( has_shortcode( $post->post_content, $sc ) ) {
                $has_shortcode = true;
                break;
            }
        }

        if ( $has_shortcode ) {
            wp_enqueue_style(
                'el-expand-site',
                EL_CORE_URL . 'modules/expand-site/assets/css/expand-site.css',
                [ 'el-core' ],
                EL_CORE_VERSION
            );
            wp_enqueue_script(
                'el-expand-site',
                EL_CORE_URL . 'modules/expand-site/assets/js/expand-site.js',
                [ 'el-core' ],
                EL_CORE_VERSION,
                true
            );
        }
    }

    /**
     * Enqueue admin CSS/JS only on our admin page.
     */
    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, 'el-expand-site' ) === false ) return;

        wp_enqueue_style(
            'el-expand-site-admin',
            EL_CORE_URL . 'modules/expand-site/assets/css/expand-site.css',
            [ 'el-admin' ],
            EL_CORE_VERSION
        );
        wp_enqueue_script(
            'el-expand-site-admin',
            EL_CORE_URL . 'modules/expand-site/assets/js/expand-site.js',
            [ 'jquery' ],
            EL_CORE_VERSION,
            true
        );
    }

    // ═══════════════════════════════════════════
    // STAGE DEFINITIONS (constants)
    // ═══════════════════════════════════════════

    public const STAGES = [
        1 => ['name' => 'Qualification',    'slug' => 'qualification',    'has_client_gate' => true],
        2 => ['name' => 'Discovery',        'slug' => 'discovery',        'has_client_gate' => true],
        3 => ['name' => 'Scope Lock',       'slug' => 'scope-lock',       'has_client_gate' => true],
        4 => ['name' => 'Visual Identity',  'slug' => 'visual-identity',  'has_client_gate' => true],
        5 => ['name' => 'Wireframes',       'slug' => 'wireframes',       'has_client_gate' => true],
        6 => ['name' => 'Build',            'slug' => 'build',            'has_client_gate' => false],
        7 => ['name' => 'Review',           'slug' => 'review',           'has_client_gate' => true],
        8 => ['name' => 'Delivery',         'slug' => 'delivery',         'has_client_gate' => true],
    ];

    // ... implement query methods, action methods, and AJAX handlers
}
```

---

## AJAX PATTERN

### PHP (in module class):
```php
public function handle_create_project( array $data ): void {
    if ( ! el_core_can( 'manage_expand_site' ) ) {
        EL_AJAX_Handler::error( 'Permission denied.', 403 );
        return;
    }

    $name = sanitize_text_field( $data['name'] ?? '' );
    if ( empty( $name ) ) {
        EL_AJAX_Handler::error( 'Project name is required.' );
        return;
    }

    $project_id = $this->create_project( $data );

    if ( $project_id ) {
        EL_AJAX_Handler::success( [ 'project_id' => $project_id ], 'Project created!' );
    } else {
        EL_AJAX_Handler::error( 'Failed to create project.' );
    }
}
```

### JavaScript (frontend — vanilla JS):
```javascript
ELCore.ajax('es_create_project', {
    name: 'Acme Website',
    client_name: 'Acme Corp'
})
.then(result => {
    // result.message = 'Project created!'
    // result.data.project_id = 123
})
.catch(err => {
    // err.message = 'Failed to create project.'
});
```

### JavaScript (admin — jQuery available):
```javascript
jQuery(document).on('click', '#save-project', function() {
    const formData = {};
    jQuery('#project-form').serializeArray().forEach(f => formData[f.name] = f.value);

    jQuery.post(ajaxurl, {
        action: 'el_core_action',
        el_action: 'es_create_project',
        nonce: elCore.nonce,
        ...formData
    }, function(response) {
        if (response.success) {
            elAdmin.flashNotice(response.data.message, 'success');
            elAdmin.closeModal('create-project');
        } else {
            elAdmin.flashNotice(response.data.message, 'error');
        }
    });
});
```

---

## DATABASE QUERY PATTERNS

The core `EL_Database` class provides these convenience methods:

```php
$db = $this->core->database;

// Insert (returns inserted ID or false)
$id = $db->insert('el_es_projects', [
    'name'        => 'Acme Website',
    'client_name' => 'Acme Corp',
    'status'      => 'active',
]);

// Get single record by ID
$project = $db->get('el_es_projects', $id);  // returns object or null

// Update (returns rows affected)
$db->update('el_es_projects', ['status' => 'completed'], ['id' => $id]);

// Delete (returns rows affected)
$db->delete('el_es_projects', ['id' => $id]);

// Query with conditions
$projects = $db->query('el_es_projects', [
    'status' => 'active',
    'current_stage >' => 3
], [
    'orderby' => 'created_at',
    'order'   => 'DESC',
    'limit'   => 20
]);

// Count
$count = $db->count('el_es_projects', ['status' => 'active']);
```

---

## SHORTCODE PATTERN

```php
<?php
/**
 * Shortcode: [el_project_portal]
 *
 * Client-facing project dashboard. Shows their project's current stage,
 * deliverables, and feedback options.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function el_shortcode_project_portal( $atts ): string {
    $atts = shortcode_atts( [
        'project_id' => 0,
    ], $atts, 'el_project_portal' );

    if ( ! is_user_logged_in() ) {
        return '<div class="el-component el-es-login-required">'
             . '<p>Please log in to view your project.</p>'
             . '</div>';
    }

    $module = EL_Expand_Site_Module::instance();
    // ... get project data, render HTML

    $html = '<div class="el-component el-es-project-portal">';
    // ... build the component HTML
    $html .= '</div>';

    return $html;
}
```

---

## ADMIN VIEW PATTERN

```php
<?php
/**
 * Admin View: Project List
 * Uses EL_Admin_UI components for all rendering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$module   = EL_Expand_Site_Module::instance();
$projects = $module->get_all_projects();

$html = '';

$html .= EL_Admin_UI::page_header([
    'title'    => 'Expand Site Projects',
    'subtitle' => count($projects) . ' projects',
    'actions'  => [
        ['label' => 'New Project', 'variant' => 'primary', 'icon' => 'plus-alt',
         'data' => ['modal-open' => 'create-project-modal']]
    ]
]);

// Stats row
$active = count(array_filter($projects, fn($p) => $p->status === 'active'));
$html .= EL_Admin_UI::stats_grid([
    ['icon' => 'portfolio', 'number' => count($projects), 'label' => 'Total', 'variant' => 'primary'],
    ['icon' => 'update',    'number' => $active,           'label' => 'Active', 'variant' => 'info'],
]);

// Build table rows
$rows = [];
foreach ($projects as $p) {
    $stage_badge = EL_Admin_UI::badge([
        'label' => EL_Expand_Site_Module::STAGES[$p->current_stage]['name'],
        'variant' => 'info'
    ]);
    $rows[] = [
        'name'      => '<strong>' . esc_html($p->name) . '</strong>',
        'client'    => esc_html($p->client_name),
        'stage'     => $stage_badge,
        '__actions' => EL_Admin_UI::btn(['label' => 'View', 'variant' => 'ghost',
            'url' => admin_url('admin.php?page=el-expand-site&project=' . $p->id)])
    ];
}

$html .= EL_Admin_UI::card([
    'title'   => 'All Projects',
    'content' => EL_Admin_UI::data_table([
        'columns' => [
            ['key' => 'name',   'label' => 'Project'],
            ['key' => 'client', 'label' => 'Client'],
            ['key' => 'stage',  'label' => 'Stage'],
        ],
        'rows'  => $rows,
        'empty' => ['icon' => 'portfolio', 'title' => 'No projects yet']
    ])
]);

echo EL_Admin_UI::wrap($html);
```

---

## CRITICAL LESSONS — AVOID THESE MISTAKES

1. **CSS class names must match across PHP, CSS, and JS.** If PHP outputs `el-es-stage-progress`, CSS must style `.el-es-stage-progress`, and JS must select `.el-es-stage-progress`.

2. **Shortcode function naming must follow the derivation rule.** Tag `el_project_portal` → function `el_shortcode_project_portal`. Not `el_shortcode_es_project_portal`.

3. **Module settings key format:** Settings stored under `el_mod_expand-site`. Access via:
   ```php
   $this->core->settings->get('mod_expand-site', 'setting_key', 'default');
   ```

4. **Never do infrastructure work in the module class.** No `CREATE TABLE`, no `add_shortcode()`, no capability registration. All in `module.json`.

5. **AJAX handlers receive a pre-sanitized `$data` array**, not raw `$_POST`.

6. **Shortcodes return strings, never echo.**

7. **Admin pages use `EL_Admin_UI` methods, never raw HTML.**

8. **All table names in module.json are written WITHOUT the WordPress prefix.** Write `el_es_projects`, not `wp_el_es_projects`.

---

## BUILD ORDER (Suggested)

1. **`module.json`** — Get the manifest right first.
2. **`class-expand-site-module.php`** — Skeleton with AJAX hooks, query methods, action methods.
3. **`admin/views/project-list.php`** — Admin project list page.
4. **`admin/views/project-detail.php`** — Single project view with tabs.
5. **`admin/views/project-form.php`** — Create/edit form.
6. **`assets/css/expand-site.css`** — Module-specific styles.
7. **`assets/js/expand-site.js`** — Module-specific JS.
8. **Shortcodes** — Client-facing portal, status, review, feedback.

---

## QUESTIONS? READ THE HANDOFF DOC

The file `el-core-cursor-handoff.md` in the repo root contains the complete architecture reference, all 10 lessons learned, and detailed explanations of every core class.
