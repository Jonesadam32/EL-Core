# EL Core — Cursor Build To-Do List

> **This is the single source of truth for all build work.**
> Read this at the start of every session. Work through tasks in order. Check off completed items with [x].
> Push to GitHub after every session so this stays current.
>
> **Last Updated:** February 24, 2026
> **Plugin Version:** v1.27.0 deployed on staging — testing found 4 bugs, fixes pending as v1.27.1
> **Next Build:** v1.27.1 — fix all 4 bugs found in testing (see Phase 6C bugfix section below)
> **Deployed Version:** v1.27.0 on staging
> **Local Repo:** `C:\Github\EL Core` (desktop) — pull from GitHub when switching computers
> **Plugin Source:** `el-core/` folder in repo root
> **Build Script:** `build-zip.ps1` (run from repo root)
> **Deploy:** Upload `el-core-vX.X.X.zip` from `releases/` or `%USERPROFILE%\Downloads` via WordPress Admin → Plugins → Add New → Upload Plugin

---

## SWITCHING COMPUTERS (March 6, 2026)

**When moving to another machine (repo already exists there):**

1. **Pull latest:** `git pull origin main`
2. **Open in Cursor** — open the repo folder as workspace
3. **Build ZIP if needed:** From repo root, run `.\build-zip.ps1` (PowerShell). Creates:
   - `releases/el-core-v1.26.0.zip` — upload to WordPress
   - `old-versions/v1.26.0/` — versioned backup
   - `%USERPROFILE%\Downloads\el-core-v1.26.0.zip` — copy in Downloads
4. **Deploy:** Upload the ZIP via WordPress Admin → Plugins → Add New → Upload Plugin

**Current state:** v1.26.0 built. Definition Consensus Review System UI complete. Ready for deploy and end-to-end test.

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
- [x] **Checkpoint E-2** — after 2F-UX: deploy v1.14.7, Fred tests new client portal UX ✅ PASSED
- [ ] **Checkpoint E-3** — after 2F-B: deploy, Fred tests proposal creation from locked definition
- [ ] **Checkpoint E-4** — after 2F-E (Organizations): deploy, Fred tests creating organizations, linking projects, client profile page
- [x] **Checkpoint F** — after 2G-A: v1.19.2 deployed, Fred tests brand settings and CSS token expansion ✅ PASSED
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

### 2F — Discovery Transcript System ✅ COMPLETE

- [x] Add Transcript tab to `admin/views/project-detail.php`
  - Textarea: paste Fathom summary or any meeting transcript
  - "Process with AI" button
  - Display extracted fields in editable form after processing
  - "Confirm & Lock Definition" button (agency admin confirms before client sees it)
- [x] AJAX handler: `es_process_transcript`
  - Save raw transcript to `discovery_transcript`
  - AI prompt: extract site description, primary goal, target customers, user types, site type, team member names — return JSON
  - Call `el_core_ai_complete()` with prompt
  - Parse response, pre-populate `el_es_project_definition`
  - Set `discovery_extracted_at` timestamp
  - Return structured data to JS
- [x] AJAX handler: `es_save_definition` — save manually edited definition fields
- [x] AJAX handler: `es_lock_definition` — lock definition (DM only on client side, admin on backend)
- [x] Admin JavaScript handlers for transcript processing and form submission
- [x] Bugfixes (v1.12.1-1.12.3): AI wrapper usage, model selection, robust JSON extraction
- [x] **Built v1.12.3 - Phase 2F admin features COMPLETE ✅**

### 2F-Retrofit — Client Portal Enhancement ✅ COMPLETE

- [x] Update `[el_expand_site_portal]` shortcode with professional UX/UI
- [x] Stats grid (4 cards: stage progress, status, deliverables, feedback counts)
- [x] Project description display (from notes field)
- [x] Project definition display (AI-extracted data when locked)
- [x] Stakeholders list with avatars and role badges
- [x] Enhanced timeline in dedicated section with proper styling
- [x] Deliverables as card grid with icons and descriptions
- [x] Feedback as colored cards with dates
- [x] Professional CSS: 300+ lines with animations, hover effects, responsive breakpoints
- [x] Increased portal width from 800px to 1200px
- [x] Mobile responsive (stacks vertically on phone, 2-column grid on tablet)
- [x] **Built v1.13.1 - Client portal retrofit COMPLETE ✅**

