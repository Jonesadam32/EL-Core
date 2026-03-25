# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** March 25, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.34.5 — Module save form now uses POST/Redirect/GET (errors will finally be visible). **Expand Site module STILL fails to activate — root cause not yet identified. This is the #1 priority next session.**
>
> **SWITCHING COMPUTERS:** Repo backed up to GitHub. On the other machine: `git pull origin main`, then run `.\build-zip.ps1` if you need a fresh ZIP.

---

## 🚨 IMMEDIATE PRIORITY — EXPAND SITE MODULE WON'T ACTIVATE

**The Expand Site module silently unchecks itself when you try to activate it on the EL Core → Modules page.** We have been debugging this across two sessions. Here is the complete diagnostic history so you don't repeat anything.

---

### WHAT THE USER REPORTS
- Go to EL Core → Modules
- Check "Expand Site", click "Save Module Configuration"
- The checkbox unchecks itself — module never activates
- No error notice has been visible (this changes with v1.34.5)
- Version 1.33.2 works fine — this started somewhere after that

---

### v1.34.5 CHANGES NEEDED FIRST: READ BEFORE NEXT DEBUG STEP

**Upload `el-core-v1.34.5.zip` (in Downloads) before the next debug session.**

With v1.34.5 installed, the module page now uses POST/Redirect/GET:
- Form processing moves to a `load-{page}` hook (before HTML output)
- After save, redirects back cleanly
- Errors from `load_module()` now survive the redirect via transient and display properly

**The most important result of this:** If the module STILL fails to activate in v1.34.5, **you will now see a red error box** with the exact PHP error message, file, and line number. Tell the next agent what that message says — that is the true root cause.

---

### FULL HISTORY OF FIXES ATTEMPTED (DO NOT REPEAT THESE)

#### Session 1 (March 24, 2026) — Transcript: [Expand Site Module Activation Debug](63c5071f-aace-4182-bee9-44e2626de144)

**v1.34.1 — Fix: `$core` property was `private`**
- `EL_Expand_Site_Module::$core` was declared `private`
- Admin views (`project-list.php`, `settings.php`) accessed `$module->core` directly → fatal PHP error
- Fixed: changed to `public ?EL_Core $core = null;`
- Also hardened the singleton `instance()` to bind `$core` if called before module loader passes it
- Result: Expand Site admin page now loads, but activation STILL fails

**v1.34.2 — Fix: Module class name mismatch (Fluent CRM & rollback hardening)**
- Module loader derived class names using `ucwords()` — this gave wrong names for:
  - `fluent-crm-integration` → derived `EL_Fluent_Crm_Integration_Module`, actual is `EL_FluentCRM_Integration_Module`
  - `ai-integration` → derived `EL_Ai_Integration_Module`, actual is `EL_AI_Integration_Module`
- Fixed: added optional `"main_class"` key in `module.json`; set it for both modules
- Fixed: `activate()` now checks `load_module()` return value and rolls back active list on failure
- Result: Fluent CRM and AI modules now activate. Expand Site STILL fails silently.

**v1.34.3 — Fix: AI Integration class name**
- Same mismatch as above — `"main_class": "EL_AI_Integration_Module"` added to `ai-integration/module.json`

**v1.34.4 — Fix: UTF-8 BOM in JS files**
- Theory: `expand-site.js` and `expand-site-admin.js` had UTF-8 BOM (`EF BB BF`) prepended
- These files are enqueued as EXTERNAL scripts via `wp_enqueue_script()` — **not inline output**
- This theory was WRONG. BOM in external JS files has zero effect on PHP execution or form handling.
- Result: Expand Site STILL fails to activate.

**v1.34.5 — Fix: POST/Redirect/GET for module form**
- Root issue discovered: WordPress page callbacks fire AFTER HTML output
  - `add_action('admin_notices', ...)` called inside `load_module()` catch block → fires too late, never shows
  - `wp_safe_redirect()` called inside page callback → headers already sent, fails silently
- Fixed: form processing moved to `handle_modules_form()` on `load-{page}` hook (fires before output)
- Fixed: errors stored in transient (`el_core_module_errors`) so they survive the redirect
- Fixed: `load_module()` exception handler also stores error in transient, not just `admin_notices`
- Result: **error notices will now display correctly** — next test will reveal the actual PHP error

---

### WHAT TO DO NEXT SESSION

