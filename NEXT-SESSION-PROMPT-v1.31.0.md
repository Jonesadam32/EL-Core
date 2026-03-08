# Next Chat Prompt — v1.31.0

Paste this entire prompt at the start of the next chat:

---

Read @START-HERE-NEXT-SESSION.md and @CURSOR-TODO.md. Current version is v1.30.0, built and ready to upload to staging. Before building v1.31.0, upload v1.30.0 to staging and complete the testing guide (`V1.30.0-TESTING-GUIDE.md`). The testing checklist covers:

1. DM "Needs Revision" flow — verify review stays open, banner appears, fields editable
2. DM "Accept" flow — verify review closes, admin sees approved state
3. Admin "Reset to Draft" escape hatch
4. Portal stage names (Qualification, Discovery, Proposal, etc.)
5. Qualification stage graceful message
6. Regression: project list, phase panels, locked definition, mood board, proposals, invoices

---

## What's ready for v1.31.0 (decide after testing passes)

Once v1.30.0 testing passes, the next priority items are:

### Option A — Continue testing backlog (Checkpoint C from CURSOR-TODO.md)
- Add/remove stakeholders on a live project (Checkpoint C — 2D)
- Test deadline and escalation system (Checkpoint D — 2E)

### Option B — Mood board / brand palette completion (Phase 2G-B Steps 3–4)
- Mood board in client portal is partly built but Step 4 (AI logo analysis + palette voting) and Step 5 (admin review management) are not complete
- Spec: `cursor-prompt-stakeholder-review-system.md`

### Option C — Organizations & Client Management (Phase 2F-E)
- Creates `el_organizations` and `el_contacts` core tables
- Client management admin page, organization autocomplete on project creation
- Spec: `cursor-prompt-organizations.md`

### Option D — Payment Terms & T&C Settings (Phase 2F-D)
- Simple: two textarea settings on Expand Site settings page
- Auto-populates new proposals with defaults
- Low-risk, fast build

Fred decides direction after testing.

---

## Architecture notes (carry forward)

- Expand Site is proprietary — no resale configurability
- Definition consensus: client team owns definition content, DM approves, admin locks
- "Needs Revision" no longer closes the review — it posts a banner and keeps editing open
- `needs_revision` is no longer a terminal `review_status` on the definition row (only `draft`, `pending_review`, `approved`, `locked` are valid)
- DB column `dm_decision` on `el_es_definition_reviews` can hold `needs_revision` when the review is still `open` — that is the new in-progress state
