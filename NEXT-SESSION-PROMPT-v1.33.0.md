# Next Session Prompt — v1.33.0 (User Journey Phase — Assignment Flow Redesign)

> **Created:** March 9, 2026
> **Start with:** Read @START-HERE-NEXT-SESSION.md and @NEXT-SESSION-PROMPT-v1.33.0.md and @SPEC-USER-JOURNEY-PHASE.md. I'm working on the Expand Site workstream — v1.33.0 (User Journey assignment flow + portal Step C).

---

## Current State

**Deployed:** v1.32.1 on staging — tested and working.

**What was built in v1.31.0 (Phase 4 foundation):**
- DB tables: `el_es_user_journeys`, `el_es_journey_reviews`, `el_es_journey_comments` (migration v10)
- 9-phase pipeline with User Journey as Phase 4
- Admin Phase 4 panel with full journey card UI (all 7 status states)
- AJAX handlers: assign, send for review, reset, lock, add user type, refine with AI
- Admin JS: full User Journey IIFE block

**What was built in v1.32.0–1.32.1 (portal fixes):**
- Stage 4 portal currently shows a **read-only placeholder** — journey cards with status badges, intro text, and a note saying the PM will be in touch
- This placeholder is intentionally incomplete — the full portal UI is the task for this session

---

## Critical Design Decision Made This Session

**The assignment flow is wrong in the current code. Here is the correct flow:**

### Correct Flow (to be built in v1.33.0):

**Step 1 — Admin cleans the user type list (before DM sees anything)**
- When the project advances to Stage 4, `init_user_journeys()` seeds one `el_es_user_journeys` row per user type from the locked definition
- **Problem:** The definition's `user_types` field may contain combined entries like "Students and Teachers" as one row. These need to be split into individual rows.
- **Admin task:** Before the DM sees the list, the admin reviews it in the Phase 4 panel. They can:
  - Edit a user type name (e.g. rename "Students and Teachers" → "Students")
  - Add a missing user type
  - Delete a duplicate or irrelevant row
  - Mark the list as "Ready to send to client"
- The admin does NOT assign team members — that is the DM's job

**Step 2 — DM assigns their team members to each user type (in the portal)**
- Once the admin marks the list ready, the DM sees it in the portal
- For each user type, the DM selects which member of their team will answer the 5 guided questions for that user type
- The assignable people are the project's stakeholders (already in `el_es_stakeholders` for this project)
- DM can reassign at any time before the stakeholder submits their answers

**Step 3 — Assigned stakeholder fills out the 5 guided questions (in the portal)**
- The assigned stakeholder sees their journey card in the portal
- They fill out 5 guided questions (spec in `SPEC-USER-JOURNEY-PHASE.md`)
- On submit, status advances to `ai_generated` and Round 1 AI runs

**Step 4 — Admin refines (already built in admin panel)**
- Admin sees AI output, adds notes, runs Round 2 AI, sends for review
- This part is already built

**Step 5 — Full consensus review (portal)**
- Already built for definition review — same pattern applies

---

## What Needs to Be Built in v1.33.0

### Part A — Fix `init_user_journeys()` to seed one row per user type

The current code reads `user_types` from the definition. If this field is a comma-separated string, split it properly into individual rows. Each row = one user type. No combined entries.

**Current behavior to check:** Open `init_user_journeys()` in `class-expand-site-module.php` — it already splits on commas and JSON arrays. Verify it handles all cases:
- JSON array: `["Students", "Teachers", "Event Facilitators"]` → 3 rows ✓
- Comma-separated: `"Students, Teachers, Event Facilitators"` → 3 rows (need to verify)
- Free text with "and": `"Students and Teachers"` → seeds as ONE row "Students and Teachers" (this is expected — admin cleans it)

The "and" case is intentional — admin sees it and splits it manually. The code doesn't need to auto-split on "and" — that's too risky (e.g. "Search and Filter" is one user type).

### Part B — Admin "Ready to Send" gate

Currently there is no concept of the admin approving the list before the DM sees it. Need to add:

1. A `list_approved` flag on the project (or a phase-level gate) — simplest: a boolean option stored in `el_es_user_journeys` metadata, or just use the admin Phase 4 panel header action
2. **In the admin Phase 4 panel:** Add a "Send List to Client" button in the panel header that:
   - Sets a flag indicating the list is ready for the DM
   - Only appears when at least 1 journey row exists and none are in draft state
3. **In the portal Stage 4:** Only show the assignment UI to the DM if this flag is set. Before that, show "Your project manager is finalizing the list of user types. You'll be notified when it's ready."

**Simplest implementation:** Add a column `dm_list_ready TINYINT(1) DEFAULT 0` to `el_es_user_journeys`... actually better: store it at the project level. Use a new column on `el_es_projects` OR just use an existing mechanism. **Recommended:** Add `journey_list_approved_at DATETIME NULL` to `el_es_projects` via a DB migration. When admin clicks "Send List to Client", set this timestamp.

