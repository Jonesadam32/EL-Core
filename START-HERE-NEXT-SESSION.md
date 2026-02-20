# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** February 20, 2026
> **Updated By:** Claude
> **Current Plugin Version:** 1.3.0

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
| Expand Site | modules/expand-site/ | Cursor | Core files done, shortcodes/CSS/JS still needed |
| Fluent CRM Integration | modules/fluent-crm-integration/ | Cursor | Functional |
| AI Integration | modules/ai-integration/ | Cursor | Functional |

> ⚠️ `modules/project-management/` must be deleted — it is fully replaced by `modules/expand-site/`

### Key Files
- `CHANGELOG.md` — version history
- `cursor-prompt-expand-site-v3.md` — current Cursor prompt for Expand Site build
- `el-core-admin-build-rules.md` — admin UI framework rules
- `el-core-cursor-handoff.md` — full architecture reference
- `build-zip.ps1` — ZIP builder (uses .NET ZipFile, NOT Compress-Archive)

---

## WHAT WAS DONE THIS SESSION (February 20, 2026)

Cursor built the Expand Site module core files:
- `module.json` — 5 tables (el_es_*), 3 capabilities, 4 shortcodes, 3 settings
- `class-expand-site-module.php` — singleton, STAGES constant, all query/action methods, 9 AJAX handlers
- `admin/views/project-list.php` — stats grid, filter bar, data table, create modal
- `admin/views/project-detail.php` — pipeline progress bar, 5 tabs, stage/deliverable/page modals
- `admin/views/project-form.php` — full edit form using EL_Admin_UI

---

## WHAT NEEDS TO HAPPEN NEXT

### For Cursor — Expand Site (shortcodes, CSS, JS):
Read `cursor-prompt-expand-site-v3.md` for full details. Summary:

1. **Delete `modules/project-management/`** — replaced by expand-site
2. **Build shortcode files** in `modules/expand-site/shortcodes/`:
   - `project-portal.php` → `el_shortcode_project_portal`
   - `project-status.php` → `el_shortcode_project_status`
   - `page-review.php` → `el_shortcode_page_review`
   - `feedback-form.php` → `el_shortcode_feedback_form`
3. **Build `assets/css/expand-site.css`** — el-es- prefix, brand variables
4. **Build `assets/js/expand-site.js`** — vanilla JS, ELCore.ajax()

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
- Deploy via ZIP only — run `build-zip.ps1`, upload through WP Admin
- ZIP filename: always `el-core.zip` (no version number)
- WordPress MCP is NOT connected — no wp_fs_write or MCP tools
- Canvas page system is core infrastructure, not a module
- All monolith development (Bold Youth, ELS) is frozen — EL Core only

---

## DEPLOYMENT RULES

- Run `build-zip.ps1` from repo root (uses .NET ZipFile, NOT Compress-Archive)
- Upload `el-core.zip` via WordPress Admin → Plugins → Add New → Upload Plugin
- Version bump: update plugin header AND `EL_CORE_VERSION` constant (two places)
- Update `CHANGELOG.md` with every version bump
