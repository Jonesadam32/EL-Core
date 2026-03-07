# Next Chat Prompt — v1.29.0 Admin Project Detail Redesign

Paste this entire prompt at the start of the next chat:

---

Read @START-HERE-NEXT-SESSION.md and @CURSOR-TODO.md. Current version is v1.28.0, built and ready to upload to staging.

We are NOT continuing testing yet. We are doing a major redesign of the admin project detail page first, as v1.29.0. A full plan has been agreed and is documented below. Read it carefully before touching any code.

---

## What we agreed in the previous session

The current admin project detail page has a flat list of 9 tabs (Overview, Stakeholders, Discovery, Proposals, Stage History, Deliverables, Pages, Feedback, Branding) all mixed together with no relationship to the project's phases. This is confusing because phase-specific content (Discovery work, Proposal, Branding) is mixed with admin utility content (Stage History, Stakeholders) and there is no visual correspondence between what the admin sees and what the client sees.

The redesign creates two distinct layers:

**Layer 1 — Utility tabs** (admin tools, not phase-specific, always accessible):
- Overview — project notes, budget, dates, change orders
- Stakeholders — who is on the project
- Stage History — log of when stages advanced

**Layer 2 — Phase bar + phase content** (8 phases, horizontally clickable):
- Each phase shows ONLY its own content when clicked
- Defaults to the current phase on page load
- Past phases are still clickable so you can look back at any time
- This mirrors what the client sees in their portal

---

## The 8 phases (final agreed names — these replace the old stage names)

| # | New Name | Replaces | What happens |
|---|---|---|---|
| 1 | Qualification | Qualification (was empty) | Intake notes, budget confirmed, call scheduled, call completed toggle |
| 2 | Discovery | Discovery | Transcript in, AI extracts definition, client reviews and approves |
| 3 | Proposal | Scope Lock | Scope of service doc created, sent to client, client accepts |
| 4 | Visual Identity | Visual Identity | Brand assets collected, mood board voting, style direction locked |
| 5 | Wireframes | Wireframes | Grayscale page layouts, client approves structure |
| 6 | Final Design | (was split across Build) | Full color mockups + client enters and approves all page content |
| 7 | Build | Build | Development, deliverables tracking |
| 8 | Delivery | Review + Delivery merged | Client reviews live site, final sign-off, launch |

Key differences from before:
- "Scope Lock" becomes "Proposal" — it was always a proposal, the old name was confusing
- "Review" and "Delivery" are merged into one "Delivery" stage — reviewing the built site IS the delivery step
- A new "Final Design" stage is inserted between Wireframes and Build — this is where full-color mockups are shown and clients enter/approve content
- Qualification is no longer empty — it now has a lightweight intake form

---

## Page layout from top to bottom

