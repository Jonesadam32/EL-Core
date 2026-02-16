# EL Core â€” Project Brief

> **Purpose:** This document is the primary knowledge source for the EL Core WordPress plugin project. It captures all architecture decisions, what has been built, what is planned, coding conventions, and workflow context. Any conversation in this project should have full context from this document.

> **Last Updated:** February 12, 2026
> **Status:** Phase 1 complete (core foundation + Events module). Starting Phase 2 (Registration module).

---

## 1. PROJECT OVERVIEW

### What Is EL Core?

EL Core is a modular WordPress plugin that serves as the foundation for educational technology platforms built by Expanded Learning Solutions LLC. It provides LMS, events, certificates, analytics, registration, and more â€” all configurable per installation through an admin UI.

### The Business Problem

Fred (the developer/owner) builds custom educational platforms for organizations â€” school districts, after-school programs, youth organizations. Previously, each client got a custom-coded solution (like the Bold Youth Project, which grew to 65,000+ lines). This approach doesn't scale: every new client means rebuilding similar features from scratch, and updates to one installation don't benefit others.

### The Solution

EL Core is a **product**, not a **project**. One codebase serves all clients. Each installation is customized through configuration (admin UI settings), not code changes. New features are built as modules that any installation can activate. This means:

- New client setup takes 30-60 minutes instead of weeks
- Bug fixes deploy to everyone simultaneously
- Features built for one client become available to all
- No forked codebases to maintain

### Revenue Context

Expanded Learning Solutions generates ~$159K annually across retreat facilitation, LMS licensing, professional development, and coaching services. EL Core is the technology backbone that enables scaling the LMS and platform licensing revenue streams.

---

## 2. ARCHITECTURE DECISIONS

### Two-Layer Architecture: Plugin + Theme

| Layer | Responsibility | Changed By |
|-------|---------------|------------|
| **Plugin (EL Core)** | Data, logic, AI, APIs, auth, database, modules | Developer |
| **Theme (EL Theme)** | Layout, colors, fonts, spacing, visual presentation | Admin in WordPress |

The plugin exposes helper functions (like `el_core_get_brand_colors()`). The theme calls those functions. The theme NEVER reaches into plugin class internals. This is the API boundary.

### Why These Decisions Were Made (Lessons from Bold Youth)

The Bold Youth Project worked but had five structural problems that EL Core fixes:

**Problem 1: PHP Configuration File**
- Bold Youth used `bold-youth-config.php` that required editing PHP to toggle features
- Non-technical admins couldn't change settings; syntax errors crashed the site
- **EL Core fix:** All settings stored in `wp_options`, managed through admin UI checkboxes and forms

**Problem 2: Hardcoded User Roles**
- Bold Youth had roles like `boldyouth_student`, `boldyouth_intern` baked into code
- Next client uses different role names (Coach, District Lead, etc.)
- **EL Core fix:** Code checks CAPABILITIES (`manage_courses`), not role names. Each installation maps capabilities to their own custom roles.

**Problem 3: Database Without Migrations**
- Bold Youth required manual SQL changes per installation
- 15 installations = 15 manual updates, easy to miss one
- **EL Core fix:** Modules declare schema version in module.json. Core runs migrations automatically on plugin update.

**Problem 4: No Brand Configuration**
- Colors were in a BRAND-COLORS.md file, CSS variables embedded in code
- Customization required editing code files
- **EL Core fix:** Admin uploads logo, picks colors/fonts in settings page. Stored in database. Injected as CSS custom properties automatically.

**Problem 5: Page-Level Shortcodes**
- Bold Youth used monolithic shortcodes like `[boldyouth_group_projects]` that rendered entire interfaces
- Admins couldn't edit headings, layout, or text without going back to the developer
- **EL Core fix:** Component-level shortcodes (`[el_event_list]`, `[el_event_rsvp]`) combined with native WordPress blocks in the Gutenberg block editor. Admins edit text and layout visually; plugin powers the interactive data-driven components.

### Module System

Every feature is a self-contained module with a `module.json` manifest that declares:
- Database tables and schema version
- Capabilities (permissions)
- Default role mappings
- Shortcodes
- Settings
- Dependencies on other modules

