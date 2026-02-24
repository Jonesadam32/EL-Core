# Cursor Build Prompt — Stakeholder Review & Decision System
# Target: Phase 2G (Branding Workflow) + Phase 2H (Wireframe Review)

> **Read before starting:**
> 1. `START-HERE-NEXT-SESSION.md`
> 2. `el-core-cursor-handoff.md`
> 3. `el-core-admin-build-rules.md`
> 4. This file
>
> **Last Updated:** February 23, 2026
> **Status:** Spec complete — ready to build
> **Prerequisite:** v1.18.11 deployed and tested ✅

---

## OVERVIEW

This spec defines the **Stakeholder Review & Decision System** — a consent-based team decision workflow built into the client portal. It allows multiple stakeholders (Decision Maker + Contributors) to review content, vote on preferences, and leave feedback, while the Decision Maker retains final authority to close decisions.

This system is NOT a standalone module. It is built **into the Expand Site module** as a reusable internal pattern used across multiple review contexts:

| Phase | Review Context | Content Type | Feedback Method |
|-------|---------------|--------------|-----------------|
| 2G | Template Style Mood Board | Image grid | Preference vote only |
| 2G | Brand Palette Options | Color swatches | Preference vote only |
| 2H | Wireframes | Full page images | Annotation pins + vote |

**Build Phase 2G review capabilities first. Phase 2H annotation layer builds on top.**

---

## DESIGN PHILOSOPHY

### Consent-Based, Not Consensus-Based

The system is designed around **consent**, not consensus. This means:
- Every stakeholder gets a voice (vote + optional comment)
- No one can block progress indefinitely
- The Decision Maker has final authority and closes every decision
- Deadlines enforce participation — silence is not a veto

This matches how district and nonprofit teams actually operate. Don't design for unanimous agreement. Design for informed decision-making by the DM.

### Simplicity First

School district staff are busy. The review interface must be:
- Completable in under 2 minutes per review item
- Mobile-friendly (staff may review from phones)
- No learning curve — obvious what to do on first visit

---

## PART 1 — TEMPLATE STYLE MOOD BOARD (Phase 2G)

### What It Is

A curated library of web page style examples, organized by aesthetic category, that clients browse to signal their visual preferences. Fred's team populates the library through the admin. Clients select from it in the portal.

### Admin Side — Template Library Management

**Location:** EL Core admin → Expand Site → Template Library (new submenu page)

