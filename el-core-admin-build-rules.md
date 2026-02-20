# EL Core Admin Framework — Build Rules

> **Purpose:** This document governs every session that touches the EL Core admin framework.
> Read this before writing any code. It is the source of truth for how we build, what we extract, and in what order.
>
> **Last Updated:** February 20, 2026
> **Status:** Rules established. Ready to begin ELS extraction.

---

## 1. THE CORE MANDATE

We are building **one universal admin framework** that serves multiple WordPress plugin installations. The framework must never be designed around the assumptions of any single source site. Every component we build must work equally well on an educational LMS, an organizational CRM, and any future site.

**The test for every component:**
> "Would this still work if the client was a school district, a nonprofit youth organization, or a government agency?"
> If yes — ship it. If it assumes site-specific data, terminology, or workflow — generalize it first.

---

## 2. SOURCE SITES

We are extracting and consolidating UI patterns from exactly two source sites:

### Source A — Expanded Learning Solutions (ELS)
- **Type:** Organizational management / CRM / business operations
- **Plugin:** `el-solutions.php` (monolith, v2.15.31)
- **What it has:** Dashboard stats, client/contact management, project pipeline, invoicing, proposals, QuickBooks integration, modal forms, tab navigation, filter bars, detail profile views
- **Admin style:** Modern, card-based, polished. Uses CSS variables with `--els-navy`, `--els-teal`, `--els-orange` brand tokens.
- **Extraction order: FIRST**

### Source B — Bold Youth Project
- **Type:** Learning management / student tracking / program operations
- **Plugin:** `bold-youth.php` (monolith, v7.x)
- **What it has:** Course builder, lesson player, student dashboards, quiz engine, certificates, calendar/events, progress tracking, notifications
- **Admin style:** More functional, WordPress-native. Less polished than ELS.
- **Extraction order: SECOND** — build on lessons learned from ELS extraction

### Future Source (NOT IN SCOPE NOW)
- **NYC SMV Tool** — Separate entity with different domain. Will be evaluated as its own extraction project after the framework is proven on ELS + Bold Youth. Do not pre-build for it.

---

## 3. EXTRACTION ORDER

### Round 1 — ELS Modules (Organizational Management)
Build these in sequence. Each one validates the framework before the next:

1. **Organizations / CRM** — list view, card grid, profile detail, add/edit modal
2. **Contacts** — contact rows, portal access badges, linked to organizations
3. **Projects & Pipeline** — project list, kanban/phase view, project detail
4. **Invoicing** — invoice list, invoice detail, line items
5. **Proposals / Scope of Service** — proposal builder, preview, PDF-ready output
6. **QuickBooks Integration** — settings panel, sync status, OAuth connection UI

### Round 2 — Bold Youth Modules (Learning Management)
After Round 1 is complete and the framework is battle-tested:

1. **Courses & Lessons** — course list, lesson builder, content editor
2. **Student Enrollment & Progress** — student list, progress tracking, completion status
3. **Quizzes & Grading** — quiz builder, question types, grading views
4. **Certificates** — certificate templates, issuance, download
5. **Calendar & Events** — merges with EL Core's existing Events module
6. **Dashboards** — role-specific student/instructor/admin views

---

## 4. BUILD RULES

### Rule 1 — Two-Source Check Before Building
Before building any admin component, ask:
- How does ELS handle this?
- How does Bold Youth handle this?

The framework component must accommodate both approaches. If they differ, the component must be configurable enough to support both without forking.

### Rule 2 — No Site-Specific Assumptions
Never hardcode into the framework:
- Role names (`boldyouth_student`, `els_admin`)
- Organization types (`nonprofit`, `for_profit`) — these are data, passed as config
- Pipeline stage names (`prospect`, `active`, `inactive`) — same, data not structure
- Product names (`Expand Site`, `Afterschool Guru`) — configuration, not framework
- Terminology specific to one domain (`client` vs `student` vs `organization`)

**Framework uses neutral terms:** `record`, `entity`, `item`, `entry`, `user`. Modules apply their own labels.