The core handles ALL infrastructure automatically based on the manifest:
- Creates/migrates database tables
- Registers capabilities with WordPress
- Registers shortcodes
- Renders settings in admin UI

**Modules declare WHAT they need. Core handles HOW.**

### Page Assembly Workflow

1. Describe desired page to Claude
2. Claude generates Gutenberg block markup (HTML)
3. Push to WordPress via MCP or paste into block editor Code view
4. Page appears as editable blocks with embedded shortcodes
5. Plugin powers the interactive shortcode components
6. Admin can edit text, headings, layout visually â€” no developer needed

---

## 3. WHAT HAS BEEN BUILT (v1.0.0)

### File Structure

```
el-core/
â”œâ”€â”€ el-core.php                          # Main plugin loader, constants, activation hooks
â”œâ”€â”€ uninstall.php                        # Cleanup on plugin deletion
â”œâ”€â”€ README.md
â”‚
â”œâ”€â”€ includes/                            # Core system classes
â”‚   â”œâ”€â”€ class-el-core.php                # Orchestrator (singleton, boot sequence)
â”‚   â”œâ”€â”€ class-settings.php               # Settings framework (wp_options, groups, caching)
â”‚   â”œâ”€â”€ class-database.php               # Schema manager (versioning, migrations, queries)
â”‚   â”œâ”€â”€ class-module-loader.php          # Module discovery, validation, activation
â”‚   â”œâ”€â”€ class-roles.php                  # Capabilities engine, role mapping
â”‚   â”œâ”€â”€ class-asset-loader.php           # CSS/JS loading, brand variable injection
â”‚   â”œâ”€â”€ class-ajax-handler.php           # Standardized AJAX with nonce verification
â”‚   â”œâ”€â”€ class-ai-client.php              # Claude/OpenAI API wrapper with usage tracking
â”‚   â””â”€â”€ functions.php                    # Global helper functions (API boundary)
â”‚
â”œâ”€â”€ admin/                               # Admin-side UI
â”‚   â”œâ”€â”€ views/
â”‚   â”‚   â”œâ”€â”€ settings-general.php         # Dashboard overview
â”‚   â”‚   â”œâ”€â”€ settings-brand.php           # Colors, logo, fonts, AI config
â”‚   â”‚   â”œâ”€â”€ settings-modules.php         # Module toggle UI
â”‚   â”‚   â””â”€â”€ settings-roles.php           # Role-capability matrix
â”‚   â”œâ”€â”€ css/admin.css
â”‚   â””â”€â”€ js/admin.js
â”‚
â”œâ”€â”€ assets/                              # Shared frontend assets
â”‚   â”œâ”€â”€ css/el-core.css                  # Component styles using CSS variables
â”‚   â””â”€â”€ js/el-core.js                    # AJAX helper, RSVP handler
â”‚
â”œâ”€â”€ modules/
â”‚   â””â”€â”€ events/                          # First module (proof of concept)
â”‚       â”œâ”€â”€ module.json                  # Manifest
â”‚       â”œâ”€â”€ class-events-module.php      # Business logic
â”‚       â””â”€â”€ shortcodes/
â”‚           â”œâ”€â”€ event-list.php           # [el_event_list] - cards/list display
â”‚           â””â”€â”€ event-rsvp.php           # [el_event_rsvp] - toggle RSVP button
â”‚
â””â”€â”€ templates/
    â””â”€â”€ events-page.html                 # Gutenberg block markup template
```

### Core Classes Summary

**class-el-core.php (Orchestrator)**
- Singleton pattern: `EL_Core::instance()`
- Boot order: Settings â†’ Database â†’ Roles â†’ Modules â†’ Assets â†’ AJAX â†’ AI
- Registers admin menu with subpages
- All subsystems accessible: `$core->settings`, `$core->database`, etc.

**class-settings.php (Configuration Engine)**
- Groups stored as serialized arrays: `el_core_brand`, `el_core_ai`, `el_core_modules`, `el_mod_{slug}`
- In-memory caching per request
- Sanitization callbacks for each group
- Brand CSS variable generation: `get_brand_css_variables()`
- WordPress Settings API integration

