# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** April 12, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.38.45
> **Git Status:** All committed. Clean working tree.
> **ZIP in Downloads:** `el-core-v1.38.45.zip` — deployed and confirmed working.

---

## CRITICAL: READ SPEC FILE FIRST

The authoritative build spec is in the project root:

```
SPEC-EL-Core-Bookkeeping-Delta-v2_6.md
```

It contains all table schemas, AJAX patterns, feature specs (A–E), and kickoff prompts. Do not make assumptions about DB structure — read the spec.

---

## WHAT WAS DONE THIS SESSION (April 12, 2026)

### v1.38.40 — Supporting Doc Upload + Web Design/License Contract Type
- **DB migration v8**: added `supporting_doc_attachment_id bigint(20)` and `supporting_doc_title varchar(255)` to `el_bk_1099_nec`
- **Supporting Document Title** field + **Supporting Document Upload** (PDF/JPG/PNG) added to substitute-row in 1099-NEC form
- **Section D table** now shows "Supporting Doc" column with titled link
- **Web Design/License** added to contract type list on Clients tab

### v1.38.41 — Fix Bank Pattern Tags Collapsing on Edit
- **Root cause:** AJAX handler's `sanitize_input()` runs `sanitize_text_field` on ALL POST fields, which strips newlines. Pattern tags joined with `\n` became one space-separated string in the DB.
- **Fix:** Changed delimiter from `\n` to `|` (pipe survives `sanitize_text_field`) in both JS (`elBkRebuildPatternHidden`) and PHP (`explode` in clients.php row rendering).
- **Note:** Any patterns saved before this fix are stored as one merged tag — user needs to re-enter them once.

### v1.38.42 — Clickable Date Column Sort on Expenses Tab
- **Date** column header now has ▼/▲ arrow and is clickable
- Click toggles between newest-first (▼) and oldest-first (▲)
- Pure client-side sort — no AJAX, works alongside all filters

### v1.38.43 — Remove Travel Date Auto-Classification
- **Root cause of misclassification:** travel date check ran FIRST in `auto_classify`, overriding known expense rules for any transaction within a travel period. Apple.com/Bill was being stamped "Travel Expense" instead of "Owner Expense".
- **Removed:** `match_travel_period()`, `map_travel_category()`, `handle_reapply_travel_rules()`, AJAX registration, Re-Apply Travel Rules button, Auto-Category Mapping reference table
- **Kept:** Travel Dates tab and CRUD (records preserved for IRS documentation)
- **Result:** Known expense rules are now the ONLY auto-classification step

### v1.38.44 — "Make Rule" Button on Every Expense Row
- **`+ Rule`** button added to Actions column on every expense row
- Click opens a popover with keyword (pre-filled from merchant) + category dropdown (pre-filled from current category)
- **Conflict detection:** on open and as you type, checks `bk_check_rule_conflict` — shows yellow warning if an existing rule will be replaced
- **Save behavior:** deletes any conflicting rules, inserts new rule at priority 0 (top of list), reclassifies the current row immediately — no page reload
- **New AJAX:** `bk_check_rule_conflict`, `bk_quick_save_rule`

### v1.38.45 — Fix Make Rule Popover Position
- Popover was rendering off-screen when page was scrolled (used document-relative `offset()` with `position:fixed`)
- Fixed by subtracting `scrollTop`/`scrollLeft` so popover appears correctly at viewport position

---

## VERSION HISTORY

