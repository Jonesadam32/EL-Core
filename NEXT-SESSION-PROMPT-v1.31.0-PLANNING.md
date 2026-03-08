# Next Session Prompt — v1.31.0 Planning: User Journey Phase

> **Mode:** PLANNING only. Do not write any code this session.
> Read this entire file before saying anything.

---

## What to do at the start of this session

1. Read `START-HERE-NEXT-SESSION.md`
2. Read `CURSOR-TODO.md`
3. Read the STAGES constant in `el-core/modules/expand-site/class-expand-site-module.php` (search for `const STAGES`) so you know the current 8-phase pipeline by name
4. Then read this file fully before responding

---

## Session Goal

Plan and fully spec the **User Journey** phase before a single line of code is written.

Fred wants to add a new phase to the Expand Site pipeline between **Proposal (Phase 3)** and **Visual Identity (Phase 4)**. This shifts Visual Identity → Build → Delivery each back by one, making it a **9-phase pipeline**.

The new phase is called **User Journey** and it will become **Phase 4**.

---

## What Fred Described (verbatim understanding)

After the proposal is accepted, the client's stakeholders come in and describe the journey for each user type that was identified during the Discovery phase.

For each user type (e.g., "Student," "Teacher," "Administrator," "Parent"):
- Where do they first land when they visit the site?
- What is their primary task?
- What path do they take through the site?
- What does success look like for them?

The stakeholders write these descriptions in their own words via the client portal. Then AI processes those descriptions and generates a structured workflow — a step-by-step flow for each user type — which the team reviews.

This is critical because each user type may have a completely different entry point, task set, and success state. Without mapping this before wireframes, design has no foundation to work from.

---

## What We Need to Plan Together

Work through each of these with Fred before writing any spec:

### 1. Who fills in the User Journey?
- Is it admin-only, or do portal stakeholders contribute?
- Can multiple stakeholders each submit a journey for the same user type, then the DM picks the best?
- Or does the DM write one canonical journey per user type?

### 2. What is the input format?
- Free-text textarea per user type (simplest)?
- A structured form with specific fields (First touchpoint, Primary task, Steps 1–N, Success state)?
- Or a hybrid: structured fields but with a free-form "Additional notes" section?

### 3. How does AI fit in?
- Does AI draft the workflow from the stakeholder's freeform description?
- Or does AI only refine/format something the DM has already written?
- What does the AI output look like — a numbered step list? A table? A narrative paragraph?
- Where does the AI output live — does the admin review it before the client sees it, or does it go straight to the portal?

### 4. What is the approval flow?
- Does the User Journey go through a consensus review like the Project Definition (with per-field verdicts)?
- Or is it simpler — admin reviews AI output, edits it, then locks it?
- Does the client approve the journeys before moving to Visual Identity?

### 5. What is the relationship to user_types from Discovery?
- The Discovery phase captures `user_types` as a text field (comma-separated or JSON list of user type names).
- For the Journey phase, each user type from that list becomes its own journey card.
- What happens if the user_types list changes after Discovery is locked? (Edge case — probably ignore for now.)

### 6. Database shape
- New table `el_es_user_journeys`: one row per user type per project
- Fields to think through: project_id, user_type (the name), raw_description (what stakeholder wrote), ai_workflow (AI-generated step list), status (draft / ai_generated / locked), locked_at, locked_by
- Do we need versioning (like definition snapshots)? Or is one row per user type enough for now?

### 7. Admin panel (Phase 4 in project-detail.php)
- List of user types pulled from the locked definition
- For each: show raw description, AI-generated workflow, status badge, Lock button
- "Generate All Workflows" button → runs AI on all user types at once
- Or per-type "Generate" button → runs AI on one at a time

### 8. Portal panel (Phase 4 in the client portal)
- Does the portal show the AI-generated workflow to the client?
- Can stakeholders edit the raw description before AI runs?
- What does the locked state look like in the portal?