```
┌────────────────────────────────────────────────────────────┐
│  [Project Name]  [Client Name]     [Edit Project] [Advance Stage] │
├────────────────────────────────────────────────────────────┤
│  [3/8 Visual Identity]  [Active]  [Deliverables]  [Feedback]      │  stats
├────────────────────────────────────────────────────────────┤
│  [Overview]  [Stakeholders 7]  [Stage History]             │  utility tabs
├────────────────────────────────────────────────────────────┤
│  1·Qualification  2·Discovery  3·Proposal  4·Visual Identity       │
│  5·Wireframes  6·Final Design  7·Build  8·Delivery         │  phase bar
├────────────────────────────────────────────────────────────┤
│                                                            │
│  [ Phase content — only this phase shown ]                 │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

The old stepper (circles in a horizontal row) is REMOVED entirely. The phase bar replaces it — styled as horizontal pill-style buttons with a connecting line, completed phases show a checkmark, current phase is highlighted indigo, future phases are gray.

The old Stage Status card is REMOVED — that status information lives inside the relevant phase panel itself.

---

## What content goes in each phase panel

**Phase 1 — Qualification (NEW content)**
- Project goal field (textarea) — uses existing `project_goal` column on `el_es_projects`
- Call scheduled date field — uses existing `deadline` column or a new meta field
- Call completed toggle (checkbox) — when checked, signals ready to advance to Discovery
- Internal notes field (the existing project `notes` column)
- No client-facing interaction in this phase

**Phase 2 — Discovery (moved from old `transcript` tab)**
- Everything currently in the `transcript` tab_panel verbatim:
  - Definition status badge + amber lock banner (if approved)
  - Send for Review button / active review state
  - Stakeholder verdicts summary card
  - Stakeholder comments panel
  - Version History collapsible
  - Definition Locked notice
  - Transcript textarea + Process with AI button
  - Project Definition form (all 6 fields)

**Phase 3 — Proposal (moved from old `proposals` tab)**
- Everything currently in the `proposals` tab_panel verbatim:
  - Proposals table with status badges
  - New Proposal button
  - Edit/Send/Delete actions
  - Accepted proposal notice

**Phase 4 — Visual Identity (moved from old `branding` tab)**
- Everything currently in the `branding` tab_panel verbatim:
  - Mood board review sessions list
  - Create Mood Board Session button
  - Review results and DM close controls

**Phase 5 — Wireframes**
- The existing `pages` tab content, labelled as wireframe pages
- Same data (el_es_pages table), same Add Page / manage functionality

**Phase 6 — Final Design**
- Same pages list as Phase 5 but labelled as design/content review pages
- A notice: "Content entry and design approval tools coming in a future update"
- This is a placeholder panel in v1.29.0 — the full content-review UI is built later

**Phase 7 — Build**
- The existing `deliverables` tab content verbatim
- Deliverables table, Add Deliverable button

**Phase 8 — Delivery**
- The existing `feedback` tab content verbatim
- Feedback table with pending/resolved statuses
- Brief deliverables summary (count + link to Phase 7 for full list)

---

## Files to change

### `el-core/modules/expand-site/class-expand-site-module.php`
- Update `STAGES` constant: rename Scope Lock → Proposal, insert Final Design as stage 6, shift Build to 7, merge Review+Delivery into Delivery at 8
- Update `STAGE_DEADLINE_DAYS` to match new 8 stages
- Update the inline CSS in `enqueue_admin_assets()`: remove stepper CSS, add phase bar CSS (horizontal pill buttons with connecting line)

### `el-core/modules/expand-site/admin/views/project-detail.php`
- Remove: current single `tab_nav` call (9 flat tabs)
- Remove: stage stepper HTML block
- Remove: Stage Status card HTML block
- Remove: `$stage_tab_map` / `$active_tab` logic (replaced by simpler `$active_phase = $current_stage`)
- Add: `utility-tabs` `tab_nav` group with Overview, Stakeholders, Stage History
- Add: `phase-tabs` `tab_nav` group with 8 phase buttons
- Add: 8 `phase-tabs` `tab_panel` entries, each wrapping the relevant existing content
- Add: Phase 1 Qualification panel content (new — intake form using existing columns)
- The tab_panel IDs change from `overview/transcript/proposals/etc` to `util-overview/util-stakeholders/util-history/phase-1` through `phase-8`

### `el-core/modules/expand-site/assets/js/expand-site-admin.js`
- Search for any hardcoded references to old tab IDs and update to new IDs
- The tab switching JS itself is handled by the existing `el-tab-btn` / `el-tab-content` pattern — no new JS logic needed, just ID references

### `el-core/modules/expand-site/module.json`
- No new tables or columns needed for v1.29.0
- No DB migration needed — stage numbers 1–8 stay the same in the database, only names change

---

## What is NOT changing in v1.29.0

- All existing AJAX handlers — no changes
- All existing data / database structure — no changes
- The client portal (`expand-site-portal.php`, `expand-site.js`) — NOT touched in this session. Aligning the portal to the new phase names is a follow-up session (v1.30.0)
- The project list page — no changes

---

## Build order

1. Update STAGES constant + STAGE_DEADLINE_DAYS in `class-expand-site-module.php`
2. Replace stepper/status card CSS in `enqueue_admin_assets()` with phase bar CSS
3. Restructure `project-detail.php` — utility tabs first, then phase bar, then 8 phase panels
4. Update any tab ID references in `expand-site-admin.js`
5. Bump to v1.29.0, update CHANGELOG, START-HERE-NEXT-SESSION, CURSOR-TODO
6. Build ZIP, commit, push
7. Write `NEXT-SESSION-PROMPT-v1.30.0.md` covering: portal alignment to new phase names, Qualification phase intake form refinement, Final Design phase content-entry UI

Do not build the ZIP until all panels are rendering correctly. Do not touch the portal or any AJAX handlers.
