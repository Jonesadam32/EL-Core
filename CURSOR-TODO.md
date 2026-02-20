# EL Core — Cursor Build To-Do List

> **This is your single source of truth.** Read this at the start of every session.
> Work through tasks in order. Check off completed items with [x].
> Push to GitHub after every session so this stays current.
>
> **Last Updated:** February 20, 2026
> **Plugin Version:** v1.4.0
> **Deployed On:** expandedlearningsolutions.com
> **Local Repo:** `C:\Github\EL Core\`
> **Plugin Source:** `C:\Github\EL Core\el-core\`
> **Build Script:** `C:\Github\EL Core\build-zip.ps1` (run from repo root)
> **Deploy:** Upload `el-core.zip` via WordPress Admin → Plugins → Add New → Upload Plugin

---

## BEFORE YOU START ANY SESSION

1. Read `el-core-cursor-handoff.md` — architecture, conventions, critical lessons
2. Read `el-core-admin-build-rules.md` — admin UI framework rules
3. Check the version in `el-core/el-core.php` so you know what's deployed

---

## PHASE 1 — DEPLOY & VERIFY CURRENT STATE

> Goal: Confirm everything that's been built actually works on the live site.

- [x] Delete `el-core/modules/project-management/` — fully replaced by expand-site
- [x] Run `build-zip.ps1` to produce `el-core.zip`
- [x] Confirm ZIP builds without errors
- [x] Bump version to `1.4.0` in `el-core.php` plugin header AND `EL_CORE_VERSION` constant
- [x] Update `CHANGELOG.md` with v1.4.0 entry
- [ ] Upload ZIP to expandedlearningsolutions.com — WordPress Admin → Plugins → Add New → Upload Plugin
- [ ] Verify plugin activates without PHP errors
- [ ] Verify Expand Site module appears in EL Core → Modules admin page
- [ ] Activate Expand Site module and confirm no errors
- [ ] Verify Expand Site admin menu item appears under EL Core
- [ ] Verify project list view loads
- [ ] Verify project creation works (create a test project)
- [ ] Verify stage advancement works on test project
- [ ] Fix any activation or runtime errors found

---

## PHASE 2 — CANVAS PAGE SYSTEM (Core Infrastructure)

> Goal: Allow AI-generated pages (full HTML/CSS/JS) to be dropped into WordPress without Gutenberg breaking them.
> This is core infrastructure, NOT a module. It lives in `includes/` and `admin/`.

- [ ] Create `includes/class-canvas-page.php` — registers meta boxes and page template
- [ ] Add meta box to page editor: **HTML Content** (raw textarea, no sanitization stripping)
- [ ] Add meta box to page editor: **Custom CSS** (textarea)
- [ ] Add meta box to page editor: **Custom JavaScript** (textarea)
- [ ] Add meta box to page editor: **Canvas Mode toggle** (checkbox — hides header/footer when checked)
- [ ] Create `templates/template-canvas.php` — page template that:
  - Outputs raw HTML content from meta box (bypasses `wpautop` and content filters)
  - Injects custom CSS in `<style>` tags in `<head>`
  - Injects custom JS in `<script>` tags before `</body>`
  - Optionally hides theme header/footer when Canvas Mode is enabled
- [ ] Register the template in WordPress so it appears in Page Attributes → Template dropdown
- [ ] Load `class-canvas-page.php` from `class-el-core.php` boot sequence
- [ ] Test: create a new page, select Canvas template, paste in a full HTML page, verify it renders correctly
- [ ] Test: verify custom CSS and JS execute properly
- [ ] Test: verify Canvas Mode hides header/footer when checked

---

## PHASE 3 — COMPLETE EXPAND SITE MODULE

> Goal: Finish any incomplete parts of the Expand Site module and make it production-ready.

### Admin UI
- [ ] Review `admin/views/project-list.php` — verify it uses `EL_Admin_UI::*` components throughout
- [ ] Review `admin/views/project-detail.php` — verify stage progress bar, all 5 tabs render correctly
- [ ] Review `admin/views/project-form.php` — verify create and edit forms work
- [ ] Add inline deliverable upload (file URL input) to project detail view
- [ ] Add change order pricing field to feedback management tab
- [ ] Verify all AJAX actions work: create project, advance stage, add deliverable, submit feedback, add page

### Client Portal Shortcodes
- [ ] Test `[el_project_portal]` on a frontend page — verify it renders for logged-in clients
- [ ] Test `[el_project_status]` — verify stage progress bar displays correctly
- [ ] Test `[el_page_review]` — verify page list and approval buttons work
- [ ] Test `[el_feedback_form]` — verify feedback submits and appears in admin
- [ ] Fix any rendering or AJAX issues found during testing

### Polish
- [ ] Verify `expand-site.css` uses `el-es-` prefix consistently and brand variables (`var(--el-primary)` etc.)
- [ ] Verify `expand-site.js` uses `ELCore.ajax()` for all AJAX calls
- [ ] Verify CSS class names match across PHP shortcodes, CSS, and JS selectors

---

## PHASE 4 — CORE ADMIN UI FRAMEWORK ROLLOUT

> Goal: Rebuild the existing core admin pages to use `class-admin-ui.php` components.
> Currently Brand, Modules, and Roles pages use older raw HTML patterns.

- [ ] Rebuild `admin/views/settings-brand.php` using `EL_Admin_UI::*` components
- [ ] Rebuild `admin/views/settings-modules.php` using `EL_Admin_UI::*` components
- [ ] Rebuild `admin/views/settings-roles.php` using `EL_Admin_UI::*` components
- [ ] Verify all three pages save settings correctly after rebuild
- [ ] Verify brand color changes reflect immediately via CSS variables

---

## PHASE 5 — EVENTS MODULE ADMIN UI

> Goal: Allow events to be created and managed from the WordPress admin without touching SQL.

- [ ] Create `modules/events/admin/views/event-list.php` — sortable/filterable event table
- [ ] Create `modules/events/admin/views/event-form.php` — create and edit form
- [ ] Register admin submenu page in `class-events-module.php`
- [ ] Add AJAX handler: `es_delete_event`
- [ ] Add event list with attendee count column
- [ ] Add RSVP management view (list of attendees per event, export to CSV)
- [ ] Test: create event via admin, verify it appears in `[el_event_list]` shortcode on frontend
- [ ] Test: RSVP via frontend, verify attendee appears in admin

---

## PHASE 6 — REGISTRATION MODULE TESTING & ADMIN

> Goal: Verify registration module works end-to-end on the live site.

### Testing
- [ ] Activate registration module on expandedlearningsolutions.com
- [ ] Test open registration mode — new user can register and log in immediately
- [ ] Test approval-based mode — new user lands in pending state, admin approves, user can log in
- [ ] Test invite-only mode — registration blocked without valid invite code
- [ ] Test closed mode — registration page shows closed message
- [ ] Test email verification flow — user registers, gets email, clicks link, account activates
- [ ] Test login blocking — pending/unverified users cannot log in
- [ ] Test rate limiting — 5 failed attempts per IP triggers lockout
- [ ] Fix any bugs found

### Admin UI
- [ ] Create pending registrations list with approve/reject actions
- [ ] Create invite code management (create, view usage, disable)
- [ ] Create user management table (status, verification state, role)

---

## PHASE 7 — CORE IMPROVEMENTS

- [ ] Improve `uninstall.php` — properly remove all capabilities, options, and tables on plugin deletion
- [ ] Add REST API endpoints for events (GET /el-core/v1/events, POST /el-core/v1/events)
- [ ] Add REST API endpoints for registration status

---

## PHASE 8 — FUTURE MODULES (Plan Only — Don't Start Until Phase 7 Complete)

These are planned but not started. When ready, each gets its own detailed prompt.

- **Tutorials Module** — content management for help resources, completion tracking, contextual triggers
- **Support Agent Module** — AI chat widget, depends on Tutorials module
- **LMS Module** — courses, lessons, enrollment, progress, AI Tutor sub-feature
- **Certificates Module** — PDF generation, completion badges
- **Analytics Module** — dashboards, reports, data export
- **Notifications Module** — email templates, in-app notifications

---

## DEPLOYMENT RULES (Read Before Every Deploy)

- Run `build-zip.ps1` from repo root — uses .NET ZipFile, NOT Compress-Archive
- ZIP always outputs as `el-core.zip` (no version number in filename)
- Version bump = update BOTH the plugin header in `el-core.php` AND `EL_CORE_VERSION` constant
- Update `CHANGELOG.md` with every version bump
- Upload via WordPress Admin → Plugins → Add New → Upload Plugin
- WordPress MCP is NOT connected — do not use wp_fs_write or any MCP file tools

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