**class-database.php (Schema Manager)**
- Tracks installed versions in `el_core_schema_versions` option
- `process_module_schema()` â€” compares installed vs declared, runs migrations
- Uses WordPress `dbDelta()` for safe table creation
- Convenience methods: `insert()`, `update()`, `delete()`, `get()`, `query()`, `count()`
- Supports operators in where clauses: `['start_date >' => '2024-01-01']`

**class-module-loader.php (Module Manager)**
- Scans `modules/` directory for `module.json` files
- Validates PHP version and EL Core version requirements
- Resolves dependencies (auto-activates required modules)
- Prevents deactivation if other modules depend on it
- Registers shortcodes from manifest declarations

**class-roles.php (Permissions Engine)**
- Collects capabilities from all active module manifests
- `apply_default_mappings()` â€” sets initial role permissions on activation
- `get_roles_with_caps()` â€” feeds the admin matrix UI
- `update_role_capabilities()` â€” saves admin form changes
- `create_role()` / `remove_role()` â€” custom role management

**class-asset-loader.php**
- Enqueues `el-core.css` and `el-core.js` on frontend
- `wp_localize_script` provides `elCore.ajaxUrl` and `elCore.nonce` to JS
- Injects brand CSS custom properties via `<style>` tag in `<head>`

**class-ajax-handler.php**
- Unified endpoint: WordPress action `el_core_action`, routed by `el_action` parameter
- Automatic nonce verification
- Modules hook into `el_core_ajax_{action_name}` and `el_core_ajax_nopriv_{action_name}`
- Static response helpers: `EL_AJAX_Handler::success()`, `EL_AJAX_Handler::error()`

**class-ai-client.php**
- Supports Anthropic (Claude) and OpenAI
- `complete()` method with system prompt, user prompt, model/token overrides
- Usage logging by day (stored in `el_core_ai_usage` option, 30-day retention)
- `is_configured()` check for admin UI status display

**functions.php (Global Helpers)**
- `el_core_get_brand()`, `el_core_get_brand_colors()`, `el_core_get_org_name()`
- `el_core_get_logo_url()`, `el_core_get_font_heading()`, `el_core_get_font_body()`
- `el_core_module_active()`, `el_core_get_active_modules()`
- `el_core_can()`, `el_core_user_can()`
- `el_core_db()`, `el_core_ai_complete()`

### Events Module (Proof of Concept)

**Capabilities:** `manage_events`, `create_events`, `rsvp_events`, `view_events`

**Database Tables:**
- `el_events` â€” id, title, description, start_date, end_date, location, max_attendees, created_by, status, created_at
- `el_event_rsvps` â€” id, event_id, user_id, status, rsvp_date

**Shortcodes:**
- `[el_event_list limit="6" layout="cards|list"]` â€” Displays upcoming events as cards or list items
- `[el_event_rsvp event_id="123"]` â€” RSVP toggle button with AJAX

**AJAX Actions:**
- `rsvp_event` â€” Toggle RSVP (creates or cancels), checks capacity
- `create_event` â€” Create new event (requires `create_events` capability)

**What the module class does NOT do:** Create tables, register shortcodes, add settings pages, manage capabilities. All of that is handled by core reading the module.json manifest.

---

## 4. WHAT NEEDS TO BE BUILT

### Phase 2: Registration Module

Build as a module (not core) because different installations need different registration flows:
- Some clients want open self-registration
- Some want invite-only access
- Some want approval-based registration
- Some don't need public registration at all

Expected features:
- Custom registration form with configurable fields
- Role selection during signup (if allowed)
- Program/cohort assignment
- Custom profile fields (organization, district, job title)
- Invite-only and approval-based flows
- Email verification
- Registration shortcodes for frontend pages

### Phase 3: Tutorials Module

A module (not core) that ships **pre-activated by default**. Every installation gets it out of the box, but it can be turned off without breaking anything — no other module depends on it to function. This follows the architecture test: if other code doesn’t need it to work, it’s a module.