### 9. Pipeline gating
- Does Phase 5 (Visual Identity) gate on all user journeys being locked?
- Or is it advisory — admin can advance even if some journeys are still draft?

---

## Context to Keep in Mind

- The Discovery phase stores `user_types` as a text field on `el_es_project_definition`
- The current STAGES constant has 8 phases — inserting User Journey as Phase 4 shifts phases 4–8 to 5–9
- The STAGES constant is in `class-expand-site-module.php` and is referenced throughout admin views, the portal, and the phase bar — any renumbering must be done carefully
- All new DB tables use the `el_es_` prefix
- All new CSS uses the `el-es-` prefix
- Build rules: single-file PHP (no external CSS/JS files), inline styles via wp_head, inline scripts via wp_footer — but for modules this is already handled by the asset loader, so new CSS goes in `expand-site.css` and new JS goes in `expand-site.js` / `expand-site-admin.js`
- Admin UI uses `EL_Admin_UI::*` exclusively — no raw HTML tables or forms

---

## What the Output of This Session Should Be

By the end of this planning session, produce:

1. **A finalized spec document** saved as `SPEC-USER-JOURNEY-PHASE.md` in the repo root covering:
   - Exact database schema (table name, columns, types)
   - STAGES constant update (new 9-phase list with correct names and order)
   - Admin panel behavior (what renders per state)
   - Portal panel behavior (what renders per state)
   - AI prompt design (what goes in, what comes out)
   - Approval/lock flow
   - Pipeline gating rules

2. **An updated CURSOR-TODO.md** with the User Journey phase added as the next build item

3. **An updated START-HERE-NEXT-SESSION.md** pointing to the spec

Do NOT write any PHP, JS, or CSS during this session. This is design and planning only.

---

## Summary of Work Completed Before This Session

### What was built (v1.21.4 → v1.30.3)

**v1.22.0 — v1.26.0: Definition Consensus Review System**
- DB schema (reviews, comments, verdicts tables)
- Full stakeholder review workflow: send for review → per-field comments → per-field verdicts → DM final decision (Accept / Needs Revision) → admin lock
- Admin Phase 2 panel: status badges, comments panel, version history, lock banner
- Portal Stage 2: countdown timer, per-field comment threads, verdict buttons, scroll-depth gate, DM decision section

**v1.27.0 — v1.27.3: Client Dashboard + Routing Fixes**
- `[el_client_dashboard]` shortcode — project cards, CTA buttons, invoice section
- Portal `?project_id=X` routing fixed
- Back to Dashboard button
- Login As / Switch Back toolbar working

**v1.28.0: Admin UX + Bug Fixes**
- Definition version history (DB migration v9)
- Needs Attention list on project list
- Lock prompt banner after client approval
- Stage stepper + status card on project detail

**v1.29.0: Phase Bar Redesign**
- 8-phase pipeline with new stage names: Qualification, Discovery, Proposal, Visual Identity, Wireframes, Final Design, Build, Delivery
- Phase bar replaces stepper — pill buttons, connecting line
- Utility tabs (Overview, Stakeholders, Stage History) above phase bar
- Phase 1 (Qualification) intake form built

**v1.30.0 — v1.30.3: Bugfix Sprint**
- Needs Revision flow fixed (review stays open, DM banner shown)
- Reset to Draft escape hatch
- Qualification form AJAX save
- HTTP 400 WAF fix for role change
- Phase bar tab auto-activation on page load
- Verdict button rename: "Approve Field" / "Flag for changes"
- Client profile contacts Role column
- Approved-state editing notice
- Draft-state helper notice
- Verdict active state persistence (localVerdicts cache)
- Verdict button height fix

### Current state
- **Plugin:** v1.30.3, built, not yet uploaded to staging
- **Next upload:** v1.30.3 to staging, then test with `V1.30.2-TESTING-GUIDE.md` (covers 1.30.2 and 1.30.3 fixes)
- **Next build:** v1.31.0 — User Journey phase (plan first, then build)
