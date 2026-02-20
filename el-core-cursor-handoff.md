# EL Core — Architecture Handoff for Cursor AI

> **Purpose:** This document gives you (Cursor) complete context on the EL Core WordPress plugin — its architecture, every file, coding conventions, design decisions, and critical lessons learned. Use this as your primary reference when working on this codebase.
>
> **Last Updated:** February 20, 2026
> **Current Plugin Version:** v1.3.0
> **Deployed On:** expandedlearningsolutions.com

---

## 1. WHAT IS EL CORE?

EL Core is a modular WordPress plugin that serves as the foundation for educational technology platforms. It provides LMS, events, certificates, analytics, registration, and more — all configurable per installation through an admin UI.

**The key idea:** This is a **product**, not a **project**. One codebase serves all clients. Each installation is customized through configuration (admin UI settings), not code changes. New features are built as self-contained modules that any installation can activate.

**Owner:** Expanded Learning Solutions LLC (Fred Jones)

---

## 2. TWO-LAYER ARCHITECTURE

| Layer | Responsibility | Changed By |
|-------|---------------|------------|
| **Plugin (EL Core)** | Data, logic, AI, APIs, auth, database, modules | Developer |
| **Theme (EL Theme)** | Layout, colors, fonts, spacing, visual presentation | Admin in WordPress |

The plugin exposes helper functions (like `el_core_get_brand_colors()`). The theme calls those functions. **The theme NEVER reaches into plugin class internals.** The global functions in `includes/functions.php` are the API boundary.

---

## 3. MODULE SYSTEM — THE CORE DESIGN PATTERN

**Modules declare WHAT they need. Core handles HOW.**

Every feature is a self-contained module with a `module.json` manifest that declares:
- Database tables and schema version
- Capabilities (permissions)
- Default role mappings
- Shortcodes
- Settings
- Dependencies on other modules

The core automatically:
- Creates/migrates database tables
- Registers capabilities with WordPress
- Registers shortcodes
- Renders settings in admin UI

**Module classes contain ONLY business logic.** No `CREATE TABLE`, no `add_shortcode()`, no settings page rendering. If you find infrastructure code in a module class, it's wrong.

### The Module-vs-Core Test

If other code depends on it to function → it's **core**.
If it's a feature, even one every installation uses → it's a **module**.

Settings, database, roles, modules, assets, AJAX, AI client, admin UI = core.
Events, Registration, Expand Site, Tutorials, LMS = modules.

---

## 4. FILE STRUCTURE

```
el-core/
├── el-core.php                          # Main plugin loader, constants, activation hooks
├── uninstall.php                        # Cleanup on plugin deletion
├── README.md
│
├── includes/                            # Core system classes (10 files)
│   ├── class-el-core.php                # Orchestrator singleton
│   ├── class-settings.php               # Settings framework
│   ├── class-database.php               # Schema manager
│   ├── class-module-loader.php          # Module discovery & activation
│   ├── class-roles.php                  # Capabilities engine
│   ├── class-asset-loader.php           # CSS/JS loading, brand injection
│   ├── class-ajax-handler.php           # Standardized AJAX
│   ├── class-ai-client.php              # Claude/OpenAI API wrapper
│   ├── class-admin-ui.php               # Shared admin UI framework (added v1.3.0)
│   └── functions.php                    # Global helper functions (API boundary)
│
├── admin/                               # Core admin-side UI
│   ├── views/
│   │   ├── settings-general.php         # Dashboard overview
│   │   ├── settings-brand.php           # Colors, logo, fonts, AI config
│   │   ├── settings-modules.php         # Module toggle UI
│   │   └── settings-roles.php           # Role-capability matrix
│   ├── css/admin.css
│   └── js/admin.js
│
├── assets/                              # Shared frontend assets
│   ├── css/el-core.css                  # Component styles (CSS variables)
│   └── js/el-core.js                    # AJAX helper, form handlers, RSVP
│
└── modules/
    ├── events/                          # Events with RSVP — functional, no admin UI
    │   ├── module.json
    │   ├── class-events-module.php
    │   └── shortcodes/
    │       ├── event-list.php           # [el_event_list]
    │       └── event-rsvp.php           # [el_event_rsvp]
    │
    ├── registration/                    # User registration — code complete, untested
    │   ├── module.json
    │   ├── class-registration-module.php
    │   └── shortcodes/
    │       ├── register-form.php        # [el_register_form]
    │       └── user-profile.php        # [el_user_profile]
    │
    ├── expand-site/                     # Site-building client management (YOUR module)
    │   ├── module.json
    │   ├── class-expand-site-module.php
    │   ├── admin/views/
    │   │   ├── project-list.php
    │   │   ├── project-detail.php
    │   │   └── project-form.php
    │   ├── shortcodes/
    │   │   ├── project-portal.php       # [el_project_portal]
    │   │   ├── project-status.php       # [el_project_status]
    │   │   ├── page-review.php          # [el_page_review]
    │   │   └── feedback-form.php        # [el_feedback_form]
    │   └── assets/
    │       ├── css/expand-site.css
    │       └── js/expand-site.js
    │
    ├── fluent-crm-integration/          # FluentCRM sync — functional
    │   ├── module.json
    │   └── class-fluent-crm-integration-module.php
    │
    └── ai-integration/                  # AI features — functional
        ├── module.json
        ├── class-ai-integration-module.php
        └── admin/settings-page.php
```

