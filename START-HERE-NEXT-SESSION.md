# EL Core â€” Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** March 10, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.34.0 â€” Phase 5 Visual Identity built. Mood Board system removed. **Module load error introduced â€” fix this first.**
>
> **SWITCHING COMPUTERS:** Repo backed up to GitHub. On the other machine: `git pull origin main`, then run `.\build-zip.ps1` if you need a fresh ZIP.

---

## ðŸš¨ IMMEDIATE PRIORITY â€” FIX MODULE LOAD ERROR

**The Expand Site module fails to load after the v1.34.0 changes.** This is the same auto-deactivation behavior as before. The module loader catches fatal errors and deactivates the module automatically (lines 152â€“168 of `class-module-loader.php`).

**Root cause â€” most likely:**
During the removal of the Template Library / Mood Board system, `add_deliverable()` was accidentally removed from `class-expand-site-module.php`. The grep for `public function add_deliverable` returns no matches, but the AJAX handler `handle_add_deliverable` still calls `$this->add_deliverable( $data )` at line 1674. This is a fatal PHP error.

**How to diagnose:**
1. Check the WordPress error log on staging (wp-content/debug.log)
2. SSH or WP CLI: `wp --path=/path/to/wp eval 'error_log(print_r(error_get_last(),1));'`
3. Look for "Call to undefined method" or "Fatal error" referencing `class-expand-site-module.php`

**How to fix:**
1. Read `class-expand-site-module.php` â€” search for `handle_add_deliverable` and verify `add_deliverable()` exists
2. If missing, restore it from git: `git diff HEAD~1 -- el-core/modules/expand-site/class-expand-site-module.php`
3. Also check for any other methods that were accidentally removed during the large block deletion
4. Fix, bump to v1.34.1, build ZIP, upload

**Other things to check if `add_deliverable` is not the issue:**
- The `generate_visual_brief()` method references `$this->get_project()` and `$this->get_visual_brief()` â€” verify both exist as public methods
- The block deletion removed lines 4037â€“4534 â€” verify nothing important was in that range besides the template/mood board methods
- Check if `add_deliverable` was defined inside that range and got deleted with it

**After fixing:**
- Verify plugin activates without PHP errors
- Verify Expand Site admin page loads
- Verify project detail page loads for any project
- Verify portal loads for a Phase 5 project (shows placeholder, no errors)

---

## âš ï¸ ARCHITECTURE CHANGES â€” READ BEFORE ANYTHING ELSE

**`ARCHITECTURE-DECISIONS-FEB-22-2026.md`** (repo root) contains major architectural decisions made February 22, 2026 that affect how all future modules are built. Read it before starting any session. Key changes:

- Expand Site is now proprietary â€” strip configurability settings (stage names, feature toggles)
- PM module is a task aggregator only â€” owns shared `el_tasks` table, not a project system
- CRM module cancelled â€” use Fluent CRM instead
- Client Portals module cancelled â€” each program owns its portals
- Public Website module cancelled â€” replaced by EL Theme and EL Learning Theme (monorepo)
- Shared `el_projects` table architecture planned for cross-program project tracking

---

## THE MASTER CHECKLIST

**`CURSOR-TODO.md`** (repo root) is the single source of truth for all build work.
- Check off items with `[x]` as you complete them
- Never start a new phase until the current phase is fully checked off and tested
- If Fred asks "what's the list" or "where are we" â€” that file is the answer
- Update it at the end of every session

---

## CURRENT STATE

### Plugin Version
- **v1.34.0** built locally â€” **NOT yet successfully deployed** (module load error)
- **v1.33.20** was the last known-good version on staging

### What Was Built This Session (March 10, 2026)

#### Part 1 â€” Removed: Mood Board / Template Library System (Phase 2G-B)

The old Visual Identity system (v1.20.0â€“v1.21.0) was a Mood Board â€” admin-curated templates that clients voted on. This was **completely replaced** per `SPEC-VISUAL-IDENTITY-PHASE.md`.

