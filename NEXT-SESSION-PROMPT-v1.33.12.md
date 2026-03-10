# Next Session Prompt — v1.33.12
## User Journey Phase 4 — Bug Fixes

> **Read this file at the start of the next session.**
> Then read `@START-HERE-NEXT-SESSION.md` and `@SPEC-USER-JOURNEY-PHASE.md` for full context.

---

## Current Plugin Version
**v1.33.11** — deployed to staging (qd19d0iehj-staging.wpdns.site)

---

## What Was Built in the Previous Session (v1.33.9 through v1.33.11)

The full Phase 4 User Journey flow was redesigned from scratch:

### New status flow
```
pending_assignment
  ↓ DM assigns stakeholder
awaiting_input
  ↓ Assigned person submits 6 answers
pending_dm_review          ← NEW
  ↓ DM reviews answers, adds notes, clicks "Send to Project Manager"
awaiting_ai
  ↓ Admin clicks "Generate with AI" deliberately
ai_generated
  ↓ Admin refines (AI or manual JSON editor) → status: admin_refined
admin_refined
  ↓ Admin clicks "Send for Review"
in_review
  ↓ DM approves
approved
  ↓ Admin locks
locked ✓
```

### Key changes made
- `handle_submit_journey_answers` — no longer fires AI. Saves answers, sets status = `pending_dm_review`. Clean success message to contributor, no AI mention.
- `handle_dm_send_to_admin` — new handler. DM sends answers + optional notes forward. Status → `awaiting_ai`.
- `handle_generate_journey_ai` — new handler. Admin clicks "Generate with AI" deliberately. Status → `ai_generated`.
- `handle_save_journey_workflow` — new handler. Admin saves manually edited workflow JSON. Status → `admin_refined`.
- Portal `pending_dm_review` state — shows Q&A answers to everyone. DM sees notes textarea + "Send to Project Manager" button.
- Admin `awaiting_ai` state — shows answers + DM notes + "Generate with AI" button.
- Admin `ai_generated` / `admin_refined` states — have collapsible "Manually edit workflow" JSON editor.
- Portal `in_review` state — full consensus UI: step verdicts, threaded comments, DM decision section with edit notes textarea.

---

## What Needs to Be Fixed in v1.33.12

### Bug 1 — DM review: answers are read-only, should be editable
**What happens:** In the portal `pending_dm_review` state, the submitted answers are displayed as plain text. The DM should be able to edit the individual answer fields directly before sending to the admin — not just add a notes blurb at the bottom.

**What to build:**
- In the portal `pending_dm_review` state, when the viewer is the DM (`$is_decision_maker`), render each answer as a pre-filled `<textarea>` instead of a `<p>` tag
- The DM's edited answers overwrite `guided_answers` in the DB when they click "Send to Project Manager"
- The `handle_dm_send_to_admin` AJAX handler should accept updated answers and save them before advancing status
- Contributors (non-DM) still see read-only answer text

**Files to change:**
- `el-core/modules/expand-site/shortcodes/expand-site-portal.php` — render textareas for DM
- `el-core/modules/expand-site/assets/js/expand-site.js` — collect textarea values and POST them with `es_dm_send_to_admin`
- `el-core/modules/expand-site/class-expand-site-module.php` — `handle_dm_send_to_admin()` accepts and saves edited answers

---

### Bug 2 — AI returns "invalid workflow structure" error
**What happens:** After the DM sends answers to the admin, admin clicks "Generate with AI" and gets: *"AI returned an invalid workflow structure."* The AI call succeeds (no network error) but the JSON parsing fails.

**Root cause to investigate:**
- `run_journey_ai_round1()` in `class-expand-site-module.php` — check how it strips markdown fences from the AI response
- The current regex: `preg_replace('/^```(?:json)?\s*/i', '', trim($raw))` only strips a fence at the very start. If the AI returns something like `\n\n```json\n{...}\n````, the leading newlines mean the regex doesn't match
- Fix: use a more robust strip that handles leading whitespace/newlines before the fence, AND trims trailing fences properly
- Also consider: if `$guided_answers` is empty (e.g. the journey was seeded before the new flow), `run_journey_ai_round1()` will produce a weak prompt — add a guard

**Files to change:**
- `el-core/modules/expand-site/class-expand-site-module.php` — fix markdown fence stripping in `run_journey_ai_round1()` and `run_journey_ai_round2()`

**Suggested fix for fence stripping:**
```php
// Strip any leading/trailing markdown code fences robustly
$raw = trim( $raw );
$raw = preg_replace( '/^```(?:json)?[\s]*/i', '', $raw );
$raw = preg_replace( '/[\s]*```[\s]*$/i', '', $raw );
$raw = trim( $raw );
```

---

## Files That Will Need Changes

| File | What Changes |
|------|-------------|
| `el-core/modules/expand-site/shortcodes/expand-site-portal.php` | DM sees editable textareas in `pending_dm_review` state |
| `el-core/modules/expand-site/assets/js/expand-site.js` | Collect DM's edited answers before POSTing `es_dm_send_to_admin` |
| `el-core/modules/expand-site/class-expand-site-module.php` | `handle_dm_send_to_admin()` saves edited answers; fix fence stripping in both AI round functions |
| `el-core/el-core.php` | Version bump to 1.33.12 |
| `build-zip.ps1` | Version bump to 1.33.12 |
| `CHANGELOG.md` | New entry for v1.33.12 |
| `START-HERE-NEXT-SESSION.md` | Update current version |

---

## Critical Lessons — Do Not Repeat These Mistakes

1. **ALWAYS check for orphaned docblocks after every StrReplace that inserts before an existing function.** The pattern `}` followed by bare `* Text` at class level is a fatal PHP parse error. After inserting code before any existing function, read 5 lines before that function's `public function` line to confirm the `/**` is present.

2. **PHP union return types (`array|WP_Error`) require PHP 8.0+** — already verified this server supports it, fine to keep.

3. **The `handle_dm_send_to_admin` nopriv hook is registered** — DM is a logged-in WP user on the portal but may not have `manage_expand_site`. The nopriv variant handles this.

4. **Do not use `&&` in PowerShell** — use `;` to chain commands.

5. **Always run `node --check` on both JS files before building ZIP.**

6. **After every `StrReplace` that touches `class-expand-site-module.php`, run the orphaned docblock scan:**
```powershell
Select-String -Path "el-core\modules\expand-site\class-expand-site-module.php" -Pattern "^\s{4}\* [A-Z]" | Where-Object { $lineNum = $_.LineNumber; $prevLine = (Get-Content "el-core\modules\expand-site\class-expand-site-module.php")[$lineNum - 2]; $prevLine -notmatch "/\*\*" -and $prevLine -notmatch "^\s{4}\*" } | Select-Object LineNumber, Line
```

---

## How to Start the Next Session

Paste this into a new chat:

```
Read @NEXT-SESSION-PROMPT-v1.33.12.md and @SPEC-USER-JOURNEY-PHASE.md.

I'm continuing work on the Expand Site workstream — v1.33.12.

Two bugs to fix:
1. In the portal pending_dm_review state, the DM should be able to edit the submitted answers (editable textareas) before sending to the admin. Right now they are read-only.
2. When the admin clicks "Generate with AI", we get "AI returned an invalid workflow structure." The markdown fence stripping in run_journey_ai_round1() needs to be made more robust.

After fixing both bugs: bump to v1.33.12, build ZIP, commit, push.
```
