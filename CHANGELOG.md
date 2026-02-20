# EL Core — Changelog

All meaningful changes to EL Core are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.3.0] — 2026-02-20
### Added
- `includes/class-admin-ui.php` — Admin UI framework with 19 static component methods
- `admin/css/admin.css` — Full rewrite with `--el-admin-*` CSS variable palette
- `admin/js/admin.js` — `elAdmin` namespace with modal, tab, notice, and filter functions
- `el-core-admin-build-rules.md` — Framework build rules
- `CHANGELOG.md` — Version tracking
- `START-HERE-NEXT-SESSION.md` — Session continuity document

### Changed
- `admin/views/settings-general.php` — Rebuilt using `EL_Admin_UI::*` components
- `includes/class-el-core.php` — Added `class-admin-ui.php` to boot sequence

---

## [1.2.7] — 2026-02-12 (prior sessions)
### Added
- Core plugin foundation (`el-core.php`, activation hooks, constants)
- `class-el-core.php` — Orchestrator singleton, boot sequence
- `class-settings.php` — Settings framework using wp_options
- `class-database.php` — Schema manager with versioning and migrations
- `class-module-loader.php` — Module discovery, validation, activation
- `class-roles.php` — Capabilities engine, role mapping
- `class-asset-loader.php` — CSS/JS loading, brand variable injection
- `class-ajax-handler.php` — Standardized AJAX with nonce verification
- `class-ai-client.php` — Claude/OpenAI API wrapper with usage tracking
- `functions.php` — Global helper functions (API boundary)
- Admin settings pages: Dashboard, Brand, Modules, Roles
- Events module — database tables, business logic, shortcodes, AJAX
- `[el_event_list]` and `[el_event_rsvp]` shortcodes
- Frontend CSS with brand variable system (`assets/css/el-core.css`)
- Frontend JS with AJAX helper (`assets/js/el-core.js`)
- `module.json` schema for declarative module configuration
- `uninstall.php` — cleanup on plugin deletion

---

## [1.1.0] — 2026-02-20
### Added
- `includes/class-admin-ui.php` — Admin UI framework with 13 static component methods:
  `wrap`, `page_header`, `card`, `stat_card`, `stats_grid`, `badge`, `empty_state`,
  `notice`, `detail_row`, `tab_nav`, `tab_panel`, `form_section`, `form_row`,
  `filter_bar`, `modal`, `btn`, `data_table`, `record_card`, `record_grid`
- `admin/css/admin.css` — Full rewrite with `--el-admin-*` CSS variable palette,
  all component styles, responsive breakpoints
- `admin/js/admin.js` — `elAdmin` namespace with modal, tab, notice, filter, and
  flash notice functions
- `el-core-admin-build-rules.md` — Framework build rules governing all sessions
- `CHANGELOG.md` — Version tracking
- `START-HERE-NEXT-SESSION.md` — Session continuity document

### Changed
- `admin/views/settings-general.php` — Rebuilt using `EL_Admin_UI::*` components
  as the first proof of concept for the framework
- `includes/class-el-core.php` — Added `class-admin-ui.php` to boot sequence