Removed in this session:
- **Deleted** `el-core/modules/expand-site/admin/views/template-library.php`
- **Removed** `es_manage_templates` capability from `module.json` (capabilities array + all role mappings)
- **Removed** from `class-expand-site-module.php`:
  - AJAX hook registrations: `es_save_template`, `es_delete_template`, `es_reorder_templates`, `es_get_mood_board`, `es_save_template_vote`, `es_get_review_status`, `es_get_review_results`, `es_close_review`, `es_create_review_item`, `es_set_review_deadline`
  - Template Library admin menu `add_submenu_page()` call (slug `el-core-template-library`)
  - `render_template_library_page()` method
  - `'el-core-template-library'` from `$our_pages` in `enqueue_admin_assets()`
  - Methods: `get_templates()`, `handle_save_template()`, `handle_delete_template()`, `handle_reorder_templates()`, `get_review_items()`, `get_review_item()`, `get_review_votes()`, `get_user_vote()`, `handle_get_mood_board()`, `handle_save_template_vote()`, `handle_get_review_status()`, `handle_get_review_results()`, `handle_close_review()`, `handle_create_review_item()`, `handle_set_review_deadline()`
- **Removed** from `expand-site-portal.php`: entire mood board voting section, replaced with placeholder comment then new Phase 5 portal section
- **Removed** from `project-detail.php`: Phase 5 mood board panel, Create Review Session modal, `$module->get_templates()` call near top
- **Removed** from `expand-site-admin.js`: Template Library IIFE (~260 lines), Branding Tab Review Management IIFE (~90 lines)
- **Removed** from `expand-site.js`: Mood Board voting/lightbox/DM Results IIFE (~210 lines)
- **Removed** from `expand-site.css`: Template Library admin styles (~175 lines, `.el-tpl-*`), Mood Board portal styles (~375 lines, `.el-es-mood-board-*`), Admin template picker styles (~75 lines, `.es-template-picker*`)
- Deprecated DB tables (`el_es_templates`, `el_es_review_items`, `el_es_review_votes`, `el_es_annotations`, `el_es_brand_options`) â€” **left in DB and module.json per spec** (no new code reads/writes them)

#### Part 2 â€” Built: Visual Identity Phase (Phase 5) â€” v1.34.0

Full spec: `SPEC-VISUAL-IDENTITY-PHASE.md` (repo root)

- **DB migration v13** â€” new table `el_es_visual_brief` (one row per project, ~30 columns)
  - Added to `module.json`: `"version": 13`, table definition in tables section, migration "13" entry
- **Phase initialization** â€” `init_visual_brief( int $project_id )` method:
  - Called from `advance_stage()` when `$new_stage === 5`
  - Creates `el_es_visual_brief` row with defaults
  - Pre-populates `pages_needed` JSON from all locked `el_es_user_journeys.admin_workflow` / `ai_workflow` `implied_pages` fields (merged, deduplicated)
- **Phase 6 gate** â€” added to `handle_advance_stage()`: blocks advance from Stage 5 unless `el_es_visual_brief.locked_at IS NOT NULL`
- **5 AJAX handlers** registered in `init_hooks()`:
  - `es_save_visual_brief` (portal DM, nopriv) â€” auto-saves individual fields
  - `es_submit_visual_brief` (portal DM, nopriv) â€” final submit, sets `portal_submitted_at`, emails admin
  - `es_get_visual_brief` (portal stakeholders, nopriv) â€” returns all brief fields
  - `es_generate_visual_brief` (admin only) â€” runs PHP generator, saves `generated_brief`
  - `es_lock_visual_brief` / `es_unlock_visual_brief` (admin only) â€” sets/clears `locked_at`
- **PHP brand brief generator** â€” `generate_visual_brief( int $project_id ): string` (private method)
  - Pulls from `el_es_visual_brief` + `el_es_projects` + `el_es_project_definition`
  - Outputs full structured markdown document
  - Handles all conditional logic (has_logo, has_brand_colors, multilingual, etc.)
