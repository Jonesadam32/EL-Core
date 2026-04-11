# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** April 10, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.38.25
> **Git Status:** All committed. Clean working tree.
> **ZIP in Downloads:** `el-core-v1.38.25.zip` — ready to upload.

---

## CRITICAL: READ SPEC FILE FIRST

The authoritative build spec is in the project root:

```
SPEC-EL-Core-Bookkeeping-Delta-v2_3.md
```

It contains all table schemas, AJAX patterns, category lists, feature specs (A–E), and kickoff prompts. Do not make assumptions about DB structure — read the spec.

---

## WHAT WAS DONE THIS SESSION (April 10, 2026)

### v1.38.13 — Feature B: Manual Receipt Entry Form
- Added `Manual Receipt Entry` card to the Receipts tab
- Fields: Title, Date (defaults today), Vendor, Amount, Location (City, ST), Category (optgroup), Notes, optional image
- Two buttons: **Save Receipt** and **Save & Add Another**
- Handler: `handle_save_receipt_manual()` — stores vendor, date, amount, category, location; `ai_raw_response = 'manual_entry'`, `status = 'unmatched'`

### v1.38.14 — Receipts tab year filtering
- `get_receipts()` now accepts optional `int $tax_year`
- Receipts with NULL/empty date always shown

### v1.38.15 — All tabs respect year selector
- `get_travel_periods()` now accepts `int $tax_year`, filters with boundary spanning
- Dashboard unmatched receipt count fixed (was querying wrong status `'unreviewed'`)

### v1.38.16 — Location field on receipts
- `location varchar(255)` added to `wp_el_bk_receipts` — DB version 2
- AI prompt now requests `"location": "City, ST"` as 5th extraction field
- Inline-editable Location column in All Receipts table

### v1.38.17 — Fix broken module.json
- module.json had duplicate table definitions from bad StrReplace → rewrote as clean JSON

### v1.38.18 — Full receipt editing
- Edit button on every receipt row expands inline edit panel
- Fields: Merchant, Date, Amount, Category (dropdown w/ optgroups), Location, Notes
- New handler `handle_save_receipt_edits()`, saves in-place without page reload
- `notes text` column added to `wp_el_bk_receipts` — DB version 3
- `handle_save_receipt_manual()` now saves notes field (was silently dropped before)

### v1.38.19 — Feature E: Receipt Auto-Match Engine (initial keyword version)
- `match_source varchar(20)` added to `wp_el_bk_receipts` — DB version 4
- "Find Match" button on unmatched rows
- Initial implementation used keyword matching (later replaced with AI — see v1.38.22)

### v1.38.20 — Fix: Income tab date filter broken
- Filter button had no JS handler wired to it
- Income `<tr>` rows had no `data-date` attribute
- Added `filterIncomeTable()` JS function + `id="el-bk-inc-table"` + filter count display
- Now works the same way as the Expenses filter

### v1.38.21 — Improved keyword matching (interim)
- Changed match to be name-first (LIKE SQL filter), date ±30 days, amount as bonus only
- Still keyword-based — superseded by v1.38.22

### v1.38.22 — AI-powered receipt matching (replaces keyword engine)
- `handle_suggest_receipt_matches()` completely rewritten
- Fetches up to 25 candidate expense transactions (same tax year, ±60 days)
- Sends receipt details + candidates to Claude via `$this->core->ai->complete()`
- AI reasons about: name variations, location suffixes, tip differences, bank posting delays
- Returns up to 3 matches with `confidence` (high/medium/low) and `reason` (one sentence)
- JS updated: shows confidence badge (green/yellow/gray) + AI reasoning instead of stars
- **Bug introduced:** used `'user'` key instead of `'prompt'` → 500 error

### v1.38.23 — Fix: AI call wrong param key
- `complete()` takes `'prompt'` not `'user'` — fixed
- **Bug remained:** `complete()` returns array, not string

### v1.38.24 — Fix: complete() returns array not string
- `$this->core->ai->complete()` returns `['success' => bool, 'content' => string, 'error' => string]`
- Was calling `trim($ai_response)` on an array → PHP fatal/500
- Fixed to check `$result['success']` and use `$result['content']`
- **Bug remained:** JS calling `.forEach()` on wrapped response object

### v1.38.25 — Fix: unwrap AJAX response data wrapper
- `EL_AJAX_Handler::success($array, $message)` wraps as `{ data: $array, message: $message }`
- JS was calling `candidates.forEach()` on the wrapper object, not the inner array
- Fixed: `var matches = Array.isArray(candidates) ? candidates : (candidates.data || []);`
- **Find Match with AI reasoning now fully works**

