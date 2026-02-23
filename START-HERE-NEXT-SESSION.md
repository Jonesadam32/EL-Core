# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** February 22, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** 1.13.1 (Phase 2F complete + Client Portal Retrofit)

---

## ⚠️ ARCHITECTURE CHANGES — READ BEFORE ANYTHING ELSE

**`ARCHITECTURE-DECISIONS-FEB-22-2026.md`** (repo root) contains major architectural decisions made February 22, 2026 that affect how all future modules are built. Read it before starting any session. Key changes:

- Expand Site is now proprietary — strip configurability settings (stage names, feature toggles)
- PM module is a task aggregator only — owns shared `el_tasks` table, not a project system
- CRM module cancelled — use Fluent CRM instead
- Client Portals module cancelled — each program owns its portals
- Public Website module cancelled — replaced by EL Theme and EL Learning Theme (monorepo)
- Shared `el_projects` table architecture planned for cross-program project tracking

---

## THE MASTER CHECKLIST

**`CURSOR-TODO.md`** (repo root) is the single source of truth for all build work.
- Check off items with `[x]` as you complete them
- Never start a new phase until the current phase is fully checked off and tested
- If Fred asks "what's the list" or "where are we" — that file is the answer
- Update it at the end of every session

---

## CURRENT STATE

### Deployed
- **EL Core v1.10.7** on staging site (qd19d0iehj-staging.wpdns.site)

### Built and Ready to Deploy
- **EL Core v1.13.1** - Phase 2F COMPLETE + Client Portal Retrofit
  - Located: `C:\Github\EL Core\releases\el-core-v1.13.1.zip`
  - Backup: `C:\Github\EL Core\old-versions\v1.13.1\el-core-v1.13.1.zip`

### What Was Done This Session (February 22, 2026)

**Phase 2F - Discovery Transcript System + Client Portal Retrofit - COMPLETE ✅**

**Version Progression:**
- v1.12.0: Phase 2F admin features (transcript processing)
- v1.12.1-v1.12.3: Bugfixes (AI wrapper, model selection, JSON extraction)
- v1.13.0: Client portal retrofit (stats, definition, stakeholders, timeline)
- v1.13.1: Fixed missing sections, increased portal width to 1200px

**Admin Features (Phase 2F):**
- Discovery tab on project detail page with AI-powered transcript processing
- Paste Fathom meeting summaries or discovery call notes
- "Process with AI" button extracts project requirements automatically
- AI extracts: site description, primary/secondary goals, target customers, user types, site type
- Editable definition form displays extracted data for manual refinement
- "Save Definition" and "Confirm & Lock Definition" buttons
- Locked state UI shows who locked and when

**Client Portal Retrofit:**
- **Stats grid** - 4-card overview (stage, status, deliverables, feedback)
- **Project description** - Shows project notes if present
- **Project definition** - Shows AI-extracted definition when locked
- **Stakeholders list** - Team members with avatars and role badges
- **Enhanced timeline** - 8-stage progress bar in dedicated section
- **Professional UX/UI** - Card-based layout, hover effects, responsive design, 1200px width
- Icons for visual recognition (📍📄💬👥📋🚀)
- Mobile responsive with proper stacking

**Technical Implementation:**
- AJAX handlers: `es_process_transcript`, `es_save_definition`, `es_lock_definition`
- `get_project_definition()` query method
- `extract_json_from_ai_response()` helper for robust JSON parsing
- 300+ lines of professional CSS with animations and responsive breakpoints
- Uses Claude API for transcript processing

