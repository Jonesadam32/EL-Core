# Next Session Prompt — v1.32.0 (Proposal + Portal Fixes)

> **Created:** March 8, 2026
> **Start with:** Read @START-HERE-NEXT-SESSION.md. I'm working on the Expand Site workstream — v1.32.0 (Proposal overhaul + portal stage content fixes).

---

## Context

We ran a full end-to-end test of v1.31.1 today (Phases 1–4). The pipeline is structurally correct but there are 6 issues to fix before continuing with Phase 4 (User Journey) portal work. All issues are in Phase 3 (Proposal) and the client portal. Fix these in v1.32.0 before resuming Phase 4 Step C.

---

## Bug 1 — "New Proposal" green button inside the proposals card does nothing

**Where:** Admin → Project Detail → Phase 3 panel → the empty state has a green "New Proposal" button inside the data table's empty state action.

**What happens:** Clicking it does nothing. The white "New Proposal" button in the card header (top right) works correctly and opens the create modal.

**Fix:** The empty state action button inside `EL_Admin_UI::data_table()` uses `data-modal-open` but is not wired to the same modal trigger as the header button. Audit `expand-site-admin.js` — the `handleNewProposal` click handler targets `.el-es-new-proposal-btn`. Make sure the empty state button in `project-detail.php` also has that class and the correct `data-project-id` attribute, same as the header action button.

---

## Bug 2 — Proposal payment terms need structured fields + AI should use final price

**Current state:** Payment terms is a single free-text textarea. The AI generates a wall of text. There's no structured breakdown of the 25%/75% split with actual dollar amounts.

**What's needed:**

**Admin proposal edit modal changes (`project-detail.php`):**
- Remove `budget_range_low` and `budget_range_high` fields from the proposal edit modal entirely — clients should only see final price
- Add an `annual_platform_fee` field (number input, labeled "Annual Platform Fee ($)") — this is always part of ELS proposals
- Keep `final_price` as the single price field
- Replace the `payment_terms` free textarea with two structured fields:
  - `first_payment_amount` — number input, labeled "First Payment (25%) Amount ($)" — admin can override, but AI should calculate as 25% of final_price
  - `final_payment_amount` — number input, labeled "Final Payment (75%) Amount ($)" — auto-calculated as 75% of final_price

**DB migration:** Add `annual_platform_fee` DECIMAL(10,2), `first_payment_amount` DECIMAL(10,2), `final_payment_amount` DECIMAL(10,2) columns to `el_es_proposals` via a new DB migration (bump schema version).

**AI proposal generation:** When `final_price` is set, the AI prompt should calculate and include:
- First payment = 25% of final_price (dollar amount)
- Final payment = 75% of final_price (dollar amount)
- Annual platform fee = value from `annual_platform_fee` field
These should be passed to the AI so it writes the Investment section with real numbers, not placeholders.

**Proposal display (admin + portal):** Under the Investment section, show a structured breakdown:
```
Development Investment: $X,XXX
  First Payment (25%): $X,XXX — due upon wireframe approval
  Final Payment (75%): $X,XXX — due upon delivery
Annual Platform Fee: $X,XXX/year
```

---

## Bug 3 — Terms & Conditions renders as one unformatted block

**Current state:** The `terms_conditions` field is stored as plain text with `\n\n` between sections. In the portal proposal view it renders as one big paragraph.

**Fix:** In the portal proposal rendering (`expand-site-portal.php`), when outputting `terms_conditions`, replace double newlines with `</p><p>` and wrap in `<div class="el-es-proposal-terms">`. Each numbered section (lines starting with `1.`, `2.`, etc.) should render as a bold heading followed by the paragraph text. Use `nl2br` + structured parsing, or simply convert `\n\n` to paragraph breaks so it reads as separate clauses instead of one blob.

Also add CSS to `expand-site.css` for `.el-es-proposal-terms` — comfortable line height, section spacing, numbered items styled distinctly.

---

## Bug 4 — Client should be able to download the proposal as a PDF

**What's needed:** A "Download as PDF" button on the portal proposal view.