### Rule 3 — Component-First, Not Page-First
We build **reusable components**, not full pages. Pages are assembled from components.

**The component inventory (target):**
| Component | Source | Description |
|-----------|--------|-------------|
| `el-stat-card` | ELS | Icon + number + label metric card |
| `el-data-table` | Both | Sortable, filterable tabular data |
| `el-record-card` | ELS | Clickable card for list views (clients, projects) |
| `el-detail-row` | ELS | Label + value pair in a profile view |
| `el-profile-header` | ELS | Page header with title, badges, back link, action buttons |
| `el-badge` | ELS | Status/type pill label |
| `el-modal` | ELS | Overlay form dialog |
| `el-tab-nav` | ELS | Horizontal tab navigation with optional badge counts |
| `el-filter-bar` | ELS | Search + dropdown filters row |
| `el-form-row` | ELS | Form field row with label, input, helper text |
| `el-form-section` | ELS | Grouped form fields with a section heading |
| `el-empty-state` | ELS | Icon + message + CTA when no records exist |
| `el-page-header` | ELS | Flex row with page title left and action button(s) right |
| `el-notice` | Both | Success/warning/error/info inline notices |
| `el-card` | Both | White rounded card container with optional header |
| `el-btn` | ELS | Primary, secondary, danger button variants |

### Rule 4 — Two Separate Color Systems (Critical)
The admin UI palette and the front-end brand palette are **completely independent systems**.

**Front-end brand colors** — set by the admin in Brand Settings, injected as CSS variables on the public-facing site, fully customizable per installation. Controlled by `el_core_get_brand_colors()`. These colors belong to the client's site identity.

**Admin UI palette** — fixed in `admin.css`, identical across every EL Core installation regardless of what the client's brand looks like. Designed to be professional, readable, and harmonious with WordPress's own admin environment. No client customization. Based on ELS's proven admin palette.

The admin palette uses its own CSS variable namespace (`--el-admin-*`) so there is zero risk of cross-contamination:

```css
:root {
    --el-admin-navy:      #001E4E;   /* Primary text, headings */
    --el-admin-teal:      #00A8B5;   /* Accent, links, icons, active states */
    --el-admin-orange:    #F9A825;   /* Warning, secondary accent */
    --el-admin-gray:      #64748B;   /* Secondary text, labels */
    --el-admin-bg:        #F8FAFC;   /* Page background */
    --el-admin-white:     #FFFFFF;   /* Card backgrounds */
    --el-admin-success:   #10B981;   /* Success states */
    --el-admin-warning:   #F59E0B;   /* Warning states */
    --el-admin-error:     #EF4444;   /* Error states */
    --el-admin-border:    #E5E7EB;   /* Borders, dividers */
    --el-admin-shadow:    rgba(0, 0, 0, 0.1); /* Card shadows */
}
```

**Framework components only ever reference `--el-admin-*` variables.** Never `--el-primary`, never hardcoded hex values.

### Rule 5 — PHP Class Architecture
- All admin UI rendering goes through `class-admin-ui.php`
- The class exposes **static methods** for each component: `EL_Admin_UI::stat_card()`, `EL_Admin_UI::modal()`, etc.
- Methods accept a `$args` array — no positional parameters beyond the first (data)
- Methods **return HTML strings** — never echo directly
- Module admin views call `EL_Admin_UI::*` methods; they do not write raw HTML for shared components

### Rule 6 — JavaScript Architecture
- Modal open/close, tab switching, and filter behavior live in `admin.js` as reusable functions
- Modules do not re-implement tab or modal logic — they call framework functions
- All admin JS uses `elAdmin.*` namespace
- No jQuery dependency for new framework code (jQuery available but use vanilla JS)

### Rule 7 — Extraction Log
When pulling a pattern from ELS or Bold Youth, document it:
- What was extracted
- What was generalized to make it universal
- What site-specific code was removed

This log lives in Section 6 of this document (updated as we go).

