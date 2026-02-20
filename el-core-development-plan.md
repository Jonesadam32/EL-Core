# EL Core — Development Plan & Task Tracker

> **Version:** 2.0
> **Date:** February 20, 2026
> **Current Plugin Version:** v1.3.0
> **Last Updated By:** Claude (this document should be updated as tasks complete)

---

## HOW TO USE THIS DOCUMENT

- **[ ]** = Not started
- **[~]** = In progress
- **[x]** = Complete
- **🔵 Claude** = Claude is responsible
- **🟢 Cursor** = Cursor is responsible
- **⚡ Parallel** = Can run simultaneously with other workstreams
- **🔗 Blocked** = Waiting on another task to finish first
- **⭐ Priority** = Do this first within its phase

Tasks marked ⚡ Parallel can be worked on in Cursor at the same time Claude is working on something else. When Cursor completes work, Claude reads the files from `C:\Github\EL Core\el-core\` to review and integrate.

---

## 1. CURRENT STATE SUMMARY

### What's Built and Deployed (v1.3.0)

**Core Infrastructure — 10 classes, all complete:**
- class-el-core.php (orchestrator)
- class-settings.php (configuration engine)
- class-database.php (schema manager)
- class-module-loader.php (module discovery)
- class-roles.php (capabilities engine)
- class-asset-loader.php (CSS/JS, brand injection)
- class-ajax-handler.php (unified AJAX)
- class-ai-client.php (AI API wrapper)
- class-admin-ui.php (shared admin framework — NEW in v1.3.0)
- functions.php (global helpers / API boundary)

**Admin Pages:** Dashboard ✅ | Brand ✅ | Modules ✅ | Roles ✅

**Modules Built:** Events (functional, no admin UI) | Registration (code complete, untested) | Expand Site (core files done, shortcodes/CSS/JS remaining) | Fluent CRM Integration (functional) | AI Integration (functional)

**Key Decisions Already Made:**
- Canvas page system bypasses Gutenberg for AI-generated pages
- All Bold Youth and ELS monolith development is frozen — EL Core only
- Unified admin UI framework — every module uses class-admin-ui.php
- Two product lines through one codebase (Learning Platform + Business Operations)
- PowerShell script for ZIP builds, deploy via WP Admin upload
- Parallel development: Claude + Cursor working simultaneously on different workstreams

---

## 2. WORKSTREAM A: CORE INFRASTRUCTURE (🔵 Claude)

These are foundational pieces that other work depends on.

### A1. Canvas Page System ⭐ Priority
> Bypasses Gutenberg for AI-generated pages. Core feature, not a module.

- [ ] Design canvas page data model (meta keys, template selection) — 🔵 Claude
- [ ] Build custom meta box: HTML content textarea — 🔵 Claude
- [ ] Build custom meta box: Custom CSS textarea — 🔵 Claude
- [ ] Build custom meta box: Custom JavaScript textarea — 🔵 Claude
- [ ] Build `template-canvas.php` page template (renders raw, no wp content filters) — 🔵 Claude
- [ ] Add canvas mode toggle to page editor — 🔵 Claude
- [ ] CSS isolation (canvas styles don't leak into WP admin) — 🔵 Claude
- [ ] Test with AI-generated page (paste in Claude/Cursor output, verify rendering) — 🔵 Claude
- [ ] Document canvas system usage for Cursor handoff — 🔵 Claude

### A2. Admin UI Framework Rollout
> Apply the new class-admin-ui.php components to all existing pages.

- [ ] Create reusable admin data table component (sortable, filterable) — 🔵 Claude
- [ ] Create reusable admin CRUD form component — 🔵 Claude
- [ ] Create reusable admin modal/dialog component — 🔵 Claude
- [ ] Rebuild Brand settings page with framework components — 🔵 Claude
- [ ] Rebuild Modules page with framework components — 🔵 Claude
- [ ] Rebuild Roles page with framework components — 🔵 Claude

### A3. Core Improvements
> Known gaps and quality fixes.

- [ ] Improve uninstall.php capability cleanup — 🔵 Claude
- [ ] Add REST API endpoints alongside AJAX (future-proofing) — 🔵 Claude
- [ ] Version bump and changelog system — 🔵 Claude

---

## 3. WORKSTREAM B: EXPAND SITE MODULE (🟢 Cursor) ⚡ Parallel

> This entire workstream runs in Cursor simultaneously with Claude's work.
> Cursor builds directly into `C:\Github\EL Core\el-core\modules\expand-site\`.
> When complete, Claude reads from filesystem to review and integrate.

### B1. Module Foundation ⭐ Priority
- [x] Create `modules/expand-site/module.json` manifest — 🟢 Cursor
- [x] Create `modules/expand-site/class-expand-site-module.php` — 🟢 Cursor

### B2. Admin Dashboard (Internal)
- [x] Project list view — 🟢 Cursor
- [x] Project detail view — 🟢 Cursor
- [x] Create/edit project form — 🟢 Cursor

### B3. Client Portal Shortcodes
- [x] `[el_project_portal]` — 🟢 Cursor
- [x] `[el_project_status]` — 🟢 Cursor
- [x] `[el_page_review]` — 🟢 Cursor
- [x] `[el_feedback_form]` — 🟢 Cursor

### B4. Feedback System
- [ ] Structured review forms with specific questions per stage — 🟢 Cursor
- [ ] Feedback status tracking (pending, actionable, change_order, resolved) — 🟢 Cursor
- [ ] Change order flagging and pricing — 🟢 Cursor

### B5. Frontend Assets
- [x] `modules/expand-site/assets/css/expand-site.css` — 🟢 Cursor
- [x] `modules/expand-site/assets/js/expand-site.js` — 🟢 Cursor

### B6. Integration Review (After Cursor Completes)
- [ ] Claude reads Cursor output from filesystem — 🔵 Claude
- [ ] Claude reviews for convention compliance — 🔵 Claude
- [ ] Claude integrates any shared CSS/JS if needed — 🔵 Claude
- [ ] Package and deploy for testing — 🔵 Claude + Fred

---

## 4. WORKSTREAM C: EVENTS MODULE COMPLETION (🔵 Claude)

> 🔗 Blocked by: A2 (admin data table and CRUD form components)

### C1. Admin Interface
- [ ] Event creation form (using admin UI framework) — 🔵 Claude
- [ ] Event list/management table (sortable, filterable) — 🔵 Claude
- [ ] Event edit form — 🔵 Claude
- [ ] Event deletion with confirmation — 🔵 Claude
- [ ] Attendee/RSVP management view — 🔵 Claude
- [ ] RSVP export (CSV) — 🔵 Claude

### C2. Frontend Improvements
- [ ] Event detail page template — 🔵 Claude
- [ ] Event calendar view shortcode — 🔵 Claude
- [ ] Past events archive — 🔵 Claude

---

## 5. WORKSTREAM D: REGISTRATION MODULE TESTING & ADMIN (🔵 Claude)

### D1. Live Testing ⭐ Priority
- [ ] Activate registration module on dev site — Fred
- [ ] Test open registration mode — 🔵 Claude + Fred
- [ ] Test approval-based registration mode — 🔵 Claude + Fred
- [ ] Test invite-only registration mode — 🔵 Claude + Fred
- [ ] Test closed registration mode — 🔵 Claude + Fred
- [ ] Test email verification flow end-to-end — 🔵 Claude + Fred
- [ ] Test invite code creation, usage, and expiration — 🔵 Claude + Fred
- [ ] Test login blocking for pending/unverified users — 🔵 Claude + Fred
- [ ] Test rate limiting (5 attempts per IP per 15 min) — 🔵 Claude + Fred
- [ ] Test honeypot field — 🔵 Claude + Fred
- [ ] Fix any bugs found during testing — 🔵 Claude

### D2. Admin Interface
> 🔗 Blocked by: A2 (admin data table and CRUD form components)

- [ ] Pending registrations list (approve/reject actions) — 🔵 Claude
- [ ] User management table (status, verification, role) — 🔵 Claude
- [ ] Invite code management (create, view, disable, delete) — 🔵 Claude
- [ ] Registration settings form (mode, fields, redirects) — 🔵 Claude
- [ ] User profile admin view/edit — 🔵 Claude

---

## 6. WORKSTREAM E: FUTURE MODULES (Planned, Not Started)

### E1. Tutorials Module
> ⚡ Parallel — Can assign to Cursor once Expand Site is done

- [ ] Design module.json manifest — TBD
- [ ] Tutorial content management (video, HTML, Scribe, files) — TBD
- [ ] Categorization and tagging system — TBD
- [ ] Contextual triggers ("show on first visit") — TBD
- [ ] Delivery methods: popup/modal, sidebar, inline embed — TBD
- [ ] Completion tracking — TBD
- [ ] Tutorial library page with search — TBD
- [ ] Shortcodes for embedding tutorials — TBD

### E2. Support Agent Module
> 🔗 Blocked by: E1 (depends on Tutorials module)

- [ ] Chat widget (floating button, conversation panel) — TBD
- [ ] Dynamic system prompt builder — TBD
- [ ] Tutorial search integration — TBD
- [ ] Support ticket table and CRUD — TBD
- [ ] Escalation workflow — TBD

### E3. LMS Module
> Revenue driver. Biggest build effort.

- [ ] Course and lesson data model — TBD
- [ ] Enrollment system — TBD
- [ ] Progress tracking — TBD
- [ ] Completion system — TBD
- [ ] AI Tutor sub-feature (course-specific system prompts) — TBD
- [ ] Integration with Registration module lifecycle hooks — TBD

### E4. Business Operations (Beyond Expand Site)
> Migrated from el-solutions.php monolith. One at a time.

- [ ] CRM Module — client contacts, interaction history — TBD
- [ ] Invoicing Module — invoice generation, QuickBooks sync — TBD

### E5. Remaining Learning Platform Modules
- [ ] Certificates Module — PDF generation, badges — TBD
- [ ] Analytics Module — dashboards, reports, export — TBD
- [ ] Notifications Module — email templates, in-app, digests — TBD

### E6. Theme & AI Pipeline
- [ ] EL Theme — companion block theme with brand integration — TBD
- [ ] AI Page Generation Pipeline — Claude generates pages, canvas renders — TBD

---

## 7. IMMEDIATE PRIORITIES (Next 3 Sessions)

| Priority | Task | Owner | Why First |
|----------|------|-------|-----------|
| 1 | Canvas Page System (A1) | 🔵 Claude | Unblocks AI page workflow, Expand Site deliverables |
| 2 | Admin UI table/form components (A2) | 🔵 Claude | Unblocks Events admin, Registration admin |
| 3 | Expand Site module foundation (B1) | 🟢 Cursor | ⚡ Runs in parallel with #1 and #2 |
| 4 | Registration module testing (D1) | 🔵 Claude + Fred | Catches bugs before building more on top |

---

## 8. PARALLEL WORKFLOW GUIDE

### How Claude + Cursor Work Simultaneously

```
┌─────────────────────────────┐     ┌─────────────────────────────┐
│        🔵 CLAUDE             │     │        🟢 CURSOR             │
│                             │     │                             │
│  Core infrastructure        │     │  Expand Site module         │
│  Canvas page system         │     │  (builds directly into      │
│  Admin UI framework         │     │   modules/expand-site/)     │
│  Events admin UI            │     │                             │
│  Registration testing       │     │  Future: Tutorials module   │
│  Integration reviews        │     │  Future: other modules      │
│                             │     │                             │
└──────────┬──────────────────┘     └──────────┬──────────────────┘
           │                                    │
           │    Both write to the same repo:    │
           │   C:\Github\EL Core\el-core\       │
           │                                    │
           └──────────┬─────────────────────────┘
                      │
                      ▼
              ┌───────────────┐
              │  build-zip.ps1 │
              │  (PowerShell)  │
              └───────┬───────┘
                      │
                      ▼
              ┌───────────────┐
              │  WP Admin     │
              │  Upload Plugin │
              └───────────────┘
