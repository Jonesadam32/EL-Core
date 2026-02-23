# EL Core — Changelog

All meaningful changes to EL Core are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows [Semantic Versioning](https://semver.org/).

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
