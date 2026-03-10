# Next Session Prompt — v1.33.12
## User Journey Phase 4 — Bug Fixes

> **Instructions for the new agent:** Read this file completely before touching any code.
> Then read `@SPEC-USER-JOURNEY-PHASE.md` for the full feature spec (it has been updated to match current implementation).
> Then read `@START-HERE-NEXT-SESSION.md` for architecture rules and deployment process.
> Check `@CURSOR-TODO.md` for the authoritative task checklist.

---

## Current Plugin Version
**v1.33.11** — deployed to staging (qd19d0iehj-staging.wpdns.site)

---

## Full Flow Context — What Phase 4 Does and Why

Phase 4 (User Journey) sits between Proposal (Phase 3) and Visual Identity (Phase 5). Each user type listed in the project definition gets a journey row. Stakeholders describe how that user type moves through the site by answering 6 guided questions. The admin uses those answers to generate an AI workflow, refines it, and sends it back to the team for consensus review before locking.

### The Complete Status Flow (as of v1.33.11)

```
pending_assignment
    ↓ DM assigns a stakeholder (contributor or themselves)
awaiting_input
    ↓ Assigned person submits 6 answers via portal form
pending_dm_review          ← Added in v1.33.11
    ↓ DM reviews/edits answers + adds notes → clicks "Send to Project Manager"
awaiting_ai
    ↓ Admin clicks "Generate with AI" deliberately (no auto-fire)
ai_generated
    ↓ Admin refines with AI (notes → Round 2) OR manually edits JSON
admin_refined
    ↓ Admin clicks "Send for Review" (sets deadline)
in_review
    ↓ Team reviews workflow (verdicts + comments) → DM clicks "Accept"
approved
    ↓ Admin clicks "Lock"
locked ✓
```

### Key Design Decisions (do not change these)

1. **AI never fires automatically.** Submitting the 6 answers does NOT trigger AI. The admin always triggers it deliberately via the "Generate with AI" button.

2. **`pending_dm_review` is a mandatory gate.** After a contributor submits their answers, nothing moves forward until the DM explicitly reviews and sends. The DM can edit the answers directly (editable textareas) before sending.

3. **Symmetric review.** Whoever fills out answers, the other party reviews. Contributor fills out → DM reviews before admin gets it. DM fills out → contributors see it in `pending_dm_review` state (read-only) before DM sends.

4. **DM is always the gatekeeper.** Only the DM has the "Send to Project Manager" button. No contributor can push anything to the admin.

5. **Admin can manually edit the workflow JSON** at any point in `ai_generated` or `admin_refined` states via a collapsible JSON editor.

6. **Phase 5 is hard-gated.** Admin cannot advance to Visual Identity until every journey row for the project is `status = 'locked'`.

---

## What Is Already Built (v1.33.11)

### PHP — `class-expand-site-module.php`
All of these AJAX handlers exist and are registered (including `nopriv` variants):
- `handle_submit_journey_answers` — saves 6 answers, sets status = `pending_dm_review`
- `handle_dm_send_to_admin` — DM sends answers + notes forward; status → `awaiting_ai`
  - **BUG:** currently does NOT save edited answers from the DM (Bug 1 below)
- `handle_generate_journey_ai` — admin triggers Round 1 AI; status → `ai_generated`
  - **BUG:** AI response parsing fails (Bug 2 below)
- `handle_refine_journey` + `run_journey_ai_round2` — AI Round 2; status → `admin_refined`
- `handle_save_journey_workflow` — saves manually edited JSON; status → `admin_refined`
- `handle_send_journey_review` — creates review round; status → `in_review`
- `handle_reset_journey_review` — cancels review; status → `admin_refined`
- `handle_lock_journey` — status → `locked`
- `handle_post_journey_comment` — post comment on a step
- `handle_journey_step_verdict` — upsert step verdict (approved / needs_revision)
- `handle_dm_journey_decision` — DM final decision; approved → status `approved`
- `handle_dm_assign_journey` — DM assigns stakeholder from portal

### Admin UI — `admin/views/project-detail.php`
All admin panel states are rendered:
- `pending_assignment`: stakeholder dropdown + Assign button
- `awaiting_input`: waiting message + Reassign link
- `pending_dm_review`: answers display + info notice
- `awaiting_ai`: answers + DM notes + **"Generate with AI"** button
- `ai_generated`: Q&A + AI workflow + Refine with AI + manual JSON editor
- `admin_refined`: refined workflow + Refine Again + manual JSON editor + Send for Review
- `in_review`: review info, DM notes display (orange box), Reset to Draft
- `approved`: Lock Journey button
- `locked`: read-only display

