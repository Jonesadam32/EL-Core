# EL Core — Changelog

All meaningful changes to EL Core are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.24.1] — 2026-03-02
### Added
- **Invoicing module (Phase 6A Step 2)** — Product Management
- Products admin page: card grid, filters (category, status), Add/Edit modal, Delete confirm, Seed Default Products button
- AJAX handlers: `inv_create_product`, `inv_update_product`, `inv_delete_product`, `inv_get_products`, `inv_seed_products`
- Seed creates 6 default ELS products (LMS Licensing, PD Training, Coaching, Retreat, Expand Site, NYC SMV Tool) when missing
- Admin JS: product form submit, edit/delete modals, slug auto-fill from name, seed button
- Product list CSS (el-inv-product-grid, cards, actions)

---

## [1.24.0] — 2026-03-01
### Added
- **Invoicing module (Phase 6A Step 1)** — database + module skeleton
- New module `invoicing`: `module.json` with tables `el_inv_products`, `el_inv_invoices`, `el_inv_line_items`, `el_inv_payments`; capabilities `manage_invoices`, `create_invoices`, `view_invoices`; shortcodes `el_invoice_list`, `el_client_invoices`, `el_invoice_view`, `el_revenue_dashboard`; settings for due days, tax, prefix, company info
- `class-invoicing-module.php`: singleton, admin menu (Invoices, Products, Revenue), AJAX handler stubs for all invoice/product/payment/reporting actions
- Placeholder shortcode files and empty admin views; assets skeleton (invoicing.css, invoicing.js)

---

## [1.23] — 2026-03-01
### Release
- Release 1.23 — sync with staging; ZIP and versioned backup per release rules

---

## [1.22.0] — 2026-02-24 — IN PROGRESS
### Added
- **Definition Consensus Review System** — full stakeholder review workflow for project definitions
- DB schema version 8: `review_status` column on `el_es_project_definition`; new tables `el_es_definition_reviews` (per-round review sessions with deadline, DM decision) and `el_es_definition_comments` (threaded per-field comments with verdicts)
- PHP query helpers: `get_active_definition_review()`, `get_definition_reviews()`, `get_definition_comments()` (tree-structured by field), `get_definition_verdicts()` (tally per field)
- AJAX handlers: `es_send_definition_review`, `es_get_definition_review`, `es_post_definition_comment`, `es_field_verdict`, `es_dm_decision` — all with nopriv variants for portal
- Review flow: Admin saves draft → sends for review with deadline → stakeholders comment per-field + set verdict (approved/needs_revision) → DM makes final decision → admin locks or re-sends
- Silence = abstention: deadline passing doesn't block DM from deciding
- Admin override: Lock Definition available at any time regardless of review status

---

## [1.21.4] — 2026-02-24
### Fixed
- Removed debug DB error output from `handle_save_definition` — clean production error message restored

---

## [1.21.3] — 2026-02-24
### Fixed
- `site_type` field in `el_es_project_definition` widened from VARCHAR(50) to VARCHAR(100) — AI was returning values exceeding 50 chars causing DB error on save
- DB migration schema version 7 runs `ALTER TABLE` automatically on first admin page load
- PHP handler now `substr()`-caps `site_type` at 100 chars as additional safety net

---

## [1.21.2] — 2026-02-24
### Fixed
- Discovery transcript and definition fields no longer accumulate backslashes on repeated save/reload cycles
- Root cause: `sanitize_input()` in AJAX handler was running `sanitize_text_field()` (which strips newlines and adds slashes) on all POST data before handlers ran their own sanitization
- Fix: all textarea fields (`site_description`, `primary_goal`, `secondary_goals`, `target_customers`, `user_types`) and `transcript` now read directly from `$_POST` with `wp_unslash()` then `sanitize_textarea_field()`
- Also added `wp_unslash()` when loading transcript into admin textarea for display

---

## [1.21.1] — 2026-02-24
### Fixed
- Forced version bump from 1.21.0 so WordPress would replace plugin files on upload (WordPress ignores uploads at same version number)

---

## [1.21.0] — 2026-02-23
### Added
- **Phase 2G-B Step 3 — Mood Board in Client Portal**: Branding section in `[el_expand_site_portal]` shows curated template cards grouped by style category; stakeholders vote liked/neutral/disliked per card with immediate AJAX save; progress tracker shows X of Y team members responded; deadline countdown banner when deadline is set; DM sees View Results button after all voted or deadline passes; DM can confirm style direction and close review session.
- **Phase 2G-B Step 5 — Admin Review Management**: Branding tab added to project detail page; shows all mood board review sessions with status, response count, deadline; "Create Mood Board Session" button opens modal with template picker (checkbox grid of all active templates grouped by category); admin can set/extend review deadline.
- **New AJAX handlers**: `es_get_mood_board`, `es_save_template_vote`, `es_get_review_status`, `es_get_review_results`, `es_close_review`, `es_create_review_item`, `es_set_review_deadline`
- **Lightbox**: Click any template image in portal to view full-size
- **Review system CSS**: Complete styling for mood board cards, vote strip, progress bar, deadline banner, lightbox, results modal, admin template picker
- **Notification hooks**: `el_review_item_created`, `el_review_vote_submitted`, `el_review_closed` action hooks added for future notifications module

---

## [1.20.5] — 2026-02-23
### Fixed
- Template library cards now uniform height (fixed 150px image, flex column body, actions pinned to bottom)

---

## [1.20.4] — 2026-02-23
### Fixed
- Template library AJAX calls used wrong action name (`el_core_ajax` → `el_core_action`) causing 400 errors on save, delete, and reorder

---

## [1.20.3] — 2026-02-23
### Fixed
- Template library "Choose from Media Library" button now works — `wp_enqueue_media()` called unconditionally on all Expand Site admin pages; script dependencies updated to include `media-upload`; JS guard moved inside `DOMContentLoaded` callback to prevent premature exit