---

## FULL VERSION HISTORY (This Session)

| Version | What | Status |
|---------|------|--------|
| v1.38.8 | Business vs Personal expense classification | Previous session — deployed |
| v1.38.9–v1.38.12 | Phase 6: Receipt upload + AI extraction | Previous session — deployed |
| **v1.38.13** | Feature B: Manual Receipt Entry Form | ✅ Built |
| **v1.38.14** | Fix: Receipts tab year filter | ✅ Built |
| **v1.38.15** | Fix: All tabs respect year selector; fix dashboard receipt count | ✅ Built |
| **v1.38.16** | Feat: Location field on receipts (AI + manual + inline edit) | ✅ Built |
| **v1.38.17** | Fix: Repair broken module.json (invalid JSON) | ✅ Built |
| **v1.38.18** | Feat: Full receipt editing — inline edit row, all fields, notes column | ✅ Built |
| **v1.38.19** | Feat: Receipt Auto-Match (keyword version) + match_source column | ✅ Built |
| **v1.38.20** | Fix: Income tab date filter was completely unwired | ✅ Built |
| **v1.38.21** | Improve: Name-first keyword matching, wider date window | ✅ Built |
| **v1.38.22** | Feat: AI-powered receipt matching replaces keyword engine | ✅ Built |
| **v1.38.23** | Fix: AI call wrong param key (`user` → `prompt`) | ✅ Built |
| **v1.38.24** | Fix: `complete()` returns array not string | ✅ Built |
| **v1.38.25** | Fix: Unwrap AJAX data wrapper before `.forEach()` | ✅ **CURRENT** |

---

## WHAT'S WORKING ✅

### Receipts Tab (Fully Built)
- **Upload zone** — drag/drop or browse; JPG/PNG/PDF; max 10MB; sequential upload with counter
- **AI extraction** — merchant, date, amount, category, location (city/state)
- **Review queue** — card per upload showing all extracted fields + badge (AI extracted / manual)
- **Manual Entry Form** — title, date, vendor, amount, location, category (optgroup), notes, optional image
- **All Receipts table** — thumbnail, merchant, date, amount, category, location (inline editable), status badge, attached transaction, detach/delete
- **Year filter** — shows only receipts for selected tax year (null-date receipts always shown)
- **Full receipt edit** — Edit button → inline panel with all fields including category dropdown → saves in-place
- **AI Auto-Match** — Find Match button (unmatched rows only) → AI reasons about candidates → confidence badge + one-sentence explanation → Attach calls `bk_attach_receipt`

### Income Tab
- Transaction table with inline category/bank/comments editing
- CSV upload modal
- Year-filtered
- **Date filter now works** — From/To inputs filter rows by date (fixed v1.38.20)

### All Other Tabs
- **Dashboard** — stat cards year-filtered and correct
- **Expenses tab** — full filter bar, inline editing, lock, reject, re-classify, B/P summary
- **Profit & Loss tab** — server-rendered, date range presets
- **Contractors tab** — directory + assignments, year-filtered
- **Known Expenses tab** — AI chat rule builder, CSV import, manual CRUD, drag-reorder
- **Travel Dates tab** — CRUD, year-filtered, Re-Apply Travel Rules
- **Settings tab** — business info, default tax year

---

## WHAT'S NEXT (Priority Order from SPEC)

From `SPEC-EL-Core-Bookkeeping-Delta-v2_3.md` Section 8:

1. ✅ ~~Phase 6 — Receipt Upload + AI Extraction~~ (v1.38.12)
2. ✅ ~~Feature B — Manual Receipt Entry Form~~ (v1.38.13)
3. ✅ ~~Feature E — Receipt Auto-Match Engine~~ (v1.38.25)
4. **Feature A — 1099-NEC Client Income Tracking** ← NEXT
   - New tables: `el_bk_clients`, `el_bk_1099_nec` — module.json DB version 5
   - Add `client_id` to `wp_el_bk_transactions`
   - Client CRUD, 1099 entry, reconciliation table in income.php
   - `el_bk_clients` = who pays Fred; `el_bk_contractors` = who Fred pays — NEVER mix
5. **Phase 7 — P&L Report Generation + Export**
   - `handle_export_pl()` — Schedule C style, CSV download + printable HTML
6. **Feature C — Receipt CSV Bulk Import**
7. **Feature D — AI Email Receipt Agent (Gmail)**

---

## KICKOFF PROMPT FOR FEATURE A (1099-NEC Client Income Tracking)