- **Admin panel** in `project-detail.php` (Phase 5 tab):
  - State A: "Awaiting client input" notice + Decision Maker name
  - State B: Read-only intake summary (logo preview, color swatches, all sections) + Generate Brand Brief button + brief display with Copy/Regenerate + Lock Brief button
  - State C (locked): Green locked banner + read-only summary + read-only brief + Copy button + Unlock Brief button
  - Helper function `el_es_vi_render_intake_summary()` defined inline
- **Portal intake form** in `expand-site-portal.php`:
  - 9 sections: Logo, Brand Colors, Typography, Existing Materials, Visual Personality, Site Pages, Photography, Constraints, Additional Notes
  - Conditional show/hide on radio selections (no page reloads)
  - Auto-saves on field blur via `es_save_visual_brief`
  - Pages stored as JSON array from textarea (one page per line)
  - Photography and logo situations mapped to tinyint columns on submit
  - Submit button with confirmation dialog
  - Post-submit: all stakeholders see read-only answers
  - Helper functions `el_es_vi_render_portal_form()` and `el_es_vi_render_portal_readonly()` defined inline
- **CSS** â€” new Visual Identity section added to `expand-site.css`:
  - `.el-es-vi-section`, `.el-es-vi-section-title`, `.el-es-vi-field-group`, `.el-es-vi-question`, `.el-es-vi-answer`, `.el-es-vi-hint`, `.el-es-vi-not-provided`
  - `.el-es-color-swatch` â€” inline color square next to hex values
  - `.el-es-logo-preview` â€” logo image in admin panel
  - `.el-es-vi-submitted-badge` â€” "Submitted by X on Y" badge
  - `.el-es-brief-output-wrap`, `.el-es-brief-actions`, `.el-es-brief-output` â€” generated brief display
  - `.el-es-vi-form` portal form styles, `.el-es-vi-radio-label`, `.el-es-vi-input`, `.el-es-vi-textarea`, `.el-es-vi-conditional`
  - `.el-es-vi-submit-row`, `.el-es-vi-autosave-indicator`
  - `.el-es-vi-readonly-row`, `.el-es-vi-intake-summary`
  - Mobile responsive at 600px breakpoint
- **Portal JS** â€” appended to `expand-site.js`:
  - Conditional show/hide on radio change
  - Auto-save on blur for all `.el-es-vi-autosave` fields
  - Pages textarea â†’ JSON hidden field on blur
  - Submit with confirmation, photography/logo situation mapped to DB fields, 600ms delay before submit AJAX
- **Admin JS** â€” appended to `expand-site-admin.js`:
  - Generate Brand Brief â€” loading state, renders brief on success, reloads on first generation
  - Copy to Clipboard â€” `navigator.clipboard.writeText()` with fallback
  - Regenerate â€” confirmation dialog, overwrites brief display
  - Lock Brief â€” confirmation dialog, reloads to State C
  - Unlock Brief â€” confirmation dialog, reloads to State B

#### Version bumps
- `el-core/el-core.php`: `1.33.20` â†’ `1.34.0` (header + constant)
- `build-zip.ps1`: `1.33.20` â†’ `1.34.0`
- `CHANGELOG.md`: v1.34.0 entry added with full added/removed details

### What's Next (after fixing the load error)

1. **Fix the module load error** (see top of this file) â€” bump to v1.34.1
2. **End-to-end Phase 5 test on staging:**
   - [ ] Advance project from Phase 4 to Phase 5 â†’ `el_es_visual_brief` row created, `pages_needed` pre-populated
   - [ ] DM completes all 9 sections in portal, auto-saves fire
   - [ ] DM submits â†’ admin receives email notification
   - [ ] Admin sees full intake summary with logo preview and color swatches
   - [ ] Admin generates brand brief â†’ markdown appears correctly formatted
   - [ ] Copy to clipboard works
   - [ ] Admin regenerates â†’ brief updates in place
   - [ ] Admin locks brief â†’ Phase 6 advance button enables
   - [ ] Admin unlocks â†’ returns to State B with brief still showing
   - [ ] Attempting to advance to Phase 6 without locking â†’ blocked with message