> ⚠️ `modules/project-management/` has been deleted. It is fully replaced by `modules/expand-site/`.

---

## 5. CORE CLASSES — HOW THEY WORK

### Boot Sequence (class-el-core.php)

Singleton (`EL_Core::instance()`) that boots in dependency order:

1. **Settings** → everything reads config
2. **Database** → modules need schema management
3. **Roles** → modules need capability checking
4. **Modules** → discovers and activates feature modules
5. **Assets** → CSS/JS with brand variables
6. **AJAX** → request handling
7. **AI Client** → shared AI integration

All subsystems are public properties: `$core->settings`, `$core->database`, etc.

### Settings (class-settings.php)

- Groups stored as serialized arrays: `el_core_brand`, `el_core_ai`, `el_core_modules`, `el_mod_{slug}`
- In-memory caching per request
- Brand CSS variable generation: `get_brand_css_variables()`
- WordPress Settings API integration with sanitization callbacks

### Database (class-database.php)

- Tracks installed schema versions in `el_core_schema_versions` option
- `process_module_schema()` compares installed vs declared version, runs migrations
- Uses WordPress `dbDelta()` for safe table creation
- Convenience methods: `insert()`, `update()`, `delete()`, `get()`, `query()`, `count()`
- Supports operators in where clauses: `['start_date >' => '2024-01-01']`
- All table names auto-prefixed with `{wp_prefix}` for multisite compatibility

### Module Loader (class-module-loader.php)

- Scans `modules/` directory for `module.json` files
- Validates PHP version and EL Core version requirements
- Resolves dependencies (auto-activates required modules)
- Prevents deactivation if other modules depend on it
- Registers shortcodes from manifest declarations
- Converts slug to class name: `expand-site` → `EL_Expand_Site_Module`

**CRITICAL PATTERN — Shortcode function naming:**
The module loader strips the `el_` prefix from the tag and prepends `el_shortcode_`:
- Tag `el_project_portal` → function `el_shortcode_project_portal`
- Tag `el_event_list` → function `el_shortcode_event_list`

If the function name doesn't match, you'll see `EL Core: Shortcode function 'xxx' not found for tag 'yyy'` in the error log.

### AJAX Handler (class-ajax-handler.php)

- Unified endpoint: WordPress action `el_core_action`, routed by `el_action` parameter
- Automatic nonce verification
- Modules hook into `el_core_ajax_{action_name}` for authenticated requests
- Modules hook into `el_core_ajax_nopriv_{action_name}` for guest requests
- Static response helpers: `EL_AJAX_Handler::success()`, `EL_AJAX_Handler::error()`

### Admin UI Framework (class-admin-ui.php) — Added v1.3.0