| Version | What | Status |
|---------|------|--------|
| v1.38.30 | Rewrite: 3-tier receipt match engine | ✅ Deployed |
| v1.38.31–32 | Pick Manually fallback + Receipt badge panel in Expenses | ✅ Deployed |
| v1.38.33 | Phase A.1 + A.2: DB setup + Clients tab | ✅ Deployed |
| v1.38.34 | Fix: Pattern tag remove + save (JSON attr, parent()) | ✅ Deployed |
| v1.38.35 | Fix: Pattern tag save (.attr vs .data jQuery cache) | ✅ Deployed |
| v1.38.36 | Phase A.3: 1099-NEC Entry Form | ✅ Deployed |
| v1.38.37 | Add Form 4852 upload (DB v7) | ✅ Deployed |
| v1.38.38 | Phase A.4: Income Tab Client Assignment | ✅ Deployed |
| v1.38.39 | Fix: all-clients load + transaction limit 5000 | ✅ Deployed |
| v1.38.40 | Supporting doc upload + title (DB v8) + Web Design/License | ✅ Deployed |
| v1.38.41 | Fix: bank pattern tags collapsing (pipe delimiter) | ✅ Deployed |
| v1.38.42 | Clickable Date sort on Expenses tab | ✅ Deployed |
| v1.38.43 | Remove travel date auto-classification | ✅ Deployed |
| v1.38.44 | Make Rule button on expense rows | ✅ Deployed |
| **v1.38.45** | Fix Make Rule popover scroll position | ✅ **CURRENT** |

---

## WHAT'S WORKING ✅

### Clients / 1099 Tab (Phases A.2 + A.3 — Complete)
- Add, edit, delete clients (all statuses visible)
- Bank deposit pattern tags — add/remove, persist on save (**pipe-delimited**)
- Status badges: Active (green), Inactive (gray), Completed (blue)
- Live search + status dropdown filter
- Contract types include: Consulting, Training, Curriculum Development, Coaching, Facilitation, **Web Design/License**, Other
- **Section C:** 1099-NEC entry form — all fields, conditional display, file uploads (1099 doc, Form 4852, **Supporting Doc with title**)
- **Section D:** 1099 records table with doc-status + reconciliation badges, Form 4852 link, **Supporting Doc link**
- **Calculate from Deposits** — sums client+year income transactions

### Income Tab (Phase A.4 — Complete)
- Transaction table with **Client column**
- Inline client assignment dropdown
- Unassign badge/button
- Reconciliation widget (matched deposits vs. 1099 Box 1)

### Expenses Tab
- Filter bar (search, category, bank, status, date range)
- Inline editing, lock/reject/reclassify
- B/P summary, receipt badge panel, Pick Manually fallback
- Loads up to **5,000 transactions** per year
- **Date column sort toggle** (▼/▲) — click header to flip newest/oldest
- **`+ Rule` button** on every row — creates a known expense rule from the merchant, with conflict detection and immediate override

### Known Expenses Tab
- AI chat rule builder, CSV import, manual CRUD, drag-reorder
- Rules are now the **only** auto-classification step (travel date override removed)
- Rules saved via `+ Rule` on expense rows go to **top of priority list** (priority 0) so they fire first

### Travel Dates Tab
- CRUD for travel periods (data preserved for IRS documentation)
- **Auto-classification removed** — travel dates no longer affect expense categorization

### Receipts Tab
- Upload zone, AI extraction, review queue, manual entry form
- All Receipts table — thumbnail, inline location edit, status badge
- **Find Match** — 3-tier: exact → contains → AI fuzzy (≤5 candidates)

### All Other Tabs
- **Dashboard** — stat cards, year-filtered
- **Profit & Loss** — server-rendered, date range presets
- **Contractors** — directory + assignments, year-filtered
- **Settings** — business info, default tax year

---

## WHAT'S NEXT (Priority Order from SPEC)

1. ✅ ~~Phase 6 — Receipt Upload + AI Extraction~~ (v1.38.12)
2. ✅ ~~Feature B — Manual Receipt Entry Form~~ (v1.38.13)
3. ✅ ~~Feature E — Receipt Auto-Match Engine~~ (v1.38.30)
4. ✅ ~~Feature A — Phase A.1: Database Setup~~ (v1.38.33)
5. ✅ ~~Feature A — Phase A.2: Clients Tab CRUD~~ (v1.38.35)
6. ✅ ~~Feature A — Phase A.3: 1099-NEC Entry Form~~ (v1.38.36)
7. ✅ ~~Feature A — Phase A.4: Income Tab Client Assignment~~ (v1.38.38)
8. **Feature A — Phase A.5: Auto-Match Patterns** ← NEXT
   - On CSV import, scan deposit descriptions against each client's `bank_patterns`
   - Auto-assign `client_id` to matching income transactions
   - No UI changes needed — runs during import
   - **Note:** `bank_patterns` is now pipe-delimited (`|`), not newline-delimited — use `explode('|', ...)` not `explode("\n", ...)`