#### Step 1: Install v1.34.5 and reproduce
1. Upload `el-core-v1.34.5.zip` (in Downloads or `releases/` folder)
2. Confirm Plugins shows 1.34.5
3. Go to EL Core → Modules
4. Check Expand Site, click Save Module Configuration
5. **Read the error notice that appears** — it will say something like:
   - `EL Core: Module "expand-site" failed to load and was deactivated. Error: [message]. File: [path] (line [N])`

#### Step 2: Based on what the error says

**If "Call to undefined method":**
- A method referenced somewhere in `class-expand-site-module.php` doesn't exist
- Most likely `add_deliverable()` — was called but may have been deleted during the v1.34.0 Template Library removal
- Fix: restore the missing method (check git history)

**If "Class not found":**
- Class name derivation still failing for expand-site
- The derived name is `EL_Expand_Site_Module` which should be correct
- Check if `class-expand-site-module.php` has a PHP parse error that prevents the class from loading

**If "Table doesn't exist" or DB error:**
- `process_module_schema()` is failing to create the `el_es_visual_brief` table (added in v1.34.0 as migration 13)
- Check the SQL in `module.json` migrations section for the expand-site module

**If PHP parse/syntax error:**
- A syntax error somewhere in `class-expand-site-module.php` (the file is 1424+ lines)
- Check specifically the Visual Identity methods added in v1.34.0 (bottom ~400 lines)
- Try running `php -l el-core/modules/expand-site/class-expand-site-module.php` if PHP is in PATH

**If requirements not met:**
- `check_requirements()` is returning false (but this is unlikely — PHP 8.0 and el_core 1.0.0 are required)

#### Step 3: Quick check you can do WITHOUT the error message
Run this in the WordPress admin (via WP CLI or a test PHP file):
```php
// Test if the class file has a parse error
$file = WP_PLUGIN_DIR . '/el-core/modules/expand-site/class-expand-site-module.php';
$output = shell_exec("php -l {$file} 2>&1");
error_log("PHP lint: " . $output);
```

Or check `wp-content/debug.log` for any lines starting with "EL Core:" — those are from the module loader's `error_log()` calls.

---

### FILES CHANGED ACROSS ALL DEBUG VERSIONS

| File | What Changed | Version |
|------|-------------|---------|
| `el-core/modules/expand-site/class-expand-site-module.php` | `$core` changed to `public`; `instance()` hardened | 1.34.1 |
| `el-core/includes/class-module-loader.php` | `activate()` rollback; `main_class` override; errors stored in transient | 1.34.2 / 1.34.5 |
| `el-core/modules/fluent-crm-integration/module.json` | Added `"main_class": "EL_FluentCRM_Integration_Module"` | 1.34.2 |
| `el-core/modules/ai-integration/module.json` | Added `"main_class": "EL_AI_Integration_Module"` | 1.34.3 |
| `el-core/modules/expand-site/assets/js/expand-site.js` | Stripped UTF-8 BOM | 1.34.4 |
| `el-core/modules/expand-site/assets/js/expand-site-admin.js` | Stripped UTF-8 BOM | 1.34.4 |
| `el-core/admin/views/settings-modules.php` | Removed form processing; now display-only; reads transients | 1.34.5 |
| `el-core/includes/class-el-core.php` | Added `load-{page}` hook + `handle_modules_form()` method | 1.34.5 |

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

### Plugin Version
- **v1.34.5** — built, in Downloads and `releases/` folder
- **v1.33.2** — last known-good version (user confirmed this works)
- Versions 1.34.1–1.34.4 had fixes but Expand Site activation still broken

### What Was Built in the March 10 Session (v1.34.0)

#### Phase 5 Visual Identity — Full details in the previous START-HERE (archived below)
- New `el_es_visual_brief` DB table (migration 13 in module.json)
- Portal intake form (9 sections, auto-save, DM submit)
- Admin panel (states A/B/C, generate/copy/lock brand brief)
- Phase 6 gate: blocks advance until brief is locked
- 5 AJAX handlers: save_visual_brief, submit_visual_brief, get_visual_brief, generate_visual_brief, lock/unlock_visual_brief

### What's Next (after fixing activation)