### Part C — Portal Stage 4: DM Assignment UI

Replace the current read-only placeholder with a real interactive UI. The DM sees:

**When `journey_list_approved_at` is NULL:**
> "Your project manager is reviewing the list of user types and will notify you when it's ready for your team's input."

**When `journey_list_approved_at` is set:**
Show the journey list. For each row:
- User type name (e.g. "Students")
- If `assigned_to` is NULL: a dropdown of project stakeholders + an "Assign" button
- If `assigned_to` is set: the assigned person's name + a "Reassign" link
- Status badge (Awaiting Input / In Progress / Submitted / Complete)

The assignment dropdown pulls from `el_es_stakeholders` for this project (already available in the portal).

On "Assign" click → AJAX call to a new handler `es_dm_assign_journey` → sets `assigned_to`, advances status from `pending_assignment` → `awaiting_input`.

**Important:** The DM can only assign/reassign when status is `pending_assignment` or `awaiting_input`. Once the stakeholder has submitted, the DM cannot reassign.

### Part D — Portal Stage 4: Stakeholder Guided Questions Form

When a stakeholder is assigned to a journey, they see their journey card in the portal. It should show:
- The user type name
- 5 guided questions with example text (spec in `SPEC-USER-JOURNEY-PHASE.md` — Question 1–5)
- A textarea for each question
- A "Submit My Input" button

On submit → AJAX `es_submit_journey_answers` → saves `guided_answers` JSON, fires Round 1 AI, advances to `ai_generated`.

**Note:** The guided questions form already exists in the spec (`SPEC-USER-JOURNEY-PHASE.md` sections "The 5 Guided Questions" and "AI Workflow — Round 1"). Follow that spec exactly.

---

## DB Changes Needed (DB migration v12)

```sql
ALTER TABLE el_es_projects ADD COLUMN journey_list_approved_at DATETIME NULL;
```

That's the only new column needed. Everything else uses existing tables.

---

## New AJAX Handlers Needed

| Action | Handler | Who calls it | What it does |
|--------|---------|-------------|-------------|
| `es_approve_journey_list` | `handle_approve_journey_list` | Admin | Sets `journey_list_approved_at` on the project |
| `es_dm_assign_journey` | `handle_dm_assign_journey` | DM (portal) | Sets `assigned_to` on a journey row, status → `awaiting_input` |
| `es_submit_journey_answers` | `handle_submit_journey_answers` | Assigned stakeholder (portal) | Saves `guided_answers` JSON, fires Round 1 AI, status → `ai_generated` |

Note: `es_assign_journey` (admin version) already exists from v1.31.0. The new `es_dm_assign_journey` is the portal version — different permission check (must be `es_decision_maker` for this project, not `manage_expand_site`).

---

## Files That Will Change

- `el-core/modules/expand-site/module.json` — DB migration v12
- `el-core/modules/expand-site/class-expand-site-module.php` — DB migration, 3 new AJAX handlers, register hooks
- `el-core/modules/expand-site/admin/views/project-detail.php` — Add "Send List to Client" button to Phase 4 panel header
- `el-core/modules/expand-site/assets/js/expand-site-admin.js` — Handler for "Send List to Client" button
- `el-core/modules/expand-site/shortcodes/expand-site-portal.php` — Full Stage 4 portal replacement (DM assignment UI + stakeholder questions form)
- `el-core/modules/expand-site/assets/css/expand-site.css` — New Stage 4 portal styles (assignment UI, questions form)

---

## Build Order for v1.33.0

1. **DB migration v12** — add `journey_list_approved_at` to `el_es_projects`
2. **PHP: `handle_approve_journey_list`** — admin marks list ready
3. **PHP: `handle_dm_assign_journey`** — DM assigns a stakeholder to a journey
4. **PHP: `handle_submit_journey_answers`** — stakeholder submits answers, triggers Round 1 AI
5. **Admin panel** — add "Send List to Client" button + JS handler
6. **Portal Stage 4** — replace placeholder with full DM assignment UI + stakeholder questions form
7. **CSS** — styles for the new portal UI
8. Bump to **v1.33.0**, update CHANGELOG, build ZIP, commit, push

---

## What is NOT in scope for v1.33.0

- The consensus review portal UI for journey workflows (Step C in the original spec) — that comes after assignment + questions are working
- Any changes to the admin Phase 4 panel beyond the "Send List to Client" button — that panel is already built

---

## Key Context to Remember

- The `el_es_stakeholders` table has all the project's portal users. The DM assignment dropdown pulls from this.
- `es_decision_maker` capability check for portal AJAX (not `manage_expand_site`)
- Round 1 AI for journey answers already has a handler from v1.31.0 — `handle_refine_journey_ai`. Verify it can be called from the submit handler, or wire it directly into `handle_submit_journey_answers`.
- `SPEC-USER-JOURNEY-PHASE.md` has the full AI prompt shape and the 5 question text — use it verbatim.
