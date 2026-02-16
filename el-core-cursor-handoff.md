# EL Core — Architecture Handoff for Cursor AI

> **Purpose:** This document gives you (the AI assistant in Cursor) complete context on the EL Core WordPress plugin — its architecture, every file, coding conventions, design decisions, and critical lessons learned during development. Use this as your primary reference when working on this codebase.
>
> **Last Updated:** February 15, 2026

---

## 1. WHAT IS EL CORE?

EL Core is a modular WordPress plugin that serves as the foundation for educational technology platforms. It provides LMS, events, certificates, analytics, registration, and more — all configurable per installation through an admin UI.

**The key idea:** This is a **product**, not a **project**. One codebase serves all clients. Each installation is customized through configuration (admin UI settings), not code changes. New features are built as self-contained modules that any installation can activate.

**Owner:** Expanded Learning Solutions LLC (Fred Jones)

---

## 2. ORIGIN STORY & WHY IT EXISTS

EL Core was born from converting the Bold Youth Project — a custom-coded educational platform that grew to 65,000+ lines. Bold Youth worked but had five structural problems that became the driving design principles for EL Core:

### Problem 1: PHP Configuration File → Admin UI Settings
Bold Youth used `bold-youth-config.php` requiring PHP edits to toggle features. A syntax error crashes the site. Non-technical admins couldn't change anything.

**EL Core solution:** All settings stored in `wp_options`, managed through admin UI checkboxes and forms. The `EL_Settings` class handles groups stored as serialized arrays with in-memory caching.

### Problem 2: Hardcoded User Roles → Capability-Based Permissions
Bold Youth baked role names like `boldyouth_student` into code. Next client uses completely different role names.

**EL Core solution:** Code checks CAPABILITIES (`manage_courses`), never role names. Each installation maps capabilities to their own custom roles. The `EL_Roles` class manages a role-capability matrix exposed in the admin UI.

### Problem 3: No Database Migrations → Automatic Schema Versioning
Bold Youth required manual SQL changes per installation. 15 installations = 15 manual updates.

**EL Core solution:** Modules declare schema version in `module.json`. The `EL_Database` class tracks installed versions in `el_core_schema_versions` option and runs migrations automatically on plugin update using `dbDelta()`.

### Problem 4: No Brand Configuration → Admin-Managed Branding
Colors lived in a `BRAND-COLORS.md` file with CSS variables embedded in code.

**EL Core solution:** Admin uploads logo, picks colors/fonts in settings page. Stored in database. Injected as CSS custom properties (`--el-primary`, `--el-accent`, etc.) automatically by the `EL_Asset_Loader` class.

### Problem 5: Monolithic Shortcodes → Component-Level Shortcodes
Bold Youth used monolithic shortcodes like `[boldyouth_group_projects]` that rendered entire interfaces. Admins couldn't edit headings, layout, or text without going back to the developer.

**EL Core solution:** Component-level shortcodes (`[el_event_list]`, `[el_event_rsvp]`) that render ONE focused piece. These are combined with native WordPress blocks in the Gutenberg block editor. Admins edit text and layout visually; the plugin powers the interactive data-driven components.

---

## 3. TWO-LAYER ARCHITECTURE

| Layer | Responsibility | Changed By |
|-------|---------------|------------|
| **Plugin (EL Core)** | Data, logic, AI, APIs, auth, database, modules | Developer |
| **Theme (EL Theme)** | Layout, colors, fonts, spacing, visual presentation | Admin in WordPress |

The plugin exposes helper functions (like `el_core_get_brand_colors()`). The theme calls those functions. **The theme NEVER reaches into plugin class internals.** The global functions in `includes/functions.php` are the API boundary.

---

## 4. MODULE SYSTEM — THE CORE DESIGN PATTERN

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

