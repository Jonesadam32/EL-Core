# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** April 11, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.38.39
> **Git Status:** All committed. Clean working tree.
> **ZIP in Downloads:** `el-core-v1.38.39.zip` — deployed and confirmed working.

---

## CRITICAL: READ SPEC FILE FIRST

The authoritative build spec is in the project root:

```
SPEC-EL-Core-Bookkeeping-Delta-v2_6.md
```

It contains all table schemas, AJAX patterns, feature specs (A–E), and kickoff prompts. Do not make assumptions about DB structure — read the spec.

---

## WHAT WAS DONE THIS SESSION (April 11, 2026)

### v1.38.36 — Phase A.3: 1099-NEC Entry Form
- **"+ 1099" button** on each client row — opens 1099 form with client pre-selected
- **Section C: 1099-NEC Entry Form** — Client, Tax Year, Document Status (radio: received/missing/substitute), Box 1 Amount, Date Received, 1099 doc file upload, Substitute docs textarea + Calculate from Deposits button, Reconciliation Status, Notes
- **Section D: 1099-NEC Records table** — all records with status badges, View doc link, Edit/Delete
- AJAX: `bk_get_1099s`, `bk_save_1099`, `bk_delete_1099`, `bk_calculate_1099_from_deposits`
- File upload via WP `media_handle_upload` → stores attachment ID in `document_attachment_id`

### v1.38.37 — Add IRS Form 4852 Upload
- DB migration v7: `form_4852_attachment_id bigint(20)` column added to `el_bk_1099_nec`
- Form 4852 upload field in substitute-row (visible for missing/substitute status only)
- Form 4852 column in Section D records table
- `handle_save_1099` handles both `nec_doc_file` and `form_4852_file` uploads

### v1.38.38 — Phase A.4: Income Tab — Client Assignment
- **Client column** added to income transactions table
- **Inline assign dropdown** — assign any income transaction to a client
- **Unassign badge** — click to remove client assignment
- **Reconciliation widget** — shows matched deposit total vs. 1099 Box 1 amount
- AJAX: `bk_assign_client_to_transaction`, `bk_unassign_client` (+ `bk_get_clients` used for dropdown)
- `$prefetch_clients` now loads for BOTH `clients` AND `income` tabs

### v1.38.39 — Critical Bug Fixes
- **Fix 1:** `$prefetch_clients` was calling `get_clients('active')` — filtered out Inactive/Completed clients entirely. Fixed: now calls `get_clients()` with no filter (all clients always load).
- **Fix 2:** `get_transactions()` had default `LIMIT 500` — cut off January expenses when a year had >500 transactions. Fixed: raised to `LIMIT 5000`.

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
| **v1.38.39** | Fix: all-clients load + transaction limit 5000 | ✅ **CURRENT** |

---

## WHAT'S WORKING ✅

### Clients / 1099 Tab (Phases A.2 + A.3 — Complete)
- Add, edit, delete clients (all statuses visible)
- Bank deposit pattern tags — add/remove, persist on save
- Status badges: Active (green), Inactive (gray), Completed (blue)
- Live search + status dropdown filter
- **Section C:** 1099-NEC entry form with all fields, conditional display, file uploads
- **Section D:** 1099 records table with doc-status + reconciliation badges, Form 4852 link
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
- Loads up to **5,000 transactions** per year (was 500 — now fixed)

### Receipts Tab
- Upload zone, AI extraction, review queue, manual entry form
- All Receipts table — thumbnail, inline location edit, status badge
- **Find Match** — 3-tier: exact → contains → AI fuzzy (≤5 candidates)

### All Other Tabs
- **Dashboard** — stat cards, year-filtered
- **Profit & Loss** — server-rendered, date range presets
- **Contractors** — directory + assignments, year-filtered
- **Known Expenses** — AI chat rule builder, CSV import, manual CRUD, drag-reorder
- **Travel Dates** — CRUD, year-filtered, Re-Apply Travel Rules
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
bank_patterns of all active clients. If a match is found, set client_id on that transaction.