**Every admin view in every module uses this class.** Never write raw HTML for admin tables, forms, or cards. Always use the framework methods.

Key methods (read the class file for full API):
- `EL_Admin_UI::page_wrap($title, $content)` — standard page wrapper
- `EL_Admin_UI::card($title, $content, $actions)` — card component
- `EL_Admin_UI::data_table($headers, $rows, $options)` — sortable table
- `EL_Admin_UI::form_field($args)` — renders labeled input/select/textarea
- `EL_Admin_UI::button($label, $args)` — button component
- `EL_Admin_UI::notice($message, $type)` — info/success/warning/error notice

Always read `includes/class-admin-ui.php` before building admin views. The available components and their parameters are defined there.

### Asset Loader (class-asset-loader.php)

- Enqueues `el-core.css` and `el-core.js` on frontend
- `wp_localize_script` provides `elCore.ajaxUrl` and `elCore.nonce` to JS
- Injects brand CSS custom properties via `<style>` tag in `<head>`
- Module-specific assets are enqueued by each module class

### AI Client (class-ai-client.php)

- Supports Anthropic (Claude) and OpenAI
- `complete()` method with system prompt, user prompt, model/token overrides
- Usage logging by day (30-day retention)
- `is_configured()` check for admin UI status display

### Global Helpers (functions.php)

These are the **API boundary**. Themes and external code use these, never class internals:

- `el_core_get_brand()`, `el_core_get_brand_colors()`, `el_core_get_org_name()`
- `el_core_get_logo_url()`, `el_core_get_font_heading()`, `el_core_get_font_body()`
- `el_core_module_active()`, `el_core_get_active_modules()`
- `el_core_can()`, `el_core_user_can()`
- `el_core_db()`, `el_core_ai_complete()`

---

## 6. MODULES IN DETAIL

### Events Module (Functional — No Admin UI)

**Capabilities:** `manage_events`, `create_events`, `rsvp_events`, `view_events`

**Database Tables:**
- `el_events` — id, title, description, start_date, end_date, location, max_attendees, created_by, status, created_at
- `el_event_rsvps` — id, event_id, user_id, status, rsvp_date

**Shortcodes:**
- `[el_event_list limit="6" layout="cards|list"]`
- `[el_event_rsvp event_id="123"]`

**Known gap:** No event creation admin UI — events require SQL or AJAX.

### Registration Module (Code Complete — Untested)

**Capabilities:** `manage_registration`, `create_invites`, `view_registrations`

**Database Tables:**
- `el_invites` — code, created_by, role, max_uses, use_count, expires_at, status

**Shortcodes:**
- `[el_register_form]` — Registration form with all workflow support
- `[el_user_profile]` — Profile display and editor

**Settings (stored as `el_mod_registration`):**
- `registration_mode` → open / approval / invite / closed
- `email_verification` → boolean
- `default_role`, `allow_role_selection`, `allowed_roles`
- `custom_fields` → JSON array of field definitions
- `redirect_after_register`, `approval_email_notify`

**Security layers:** Honeypot field, rate limiting (5/IP/15min via transients), nonce verification, authentication filter at priority 30, hashed one-time email verification tokens.

### Expand Site Module (YOUR MODULE — Built by Cursor)

**Purpose:** Fred's site-building service (Expand Site) client pipeline management. 8-stage workflow from Qualification through Delivery. Client-facing portal so clients can track progress, review deliverables, and submit feedback.

**8-Stage Pipeline:**
1. Qualification
2. Discovery
3. Scope Lock
4. Visual Identity
5. Wireframes
6. Build
7. Review
8. Delivery

**Capabilities:** `manage_projects`, `view_own_project`, `submit_feedback`

**Database Tables (all use `el_es_` prefix):**
- `el_es_projects` — project records
- `el_es_stage_history` — stage transition log
- `el_es_deliverables` — deliverable items per stage
- `el_es_feedback` — client feedback submissions
- `el_es_pages` — pages being delivered for review