**Bugfixes Along the Way:**
- Fixed AI wrapper function usage (returns array, not string)
- Added AI configuration check before processing
- Removed hardcoded GPT-4 model (respects user's Claude/OpenAI choice)
- Robust JSON extraction from markdown code blocks and text wrappers

**Previous Session - Phase 2E (Timer and Escalation System) ✅**
- Added missing JavaScript handler for "Advance Stage" form
- Form was submitting as HTML instead of AJAX (causing blank page)
- Now properly advances stages via AJAX and reloads page
- Fred confirmed working on staging

**Phase 2E Features:**
- Deadline date picker in "Advance Stage" modal with smart defaults per stage
- Stage-specific deadline constants (Qualification: 3d, Discovery: 7d, Build: 14d, etc.)
- Deadline column on project list with warning badges
- Auto-flagging for expired deadlines
- "Projects Needing Attention" section (flagged + deadline warnings)
- "HELD UP" badge for flagged projects (red)
- "Xd OVERDUE" badge for expired deadlines (red)
- "Xd left" badge for approaching deadlines (yellow)
- Three new AJAX handlers: `es_set_deadline`, `es_extend_deadline`, `es_clear_flag`
- Deadlines tracked in both `el_es_projects` and `el_es_deadlines` tables
- Removed `default_stage_deadline_days` setting (replaced with per-stage defaults)

**Version Progression: v1.10.7 → v1.11.0 → v1.11.1**
- v1.11.0: Architecture refactor (removed configurability)
- v1.11.1: Phase 2E complete (timer and escalation system)

**Earlier This Session - Architecture Refactor (v1.11.0) ✅**
- Removed configurability settings from `module.json`:
  - Removed `stage_1_name` through `stage_8_name` (stage names now hardcoded)
  - Removed `enable_ai_content_generation` (AI always enabled)
  - Removed `enable_branding_ai` (AI branding always enabled)
  - Removed `enable_multi_stakeholder` (multi-stakeholder always enabled)
  - Removed `agency_name` (not needed for internal use)
- Kept operational settings: `default_stage_deadline_days`, `deadline_warning_days`
- Updated `get_stages()` to return hardcoded STAGES constant
- Simplified settings page (removed "Stage Names", "Feature Toggles", "Agency Settings" sections)
- Updated module comments to reflect proprietary nature
- Version bumped: v1.10.7 → v1.11.0
- CHANGELOG updated with architecture decision rationale
- ZIP built successfully

**Reason for Change:**
Per ARCHITECTURE-DECISIONS-FEB-22-2026.md: Expand Site is a proprietary internal tool for ELS competitive advantage, not a sellable product. Removed unnecessary configurability layers to simplify codebase.

**Previous Session (Phase 2D - Multi-Stakeholder System) ✅**
- Stakeholders tab with full CRUD operations (add, remove, change role)
- User search with autocomplete (searches name, email, first name, last name)
- Create new WordPress users directly from stakeholder modal
- Role badges (Decision Maker = green, Contributor = blue)
- Disabled button states with helpful messages (can't remove only DM, etc.)
- Permission-based portal view (shows user's role and appropriate controls)
- "Login As" feature for admins to test as any stakeholder
- Users column on project list showing stakeholder count
- Project deletion feature with cascading delete of all related data
- Shortcode renamed: `[el_project_portal]` → `[el_expand_site_portal]` (future-proofing)

**Version Progression: v1.9.4 → v1.10.0 → v1.10.7 (8 releases)**
- v1.10.0: Core multi-stakeholder system
- v1.10.1: UX fix (disabled button states)
- v1.10.2: First/last name fields for user creation
- v1.10.3: User search functionality fixed
- v1.10.4: Users column added to project list
- v1.10.5: Shortcode renamed for clarity
- v1.10.6: User login fix + "Login As" feature
- v1.10.7: Project deletion feature

**Bug Fixes:**
- Fixed user creation (email-based usernames for login)
- Fixed user search (ID mismatch, meta field search)
- Fixed action button visibility (always show with disabled states)

### What Needs to Happen Next

**Decision Point: Build Approach Going Forward**

Fred requested we build client-facing pages alongside admin features (not all admin first, then all client pages). This gives immediate end-to-end testing and lets frontend UX guide backend decisions.

**Next Steps:**
1. **Test v1.13.1 thoroughly** - Upload to staging and verify:
   - Transcript processing with Claude API works
   - Definition lock/unlock flow
   - Client portal shows all sections when data present
   - Mobile responsive design
   - Stats grid accuracy

2. **Phase 2G - Branding Workflow (Admin + Client Together)**:
   - Admin: Branding tab, mood board upload, AI color generation
   - Client: `[el_brand_selector]` shortcode for brand approval
   - Test full flow: Admin generates → Client sees/selects → Admin sees choice

**Architecture Decision Made:**
Client pages built alongside admin features for each phase, not at the end. This allows complete feature testing and UX refinement in real-time.
- Add Transcript tab to project detail page
- Textarea to paste Fathom meeting summary
- "Process with AI" button to extract project requirements
- AI extracts: site description, goals, target customers, user types, site type
- Display extracted data in editable form
- "Confirm & Lock Definition" button
- AJAX handlers: `es_process_transcript`, `es_save_definition`, `es_lock_definition`
- Add deadline date picker to "Advance Stage" modal
- Auto-flag projects with expired deadlines
- Display deadline warnings on project list
- Add "HELD UP" badge for flagged projects
- Create "Projects Needing Attention" section
- AJAX handlers: `es_extend_deadline`, `es_clear_flag`, `es_set_deadline`

---

## THE MASTER CHECKLIST

**`CURSOR-TODO.md`** (repo root) is the single source of truth for all build work.
- Check off items with `[x]` as you complete them
- Never start a new phase until the current phase is fully checked off and tested
- If Fred asks "what's the list" or "where are we" — that file is the answer
- Update it at the end of every session

---

## HOW TO START A SESSION (Two workstreams in Cursor)

When you open a new chat, paste one of these to set the workstream:

**Expand Site workstream** (shortcodes, CSS, JS, module features):
```
Read @START-HERE-NEXT-SESSION.md. I'm working on the Expand Site workstream.
```

**Core workstream** (Canvas, Admin UI, infrastructure):
```
Read @START-HERE-NEXT-SESSION.md. I'm working on the Core workstream.
```

The agent reads the handoff doc and works only in its scope. Update START-HERE when you finish.

---

## CURRENT STATE

### Deployed
- **EL Core v1.3.0** is active on **expandedlearningsolutions.com**
- NOT deployed to Region 6 (p1vcypp64w.wpdns.site)
- No staging site — test on live site

### What's in the Codebase (v1.3.0)

**Core Infrastructure — 10 classes:**
- class-el-core.php (orchestrator)
- class-settings.php (configuration engine)
- class-database.php (schema manager)
- class-module-loader.php (module discovery)
- class-roles.php (capabilities engine)
- class-asset-loader.php (CSS/JS, brand injection)
- class-ajax-handler.php (unified AJAX)
- class-ai-client.php (AI API wrapper)
- class-admin-ui.php (shared admin UI framework — added in v1.3.0)
- functions.php (global helpers / API boundary)

**Admin Pages:** Dashboard ✅ | Brand ✅ | Modules ✅ | Roles ✅

**Modules in repo (6 total):**
| Module | Directory | Built By | Status |
|--------|-----------|----------|--------|
| Events | modules/events/ | Claude | Functional, no admin UI |
| Registration | modules/registration/ | Claude | Code complete, untested |
| Expand Site | modules/expand-site/ | Cursor | Complete: shortcodes, CSS, JS built |
| Fluent CRM Integration | modules/fluent-crm-integration/ | Cursor | Functional |
| AI Integration | modules/ai-integration/ | Cursor | Functional |

> ✅ `modules/project-management/` deleted — replaced by `modules/expand-site/`

### Key Files
- `CHANGELOG.md` — version history
- `cursor-prompt-expand-site-v3.md` — current Cursor prompt for Expand Site build
- `el-core-admin-build-rules.md` — admin UI framework rules
- `el-core-cursor-handoff.md` — full architecture reference
- `build-zip.ps1` — ZIP builder (uses .NET ZipFile, NOT Compress-Archive)

---

## WHAT WAS DONE THIS SESSION (February 20, 2026)

**Cursor completed Expand Site module build and debugging:**
- Deleted `modules/project-management/` (replaced by expand-site)
- Built 4 shortcodes: project-portal, project-status, page-review, feedback-form
- Built `expand-site.css` and `expand-site.js` (el-es- prefix, ELCore.ajax)
- Added `es_client_review_page` AJAX handler for page approval
- **Fixed critical bugs through v1.4.0 → v1.4.4:**
  - v1.4.1: Added module activation error visibility
  - v1.4.2: Added capability registration debugging
  - v1.4.3: Fixed PHP 7.4 compatibility (replaced `match` with if/switch)
  - v1.4.4: **Fixed infinite loop bug** - all 5 modules were calling `EL_Core::instance()` in constructors, causing recursion

**Current problem:** Expand Site module admin page not rendering. Visiting `/wp-admin/admin.php?page=el-expand-site` shows front-end website instead of WordPress admin. This means `add_submenu_page()` registration failed or routed incorrectly.

**Evidence:**
- Module activates without fatal errors
- "Expand Site" menu item appears in sidebar
- Clicking menu loads front-end site, not admin page
- Events module also has same issue
- CSS/JS assets return 404 (non-critical - files exist locally)

---

## WHAT NEEDS TO HAPPEN NEXT

### URGENT — Fix admin page rendering (Cursor):
**Symptom:** Module menu items appear, but clicking them shows front-end site instead of admin page.

**Diagnosis needed:**
1. Open `modules/expand-site/class-expand-site-module.php` → check `register_admin_pages()` method
2. Verify `add_submenu_page()` is hooked to `admin_menu` in `init_hooks()`
3. Check `render_admin_page()` callback - might have routing issue
4. Compare with Events module (also broken) vs working core pages (Dashboard, Brand, Modules, Roles)
5. Check if `admin/views/project-list.php` has errors preventing render
6. Database tables might not exist - check if `el_es_projects` table was created during activation

**Root cause likely one of:**
- Admin menu hook not firing (timing issue)
- Callback method not found/accessible
- View file missing or has fatal error
- Database tables don't exist, causing query to fail
- Core instance null when view tries to use it

### After admin page fix:
1. Test project creation, stage advancement, and shortcodes on frontend
2. Continue to Phase 2 (Canvas) or Phase 3 (Expand Site polish) per CURSOR-TODO.md

### For Claude — Core Infrastructure:
1. Canvas Page System — bypasses Gutenberg for AI-generated pages
2. Admin UI Framework Rollout — rebuild Brand, Modules, Roles pages
3. Core improvements — uninstall.php cleanup, REST API endpoints

---

## DECISIONS — FINAL, DO NOT RE-DEBATE

- Module is `expand-site` (not `project-management` — that module is deleted)
- All Expand Site tables use `el_es_` prefix
- Asset files: `expand-site.css`, `expand-site.js`
- CSS class prefix: `el-es-` for all Expand Site components
- Admin UI uses `EL_Admin_UI::*` exclusively — no raw HTML
- Deploy via ZIP only — Cursor runs `build-zip.ps1` when needed, upload through WP Admin
- ZIP filename: always `el-core.zip` (no version number)
- WordPress MCP is NOT connected — no wp_fs_write or MCP tools
- Canvas page system is core infrastructure, not a module
- All monolith development (Bold Youth, ELS) is frozen — EL Core only

---

## DEPLOYMENT RULES

- Cursor runs `build-zip.ps1` from repo root when a deployment build is needed (uses .NET ZipFile, NOT Compress-Archive)
- Upload `el-core.zip` via WordPress Admin → Plugins → Add New → Upload Plugin
- Version bump: update plugin header AND `EL_CORE_VERSION` constant (two places)
- Update `CHANGELOG.md` with every version bump
