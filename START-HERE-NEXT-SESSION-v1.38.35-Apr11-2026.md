# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** April 11, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.38.35
> **Git Status:** All committed. Clean working tree.
> **ZIP in Downloads:** `el-core-v1.38.35.zip` — ready to upload.

---

## CRITICAL: READ SPEC FILE FIRST

The authoritative build spec is in the project root:

```
SPEC-EL-Core-Bookkeeping-Delta-v2_6.md
```

It contains all table schemas, AJAX patterns, feature specs (A–E), and kickoff prompts. Do not make assumptions about DB structure — read the spec.

---

## WHAT WAS DONE THIS SESSION (April 11, 2026)

### Phase A.1 — Database Setup (module.json → DB v6)
- Bumped `database.version` from 5 → 6
- Added `el_bk_clients` table (clients who PAY Fred — 1099-NEC issuers)
- Added `el_bk_1099_nec` table (annual 1099-NEC records per client)
- Added `client_id bigint(20) NOT NULL DEFAULT 0` to `el_bk_transactions` schema (fresh installs)
- Added migration `"6"`: `ALTER TABLE el_bk_transactions ADD COLUMN IF NOT EXISTS client_id bigint(20) NOT NULL DEFAULT 0`
- **CRITICAL:** `module.json` was edited with the `Write` tool (full file replacement), NOT `StrReplace`

### v1.38.33 — Phase A.2: Clients / 1099 Tab (Phase A.2 complete)
**What was built:**
- New tab **"Clients / 1099"** in the tab nav (9th tab, between Receipts and Settings)
- `admin/views/clients.php` — new view file:
  - **Section A:** Client list table — Client Name, Short Name, EIN, Contract Type, Status badge (color-coded), Bank Patterns (as tags), Edit/Delete actions; live search + status dropdown filter
  - **Section B:** Voice-friendly Add/Edit form — 2-column grid, all spec fields, bank pattern tag input
- `class-bookkeeping-module.php` — additions:
  - `'clients'` added to `$valid_tabs` and `$tabs` nav array
  - `$prefetch_clients` loaded when tab is active
  - `get_clients()` public method
  - AJAX hooks: `bk_get_clients`, `bk_save_client`, `bk_delete_client`
  - `handle_get_clients()`, `handle_save_client()`, `handle_delete_client()` methods
  - `handle_delete_client()` also clears `client_id` on linked transactions and deletes associated 1099 records
- `bookkeeping.js` — client CRUD JS, pattern tag add/remove, search + status filter
- `bookkeeping.css` — form grid, voice-input sizing (min 44px), pattern tag chips, status badges, danger button

### v1.38.34 — Fix: Pattern tag × button not removing; tags not populating on edit
- **Remove bug:** `$(this).closest('.el-bk-pattern-tag')` was ambiguous — changed to `$(this).parent()` (button is always a direct child of the tag span)
- **Save/edit bug:** Data attribute used literal newline delimiter (`\n`), unreliable in HTML attributes. Changed PHP to JSON-encode the patterns array (`wp_json_encode($patterns)`) and JS to parse with `JSON.parse()`. Also changed from `$btn.data()` to `$btn.attr()` to read the attribute.

### v1.38.35 — Fix: Pattern tags still not saving (jQuery `.data()` cache bug)
- Root cause: `elBkRebuildPatternHidden()` and the duplicate check used `$(this).data('value')` to read tag values, but the tags were created using `.attr('data-value', value)` to set them
- jQuery's `.data()` reads from the HTML attribute once then caches internally — in some contexts with dynamically created elements this cache is never populated, returning `undefined`
- Fix: Changed ALL reads to `$(this).attr('data-value')` which always reads the live DOM attribute. No caching involved.

---

## VERSION HISTORY — THIS SESSION

| Version | What | Status |
|---------|------|--------|
| v1.38.30 | Rewrite: 3-tier receipt match engine | Previous session — deployed |
| v1.38.31–32 | Add Pick Manually fallback + Receipt badge panel in Expenses | Previous session — deployed |
| **v1.38.33** | Phase A.1 + A.2: DB setup + Clients tab | ✅ Deployed |
| **v1.38.34** | Fix: Pattern tag remove + save (JSON attr, parent()) | ✅ Deployed |
| **v1.38.35** | Fix: Pattern tag save (.attr vs .data jQuery cache) | ✅ **CURRENT** |