### 2F-UX — Client Portal UX Redesign ✅ COMPLETE

- [x] Stage navigation as primary element (interactive wizard/stepper)
- [x] Progressive disclosure (click stage → see only that stage's content)
- [x] Modern Tech color palette: Indigo (#6366F1) + Cyan (#06B6D4)
- [x] All emoji replaced with 14 SVG Feather Icons
- [x] Wizard pattern: completed ✓, current highlighted, upcoming disabled
- [x] URL hash support for bookmarking (#stage-3)
- [x] Modal-based UX: Deliverables, Feedback, Project Definition as clickable info cards → open modals
- [x] Removed redundant stage headers and status badges (info already in stage nav)
- [x] Three info cards on one horizontal line (Deliverables, Feedback, Project Definition)
- [x] Project Team: removed avatars, fixed card sizing/padding
- [x] WCAG AA compliant (4.5:1 contrast ratio)
- [x] Mobile responsive (768px and 480px breakpoints)
- [x] **Built v1.14.0 through v1.14.7 (multiple bugfix iterations)**
- [x] **Deployed v1.14.7 to staging - UX Redesign COMPLETE ✅**

### 2F-B — Proposal / Scope of Service Generation

> **Goal:** Generate professional Scope of Service proposals from locked project definitions.
> This is built INTO Expand Site (not a standalone module) because the proposal format is specific to web design projects.
> Adapted from the existing proposal system in the ELS monolith (`el-solutions.php`).

**Database:**
- [x] Create `el_es_proposals` table via migration (database version 3)

**Admin UI:**
- [x] Add "Proposal" tab to `admin/views/project-detail.php`
- [x] Proposal display in client portal — professional document layout
- [x] Accept/Decline buttons for Decision Maker

**AJAX Handlers:**
- [x] `es_create_proposal`, `es_save_proposal`, `es_generate_proposal_ai`, `es_send_proposal`, `es_delete_proposal`
- [x] `es_accept_proposal` / `es_decline_proposal`
- [x] Accepted proposal locks Stage 3 and advances to Stage 4
- [x] **Built v1.15.0 — Phase 2F-B COMPLETE ✅**

### 2F-C — Proposal Narrative Redesign ✅ COMPLETE

- [x] Database migration: 5 new LONGTEXT columns on `el_es_proposals` (version 4)
- [x] AI prompt rewrite: generates 5 narrative sections as flowing prose
- [x] Admin edit modal: 5 narrative textareas + help text
- [x] Client portal: document-style layout (Georgia serif, letterhead, indigo section headers)
- [x] **Built v1.16.0 — Phase 2F-C COMPLETE ✅**

### 2F-D — Payment Terms & T&C Settings

> **Cursor prompt:** `cursor-prompt-payment-terms-settings.md`
> **Target version:** v1.17.0

- [ ] Add `default_payment_terms` and `default_terms_conditions` to `module.json` settings array
- [ ] Seed default text on activation (only if setting is currently empty — never overwrite existing)
- [ ] Add two textarea fields to Expand Site settings admin page using `EL_Admin_UI::form_row()`
- [ ] Auto-populate `payment_terms` and `terms_conditions` on new proposal creation from settings defaults
- [ ] Add `// TODO: Invoice trigger — Phase 2F-E` comment in `handle_accept_proposal()`
- [ ] Bump to v1.17.0, update CHANGELOG, build ZIP, deploy
- [ ] **Test:** Settings page shows both textareas with default text pre-filled
- [ ] **Test:** New proposal creation auto-populates both fields
- [ ] **Test:** Existing proposals unaffected

### 2F-E — Organizations & Client Management (Core Infrastructure)

> **Cursor prompt:** `cursor-prompt-organizations.md`
> **Target version:** v1.18.0

**Database (core tables — `el_` prefix):**
- [ ] Create `el_organizations` table: id, name, type, status, address, phone, website, created_at, updated_at
- [ ] Create `el_contacts` table: id, organization_id, first_name, last_name, email, phone, title, is_primary, user_id, created_at, updated_at
- [ ] Add `organization_id` BIGINT to `el_es_projects` + migration to create orgs from existing client_name values

**Admin UI:**
- [ ] Register "Clients" submenu under EL Core admin menu
- [ ] Client list: card grid with org name, type/status badges, contact count, project count
- [ ] Client profile page: details card, contacts table, linked projects list
- [ ] Add/Edit/Delete organizations and contacts via AJAX modals
- [ ] Portal access on contacts: auto-create WP user

**Project creation integration:**
- [ ] Organization autocomplete in "Create Project" replaces plain text client name
- [ ] Auto-add primary contact as Decision Maker stakeholder on project creation
- [ ] Project list shows org name linked to client profile

**AJAX Handlers:**
- [ ] `el_create_organization`, `el_update_organization`, `el_delete_organization`, `el_get_organization`
- [ ] `el_add_contact`, `el_update_contact`, `el_delete_contact`, `el_get_contact`
- [ ] `el_search_organizations`

**Testing:**
- [ ] Create org, add contacts with/without portal access, verify WP user creation
- [ ] Create project linked to org, verify project list shows org name
- [ ] Open client profile — contacts and projects listed
- [ ] Existing projects still work via `client_name` fallback

---

### 2G — Branding System (Two Parts)

---

#### 2G-A — Admin Brand Settings + CSS Token Expansion

> **ARCHITECTURE NOTE (Feb 23, 2026):** The admin Settings → Brand page is Fred's tool for ELS's own brand. Simple: logo, colors, fonts, brand voice. AI palette generation and client color selection belong in the Expand Site client portal (Phase 2G-B).
>
> **v1.19.0 was built incorrectly** — it put AI analysis, Pickr color wheels, and palette voting inside the admin page. Fix this in v1.19.1.
>
> **Cursor prompt:** `cursor-prompt-branding-fix.md`
> **Target version:** v1.19.1

**v1.19.0 work that is CORRECT — keep as-is:**
- `class-settings.php`: 9 new brand fields (logo variants, dark mode, brand voice, ai_palette_suggestions, palette_selected) ✅
- `class-asset-loader.php`: `generate_full_token_set()` expanding from 5 to ~25 CSS tokens ✅
- `class-ai-client.php`: `complete_with_image()` and `call_anthropic_vision()` (used later by portal) ✅

**v1.19.0 work that was WRONG — remove in v1.19.1:**
- `settings-brand.php`: remove AI analysis path, Pickr color pickers, palette swatches, semantic preview
- `admin.js`: remove Pickr init, palette rendering, analyze logo handler
- `admin.css`: remove palette card styles
- `class-el-core.php`: remove `el_analyze_logo` and `el_save_brand_selection` AJAX handlers, remove Pickr CDN enqueue

**What the admin brand page SHOULD have after fix:**
- [x] Section 1 — Logo: primary logo, logo variant dark, logo variant light, favicon (media uploader buttons)
- [x] Section 2 — Brand Colors: three plain hex text inputs for primary, secondary, accent — NO color wheel, NO AI
- [x] Section 3 — Typography: heading font select + body font select (existing 8-option lists)
- [x] Section 4 — Brand Voice: tone select, audience text field, values textarea
- [x] Section 5 — Dark Mode: single checkbox
- [x] All sections use `EL_Admin_UI::*` — no raw HTML

**Testing (v1.19.1/v1.19.2):**
- [x] All 5 sections save and reload correctly
- [x] Frontend shows expanded `--el-*` CSS variables in browser inspector
- [x] `--el-primary-dark` is visually darker than `--el-primary`
- [x] Semantic colors present and correct hues
- [x] Expand Site CSS still renders correctly (no broken variables)
- [x] No JS console errors on brand page
- [x] Bump to v1.19.2, update CHANGELOG, build ZIP, upload
- [x] **Checkpoint F: Fred tests brand settings end-to-end ✅ PASSED**

---

#### 2G-B — Stakeholder Review & Decision System (client portal)

> **What this is:** AI logo analysis, palette generation, and stakeholder voting all happen HERE — inside the Expand Site client portal as part of the project workflow. NOT in the admin settings page.
>
> **Full spec:** `cursor-prompt-stakeholder-review-system.md` — read before starting.
> **Target versions:** v1.20.0 (Steps 1–3), v1.21.0 (Steps 4–5)
> **Prerequisite:** v1.19.2 deployed and tested ✅

**Step 1 — Database Schema (v1.20.0)**
- [ ] Add `el_es_review_items` table to module.json (new database version)
- [ ] Add `el_es_review_votes` table
- [ ] Add `el_es_annotations` table (schema only — no UI until Phase 2H)
- [ ] Add `el_es_templates` table
- [ ] Add new capabilities: `es_review_content`, `es_close_review`, `es_manage_templates`
- [ ] Deploy, verify all 4 tables created in database

**Step 2 — Template Library Admin Page (v1.20.0)**
- [ ] Create `modules/expand-site/admin/views/template-library.php`
- [ ] Register "Template Library" submenu under Expand Site in admin
- [ ] Card grid view of templates grouped by style category
- [ ] "Add Template" button → modal: title, category select, description, image URL + media uploader, active toggle
- [ ] Edit / Delete / Active toggle per template card
- [ ] Filter bar: by category, by active/inactive
- [ ] AJAX handlers: `es_save_template`, `es_delete_template`, `es_reorder_templates`
- [ ] **Checkpoint:** Add 6 sample templates across 3 categories — verify display and CRUD ✅

**Step 3 — Mood Board in Client Portal (v1.20.0)**
- [ ] Add "Template Style" section to Branding tab in `[el_expand_site_portal]` shortcode
- [ ] Display active templates grouped by category in card grid
- [ ] Each card: preview image (lightbox on click), category badge, title
- [ ] Vote buttons per card: Liked ♥ / Neutral / Disliked ✕ (AJAX, immediate save, toggle behavior)
- [ ] Progress tracker: "X of Y team members responded" (names only — no votes shown until DM closes)
- [ ] Deadline countdown banner if deadline is set
- [ ] DM: "View Results" button appears after all voted or deadline passed
- [ ] Results view: per-template breakdown (liked/neutral/disliked count + per-stakeholder breakdown)
- [ ] DM: "Confirm Style Direction" → closes review, records selection, shows confirmation banner
- [ ] AJAX handlers: `es_get_mood_board`, `es_save_template_vote`, `es_get_review_status`, `es_get_review_results`, `es_close_review`
- [ ] Notification hooks (no email yet): `el_review_item_created`, `el_review_vote_submitted`, `el_review_closed`
- [ ] **Checkpoint:** Vote as each stakeholder type, verify progress tracker, DM closes review ✅

**Step 4 — Brand Palette Voting + AI Logo Analysis (v1.21.0)**
- [ ] Admin Branding tab on project detail page: "Analyze Logo" button + logo URL input
- [ ] AJAX handler `es_analyze_client_logo`:
  - Admin-triggered; receives logo URL linked to project
  - Calls Claude vision API via `complete_with_image()` in class-ai-client.php
  - Returns 3 palette options (primary, secondary, accent, font_heading, font_body, rationale)
  - Saves JSON to `el_core_brand.ai_palette_suggestions`
- [ ] Admin sees 3 palette previews with "Release to Portal" button
- [ ] Brand Palette section in client portal Branding tab (after mood board)
- [ ] 3 side-by-side cards: color swatches, font names, AI rationale
- [ ] Vote buttons: Prefer / Neutral / Don't Prefer (AJAX, same pattern as mood board)
- [ ] Progress tracker + deadline countdown (same pattern as mood board)
- [ ] DM: "Lock Brand Colors" → saves to `el_core_brand`, locks palette section
- [ ] AJAX handlers: `es_get_palette_votes`, `es_save_palette_vote`
- [ ] **Checkpoint:** Generate AI palettes, verify 3 appear in portal, vote as stakeholders, DM locks ✅

**Step 5 — Admin Review Management (v1.21.0)**
- [ ] Project detail → Branding tab: list existing review items with status badges (Open / Awaiting Results / Closed)
- [ ] "Create Review Session" button: select type (mood_board / brand_palette), set optional deadline
- [ ] "Set/Extend Deadline" control per review item
- [ ] "View Results" per item — full breakdown table
- [ ] AJAX handlers: `es_create_review_item`, `es_set_review_deadline`
- [ ] **Checkpoint:** Create review from admin, set deadline, verify portal shows countdown ✅

---

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
- [ ] New shortcode `[el_client_dashboard]` — shows all projects current user is a stakeholder on
- [ ] New shortcode `[el_workflow_input]` (see 2H)
- [ ] New shortcode `[el_brand_selector]` (see 2G-B)
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
> Note: settings-brand.php is being handled as part of Phase 2G-A.

- [x] Rebuild `admin/views/settings-brand.php` using `EL_Admin_UI::*` components (done in v1.19.1)
- [ ] Rebuild `admin/views/settings-modules.php` using `EL_Admin_UI::*` components
- [ ] Rebuild `admin/views/settings-roles.php` using `EL_Admin_UI::*` components
- [ ] Verify all pages save settings correctly after rebuild
- [ ] Verify brand color changes reflect immediately via CSS variables

---

## PHASE 4 — EVENTS MODULE ADMIN UI

> Goal: Allow events to be created and managed from WordPress admin without SQL.

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

### Testing
- [ ] Activate on expandedlearningsolutions.com
- [ ] Test open registration mode
- [ ] Test approval-based mode
- [ ] Test invite-only mode
- [ ] Test closed mode
- [ ] Test email verification flow
- [ ] Test login blocking for pending/unverified users
- [ ] Test rate limiting
- [ ] Fix any bugs found

### Admin UI
- [ ] Pending registrations list with approve/reject actions (EL_Admin_UI)
- [ ] Invite code management — create, view usage, disable (EL_Admin_UI)
- [ ] User management table — status, verification state, role (EL_Admin_UI)
- [ ] Registration settings page

---

## PHASE 6 — CORE IMPROVEMENTS

- [ ] Improve `uninstall.php` — properly remove all capabilities, options, and tables on plugin deletion
- [ ] Add REST API endpoints for events (GET /el-core/v1/events, POST /el-core/v1/events)
- [ ] Add REST API endpoints for registration status
- [ ] Review and update `el-core-project-brief.md` to reflect current state
- [ ] Review and update `el-core-cursor-handoff.md` to reflect all new modules and patterns

---

## PHASE 6A — INVOICING MODULE

> Replaces QuickBooks as ELS's sole invoicing tool. Full build spec: **`docs/cursor-handoff-invoicing-module.md`** — read before starting.
> **Prerequisite:** v1.22.0 deployed and tested. Table prefix: `el_inv_`. CSS prefix: `el-inv-`.
> Client linking: `el_organizations` + `el_contacts` (core). Project linking: `el_es_projects.id` for now.

### Step 1 — Database + Module Skeleton

- [x] Create `module.json` with tables, capabilities, shortcodes, settings (see handoff)
- [x] Create `class-invoicing-module.php` skeleton (singleton, init_hooks, AJAX registrations)
- [x] Create empty shortcode files (placeholder HTML)
- [ ] **Checkpoint:** Module activates, tables created, admin menu appears

### Step 2 — Product Management

- [x] Build `admin/views/product-list.php` with `EL_Admin_UI::*`
- [x] AJAX: create, update, delete, seed products
- [x] Seed default products (6 from handoff)
- [x] **Checkpoint:** Products page works, seed data created ✅

### Step 3 — Invoice CRUD

- [x] Build `admin/views/invoice-list.php` and `invoice-edit.php` (org autocomplete, line items, calculations)
- [x] AJAX: create, update, delete, duplicate, get invoice
- [x] Auto-increment invoice numbers (ELS-YYYY-NNN)
- [ ] **Checkpoint:** Create invoice, add line items, save, view in list

### Step 4 — Payment Recording

- [x] Payment modal in admin; AJAX: record payment, delete payment
- [x] Auto-update invoice totals and status; overdue detection on page load
- [ ] **Checkpoint:** Record payment, status changes, overdue flagging works

### Step 5 — Send & Client Portal

- [x] Send invoice (mark sent, wp_mail); build `[el_client_invoices]` and `[el_invoice_view]` shortcodes with print styles
- [ ] **Checkpoint:** Send invoice, client sees in portal, print looks professional

### Step 6 — Revenue Dashboard + Export

- [x] Build `[el_revenue_dashboard]` shortcode (charts, breakdowns); CSV export handler
- [ ] **Checkpoint:** Dashboard accurate, CSV exports correctly

---

## PHASE 6C — CLIENT DASHBOARD (v1.27.0) ← NEXT BUILD

> Universal client home base. Lives in EL Core (not a module) so all future modules can plug into it.
> A logged-in client lands here, sees all their org's projects and invoices, and gets a clear CTA for whatever needs their attention.
>
> **Prerequisite:** v1.26.0 deployed and tested.
> **Data model:** el_contacts → organization_id → el_es_projects + el_inv_invoices (all links already exist).
> **No new DB tables needed.**

### Architecture

- New file: `el-core/includes/shortcodes/client-dashboard.php`
- Registered in `el-core/el-core.php` boot sequence (same pattern as Canvas pages)
- Server-side render only (no new AJAX for v1)
- Inline CSS via `wp_head` — no external CSS file

### Phase 6C bugfix checklist — v1.27.1 ← DO THIS FIRST

> Found during testing of v1.27.0. Fix all 4, build v1.27.1, redeploy.

- [x] **Fix 1 — "View Project" opens wrong project**: Portal authorization check at line 74 of `expand-site-portal.php` only calls `is_stakeholder()`, not `is_decision_maker()`. DM designated via `decision_maker_id` fails auth and portal falls back to auto-detect, opening a different project. Fix: add `|| $module->is_decision_maker( $project_id )` to the auth check.
- [x] **Fix 2 — Project Definition consensus UI not rendering**: In the portal, the `pending_review` block outputs a loading div but the JS (`es_get_definition_review` AJAX call) never fires or fails silently. No countdown timer, no fields, no verdict buttons appear. Root cause: JS was reading `data.definition` etc. from the EL_AJAX_Handler::success() envelope without unwrapping `resp.data` first. Fixed in `expand-site.js` `loadReview()`.
- [x] **Fix 3 — "View as" in Clients contact list needs to be real "Log in as"**: Replaced the "View As" link in `client-profile.php` with a proper "Log in as" button using `switch_to_user` nonce URL that calls `wp_set_auth_cookie()`. Admin ID stored in user meta.
- [x] **Fix 4 — "Switch back to admin" button missing from WP toolbar**: Added `add_switch_back_admin_bar_button()` hooked to `admin_bar_menu` and `handle_switch_back_user()` hooked to `admin_init` in `class-expand-site-module.php`. Red button visible on both frontend and backend.
- [x] Build v1.27.1, update CHANGELOG, run `build-zip.ps1`, push to GitHub — **DONE** ✅

---


- [ ] Look up org IDs from `el_contacts` where `user_id = current user`
- [ ] Load all `el_es_projects` where `organization_id IN (orgs)` AND user is in `el_es_stakeholders`
- [ ] Load all `el_inv_invoices` where `organization_id IN (orgs)` AND `status != draft` (only if invoicing module active via `el_core_module_active('invoicing')`)
- [ ] Group invoices by `project_id` so each project card can show its outstanding balance

**Action banner:**
- [ ] Detect pending_review definitions (`el_es_project_definition.review_status = 'pending_review'`)
- [ ] Detect sent proposals (`el_es_proposals.status = 'sent'`)
- [ ] Detect overdue invoices (`el_inv_invoices.status = 'overdue'`)
- [ ] Render amber attention banner at top if any items found: "You have X items that need your attention"

**Project cards:**
- [ ] One card per project the user is a stakeholder on
- [ ] Module type badge (Website Development / Partnership / Training — Coming Soon for inactive modules)
- [ ] Current stage indicator (stage number + stage name for Expand Site projects)
- [ ] Status badge (Active / On Hold / Complete)
- [ ] Outstanding invoice balance for that project (if any, pulled from grouped invoices query)
- [ ] Role badge: Decision Maker vs Contributor
- [ ] Context-aware CTA button:
  - "Review Definition" if `review_status = pending_review`
  - "Review Proposal" if proposal `status = sent`
  - "View Project" otherwise → links to `[el_expand_site_portal]` page with `?project_id=X`

**Invoices section:**
- [ ] Only rendered if invoicing module is active
- [ ] Total outstanding balance callout if balance > $0
- [ ] Table: Invoice #, Project name, Date, Amount, Status badge, Balance Due, View link
- [ ] View link goes to existing `[el_invoice_view]` shortcode page

**Access & edge cases:**
- [ ] Not logged in → login prompt
- [ ] No org linked → "No client account is linked to your user. Contact your project manager."
- [ ] No projects yet → friendly empty state with org name
- [ ] Contributor footer note: "Some actions are only available to the Decision Maker on your team"

**Finish:**
- [ ] Register shortcode in `el-core/el-core.php`
- [ ] Bump to v1.27.0, update CHANGELOG
- [ ] Run `build-zip.ps1`, upload to staging, test with a real client user

### What is NOT in v1.27.0 (deferred)
- Admin "View As" for the dashboard (add in v1.27.1)
- Training module section (no module exists yet)
- Expand Partners portal section (no module yet)
- Notifications / activity feed (Phase 10)

---

## PHASE 6B — EXPAND PARTNERS MODULE

> Proprietary internal module for managing ELS partner relationships end-to-end.
> Pipeline: Application → Discovery → Contract → Onboarding → Site Build → Training → Active Partner
> Revenue tracking: partner logs invoices, system calculates ELS fee, Fred invoices manually.
> Full design spec in `EXPAND-PARTNERS-DESIGN.md` — read before starting.
> Build AFTER Expand Site Phase 2 is fully stable.

### Phase A — Foundation

- [ ] Create `modules/expand-partners/module.json` — capabilities, shortcodes, database declarations
- [ ] Create `el_ep_applications`, `el_ep_partners`, `el_ep_stage_history`, `el_ep_onboarding_checklist`, `el_ep_project_brief`, `el_ep_invoices`, `el_ep_messages` tables
- [ ] Create `modules/expand-partners/class-expand-partners-module.php` — module skeleton
- [ ] Shortcode `[el_partner_apply]` — public application form (no WP account required)
- [ ] Admin view: Pending Applications queue with advance/decline actions
- [ ] AJAX handlers: `ep_submit_application` (nopriv), `ep_advance_application`, `ep_decline_application`
- [ ] **Checkpoint A:** Deploy, test application form, verify pending queue, verify advance to Stage 1

### Phase B — Pipeline Stages 1–3

- [ ] Admin partner list + detail view with tabs: Overview, Stage History, Brief, Contract, Onboarding
- [ ] Stage 1 — Discovery: transcript textarea + "Process with AI", editable brief fields, confirm button
- [ ] AJAX handlers: `ep_process_transcript`, `ep_save_brief`, `ep_confirm_brief`
- [ ] Stage 2 — Contract: status field (unsigned/signed), signed date, notes
- [ ] Stage 3 — Onboarding: checklist items per partner, status tracking
- [ ] AJAX handlers: `ep_mark_contract_signed`, `ep_update_checklist_item`, `ep_advance_stage`
- [ ] **Checkpoint B:** Run through Stages 1–3 with test partner, verify AI extraction and stage history

### Phase C — Stages 4–5 and Active State

- [ ] Stage 4 — Site Build: milestone checklist, partner sign-off button
- [ ] Stage 5 — Training: resource links with completion tracking
- [ ] AJAX handlers: `ep_mark_training_complete`, `ep_partner_sign_off`
- [ ] **Checkpoint C:** Advance test partner through all stages to Active

### Phase D — Revenue Tracking

- [ ] Partner invoice log form: client name, date, amount, revenue type (product / training)
- [ ] Auto-calculate ELS fee based on partner's stored rates
- [ ] AJAX handlers: `ep_log_invoice`, `ep_mark_fee_paid`
- [ ] Partner dashboard totals: total revenue, fee owed, fee paid
- [ ] Admin revenue overview: all partners with outstanding balances, sortable
- [ ] Flag partners with no invoice logged in 60+ days
- [ ] **Checkpoint D:** Log test invoices, verify fee calculations and totals

### Phase E — Messaging

- [ ] Threaded message system between partner and ELS admin
- [ ] AJAX handlers: `ep_send_message`, `ep_mark_read`
- [ ] Unread message count badge on admin partner list
- [ ] **Checkpoint E:** Test message send/receive from both admin and partner portal

### Phase F — Partner Portal Shortcodes

- [ ] Shortcode `[el_partner_portal]` — full partner dashboard with tabs: Overview, Revenue, Project, Resources, Messages, Support
- [ ] Portal adapts tabs based on current stage (Revenue only visible when Active)
- [ ] **Checkpoint F:** Full end-to-end test — application through Active portal

---

## PHASE 7 — TUTORIALS MODULE

> Ships pre-activated by default. No other module depends on it.

- [ ] Define `module.json` — schema, capabilities, shortcodes, settings
- [ ] Database tables: `el_tutorials`, `el_tutorial_categories`, `el_tutorial_completions`
- [ ] Business logic: `class-tutorials-module.php`
- [ ] Admin UI: tutorial list, create/edit form, category management
- [ ] Shortcodes: `[el_tutorial_library]`, `[el_tutorial]`, `[el_tutorial_progress]`
- [ ] Completion tracking, contextual triggers, multiple delivery methods (modal, sidebar, inline)

---

## PHASE 8 — SUPPORT AGENT MODULE

> AI-powered help. Depends on Tutorials module. Uses `class-ai-client.php` from core.

- [ ] Define `module.json` with `"modules": ["tutorials"]` dependency
- [ ] Database tables: `el_support_tickets`
- [ ] Business logic: `class-support-agent-module.php`
- [ ] Chat widget, dynamic system prompt, tutorial search, ticket creation, escalation
- [ ] Admin UI: ticket list, conversation history, escalation management
- [ ] Shortcode: `[el_support_chat]`

---

## PHASE 9 — LMS MODULE

> Highest priority revenue-driving module. AI Tutor is a sub-feature within LMS.

- [ ] Define `module.json` — schema, capabilities, shortcodes, settings
- [ ] Database tables: `el_courses`, `el_lessons`, `el_enrollments`, `el_progress`, `el_completions`
- [ ] Business logic: `class-lms-module.php`
- [ ] Admin UI: course builder, lesson management, enrollment management, progress reports
- [ ] Shortcodes: `[el_course_list]`, `[el_course]`, `[el_lesson]`, `[el_progress]`, `[el_enroll]`
- [ ] AI Tutor sub-feature: chat interface within lesson context
- [ ] Settings: enrollment modes, completion requirements, AI Tutor enable/disable

---

## PHASE 10 — REMAINING MODULES (plan only)

**Certificates Module** — PDF generation, badge system
**Analytics Module** — Dashboards, reports, CSV export
**Notifications Module** — Email templates, in-app feed, digest emails
**EL Theme** — Companion block theme, reads EL Core brand settings, block patterns

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
- CSS class prefix for Invoicing module: `el-inv-`
- Shortcodes return HTML strings — never echo
- Module classes contain business logic only — no CREATE TABLE, no add_shortcode()
- Stage names and pipeline configuration come from settings — never hardcode them