```

### Rules for Parallel Work

1. **No file conflicts.** Claude and Cursor work on different directories/files. Cursor owns `modules/expand-site/`. Claude owns `includes/`, `admin/`, and other modules.

2. **Shared files need coordination.** If Cursor needs CSS or JS, it creates module-specific assets in `modules/expand-site/assets/` rather than editing `el-core.css` or `el-core.js`. Claude handles shared asset integration during review.

3. **Convention compliance.** Both tools reference:
   - `el-core-project-brief.md` — architecture and conventions
   - `el-core-cursor-handoff.md` — lessons learned and patterns
   - This development plan — task assignments and status

4. **Integration checkpoint.** When Cursor finishes a workstream, Claude reviews the output before packaging.

### Handoff Process: Cursor → Claude

1. Cursor builds files into `modules/expand-site/`
2. Fred tells Claude: "Cursor finished the Expand Site module, review it"
3. Claude reads files from `C:\Github\EL Core\el-core\modules\expand-site\`
4. Claude checks:
   - module.json follows schema
   - Class naming: `EL_Expand_Site_Module`
   - Shortcode function names follow derivation rule (`el_project_portal` → `el_shortcode_project_portal`)
   - CSS class names match across PHP/CSS/JS
   - AJAX hooks include both priv and nopriv where needed
   - No infrastructure code in module class
5. Claude flags issues or confirms ready
6. Fred runs `build-zip.ps1` and deploys

---

## 9. MIGRATION RULES (Monoliths → EL Core)

1. **No direct ports.** Features get redesigned to fit module architecture.
2. **Transfer as-is, then finish.** Incomplete work moves in and gets completed inside EL Core.
3. **One module at a time.** Don't migrate multiple features simultaneously.
4. **Module.json first.** Define the manifest before writing any code.
5. **No infrastructure in modules.** No CREATE TABLE, no add_shortcode(), no settings pages.
6. **Business logic only.** Module classes contain AJAX handlers, data operations, helpers. Nothing else.
7. **CRM, Invoicing, QuickBooks** become separate modules, not parts of Expand Site.
8. **Bold Youth features** (LMS, certificates, etc.) get rebuilt fresh using EL Core patterns.

---

## 10. SESSION LOG

> Track what gets done each session. Add entries as work progresses.

| Date | Session | What Was Done | Version |
|------|---------|---------------|---------|
| Feb 13 | 1-4 | Built core foundation, Events module, Registration module | v1.0.0 |
| Feb 15 | 5 | Created cursor handoff document, project brief updates | v1.0.0 |
| Feb 15 | 6 | Created Expand Site process document | v1.0.0 |
| Feb 20 | 7 | Built class-admin-ui.php, updated admin.css, rebuilt dashboard | v1.3.0 |
| Feb 20 | 8 | Created development plan v2.0 (this document) | v1.3.0 |
| Feb 20 | 9 | Cursor built Expand Site module — all core files, admin views, shortcodes, CSS, JS. Deleted project-management module. | v1.3.0 |
| | | | |
| | | | |
| | | | |

---

## 11. QUICK REFERENCE

**Local repo:** `C:\Github\EL Core\el-core\`
**Release ZIPs:** `C:\Github\EL Core\releases\`
**Build script:** `C:\Github\EL Core\build-zip.ps1`
**Dev site:** `...scqz.wpdns.site`
**Deploy method:** ZIP upload via WP Admin → Plugins → Add New → Upload Plugin
**Plugin text domain:** `el-core`
**Table prefix:** `{wp_prefix}el_`
**Option prefix:** `el_core_` or `el_mod_`
