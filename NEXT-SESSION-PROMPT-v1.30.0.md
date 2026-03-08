# Next Chat Prompt — v1.30.0 Portal Alignment + Testing Resume

Paste this entire prompt at the start of the next chat:

---

Read @START-HERE-NEXT-SESSION.md and @CURSOR-TODO.md. Current version is v1.29.0, built and ready to upload to staging.

We are starting this session with two goals in order:

1. **Upload v1.29.0 to staging and verify the admin project detail page looks correct** — phase bar loads, utility tabs work, all 8 phase panels render, Qualification intake form shows.
2. **Resume testing** using `V1.28.0-TESTING-GUIDE.md` starting at section 3G (DM Final Decision).

If any bugs are found during the v1.29.0 visual check, fix them first as v1.29.1 before resuming the testing guide.

---

## What was built in v1.29.0

The admin project detail page was completely restructured:

- **Flat 9-tab layout removed** — replaced with a two-layer layout
- **Layer 1 — Utility tabs** (always visible at top): Overview, Stakeholders, Stage History
- **Layer 2 — Phase bar** (below utility tabs): 8 horizontally clickable pill buttons. Completed phases show a green checkmark, current phase is highlighted indigo, future phases are gray. Defaults to the current phase on page load.
- **Old stepper** (circles in a row) removed entirely
- **Old Stage Status card** removed

### New 8-phase structure

| # | Phase | Content |
|---|-------|---------|
| 1 | Qualification | Intake form: Project Goal, Discovery Call Date, Call Completed toggle, Internal Notes |
| 2 | Discovery | Former "transcript" tab content verbatim |
| 3 | Proposal | Former "proposals" tab content verbatim (was "Scope Lock") |
| 4 | Visual Identity | Former "branding" tab content verbatim |
| 5 | Wireframes | Pages table (wireframe pages) |
| 6 | Final Design | Same pages list + placeholder notice (content-entry tools coming) |
| 7 | Build | Former "deliverables" tab content verbatim |
| 8 | Delivery | Former "feedback" tab content verbatim + deliverables count summary |

### Stage names changed in the STAGES constant
- `Scope Lock` → `Proposal` (stage 3)
- `Build` shifted to stage 7 (was 6)
- `Review` + `Delivery` merged → `Delivery` at stage 8
- `Final Design` inserted as stage 6 (new)

---

## What to build in v1.30.0 (after testing passes)

### Goal: Align the client portal to the new phase names

The client portal (`expand-site-portal.php`, `expand-site.js`) still uses the old stage names (Scope Lock, Review, etc.) in its stage navigation. These need to be updated to match the new names.

**Files to update:**
- `el-core/modules/expand-site/expand-site-portal.php` — stage navigation labels and any hardcoded stage names
- `el-core/modules/expand-site/assets/js/expand-site.js` — any hardcoded stage name strings
- `el-core/modules/expand-site/assets/css/expand-site.css` — if any stage-specific CSS uses old names

**What to check:**
- Portal stage nav shows: Qualification, Discovery, Proposal, Visual Identity, Wireframes, Final Design, Build, Delivery
- Stage-gated content (e.g., proposals only shown at stage 3+) still works with new numbers
- No references to "Scope Lock" or "Review" remain visible to clients

### Also in v1.30.0: Qualification phase intake form — portal side

Phase 1 (Qualification) has no client-facing interaction. But we should verify the portal gracefully handles a project in stage 1 — it should show a "Your project is in the early qualification stage" message rather than an empty panel.

### After v1.30.0: Resume testing checkpoints

Use `V1.28.0-TESTING-GUIDE.md`:
- **3G — DM Final Decision**: test Accept and Needs Revision from the DM portal view
- **3H — Post-decision states (admin)**: check admin badges and button states after each decision
- **Part 4 — Regression check**: project list, all phase panels, locked definition view, mood board, proposal, invoices

---

## Build order for v1.30.0

1. Review portal stage navigation in `expand-site-portal.php` — find all stage name references
2. Update stage names to match new STAGES constant (pull from `EL_Expand_Site_Module::STAGES`)
3. Check `expand-site.js` for hardcoded stage name strings and update
4. Add a graceful "Qualification stage" message for stage 1 in the portal
5. Bump to v1.30.0, update CHANGELOG, START-HERE, CURSOR-TODO
6. Build ZIP, commit, push
7. Write `NEXT-SESSION-PROMPT-v1.31.0.md` covering: Final Design phase full content-entry UI

Do not touch any AJAX handlers. Do not touch the admin side.