---

## WHAT'S WORKING ✅

### Clients / 1099 Tab (Phase A.2 — New this session)
- Add, edit, delete clients
- Bank deposit pattern tags — add via input + Enter or Add button, remove with ×, persist on save
- Status badges: Active (green), Inactive (gray), Completed (blue)
- Live search by client name; status dropdown filter
- Voice-friendly form (min 44px inputs, no required attributes, no input masks)

### Receipts Tab
- Upload zone, AI extraction, review queue, manual entry form
- All Receipts table — thumbnail, inline location edit, status badge, attached transaction
- Year filter, full receipt edit (inline panel)
- **Find Match** — 3-tier engine: exact → contains → AI fuzzy (≤5 candidates)

### Income Tab
- Transaction table, inline editing, CSV upload, year-filtered, date range filter

### All Other Tabs
- **Dashboard** — stat cards, year-filtered
- **Expenses tab** — filter bar, inline editing, lock/reject/reclassify, B/P summary, receipt badge panel, Pick Manually fallback
- **Profit & Loss tab** — server-rendered, date range presets
- **Contractors tab** — directory + assignments, year-filtered
- **Known Expenses tab** — AI chat rule builder, CSV import, manual CRUD, drag-reorder
- **Travel Dates tab** — CRUD, year-filtered, Re-Apply Travel Rules
- **Settings tab** — business info, default tax year

---

## WHAT'S NEXT (Priority Order from SPEC)

From `SPEC-EL-Core-Bookkeeping-Delta-v2_6.md`:

1. ✅ ~~Phase 6 — Receipt Upload + AI Extraction~~ (v1.38.12)
2. ✅ ~~Feature B — Manual Receipt Entry Form~~ (v1.38.13)
3. ✅ ~~Feature E — Receipt Auto-Match Engine~~ (v1.38.30)
4. ✅ ~~Feature A — Phase A.1: Database Setup~~ (v1.38.33)
5. ✅ ~~Feature A — Phase A.2: Clients Tab CRUD~~ (v1.38.35)
6. **Feature A — Phase A.3: 1099-NEC Entry Form** ← NEXT
   - Build Section C: 1099 entry form (tax year, document status, box1 amount, file upload)
   - Conditional logic: received vs missing vs substitute
   - AJAX: `bk_save_1099`, `bk_delete_1099`, `bk_calculate_1099_from_deposits`
7. **Feature A — Phase A.4: Income Tab — Client Assignment**
   - Add Client column to income transactions table
   - Inline assignment dropdown
   - AJAX: `bk_assign_client_to_transaction`, `bk_unassign_client`
8. **Feature A — Phase A.5: Auto-Match Patterns**
   - Bank pattern matching on import
9. **Feature A — Phase A.6: Reconciliation Views**
   - Section D: Reconciliation detail
   - Section E: Annual income summary
10. **Phase 7 — P&L Report Generation + Export**
11. **Feature C — Receipt CSV Bulk Import**
12. **Feature D — AI Email Receipt Agent (Gmail)**

---

## KICKOFF PROMPT FOR PHASE A.3 (1099-NEC Entry Form)

```
Read SPEC-EL-Core-Bookkeeping-Delta-v2_6.md (Section C of the Clients tab spec).
Then read:
- modules/bookkeeping/class-bookkeeping-module.php (handle_save_client pattern)
- modules/bookkeeping/admin/views/clients.php (existing structure)
- modules/bookkeeping/module.json (confirm el_bk_1099_nec schema)

Implement Phase A.3 — 1099-NEC Entry Form:
1. Add "Add 1099" button to each client row in the client list table
2. Build Section C: 1099 entry form in clients.php
   - Fields: Client (pre-selected), Tax Year, Document Status (radio), Box 1 Amount,
     Date Received, 1099 Document upload, Substitute Documents upload, Notes
   - Conditional: received → show 1099 upload; missing/substitute → show substitute upload,
     auto-populate Box 1 from matched deposits
3. Add AJAX: bk_save_1099, bk_delete_1099, bk_get_1099s, bk_calculate_1099_from_deposits
4. Wire "Calculate from Deposits" button to sum matched deposits for client+year

DB version is 6. el_bk_1099_nec table already exists.
Follow handle_save_client() for CRUD pattern.
Voice-friendly: no required attributes, no input masks, large fields.
```

---

