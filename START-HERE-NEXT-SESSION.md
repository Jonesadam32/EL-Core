# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** February 20, 2026
> **Updated By:** Claude
> **Current Plugin Version:** 1.4.0

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

Cursor completed Phase 1 deploy prep and Expand Site client portal:
- Deleted `modules/project-management/` (replaced by expand-site)
- Built 4 shortcodes: project-portal, project-status, page-review, feedback-form
- Built `expand-site.css` and `expand-site.js` (el-es- prefix, ELCore.ajax)
- Added `es_client_review_page` AJAX handler for page approval
- Bumped version to 1.4.0, updated CHANGELOG, ran build-zip.ps1

---

## WHAT NEEDS TO HAPPEN NEXT

### For Cursor — Phase 1 verification (user actions):
1. **Upload** `el-core.zip` to expandedlearningsolutions.com (WordPress Admin → Plugins → Add New → Upload)
2. Verify plugin activates, Expand Site appears, create test project, test shortcodes on frontend
3. Continue to Phase 2 (Canvas) or Phase 3 (Expand Site polish) per CURSOR-TODO.md

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
