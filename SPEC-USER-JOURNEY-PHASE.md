# Spec: User Journey Phase (Phase 4)

> **Status:** Finalized — March 8, 2026
> **Planned version:** v1.31.0
> **Session type:** Planning only — no code written this session
> **Next action:** Build v1.31.0 from this spec

---

## Overview

The User Journey phase is inserted as **Phase 4** of the Expand Site pipeline, between Proposal (Phase 3) and Visual Identity (formerly Phase 4, now Phase 5). This shifts Visual Identity through Delivery each back by one, making it a **9-phase pipeline**.

**Why this phase exists:** After the proposal is accepted, the team needs to understand how each type of user will actually move through the site before any design work begins. Without mapping this, design has no foundation. This phase captures that knowledge from the client's stakeholders, uses AI to structure it into a workflow, refines it through admin editing and another AI pass, then validates it through full team consensus before locking.

---

## Pipeline Change

### Updated STAGES constant (class-expand-site-module.php)

| # | Name | Slug | has_client_gate | Deadline Days |
|---|------|------|-----------------|---------------|
| 1 | Qualification | qualification | true | 3 |
| 2 | Discovery | discovery | true | 7 |
| 3 | Proposal | proposal | true | 5 |
| **4** | **User Journey** | **user-journey** | **true** | **7** |
| 5 | Visual Identity | visual-identity | true | 10 |
| 6 | Wireframes | wireframes | true | 10 |
| 7 | Final Design | final-design | true | 10 |
| 8 | Build | build | false | 14 |
| 9 | Delivery | delivery | true | 7 |

### Files that reference the STAGES constant or phase numbers

When building v1.31.0, audit these files for hardcoded phase numbers (4–8) that need shifting to 5–9:

- `class-expand-site-module.php` — STAGES constant, STAGE_DEADLINE_DAYS constant
- `admin/views/project-detail.php` — phase panel IDs, tab references
- `expand-site-admin.js` — any hardcoded phase numbers in tab activation logic
- `shortcodes/expand-site-portal.php` — stage number checks for portal rendering

---

## Database Schema

### DB migration version: 9 → 10

Add all three tables in migration "10" in `module.json`.

---

### Table: `el_es_user_journeys`

One row per user type per project.

```sql
CREATE TABLE {prefix}el_es_user_journeys (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id   BIGINT UNSIGNED NOT NULL,
    user_type    VARCHAR(100) NOT NULL,
    added_by     BIGINT UNSIGNED NULL,
    assigned_to  BIGINT UNSIGNED NULL,
    guided_answers LONGTEXT NULL,
    ai_workflow    LONGTEXT NULL,
    admin_notes    TEXT NULL,
    admin_workflow LONGTEXT NULL,
    status       VARCHAR(30) NOT NULL DEFAULT 'pending_assignment',
    locked_at    DATETIME NULL,
    locked_by    BIGINT UNSIGNED NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_project (project_id),
    INDEX idx_assigned (assigned_to)
)
```

**Column notes:**

- `user_type` — pulled from `el_es_project_definition.user_types` on phase activation, or manually entered by admin/DM
- `added_by` — NULL means auto-generated from the definition list; a user_id means a human added it manually
- `assigned_to` — WP user_id of the stakeholder responsible for the initial 5-question input; DM assigns and can reassign at any time (overwrites same column)
- `guided_answers` — JSON array: `[{"question": "...", "answer": "..."}, ...]` — one object per question
- `ai_workflow` — JSON object (see AI Output Shape below) — output of Round 1 AI call
- `admin_notes` — free-text admin instructions for the AI refinement call ("add a branch for password reset", etc.)
- `admin_workflow` — JSON object (same shape as ai_workflow) — output of Round 2 AI call, the admin-refined version
- `status` — see Status State Machine below

**Status enum values:**

| Value | Meaning |
|-------|---------|
| `pending_assignment` | Row exists, no stakeholder assigned yet |
| `awaiting_input` | Stakeholder assigned, waiting for them to submit 5 answers |
| `ai_generated` | Round 1 AI has run, raw workflow exists, admin needs to refine |
| `admin_refined` | Admin has run Round 2 AI, workflow is ready to send for review |
| `in_review` | Sent to portal for consensus review |
| `approved` | DM has approved, waiting for admin to lock |
| `locked` | Fully locked — contributes to Phase 5 gate |

---

### Table: `el_es_journey_reviews`

One row per review round per journey. Mirrors `el_es_definition_reviews`.