---

## [1.20.2] — 2026-02-23
### Fixed
- `EL_Database::$schema_versions` now casts `get_option()` result to array — prevents fatal error during plugin installation when the option doesn't exist yet (PHP 8.1+ strict typed property)

---

## [1.20.1] — 2026-02-23
### Added
- **Phase 2G-B: Stakeholder Review & Decision System — Step 2 (Template Library)**
- New admin submenu: EL Core → Template Library
- Template card grid UI grouped by style category (Modern, Classic, Bold, Minimal, Playful, Professional)
- Full CRUD: add/edit/delete templates via modal
- Media Library uploader for template images + live preview
- Drag-to-reorder within category (saves sort_order via AJAX)
- Filter bar: filter by category and active/inactive status
- Stats grid: total, active, category count
- 3 new AJAX handlers: `es_save_template`, `es_delete_template`, `es_reorder_templates`

---

## [1.20.0] — 2026-02-23
### Added
- **Phase 2G-B: Stakeholder Review & Decision System — Step 1 (Database Schema)**
- 4 new database tables via module.json migration 6: `el_es_review_items`, `el_es_review_votes`, `el_es_annotations`, `el_es_templates`
- 3 new capabilities: `es_review_content` (stakeholder voting), `es_close_review` (DM final decision), `es_manage_templates` (admin template library)
- Database version bumped from 5 to 6

---

## [1.19.2] — 2026-02-23
### Changed
- Removed the "Pipeline Progress" card from the admin project detail page — it was rendering as a plain list taking up space without adding value; stage info is already in the stats grid and Stage History tab

---

## [1.19.1] — 2026-02-23
### Changed
- **Brand settings page simplified**: Removed AI logo analysis, Pickr color wheel pickers, palette card UI, and path toggle — these features belong in the client portal workflow (Phase 2G-B), not the admin tool
- Brand colors now use simple hex text inputs with a live color swatch preview strip
- Removed `el_analyze_logo` and `el_save_brand_selection` AJAX handlers (moved to portal phase)
- Removed Pickr CDN enqueue from admin assets (no longer needed on this page)
- Cleaned palette/Pickr CSS and JS from admin.css and admin.js

### Kept from 1.19.0 (correct work preserved)
- `generate_full_token_set()` in `class-asset-loader.php` — full ~25-token CSS output still active
- All new settings fields (`logo_variant_dark`, `logo_variant_light`, `favicon_url`, `dark_mode_preference`, `brand_tone`, `brand_audience`, `brand_values`, `ai_palette_suggestions`, `palette_selected`)
- `complete_with_image()` / `call_anthropic_vision()` in `class-ai-client.php` — kept for portal use later

---

## [1.19.0] — 2026-02-23
### Added
- Brand settings: logo variant fields (dark background, light background, favicon)
- Brand settings: dark mode preference toggle (records intent only — no dark CSS generated yet)
- Brand settings: AI palette suggestions storage (`ai_palette_suggestions`)
- Brand settings: palette selection index (`palette_selected`)
- Brand settings: brand voice fields (tone, audience, values)
- Asset loader: `generate_full_token_set()` — expands CSS output from 5 to ~25 tokens
- CSS tokens: `--el-primary-dark`, `--el-primary-text` (and secondary/accent variants)
- CSS tokens: full neutral scale (`--el-white`, `--el-bg`, `--el-border`, `--el-muted`, `--el-text`, `--el-dark`)
- CSS tokens: semantic colors (`--el-success`, `--el-warning`, `--el-error`, `--el-info`)
- Brand settings page: rebuilt using `EL_Admin_UI` framework (all 5 sections)
- Brand settings page: Pickr JS color wheel pickers for primary/secondary/accent
- Brand settings page: live semantic color preview strip
- Brand settings page: AI logo analysis with 3-palette output
- Brand settings page: path toggle (AI analysis vs manual picker)
- Brand settings page: media uploader buttons for all logo/favicon fields
- AJAX handler: `el_analyze_logo` — sends logo to Claude vision API, returns 3 palette options
- AJAX handler: `el_save_brand_selection` — saves AI palette selection index
- AI client: `complete_with_image()` method for vision-capable models (Anthropic only)
- Admin: `elAdmin.ajax()` helper — standardized AJAX wrapper for brand page JS

### Changed
- Brand settings page HTML rebuilt from raw `<table>` markup to `EL_Admin_UI` components
- CSS variable output now generates full design token set (was 5 variables, now ~25)
- `enqueue_admin_assets()` now injects `elAdminData` (ajaxUrl + nonce) and enqueues Pickr on brand page

---

## [1.18.11] — 2026-02-23
### Changed
- **Reverted to v1.18.5 baseline**: Removed all Login As / Switch Back / toolbar-hiding changes added in v1.18.6–1.18.10. The stakeholders tab Login As (User Switching plugin) remains exactly as it was. Switch Back feature deferred to a future session once staging environment URLs are resolved.

---

## [1.18.10] — 2026-02-23
### Changed
- **Login As / Switch Back deferred**: Removed the portal switch-back bar and all custom session switching code. The Login As buttons remain (using User Switching plugin) but the switch-back feature requires the staging environment to have correct URLs configured in wp-config.php (`WP_HOME` / `WP_SITEURL`) before it can work reliably. This will be revisited once staging is properly configured.

---

## [1.18.9] — 2026-02-23
### Fixed
- **Reverted Login As to use User Switching plugin**: The custom session-switching code was unreliable. Reverted all Login As buttons (stakeholders tab + client profile contacts) to use the same User Switching plugin pattern (`action=switch_to_user`) that was already working. Switch-back bar in portal also reverts to use `user_switching_get_old_user()`.

---