1. **Fix Expand Site activation** (see above) — bump to v1.34.6
2. **End-to-end Phase 5 test on staging:**
   - [ ] Advance project from Phase 4 to Phase 5 → `el_es_visual_brief` row created, `pages_needed` pre-populated
   - [ ] DM completes all 9 sections in portal, auto-saves fire
   - [ ] DM submits → admin receives email notification
   - [ ] Admin sees full intake summary with logo preview and color swatches
   - [ ] Admin generates brand brief → markdown appears correctly formatted
   - [ ] Copy to clipboard works
   - [ ] Admin regenerates → brief updates in place
   - [ ] Admin locks brief → Phase 6 advance button enables
   - [ ] Admin unlocks → returns to State B with brief still showing
   - [ ] Attempting to advance to Phase 6 without locking → blocked with message
3. **Begin Phase 6 (Wireframes)** when Phase 5 passes testing

---

## CRITICAL LESSONS LEARNED

- Module loader (`class-module-loader.php`) already loads shortcodes from `module.json` — NEVER add `add_shortcode()` in the module class
- Module class should NOT load shortcode files — module loader does this
- **If module fails to load, it AUTO-DEACTIVATES** — `catch (\Throwable $e)` removes slug from active list and saves
- **WordPress admin page callbacks fire AFTER HTML output** — NEVER call `wp_safe_redirect()` or `add_action('admin_notices', ...)` inside a page callback and expect it to work. Use the `load-{page}` hook instead.
- **`admin_notices` hook fires BEFORE the page callback** — errors added inside the callback never show on that page load. Store them in a transient and redirect.
- Always bump version number for EVERY deployment, no exceptions
- **`EL_Admin_UI::form_row()` now supports custom `id` parameter** — always pass `'id'` when JS needs to target the field by ID
- **Admin brand page is ELS's tool only** — per-client branding happens inside Expand Site portal workflow
- **`sanitize_text_field()` strips newlines** — never use it on textarea/transcript content; use `sanitize_textarea_field( wp_unslash( $_POST['field'] ) )` reading directly from `$_POST`, not from the pre-sanitized `$data` array
- **Same-version ZIP uploads are ignored by WordPress** — always bump version before building ZIP
- **`$wpdb->update()` returns `0` (not false) when data is unchanged** — `0 !== false` so treat as success
- **VARCHAR(50) is too small for AI-generated site_type values** — now VARCHAR(100)
- **When doing large block deletions from PHP class files, verify no non-target methods were accidentally removed** — always grep for called methods after deletion
- **BOM in external enqueued JS/CSS files does NOT affect PHP execution** — only inline output BOMs matter

---

## VERSION HISTORY

| Version | What Changed | Status |
|---------|-------------|--------|
| v1.33.2 | (unknown — last known-good version for Expand Site activation) | ✅ Works |
| v1.34.0 | Phase 5 Visual Identity — Mood Board removed, intake form + brand brief generator + lock/unlock + Phase 6 gate | ⚠️ Module fails to activate |
| v1.34.1 | Fixed `$core` private property fatal error in Expand Site admin views | Still broken |
| v1.34.2 | Fixed Fluent CRM class name mismatch; hardened activate() rollback | Still broken for Expand Site |
| v1.34.3 | Fixed AI Integration class name mismatch | Still broken for Expand Site |
| v1.34.4 | Stripped UTF-8 BOM from expand-site.js and expand-site-admin.js (wrong theory) | Still broken |
| v1.34.5 | POST/Redirect/GET for modules form; errors now visible via transient | **Current — install this and test** |
| v1.34.6 | Fix actual root cause once v1.34.5 reveals the error message | **NEXT** |

---

## DEPLOYMENT RULES

- Cursor runs `build-zip.ps1` from repo root when a deployment build is needed (uses .NET ZipFile, NOT Compress-Archive)
- Upload `el-core.zip` via WordPress Admin → Plugins → Add New → Upload Plugin
- Version bump: update plugin header AND `EL_CORE_VERSION` constant AND `build-zip.ps1` (THREE places)
- Update `CHANGELOG.md` with every version bump

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
- **Proposals are built INTO Expand Site** — not a standalone module
- **Module loader handles shortcodes** — NEVER add add_shortcode() in module class
- **Always bump version for every deployment** — no exceptions
- **Admin Brand page = ELS brand only** — per-client branding = Phase 2G-B in the portal
- **Generic feedback card is REMOVED from portal** — feedback is contextual to each stage
- **Definition review = full consensus workflow** — silence is abstention, DM has final say, admin can override-lock anytime
- **Textarea fields MUST use `sanitize_textarea_field( wp_unslash( $_POST['field'] ) )`** — never rely on pre-sanitized `$data` array for multiline content
