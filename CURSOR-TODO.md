# EL Core — Cursor Build To-Do List

> **This is the single source of truth for all build work.**
> Read this at the start of every session. Work through tasks in order. Check off completed items with [x].
> Push to GitHub after every session so this stays current.
>
> **Last Updated:** February 22, 2026
> **Plugin Version:** v1.11.2 (tested and working - Phase 2E complete)
> **Deployed Version:** v1.10.7 on qd19d0iehj-staging.wpdns.site
> **Local Repo:** `C:\Github\EL Core\`
> **Plugin Source:** `C:\Github\EL Core\el-core\`
> **Build Script:** `C:\Github\EL Core\build-zip.ps1` (run from repo root)
> **Deploy:** Upload `el-core.zip` via WordPress Admin → Plugins → Add New → Upload Plugin

---

## PRODUCT DESIGN PRINCIPLE — READ THIS FIRST

**ARCHITECTURE CHANGE (Feb 22, 2026):** Expand Site is now proprietary.

Expand Site module is a proprietary internal tool for Expanded Learning Solutions. It is NOT a sellable product. The 8-stage pipeline, AI features, and multi-stakeholder system are hardcoded for ELS workflow. Only operational settings (deadlines, budgets) are configurable.

**Other modules** (Events, Registration, LMS, etc.) should still be built as resale-ready products with configurable settings per the original design principle below:

EL Core modules are products, not custom builds. Every module must be configurable by other agencies and developers, not hardcoded to Fred's workflow. When building:
- Stage names, deadlines, and flow options come from settings — never hardcoded constants
- Feature flags (enable/disable AI features, multi-stakeholder, etc.) live in module settings
- Default values reflect Fred's workflow; other agencies configure their own

---

## BEFORE YOU START ANY SESSION

1. Read `START-HERE-NEXT-SESSION.md`
2. Read `el-core-cursor-handoff.md` — architecture, conventions, critical lessons
3. Read `el-core-admin-build-rules.md` — admin UI framework rules
4. Check the version in `el-core/el-core.php` so you know what's deployed

---

## PHASE 0 — CRITICAL BUG FIX (DO THIS FIRST)

> **Blocker.** Admin menu items for modules (Expand Site, Events) load the front-end site instead of the admin page. Nothing can be tested until this is fixed.

- [ ] Open `modules/expand-site/class-expand-site-module.php` → check `register_admin_pages()` method
- [ ] Verify `add_submenu_page()` is hooked to `admin_menu` in `init_hooks()` at priority 20
- [ ] Verify the callback `render_admin_page()` is accessible (public method, correct class reference)
- [ ] Check if `admin/views/project-list.php` has any PHP fatal errors preventing render
- [ ] Check if `el_es_projects` database table was created — if not, tables didn't create on activation
- [ ] Compare the broken module registration pattern against working core pages (Dashboard, Brand, Modules, Roles)
- [ ] Apply same fix to Events module if it has the same problem
- [ ] Build ZIP, deploy, verify both admin pages load correctly

---

## PHASE 1 — CANVAS PAGE SYSTEM (Core Infrastructure) ✅ COMPLETE

> Goal: Allow AI-generated pages (full HTML/CSS/JS) to be dropped into WordPress without Gutenberg breaking them.
> This is core infrastructure — lives in `includes/` and `admin/`. NOT a module.
> Required before any client-facing pages can be built for Expand Site.

**Why this exists:** Gutenberg's block editor mangles raw HTML, strips JavaScript, and conflicts with AI-generated page code. The Canvas system bypasses WordPress's content parsing entirely via a custom page template + meta boxes.

**Two types of pages in Expand Site:**
- Agency-built pages (wire frames, mockups, AI-generated site pages) → Canvas mode
- Client interaction pages (portal, content review, workflow input) → shortcodes on normal pages

- [x] Create `includes/class-canvas-page.php` — registers meta boxes and page template
- [x] Add meta box to page editor: **HTML Content** (raw textarea — no sanitization stripping)
- [x] Add meta box to page editor: **Custom CSS** (textarea)
- [x] Add meta box to page editor: **Canvas Mode** toggle (checkbox — hides header/footer when checked)
- [x] Create `templates/template-canvas.php` — page template that:
  - Outputs raw HTML content from meta box (bypasses `wpautop` and all content filters)
  - Injects custom CSS in `<style>` tags
  - Optionally hides theme header/footer when Canvas Mode is enabled
- [x] Register template so it appears in Page Attributes → Template dropdown
- [x] Load `class-canvas-page.php` from `class-el-core.php` boot sequence
- [x] Test: create a new page, select Canvas template, paste full HTML page, verify it renders correctly
- [x] Test: verify Canvas Mode hides header/footer when checked
- [x] Bump version, update CHANGELOG, deploy

**Note:** Gutenberg disabled for all pages (switched to Classic Editor). Rocket.net WAF may block complex HTML with JavaScript - contact support to whitelist if needed.

---

## PHASE 2 — EXPAND SITE MODULE REDESIGN

> **Major redesign.** The current module has the delivery pipeline (stages, deliverables, feedback, pages).
> That foundation is reused. Everything new is additive — new tables, new tabs, new AJAX handlers.
> Read the full redesign spec in `el-core-cursor-handoff.md` before starting.

### DEPLOY CHECKPOINTS FOR PHASE 2

Do not skip these. Build the sub-phase, deploy, wait for Fred to confirm it works, then continue.

- [x] **Checkpoint A** — after 2A: deploy v1.8.0, verify tables created, verify existing projects didn't break (MERGED INTO CHECKPOINT B)
- [x] **Checkpoint B** — after 2B + 2C: deploy v1.9.4, verify module activates, settings page loads, project creation works ✅ PASSED
- [ ] **Checkpoint C** — after 2D: deploy, Fred tests adding/removing stakeholders on a project
- [ ] **Checkpoint D** — after 2E: deploy, Fred tests setting a deadline and the flagging system
- [ ] **Checkpoint E** — after 2F: deploy, Fred tests pasting a transcript and reviewing AI-extracted data
- [ ] **Checkpoint F** — after 2G: deploy, Fred tests mood board upload and AI color option generation
- [ ] **Checkpoint G** — after 2H + 2I: deploy, Fred tests full client workflow input and content review flow
- [ ] **Checkpoint H** — after 2J + 2K: final deploy, Fred does full end-to-end test of all client-facing pages

---

### 2A — Database Schema (do first — everything depends on it)

- [x] Add columns to `el_es_projects` via migration:
  - `decision_maker_id` BIGINT
  - `deadline` DATETIME NULL
  - `deadline_stage` TINYINT
  - `flagged_at` DATETIME NULL
  - `flag_reason` VARCHAR(255)
  - `project_type` VARCHAR(50)
  - `project_goal` TEXT
  - `discovery_transcript` LONGTEXT
  - `discovery_extracted_at` DATETIME NULL
- [x] Create `el_es_stakeholders` table: id, project_id, user_id, role, added_at
- [x] Create `el_es_project_definition` table: id, project_id, site_description, primary_goal, secondary_goals, target_customers, user_types (JSON), site_type, locked_at, locked_by
- [x] Create `el_es_brand_options` table: id, project_id, has_existing_brand, mood_board_url, ai_options (JSON), selected_option, custom_primary, custom_secondary, custom_accent, font_heading, font_body, brand_locked_at
- [x] Add columns to `el_es_pages`: ai_draft_content LONGTEXT, client_review_status VARCHAR(20), content_blocks LONGTEXT (JSON)
- [x] Create `el_es_user_workflows` table: id, project_id, user_type, submitted_by, description, is_initial TINYINT(1), locked_at, locked_by, created_at
- [x] Create `el_es_deadlines` table: id, project_id, stage, deadline DATETIME, set_by, extended_at, met TINYINT(1), created_at
- [x] Bump `module.json` database version to 2 with proper migrations for all of the above

### 2B — Capabilities

- [x] Add `es_decision_maker` capability to `module.json` — client role with lock/approve authority
- [x] Add `es_contributor` capability to `module.json` — client role with input-only access
- [x] Update default role mappings in `module.json`
- [x] Update all permission checks in AJAX handlers — DM actions require `es_decision_maker`, input actions require `es_contributor` OR `manage_expand_site`
- [x] Added permission helper methods to module class: `is_decision_maker()`, `is_stakeholder()`, `can_contribute()`
- [x] Updated all 3 client-facing shortcodes to use new stakeholder-based permissions (supports both legacy single-client and new multi-stakeholder models)

### 2C — Module Settings (configurability for resale) ✅ COMPLETE — THEN REVERSED

**Note:** This sub-phase was completed in v1.9.4, then partially reversed in v1.11.0 per architecture decision.

- [x] Added to `module.json` settings (v1.9.4):
  - ~~`stage_1_name` through `stage_8_name`~~ — **REMOVED in v1.11.0** (hardcoded now)
  - `default_stage_deadline_days` (kept)
  - `deadline_warning_days` (kept)
  - ~~`enable_ai_content_generation`~~ — **REMOVED in v1.11.0** (always enabled)
  - ~~`enable_branding_ai`~~ — **REMOVED in v1.11.0** (always enabled)
  - ~~`enable_multi_stakeholder`~~ — **REMOVED in v1.11.0** (always enabled)
  - ~~`agency_name`~~ — **REMOVED in v1.11.0** (not needed)
- [x] ~~Updated `STAGES` constant to pull names from settings~~ — **REVERTED in v1.11.0**
- [x] `get_stages()` now returns hardcoded STAGES constant (v1.11.0)
- [x] Settings page simplified to operational settings only (v1.11.0)

**Architecture Decision:** Expand Site is proprietary — see ARCHITECTURE-DECISIONS-FEB-22-2026.md

### 2D — Multi-Stakeholder System

- [x] Add Stakeholders tab to `admin/views/project-detail.php`
  - List current stakeholders with role badges (Decision Maker / Contributor)
  - Add Stakeholder modal: search existing WP users OR create new WP user account
  - Enforce one Decision Maker per project
  - Remove stakeholder button
- [x] AJAX handler: `es_add_stakeholder`
- [x] AJAX handler: `es_remove_stakeholder`
- [x] AJAX handler: `es_change_stakeholder_role`
- [x] AJAX handler: `es_search_users` (user autocomplete)
- [x] Admin JavaScript: stakeholder form handlers, user search with debouncing
- [x] Update `[el_expand_site_portal]` shortcode to detect DM vs Contributor and show appropriate controls
- [x] Added `get_stakeholders()` query method
- [x] Added stakeholder action methods: `add_stakeholder()`, `remove_stakeholder()`, `change_stakeholder_role()`
- [x] Built v1.10.0 with comprehensive CHANGELOG
- [x] UX improvements through v1.10.1-v1.10.3 (disabled states, first/last name, search fix)
- [x] Users column added to project list (v1.10.4)
- [x] Shortcode renamed for clarity (v1.10.5)
- [x] User login fixed + "Login As" feature (v1.10.6)
- [x] Project deletion feature (v1.10.7)
- [x] **Deployed v1.10.7 to staging - Phase 2D COMPLETE ✅**
- [ ] **Complete Checkpoint C testing with fresh data**

### 2E — Timer and Escalation System ✅ COMPLETE

- [x] Add deadline date picker to "Advance Stage" modal — saves to `el_es_deadlines`
- [x] Add deadline display to project list (warning badge if deadline within `deadline_warning_days`)
- [x] Add deadline display to project detail header and client portal
- [x] On project list load: check for expired deadlines, auto-set `flagged_at` and `flag_reason`
- [x] Show "HELD UP" badge prominently on flagged projects in list view
- [x] Add "Projects Needing Attention" section to top of project list for flagged/overdue projects
- [x] AJAX handler: `es_extend_deadline`
- [x] AJAX handler: `es_clear_flag`
- [x] AJAX handler: `es_set_deadline`
- [x] Stage-specific deadline defaults added (STAGE_DEADLINE_DAYS constant)
- [x] Removed `default_stage_deadline_days` setting (per architecture decision)
- [x] **Built v1.11.1 - Phase 2E COMPLETE ✅**

### 2F — Discovery Transcript System

- [ ] Add Transcript tab to `admin/views/project-detail.php`
  - Textarea: paste Fathom summary or any meeting transcript
  - "Process with AI" button
  - Display extracted fields in editable form after processing
  - "Confirm & Lock Definition" button (agency admin confirms before client sees it)
- [ ] AJAX handler: `es_process_transcript`
  - Save raw transcript to `discovery_transcript`
  - AI prompt: extract site description, primary goal, target customers, user types, site type, team member names — return JSON
  - Call `el_core_ai_complete()` with prompt
  - Parse response, pre-populate `el_es_project_definition`
  - Set `discovery_extracted_at` timestamp
  - Return structured data to JS
- [ ] AJAX handler: `es_save_definition` — save manually edited definition fields
- [ ] AJAX handler: `es_lock_definition` — lock definition (DM only on client side, admin on backend)

### 2G — Branding Workflow

- [ ] Add Branding tab to `admin/views/project-detail.php`
  - Radio: "Client has existing brand" / "We need to create branding"
  - If existing: fields for logo URL, color hex codes, font names
  - If creating: mood board image upload, "Generate Brand Options" button
- [ ] AJAX handler: `es_generate_brand_options`
  - Receive mood board image URL
  - AI prompt: suggest 3 color palette options, each with primary/secondary/accent hex codes, heading font, body font, 1-sentence rationale — return JSON
  - Save to `el_es_brand_options.ai_options`
  - Return options to JS
- [ ] Admin view: display 3 color swatch options with font names and rationale
- [ ] AJAX handler: `es_select_brand_option` — agency selects which option to present to client
- [ ] AJAX handler: `es_lock_brand` — Decision Maker locks their selection
- [ ] New shortcode `[el_brand_selector]` — client-facing brand option selection, DM can select and lock

### 2H — User Workflow Definition

- [ ] Add User Workflows tab to `admin/views/project-detail.php`
  - List of user types from project definition
  - Status per user type: no input / draft / locked
  - View all iterations submitted
- [ ] Client portal workflow input:
  - First Contributor for a user type sees blank textarea
  - Subsequent contributors see initial description and can add refinements
  - Each submission saved to `el_es_user_workflows`
  - DM sees "Lock this workflow" button per user type
- [ ] AJAX handler: `es_submit_workflow`
- [ ] AJAX handler: `es_lock_workflow` (DM only)
- [ ] New shortcode `[el_workflow_input]` — standalone workflow input interface for clients

### 2I — AI Content Generation per Page

- [ ] "Generate Content" button per page in admin and client portal
- [ ] AJAX handler: `es_generate_page_content`
  - Pull locked project definition, brand, user workflows
  - AI prompt: generate content blocks for [page name] based on project context — return JSON array of blocks (label, suggested_content)
  - Save to `el_es_pages.ai_draft_content` and `content_blocks`
- [ ] Client portal: block-by-block review interface
  - Each block shows AI draft with Edit / Accept buttons
  - Edit: inline textarea replaces draft, saves client version
  - Accept: marks block as accepted
  - Progress indicator: X of Y blocks reviewed
  - "Approve Page" button appears when all blocks are accepted or edited
- [ ] New shortcode `[el_content_review]` — standalone block-by-block content review interface

### 2J — Shortcode Updates

- [ ] `[el_project_portal]` — update for DM vs Contributor roles, show deadline, show flag notice
- [ ] New shortcode `[el_client_dashboard]` — shows all projects current user is a stakeholder on, each as a card with stage/deadline/status, links to portal
- [ ] New shortcode `[el_workflow_input]` (see 2H)
- [ ] New shortcode `[el_brand_selector]` (see 2G)
- [ ] New shortcode `[el_content_review]` (see 2I)
- [ ] Register all new shortcodes in `module.json`

### 2K — Admin View Cleanup

- [ ] Project list: add flagged/overdue indicators, deadline column, stakeholder count column
- [ ] Project detail: restructure tabs to include Stakeholders, Transcript/Definition, Branding, User Workflows
- [ ] Verify all existing tabs (Overview, Stage History, Deliverables, Pages, Feedback) still work after schema changes

---

## PHASE 3 — CORE ADMIN UI FRAMEWORK ROLLOUT

> Goal: Rebuild existing core admin pages to use `class-admin-ui.php` components.
> Currently Brand, Modules, and Roles use older raw HTML patterns.

- [ ] Rebuild `admin/views/settings-brand.php` using `EL_Admin_UI::*` components
- [ ] Rebuild `admin/views/settings-modules.php` using `EL_Admin_UI::*` components
- [ ] Rebuild `admin/views/settings-roles.php` using `EL_Admin_UI::*` components
- [ ] Verify all three pages save settings correctly after rebuild
- [ ] Verify brand color changes reflect immediately via CSS variables

---

## PHASE 4 — EVENTS MODULE ADMIN UI

> Goal: Allow events to be created and managed from WordPress admin without SQL.
> Also: make this module resale-ready — clean, configurable, well-documented.

- [ ] Create `modules/events/admin/views/event-list.php` — sortable/filterable event table using EL_Admin_UI
- [ ] Create `modules/events/admin/views/event-form.php` — create and edit form using EL_Admin_UI
- [ ] Register admin submenu page in `class-events-module.php`
- [ ] Add AJAX handler: `delete_event`
- [ ] Add attendee count column to event list
- [ ] Add RSVP management view — list of attendees per event, with export to CSV option
- [ ] Add module settings: default RSVP behavior, event display defaults
- [ ] Test: create event via admin, verify it appears in `[el_event_list]` on frontend
- [ ] Test: RSVP via frontend, verify attendee appears in admin

---

## PHASE 5 — REGISTRATION MODULE TESTING AND ADMIN UI

> Goal: Verify registration module works end-to-end. Build admin UI for managing registrations.
> Also: make resale-ready — configurable flows, clean admin.

### Testing
- [ ] Activate on expandedlearningsolutions.com
- [ ] Test open registration mode — user registers and logs in immediately
- [ ] Test approval-based mode — user lands in pending, admin approves, user can log in
- [ ] Test invite-only mode — blocked without valid invite code
- [ ] Test closed mode — registration page shows closed message
- [ ] Test email verification flow — register, receive email, click link, account activates
- [ ] Test login blocking — pending/unverified users cannot log in
- [ ] Test rate limiting — 5 failed attempts triggers lockout
- [ ] Fix any bugs found

### Admin UI
- [ ] Pending registrations list with approve/reject actions (EL_Admin_UI)
- [ ] Invite code management — create, view usage, disable (EL_Admin_UI)
- [ ] User management table — status, verification state, role (EL_Admin_UI)
- [ ] Registration settings page (registration mode, email verification toggle, allowed roles, custom fields)

---

## PHASE 6 — CORE IMPROVEMENTS

- [ ] Improve `uninstall.php` — properly remove all capabilities, options, and tables on plugin deletion
- [ ] Add REST API endpoints for events (GET /el-core/v1/events, POST /el-core/v1/events)
- [ ] Add REST API endpoints for registration status
- [ ] Review and update `el-core-project-brief.md` to reflect current state
- [ ] Review and update `el-core-cursor-handoff.md` to reflect all new modules and patterns

---

## PHASE 6B — EXPAND PARTNERS MODULE (new)

> Proprietary internal module for managing ELS partner relationships end-to-end.
> Pipeline: Application → Discovery → Contract → Onboarding → Site Build → Training → Active Partner
> Revenue tracking: partner logs invoices, system calculates ELS fee, Fred invoices manually.
> Full design spec in `EXPAND-PARTNERS-DESIGN.md` — read before starting.
> Build AFTER Expand Site Phase 2 is fully stable.

### Phase A — Foundation

- [ ] Create `modules/expand-partners/module.json` — capabilities, shortcodes, database declarations
- [ ] Create `el_ep_applications` table — application form submissions pre-pipeline
- [ ] Create `el_ep_partners` table — partner records with stage, rates, status
- [ ] Create `el_ep_stage_history` table
- [ ] Create `el_ep_onboarding_checklist` table
- [ ] Create `el_ep_project_brief` table
- [ ] Create `el_ep_invoices` table
- [ ] Create `el_ep_messages` table
- [ ] Create `modules/expand-partners/class-expand-partners-module.php` — module skeleton
- [ ] Shortcode `[el_partner_apply]` — public application form (no WP account required)
- [ ] Admin view: Pending Applications queue with advance/decline actions
- [ ] AJAX handler: `ep_submit_application` (nopriv — guests can apply)
- [ ] AJAX handler: `ep_advance_application` — converts application to partner record, enters Stage 1
- [ ] AJAX handler: `ep_decline_application`
- [ ] **Checkpoint A:** Deploy, test application form submission, verify pending queue, verify advance to Stage 1 creates partner record

### Phase B — Pipeline Stages 1–3

- [ ] Admin partner list view — name, project, stage badge, status, balance owed
- [ ] Admin partner detail view with tabs: Overview, Stage History, Brief, Contract, Onboarding
- [ ] Stage 1 — Discovery: transcript textarea + "Process with AI" button, editable brief fields, confirm button
- [ ] AJAX handler: `ep_process_transcript` — AI extracts Project Brief fields from pasted transcript
- [ ] AJAX handler: `ep_save_brief` — save edited brief fields
- [ ] AJAX handler: `ep_confirm_brief` — lock brief and mark Stage 1 complete
- [ ] Stage 2 — Contract: contract status field (unsigned / signed), signed date, notes
- [ ] AJAX handler: `ep_mark_contract_signed`
- [ ] Stage 3 — Onboarding: checklist items per partner, status tracking
- [ ] AJAX handler: `ep_update_checklist_item` — mark item submitted or confirmed
- [ ] Advance stage button on admin detail view (with notes field)
- [ ] AJAX handler: `ep_advance_stage`
- [ ] **Checkpoint B:** Deploy, run through Stages 1–3 manually with test partner, verify AI transcript extraction works, verify stage history records correctly

### Phase C — Stages 4–5 and Active State

- [ ] Stage 4 — Site Build: milestone list (simple checklist, not full Expand Site pipeline), partner sign-off button
- [ ] Stage 5 — Training: resource links with completion tracking per partner
- [ ] AJAX handler: `ep_mark_training_complete`
- [ ] AJAX handler: `ep_partner_sign_off` — partner confirms stage complete
- [ ] Active Partner status badge on admin list and detail view
- [ ] **Checkpoint C:** Deploy, advance a test partner through all stages to Active, verify status updates correctly

### Phase D — Revenue Tracking

- [ ] Partner invoice log form: client name, date, amount, revenue type (product / training)
- [ ] Auto-calculate ELS fee on save based on partner's stored rates
- [ ] AJAX handler: `ep_log_invoice`
- [ ] AJAX handler: `ep_mark_fee_paid` — admin marks ELS fee as received
- [ ] Partner dashboard totals: total revenue, fee owed, fee paid
- [ ] Admin revenue overview: all partners with outstanding balances, sortable
- [ ] Flag partners with no invoice logged in 60+ days
- [ ] **Checkpoint D:** Deploy, log test invoices of both types, verify fee calculations, verify totals update correctly

### Phase E — Messaging

- [ ] Threaded message system between partner and ELS admin
- [ ] AJAX handler: `ep_send_message`
- [ ] AJAX handler: `ep_mark_read`
- [ ] Unread message count badge on admin partner list
- [ ] **Checkpoint E:** Deploy, test message send/receive from both admin and partner portal

### Phase F — Partner Portal Shortcodes

- [ ] Shortcode `[el_partner_portal]` — full partner dashboard with tabs:
  - Overview (stage progress, action items)
  - Revenue (invoice log, log new invoice form, totals) — Active partners only
  - Project (build milestones, content review links) — Stages 4–5
  - Resources (training materials)
  - Messages (threaded with ELS)
  - Support (link to help desk when Support Agent module exists)
- [ ] Portal adapts tabs and content based on current stage
- [ ] Partner cannot see Revenue tab until Active
- [ ] Register both shortcodes in `module.json`
- [ ] **Checkpoint F:** Full end-to-end test — application through Active portal

---

## PHASE 7 — TUTORIALS MODULE (new)

> A module that ships pre-activated by default. Every installation gets it, but it can be turned off.
> No other module depends on it to function — it is self-contained.

- [ ] Define `module.json` — schema, capabilities, shortcodes, settings
- [ ] Database tables: `el_tutorials`, `el_tutorial_categories`, `el_tutorial_completions`
- [ ] Business logic: `class-tutorials-module.php`
- [ ] Admin UI: tutorial list, create/edit form, category management
- [ ] Shortcodes: `[el_tutorial_library]`, `[el_tutorial]`, `[el_tutorial_progress]`
- [ ] Completion tracking: mark as seen, don't show again
- [ ] Contextual triggers: show tutorial when user first visits a specific page
- [ ] Multiple delivery methods: modal, sidebar panel, inline embed
- [ ] Settings: enable/disable per delivery method, auto-show behavior

---

## PHASE 8 — SUPPORT AGENT MODULE (new)

> AI-powered help system. Depends on Tutorials module (declared in module.json requires).
> Uses `class-ai-client.php` from core.

- [ ] Define `module.json` with `"modules": ["tutorials"]` dependency
- [ ] Database tables: `el_support_tickets`
- [ ] Business logic: `class-support-agent-module.php`
- [ ] Chat widget: floating button, opens conversation panel
- [ ] System prompt built dynamically from active modules + tutorial library
- [ ] Searches tutorials to answer "how do I..." questions
- [ ] Creates support tickets when issue not resolved
- [ ] Escalation: flags for human follow-up
- [ ] Admin UI: ticket list, conversation history, escalation management
- [ ] Shortcode: `[el_support_chat]`

---

## PHASE 9 — LMS MODULE (new)

> Highest priority revenue-driving module. Build after Tutorials and Support Agent are stable.
> AI Tutor is a sub-feature within LMS — not a separate module.

- [ ] Define `module.json` — schema, capabilities, shortcodes, settings
- [ ] Database tables: `el_courses`, `el_lessons`, `el_enrollments`, `el_progress`, `el_completions`
- [ ] Business logic: `class-lms-module.php`
- [ ] Admin UI: course builder, lesson management, enrollment management, progress reports
- [ ] Shortcodes: `[el_course_list]`, `[el_course]`, `[el_lesson]`, `[el_progress]`, `[el_enroll]`
- [ ] AI Tutor sub-feature: chat interface within lesson context, answers course content questions
- [ ] Progress tracking and completion certificates (hooks into Certificates module when built)
- [ ] Settings: enrollment modes, completion requirements, AI Tutor enable/disable

---

## PHASE 10 — REMAINING MODULES (plan only, build after Phase 9)

**Certificates Module**
- PDF certificate generation on course/event completion
- Badge system
- Shortcode: `[el_certificate]`, `[el_badge_wall]`

**Analytics Module**
- Dashboards for course progress, event attendance, registration trends
- Data export (CSV)
- Shortcodes: `[el_analytics_dashboard]`

**Notifications Module**
- Email templates with brand variable support
- In-app notification feed
- Digest emails
- Hooks other modules use to trigger notifications

**EL Theme**
- Companion block theme
- Reads brand settings from EL Core via `el_core_get_brand_colors()` etc.
- Header/footer template variations
- Block patterns for common educational page layouts
- `theme.json` with EL Core brand integration

---

## DEPLOYMENT RULES (Read Before Every Deploy)

- Run `build-zip.ps1` from repo root — uses .NET ZipFile, NOT Compress-Archive
- ZIP always outputs as `el-core.zip` (no version number in filename)
- Version bump = update BOTH plugin header in `el-core.php` AND `EL_CORE_VERSION` constant
- Update `CHANGELOG.md` with every version bump
- Upload via WordPress Admin → Plugins → Add New → Upload Plugin
- WordPress MCP is NOT connected — do not use `wp_fs_write` or any MCP file tools

---

## CODING RULES (Quick Reference)

- All admin views use `EL_Admin_UI::*` — never raw HTML tables or forms
- Shortcode function names: tag `el_my_shortcode` → function `el_shortcode_my_shortcode`
- Module settings accessed as: `$this->core->settings->get('mod_{slug}', 'key', 'default')`
- Guest AJAX needs both hooks: `el_core_ajax_{action}` AND `el_core_ajax_nopriv_{action}`
- CSS class prefix for shared components: `el-`
- CSS class prefix for Expand Site: `el-es-`
- Shortcodes return HTML strings — never echo
- Module classes contain business logic only — no CREATE TABLE, no add_shortcode()
- Stage names and pipeline configuration come from settings — never hardcode them