## [1.18.8] — 2026-02-23
### Fixed
- **Login As was logging in as self**: The handler was running on `init` from the front-end URL, where WordPress session state can be unreliable. Moved to `admin_post_el_login_as` hook via `admin-post.php` — fires while Fred is fully authenticated as admin, guarantees clean session switch before redirecting to portal.

---

## [1.18.7] — 2026-02-23
### Changed
- **Login As / Switch Back is now fully built into EL Core** — no third-party plugin required. Uses WordPress transients to store the admin session (8-hour expiry) and `wp_set_auth_cookie()` to switch users. Both the stakeholders tab and client profile contacts table now use the built-in `?el_login_as=` URL. The portal switch-back bar reads the stored transient and uses `?el_switch_back=` to restore the admin session.

---

## [1.18.6] — 2026-02-23
### Added
- **WP toolbar hidden for clients**: Portal-only users (with `view_expand_site`, `es_contributor`, or `es_decision_maker` caps) no longer see the WordPress admin toolbar — clean portal experience only
- **"Switch back to admin" bar**: When an admin switches into a client account via the User Switching plugin, a fixed dark bar appears at the top of the portal showing who you're viewing as, with a one-click "Switch back to admin" button
- **"Login As" on Client Profile contacts**: Each contact with a WP account now has a "Login As" button in the contacts table on the Client Profile page, alongside the existing button on the Project Stakeholders tab

---

## [1.18.5] — 2026-02-23
### Fixed
- **Portal header badge shows wrong role**: `is_decision_maker()` was only checking the legacy `decision_maker_id` column on the project record, not the `el_es_stakeholders` table role. Now checks both — so contacts added as Decision Maker via the stakeholders table correctly see the "Decision Maker" badge in the portal header instead of "Contributor".

---

## [1.18.4] — 2026-02-23
### Improved
- **Add Stakeholder modal shows org contacts**: When a project is linked to an organization, the Add Stakeholder modal now shows a "Contacts from this organization" section at the top. Each contact with a WordPress account (portal access) appears as a one-click **Add** button — primary contacts default to Decision Maker role, others default to Contributor. Only contacts not already on the project are shown.

---

## [1.18.3] — 2026-02-23
### Fixed
- **Primary contacts automatically get portal access**: Marking a contact as Primary now always creates their WordPress account — no second trip to edit required. This applies to both Add Contact and Edit Contact flows.
- **Helper text added**: Primary Contact checkbox now explains that portal access is automatic for primary contacts. Portal Access checkbox clarified as being for non-primary contacts only.

---

## [1.18.2] — 2026-02-23
### Fixed
- **Edit Contact modal missing Portal Access field**: Edit Contact now shows a "Portal Access" section — if the contact already has a WP account it shows a green "already has portal access" notice; if not, shows a checkbox to grant access
- **`update_contact()` now supports granting portal access**: Passing `grant_portal_access=1` creates the WP user and links it to the contact record, same as Add Contact

---

## [1.18.1] — 2026-02-23
### Fixed
- **Clients JS form submission**: Switched from delegated `document` submit listeners to direct form element bindings, fixing a browser issue where submit events from forms inside dynamically-shown modals were not being intercepted correctly
- **Script load order**: Added `el-core-admin` as an explicit dependency for `clients.js` to guarantee correct load sequence
- **Debugging**: Added console log diagnostics to clients.js to aid future troubleshooting

---

## [1.18.0] — 2026-02-23
### Added — Phase 2F-E: Organizations & Client Management
- **New core tables**: `el_organizations` and `el_contacts` — shared infrastructure used by Expand Site and future modules
- **Clients admin page**: Card grid of all client organizations with contact counts, project counts, type/status badges
- **Client profile page**: Org details, contacts table with edit/delete, linked projects list
- **Add/Edit/Delete organization**: Modal forms with full CRUD via AJAX
- **Add/Edit/Delete contacts**: Per-organization contact management with title, primary badge, portal access
- **WP user auto-creation**: Contacts flagged for portal access get a WordPress account automatically
- **Organization search/select**: Project creation modal now has autocomplete org search instead of plain text client name input
- **Auto-org creation**: Typing a new client name during project creation auto-creates an organization record
- **Primary contact auto-stakeholder**: When creating a project linked to an org, the primary contact (if they have a WP account) is automatically added as Decision Maker stakeholder
- **Data migration**: Existing projects get organizations created from their `client_name` values on first upgrade
- **Project list org links**: Client names in project list now link to client profile page

### Technical Changes
- `class-database.php`: Added `ensure_core_tables()` method for core infrastructure tables with version tracking (`el_core_db_version` option)
- `class-organizations.php`: New core class with full CRUD, search, portal user creation, and 9 AJAX handlers
- `class-el-core.php`: Added organizations as 8th subsystem in boot sequence
- `module.json` database version bumped from 4 to 5 — adds `organization_id BIGINT UNSIGNED DEFAULT 0` column to `el_es_projects`
- `class-expand-site-module.php`: `create_project()` now resolves/creates organizations; `migrate_projects_to_organizations()` runs once on upgrade
- `admin/views/client-list.php` and `admin/views/client-profile.php`: New admin view files using `EL_Admin_UI` components
- `admin/js/clients.js`: New JavaScript for org/contact CRUD on Clients page
- `expand-site-admin.js`: Added org search autocomplete in project creation modal
- `project-list.php`: Client names now hyperlinked to client profile when organization_id is set

---

## [1.17.0] — 2026-02-23
### Added — Phase 2F-D: Payment Terms & T&C Settings
- **Default Payment Terms setting**: Full payment schedule (25%/75% split, 30-day net terms, 1.5% late fee, 90-day inactivity clause) auto-seeded as editable setting in Expand Site module settings.
- **Default Terms & Conditions setting**: 9-section boilerplate (scope, client responsibilities, IP, confidentiality, platform/hosting, liability, termination, Georgia governing law, entire agreement) auto-seeded as editable setting.
- **Settings admin page**: Two new textarea fields under "Proposal Defaults" section on the Expand Site Settings page. Fred can edit the boilerplate anytime without touching code.
- **Auto-populate on proposal creation**: Every new proposal automatically inherits both defaults from settings. Existing proposals are not affected.
- **Invoice trigger placeholder**: TODO comment added in `handle_accept_proposal()` for future Phase 2F-E automatic invoice flagging tied to stage advancement.