```
Read SPEC-EL-Core-Bookkeeping-Delta-v2_3.md in the project root first. Then read:
- modules/bookkeeping/class-bookkeeping-module.php
- modules/bookkeeping/module.json
- modules/bookkeeping/admin/views/income.php

Implement Feature A — 1099-NEC Client Income Tracking:
1. Add el_bk_clients and el_bk_1099_nec tables to module.json. Bump database version to 5.
2. Add client_id bigint(20) NOT NULL DEFAULT 0 to wp_el_bk_transactions via ALTER TABLE migration.
3. Add get_clients() and get_1099s( int $tax_year ) public methods to EL_Bookkeeping_Module.
4. Register and implement: bk_save_client, bk_delete_client, bk_save_1099, bk_delete_1099, bk_match_client_deposits, bk_assign_deposit_to_client.
5. Add a Clients / 1099-NEC section to income.php with client CRUD form, 1099 entry form per client per year, and a reconciliation table (1099 Amount vs Matched Deposits vs Difference with status badge).

IMPORTANT: el_bk_clients = entities that PAY Fred. el_bk_contractors (already exists) = people Fred PAYS. Completely separate — never mix them.
IMPORTANT: module.json is currently at version 4. The migration key must be "5".
Follow handle_save_contractor() for CRUD pattern. All forms voice-friendly — no required validation, no input masks.
```

---

## KEY FILES

| File | Purpose |
|------|---------|
| `el-core/el-core.php` | Main plugin file — version number (TWO places: header + constant) |
| `el-core/modules/bookkeeping/class-bookkeeping-module.php` | All PHP logic (~2400 lines) |
| `el-core/modules/bookkeeping/module.json` | DB schema + migrations (currently v4) |
| `el-core/modules/bookkeeping/admin/views/receipts.php` | Receipts tab view |
| `el-core/modules/bookkeeping/admin/views/expenses.php` | Expenses tab view |
| `el-core/modules/bookkeeping/admin/views/income.php` | Income tab view |
| `el-core/modules/bookkeeping/admin/views/travel-dates.php` | Travel Dates tab view |
| `el-core/modules/bookkeeping/assets/js/bookkeeping.js` | All JS (~1400 lines) |
| `el-core/modules/bookkeeping/assets/css/bookkeeping.css` | All CSS (~1320 lines) |
| `el-core/includes/class-ai-client.php` | AI client — `complete()` and `complete_with_image()` |
| `build-zip.ps1` | Build script — update `$version` here too |
| `SPEC-EL-Core-Bookkeeping-Delta-v2_3.md` | **Authoritative feature spec** |

---

## CRITICAL LESSONS LEARNED

- **`module.json` edits: ALWAYS use `Write` tool, never `StrReplace`** — partial replacements leave orphaned JSON blocks that silently break the entire module loader. After editing, always verify with `ConvertFrom-Json`.
- **DB migrations go in `module.json`** — bump `"version"` and add `"migrations": { "N": "ALTER TABLE..." }`. The `EL_Database` class runs them automatically on next admin page load.
- **`module.json` `"version"` is for the DB schema** — plugin version lives in `el-core.php` (header + constant) and `build-zip.ps1`.
- **PowerShell `Compress-Archive` breaks ZIP** — always use `build-zip.ps1` (.NET ZipFile API). Never skip it.
- **PowerShell doesn't support `&&`** — use separate commands or `;`.
- **Receipt status values** are `'unmatched'` and `'matched'` — NOT `'unreviewed'`.
- **`get_receipts()` signature**: `get_receipts( string $status = '', int $tax_year = 0 )`
- **`get_travel_periods()` signature**: `get_travel_periods( int $tax_year = 0 )`
- **`notes` column**: DB migration v3 on `wp_el_bk_receipts`
- **`match_source` column**: DB migration v4 on `wp_el_bk_receipts`; defaults `''`; Feature D will populate `'ai_email'`
- **Feature A migration key must be "5"** — v4 was used for match_source
- **`$this->core->ai->complete()` returns an ARRAY** — `['success' => bool, 'content' => string, 'error' => string]`. NOT a raw string. Always check `$result['success']` and use `$result['content']`. Fatal 500 if you call `trim()` on the return value directly.
- **`EL_AJAX_Handler::success($data, $message)` wraps response** — JS receives `{ data: $data, message: $message }` as `res.data`. Always unwrap: `var items = Array.isArray(r) ? r : (r.data || [])` before calling `.forEach()`.
- **Voice input (Wispr)**: NO `required` HTML5 attributes, NO input masks anywhere in the bookkeeping module.
- **`EL_Admin_UI::notice()` takes an array**: `notice( ['message' => '...', 'type' => 'info'] )` NOT `notice('...', 'info')`.