**Shortcodes:**
- `[el_project_portal]` → function `el_shortcode_project_portal`
- `[el_project_status]` → function `el_shortcode_project_status`
- `[el_page_review]` → function `el_shortcode_page_review`
- `[el_feedback_form]` → function `el_shortcode_feedback_form`

**CSS prefix:** `el-es-` for all components
**Asset files:** `assets/css/expand-site.css`, `assets/js/expand-site.js`

---

## 7. CODING CONVENTIONS — FOLLOW THESE EXACTLY

### PHP

- **No namespaces.** Uses `EL_` prefix for all classes (WordPress convention)
- **Singleton pattern:** All module classes use `ModuleName::instance()`
- **Type declarations:** PHP 8.0+ with typed parameters, return types, nullable types
- **Class naming:** `EL_Expand_Site_Module` → file is `class-expand-site-module.php`
- **Global functions:** Prefixed with `el_core_`
- **Sanitization:** All user input sanitized (`sanitize_text_field`, `absint`, `wp_kses_post`, `esc_url_raw`)
- **Nonce verification:** All form submissions and AJAX requests
- **Text domain:** `el-core`

```php
// Singleton pattern — every module class uses this exactly
class EL_Module_Name {
    private static ?EL_Module_Name $instance = null;
    
    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->core = EL_Core::instance();
        $this->init_hooks();
    }
}
```

### CSS

- All colors use CSS custom properties: `var(--el-primary)`, `var(--el-accent)`, etc.
- Shared component prefix: `el-` (e.g., `el-event-card`, `el-btn-primary`)
- Expand Site component prefix: `el-es-` (e.g., `el-es-project-card`, `el-es-stage-bar`)
- Component wrapper: `el-component` class on outermost element
- Form elements: `el-field`, `el-label`, `el-input`, `el-select`, `el-textarea`
- Buttons: `el-btn`, `el-btn-primary`, `el-btn-accent`, `el-btn-outline`
- Notices: `el-notice`, `el-notice-info`, `el-notice-success`, `el-notice-warning`, `el-notice-error`

### JavaScript

- Vanilla JS for frontend (no jQuery dependency on frontend)
- jQuery used in admin only (WordPress provides it)
- Global `ELCore.ajax()` method for all AJAX calls
- Event delegation on `document` for dynamically rendered components
- `elCore.ajaxUrl` and `elCore.nonce` provided by `wp_localize_script`

---

## 8. CRITICAL LESSONS LEARNED

### Lesson 1: CSS Class Names Must Match Across All Three Layers

There are THREE files that must agree on class names:
1. Shortcode PHP (HTML output)
2. CSS file
3. JavaScript file

**Canonical shared CSS class names:**
- Form wrapper: `el-form`
- Field wrapper: `el-field`
- Labels: `el-label`
- Text inputs: `el-input`
- Select dropdowns: `el-select`
- Textareas: `el-textarea`
- Required markers: `el-required`
- Submit wrapper: `el-field-submit`
- Side-by-side fields: `el-field-row`
- Form footer: `el-form-footer`
- Status messages: `el-form-status`
- Component wrapper: `el-component`

### Lesson 2: Shortcode Function Name Derivation

The module loader strips `el_` prefix from the tag and prepends `el_shortcode_`:
- Tag `el_project_portal` → function `el_shortcode_project_portal`
- Tag `el_event_list` → function `el_shortcode_event_list`

### Lesson 3: Module Settings Key Format

Module settings stored under `el_mod_{slug}`. Access from within module class:
```php
$this->core->settings->get('mod_expand_site', 'setting_key', 'default');
```

### Lesson 4: Guest AJAX Requires Both Hooks

For any AJAX action that non-logged-in users need:
```php
add_action('el_core_ajax_my_action', [$this, 'handle_my_action']);
add_action('el_core_ajax_nopriv_my_action', [$this, 'handle_my_action']);
```

### Lesson 5: Modules Must NOT Do Infrastructure Work

Modules declare needs in `module.json`. They never call:
- `$wpdb->query("CREATE TABLE...")` — declare in module.json
- `add_shortcode()` — declare in module.json
- Capability registration — declare in module.json
- Settings page rendering — core handles from module.json