### Portal — `shortcodes/expand-site-portal.php`
All portal states are rendered:
- `pending_assignment`: DM sees assign dropdown; others see waiting message
- `awaiting_input`: assigned person sees 6-question form; DM sees reassign link
- `pending_dm_review`: everyone sees Q&A answers read-only; DM sees notes textarea + "Send to Project Manager" button
  - **BUG:** DM answers are read-only plain text — should be editable textareas (Bug 1 below)
- `awaiting_ai` / `ai_generated` / `admin_refined`: "Our team is reviewing..." placeholder
- `in_review`: full consensus UI — summary, step list, per-step verdict buttons (Looks good / Flag for changes), threaded comments with replies, overall comment box, DM decision section with edit notes textarea and Accept/Needs Revision buttons
- `approved`: green approved banner
- `locked`: blue finalized banner

---

## Bugs to Fix in v1.33.12

### Bug 1 — DM answer fields are read-only in `pending_dm_review` portal state

**What the user sees:** When the DM goes to the portal to review a contributor's submitted answers, the answers are displayed as plain read-only text paragraphs. The DM cannot edit them.

**What should happen:** When the viewer IS the Decision Maker (`$is_decision_maker === true`), each answer should render as a pre-filled `<textarea>`. When the DM clicks "Send to Project Manager", the JS should collect the current textarea values and POST them as updated answers. The PHP handler saves the edited answers back to `guided_answers` in the DB before advancing status.

**Exact changes needed:**

**`shortcodes/expand-site-portal.php`** — in the `pending_dm_review` block, change:
```php
// Currently renders each answer as a <p> tag for everyone
$html .= '<p class="el-es-journey-pdm-a">' . esc_html( $qa['answer'] ?? '' ) . '</p>';
```
To: for DMs, render a `<textarea>` pre-filled with the answer, with a `name="answer_{n}"` and `data-question-index="{n}"`. For non-DMs, keep the `<p>` tag.

**`assets/js/expand-site.js`** — in the `es_dm_send_to_admin` click handler, before calling `ELCore.ajax`, collect answer values from the textareas:
```js
var answers = {};
wrapper.querySelectorAll('.el-es-journey-pdm-answer-edit').forEach(function(ta, idx) {
    answers['answer_' + (idx + 1)] = ta.value;
});
// include answers in the ajax call payload
```

**`class-expand-site-module.php`** — in `handle_dm_send_to_admin()`, before updating status, check if answer data was POSTed and if so, re-collect and save to `guided_answers`:
```php
// If DM submitted edited answers, save them
$has_edited_answers = false;
$edited_answers = [];
$questions = [ 1 => '...q1...', 2 => '...q2...', ... ]; // same array as in handle_submit_journey_answers
for ( $n = 1; $n <= 6; $n++ ) {
    if ( isset( $_POST['answer_' . $n] ) ) {
        $has_edited_answers = true;
        $edited_answers[] = [
            'question' => $questions[$n],
            'answer'   => sanitize_textarea_field( wp_unslash( $_POST['answer_' . $n] ) ),
        ];
    }
}
if ( $has_edited_answers && ! empty( $edited_answers ) ) {
    $wpdb->update( $jt, [ 'guided_answers' => wp_json_encode( $edited_answers ) ], [ 'id' => $journey_id ] );
}
```

---

### Bug 2 — "AI returned an invalid workflow structure" on Generate with AI

**What the user sees:** Admin clicks "Generate with AI" on an `awaiting_ai` journey. Gets an alert: *"AI returned an invalid workflow structure."* The page stays on `awaiting_ai` state.

