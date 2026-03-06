# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** March 6, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** 1.25.0 — Invoicing Phase 6A Step 6 (Revenue Dashboard + Export) done ✅. Revenue metrics, by product/client/month, CSV export. View As from Client profile + client invoices page. **Next:** Deploy v1.25.0 checkpoint; then Phase 6B or other. v1.22.0 Definition Consensus Review System backend DONE, UI NOT YET BUILT.

---

## ⚠️ ARCHITECTURE CHANGES — READ BEFORE ANYTHING ELSE

**`ARCHITECTURE-DECISIONS-FEB-22-2026.md`** (repo root) contains major architectural decisions made February 22, 2026 that affect how all future modules are built. Read it before starting any session. Key changes:

- Expand Site is now proprietary — strip configurability settings (stage names, feature toggles)
- PM module is a task aggregator only — owns shared `el_tasks` table, not a project system
- CRM module cancelled — use Fluent CRM instead
- Client Portals module cancelled — each program owns its portals
- Public Website module cancelled — replaced by EL Theme and EL Learning Theme (monorepo)
- Shared `el_projects` table architecture planned for cross-program project tracking

---

## THE MASTER CHECKLIST

**`CURSOR-TODO.md`** (repo root) is the single source of truth for all build work.
- Check off items with `[x]` as you complete them
- Never start a new phase until the current phase is fully checked off and tested
- If Fred asks "what's the list" or "where are we" — that file is the answer
- Update it at the end of every session

---

## CURRENT STATE

### Deployed
- **EL Core v1.21.4** on staging (qd19d0iehj-staging.wpdns.site) — uploaded and tested ✅

### What Was Completed This Session (February 24, 2026)

**Bugfix sprint (v1.21.1 → v1.21.4):**

- **v1.21.1**: Bumped version so WordPress would replace files on upload (same-version uploads are ignored)
- **v1.21.2**: Fixed double-escaping/slashes bug on discovery transcript — `sanitize_text_field()` in the AJAX handler was stripping newlines and adding slashes before the handler ran `sanitize_textarea_field()`. Fixed by reading textarea fields directly from `$_POST` with `wp_unslash()`. Also added `wp_unslash()` when loading transcript into admin textarea.
- **v1.21.3**: Fixed `site_type` DB error — AI was returning values longer than VARCHAR(50). Widened to VARCHAR(100) via DB migration (schema version 7). Added `substr()` safety cap in PHP handler.
- **v1.21.4**: Removed debug DB error code from `handle_save_definition` — save definition is now working correctly.

**Design decisions made this session:**

- **Generic feedback card removed** — feedback must be contextual to each stage, not a global dump
- **Definition review is a full consensus workflow** — not just "leave a comment"
- **Silence = abstention** — if a contributor doesn't respond by deadline, DM decides without them
- **DM verdict = structured button (Accept/Needs Revision) + optional note** — scannable for admin
- **Per-field inline comments** — not a modal, anchored to the content being reviewed
- **Scroll-depth gate on Approve** — button disabled until client has scrolled past all fields
- **Threaded replies per field** — functions like chat but stays anchored to source (Google Docs pattern)
- **Reminder at 50% of deadline and 24h before** — contributors notified, never block DM
- **Build in full now** — no simplified first version

### What's IN PROGRESS — v1.22.0 (Definition Consensus Review System)

**Half built — pick up here next session.**

#### ✅ DONE in this session:
- **DB schema (version 8)**:
  - `review_status` column added to `el_es_project_definition` (`draft` / `pending_review` / `approved` / `needs_revision` / `locked`)
  - New table `el_es_definition_reviews` — one row per send-for-review round (project_id, round, sent_by, deadline, status, dm_decision, dm_note, dm_decided_at)
  - New table `el_es_definition_comments` — threaded per-field comments (review_id, field_key, parent_id, user_id, comment, verdict)
  - Both tables added to `module.json` tables section AND migration "8"