9. **Feature A — Phase A.6: Reconciliation Views**
   - Section D: Per-client reconciliation detail (deposits vs. 1099)
   - Section E: Annual income summary across all clients
10. **Phase 7 — P&L Report Generation + Export**
11. **Feature C — Receipt CSV Bulk Import**
12. **Feature D — AI Email Receipt Agent (Gmail)**

---

## KICKOFF PROMPT FOR PHASE A.5 (Auto-Match Patterns)

```
Read SPEC-EL-Core-Bookkeeping-Delta-v2_6.md (Phase A.5 section).
Then read:
- modules/bookkeeping/class-bookkeeping-module.php (handle_csv_import and get_clients)
- modules/bookkeeping/module.json (confirm el_bk_clients schema has bank_patterns column)

Implement Phase A.5 — Auto-Match Bank Patterns:
When income transactions are imported via CSV (handle_csv_import / handle_import_ledger),
after each income transaction is saved, check its merchant/description against the
bank_patterns of all clients. If a match is found, set client_id on that transaction.

IMPORTANT: bank_patterns is now PIPE-delimited (|), NOT newline-delimited.
Use: explode('|', $client->bank_patterns) — NOT explode("\n", ...)
Match logic: case-insensitive contains check (stripos).
If multiple clients match, assign the first match (most specific patterns should be listed first).

No new AJAX endpoints needed. No UI changes. Runs silently during import.
```

---

## KEY FILES

| File | Purpose |
|------|---------|
| `el-core/el-core.php` | Main plugin file — version number (TWO places: header + constant) |
| `el-core/modules/bookkeeping/class-bookkeeping-module.php` | All PHP logic (~2900 lines) |
| `el-core/modules/bookkeeping/module.json` | DB schema + migrations (currently **v8**) |
| `el-core/modules/bookkeeping/admin/views/clients.php` | Clients / 1099 tab view |
| `el-core/modules/bookkeeping/admin/views/income.php` | Income tab view |
| `el-core/modules/bookkeeping/admin/views/expenses.php` | Expenses tab view |
| `el-core/modules/bookkeeping/admin/views/receipts.php` | Receipts tab view |
| `el-core/modules/bookkeeping/assets/js/bookkeeping.js` | All JS (~2200 lines) |
| `el-core/modules/bookkeeping/assets/css/bookkeeping.css` | All CSS (~1700 lines) |
| `el-core/includes/class-ai-client.php` | AI client — `complete()` and `complete_with_image()` |
| `el-core/includes/class-database.php` | DB manager — `process_module_schema`, `create_table`, migrations |
| `build-zip.ps1` | Build script — update `$version` here too |
| `SPEC-EL-Core-Bookkeeping-Delta-v2_6.md` | **Authoritative feature spec** |

---

## CRITICAL LESSONS LEARNED

### Version Management
- **ALWAYS run `git log --oneline -5` BEFORE bumping version** — `el-core.php` and `build-zip.ps1` are the source of truth, NOT this doc. The doc can be stale by one or more versions.
- **Never build a ZIP under a version already in git** — check git log, not Downloads folder.
- **`module.json` edits: ALWAYS use `Write` tool, never `StrReplace`** — partial replacements leave orphaned JSON blocks. After editing, always verify with `ConvertFrom-Json`.
- **PowerShell `Compress-Archive` breaks ZIP** — always use `build-zip.ps1` (.NET ZipFile API).
- **PowerShell doesn't support `&&`** — use separate commands or `;`.

### Database & Data Safety
- **Plugin uploads reset `el_core_schema_versions`** via activation hook (line 75 of el-core.php) — this is expected, intentional, and safe. `dbDelta` never deletes rows; it only adds missing columns.
- **Data that appears "missing" after an upload is NEVER actually deleted** — it is always a query filter or LIMIT issue. Check filters before assuming data loss.
- **`get_transactions()` default LIMIT is 5000** — if a year has >5000 transactions, oldest will be cut off. Pass explicit `limit` arg if needed.
- **`get_clients()` must be called with NO status filter for the Clients tab prefetch** — `get_clients('active')` hides Inactive/Completed clients. Always use `get_clients()` for full-page prefetch.
- **DB migrations go in `module.json`** — bump `"version"` and add `"migrations": { "N": "ALTER TABLE..." }`. The `EL_Database` class runs them automatically on next admin page load.
- **`module.json` `"version"` is the DB schema version** (currently **v8**) — plugin version lives in `el-core.php`.
- **After uploading a new plugin version, if new AJAX actions return 404**, deactivate and reactivate the plugin. This clears PHP opcode cache on the server and forces hooks to re-register.

