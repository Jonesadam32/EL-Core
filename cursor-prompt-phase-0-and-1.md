# Cursor Session Prompt — Phase 0 and Phase 1

> **Read this entire document before writing a single line of code.**

---

## YOUR MASTER CHECKLIST

There is a file called `CURSOR-TODO.md` in the root of this repo (`C:\Github\EL Core\CURSOR-TODO.md`).

**That file is your single source of truth for all build work on this project.**

- Every task you complete must be checked off in that file with `[x]`
- If Fred asks "where are we" or "what's the list" — that file is the answer
- Do not start a new phase until every item in the current phase is checked off and tested
- At the end of every session, update `CURSOR-TODO.md` and `START-HERE-NEXT-SESSION.md` to reflect exactly what was completed and what is next

**Read `CURSOR-TODO.md` now before proceeding.**

---

## BUILD AND TEST PROTOCOL — READ THIS BEFORE WRITING ANY CODE

**You do not build everything at once and hand it over. You build one sub-phase at a time, deploy, and wait for Fred to verify it works before continuing.**

After every sub-phase:
1. Run `build-zip.ps1`
2. Tell Fred: "Ready to deploy. Upload `el-core.zip` via WordPress Admin → Plugins → Add New → Upload Plugin. Tell me when it's live and what you see."
3. Wait for Fred to confirm it works
4. Only then check off the items and move to the next sub-phase

If Fred finds a bug, fix it before moving forward. Never stack unverified code on top of unverified code.

---

## YOUR TASK THIS SESSION

You are working on **Phase 0** (critical bug fix) and **Phase 1** (Canvas page system).

Find these phases in `CURSOR-TODO.md` and work through every checkbox in order.

---

## REQUIRED READING — DO THIS BEFORE WRITING CODE

Read these files in this order:

1. `CURSOR-TODO.md` — your checklist (read the whole thing to understand the big picture)
2. `START-HERE-NEXT-SESSION.md` — current state of the codebase
3. `el-core-cursor-handoff.md` — full architecture reference, coding conventions, critical lessons
4. `el-core-admin-build-rules.md` — admin UI framework rules

Do not skip any of these. They contain lessons learned from debugging that will save you significant time.

---

## PHASE 0 — CRITICAL BUG FIX

**Problem:** When you click the "Expand Site" or "Events" menu items in the WordPress admin, the page loads the front-end website instead of the admin page. This means `add_submenu_page()` registration is failing or the callback is routing incorrectly.

**Nothing can be tested until this is fixed.**

### Diagnosis steps (do these before changing anything):

1. Open `el-core/modules/expand-site/class-expand-site-module.php`
2. Find `register_admin_pages()` — verify `add_submenu_page()` is called with the correct parent slug (`el-core`) and the correct capability (`manage_options`)
3. Find `init_hooks()` — verify `admin_menu` hook is registered at priority 20
4. Check that `render_admin_page()` is a `public` method (private methods fail silently as callbacks)
5. Open `el-core/modules/expand-site/admin/views/project-list.php` — check for any PHP fatal errors (missing class references, undefined variables, etc.)
6. Check whether `el_es_projects` database table exists — if the module activation hook didn't fire correctly, tables won't exist, causing a fatal query error that breaks the admin page load
7. Compare the broken pattern against a working core page — look at how `class-el-core.php` registers its own admin menu and how `admin/views/settings-general.php` is called

### The fix:
Apply whatever you find. Then check if the Events module (`modules/events/class-events-module.php`) has the same issue and fix it too.

### Verify the fix:
- Build ZIP using `build-zip.ps1` from repo root
- Upload to expandedlearningsolutions.com via WordPress Admin → Plugins → Add New → Upload Plugin
- Click "Expand Site" in the admin sidebar — it must load the project list admin page
- Click "Events" — it must load the events admin page
- Create a test project to confirm the project list and creation flow work
- Check off all Phase 0 items in `CURSOR-TODO.md`

---

## PHASE 1 — CANVAS PAGE SYSTEM

**What this is:** Core infrastructure that allows AI-generated pages (full HTML/CSS/JS) to be dropped into WordPress without Gutenberg breaking them. Lives in `includes/` and `admin/` — this is NOT a module.

**Why it matters:** Gutenberg's block editor mangles raw HTML and strips JavaScript. The Canvas system bypasses WordPress's content parsing entirely via a custom page template paired with meta boxes for raw HTML/CSS input.