- **PHP AJAX handlers** (all in `class-expand-site-module.php`):
  - `get_active_definition_review()` — query helper
  - `get_definition_reviews()` — all rounds for a project
  - `get_definition_comments()` — returns tree keyed by field_key with nested replies
  - `get_definition_verdicts()` — tally per field_key
  - `handle_send_definition_review` — admin sends draft for review, creates new round, closes previous
  - `handle_get_definition_review` — loads all review data for portal (definition + comments + verdicts + timer + user's own verdicts)
  - `handle_post_definition_comment` — post a comment or reply on a specific field
  - `handle_field_verdict` — upsert contributor's verdict per field (approved / needs_revision)
  - `handle_dm_decision` — DM submits final decision, closes review, updates definition status
  - All AJAX hooks registered including `nopriv` variants for logged-in stakeholders on frontend

#### ❌ NOT YET BUILT — start here next session:
1. **Admin UI** (`project-detail.php` Discovery tab):
   - Definition status badge (Draft / Sent for Review / Client Approved / Needs Revision / Locked)
   - "Send to Client for Review" button with deadline date picker
   - Per-field stakeholder comments panel (admin sees all comments while editing)
   - DM verdict summary card ("6 fields accepted, 1 needs revision")
   - Lock button always visible (override), with confirmation prompt if not yet approved

2. **Client Portal** (`expand-site-portal.php` Stage 1):
   - Replace current locked-only definition display with full consensus UI
   - Countdown timer showing time remaining in review period
   - Per-field layout: field value → thread of comments → Reply button → "+ Add comment" toggle → "✓ Looks good" / "Needs revision" verdict buttons
   - "Updated" badge on fields changed since last round
   - Scroll-depth gate: Approve All button disabled until scrolled past all fields
   - Overall comment box at bottom
   - "Submit My Input" button (contributors)
   - "Make Final Decision" section (DM only) — Accept / Needs Revision + note field
   - Post-decision states: approved banner, needs-revision banner with DM note

3. **Admin-side JS** (`expand-site-admin.js`):
   - Send for Review form submit handler (with deadline)
   - Comments panel refresh after DM decision

4. **Portal-side JS** (`expand-site.js`):
   - Load review data on page load via `es_get_definition_review`
   - Post comment (inline expand/collapse per field)
   - Reply to comment (nested)
   - Field verdict buttons (optimistic UI, single selection per field)
   - Scroll-depth tracker → enable Approve All
   - Countdown timer (live update every second)
   - DM final decision form submit
   - Handle all post-decision state rendering

5. **CSS** (`expand-site.css` + `admin.css`):
   - Review status badge styles
   - Per-field comment thread layout
   - Reply nesting indentation
   - Verdict buttons (approved = green, needs_revision = amber)
   - Countdown timer widget
   - Scroll-depth-gated approve button states
   - DM decision section (visually distinct)
   - Admin comments panel in project-detail

---

## WHAT'S NEXT AFTER v1.22.0

**Step 4 — Brand Palette Voting in portal (v1.23.0)**
- Admin triggers AI logo analysis from project detail Branding tab → generates 3 palettes
- Palettes appear in portal as 3 cards (swatches, fonts, rationale)
- Stakeholders vote: Prefer / Neutral / Don't Prefer
- DM: "Lock Brand Colors" → saves selection to `el_core_brand`, locks palette section

**Step 6 — Wireframe Annotation (Phase 2H — separate session, do not build yet)**

**Invoicing module** (Phase 6A — Step 6 done ✅)
- Full build spec: **`docs/cursor-handoff-invoicing-module.md`** (canonical copy in repo)
- **Done:** Step 1–6 (DB, products, CRUD, payments, Send & Client Portal, Revenue Dashboard + CSV export). View As from Clients page.
- Replaces QuickBooks; Phase 6A complete. Next: Phase 6B (Expand Partners) or deploy checkpoint.

---

## CRITICAL LESSONS LEARNED

- Module loader (`class-module-loader.php`) already loads shortcodes from `module.json` — NEVER add `add_shortcode()` in the module class
- Module class should NOT load shortcode files — module loader does this
- If module fails to load, it AUTO-DEACTIVATES (lines 152-168 of module loader) — check error log
- Always bump version number for EVERY deployment, no exceptions
- **`EL_Admin_UI::form_row()` now supports custom `id` parameter** — always pass `'id'` when JS needs to target the field by ID
- **Admin brand page is ELS's tool only** — per-client branding happens inside Expand Site portal workflow
- **`sanitize_text_field()` strips newlines** — never use it on textarea/transcript content; use `sanitize_textarea_field( wp_unslash( $_POST['field'] ) )` reading directly from `$_POST`, not from the pre-sanitized `$data` array passed to handlers
- **Same-version ZIP uploads are ignored by WordPress** — always bump version before building ZIP
- **`$wpdb->update()` returns `0` (not false) when data is unchanged** — `0 !== false` so treat as success
- **VARCHAR(50) is too small for AI-generated site_type values** — now VARCHAR(100)

---

## VERSION HISTORY

| Version | What Changed | Status |
|---------|-------------|--------|
| v1.14.7 | Client Portal UX Redesign | Deployed ✅ |
| v1.15.0 | Phase 2F-B: Proposal / Scope of Service System | Built ✅ |
| v1.15.1 | Fix AI proposal generation + edit modal population bug | Built ✅ |
| v1.16.0 | Proposal Narrative Redesign | Built ✅ |
| v1.17.0 | Phase 2F-D: Payment Terms & T&C Settings | Uploaded & Tested ✅ |
| v1.18.0 | Phase 2F-E: Organizations & Client Management | Built ✅ |
| v1.18.1 | Fix: Clients JS form submission + script load order | Uploaded ✅ |
| v1.18.2 | Fix: Edit Contact missing Portal Access field | Built ✅ |
| v1.18.3 | Fix: Primary contacts auto-get portal access | Built ✅ |
| v1.18.4 | Add Stakeholder modal shows org contacts for quick-add | Built ✅ |
| v1.18.5 | Fix: Portal header badge shows correct role (DM vs Contributor) | Uploaded & Tested ✅ |
| v1.18.6 | WP toolbar hidden for clients + Switch Back bar + Login As on contacts | Built ✅ |
| v1.18.7 | Login As / Switch Back fully built into EL Core (no plugin) | Built ✅ |
| v1.18.8 | Fix: Login As was logging in as self (wrong hook) | Built ✅ |
| v1.18.9 | Revert Login As to User Switching plugin (working) + switch-back bar | Built ✅ |
| v1.18.10 | Deferred Login As/Switch Back — staging URL issue must be fixed first | Built ✅ |
| v1.18.11 | Reverted to v1.18.5 baseline — all switch-back attempts removed | Uploaded & Tested ✅ |
| v1.19.0 | Phase 2G: Branding Workflow — CSS token expansion, AI vision (admin page overcomplicated) | Built ✅ |
| v1.19.1 | Fix: Simplified brand settings page — removed AI/Pickr (belongs in portal, Phase 2G-B) | Built ✅ |
| v1.19.2 | Remove Pipeline Progress card from project detail page | Uploaded & Tested ✅ |
| v1.20.0 | Phase 2G-B Step 1: Database schema (4 tables, 3 capabilities) | Deployed ✅ |
| v1.20.1 | Phase 2G-B Step 2: Template Library admin page | Built ✅ |
| v1.20.2 | Fix: schema_versions cast to array (PHP 8.1 strict typing) | Built ✅ |
| v1.20.3 | Fix: wp_enqueue_media + JS DOM ready guard | Built ✅ |
| v1.20.4 | Fix: AJAX action name el_core_ajax → el_core_action | Built ✅ |
| v1.20.5 | Fix: Template cards uniform height (flex column, fixed image) | Uploaded & Tested ✅ |
| v1.21.0 | Phase 2G-B Steps 3 & 5: Mood Board portal + Admin Review Management | Built ✅ |
| v1.21.1 | Force version bump so WordPress replaces files on upload | Built ✅ |
| v1.21.2 | Fix: Double-escaping/slashes on transcript + definition fields (wp_unslash + direct $_POST reads) | Built ✅ |
| v1.21.3 | Fix: site_type VARCHAR(50→100) + DB migration schema v7 | Built ✅ |
| v1.21.4 | Fix: Remove debug DB error output from handle_save_definition | Uploaded & Tested ✅ |
| v1.22.0 | Definition Consensus Review System (DB schema v8 + PHP handlers done; UI in progress) | IN PROGRESS 🔨 |

---

## HOW TO START A SESSION

When you open a new chat, paste one of these:

**Expand Site workstream** (shortcodes, CSS, JS, module features):
```
Read @START-HERE-NEXT-SESSION.md. I'm working on the Expand Site workstream.
```

**Core workstream** (Canvas, Admin UI, infrastructure):
```
Read @START-HERE-NEXT-SESSION.md. I'm working on the Core workstream.
```

**Invoicing module workstream:**
```
Read @START-HERE-NEXT-SESSION.md and @docs/cursor-handoff-invoicing-module.md. Phase 6A Step 3 is in CURSOR-TODO.md. Start with Step 3 (Invoice CRUD — invoice list + editor, line items, AJAX, ELS-YYYY-NNN numbers).
```

---

## DEPLOYMENT RULES

- Cursor runs `build-zip.ps1` from repo root when a deployment build is needed (uses .NET ZipFile, NOT Compress-Archive)
- Upload `el-core.zip` via WordPress Admin → Plugins → Add New → Upload Plugin
- Version bump: update plugin header AND `EL_CORE_VERSION` constant AND `build-zip.ps1` (THREE places)
- Update `CHANGELOG.md` with every version bump

---

## DECISIONS — FINAL, DO NOT RE-DEBATE

- Module is `expand-site` (not `project-management` — that module is deleted)
- All Expand Site tables use `el_es_` prefix
- Asset files: `expand-site.css`, `expand-site.js`
- CSS class prefix: `el-es-` for all Expand Site components
- Admin UI uses `EL_Admin_UI::*` exclusively — no raw HTML
- Deploy via ZIP only — Cursor runs `build-zip.ps1` when needed, upload through WP Admin
- ZIP filename: always `el-core.zip` (no version number)
- WordPress MCP is NOT connected — no wp_fs_write or MCP tools
- Canvas page system is core infrastructure, not a module
- All monolith development (Bold Youth, ELS) is frozen — EL Core only
- **Proposals are built INTO Expand Site** — not a standalone module
- **Module loader handles shortcodes** — NEVER add add_shortcode() in module class
- **Always bump version for every deployment** — no exceptions
- **Admin Brand page = ELS brand only** — per-client branding = Phase 2G-B in the portal
- **Generic feedback card is REMOVED from portal** — feedback is contextual to each stage
- **Definition review = full consensus workflow** — silence is abstention, DM has final say, admin can override-lock anytime
- **Textarea fields MUST use `sanitize_textarea_field( wp_unslash( $_POST['field'] ) )`** — never rely on pre-sanitized `$data` array for multiline content
