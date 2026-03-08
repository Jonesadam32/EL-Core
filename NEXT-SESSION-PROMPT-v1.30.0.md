# Next Chat Prompt — v1.30.0 Portal Alignment + Definition Workflow Fix

Paste this entire prompt at the start of the next chat:

---

Read @START-HERE-NEXT-SESSION.md and @CURSOR-TODO.md. Current version is v1.29.0, uploaded to staging and visually verified. Before building v1.30.0, read the workflow correction below carefully — it changes what the "Needs Revision" flow means and what needs to be built.

---

## Critical workflow correction — Definition Consensus Review

The current implementation has the wrong mental model for the "Needs Revision" flow. Here is the correct model:

**The client team owns the definition content entirely.** ELS (admin) fills in a first draft from the discovery transcript, but from that point on, it is the client's language for their website. The client team reviews it, discusses it internally, edits it themselves via the inline edit buttons, and ultimately approves it. ELS does not revise the definition on their behalf.

### Correct flow:

1. **Admin** pastes transcript → AI extracts definition → admin reviews the AI output for completeness → clicks "Send to Client for Review"
2. **Client team (contributors)** each review the definition, leave per-field comments flagging things they want changed
3. **Client team members** (any contributor or the DM) can edit the definition fields inline directly in the portal — they fix their own language
4. **Decision Maker** reviews all comments and the final state of the fields, then either:
   - Clicks **"Accept"** — definition is approved, signals to ELS to proceed
   - Clicks **"Needs Revision"** — this means the DM is NOT satisfied yet; another round of internal editing is needed. The review stays open (or a new round begins) so the team can continue editing.
5. **Admin** sees the approved definition and locks it.

### What is wrong in the current build:

The current "Needs Revision" DM decision **closes the review and sets status to `needs_revision`**, which hides the portal review UI and shows only a "Needs Revision" banner. This strands the client — they can't keep editing because the review is closed.

The correct behavior when DM clicks "Needs Revision":
- The review should NOT close
- The definition fields should remain editable in the portal
- The DM's note should be visible to all contributors as context for what to fix
- The deadline can be extended by admin
- Contributors keep editing
- When ready, DM clicks Accept (or admin can manually re-open/extend)

### What needs to change in v1.30.0:

**Portal (`expand-site-portal.php`, `expand-site.js`):**
- When DM clicks "Needs Revision": post the DM note to the review as a comment, but keep the review open (`status` stays `open`, `review_status` stays `pending_review`)
- Show the DM note prominently as a banner above the fields ("Decision Maker requested changes: [note]")
- Definition fields remain editable
- Verdict buttons reset so contributors can re-vote after editing
- DM can still click Accept when satisfied

**Admin (`project-detail.php` Phase 2 panel):**
- Remove the "Needs Revision" state that hides the send button — this state no longer exists as a terminal state
- Admin's only action after send is: wait for DM Accept, or extend deadline, or (escape hatch) reset to draft

**AJAX handler `handle_dm_decision` (`class-expand-site-module.php`):**
- When `decision = needs_revision`: record the note on the review row, do NOT close the review, do NOT change `review_status` on the definition
- When `decision = accepted`: close the review, set `review_status = approved` on the definition (existing behavior — keep this)

---

## What to build in v1.30.0

### Part A — Fix the "Needs Revision" flow (described above)

### Part B — Portal alignment to new phase names

The client portal still uses old stage names (Scope Lock, Review, etc.). Update to match the new STAGES constant.

**Files:**
- `el-core/modules/expand-site/expand-site-portal.php` — stage nav labels
- `el-core/modules/expand-site/assets/js/expand-site.js` — any hardcoded stage name strings

**What to verify:**
- Portal stage nav shows: Qualification, Discovery, Proposal, Visual Identity, Wireframes, Final Design, Build, Delivery
- No references to "Scope Lock" or "Review" remain visible to clients
- Portal gracefully handles stage 1 (Qualification) — show a friendly "Your project is in the early qualification stage" message

### Part C — Admin escape hatch

When a review is in `pending_review` state, admin should have a "Reset to Draft" button that cancels the active review and returns the definition to `draft` status. This is needed if ELS sends by mistake or needs to make significant changes before the client sees it.

---

## Build order for v1.30.0

1. Fix `handle_dm_decision` AJAX handler — "needs_revision" keeps review open
2. Update portal DM decision UI — "Needs Revision" posts note as banner, does NOT close review
3. Update admin Phase 2 panel — remove terminal `needs_revision` state, add "Reset to Draft" escape hatch
4. Align portal stage names to new STAGES constant
5. Add Qualification stage graceful message in portal
6. Bump to v1.30.0, update CHANGELOG, START-HERE, CURSOR-TODO
7. Build ZIP, commit, push
8. Write new testing guide (`V1.30.0-TESTING-GUIDE.md`) covering the corrected review workflow end-to-end
9. Write `NEXT-SESSION-PROMPT-v1.31.0.md`

---

## Testing after v1.30.0

A new testing guide is needed because the v1.28.0 testing guide's section 3G and 4C are based on the wrong workflow model. The new guide should cover:

- Admin sends definition for review
- Contributors leave per-field comments
- DM clicks "Needs Revision" → verify review stays open, DM note banner appears, fields still editable
- Contributor edits a field inline → verify change saves
- DM clicks "Accept" → verify review closes, admin sees "Client Approved" badge and amber lock banner
- Admin locks definition → verify locked state
- Admin "Reset to Draft" escape hatch works
- Portal stage names show correctly (Proposal, not Scope Lock, etc.)
