# Master Prompt — New Chat — EL Core v1.33.20

> Paste this entire file at the start of a new Cursor chat.

---

## CONTEXT

Read `@START-HERE-NEXT-SESSION.md` and `@SPEC-USER-JOURNEY-PHASE.md` first.

I'm working on the **Expand Site workstream** of the EL Core WordPress plugin.

**Current version:** v1.33.20  
**Repo:** `c:\Github\EL Core`  
**Plugin folder:** `el-core\` (inside repo root)  
**Build script:** `build-zip.ps1` (repo root) — always use this to build ZIPs, never Compress-Archive  
**Staging site:** qd19d0iehj-staging.wpdns.site

---

## WHAT WAS JUST FINISHED (this session, March 10 2026)

We completed a full bug-fix sprint on Phase 4 (User Journey). The entire journey workflow — from client answering questions through AI generation, admin refinement, client consensus review, and admin locking — is now working end-to-end.

### Versions shipped this session:

| Version | What changed |
|---------|-------------|
| v1.33.12 | DM answer fields made editable in portal `pending_dm_review`; AI markdown fence regex multiline-safe; empty-answers guard; auto-retry on AI parse failure |
| v1.33.13 | Admin manual editor replaced JSON textarea with structured form (summary + per-step inputs); Refine with AI now uses `admin_workflow` as base; `resize:both` on all textareas |
| v1.33.14 | AI prompts (Round 1 & 2) rewritten for clean JSON output; `parse_journey_ai_response()` unwraps extra `workflow` wrapper key |
| v1.33.15 | Add Step + Remove Step buttons in admin manual editor |
| v1.33.16 | "Insert step below" placed inside each step card (between steps); `renumberSteps()` helper |
| v1.33.17 | `max_tokens: 4096` on all AI journey calls — this was the root cause of truncated JSON |
| v1.33.18 | Portal `in_review` rebuilt: stakeholders can edit steps inline, insert/remove steps, verdict banners (name + date/time) appear after voting, all teammates' verdicts visible, consensus badge per step |
| v1.33.19 | Fixed "Invalid decision data" error — `$user_id = get_current_user_id()` was missing from `handle_dm_journey_decision()` |
| v1.33.20 | Admin `approved` state now renders full numbered step list (label + description + branch + implied pages) before "Lock Journey" button — was only showing summary paragraph |

---

## CURRENT STATE OF PHASE 4 — User Journey (fully complete)

Full status machine works end-to-end:

```
pending_assignment → awaiting_input → pending_dm_review → awaiting_ai
→ ai_generated → admin_refined → in_review → approved → locked
```

### Key files:
- **Admin UI:** `el-core\modules\expand-site\admin\views\project-detail.php`
- **Portal UI:** `el-core\modules\expand-site\shortcodes\expand-site-portal.php`
- **Backend/AJAX:** `el-core\modules\expand-site\class-expand-site-module.php`
- **Admin JS:** `el-core\modules\expand-site\assets\js\expand-site-admin.js`
- **Portal JS:** `el-core\modules\expand-site\assets\js\expand-site.js`
- **CSS:** `el-core\modules\expand-site\assets\css\expand-site.css`

### What each state shows:

**Admin side:**
- `pending_assignment` — stakeholder dropdown + Assign button
- `awaiting_input` — waiting message + Reassign link
- `pending_dm_review` — answers display + info notice
- `awaiting_ai` — answers + DM notes + "Generate with AI" button
- `ai_generated` — Q&A + AI workflow + Refine with AI + structured manual editor (summary + per-step inputs) + Insert/Remove step buttons
- `admin_refined` — refined workflow + Refine Again + structured editor + Send for Review
- `in_review` — review info, DM decision status, DM notes, Reset to Draft
- `approved` — approval banner + **full numbered step list** + Lock Journey button ← JUST FIXED
- `locked` — read-only numbered step list

**Portal side:**
- `pending_assignment` — DM sees assign dropdown; others see waiting
- `awaiting_input` — assigned person sees 6-question form; DM sees reassign
- `pending_dm_review` — everyone sees Q&A; DM sees **editable** answer textareas + "Send to Project Manager"
- `awaiting_ai / ai_generated / admin_refined` — "Our team is reviewing…"
- `in_review` — full consensus UI: workflow summary + steps, per-step inline editing (label/description), insert/remove steps, "Looks Good" / "Flag for Changes" verdict buttons, verdict banner with name+datetime after voting, all teammates' verdicts visible, comment box, DM decision section
- `approved` — green approved banner
- `locked` — blue finalized banner

---

## WHAT TO DO NEXT

**Recommended:** Upload v1.33.20 ZIP to staging and test the full Phase 4 journey flow end-to-end:

1. Admin assigns a user type journey to a stakeholder
2. Stakeholder fills in the 6 answers and submits
3. DM reviews answers (editable), sends to PM
4. Admin clicks "Generate with AI" → workflow appears
5. Admin refines with AI (or manually edits steps, inserts/removes steps)
6. Admin clicks "Send for Review"
7. Portal users vote on each step (Looks Good / Flag for Changes) — banners appear with name+datetime
8. Contributors see each other's verdicts
9. DM accepts the workflow
10. Admin sees full step list in `approved` state, clicks "Lock Journey"
11. Once all journeys locked, admin can advance to Phase 5

**Then:** If Phase 4 passes testing, begin Phase 5 (Visual Identity).

---

## IMPORTANT RULES

- Version number lives in **two places** in `el-core\el-core.php`: the plugin header comment AND the `EL_CORE_VERSION` constant
- Also update `build-zip.ps1` `$version` variable
- Always run `build-zip.ps1` to build — never `Compress-Archive`
- Update `CHANGELOG.md`, `CURSOR-TODO.md`, and `START-HERE-NEXT-SESSION.md` at end of session
- ZIP uploads to: `old-versions\vX.X.X\` AND `releases\` AND `el-core-releases\` AND `Downloads`
