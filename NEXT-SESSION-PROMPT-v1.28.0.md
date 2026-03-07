# Next Chat Prompt — v1.28.0 Admin UX + Bug Fixes

Paste this entire prompt at the start of the next chat:

---

Read @START-HERE-NEXT-SESSION.md and @CURSOR-TODO.md. Current version is v1.27.3 on staging.

We completed testing through section 3F. Sections 3G and 3H surfaced bugs and a major UX problem with the admin project detail page. We need to fix 5 issues before continuing testing.

---

## Issue 1 — Definition revision history (version tracking)

**File:** `el-core/modules/expand-site/admin/views/project-detail.php` (Discovery tab) + `el-core/modules/expand-site/class-expand-site-module.php`

**Problem:** When the admin edits the definition and re-sends for review, there is no way to see what changed between round 1 and round 2. The client also can't see what was updated since their last review.

**Fix:**
- The `el_es_definition_reviews` table already has a `round` column — use it
- Add a "Version History" collapsible section in the Discovery tab admin view that shows each round with a diff (field-by-field comparison between rounds), the deadline, the DM decision, and the DM note
- In the portal review UI, add an "Updated since last round" badge on fields that changed since the previous round (compare current value to the value at the time of the previous closed review)
- Store a snapshot of the definition fields in `el_es_definition_reviews` when a review round is sent (new columns: `snapshot` LONGTEXT JSON) — add via DB migration

---

## Issue 2 — Admin notice to lock the definition after client approval

**File:** `el-core/modules/expand-site/admin/views/project-detail.php` (Discovery tab)

**Problem:** After the DM clicks "Accept", the definition status becomes `approved` but the admin doesn't get any obvious prompt to lock it. The Lock button exists but it's easy to miss — there's no banner drawing attention to the next required action.

**Fix:**
- When `review_status = approved`, render a prominent amber action banner at the top of the Discovery tab: "The client has approved the Project Definition. Lock it now to proceed to the next stage."
- Include a "Lock Definition" button directly in the banner (same action as the existing Lock button)
- This banner should only show when status is `approved` — not `locked`, not `pending_review`

---

## Issue 3 — "Needs attention" projects not appearing in the project list

**File:** `el-core/modules/expand-site/admin/views/project-list.php`

**Problem:** The project list splits projects into "Projects Needing Attention" (flagged or near deadline) and regular projects. But projects where the definition status is `needs_revision` or `approved` (action required from admin) are NOT included in the needs-attention bucket — only deadline-based flagging is checked.

**Fix:**
- Extend the needs-attention query to also include:
  - Projects where `el_es_project_definition.review_status = 'approved'` (admin needs to lock)
  - Projects where `el_es_project_definition.review_status = 'needs_revision'` (admin needs to revise and re-send)
- Add appropriate badge labels for these cases in the project list row (e.g., "Client Approved — Lock Required", "Needs Revision")
- The existing `flagged_at` / deadline logic stays as-is; this is additive

---

## Issue 4 — After clicking "Looks good" or "Needs revision", must refresh to add a comment

**File:** `el-core/modules/expand-site/assets/js/expand-site.js`

**Problem:** The verdict button handler (around line 667) calls `ELCore.ajax('es_field_verdict', ...)` optimistically — it updates the active state visually but does NOT re-render the field block. The `+ Add comment` form relies on the correct `review.id` being bound to the event handler. After a verdict is clicked, something in the bound state goes stale and the comment toggle stops working until the full page reloads via `loadReview()`.

**Fix:**
- After a verdict AJAX call succeeds, call `loadReview()` to re-render the full review UI (same as the comment post handler does). This ensures the comment buttons are always bound to fresh state.
- Alternatively, ensure the `review` variable captured in the `bindDefinitionReviewEvents` closure is not going stale — but the cleanest fix is to just reload on verdict success.

---

## Issue 5 — Admin project detail page UX redesign (major)

**File:** `el-core/modules/expand-site/admin/views/project-detail.php`

**Problem (as described by Fred):**
> "With Overview, Stakeholders, and all on the same tabs, I don't know which stage I'm in — that's very confusing. The UX design is really bad. It should mirror the steps. I also don't see 'Needs Revision' anywhere after the DM submits a needs-revision decision."

**Root cause:**
- The current tab layout is flat — 9 tabs all visible at once regardless of the project's current stage. This makes Stage 1 content (Discovery) look equal to Stage 4 content (Style Direction) even when you're in Stage 1.
- The definition `review_status` (draft / pending_review / approved / needs_revision / locked) is buried inside the Discovery tab with no visible indicator at the page level.
- After a DM decision, the admin has to know to click into the Discovery tab to see the outcome.

**Proposed redesign — discuss and confirm before building:**

The project detail page should have TWO layers:

**Layer 1 — Stage Progress Bar (always visible at top)**
- A horizontal stepper showing all 8 stages, with the current stage highlighted
- Each stage shows its name and a status indicator (complete / current / upcoming)
- Clicking a completed or current stage scrolls/switches to that stage's content panel

**Layer 2 — Stage-contextual content below**
- The content area shows tabs that are RELEVANT to the current stage:
  - Stage 1 (Discovery): Discovery tab is prominent, Stakeholders always visible
  - Stage 2 (Site Definition): Definition review status card is prominent
  - Stage 3 (Scope Lock): Proposals tab is prominent
  - Stage 4+ (Style Direction, etc.): Branding, Pages tabs come forward
- Tabs that aren't relevant to the current stage are still accessible but visually de-emphasized (gray/secondary)

**Status visibility:**
- A "Stage Status" card always visible below the stepper showing: current definition review status, active review deadline, any DM decision with note
- This makes "Needs Revision" visible at a glance without clicking into Discovery tab

**What to build in v1.28.0:**
1. Add the 8-stage horizontal stepper to the top of project-detail (below the page header, above tabs)
2. Add a "Current Stage Status" card that surfaces the most important action item (definition review status, DM decision + note, lock prompt)
3. Reorder tabs so the most relevant tab for the current stage is auto-activated when the page loads
4. Keep all existing tabs — no functionality removed

**What to defer (v1.29.0+):**
- Full stage-gated tab hiding
- Stage-specific content panels replacing the tab metaphor entirely

---

## Build order for this session

Fix issues 4, 3, 2 first (quick code fixes), then tackle the Issue 5 redesign (larger), then Issue 1 (definition versioning — requires DB migration).

All fixes go into v1.28.0 unless a DB migration is needed, in which case versioning is split.

Do not build the ZIP until all fixes for a given version are done.

---

## Key files
- `el-core/modules/expand-site/admin/views/project-detail.php` — admin project detail (Issues 2, 5)
- `el-core/modules/expand-site/admin/views/project-list.php` — project list (Issue 3)
- `el-core/modules/expand-site/assets/js/expand-site.js` — portal JS (Issue 4)
- `el-core/modules/expand-site/class-expand-site-module.php` — AJAX handlers (Issues 1, 3)
- `el-core/modules/expand-site/assets/css/expand-site.css` — styles
- `el-core/modules/expand-site/assets/css/expand-site-admin.css` (or admin.css) — admin styles