```sql
CREATE TABLE {prefix}el_es_journey_reviews (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journey_id    BIGINT UNSIGNED NOT NULL,
    round         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    sent_by       BIGINT UNSIGNED NOT NULL,
    deadline      DATETIME NULL,
    status        VARCHAR(20) NOT NULL DEFAULT 'open',
    dm_decision   VARCHAR(20) NULL,
    dm_note       TEXT NULL,
    dm_decided_at DATETIME NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_journey (journey_id)
)
```

- `status` — `open` / `closed`
- `dm_decision` — `approved` / `needs_revision` / NULL

---

### Table: `el_es_journey_comments`

Threaded per-step comments with verdicts. Mirrors `el_es_definition_comments`.

```sql
CREATE TABLE {prefix}el_es_journey_comments (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    review_id  BIGINT UNSIGNED NOT NULL,
    journey_id BIGINT UNSIGNED NOT NULL,
    step_key   VARCHAR(50) NULL,
    parent_id  BIGINT UNSIGNED NULL DEFAULT 0,
    user_id    BIGINT UNSIGNED NOT NULL,
    comment    TEXT NOT NULL,
    verdict    VARCHAR(20) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_review (review_id),
    INDEX idx_journey (journey_id)
)
```

- `step_key` — the step `id` from the workflow JSON that this comment is anchored to (e.g. `"step_2"`); NULL for overall journey comments
- `verdict` — `approved` / `needs_revision` / NULL (NULL = comment only, no verdict)

---

## The 5 Guided Questions

Rendered in the portal for the assigned stakeholder. Each question has a visible "For example:" line that stays on screen while they type — not placeholder text inside the field.

---

**Question 1 — Entry point**

> How does this person first find or arrive at the website?

*For example: They search Google for our services and click a result, or a teacher sends them a link, or they scan a QR code from a flyer.*

---

**Question 2 — First action**

> Once they land on the site, what is the first thing they need to do?

*For example: Find out what programs are available, contact someone for more information, or sign up for an account.*

---

**Question 3 — Account / login**

> Do they need to create an account or log in to use the site — or can they get what they need without one?

*For example: They can browse without an account but need to register to enroll. Or they always need to log in first because the content is private.*

---

**Question 4 — Success state**

> What does success look like for this person — what have they accomplished when they leave the site happy?

*For example: They signed up for a program, they found the schedule they needed, or they submitted a contact form and got a confirmation.*

---

**Question 5 — Restrictions / frustrations to prevent**

> Is there anything this person should NOT be able to do, or any frustration you want to prevent?

*For example: They should not be able to see other users' information. They should not get lost trying to find the registration button.*

---

## AI Workflow — Round 1

**Trigger:** Automatic, fires immediately after the assigned stakeholder submits their 5 answers.

**Input to AI prompt:**

- Project site description (from locked definition)
- Project primary goal (from locked definition)
- Site type (from locked definition)
- User type name (e.g. "Student")
- All 5 question/answer pairs

**Prompt intent:** "You are a UX designer reading a client's description of how a specific user type interacts with their website. Based on this information, produce a structured user journey workflow."

**Output — structured JSON shape:**

```json
{
  "summary": "A student arrives via Google search, creates a free account, browses available after-school programs, and enrolls in one.",
  "steps": [
    {
      "id": "step_1",
      "label": "Arrives on Homepage",
      "description": "User lands via Google search result for after-school programs",
      "branch": null
    },
    {
      "id": "step_2",
      "label": "Account check",
      "description": "System detects whether the user has an existing account",
      "branch": {
        "condition": "Has account?",
        "yes": "step_3a",
        "no": "step_3b"
      }
    },
    {
      "id": "step_3a",
      "label": "Log In",
      "description": "Returning user logs in and proceeds to Program Catalog",
      "branch": null
    },
    {
      "id": "step_3b",
      "label": "Register",
      "description": "New user creates a free account",
      "branch": null
    },
    {
      "id": "step_4",
      "label": "Browse Program Catalog",
      "description": "User views available programs filtered by age group or location",
      "branch": null
    },
    {
      "id": "step_5",
      "label": "Enroll in Program",
      "description": "User completes enrollment form and receives confirmation",
      "branch": null
    }
  ],
  "implied_pages": [
    "Homepage",
    "Login",
    "Registration",
    "Program Catalog",
    "Program Detail",
    "Enrollment Form",
    "Enrollment Confirmation"
  ],
  "open_questions": [
    "It is unclear whether users self-select their user type during registration or are assigned one by an admin.",
    "The client did not specify whether enrollment requires payment at this stage."
  ]
}
```

**After Round 1:** Journey status advances from `awaiting_input` to `ai_generated`. The admin sees the raw output in the project-detail Phase 4 panel.