Settings, database, roles, modules, assets, AJAX, AI client = core (modules literally can't work without them).
Events, Registration, Tutorials, LMS = modules (can be toggled without breaking anything).

---

## 5. FILE STRUCTURE & EVERY FILE EXPLAINED

```
el-core/
├── el-core.php                          # Main plugin loader
├── uninstall.php                        # Cleanup on plugin deletion
├── README.md
│
├── includes/                            # Core system classes (9 files)
│   ├── class-el-core.php                # Orchestrator singleton
│   ├── class-settings.php               # Settings framework
│   ├── class-database.php               # Schema manager
│   ├── class-module-loader.php          # Module discovery & activation
│   ├── class-roles.php                  # Capabilities engine
│   ├── class-asset-loader.php           # CSS/JS loading, brand injection
│   ├── class-ajax-handler.php           # Standardized AJAX
│   ├── class-ai-client.php              # Claude/OpenAI API wrapper
│   └── functions.php                    # Global helper functions (API boundary)
│
├── admin/                               # Admin-side UI
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
├── modules/
│   ├── events/                          # First module (proof of concept)
│   │   ├── module.json
│   │   ├── class-events-module.php
│   │   └── shortcodes/
│   │       ├── event-list.php           # [el_event_list]
│   │       └── event-rsvp.php           # [el_event_rsvp]
│   │
│   └── registration/                    # Second module (Phase 2)
│       ├── module.json
│       ├── class-registration-module.php
│       └── shortcodes/
│           ├── register-form.php        # [el_register_form]
│           └── user-profile.php         # [el_user_profile]
│
└── templates/                           # Gutenberg block markup templates
    ├── events-page.html
    ├── registration-page.html
    └── profile-page.html
```

---

## 6. CORE CLASSES — HOW THEY WORK

### Boot Sequence (class-el-core.php)

The orchestrator is a singleton (`EL_Core::instance()`) that boots subsystems in dependency order:

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
- In-memory caching per request (no repeated `get_option()` calls)
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
- Converts slug to class name: `events` → `EL_Events_Module`

**CRITICAL PATTERN — Shortcode function naming:**
The module loader derives function names from shortcode tags. Tag `el_register_form` → strips `el_` prefix → function `el_shortcode_register_form`. If the function name doesn't match this convention, the shortcode won't register and you'll get an error log.

### AJAX Handler (class-ajax-handler.php)

- Unified endpoint: WordPress action `el_core_action`, routed by `el_action` parameter
- Automatic nonce verification
- Modules hook into `el_core_ajax_{action_name}` for authenticated requests
- Modules hook into `el_core_ajax_nopriv_{action_name}` for guest requests
- Static response helpers: `EL_AJAX_Handler::success()`, `EL_AJAX_Handler::error()`

### Asset Loader (class-asset-loader.php)

- Enqueues `el-core.css` and `el-core.js` on frontend
- `wp_localize_script` provides `elCore.ajaxUrl` and `elCore.nonce` to JS
- Injects brand CSS custom properties via `<style>` tag in `<head>`

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

## 7. MODULES IN DETAIL

### Events Module (Proof of Concept — Complete)

**Purpose:** First module built to prove the architecture works. Events with RSVP functionality.

**Capabilities:** `manage_events`, `create_events`, `rsvp_events`, `view_events`

**Database Tables:**
- `el_events` — id, title, description, start_date, end_date, location, max_attendees, created_by, status, created_at
- `el_event_rsvps` — id, event_id, user_id, status, rsvp_date

**Shortcodes:**
- `[el_event_list limit="6" layout="cards|list"]` — Displays upcoming events
- `[el_event_rsvp event_id="123"]` — RSVP toggle button with AJAX

**AJAX Actions:**
- `rsvp_event` — Toggle RSVP (creates or cancels), checks capacity
- `create_event` — Create new event (requires `create_events` capability)

**Known gap:** No event creation admin UI — events must be created via SQL or AJAX.

### Registration Module (Phase 2 — Built, Needs Testing)

**Purpose:** User registration with configurable workflows: open, approval-based, invite-only, or closed.

**Capabilities:** `manage_registration`, `create_invites`, `view_registrations`

**Database Tables:**
- `el_invites` — code, created_by, role, max_uses, use_count, expires_at, status

**Shortcodes:**
- `[el_register_form]` — Registration form with all workflow support
- `[el_user_profile]` — Profile display and editor for custom fields

**Settings (stored as `el_mod_registration`):**
- `registration_mode` → open / approval / invite / closed
- `email_verification` → boolean (independent of mode)
- `default_role` → what role new users get
- `allow_role_selection` / `allowed_roles` → role picker on form
- `custom_fields` → JSON array of field definitions
- `redirect_after_register` → URL after signup
- `approval_email_notify` → notify admins on new registrations

**Security layers:**
- Honeypot field (hidden `website_url` input — bots fill it, humans don't)
- Rate limiting (5 attempts per IP per 15 minutes, stored as transients)
- Nonce verification on all AJAX
- WordPress default registration disabled and redirected
- Login blocked for pending/unverified users via `authenticate` filter at priority 30
- Expiring one-time email verification tokens (hashed, 24-hour expiry)

**Lifecycle hooks for future modules:**
- `el_registration_before_validate`
- `el_registration_before_create`
- `el_registration_after_create`
- `el_registration_after_verify_email`
- `el_registration_after_approval`
- `el_registration_after_rejection`

---

## 8. CODING CONVENTIONS — FOLLOW THESE EXACTLY

### PHP

- **No namespaces.** Uses `EL_` prefix for all classes (WordPress convention)
- **Singleton pattern:** All module classes use `ModuleName::instance()`
- **Type declarations:** PHP 8.0+ with typed parameters, return types, nullable types
- **Class naming:** `EL_Module_Name` → file is `class-module-name.php`
- **Global functions:** Prefixed with `el_core_`
- **Sanitization:** All user input sanitized (`sanitize_text_field`, `absint`, `wp_kses_post`, `esc_url_raw`)
- **Nonce verification:** All form submissions and AJAX requests
- **Text domain:** `el-core`

### CSS

- All colors use CSS custom properties: `var(--el-primary)`, `var(--el-accent)`, etc.
- Component class prefix: `el-` (e.g., `el-event-card`, `el-btn-primary`)
- Layout class convention: `el-layout-cards`, `el-layout-list`
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

### Module Structure Convention

Every module follows this exact structure:
```
modules/{slug}/
├── module.json
├── class-{slug}-module.php
├── shortcodes/
├── ajax/         (optional)
└── assets/       (optional)
    ├── css/
    └── js/
```

### Shortcode Convention

- Tag format: `el_{component_name}` (e.g., `el_event_list`, `el_user_profile`)
- Function format: `el_shortcode_{component_name}` (e.g., `el_shortcode_event_list`)
- Each shortcode renders ONE focused component, not an entire page
- All shortcode output uses brand CSS variables
- **Shortcodes return HTML strings (never echo)**

---

## 9. CRITICAL LESSONS LEARNED

These are things that were discovered and fixed during development. They'll save you debugging time.

### Lesson 1: CSS Class Names Must Match Across All Three Layers

**What happened:** The shortcode PHP files were initially written with class names like `el-form-group`, `el-form-label`, `el-form-input`. The CSS file used `el-field`, `el-label`, `el-input`. The JavaScript selectors referenced yet a third set. Nothing styled correctly and interactive features didn't work.

**The rule:** There are THREE files that must agree on class names:
1. Shortcode PHP (HTML output) — e.g., `register-form.php`
2. CSS — `el-core.css`
3. JavaScript — `el-core.js`

When you add a component, check ALL THREE. The canonical names are defined in the CSS. Shortcodes and JS must match them.

**Current canonical CSS class names:**
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

**What happened:** Shortcodes wouldn't register because the function name didn't match what the module loader expected.

**The rule:** The module loader strips the `el_` prefix from the tag and prepends `el_shortcode_`:
- Tag `el_register_form` → function `el_shortcode_register_form`
- Tag `el_event_list` → function `el_shortcode_event_list`
- Tag `el_user_profile` → function `el_shortcode_user_profile`

If you name the function wrong, you'll see `EL Core: Shortcode function 'xxx' not found for tag 'yyy'` in the error log.

### Lesson 3: Module Settings Key Format

**What happened:** Settings weren't loading for modules because the key format wasn't consistent.

**The rule:** Module settings are stored under `el_mod_{slug}`. The Registration module stores its settings in `el_mod_registration`. When accessing settings from within a module class, use:
```php
$this->core->settings->get('mod_registration', 'registration_mode', 'closed');
```
Note the `mod_` prefix in the group name.

### Lesson 4: Guest AJAX Requires Both Hooks

**What happened:** Registration form AJAX failed for non-logged-in users.

**The rule:** For any AJAX action that guest (non-logged-in) users need, you must register BOTH hooks:
```php
add_action('el_core_ajax_register_user', [$this, 'handle_register_user']);
add_action('el_core_ajax_nopriv_register_user', [$this, 'handle_register_user']);
```
The `nopriv` variant is for guests. Without it, non-authenticated users get "Authentication required."

### Lesson 5: Email Verification Uses GET, Not AJAX

**What happened:** The email verification link handler was registered on `template_redirect` but the actual method body was never written, so clicking the link in the email did nothing.

**The rule:** Email verification links use GET parameters (`?el_action=verify_email&user=X&token=Y`) intercepted on `template_redirect`, NOT the AJAX system. This is because the user clicks a link in their email — they're not submitting a form. The `handle_verification_link()` method on the Registration module handles this by:
1. Checking for the `el_action=verify_email` query parameter
2. Validating the user ID and token
3. Rendering a standalone page using `get_header()`/`get_footer()` and `exit`

### Lesson 6: The Singleton Pattern Must Be Consistent

**The rule:** Every module class uses this exact pattern:
```php
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
The constructor is **private**. All access goes through `::instance()`. The module loader calls this when activating a module.

### Lesson 7: Modules Must NOT Do Infrastructure Work

**What happened:** Early drafts had modules creating their own database tables and registering their own shortcodes. This duplicated core functionality and broke when the core evolved.

**The rule:** Modules declare infrastructure needs in `module.json`. They never call:
- `$wpdb->query("CREATE TABLE...")` — declare tables in module.json
- `add_shortcode()` — declare shortcodes in module.json
- Capability registration — declare in module.json
- Settings page rendering — core handles it from module.json

The module class file contains ONLY business logic, AJAX handlers, and helper methods.

### Lesson 8: Token Security Pattern

**The rule for email verification tokens (and any future one-time tokens):**
1. Generate a random string: `wp_generate_password(32, false, false)`
2. Store the HASH: `wp_hash_password($raw_token)`
3. Send the RAW token in the email/link
4. On verification, check with `wp_check_password($raw_token, $stored_hash)`
5. Delete the token after use (one-time only)
6. Set an expiration timestamp and check it before validating

Never store raw tokens. Never reuse tokens.

### Lesson 9: WordPress Authentication Hook Priority

**The rule:** The `authenticate` filter at priority 30 runs AFTER WordPress validates the username/password at priority 20. This is intentional — we only block login for users who passed credential checks but have a pending/rejected registration status or unverified email. If you hook at a lower priority, you'd interfere with WordPress's own authentication.

### Lesson 10: Rate Limiting Uses Transients

**The rule:** Registration rate limiting uses WordPress transients keyed by `md5($ip)` with a 15-minute TTL. This is simple and works without external dependencies. The limit is 5 attempts per IP per window. The increment happens BEFORE the registration attempt (not after success), so failed attempts count too.

---

## 10. MODULE.JSON SCHEMA REFERENCE

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
            "2": ["ALTER TABLE el_table_name ADD COLUMN new_col VARCHAR(255)"],
            "3": ["ALTER TABLE el_table_name ADD INDEX idx_col (new_col)"]
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

## 11. CURRENT STATUS & WHAT'S LEFT

### Completed
- Core foundation (all 9 infrastructure classes)
- Admin settings pages (Dashboard, Brand, Modules, Roles)
- Events module (database, business logic, shortcodes, AJAX)
- Registration module (business logic, shortcodes, AJAX, email verification, invite codes, approval workflow)
- Frontend CSS with brand variable system
- Frontend JS with AJAX helper, registration form handler, profile form handler, RSVP handler
- Gutenberg page templates for events, registration, and profile pages

### Registration Module — Fully Built But Untested on Live Site
All code is written. Needs deployment to a WordPress site for testing. The module handles:
- Four registration modes (open, approval, invite-only, closed)
- Email verification with secure token system
- Invite code management with expiration and usage limits
- Custom profile fields (JSON-configured)
- Login enforcement for pending/unverified users
- Admin approval/rejection workflow
- Rate limiting and honeypot spam protection

### Planned Modules (Future)
1. **Tutorials** — content management for help resources (ships pre-activated by default)
2. **Support Agent** — AI-powered help (depends on Tutorials module)
3. **LMS** — courses, lessons, progress, AI Tutor sub-feature (revenue driver)
4. **Certificates** — PDF generation, completion certificates
5. **Analytics** — dashboards, reports, data export
6. **Notifications** — email templates, in-app notifications
7. **EL Theme** — companion block theme
8. **AI Page Generation Pipeline** — Claude generates Gutenberg block markup from prompts

### Known Gaps
- No event creation admin UI (events require SQL or AJAX)
- `uninstall.php` capability cleanup needs improvement
- No REST API endpoints yet (AJAX only)
- Block pattern registration system not yet built (planned for Phase 7/8)

---

## 12. DEVELOPMENT ENVIRONMENT

- **PHP:** 8.0+ required (checked on activation)
- **WordPress:** 6.0+ required
- **Database:** MySQL via WordPress `$wpdb`
- **Hosting:** Rocket.net (managed WordPress hosting)
- **Local repo:** `C:\Github\EL Core\` — source files
- **Release ZIPs:** `C:\Github\EL Core\releases\` — packaged plugin versions
- **Deployment:** ZIP files uploaded through WordPress admin → Plugins → Add New → Upload Plugin
- **Plugin text domain:** `el-core`
- **All table names prefixed:** `{wp_prefix}el_` (e.g., `wp_el_events`)
- **All option names prefixed:** `el_core_` or `el_mod_`

---

## 13. QUICK REFERENCE — ADDING A NEW MODULE

1. Create `modules/{slug}/module.json` with all declarations
2. Create `modules/{slug}/class-{slug}-module.php` with business logic
3. Create shortcode files in `modules/{slug}/shortcodes/`
4. Add CSS for new components to `assets/css/el-core.css`
5. Add JS handlers to `assets/js/el-core.js`
6. Verify class names match across PHP, CSS, and JS
7. Register AJAX hooks in the module constructor (both priv and nopriv if needed)
8. Create Gutenberg page template in `templates/`

The module loader will automatically discover the module, register its shortcodes, create its tables, and register its capabilities — all from `module.json`.