### Lesson 6: Admin UI — Always Use the Framework

Never write raw HTML for admin tables, cards, or forms. Always use `EL_Admin_UI::*` methods. Read `includes/class-admin-ui.php` before building any admin view.

### Lesson 7: Token Security Pattern

1. Generate: `wp_generate_password(32, false, false)`
2. Store the HASH: `wp_hash_password($raw_token)`
3. Send the RAW token in the email/link
4. Verify: `wp_check_password($raw_token, $stored_hash)`
5. Delete token after use (one-time only)
6. Check expiration timestamp before validating

### Lesson 8: WordPress Authentication Hook Priority

The `authenticate` filter at priority 30 runs AFTER WordPress validates credentials at priority 20. Hook at 30 to block users who passed credential checks but have pending/unverified status.

---

## 9. MODULE.JSON SCHEMA REFERENCE

```json
{
    "name": "Human-readable module name",
    "slug": "url-safe-identifier",
    "version": "1.0.0",
    "description": "What this module does",
    "author": "Expanded Learning Solutions",

    "requires": {
        "el_core": "1.0.0",
        "php": "8.0",
        "modules": ["other-module-slugs"]
    },

    "capabilities": ["manage_thing", "create_thing", "view_thing"],

    "default_role_mapping": {
        "administrator": ["manage_thing", "create_thing", "view_thing"],
        "editor": ["create_thing", "view_thing"],
        "subscriber": ["view_thing"]
    },

    "database": {
        "version": 1,
        "tables": {
            "el_table_name": {
                "column_name": "SQL_DEFINITION"
            }
        },
        "migrations": {
            "2": ["ALTER TABLE el_table_name ADD COLUMN new_col VARCHAR(255)"]
        }
    },

    "shortcodes": [
        {
            "tag": "el_shortcode_name",
            "file": "shortcodes/filename.php",
            "description": "What this shortcode renders",
            "params": {
                "param_name": { "type": "string", "default": "value" }
            }
        }
    ],

    "settings": [
        {
            "key": "setting_key",
            "label": "Human Label",
            "type": "number|string|boolean",
            "default": "value"
        }
    ]
}
```

---

## 10. DEVELOPMENT ENVIRONMENT

- **PHP:** 8.0+ required
- **WordPress:** 6.0+ required
- **Hosting:** Rocket.net (managed WordPress hosting)
- **Deployed on:** expandedlearningsolutions.com
- **Local repo:** `C:\Github\EL Core\` — source files
- **Plugin source:** `C:\Github\EL Core\el-core\`
- **Build script:** `C:\Github\EL Core\build-zip.ps1` — uses .NET ZipFile, NOT Compress-Archive
- **Deployment:** ZIP upload via WordPress Admin → Plugins → Add New → Upload Plugin
- **WordPress MCP is NOT connected** — do not use wp_fs_write or any MCP tools
- **Plugin text domain:** `el-core`
- **Table prefix:** `{wp_prefix}el_`
- **Option prefix:** `el_core_` or `el_mod_`

### ZIP Build Rule

```powershell
# Run from repo root
.\build-zip.ps1
```

Always outputs `el-core.zip` (no version number in filename). Upload through WP Admin. Version bump requires updating BOTH the plugin header in `el-core.php` AND the `EL_CORE_VERSION` constant.

---

## 11. QUICK REFERENCE — ADDING A NEW MODULE

1. Create `modules/{slug}/module.json` with all declarations
2. Create `modules/{slug}/class-{slug}-module.php` with business logic only
3. Create shortcode files in `modules/{slug}/shortcodes/` — function names must follow derivation rule
4. Create module-specific assets in `modules/{slug}/assets/css/` and `assets/js/`
5. Use `el-{slug}-` CSS prefix for all module components
6. Register AJAX hooks in constructor (both priv and nopriv if needed)
7. Use `EL_Admin_UI::*` for all admin views — never raw HTML
8. Verify CSS class names match across PHP, CSS, and JS

The module loader handles discovery, shortcode registration, table creation, and capability registration automatically from `module.json`.