## KEY FILES

| File | Purpose |
|------|---------|
| `el-core/el-core.php` | Main plugin file — version number (TWO places: header + constant) |
| `el-core/modules/bookkeeping/class-bookkeeping-module.php` | All PHP logic (~2600 lines) |
| `el-core/modules/bookkeeping/module.json` | DB schema + migrations (currently **v6**) |
| `el-core/modules/bookkeeping/admin/views/clients.php` | Clients / 1099 tab view (NEW) |
| `el-core/modules/bookkeeping/admin/views/receipts.php` | Receipts tab view |
| `el-core/modules/bookkeeping/admin/views/expenses.php` | Expenses tab view |
| `el-core/modules/bookkeeping/admin/views/income.php` | Income tab view |
| `el-core/modules/bookkeeping/assets/js/bookkeeping.js` | All JS (~1800 lines) |
| `el-core/modules/bookkeeping/assets/css/bookkeeping.css` | All CSS (~1580 lines) |
| `el-core/includes/class-ai-client.php` | AI client — `complete()` and `complete_with_image()` |
| `build-zip.ps1` | Build script — update `$version` here too |
| `SPEC-EL-Core-Bookkeeping-Delta-v2_6.md` | **Authoritative feature spec** |

---

## CRITICAL LESSONS LEARNED

- **`module.json` edits: ALWAYS use `Write` tool, never `StrReplace`** — partial replacements leave orphaned JSON blocks that silently break the entire module loader. After editing, always verify with `ConvertFrom-Json`.
- **DB migrations go in `module.json`** — bump `"version"` and add `"migrations": { "N": "ALTER TABLE..." }`. The `EL_Database` class runs them automatically on next admin page load.
- **`module.json` `"version"` is the DB schema version** — plugin version lives in `el-core.php` (header + constant) and `build-zip.ps1`.
- **Check the actual plugin version before building** — `git log` and `el-core.php` are the source of truth, not the START-HERE doc. The doc can be stale.
- **ALWAYS build a new version number** — never build a ZIP under a version that has already been uploaded. Check git log to confirm the last deployed version.
- **PowerShell `Compress-Archive` breaks ZIP** — always use `build-zip.ps1` (.NET ZipFile API). Never skip it.
- **PowerShell doesn't support `&&`** — use separate commands or `;`.
- **jQuery `.data()` vs `.attr()`** — when setting HTML attributes with `.attr('data-foo', val)`, always READ with `.attr('data-foo')` too. jQuery's `.data('foo')` reads from the HTML attribute once then caches — on dynamically created elements this cache can be empty, returning `undefined`. This killed the bank pattern tags save in v1.38.33/34.
- **JSON-encode array data attributes** — never put newline-delimited or whitespace-delimited values in HTML `data-*` attributes. Use `wp_json_encode($array)` in PHP and `JSON.parse(el.attr('data-foo'))` in JS.
- **Receipt status values** are `'unmatched'` and `'matched'` — NOT `'unreviewed'`.
- **`get_receipts()` signature**: `get_receipts( string $status = '', int $tax_year = 0 )`
- **`get_travel_periods()` signature**: `get_travel_periods( int $tax_year = 0 )`
- **`receipt_id` column on `wp_el_bk_transactions`**: DB migration v5
- **`client_id` column on `wp_el_bk_transactions`**: DB migration v6
- **`el_bk_clients` = entities that PAY Fred** (1099-NEC issuers). **`el_bk_contractors` = people Fred PAYS**. These are COMPLETELY SEPARATE. Never mix them.
- **`$this->core->ai->complete()` returns an ARRAY** — `['success' => bool, 'content' => string, 'error' => string]`. NOT a raw string.
- **`EL_AJAX_Handler::success($data, $message)` wraps response** — JS receives `{ data: $data, message: $message }` as `res.data`. Always unwrap before `.forEach()`.
- **Voice input (Wispr)**: NO `required` HTML5 attributes, NO input masks anywhere in the bookkeeping module.
- **`EL_Admin_UI::notice()` takes an array**: `notice( ['message' => '...', 'type' => 'info'] )` NOT `notice('...', 'info')`.
- **Receipt match engine (v1.38.30)**: 3-tier — (1) exact name, (2) contains, (3) AI with ≤5 word-filtered candidates. Tax year from receipt date, not UI selector. Date window: receipt date + 10 days forward only.