bank_patterns is a newline-delimited text field on el_bk_clients.
Match logic: case-insensitive contains check (strpos/stripos).
If multiple clients match, assign the first match (most specific patterns should be listed first).

No new AJAX endpoints needed. No UI changes. Runs silently during import.
```

---

## KEY FILES

| File | Purpose |
|------|---------|
| `el-core/el-core.php` | Main plugin file — version number (TWO places: header + constant) |
| `el-core/modules/bookkeeping/class-bookkeeping-module.php` | All PHP logic (~2800 lines) |
| `el-core/modules/bookkeeping/module.json` | DB schema + migrations (currently **v7**) |
| `el-core/modules/bookkeeping/admin/views/clients.php` | Clients / 1099 tab view |
| `el-core/modules/bookkeeping/admin/views/income.php` | Income tab view |
| `el-core/modules/bookkeeping/admin/views/expenses.php` | Expenses tab view |
| `el-core/modules/bookkeeping/admin/views/receipts.php` | Receipts tab view |
| `el-core/modules/bookkeeping/assets/js/bookkeeping.js` | All JS (~2000 lines) |
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
- **`module.json` `"version"` is the DB schema version** (currently v7) — plugin version lives in `el-core.php`.

### PHP / WordPress Patterns
- **`$this->core->ai->complete()` returns an ARRAY** — `['success' => bool, 'content' => string, 'error' => string]`. NOT a raw string.
- **`EL_AJAX_Handler::success($data, $message)` wraps response** — JS receives `{ data: $data, message: $message }` as `res.data`. Always unwrap before `.forEach()`.
- **`EL_Admin_UI::notice()` takes an array**: `notice( ['message' => '...', 'type' => 'info'] )` NOT `notice('...', 'info')`.
- **Voice input (Wispr)**: NO `required` HTML5 attributes, NO input masks anywhere in the bookkeeping module.
- **`media_handle_upload('field_name', 0)`** requires `require_once` of file.php, image.php, media.php from wp-admin/includes.

### JavaScript
- **jQuery `.data()` vs `.attr()`** — when setting HTML attributes with `.attr('data-foo', val)`, always READ with `.attr('data-foo')` too. jQuery `.data('foo')` caches on first read — on dynamic elements the cache is empty, returning `undefined`.
- **JSON-encode array data attributes** — use `wp_json_encode($array)` in PHP and `JSON.parse(el.attr('data-foo'))` in JS. Never newline-delimited.
- **FormData AJAX** (for file uploads): `processData: false, contentType: false`. Standard `elBkAjax()` doesn't support file uploads — use raw `$.ajax()` with FormData.

### Domain Knowledge
- **`el_bk_clients` = entities that PAY Fred** (1099-NEC issuers). **`el_bk_contractors` = people Fred PAYS**. COMPLETELY SEPARATE. Never mix them.
- **Receipt status values** are `'unmatched'` and `'matched'` — NOT `'unreviewed'`.
- **`receipt_id` column on `wp_el_bk_transactions`**: DB migration v5
- **`client_id` column on `wp_el_bk_transactions`**: DB migration v6
- **`form_4852_attachment_id` column on `wp_el_bk_1099_nec`**: DB migration v7
- **Receipt match engine (v1.38.30)**: 3-tier — (1) exact name, (2) contains, (3) AI with ≤5 word-filtered candidates. Tax year from receipt date, not UI selector. Date window: receipt date + 10 days forward only.
- **Calculate from Deposits** sums `el_bk_transactions` WHERE `client_id = X AND tax_year = Y AND type = 'income'` — requires transactions to have `client_id` assigned first (Phase A.4).
- **Mobile deposits** show as "Mobile Deposit" in bank CSVs — no client name to pattern-match. Must be manually assigned via Income tab (Phase A.4) before Calculate from Deposits works for them.