---

## AI Workflow — Round 2 (Admin Refinement)

**Trigger:** Admin-initiated. Admin types notes/instructions into a free textarea, then clicks "Refine with AI."

**Input to AI prompt:**

- Everything from Round 1 (project context + user type + guided answers)
- The existing `ai_workflow` JSON from Round 1
- `admin_notes` (admin's free-text instructions — e.g. "Add a branch for users who forgot their password", "Registration should happen before browsing", "Add a step for email verification")

**Prompt intent:** "You are a UX designer refining a user journey workflow based on additional instructions from the project manager. Produce a more detailed, complete version that resolves the open questions where possible and incorporates the admin's notes."

**Output:** Same JSON shape as Round 1, stored in `admin_workflow`. Should have more steps, resolved branches, and fewer (ideally zero) open questions.

**Admin can run Round 2 multiple times.** Each run overwrites `admin_workflow`. The `ai_workflow` (Round 1 output) is never overwritten.

**After Round 2:** Journey status advances to `admin_refined`.

---

## Visual Flow Diagram

**Rendered from:** The step JSON in `admin_workflow` (or `ai_workflow` if `admin_workflow` is null).

**Format:** Mermaid flowchart syntax, generated client-side from the step data by a JS helper function. Rendered using the Mermaid JS library (loaded from CDN or bundled).

**Where it appears:**
- Admin panel: shown in the `admin_refined` and later states, below the workflow JSON display
- Portal: shown during `in_review`, `approved`, and `locked` states

**Read-only for everyone.** Neither admin nor portal users can drag, edit, or manipulate the diagram directly. Editing is done through the admin notes + Round 2 AI process.

**Mermaid generation logic (pseudocode for the build session):**

```
flowchart TD
  For each step in steps[]:
    Add node: step.id["step.label\nstep.description"]
    If step.branch is null:
      Connect to next sequential step
    If step.branch is not null:
      Add decision diamond: step.id + "_branch"{"branch.condition"}
      Connect step → diamond
      Connect diamond →|"Yes"| branch.yes step
      Connect diamond →|"No"| branch.no step
```

---

## Status State Machine

```
pending_assignment
    ↓ DM assigns a stakeholder
awaiting_input
    ↓ Assigned person submits 5 answers → AI Round 1 fires automatically
ai_generated
    ↓ Admin types notes + clicks "Refine with AI" → AI Round 2 fires
admin_refined
    ↓ Admin clicks "Send for Review" (sets deadline)
in_review
    ↓ DM clicks "Accept" in portal
approved
    ↓ Admin clicks "Lock"
locked ✓
```

**Escape hatch:** Admin can reset from `in_review` back to `admin_refined` at any time (same pattern as definition "Reset to Draft"). This cancels the active review round and returns the journey to admin control.

**DM "Needs Revision" path:** If DM clicks "Needs Revision" during consensus, the review stays open (status remains `in_review`), DM revision banner shown in portal, stakeholders can continue adding comments. Admin can reset to `admin_refined` to make changes and re-send.

---

## Admin Panel — Phase 4 in project-detail.php

### Panel header

- Title: "User Journey"
- Progress badge: "X of Y journeys locked" — amber if incomplete, green if all locked
- Phase 5 gate notice: "Visual Identity will unlock once all journeys are locked." (shown until all locked)
- "Add User Type" button — opens a small modal with a text input; saves a new row to `el_es_user_journeys` with `added_by = current user ID` and `status = pending_assignment`

### Journey cards (collapsed list)

Each card shows:
- User type name (bold)
- Assigned stakeholder name, or "Unassigned" in muted text
- Status badge (color-coded: grey=pending, blue=awaiting, yellow=ai_generated, orange=admin_refined, purple=in_review, green=approved/locked)
- Click anywhere on card to expand

### Journey card — expanded states

**`pending_assignment`**
- Stakeholder assignment dropdown (lists all project stakeholders)
- "Assign" button — saves `assigned_to`, advances status to `awaiting_input`

**`awaiting_input`**
- Shows assigned stakeholder name
- "Waiting for [Name] to complete their input."
- Option to reassign: "Reassign to a different team member" link → shows dropdown again

**`ai_generated`**
- Section: "What the team described" — displays the 5 Q&A pairs in read-only format
- Section: "AI-generated workflow" — displays summary, numbered step list with branch notation, implied pages list, open questions list (highlighted in amber if any exist)
- Rendered Mermaid diagram from `ai_workflow`
- "Admin Notes" textarea — labeled "Instructions for AI refinement. Describe what to add, change, or clarify."
- "Refine with AI" button — fires AJAX call, runs Round 2, updates `admin_workflow` and status
- Loading state while AI runs: spinner, "Refining workflow..."

**`admin_refined`**
- Section: "Refined workflow" — displays `admin_workflow` summary, steps, implied pages, open questions
- Rendered Mermaid diagram from `admin_workflow`
- "Admin Notes" textarea still present — admin can run "Refine with AI" again
- "Send for Review" button — opens deadline date picker modal, on confirm sends to portal, advances status to `in_review`, creates `el_es_journey_reviews` row

**`in_review`**
- Shows active review round info: sent date, deadline, round number
- Displays `admin_workflow` + diagram (read-only)
- Comments panel: all comments from `el_es_journey_comments` grouped by `step_key`
- DM verdict status: "DM has not decided yet" / "DM approved" / "DM requested revisions"
- "Reset to Draft" button — cancels active review, resets status to `admin_refined`

**`approved`**
- Green "Client Approved" badge
- Amber banner: "This journey has been approved. Lock it to advance the phase."
- "Lock Journey" button — sets `locked_at`, `locked_by`, advances status to `locked`

**`locked`**
- Locked workflow display (read-only) — summary, steps, implied pages, diagram
- "Locked" badge with lock icon and timestamp

---

## Portal Panel — Phase 4 in expand-site-portal.php

### Layout

All user type cards are visible to all stakeholders at all times. What changes per state is what's inside the card — never card visibility.

The panel opens with a brief intro:

> "In this phase, each member of your team will describe how a specific type of user moves through your website. This helps us design a site that works for everyone who uses it."

### Per card — all states

Card header always shows:
- User type name
- Assigned stakeholder name (or "Unassigned")
- Status indicator dot

Card body varies by state and viewer:

---

**`pending_assignment`** — all viewers

> "Waiting for the Decision Maker to assign a team member to this journey."

DM additionally sees: "Assign" dropdown + button.

---

**`awaiting_input`** — viewer IS the assigned person

Full 5-question form:

- Intro line: "You've been asked to describe the journey for: **[User Type]**. Answer each question in your own words — there are no wrong answers."
- Question 1 through 5, each rendered as:
  - Bold question text
  - Italic "For example:" line (always visible, never disappears)
  - Textarea input
- "Submit" button — disabled until all 5 fields have at least one character
- On submit: loading state ("Generating workflow..."), then success message ("Thank you! Our team will review and build out this workflow.")

DM additionally sees at top of card: "Reassign" link → dropdown.

---

**`awaiting_input`** — viewer is NOT the assigned person

> "Waiting for [Assigned Name] to complete this journey."

DM additionally sees: "Reassign" link.

---

**`ai_generated`** / **`admin_refined`** — all viewers

> "Our team is reviewing and building out this workflow. Check back soon."

Subtle in-progress indicator (spinner or animated dots). No workflow content shown to clients at this stage — admin is still working.

---

**`in_review`** — all viewers

Full consensus UI:

- Section header: "Please review the workflow below and share your input."
- Summary sentence (from `admin_workflow.summary`)
- Rendered Mermaid flow diagram
- Numbered step list — each step rendered as:
  - Step label + description
  - Thread of existing comments for this step (nested replies, same pattern as definition comments)
  - "Add comment" toggle → inline textarea + "Post" button
  - Verdict buttons: "✓ Looks good" / "⚑ Flag for changes" (single selection per step, persists across page loads)
- Overall comment box at bottom (not anchored to a step — `step_key = NULL`)
- "Submit My Input" button (Contributors)
- DM decision section (DM only, visually distinct at bottom):
  - "Make Final Decision" heading
  - "Accept" button + "Needs Revision" button
  - Optional note textarea
  - On Needs Revision: review stays open, DM revision banner appears at top of card

**Scroll-depth gate:** "Submit My Input" button is disabled until the stakeholder has scrolled past the full step list (same pattern as definition approve button).

---

**`approved`** / **`locked`** — all viewers

- Read-only display of summary, step list, and rendered diagram
- Green "Approved" or locked badge
- If `locked`: lock icon + "This journey has been finalized."

---

### DM assignment UX

The DM sees an "Assign team member" control on each card whenever `status = pending_assignment` or `status = awaiting_input`. It renders as a dropdown of all project stakeholders (Contributors + DM). On save, fires AJAX, updates `assigned_to` and status.

---

## AJAX Handlers (to be registered in class-expand-site-module.php)

All handlers follow the existing `el_core_action` pattern with `action` field in POST data.

| Action | Who can call | Description |
|--------|-------------|-------------|
| `es_init_user_journeys` | Admin | Seeds journey rows from `user_types` on definition; called when Phase 4 first activated |
| `es_add_user_type` | Admin | Adds a new journey row manually |
| `es_assign_journey` | DM (portal), Admin | Sets `assigned_to` on a journey row |
| `es_submit_journey_answers` | Assigned stakeholder | Saves `guided_answers`, triggers AI Round 1, updates status |
| `es_refine_journey` | Admin | Saves `admin_notes`, triggers AI Round 2, saves `admin_workflow`, updates status |
| `es_send_journey_review` | Admin | Creates `el_es_journey_reviews` row, sets deadline, updates status to `in_review` |
| `es_get_journey_review` | Stakeholders (portal) | Returns journey data + active review + comments + verdicts for portal rendering |
| `es_post_journey_comment` | Stakeholders | Posts a comment or reply on a specific step |
| `es_journey_step_verdict` | Stakeholders | Upserts verdict for a specific step (approved / needs_revision) |
| `es_dm_journey_decision` | DM | Submits final decision (approved / needs_revision) on active review |
| `es_reset_journey_review` | Admin | Cancels active review, resets status to `admin_refined` |
| `es_lock_journey` | Admin | Sets `locked_at`, `locked_by`, status to `locked` |

`nopriv` variants needed for all portal-facing handlers (stakeholders are logged-in WP users but may not have `manage_expand_site`).

---

## Pipeline Gating Rule

**Phase 5 (Visual Identity) is hard-gated.**

The "Advance to Visual Identity" button in the admin is:
- **Disabled** with tooltip: "X journeys must be locked before advancing." — as long as any journey for the project has `status != 'locked'`
- **Enabled** only when every row in `el_es_user_journeys` for the project has `status = 'locked'`

No admin override. If a stakeholder goes quiet, the DM handles it through the consensus system (silence = abstention, DM decides, admin locks).

---

## Phase Initialization

When the admin advances a project from Phase 3 (Proposal) to Phase 4 (User Journey), the system should automatically:

1. Read `el_es_project_definition.user_types` for the project (JSON or comma-separated list)
2. Parse out the individual user type names
3. Insert one row into `el_es_user_journeys` per user type with `status = 'pending_assignment'` and `added_by = NULL`

This happens in the `es_advance_stage` AJAX handler (or equivalent stage-advance logic), gated on `$new_stage === 4`.

If the definition has no `user_types` value, insert a single placeholder row with `user_type = 'General User'` so the phase is never empty.

---

## CSS / JS Notes for Build Session

- New CSS classes use `el-es-` prefix, added to `expand-site.css`
- New admin JS in `expand-site-admin.js`
- New portal JS in `expand-site.js`
- Mermaid JS: load from CDN (`https://cdn.jsdelivr.net/npm/mermaid/dist/mermaid.min.js`) only on pages where journey diagrams are rendered — enqueue conditionally in `enqueue_portal_assets()` and `enqueue_admin_assets()` when project is in phase 4+
- Mermaid init: `mermaid.initialize({ startOnLoad: false })` then call `mermaid.run()` after diagram HTML is injected into the DOM

---

## Build Order for v1.31.0

Recommended sequence to minimize risk:

1. **DB migration (v10)** — add 3 tables to `module.json`, write migration, deploy and verify tables created
2. **STAGES constant update** — add User Journey as phase 4, shift phases 5–9, update STAGE_DEADLINE_DAYS
3. **Phase initialization logic** — `es_advance_stage` seeds journey rows from definition
4. **Admin Phase 4 panel** — static HTML first (pending_assignment state only), then add states one by one
5. **Admin AJAX handlers** — assign, add user type, refine, send for review, reset, lock
6. **AI Round 1** — submit answers handler + AI call + response parsing
7. **AI Round 2** — admin refine handler + AI call + response parsing
8. **Mermaid diagram rendering** — JS helper, admin side first
9. **Portal Phase 4 panel** — all states, guided questions form, consensus UI
10. **Portal AJAX handlers** — get review data, post comment, verdict, DM decision
11. **Pipeline gate** — disable Advance button until all locked
12. **CSS** — all new `el-es-` classes

---

## Open Questions (Deferred — do not block build)

- Should the `implied_pages` list from locked journeys feed into the Wireframes phase (Phase 6) as a starting page list? Likely yes — revisit when building Phase 6.
- Should there be email notifications when a journey is assigned to a stakeholder? Deferred to a notifications pass.
- Version history for journeys (like definition snapshots)? Not required for v1.31.0 — can add later.