### Rule 8 — Session Continuity Protocol
Every new session that touches admin framework code must:
1. Read this rules file first
2. Read `START-HERE-NEXT-SESSION.md` to understand what was done last session and what comes next
3. Check the component status tracker in Section 7 — know what's built vs pending
4. Review any open decisions in Section 8 of this file
5. Write directly to the repo via filesystem access — never hand files to the developer to paste
6. Update `START-HERE-NEXT-SESSION.md` and `CHANGELOG.md` before ending the session

### Rule 9 — Versioning
EL Core uses semantic versioning: `MAJOR.MINOR.PATCH`

- **PATCH** (`1.0.1`) — Bug fix, typo correction, minor tweak that doesn't add functionality
- **MINOR** (`1.1.0`) — New component, new module feature, new setting, backward-compatible addition
- **MAJOR** (`2.0.0`) — Breaking change, architecture shift, anything that could affect existing installations

**Version bumps happen when something meaningful ships** — not after every edit. A working session that's mid-build does not bump the version. A session that completes a component, module, or fix does.

**When bumping a version, update all three locations in sync:**
1. Plugin file header in `el-core.php` — `Version: x.x.x`
2. The `EL_CORE_VERSION` constant in `el-core.php`
3. `CHANGELOG.md` in the repo root — add a new dated section

Never bump one without updating the others. A version mismatch between the plugin header and the constant will cause confusion on every site running EL Core.

**CHANGELOG.md format:**
```
## [1.1.0] — 2026-02-20
### Added
- class-admin-ui.php with initial component framework
- admin.css with --el-admin-* color palette
- EL_Admin_UI::stat_card() component
- EL_Admin_UI::page_header() component

### Changed
- Updated admin.css to replace hardcoded colors with --el-admin-* variables

### Fixed
- (none)
```

---

## 5. WHAT MAKES A GOOD FRAMEWORK COMPONENT

A component is ready to ship when it satisfies all of these:

- [ ] Works without knowing which module called it
- [ ] Works without knowing which site it's installed on
- [ ] Accepts all variable content as parameters (text, URLs, counts, statuses)
- [ ] Uses only `--el-admin-*` CSS variables for color — no hardcoded values, no brand variables
- [ ] Has at minimum: default state, empty/null state
- [ ] Returns a string (never echoes)
- [ ] Has a clear, single responsibility — not trying to be two components

---

## 6. EXTRACTION LOG

*(Updated as extraction progresses)*

| Date | Component | Extracted From | What Was Generalized |
|------|-----------|---------------|---------------------|
| — | — | — | — |

---

## 7. COMPONENT STATUS TRACKER

| Component | Status | Notes |
|-----------|--------|-------|
| `el-stat-card` | 🔲 Pending | Source: ELS `.els-stat-card` |
| `el-data-table` | 🔲 Pending | Source: Both sites |
| `el-record-card` | 🔲 Pending | Source: ELS `.els-client-card-simple` |
| `el-detail-row` | 🔲 Pending | Source: ELS `.els-detail-row` |
| `el-profile-header` | 🔲 Pending | Source: ELS `.els-profile-header` |
| `el-badge` | 🔲 Pending | Source: ELS `.els-badge` |
| `el-modal` | 🔲 Pending | Source: ELS `.els-modal` |
| `el-tab-nav` | 🔲 Pending | Source: ELS `.els-tab-btn` / `.els-tab-content` |
| `el-filter-bar` | 🔲 Pending | Source: ELS `.els-filters-bar` |
| `el-form-row` | 🔲 Pending | Source: ELS `.els-form-row` |
| `el-form-section` | 🔲 Pending | Source: ELS `.els-form-section` |
| `el-empty-state` | 🔲 Pending | Source: ELS `.els-empty-state` |
| `el-page-header` | 🔲 Pending | Source: ELS `.els-page-header` |
| `el-notice` | 🔲 Pending | Source: Both sites |
| `el-card` | 🔲 Pending | Source: ELS `.els-profile-card` |
| `el-btn` | 🔲 Pending | Source: ELS button variants |

---

## 8. OPEN DECISIONS

*(Add unresolved questions here between sessions)*

- None currently open.