### Technical Changes
- `module.json` settings array extended with `default_payment_terms` and `default_terms_conditions`
- `seed_default_settings()` method added to module constructor — writes defaults only if setting is currently empty (never overwrites customizations)
- Settings save handler uses `wp_kses_post()` for textarea fields to preserve line breaks
- `handle_create_proposal()` pulls defaults from settings into `payment_terms` and `terms_conditions` columns on insert

---

## [1.16.0] — 2026-02-23
### Changed — Proposal Narrative Redesign
- **AI prompt rewrite**: Proposals now generate as flowing narrative prose across 5 sections (Situation, What We're Building, Why ELS, Investment, Next Steps) instead of labeled form fields. Designed for $35K+ district administrator and nonprofit executive director clients who share proposals with boards.
- **New database columns**: Added 5 LONGTEXT columns to `el_es_proposals` (`section_situation`, `section_what_we_build`, `section_why_els`, `section_investment`, `section_next_steps`) via database version 4 migration. Old columns retained for backward compatibility.
- **Admin edit modal**: Content fields replaced with 5 narrative section textareas with help text describing the purpose of each section. Fred can edit any section after AI generation before sending.
- **Client portal**: Proposal display redesigned as a document-style layout with Georgia serif body font, letterhead header, uppercase section labels in indigo, pricing summary box, social proof placeholder, and collapsible Terms & Conditions. Max-width 800px, responsive at 768px.
- **Definition lock required**: AI generation now requires the project definition to be locked before generating a proposal. Returns clear error message if not locked.

### Technical Changes
- `module.json` database version bumped from 3 to 4
- AI handler returns flat keys (`situation`, `what_we_are_building`, `why_els`, `investment`, `next_steps`) instead of nested `proposal_content` object
- Save handler includes 5 new columns with `wp_kses_post()` sanitization
- JS updated: AI generation handler, Edit button population, and save form all use new field IDs
- Portal shortcode falls back to old columns for pre-migration proposals

---

## [1.15.1] — 2026-02-23
### Fixed
- **AI proposal generation bug**: "Generate with AI" filled modal fields but content didn't persist after save. Root cause: `EL_Admin_UI::form_row()` ignored the custom `id` parameter and always generated IDs as `el-field-{name}`, while the JS targeted custom IDs like `prop-scope`, `prop-goals`, etc. The `form_row()` method now respects the `id` key in args when provided.
- **Proposal edit modal population**: Clicking "Edit" on a proposal now correctly populates all form fields, since the custom element IDs are properly rendered.

---

## [1.15.0] — 2026-02-23
### Added — Phase 2F-B: Proposal / Scope of Service System
- **New database table**: `el_es_proposals` (database version 3 migration)
- **Admin Proposals tab**: View, create, edit, send, and delete proposals from project detail
- **AI proposal generation**: "Generate with AI" button drafts scope, goals, activities, deliverables, and terms from locked project definition + discovery transcript
- **Proposal editing modal**: Full form with client info, scope sections, pricing, payment terms, and conditions
- **Send to client**: Mark proposals as sent — client sees them in the portal
- **Client portal proposal view**: Professional document layout showing full scope of service
- **Accept/Decline**: Decision Maker can accept or decline proposals in the portal
- **Stage 3 auto-advance**: Accepting a proposal at Stage 3 (Scope Lock) automatically advances to Stage 4 (Visual Identity)
- **Final price propagation**: Accepted proposal's final_price auto-updates the project record
- **Proposal delete cascade**: Deleting a project also removes associated proposals

### Technical Changes
- `module.json` database version bumped from 2 to 3
- 7 new AJAX handlers: `es_create_proposal`, `es_save_proposal`, `es_generate_proposal_ai`, `es_send_proposal`, `es_delete_proposal`, `es_accept_proposal`, `es_decline_proposal`
- 3 new query methods: `get_proposals()`, `get_proposal()`, `get_accepted_proposal()`
- Client portal shortcode enhanced with proposal document display and accept/decline buttons
- Frontend CSS: ~120 lines of proposal section styles (info grid, pricing block, terms block)
- Frontend JS: Accept/decline handlers using ELCore.ajax
- Admin JS: New/edit/save/send/delete/AI-generate proposal handlers

---

## [1.14.3] — 2026-02-23
### Fixed - CRITICAL BUGFIX #2 🐛
- **Admin menu disappeared**: Shortcode loading was breaking module initialization
- **Issue**: Loading shortcodes in `__construct()` caused fatal error that prevented admin menu from loading
- **Solution**: Deferred shortcode loading to `init` hook instead of constructor
- **Changed method**: `load_shortcodes()` from `private` to `public` so WordPress can call it via hook

### Technical Changes
- Moved shortcode loading from constructor to `add_action( 'init', [ $this, 'load_shortcodes' ] )`
- Changed `load_shortcodes()` visibility from `private` to `public`
- Module now initializes safely before attempting to load shortcodes

## [1.14.2] — 2026-02-23
### Fixed - CRITICAL BUGFIX 🐛
- **Shortcodes not loading**: Added missing `load_shortcodes()` method to module initialization
- **Issue**: Shortcode files existed but were never required or registered with WordPress
- **Impact**: Portal was showing `[el_expand_site_portal]` text instead of rendering
- **Solution**: Added `load_shortcodes()` method that requires files and calls `add_shortcode()`

### Technical Changes
- Added `load_shortcodes()` method to `class-expand-site-module.php`
- Loads all 4 shortcode files: portal, status, page-review, feedback-form
- Registers shortcodes during module `__construct()` initialization

## [1.14.1] — 2026-02-23
### Changed - Modal-based UX 🎯
- **Card-based interface**: Replaced inline content sections with compact info cards
- **Modal interactions**: Deliverables, Feedback, and Project Definition now open in modals
- **Removed redundant badges**: Removed "Completed"/"Active" badges on stage headers (redundant with stage navigation checkmarks)
- **Better space utilization**: Empty sections no longer take up page space
- **Improved visual hierarchy**: Cleaner, more focused stage content areas

### Technical Changes
- Added `.el-es-info-card` component (clickable cards with icon, title, count, arrow)
- Added `.el-es-modal` system (overlay, container, header, body, close button)
- Added `.el-es-stage-cards` grid layout for stage-specific cards
- Added `.el-es-global-cards` grid for global info cards
- Added `chevron-right` icon to icon set
- Added modal open/close JavaScript with keyboard (Escape) support
- Updated CSS: 150+ lines of new modal and card styles

## [1.14.0] — 2026-02-23
### Changed - MAJOR UX REDESIGN 🎨
- **Client Portal Complete UX Overhaul**:
  - **Stage Navigation as Primary Element**: Timeline moved to top as interactive navigation
  - **Progressive Disclosure**: Click any stage → see only that stage's content
  - **Modern Tech Color Palette**: Indigo (#6366F1) + Cyan (#06B6D4) replacing dark navy + pink
  - **SVG Line Icons**: All emoji replaced with professional Feather Icons
  - **Wizard/Stepper Pattern**: Clear visual states (completed ✓, current, upcoming)
  - **Improved Information Architecture**: Stage-specific content (deliverables, feedback) filtered by selection
  - **Better Visual Hierarchy**: Clear focus on what matters NOW

### Added
- **Stage Navigation System**:
  - 8 clickable stage buttons with hover states
  - Completed stages show checkmark icon (green background)
  - Current stage highlighted with primary color (indigo)
  - Upcoming stages grayed out and disabled
  - URL hash support for bookmarking stages (#stage-3)
  - Smooth scroll between stage content areas

- **Icon System**:
  - 14 inline SVG icons (no external dependencies)
  - Consistent 20px size with 2px stroke weight
  - Icons: check-circle, circle, file-text, file, message-circle, users, user, clipboard, calendar, activity, info, alert-triangle, alert-circle, external-link
  - Helper function `el_es_icon()` for reusable icons

- **CSS Variables**:
  - Complete Modern Tech color system (36 variables)
  - Brand colors (primary, accent, secondary with hover/light variations)
  - Semantic colors (success, warning, error, info with light variants)
  - Neutral scale (11 grays from 50-900)
  - 8 stage-specific colors for visual differentiation

- **New UX Strategy Document**: `CLIENT-PORTAL-UX-STRATEGY.md` explaining design decisions
- **New Color System Document**: `EXPAND-SITE-COLOR-SYSTEM.md` with complete palette reference

### Removed
- **Stats Grid**: Redundant with stage navigation (progress already visible)
- **All Emoji Icons**: 📍📄💬👥📋🚀 replaced with professional SVG line icons
- **Linear Content Layout**: Replaced with progressive disclosure pattern

### Technical
- **JavaScript**: Added stage switching functionality with smooth animations
- **CSS**: Complete rewrite (1000+ lines)
  - CSS Grid for responsive layouts
  - Flexbox for component alignment
  - CSS transitions for smooth interactions
  - Mobile-first responsive design
  - Hover effects with transforms and shadows
- **HTML Structure**: Complete reorganization
  - Stage navigation wrapper
  - Individual stage content areas
  - Global sections (definition, team, notes)
  - Semantic heading hierarchy (H1→H2→H3)
- **Accessibility**: WCAG AA compliant
  - Proper color contrast (4.5:1 minimum)
  - ARIA labels on interactive elements
  - Keyboard navigation support
  - Focus states on all buttons

### Design Philosophy
- **Progressive Disclosure**: Show only relevant content for selected stage
- **Clear Visual Hierarchy**: Most important element (stage nav) is most prominent
- **Context Over Content**: What matters NOW, not everything at once
- **Affordances**: Make interactive elements obviously clickable
- **Professional**: Line icons, modern colors, clean typography

---

## [1.13.1] — 2026-02-22
### Fixed
- **Client Portal Missing Sections**: Actually added the sections that failed to save in v1.13.0
  - Project description/notes now display if present
  - Project definition (AI-extracted) now displays when locked
  - Stakeholders list with avatars and role badges now displays
  - Timeline wrapped in section with proper heading
- **Portal width increased**: Changed max-width from 800px to 1200px to match site width

### Added
- Description content styling (line-height, readable text)

---

## [1.13.0] — 2026-02-22
### Added
- **Phase 2F - Client Portal Retrofit - COMPLETE ✅**:
  - **Stats grid** - 4-card overview showing stage progress, status, deliverable count, pending feedback
  - **Project definition display** - Shows locked definition (description, goals, customers, user types, site type)
  - **Stakeholder list** - Visual cards showing all team members with avatars and role badges
  - **Enhanced pipeline progress** - Moved from redundant admin page to client portal where it's valuable
  - **Professional UX/UI design** - Card-based layout, hover effects, responsive grid, proper spacing
  - Icons for visual recognition (📍stage, 📄deliverables, 💬feedback, 👥team, 📋definition, 🚀timeline)
  - Clean typography hierarchy with large headings and readable body text
  - Color-coded status indicators (green=completed, blue=current, gray=upcoming)
  - Mobile responsive (stacks vertically on phones, 2-column grid on tablets)

### Changed
- **Removed pipeline progress from admin project detail page** - Was redundant with stats grid
- **Restructured portal layout**: Header → Stats → Definition → Team → Timeline → Deliverables → Feedback
- Updated deliverables display from simple list to card-based grid with icons
- Updated feedback display with colored cards (yellow background, orange border)
- Enhanced section headings with emojis and better typography
- Improved empty states with helpful messages

### Technical
- Added 300+ lines of professional CSS with proper animations, shadows, and transitions
- Card hover effects (subtle lift and shadow)
- Gradient backgrounds on info notices
- Responsive breakpoints: 768px (tablet), 480px (mobile)
- Button hover states with transform effects
- Grid layouts with `auto-fit` for flexibility
- Mobile-first approach with column stacking

---

## [1.12.3] — 2026-02-22
### Fixed
- **JSON Parsing from AI Response**: Added robust JSON extraction
  - AI models (especially Claude) sometimes wrap JSON in markdown code blocks
  - AI models may add explanatory text before/after the JSON
  - New `extract_json_from_ai_response()` method handles multiple formats:
    - Markdown code blocks: ` ```json {...} ``` `
    - Generic code blocks: ` ``` {...} ``` `
    - Inline JSON with surrounding text: `Here's the data: {...} Hope this helps!`
  - Extracts clean JSON before parsing
  - Better error logging shows both raw response and extracted JSON

### Technical
- Added regex patterns to find and extract JSON from various AI response formats
- Logs full AI response when JSON extraction or parsing fails (for debugging)
- More helpful error messages distinguish between "no JSON found" vs "invalid JSON"

---

## [1.12.2] — 2026-02-22
### Fixed
- **Model Selection Bug**: Removed hardcoded `'model' => 'gpt-4'` parameter
  - Code was forcing OpenAI's GPT-4 model even when Claude/Anthropic was selected
  - Now respects user's provider and model choice from Brand settings
  - Allows transcript processing to work with Claude API keys

---

## [1.12.1] — 2026-02-22
### Fixed
- **CRITICAL: AI Transcript Processing Error**:
  - Fixed incorrect usage of `el_core_ai_complete()` wrapper function
  - Function returns array with `['success' => bool, 'content' => string, 'error' => string]`
  - Previous code treated it as returning just the content string
  - Now properly checks `$response['success']` and extracts `$response['content']`
  - Added check for AI configuration before processing (helpful error message)
  - Added logging of AI response for debugging JSON parse failures
  - Fixed function call signature (3rd parameter is `$options` array, not `$system` string)
  - Error message now directs users to: EL Core → Brand → AI Settings
  - **Removed hardcoded 'gpt-4' model** - now uses configured provider/model from settings

### Technical
- Added `$this->core->ai->is_configured()` check before AI processing
- Logs AI response to error_log when JSON parsing fails (for debugging)
- Returns friendly error: "AI is not configured. Go to EL Core → Brand → AI Settings to add your API key."
- Removed `'model' => 'gpt-4'` override - respects user's provider choice (Anthropic/OpenAI)

---

## [1.12.0] — 2026-02-22
### Added
- **Phase 2F - Discovery Transcript System - COMPLETE ✅**:
  - **Discovery tab** on project detail page for AI-powered transcript processing
  - **Transcript textarea** for pasting Fathom meeting summaries or discovery call notes
  - **"Process with AI" button** extracts project requirements automatically
  - **AI extraction** pulls from transcript:
    - Site description (1-2 sentence overview)
    - Primary goal (main objective)
    - Secondary goals (additional objectives)
    - Target customers (audience description)
    - User types (different user roles, comma-separated)
    - Site type (category: e-commerce, educational, corporate, etc.)
  - **Editable definition form** displays extracted data for manual refinement
  - **"Save Definition" button** saves changes to project definition
  - **"Confirm & Lock Definition" button** locks definition (prevents further edits)
  - **Locked state UI** shows who locked and when, hides edit controls
  - AJAX handler: `es_process_transcript` - calls AI API and parses JSON response
  - AJAX handler: `es_save_definition` - saves edited definition fields
  - AJAX handler: `es_lock_definition` - locks definition and records who/when
  - `get_project_definition()` query method
  - Admin JavaScript handlers for all transcript/definition interactions
  - Transcript saved to `el_es_projects.discovery_transcript`
  - Extracted data saved to `el_es_project_definition` table
  - `discovery_extracted_at` timestamp tracks when AI last processed transcript

### Changed
- Discovery tab renamed from "Transcript" to "Discovery" in tab nav
- Definition form conditionally shows/hides based on locked state
- Transcript input hidden after definition is locked (immutable once confirmed)

### Technical
- Uses `el_core_ai_complete()` wrapper for AI API calls (GPT-4, temp 0.3)
- AI prompt engineering for structured JSON extraction
- Handles array-to-string conversion for user_types field
- Permission checks: only admins can process/save/lock definitions
- Database migration already exists (v2) - no schema changes needed

---

## [1.11.2] — 2026-02-22
### Fixed
- **CRITICAL: Advance Stage Form Not Working**:
  - Added missing JavaScript handler for `#advance-stage-form`
  - Form was submitting as regular HTML form instead of AJAX
  - Caused blank page when clicking "Approve & Advance"
  - Now properly submits via AJAX and reloads page after stage advancement
  - Handler captures project_id, deadline, and notes fields

---

## [1.11.1] — 2026-02-22
### Added
- **Phase 2E - Timer and Escalation System - COMPLETE ✅**:
  - Deadline date picker in "Advance Stage" modal with stage-specific smart defaults
  - Stage-specific deadline defaults (hardcoded: Qualification 3d, Discovery 7d, Build 14d, etc.)
  - Deadline column on project list with warning badges ("2d left")
  - Auto-flagging: expired deadlines automatically set `flagged_at` and `flag_reason`
  - "Projects Needing Attention" section at top of project list (flagged or deadline warnings)
  - "HELD UP" badge for flagged projects
  - "Xd OVERDUE" badge for expired deadlines (red)
  - "Xd left" badge for approaching deadlines (yellow warning)
  - AJAX handler: `es_set_deadline` - manually set deadline for current stage
  - AJAX handler: `es_extend_deadline` - extend existing deadline
  - AJAX handler: `es_clear_flag` - clear flagged status
  - Deadlines stored in both `el_es_projects.deadline` and `el_es_deadlines` table for history
  - `get_stage_deadline_days()` helper method

### Changed
- **Removed `default_stage_deadline_days` setting** (per architecture decision):
  - Setting was a blanket number for all stages (doesn't make sense)
  - Replaced with stage-specific hardcoded defaults (STAGE_DEADLINE_DAYS constant)
  - Actual deadlines set per-project when advancing stages
  - Settings page now cleaner with only truly operational settings
- `advance_stage()` method now accepts optional `$deadline` parameter
- Project list now separates "Projects Needing Attention" from regular projects

### Reason
Different stages have different time requirements. A blanket "7 days for everything" doesn't reflect reality. Discovery ≠ Build ≠ Review. Stage-specific defaults + per-project adjustment when advancing stages is more flexible and accurate.

---

## [1.11.0] — 2026-02-22
### Changed
- **ARCHITECTURE: Expand Site is now proprietary (not resale-ready)**:
  - Removed configurability settings from module.json:
    - `stage_1_name` through `stage_8_name` — stage names now hardcoded
    - `enable_ai_content_generation` — AI features always enabled
    - `enable_branding_ai` — AI branding tools always enabled
    - `enable_multi_stakeholder` — multi-stakeholder always enabled
    - `agency_name` — not needed for internal use
  - Kept operational settings: `default_stage_deadline_days`, `deadline_warning_days`
  - `get_stages()` method now returns hardcoded STAGES constant
  - Settings page simplified: removed "Stage Names", "Feature Toggles", and "Agency Settings" sections
  - Module description updated to reflect proprietary nature
  - Expand Site is built exactly for Expanded Learning Solutions workflow

### Reason
Expand Site will never be marketed as a standalone product. It is a competitive advantage tool for ELS. Building it as a configurable product for other agencies is wasted engineering effort. This change removes unnecessary abstraction layers and makes the codebase simpler and more maintainable.

---

## [1.10.7] — 2026-02-22
### Added
- **Project Deletion**:
  - Added "Delete" button on project list actions
  - Confirmation dialog shows what will be deleted
  - Cascading delete removes all related data:
    - Project record
    - All stakeholders
    - All deliverables  
    - All feedback
    - All pages
    - All stage history
  - AJAX handler with permission checks
  - Cannot be undone (permanent deletion)

---

## [1.10.6] — 2026-02-21
### Added
- **User Switching / Login As Feature**:
  - Added "Login As" button on stakeholder list (admin only)
  - Allows admins to switch to any stakeholder account to test client experience
  - Redirects to home page after switch so admin sees site as that user
  - Stores original admin ID for future "switch back" feature

### Fixed
- **User Login Issues**:
  - Changed username creation to use email address directly (better UX)
  - Users can now login with their email instead of mangled username
  - Fallback to `email_###` if email username already exists
  - Added temporary password storage in user meta for admin reference
  - Fixed: "User does not exist" error when trying to login

### Changed
- Improved user creation with better error handling for duplicate usernames

---

## [1.10.5] — 2026-02-21
### Changed
- **BREAKING: Shortcode Renamed for Clarity**:
  - Renamed `[el_project_portal]` → `[el_expand_site_portal]`
  - More specific name prepares for future modules (Expand Partner, etc.)
  - File renamed: `project-portal.php` → `expand-site-portal.php`
  - Updated module.json shortcode registration
  - Updated frontend asset enqueue check
  - **Action Required:** Update any pages using old shortcode name

---

## [1.10.4] — 2026-02-21
### Added
- **Expand Site - Project List**:
  - Added "Users" column showing stakeholder count per project
  - Shows number of team members (Decision Makers + Contributors)
  - Displays "—" if no stakeholders assigned yet
  - Positioned between Stage and Budget columns

---

## [1.10.3] — 2026-02-21
### Fixed
- **Expand Site - User Search**:
  - Fixed user search input ID mismatch (was generating wrong ID)
  - Search field now properly triggers AJAX autocomplete
  - Enhanced search to include first_name and last_name meta fields
  - Search now finds users by: login, email, display_name, first_name, last_name
  - Results deduplicated and limited to 10 users
  - Added console logging for debugging search functionality
  - Fixed event delegation for dynamically loaded modal
  - Improved debounce handling for search input

---

## [1.10.2] — 2026-02-21
### Changed
- **Expand Site - Stakeholder User Creation**:
  - Split "New User Display Name" field into separate "First Name" and "Last Name" fields
  - Better data structure - first_name and last_name saved separately in WordPress user meta
  - Display name automatically built from "First Last" format
  - More professional and database-friendly user creation
  - Validation requires both first and last name (not just display name)

---

## [1.10.1] — 2026-02-21
### Fixed
- **Expand Site - Stakeholder Management UX**:
  - Action buttons now always visible on stakeholder rows
  - Disabled state for buttons that can't be clicked (with helpful tooltips)
  - Can't remove only stakeholder → button disabled with message
  - Can't demote only Decision Maker → button disabled with message
  - JavaScript alerts explain why action is blocked
  - Added visual styling for disabled buttons (opacity, no-cursor)
  - Added portal role badges styling and contributor notice styling

---

## [1.10.0] — 2026-02-21
### Added
- **Expand Site Phase 2D - Multi-Stakeholder System**:
  - New "Stakeholders" tab on project detail page with user management UI
  - Add Stakeholder modal with user search and new user creation
  - Role management: Decision Maker vs Contributor with visual badges
  - Change role button (toggle between DM and Contributor)
  - Remove stakeholder functionality with safeguards (can't remove last stakeholder or only DM)
  - User search via AJAX with autocomplete results
  - New WP user creation directly from stakeholder modal (sends password reset email)
  - AJAX handlers: `es_add_stakeholder`, `es_remove_stakeholder`, `es_change_stakeholder_role`, `es_search_users`
  - Project portal shortcode now displays user's role badge (Decision Maker / Contributor)
  - Permission notice for Contributors explaining their limited access vs Decision Makers
  - `get_stakeholders()` query method in module class
  - Stakeholder action methods: `add_stakeholder()`, `remove_stakeholder()`, `change_stakeholder_role()`

### Changed
- Project detail page tab order: Stakeholders now appears as second tab (after Overview)
- Stakeholder management syncs with `decision_maker_id` field in projects table
- Admin JavaScript expanded with stakeholder form handlers and user search debouncing

---

## [1.9.1] — 2026-02-21
### Fixed
- **Expand Site Module** - Added missing admin JavaScript for project creation form
- Project creation form now submits via AJAX and redirects to project detail page
- Created `expand-site-admin.js` to handle admin-side form submissions

---

## [1.9.0] — 2026-02-21
### Added
- **Expand Site Phase 2B & 2C - Capabilities and Settings**:
  - Added `es_decision_maker` and `es_contributor` capabilities for multi-stakeholder projects
  - Permission helper methods: `is_decision_maker()`, `is_stakeholder()`, `can_contribute()`
  - Updated all 3 client-facing shortcodes to support both legacy single-client and new multi-stakeholder models
  - Module settings page with configurable stage names, deadline defaults, and feature toggles
  - Settings: 8 customizable stage names, deadline days, AI feature toggles, agency name
  - Stage names now pull from settings instead of hardcoded constants (resale-ready)
  - Default role mappings include new capabilities for all standard WordPress roles

### Changed
- `get_stage_name()` converted from static method to instance method that reads from settings
- All admin views and shortcodes updated to use dynamic stage names from settings
- Backward compatibility maintained with static `get_stage_name_static()` fallback

---

## [1.8.0] — 2026-02-21
### Added
- **Expand Site Phase 2A - Database Schema** (module v1.0.0 → database v2):
  - Added 9 new columns to `el_es_projects` table: `decision_maker_id`, `deadline`, `deadline_stage`, `flagged_at`, `flag_reason`, `project_type`, `project_goal`, `discovery_transcript`, `discovery_extracted_at`
  - Added 3 new columns to `el_es_pages` table: `ai_draft_content`, `client_review_status`, `content_blocks`
  - Created 5 new tables:
    - `el_es_stakeholders` - Multi-stakeholder project access control
    - `el_es_project_definition` - Structured discovery data (site description, goals, user types)
    - `el_es_brand_options` - Branding workflow data (mood boards, AI color options, fonts)
    - `el_es_user_workflows` - Client-submitted workflow descriptions per user type
    - `el_es_deadlines` - Stage-based deadline tracking and escalation
  - Schema migrations run automatically on next module activation
  - Existing projects unaffected - new columns nullable with safe defaults

---

## [1.7.0] — 2026-02-21
### Changed
- **Gutenberg disabled for all pages**: Switched to Classic Editor to prevent Gutenberg from interfering with Canvas pages
- Canvas pages tested with simple HTML - full JavaScript support pending Rocket.net WAF whitelist

---

## [1.6.0] — 2026-02-21
### Added
- **Canvas Page System** (Phase 1 complete):
  - `class-canvas-page.php` - Meta boxes for raw HTML content, custom CSS, and Canvas Mode toggle
  - `template-canvas.php` - Custom page template that bypasses WordPress content filters
  - Allows AI-generated pages (full HTML/CSS/JS) to be dropped into WordPress without Gutenberg breaking them
  - Canvas Mode option hides theme header/footer for full-page control
  - No sanitization on HTML content - scripts and styles render exactly as written
  - Integrated into EL_Core boot sequence

---

## [1.5.4] — 2026-02-21
### Fixed
- **Admin menu priority issue**: Module submenu items (Expand Site, Events) now register at priority 20 (after core's priority 10), fixing race condition where menus wouldn't appear
- **Standardized menu slugs**: Changed to `el-core-projects` and `el-core-events` to match naming pattern of other core submenus

---

## [1.4.5] — 2026-02-20
### Changed
- Session handoff: Updated START-HERE-NEXT-SESSION.md with admin page rendering issue diagnosis

---

## [1.4.4] — 2026-02-20
### Fixed
- **CRITICAL: Infinite loop bug in all modules**: Module constructors were calling `EL_Core::instance()` which triggered module loading again, causing infinite recursion. Changed all 5 modules (Expand Site, Events, Registration, AI Integration, Fluent CRM) to accept core instance as parameter instead.

---

## [1.4.3] — 2026-02-20
### Fixed
- **Expand Site module PHP compatibility**: Replaced `match` expressions with if/switch for PHP 7.4 compatibility (match requires PHP 8.0)

---

## [1.4.2] — 2026-02-20
### Fixed
- Added capability registration debugging to diagnose Expand Site menu visibility issue

---

## [1.4.1] — 2026-02-20
### Fixed
- Module activation: Added detailed error messages when module fails to load
- Module activation: Better stack trace logging
- Module activation: Throws exception if class file missing or class not found

---

## [1.4.0] — 2026-02-20
### Added
- Expand Site module: 4 client portal shortcodes (`[el_project_portal]`, `[el_project_status]`, `[el_page_review]`, `[el_feedback_form]`)
- Expand Site assets: `expand-site.css`, `expand-site.js` with `el-es-` prefix and brand variables
- AJAX handler `es_client_review_page` for client page approval/revision

### Changed
- Deleted `modules/project-management/` — fully replaced by `modules/expand-site/`
- Session handoff: added workstream startup prompts to START-HERE-NEXT-SESSION.md

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