### PHP / WordPress Patterns
- **`$this->core->ai->complete()` returns an ARRAY** — `['success' => bool, 'content' => string, 'error' => string]`. NOT a raw string.
- **`EL_AJAX_Handler::success($data, $message)` wraps response** — JS receives `{ data: $data, message: $message }` as `res.data`. Always unwrap before `.forEach()`.
- **`EL_Admin_UI::notice()` takes an array**: `notice( ['message' => '...', 'type' => 'info'] )` NOT `notice('...', 'info')`.
- **Voice input (Wispr)**: NO `required` HTML5 attributes, NO input masks anywhere in the bookkeeping module.
- **`media_handle_upload('field_name', 0)`** requires `require_once` of file.php, image.php, media.php from wp-admin/includes.
- **`sanitize_input()` in `EL_AJAX_Handler` uses `sanitize_text_field` on ALL POST values** — this strips newlines (`\n`), tabs, and other control characters. Any multi-value field must use a pipe (`|`) or other non-stripped delimiter. NEVER use newlines as a delimiter for POST data.

### JavaScript
- **jQuery `.data()` vs `.attr()`** — when setting HTML attributes with `.attr('data-foo', val)`, always READ with `.attr('data-foo')` too. jQuery `.data('foo')` caches on first read — on dynamic elements the cache is empty, returning `undefined`.
- **JSON-encode array data attributes** — use `wp_json_encode($array)` in PHP and `JSON.parse(el.attr('data-foo'))` in JS. Never newline-delimited.
- **FormData AJAX** (for file uploads): `processData: false, contentType: false`. Standard `elBkAjax()` doesn't support file uploads — use raw `$.ajax()` with FormData.
- **`position:fixed` popover positioning** — always subtract `$(window).scrollTop()` / `$(window).scrollLeft()` from `$el.offset()` when positioning a fixed element near a document-position-based anchor.

### Domain Knowledge
- **`el_bk_clients` = entities that PAY Fred** (1099-NEC issuers). **`el_bk_contractors` = people Fred PAYS**. COMPLETELY SEPARATE. Never mix them.
- **Receipt status values** are `'unmatched'` and `'matched'` — NOT `'unreviewed'`.
- **`receipt_id` column on `wp_el_bk_transactions`**: DB migration v5
- **`client_id` column on `wp_el_bk_transactions`**: DB migration v6
- **`form_4852_attachment_id` column on `wp_el_bk_1099_nec`**: DB migration v7
- **`supporting_doc_attachment_id` + `supporting_doc_title` on `wp_el_bk_1099_nec`**: DB migration v8
- **`bank_patterns` is pipe-delimited (`|`)** — was newline-delimited before v1.38.41. Use `explode('|', $bank_patterns)` everywhere.
- **Receipt match engine (v1.38.30)**: 3-tier — (1) exact name, (2) contains, (3) AI with ≤5 word-filtered candidates. Tax year from receipt date, not UI selector. Date window: receipt date + 10 days forward only.
- **Calculate from Deposits** sums `el_bk_transactions` WHERE `client_id = X AND tax_year = Y AND type = 'income'` — requires transactions to have `client_id` assigned first (Phase A.4).
- **Mobile deposits** show as "Mobile Deposit" in bank CSVs — no client name to pattern-match. Must be manually assigned via Income tab (Phase A.4) before Calculate from Deposits works for them.
- **Travel dates no longer affect expense categorization** (removed in v1.38.43). Known expense rules are the sole auto-classification mechanism.
- **`+ Rule` button on expense rows** (`bk_quick_save_rule`) inserts at priority 0 and shifts all other rules up — guarantees the new rule fires first on future imports.