**Two types of pages in this project:**
- Agency-built pages (wire frames, AI-generated site pages) → Canvas mode
- Client interaction pages (portal, content review) → shortcodes on normal Gutenberg pages

Canvas is needed before client-facing Expand Site pages can be built properly.

### Files to create:

**`el-core/includes/class-canvas-page.php`**

This class should:
- Hook into `add_meta_boxes` to register meta boxes on the `page` post type
- Meta box 1: **HTML Content** — full-width `<textarea>` with `rows="30"`, NO sanitization that strips tags or scripts. Save with `update_post_meta`. Use `wp_unslash()` on save but do NOT run through `wp_kses` or any sanitizer that strips HTML/JS.
- Meta box 2: **Custom CSS** — `<textarea>` for CSS rules. Saved separately.
- Meta box 3: **Canvas Mode** — checkbox. When checked: hide theme header and footer on this page.
- Hook into `save_post` to save all three meta values (with nonce verification)
- Hook into `template_include` to return the canvas template path when this page uses it
- Hook into `wp_head` to inject the custom CSS
- Hook into `wp_footer` to respect the Canvas Mode header/footer hiding

**`el-core/templates/template-canvas.php`**

This template should:
- Use `get_post_meta(get_the_ID(), '_el_canvas_content', true)` to get the raw HTML
- Output it with `echo $content` — no `the_content()`, no `wpautop()`, no filters
- Check Canvas Mode meta — if enabled, do NOT call `get_header()` / `get_footer()`
- If Canvas Mode is off, call `get_header()` and `get_footer()` normally
- Still enqueue EL Core frontend assets so brand CSS variables are available

**`el-core/includes/class-el-core.php`**

- Add `require_once` for `class-canvas-page.php` in the boot sequence
- Instantiate `EL_Canvas_Page` after assets are loaded

### WordPress template registration:

The template file must be in `el-core/templates/template-canvas.php` and must have this header comment for WordPress to recognize it as a page template:

```php
<?php
/**
 * Template Name: Canvas (EL Core)
 * Template Post Type: page
 */
```

### Meta box admin styling:

Add a small CSS block via `admin_head` (scoped to post.php and post-new.php) to make the HTML content textarea monospace font and full width. Keep it minimal — just enough for usability.

### Verify Canvas works:
1. Create a new WordPress page
2. In Page Attributes, verify "Canvas (EL Core)" appears in the Template dropdown
3. Select it, add some raw HTML with a `<style>` block and a `<script>` block to the HTML Content meta box
4. Publish and view the page — HTML must render exactly as written, script must execute
5. Enable Canvas Mode checkbox, update, view page — theme header and footer must be hidden
6. Disable Canvas Mode, update — header and footer return

- Check off all Phase 1 items in `CURSOR-TODO.md`
- Bump plugin version, update `CHANGELOG.md`
- Build and deploy ZIP

---

## WHEN YOU FINISH

Before ending the session:

1. Check off every completed item in `CURSOR-TODO.md` with `[x]`
2. Update the plugin version number in both places (`el-core.php` plugin header AND `EL_CORE_VERSION` constant)
3. Update `CHANGELOG.md` with what was built
4. Update `START-HERE-NEXT-SESSION.md`:
   - Change "Last Updated" date
   - Change "Updated By" to Cursor
   - Update "Current Plugin Version"
   - Update "What Was Done This Session"
   - Update "What Needs to Happen Next" to point to Phase 2 in `CURSOR-TODO.md`
5. Run `build-zip.ps1` and deploy final ZIP

---

## RULES — DO NOT VIOLATE THESE

- WordPress MCP is NOT connected — do not use `wp_fs_write` or any MCP tools
- Deploy only via ZIP upload through WordPress Admin → Plugins → Add New → Upload Plugin
- All admin views use `EL_Admin_UI::*` — never raw HTML tables or forms
- Module classes contain business logic only — no `CREATE TABLE`, no `add_shortcode()`
- Shortcode function names must match: tag `el_my_thing` → function `el_shortcode_my_thing`
- Shortcodes return HTML strings — never echo
- CSS class prefix for shared components: `el-`
- CSS class prefix for Expand Site components: `el-es-`
- Guest AJAX needs both hooks: `el_core_ajax_{action}` AND `el_core_ajax_nopriv_{action}`
- Module settings accessed as: `$this->core->settings->get('mod_{slug}', 'key', 'default')`