Expected features:
- Tutorial content management (video embeds, HTML, Scribe documents, uploaded files)
- Categorization and tagging (by module, by user role, by topic)
- Contextual triggers — “show this tutorial when a user first visits this page”
- Multiple delivery methods: popup/modal, sidebar panel, inline embed
- Completion tracking — “user has seen this, don’t show again”
- Tutorial library page where users can browse and search
- Shortcodes for embedding tutorials and tutorial lists in pages

### Phase 4: Support Agent Module

An AI-powered help system that uses `class-ai-client.php` (core infrastructure) and **declares a dependency on the Tutorials module** in its `module.json`. The agent searches and shares tutorials as part of its responses.

Expected features:
- Chat widget (floating button, opens conversation panel)
- System prompt built dynamically from active modules, site content, and tutorial library
- Answers “how do I...” questions by searching tutorials and site documentation
- Creates support tickets (simple ticket table in its own schema)
- Walks users through troubleshooting steps
- Shares direct links to relevant tutorials
- Escalates to a real person when it can’t resolve the issue

**Important architecture distinction — three AI-powered features, three separate concerns:**

| Feature | Module | Purpose | Knowledge Base |
|---------|--------|---------|----------------|
| **Support Agent** | support-agent | Help users navigate the platform | Tutorials, site docs, FAQs |
| **AI Tutor** | LMS (sub-feature) | Help users learn course content | Course materials, curriculum |
| **AI Client** | Core (infrastructure) | API wrapper used by both | N/A — it’s plumbing |

The Support Agent answers “how do I submit my assignment.” The AI Tutor answers “explain photosynthesis.” They share the same underlying API client but serve fundamentally different purposes with different system prompts and knowledge bases.

### Phase 5: LMS Module

Highest priority revenue-driving module. Courses, lessons, enrollments, progress tracking, completions. The AI Tutor is a sub-feature within the LMS module, not a separate module, because it exists solely to support course learning.

### Phase 6: Remaining Modules

1. **Certificates Module** — PDF generation, completion certificates, badge system
2. **Analytics Module** — Dashboards, reports, data export
3. **Notifications Module** — Email templates, in-app notifications, digest emails

### Phase 7: Theme Foundation (EL Theme)

- Companion block theme
- Reads brand settings from EL Core plugin
- Header/footer template variations
- Block patterns for common page layouts
- Responsive framework
- `theme.json` with EL Core brand integration

### Phase 8: AI Page Generation Pipeline

- Claude generates Gutenberg block markup from natural language descriptions
- Page template library
- MCP integration for direct page creation
- Block pattern library for common educational page layouts


---

## 5. CODING CONVENTIONS

### PHP

- **Namespace-free:** Uses `EL_` prefix for all classes (WordPress convention)
- **Singleton pattern:** All modules use `ModuleName::instance()`
- **Type declarations:** PHP 8.0+ with typed parameters, return types, and nullable types
- **Naming:** Classes are `EL_Module_Name`, files are `class-module-name.php`
- **Functions:** Global helpers prefixed with `el_core_`
- **Sanitization:** All user input sanitized (sanitize_text_field, absint, wp_kses_post, esc_url_raw)
- **Nonce verification:** All form submissions and AJAX requests

### CSS

- All colors use CSS custom properties: `var(--el-primary)`, `var(--el-accent)`, etc.
- Component class prefix: `el-` (e.g., `el-event-card`, `el-btn-primary`)
- Layout class convention: `el-layout-cards`, `el-layout-list`
- Component wrapper: `el-component` class on outermost element

### JavaScript

- Vanilla JS for frontend (no jQuery dependency on frontend)
- jQuery used in admin (WordPress provides it)
- Global `ELCore.ajax()` method for all AJAX calls
- Event delegation on `document` for dynamically rendered components

### Module Convention

Every module follows identical structure:
```
modules/{slug}/
â”œâ”€â”€ module.json
â”œâ”€â”€ class-{slug}-module.php
â”œâ”€â”€ shortcodes/
â”œâ”€â”€ ajax/         (optional, if module needs custom AJAX beyond standard hooks)
â”œâ”€â”€ assets/
â”‚   â”œâ”€â”€ css/
â”‚   â””â”€â”€ js/
```

Module classes contain ONLY business logic. No infrastructure code (no `CREATE TABLE`, no `add_shortcode()`, no settings page rendering).