**Implementation:** Use browser `window.print()` with a print stylesheet — this is the simplest approach that requires no server-side library. Add a "Download PDF" button to the proposal view in the portal. When clicked, it calls `window.print()`. Add `@media print` CSS in `expand-site.css` that:
- Hides everything except the proposal content (nav, header, buttons, stage bar all hidden)
- Sets font to serif, black on white, good margins
- Forces page breaks at major sections
- Shows the ELS logo/letterhead cleanly

The button should have class `el-es-proposal-print-btn` and be in the portal proposal view.

---

## Bug 5 — After proposal is accepted, proposal + scope text still shows in portal main view

**Current state:** After the DM accepts the proposal and the project advances to Phase 4 (User Journey), the portal still shows the full proposal narrative inline in the stage content area. This is cluttering the view.

**What's needed:** 

The accepted proposal should no longer render inline in the stage content area. Instead, add it as a persistent **info card** in the stage cards row (the row that already has Deliverables, Feedback, and Project Definition cards).

**New card: "Proposal"**
- Shows in the cards row for Stage 3 and all later stages
- Greyed out / disabled if no accepted proposal exists yet
- When clicked: opens a modal showing the full accepted proposal (read-only, same letterhead layout as the current portal view, with the Download PDF button)
- Card label: "Proposal", icon: document icon, count/status: "Accepted" badge when accepted, "Pending" when sent but not yet accepted, greyed out when no proposal

**Similarly — "Project Definition" card:**
- Already exists as a card in some stages — audit the portal to confirm it's shown at Stage 3+ and is greyed out when the definition isn't locked yet. If it's only shown at certain stages, make it persistent from Stage 2 onward.

**The stage content area** for Stage 3 (when the project is past Stage 3) should just show a simple "Proposal accepted on [date] by [name]" confirmation line — not the full proposal inline.

---

## Bug 6 — Client portal Stage 4 (User Journey) shows proposal content instead of journey cards

**Current state:** When the project advances to Stage 4 (User Journey) and the DM views the portal, they see the proposal content / Stage 3 content instead of the Phase 4 User Journey cards.

**Root cause:** The portal shortcode (`expand-site-portal.php`) does not yet have a Stage 4 content block. It loops through stages and outputs generic content (deliverables, feedback cards) for every stage. The User Journey phase-specific content (journey cards, guided questions, assignment UI) has not been added to the portal for Stage 4 yet.

**Fix — two parts:**

**Part A (this version):** Add a Stage 4 placeholder block to the portal so the DM sees something meaningful instead of the wrong content. Show:
- A brief intro: "In this phase, each member of your team will describe how a specific type of user moves through your website."
- A list of the journey cards (user type name, assigned stakeholder, status) — read-only for now, matching what's in the DB
- A note: "Your project manager will assign team members to each journey. Check back for updates."

**Part B (Step C, next session):** The full portal Phase 4 panel with guided questions form, AI Round 1 trigger, consensus review UI etc. as specced in `SPEC-USER-JOURNEY-PHASE.md`. Do NOT build Part B in v1.32.0 — just the placeholder so the portal isn't broken.

---

## Build order for v1.32.0

1. **DB migration** — add `annual_platform_fee`, `first_payment_amount`, `final_payment_amount` to `el_es_proposals`
2. **Bug 1** — fix empty-state New Proposal button (quick)
3. **Bug 2** — structured payment fields in modal + AI generation update + proposal display
4. **Bug 3** — T&C formatting in portal
5. **Bug 4** — PDF print button + print CSS
6. **Bug 5** — move proposal to info card, remove inline from stage content
7. **Bug 6** — Stage 4 portal placeholder

Bump to **v1.32.0**, update CHANGELOG, build ZIP, commit, push.

---

## Files that will change

- `el-core/modules/expand-site/admin/views/project-detail.php` — proposal modal fields
- `el-core/modules/expand-site/class-expand-site-module.php` — DB migration, AI prompt, save handler
- `el-core/modules/expand-site/module.json` — new DB migration version
- `el-core/modules/expand-site/shortcodes/expand-site-portal.php` — proposal card, T&C formatting, Stage 4 placeholder
- `el-core/modules/expand-site/assets/js/expand-site-admin.js` — fix New Proposal empty state button
- `el-core/modules/expand-site/assets/css/expand-site.css` — T&C styles, print styles, proposal card styles
