# Cursor — Continue Expand Site Build

Yes, continue. The 5 core files are done. Now build items 6–8.

Also: **delete the entire `modules/project-management/` directory.** It is replaced by `expand-site`. Do not merge them.

---

## What's Done
- `module.json` ✅
- `class-expand-site-module.php` ✅
- `admin/views/project-list.php` ✅
- `admin/views/project-detail.php` ✅
- `admin/views/project-form.php` ✅

## What To Build Now

### 6. Shortcode Files

Files go in `modules/expand-site/shortcodes/`. All return HTML strings, never echo. All require login — show a message if user is not logged in. Use `el-component` as the outermost wrapper class.

**Naming — module loader derives function name from tag automatically:**
- Tag `el_project_portal` → function `el_shortcode_project_portal` in `shortcodes/project-portal.php`
- Tag `el_project_status` → function `el_shortcode_project_status` in `shortcodes/project-status.php`
- Tag `el_page_review` → function `el_shortcode_page_review` in `shortcodes/page-review.php`
- Tag `el_feedback_form` → function `el_shortcode_feedback_form` in `shortcodes/feedback-form.php`

**`[el_project_portal project_id=""]`**
- If no project_id, auto-detect from logged-in user's `client_user_id` on `el_es_projects`
- Show: current stage name, 8-step visual progress indicator, deliverables for current stage, pending feedback items
- Wrapper: `<div class="el-component el-es-portal">`

**`[el_project_status project_id=""]`**
- Visual 8-step progress bar: completed stages filled, current highlighted, upcoming muted
- Wrapper: `<div class="el-component el-es-stage-bar">`

**`[el_page_review project_id=""]`**
- Lists pages from `el_es_pages` for the project
- Each row: page name, link to page URL, status badge, Approve / Request Revision buttons
- Submits via AJAX: `ELCore.ajax('approve_page', {page_id: X})`
- Wrapper: `<div class="el-component el-es-page-review">`

**`[el_feedback_form project_id="" deliverable_id=""]`**
- Textarea for feedback content
- Dropdown: revision, approval, question, change_order
- Submit via AJAX: `ELCore.ajax('submit_feedback', {...})`
- Wrapper: `<div class="el-component el-es-feedback-form">`

---

### 7. CSS — `modules/expand-site/assets/css/expand-site.css`

- Styles for `.el-es-portal`, `.el-es-stage-bar`, `.el-es-page-review`, `.el-es-feedback-form`
- Use brand variables: `var(--el-primary)`, `var(--el-accent)`, `var(--el-text)`, `var(--el-bg)`
- Do NOT restyle core classes (`el-form`, `el-field`, `el-btn`, etc.) — those are already in `assets/css/el-core.css`

---

### 8. JS — `modules/expand-site/assets/js/expand-site.js`

- Vanilla JS only, no jQuery
- Use `ELCore.ajax(action, data, successCallback, errorCallback)` for all AJAX
- Event delegation on `document` for all button interactions

---

## Conventions Reminder

- Shortcodes return HTML string, never echo
- AJAX hooks: `el_core_ajax_{action}` (authenticated), `el_core_ajax_nopriv_{action}` (guests) — register both if clients might not be admins
- CSS class names must match across PHP, CSS, and JS — three files, one set of names
- `EL_AJAX_Handler::success($data)` and `EL_AJAX_Handler::error($message)` for responses

---

## Deployment

Cursor runs `build-zip.ps1` when deployment is needed; upload via WordPress Admin → Plugins → Add New → Upload Plugin. No MCP tools.