### Shortcode Convention

- Tag format: `el_{component_name}` (e.g., `el_event_list`, `el_user_profile`)
- Function format: `el_shortcode_{component_name}` (e.g., `el_shortcode_event_list`)
- Each shortcode renders ONE focused component, not an entire page
- All shortcode output uses brand CSS variables
- Shortcodes return HTML strings (never echo)

---

## 6. DEVELOPMENT WORKFLOW

### Environment

- **Hosting:** Rocket.net (managed WordPress hosting, no local dev environment)
- **Local repo:** `C:\Github\EL Core\` — source files and project assets
- **Release ZIPs:** `C:\Github\EL Core\releases\` — packaged plugin versions ready for upload
- **WordPress MCP:** Connected to `...scqz.wpdns.site` — can read/write files directly on server
- **Plugin delivery:** ZIP files uploaded through WordPress admin → Plugins → Add New → Upload Plugin (not FTP, not file editor)
- **Iteration:** Changes made via MCP `wp_fs_write` commands for file edits, or new ZIP uploads for major changes
- **MCP connections:** WordPress site, Filesystem access, and Chrome extension are account-level and persist across projects

### How We Work

1. Fred describes what he needs
2. Claude builds the code (complete files, not snippets)
3. Code is packaged as ZIP or pushed via MCP
4. Fred installs/activates and tests on Rocket.net site
5. Issues reported back, Claude fixes via MCP file edits
6. Repeat until feature is solid

### Important Development Rules

- **Challenge assumptions.** Never agree just to agree. Push back with specific reasons when evidence suggests a better approach. Silence means agreement.
- **Explain the WHY.** Fred is learning architecture while building. Every decision should come with reasoning.
- **No partial code.** Always provide complete, functional files. Fred should never need to fill in blanks.
- **Test-ready output.** Every ZIP or file push should result in something that activates without errors.
- **Module-first thinking.** New features should be modules unless they're truly universal infrastructure.

---

## 7. TECHNICAL ENVIRONMENT

- **PHP:** 8.0+ (required, checked on activation)
- **WordPress:** 6.0+ (required)
- **Database:** MySQL (via WordPress $wpdb)
- **AI Provider:** Anthropic Claude (configurable to OpenAI)
- **Plugin Text Domain:** `el-core`
- **All table names prefixed:** `{wp_prefix}el_` (e.g., `wp_el_events`)
- **All option names prefixed:** `el_core_` or `el_mod_`

---

## 8. REFERENCE: MODULE.JSON SCHEMA

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

## 9. COMPANION DOCUMENTS

- **el-core-architecture-guide.docx** â€” Deep-dive learning document explaining the "why" behind every architecture decision. 10 chapters covering fundamentals through implementation. Dual-purpose: architecture blueprint AND potential course material.
- **el-core-v1.0.0.zip** â€” The current plugin package, ready for WordPress installation.

---

## 10. CURRENT STATUS & NEXT STEPS

**COMPLETED:**
- [x] Core foundation (all 9 infrastructure classes)
- [x] Admin settings pages (Dashboard, Brand, Modules, Roles)
- [x] Events module (database, business logic, shortcodes, AJAX)
- [x] Frontend CSS with brand variable system
- [x] Frontend JS with AJAX helper
- [x] Architecture guide document
- [x] Plugin packaged as installable ZIP

**IN PROGRESS:**
- [ ] Registration module (next to build)

**PLANNED:**
- [ ] Tutorials module (pre-activated by default, content management for help resources)
- [ ] Support Agent module (AI-powered help, depends on Tutorials)
- [ ] LMS module (revenue driver, includes AI Tutor sub-feature)
- [ ] Certificates module
- [ ] Analytics module
- [ ] Notifications module
- [ ] EL Theme (companion block theme)
- [ ] AI page generation pipeline
- [ ] Event admin creation form (so events can be created without SQL)

**KNOWN GAPS:**
- No event creation admin UI yet (events must be created via SQL or AJAX)
- Uninstall.php capability cleanup is conservative (needs improvement)
- No REST API endpoints yet (AJAX only for now)
- WordPress MCP site may need reactivation (had 404 errors previously)