**Admin can:**
- Add template entries (title, style category, description, image URL or upload)
- Edit or delete template entries
- Reorder templates within a category
- Mark templates as active/inactive (inactive don't appear in portal mood board)

**Template entry fields:**
- `title` — VARCHAR 100 (e.g. "Modern Clean", "Bold Editorial")
- `style_category` — VARCHAR 50 (Modern / Classic / Bold / Minimal / Playful / Professional)
- `description` — TEXT (1–2 sentences describing the aesthetic)
- `image_url` — VARCHAR 500 (screenshot or design preview image)
- `sort_order` — INT
- `is_active` — TINYINT(1)

**Admin view:** Card grid using `EL_Admin_UI::*` components. Each card shows the template image, title, category badge, active/inactive toggle. Page header has "Add Template" button that opens a modal.

---

### Client Portal Side — Mood Board Selection

**Location:** Client portal → Branding tab → Template Style section (appears before color selection)

**Display:** Visual grid of template cards, grouped by style category. Each card shows:
- Full template preview image (click to view larger — lightbox)
- Style category badge
- Title

**Stakeholder interaction:**
- Each stakeholder can mark templates as **Liked** (heart icon), **Neutral** (default), or **Disliked** (X icon)
- No limit on how many they can like or dislike
- Their preferences save immediately on click (AJAX, no submit button)
- They can change their preference at any time before the DM closes the review

**Deadline display:**
- If a review deadline is set, a countdown banner appears at top of the mood board section: "Your team has until [date] to share preferences. [X of Y] members have responded."
- Shows which stakeholders have responded vs. not yet responded (names only, not their votes — privacy until DM closes)

**DM view (additional):**
- After all stakeholders have responded OR deadline passes, DM sees a "View Results" button
- Results show: for each template, how many liked / disliked / neutral, broken down by stakeholder
- DM selects one or more template styles as the official direction → clicks "Confirm Style Direction"
- Confirmation is recorded and the mood board section shows "Style Direction Confirmed ✓"

---

## PART 2 — BRAND PALETTE VOTING (Phase 2G)

### What It Is

When AI generates 3 brand palette options from the logo analysis (see Phase 2G branding spec), stakeholders vote on which palette they prefer. Same consent model as the mood board.

**Location:** Client portal → Branding tab → Color Palette section (after mood board, before final brand lock)

**Display:** Three palette cards side by side. Each shows:
- Color swatches (primary, secondary, accent)
- Suggested font names
- AI-generated rationale (1 sentence)
- Vote button: Prefer / Neutral / Don't Prefer

**Behavior:** Same as mood board — immediate AJAX save, stakeholder response tracker, deadline counter.

**DM close:** DM sees vote summary, selects the final palette, clicks "Lock Brand Colors." This saves the selection to `el_core_brand` and triggers token generation. Locks the palette section so no further changes without admin override.

---

## PART 3 — WIREFRAME ANNOTATION (Phase 2H — Build After 2G is Stable)

### What It Is

For wireframe review, stakeholders can pin notes directly onto specific areas of a wireframe image. This is more powerful than free-form comments because it removes ambiguity about which section of the page needs to change.

### How It Works

**Admin uploads wireframe:** Admin uploads wireframe image(s) to the project. Each wireframe is a review item with its own annotation thread and vote.

**Client portal — wireframe review:**
- Wireframe image displayed at full width (or close to it)
- Stakeholder clicks anywhere on the image → a pin drops at that location
- A small comment input appears → they type their note → submit
- Pin appears as a numbered marker on the image (e.g. ①, ②, ③)
- A sidebar or below-image panel lists all pins with their comments, author, and timestamp
- Stakeholder can delete their own pins

**Multiple stakeholders:**
- Each stakeholder's pins are color-coded by author
- All stakeholders see all pins from all team members in real time (or on page load)
- Pins from other stakeholders are read-only (can't delete others' pins)

**Vote (same as above):** Separate from annotations, stakeholder also votes: Approve / Needs Changes / Reject

**DM close:** DM reviews all annotations and votes, then either:
- Marks wireframe as Approved → advances project
- Marks wireframe as Needs Revision → sends back to agency with all annotations visible in admin

**Admin side:** Admin can see all wireframe annotations. No delete — annotations are a permanent record of what the client requested.

---

## DATABASE SCHEMA

All tables use `el_es_` prefix (Expand Site infrastructure).

### `el_es_review_items`
Represents a single thing being reviewed (mood board session, palette options, a wireframe).

```sql
id              BIGINT AUTO_INCREMENT PRIMARY KEY
project_id      BIGINT NOT NULL
review_type     VARCHAR(50) NOT NULL  -- 'mood_board' | 'brand_palette' | 'wireframe'
title           VARCHAR 255
status          VARCHAR(20) DEFAULT 'open'  -- 'open' | 'closed'
deadline        DATETIME NULL
closed_by       BIGINT NULL  -- user_id of DM who closed it
closed_at       DATETIME NULL
dm_decision     TEXT NULL  -- JSON: what the DM selected/confirmed
created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
```

### `el_es_review_votes`
One row per stakeholder per review item.

```sql
id              BIGINT AUTO_INCREMENT PRIMARY KEY
review_item_id  BIGINT NOT NULL
user_id         BIGINT NOT NULL
vote_data       LONGTEXT NOT NULL  -- JSON: flexible per review_type
comment         TEXT NULL  -- optional overall comment
submitted_at    DATETIME DEFAULT CURRENT_TIMESTAMP
updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
UNIQUE KEY unique_vote (review_item_id, user_id)
```

**vote_data JSON structure by review_type:**

Mood board:
```json
{
  "preferences": {
    "template_id_1": "liked",
    "template_id_2": "disliked",
    "template_id_3": "neutral"
  }
}
```

Brand palette:
```json
{
  "option_0": "prefer",
  "option_1": "neutral",
  "option_2": "dont_prefer"
}
```

Wireframe (vote only — annotations are separate):
```json
{
  "verdict": "approve"
}
```

### `el_es_annotations`
Pinned notes on wireframe images. Phase 2H only — do not build in Phase 2G.

```sql
id              BIGINT AUTO_INCREMENT PRIMARY KEY
review_item_id  BIGINT NOT NULL
user_id         BIGINT NOT NULL
pos_x           DECIMAL(5,2) NOT NULL  -- percentage from left (0-100)
pos_y           DECIMAL(5,2) NOT NULL  -- percentage from top (0-100)
comment         TEXT NOT NULL
created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
```

### `el_es_templates`
The curated template style library.

```sql
id              BIGINT AUTO_INCREMENT PRIMARY KEY
title           VARCHAR(100) NOT NULL
style_category  VARCHAR(50) NOT NULL
description     TEXT
image_url       VARCHAR(500)
sort_order      INT DEFAULT 0
is_active       TINYINT(1) DEFAULT 1
created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
```

---

## AJAX HANDLERS

All handlers go in `class-expand-site-module.php`. Register both priv and nopriv only where guests need access (none here — all portal actions require login).

### Phase 2G Handlers

```
es_get_mood_board           — load active templates + current user's votes for a project
es_save_template_vote       — save/update a stakeholder's preference on a template
es_get_palette_votes        — load palette options + current user's vote
es_save_palette_vote        — save/update stakeholder's palette preference
es_get_review_status        — return who has/hasn't voted on a review item (DM only)
es_get_review_results       — return full vote breakdown (DM only, after deadline or all voted)
es_close_review             — DM closes a review item and records decision
es_set_review_deadline      — set or update deadline on a review item
```

### Phase 2H Handlers (Wireframe Annotation — build later)

```
es_add_annotation           — add a pin + comment to a wireframe
es_delete_annotation        — delete own pin (cannot delete others')
es_get_annotations          — load all pins for a wireframe image
```

### Admin-Only Handlers

```
es_save_template            — create or update a template library entry
es_delete_template          — delete a template
es_reorder_templates        — save new sort_order values
es_upload_wireframe         — attach wireframe image to a project review item
es_create_review_item       — admin creates a new review session for a project
```

---

## CLIENT PORTAL UI — PHASE 2G

### Branding Tab Structure (updated)

The Branding tab in the client portal gains two new sections, in this order:

1. **Template Style Mood Board** — stakeholder preference voting on style templates
2. **Brand Palette Options** — stakeholder preference voting on AI color palettes
3. **Brand Summary** *(existing)* — locked colors, fonts, logo — shown after both above are closed

### Mood Board Section UI

```
┌─────────────────────────────────────────────────────────────────┐
│  Step 1: Choose Your Style Direction                            │
│  ─────────────────────────────────────────────────────────────  │
│  Browse the examples below. Mark anything you like ♥ or        │
│  dislike ✕. Your team has until [DATE] to share preferences.   │
│                                                                  │
│  [Progress bar: 2 of 4 team members responded]                  │
├─────────────────────────────────────────────────────────────────┤
│  MODERN                                                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                      │
│  │  [image] │  │  [image] │  │  [image] │                      │
│  │          │  │          │  │          │                      │
│  │ ♥  —  ✕ │  │ ♥  —  ✕ │  │ ♥  —  ✕ │                      │
│  │ Clean    │  │ Minimal  │  │ Bold     │                      │
│  └──────────┘  └──────────┘  └──────────┘                      │
│                                                                  │
│  CLASSIC                                                         │
│  ┌──────────┐  ┌──────────┐                                     │
│  │  [image] │  │  [image] │                                     │
│  │ ♥  —  ✕ │  │ ♥  —  ✕ │                                     │
│  └──────────┘  └──────────┘                                     │
│                                                                  │
│  [DM only: View Results button — appears when ready]            │
└─────────────────────────────────────────────────────────────────┘
```

**Voted state:** When a stakeholder clicks ♥ or ✕, that icon fills/highlights and the others dim. Clicking again on the selected icon returns to neutral.

**Confirmed state (after DM closes):** All vote controls are hidden. A banner shows: "Style Direction Confirmed ✓ — [Category Name] / [Title]" or multiple if DM selected more than one.

### Brand Palette Section UI

```
┌─────────────────────────────────────────────────────────────────┐
│  Step 2: Choose Your Color Direction                            │
│  ─────────────────────────────────────────────────────────────  │
│  Our AI analyzed your logo and generated these 3 palettes.     │
│  Mark your preference. The Decision Maker will make the         │
│  final choice.                                                  │
│                                                                  │
│  ┌────────────────┐  ┌────────────────┐  ┌────────────────┐   │
│  │  ● ● ●         │  │  ● ● ●         │  │  ● ● ●         │   │
│  │  Option A      │  │  Option B      │  │  Option C      │   │
│  │  [rationale]   │  │  [rationale]   │  │  [rationale]   │   │
│  │  Fonts: ...    │  │  Fonts: ...    │  │  Fonts: ...    │   │
│  │                │  │                │  │                │   │
│  │ [Prefer]       │  │ [Prefer]       │  │ [Prefer]       │   │
│  │ [Neutral]      │  │ [Neutral]      │  │ [Neutral]      │   │
│  │ [Don't Prefer] │  │ [Don't Prefer] │  │ [Don't Prefer] │   │
│  └────────────────┘  └────────────────┘  └────────────────┘   │
│                                                                  │
│  [DM only: Lock Brand Colors button — appears when ready]       │
└─────────────────────────────────────────────────────────────────┘
```

---

## ADMIN SIDE — REVIEW MANAGEMENT

### Where Admins See Review Status

**Project detail → Branding tab (admin view):**
- Shows which review items exist for this project
- Status badge per item: Open / Awaiting Results / Closed
- Deadline display with ability to extend
- "View Results" button for any closed or ready review
- "Create Review Session" button to initiate a new mood board or palette review

**Results view (admin):**
- Table: one row per template/option
- Columns: Template Name, Liked count, Neutral count, Disliked count, Individual breakdown (avatar + vote per stakeholder)
- DM decision highlighted if closed

### Template Library Page

**EL Core admin → Expand Site → Template Library**

- Card grid of all templates grouped by category
- Each card: image preview, title, category badge, active toggle, Edit / Delete buttons
- "Add Template" opens modal with fields: Title, Category (select), Description, Image URL (+ media uploader), Active toggle
- Drag to reorder within category (saves sort_order via AJAX)
- Filter bar: filter by category, active/inactive

---

## NOTIFICATION TRIGGERS (Hook Points — Implement When Notifications Module Exists)

Add these action hooks now so the Notifications module can plug in later. Don't implement email sending in this phase.

```php
// When a review item is created with a deadline
do_action('el_review_item_created', $review_item_id, $project_id, $deadline);

// When a stakeholder submits their vote
do_action('el_review_vote_submitted', $review_item_id, $user_id, $vote_data);

// When deadline is 48 hours away (triggered by cron check)
do_action('el_review_deadline_approaching', $review_item_id, $project_id, $non_voters);

// When DM closes a review and records decision
do_action('el_review_closed', $review_item_id, $project_id, $dm_decision);
```

---

## MODULE.JSON UPDATES

Add to `modules/expand-site/module.json`:

**New database tables** (increment database version):
- `el_es_review_items`
- `el_es_review_votes`
- `el_es_annotations` (create now, populate in Phase 2H)
- `el_es_templates`

**New capabilities:**
- `es_review_content` — stakeholder can vote and annotate (Contributor + DM)
- `es_close_review` — can close a review and record final decision (DM only)
- `es_manage_templates` — can add/edit/delete template library entries (admin only)

**New shortcodes:** None — all review UI is inside `[el_expand_site_portal]` and admin views. No standalone shortcodes needed.

---

## BUILD ORDER

Build in this exact order. Do not skip ahead. Deploy and test each checkpoint before continuing.

### Step 1 — Database (no UI, just schema)
- Create all 4 tables via module.json migration
- Verify tables exist after plugin update
- No visual output — just confirm in database

### Step 2 — Template Library Admin Page
- Admin can add/edit/delete/reorder templates
- Upload images via WP media uploader
- Filter by category
- **Deploy + test:** Add 6 sample templates across 3 categories. Confirm they save and display correctly.

### Step 3 — Mood Board in Client Portal
- Mood board section appears in Branding tab
- Shows ONLY templates selected for this project's review session (loaded from `el_es_review_items.dm_decision.selected_template_ids`)
- Compact card grid (160px image height, vote strip at bottom) — not full-size
- Stakeholders can vote (liked/neutral/disliked) per template, AJAX save on click
- Progress tracker shows who has/hasn't voted
- DM sees "View Results" button after all voted or deadline passes
- DM can close and record style direction
- **NOTE:** Requires a review session to exist for the project. Build Step 5 admin side first (or build together).
- **Deploy + test:** Log in as each stakeholder role, vote on templates, verify DM can view results and close.

### Step 4 — Brand Palette Voting in Client Portal
- Palette section appears after mood board
- Connects to AI palette suggestions stored in `el_core_brand.ai_palette_suggestions`
- Stakeholders vote prefer/neutral/don't prefer per option
- DM closes and locks brand colors
- **Deploy + test:** Generate AI palettes from logo, verify 3 options appear in portal, vote as stakeholders, DM locks palette.

### Step 5 — Admin Review Management
- Project detail Branding tab shows review status
- Admin can create review sessions, set deadlines, view results
- **CREATE SESSION flow includes template picker:** full library grid (active templates only) with checkboxes — admin selects which templates this client sees
- Selected template IDs saved as JSON in `el_es_review_items.dm_decision` field: `{"selected_template_ids": [1, 3, 5, 7]}`
- **Deploy + test:** Create review from admin, select templates, set deadline, verify portal deadline counter updates and only selected templates appear.

### Step 6 — Wireframe Annotation (Phase 2H — separate session)
- Do not build until Steps 1–5 are stable and deployed
- Full spec in Part 3 of this document
- New session, new Cursor prompt when ready

---

## CODING RULES REMINDER

- All admin views use `EL_Admin_UI::*` — no raw HTML tables or forms
- CSS classes for new components: `el-es-review-*` prefix
- JavaScript for portal interactions: add to `expand-site.js`
- JavaScript for admin interactions: add to `admin.js` using `elAdmin.*` namespace
- All AJAX handlers: registered in `init_hooks()` — both priv and nopriv where needed (portal users are always logged in, so priv only for all review actions)
- Shortcode output: returns HTML string, never echoes
- Image position for annotations stored as percentage (not pixels) — device-independent

---

## FILES TOUCHED

| File | Change |
|------|--------|
| `modules/expand-site/module.json` | New tables, capabilities, database version bump |
| `modules/expand-site/class-expand-site-module.php` | New AJAX handlers, helper methods |
| `modules/expand-site/admin/views/template-library.php` | New file — template management page |
| `modules/expand-site/admin/views/project-detail.php` | Branding tab — review session management |
| `modules/expand-site/shortcodes/expand-site-portal.php` | Mood board + palette voting UI |
| `assets/css/expand-site.css` | Review UI styles |
| `assets/js/expand-site.js` | Mood board voting, palette voting, lightbox |
| `admin/js/admin.js` | Template library CRUD, review management |
| `el-core/el-core.php` | Version bump |
| `CHANGELOG.md` | New version entry |

---

## VERSION TARGET

- **v1.20.0** — Steps 1–3 (database + template library + mood board voting)
- **v1.21.0** — Steps 4–5 (palette voting + admin review management)
- **v1.22.0** — Step 6 (wireframe annotation — Phase 2H, separate session)

Note: v1.19.0 is reserved for the Phase 2G branding system (color picker, logo analysis, CSS token expansion). Build that first, then this.
