# Next Chat Prompt — v1.27.3 Testing & Fixes Session

Paste this entire prompt at the start of the next chat:

---

Read @START-HERE-NEXT-SESSION.md and @CURSOR-TODO.md. Current version is v1.27.3 on staging.

## What was done this session (March 7, 2026)

All 4 bugs from v1.27.0 testing were fixed (v1.27.1), then 5 more improvements were made (v1.27.2), then a critical error hotfix (v1.27.3). Testing completed through section 3F — everything passed.

### v1.27.1 — Bug fixes
1. Portal auth already had `is_decision_maker()` check in place
2. Definition consensus UI was blank — AJAX response envelope unwrap fix in `expand-site.js`
3. "View As" replaced with real "Log in as" in Clients contact list (`client-profile.php`)
4. "Switch back to admin" red button added to WP toolbar after Log in as

### v1.27.2 — Improvements
1. **Portal ?project_id routing** — portal now reads `$_GET['project_id']` so "View Project" from dashboard opens the correct project
2. **Back to Dashboard button** — appears above portal header whenever `[el_client_dashboard]` page exists
3. **Definition field editing** — clients can click "✏ Edit" on any field during `pending_review` to update the value directly (new AJAX: `es_client_edit_definition_field`)
4. **Org-contact linkage warning** — Stakeholders tab shows amber warning for any user not linked to a contact record
5. **Menu Visibility settings page** — EL Core → Menus: per-item rules (Always / Logged-in only / Clients only), stored in `wp_options`

### v1.27.3 — Hotfix
- Critical error on all frontend pages caused by `object` type hint in `filter_client_nav_items` — removed strict type hint
- Missing `global $wpdb` in Stakeholders tab admin view — fixed

## What to do next

### Step 1 — Upload v1.27.3 if not yet done
Upload `el-core-v1.27.3.zip` from Downloads to WordPress Admin → Plugins → Add New → Upload Plugin.

### Step 2 — Resume testing at 3G
Open `V1.27.0-TESTING-GUIDE.md` and pick up at **section 3G — DM Final Decision**:
- Log in as the DM test account
- Test the Accept flow → confirm green "Definition approved!" banner
- Test the Needs Revision flow → confirm amber banner with DM note
- Continue through **3H** (admin post-decision states) and **Part 4** (regression check)

### Step 3 — Fix any bugs found in 3G/3H/Part 4
Each fix goes into the next version. Do not build the ZIP until all fixes for that round are done.

### Step 4 — After clean test pass
- Update `V1.27.0-TESTING-GUIDE.md` to add sections covering v1.27.x new features (field editing, back button, menu visibility)
- Update CURSOR-TODO.md Phase 6C testing checklist
- Move on to Phase 6B (Expand Partners) or Phase 2F-D/2F-E (whichever Fred wants next)

## Key files for this session
- `el-core/modules/expand-site/shortcodes/expand-site-portal.php` — portal shortcode
- `el-core/modules/expand-site/assets/js/expand-site.js` — portal JS (definition review, field editing)
- `el-core/modules/expand-site/class-expand-site-module.php` — all AJAX handlers
- `el-core/includes/class-el-core.php` — menu visibility filter
- `el-core/admin/views/settings-menu.php` — Menu Visibility admin page
- `el-core/includes/shortcodes/client-dashboard.php` — client dashboard shortcode
- `V1.27.0-TESTING-GUIDE.md` — testing guide (resume at 3G)