**Root cause:** The AI response often comes back with the JSON wrapped in a markdown code fence, sometimes with leading whitespace or newlines before the opening ` ``` `. The current regex only strips a fence that appears at the very beginning of the string (after a single `trim()`), so if there are any leading newlines the fence is not stripped and `json_decode()` fails.

**Current code** (in `run_journey_ai_round1()` and `run_journey_ai_round2()`):
```php
$raw = preg_replace( '/^```(?:json)?\s*/i', '', trim( $raw ) );
$raw = preg_replace( '/\s*```$/', '', $raw );
```

**Fix — replace both preg_replace calls in BOTH functions with:**
```php
// Robustly strip markdown code fences regardless of leading/trailing whitespace
$raw = trim( $raw );
$raw = preg_replace( '/^```(?:json)?\s*/im', '', $raw );  // strip opening fence
$raw = preg_replace( '/\s*```\s*$/im', '', $raw );         // strip closing fence
$raw = trim( $raw );
```

The `m` (multiline) flag ensures the `^` and `$` anchors match at line boundaries, not just the absolute start/end of the string. This handles the case where the AI puts the fence on a line by itself with the JSON on subsequent lines.

**Also add a guard for empty answers** in `run_journey_ai_round1()`:
```php
if ( empty( $guided_answers ) ) {
    return new WP_Error( 'no_answers', __( 'No answers have been saved for this journey yet.', 'el-core' ) );
}
```

---

## Files That Will Need Changes

| File | What Changes |
|------|-------------|
| `el-core/modules/expand-site/shortcodes/expand-site-portal.php` | DM sees editable textareas in `pending_dm_review` state |
| `el-core/modules/expand-site/assets/js/expand-site.js` | Collect DM's edited answer values before POSTing `es_dm_send_to_admin` |
| `el-core/modules/expand-site/class-expand-site-module.php` | `handle_dm_send_to_admin()` saves edited answers; fix fence stripping in `run_journey_ai_round1()` and `run_journey_ai_round2()` |
| `el-core/el-core.php` | Version bump to 1.33.12 (TWO places: header + constant) |
| `build-zip.ps1` | Version bump to 1.33.12 |
| `CHANGELOG.md` | New entry for v1.33.12 |
| `START-HERE-NEXT-SESSION.md` | Update current version |
| `CURSOR-TODO.md` | Check off Bug 1 and Bug 2 under v1.31.0 outstanding section |

---

## Critical Safety Rules — Do Not Skip These

### 1. Orphaned docblock check after EVERY StrReplace on class-expand-site-module.php
This has caused two fatal PHP parse errors (v1.33.8, v1.33.9). After every edit to the PHP file, run:
```powershell
Select-String -Path "el-core\modules\expand-site\class-expand-site-module.php" -Pattern "^\s{4}\* [A-Z]" | Where-Object { $lineNum = $_.LineNumber; $prevLine = (Get-Content "el-core\modules\expand-site\class-expand-site-module.php")[$lineNum - 2]; $prevLine -notmatch "/\*\*" -and $prevLine -notmatch "^\s{4}\*" } | Select-Object LineNumber, Line
```
Empty output = safe. Any output = fatal error waiting to happen — fix before building.

### 2. JS syntax check before building ZIP
```powershell
node --check "el-core\modules\expand-site\assets\js\expand-site.js"
node --check "el-core\modules\expand-site\assets\js\expand-site-admin.js"
```

### 3. Version bump in THREE places
- `el-core/el-core.php` — plugin header (`* Version: X.X.X`)
- `el-core/el-core.php` — constant (`define( 'EL_CORE_VERSION', 'X.X.X' )`)
- `build-zip.ps1` — `$version` variable

### 4. PowerShell: use `;` not `&&` to chain commands

### 5. DM is a logged-in WP user on the portal without `manage_expand_site`
All portal-facing handlers need `nopriv` variants. `handle_dm_send_to_admin` already has one — do not remove it.

### 6. Never use Compress-Archive for ZIP — always use build-zip.ps1

---

## How to Start the Next Session

Paste this into a new chat:

```
Read @NEXT-SESSION-PROMPT-v1.33.12.md and @SPEC-USER-JOURNEY-PHASE.md.

I'm continuing work on the Expand Site workstream — v1.33.12.

Two bugs to fix:
1. In the portal pending_dm_review state, the DM should be able to edit the submitted answers (editable textareas) before sending to the admin. Right now they are read-only plain text.
2. When the admin clicks "Generate with AI", we get "AI returned an invalid workflow structure." The markdown fence stripping regex in run_journey_ai_round1() and run_journey_ai_round2() needs to handle multiline responses.

Full details for both bugs (exact code locations, suggested fixes, affected files) are in NEXT-SESSION-PROMPT-v1.33.12.md.

After fixing both bugs: bump to v1.33.12, build ZIP, commit, push.
```