3. **Begin Phase 6 (Wireframes)** when Phase 5 passes testing

---

## CRITICAL LESSONS LEARNED

- Module loader (`class-module-loader.php`) already loads shortcodes from `module.json` â€” NEVER add `add_shortcode()` in the module class
- Module class should NOT load shortcode files â€” module loader does this
- **If module fails to load, it AUTO-DEACTIVATES** (lines 152â€“168 of module loader) â€” check error log
- Always bump version number for EVERY deployment, no exceptions
- **`EL_Admin_UI::form_row()` now supports custom `id` parameter** â€” always pass `'id'` when JS needs to target the field by ID
- **Admin brand page is ELS's tool only** â€” per-client branding happens inside Expand Site portal workflow
- **`sanitize_text_field()` strips newlines** â€” never use it on textarea/transcript content; use `sanitize_textarea_field( wp_unslash( $_POST['field'] ) )` reading directly from `$_POST`, not from the pre-sanitized `$data` array
- **Same-version ZIP uploads are ignored by WordPress** â€” always bump version before building ZIP
- **`$wpdb->update()` returns `0` (not false) when data is unchanged** â€” `0 !== false` so treat as success
- **VARCHAR(50) is too small for AI-generated site_type values** â€” now VARCHAR(100)
- **When doing large block deletions from PHP class files, verify no non-target methods were accidentally removed** â€” always grep for called methods after deletion

---

## VERSION HISTORY

| Version | What Changed | Status |
|---------|-------------|--------|
| v1.33.19 | Fix "Invalid decision data" â€” missing $user_id in handle_dm_journey_decision() | Built âœ… |
| v1.33.20 | Admin approved state shows full step list before Lock Journey button | Built âœ… |
| v1.34.0 | Phase 5 Visual Identity â€” Mood Board removed, intake form + brand brief generator + lock/unlock + Phase 6 gate | **Build broken â€” module fails to load** âš ï¸ |
| v1.34.1 | Fix module load error from v1.34.0 | **NEXT** |

---

## DEPLOYMENT RULES

- Cursor runs `build-zip.ps1` from repo root when a deployment build is needed (uses .NET ZipFile, NOT Compress-Archive)
- Upload `el-core.zip` via WordPress Admin â†’ Plugins â†’ Add New â†’ Upload Plugin
- Version bump: update plugin header AND `EL_CORE_VERSION` constant AND `build-zip.ps1` (THREE places)
- Update `CHANGELOG.md` with every version bump

---

## DECISIONS â€” FINAL, DO NOT RE-DEBATE

- Module is `expand-site` (not `project-management` â€” that module is deleted)
- All Expand Site tables use `el_es_` prefix
- Asset files: `expand-site.css`, `expand-site.js`
- CSS class prefix: `el-es-` for all Expand Site components
- Admin UI uses `EL_Admin_UI::*` exclusively â€” no raw HTML
- Deploy via ZIP only â€” Cursor runs `build-zip.ps1` when needed, upload through WP Admin
- ZIP filename: always `el-core.zip` (no version number)
- WordPress MCP is NOT connected â€” no wp_fs_write or MCP tools
- Canvas page system is core infrastructure, not a module
- All monolith development (Bold Youth, ELS) is frozen â€” EL Core only
- **Proposals are built INTO Expand Site** â€” not a standalone module
- **Module loader handles shortcodes** â€” NEVER add add_shortcode() in module class
- **Always bump version for every deployment** â€” no exceptions
- **Admin Brand page = ELS brand only** â€” per-client branding = Phase 2G-B in the portal
- **Generic feedback card is REMOVED from portal** â€” feedback is contextual to each stage
- **Definition review = full consensus workflow** â€” silence is abstention, DM has final say, admin can override-lock anytime
- **Textarea fields MUST use `sanitize_textarea_field( wp_unslash( $_POST['field'] ) )`** â€” never rely on pre-sanitized `$data` array for multiline content

