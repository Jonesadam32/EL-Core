# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** April 10, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.38.19
> **Git Status:** All committed. Clean working tree.
> **ZIP in Downloads:** `el-core-v1.38.19.zip` — ready to upload.

---

## CRITICAL: READ SPEC FILE FIRST

The authoritative build spec is in the project root:

```
SPEC-EL-Core-Bookkeeping-Delta-v2_2.md
```

It contains all table schemas, AJAX patterns, category lists, feature specs (A–E), and kickoff prompts. Do not make assumptions about DB structure — read the spec.

---

## WHAT WAS DONE THIS SESSION (April 10, 2026)

### v1.38.13 — Feature B: Manual Receipt Entry Form
- Added `Manual Receipt Entry` card to the Receipts tab, placed below the upload/review queue
- Fields: Title, Date (defaults today), Vendor, Amount, Location (City, ST), Category (optgroup), Notes, optional image (JPG/PNG/PDF, max 10MB)
- Two buttons: **Save Receipt** (keeps form) and **Save & Add Another** (clears form after save)
- New PHP handler `handle_save_receipt_manual()` — stores vendor→`ai_extracted_merchant`, date, amount, category, location; `ai_raw_response = 'manual_entry'`, `status = 'unmatched'`
- Optional image upload uses same path as Phase 6 (`wp-content/uploads/el-bk-receipts/`)
- On save, result appears in the review queue card (same `addToReviewQueue()` as AI uploads)
- All fields voice-friendly — no `required`, no input masks

### v1.38.14 — Receipts tab year filtering
- Fixed: Receipts tab was showing ALL receipts regardless of selected tax year
- `get_receipts()` now accepts optional `int $tax_year` — filters by `YEAR(ai_extracted_date) = $tax_year`
- Receipts with `NULL` or empty date are **always shown** (so nothing is silently hidden)
- Tab description now shows: "Showing receipts for 2026. Receipts with no date are always included."

### v1.38.15 — All tabs respect year selector
Audit of every tab — only two things were broken:

1. **Travel Dates tab** — was showing ALL travel periods regardless of year.
   - `get_travel_periods()` now accepts optional `int $tax_year`
   - Filters with `start_date <= {year}-12-31 AND end_date >= {year}-01-01` (handles cross-year trips correctly)
   - Tab heading now shows "Travel Dates — 2026"
   - Empty state message now says "No travel periods found for 2026..."

2. **Dashboard unmatched receipt count** — was always showing 0.
   - Status string was `'unreviewed'` (doesn't exist in DB). Fixed to `'unmatched'`.
   - Dashboard stat card now correctly counts unmatched receipts for the selected year.

Tabs already correctly filtered: Expenses, Income, P&L, Contractors (contract labor). Intentionally global (no year filter): Known Expenses rules, Settings.

### v1.38.16 — Location field on receipts
- Added `location varchar(255) NOT NULL DEFAULT ''` column to `wp_el_bk_receipts`
- **module.json** bumped to `"version": 2` with migration `"2": "ALTER TABLE el_bk_receipts ADD COLUMN location ..."`
- AI extraction updated: system prompt now asks for `"location": "City, ST"` as a 5th field
- `parse_ai_receipt_response()` now extracts and returns `location`
- `handle_upload_receipt()` — stores AI-extracted location; returns it in response payload
- `handle_save_receipt_manual()` — accepts and stores `location` field
- New AJAX handler `handle_update_receipt()` — allows inline editing of `location` (and `ai_extracted_merchant`, `ai_extracted_category`) on saved receipts
- **Receipts table** — new Location column with inline editable text input; saves on blur with green flash, no page reload
- **Manual entry form** — Location field added between Vendor/Amount and Category
- **Review queue cards** — location shown if extracted/entered
- **CSS** — `el-bk-receipt-inline-input` styles for the table input

### v1.38.19 — Feature E: Receipt Auto-Match Engine
- **`module.json`** bumped to DB version 4 — migration adds `match_source varchar(20) NOT NULL DEFAULT ''` to `wp_el_bk_receipts`
- New AJAX handler `handle_suggest_receipt_matches()`:
  - Fetches the receipt's amount + date + merchant
  - Queries `wp_el_bk_transactions` WHERE type='expense', receipt_id=0, ABS(amount - receipt_amount) < $1.00, ABS(DATEDIFF(date, receipt_date)) <= 3 days
  - Scores each candidate: exact amount (within $0.01) = +3, exact date = +2, merchant keyword overlap (words ≥ 3 chars) = +1
  - Returns top 3 sorted by score descending
- **"Find Match" button** added to every unmatched row in the All Receipts table (Actions column)
- Clicking Find Match expands a match panel below the row showing up to 3 candidates in a table: Merchant, Date, Amount, Category, Match score (★☆ stars)
- **Attach** button on each candidate calls the existing `bk_attach_receipt` handler (unchanged) → page reloads as matched
- Opening Find Match closes any open Edit row (and vice versa); only one panel open at a time per table

### v1.38.18 — Full Receipt Editing
- Added **Edit button** to every row in the All Receipts table (in the Actions column)
- Clicking Edit expands an inline edit row below that receipt with all editable fields:
  - **Merchant**, **Date** (date picker), **Amount**, **Location** — text inputs
  - **Category** — full dropdown with Business/Personal optgroups (same as manual entry form)
  - **Notes** — textarea
- Save updates the DB and updates the table row cells in-place (no page reload)
- Cancel closes the edit row without saving
- New PHP handler `handle_save_receipt_edits()` — validates all fields (category checked against known list, amount parsed as positive float, date normalised to Y-m-d)
- `module.json` bumped to DB version 3 — migration adds `notes text NOT NULL DEFAULT ''` column to `wp_el_bk_receipts`
- `handle_save_receipt_manual()` now saves the notes field (was silently dropped before)
- `handle_update_receipt()` allowed-fields expanded to include `ai_extracted_date`, `ai_extracted_amount`, `notes`

### v1.38.17 — Fix broken module.json
- `module.json` had duplicate table definitions from a bad StrReplace (new + original block both present)
- This made `json_decode()` return null, so the module loader couldn't find the bookkeeping module at all
- Rewrote module.json as a clean single valid JSON object
- Confirmed parseable via PowerShell `ConvertFrom-Json`

---

## FULL VERSION HISTORY (This Session)

| Version | What | Status |
|---------|------|--------|
| v1.38.8 | Business vs Personal expense classification | Previous session — deployed |
| v1.38.9–v1.38.12 | Phase 6: Receipt upload + AI extraction (previous session) | Deployed |
| **v1.38.13** | Feature B: Manual Receipt Entry Form | ✅ Built |
| **v1.38.14** | Fix: Receipts tab year filter | ✅ Built |
| **v1.38.15** | Fix: All tabs respect year selector; fix dashboard receipt count | ✅ Built |
| **v1.38.16** | Feat: Location field on receipts (AI + manual + inline edit) | ✅ Built |
| **v1.38.19** | Feature E: Receipt Auto-Match Engine | ✅ **CURRENT** |
| **v1.38.18** | Feat: Full receipt editing — inline edit row, all fields, notes column | ✅ Built |
| **v1.38.17** | Fix: Repair broken module.json (invalid JSON) | ✅ Built |

---

## WHAT'S WORKING ✅

### Receipts Tab (Fully Built — Phase 6 + Feature B)
- **Upload zone** — drag/drop or browse; JPG/PNG/PDF; max 10MB; sequential upload with counter
- **AI extraction** — merchant, date, amount, category, **location** (city/state)
- **Review queue** — card per upload showing all extracted fields + badge (AI extracted / manual)
- **Manual Entry Form** — title, date, vendor, amount, location, category (optgroup), notes, optional image; Save + Save & Add Another
- **All Receipts table** — thumbnail, merchant, date, amount, category, location (inline editable), status badge, attached transaction, detach/delete
- **Year filter** — shows only receipts for the selected tax year (null-date receipts always shown)
- **Inline location edit** — click location cell, type, blur → saves via `bk_update_receipt`
- **Full receipt edit** — Edit button expands edit row with all fields: merchant, date, amount, category (dropdown), location, notes → saves via `bk_save_receipt_edits`
- **Auto-Match** — Find Match button (unmatched rows only) searches for candidate transactions by amount/date/merchant, shows scored results, Attach links to `bk_attach_receipt`

### All Other Tabs
- **Dashboard** — stat cards (expenses, income, net profit, unmatched receipts) all year-filtered and correct
- **Expenses tab** — full transaction table, inline category/bank/comments editing, lock mechanism, reject, re-classify, full filter bar, B/P summary bar
- **Income tab** — transaction table, CSV upload modal, year-filtered
- **Profit & Loss tab** — server-rendered P&L report, date range presets, defaults to selected tax year
- **Contractors tab** — directory + contract labor transactions, year-filtered, assign to contractor
- **Known Expenses tab** — AI chat rule builder, CSV rule import, manual CRUD, bulk delete, drag-reorder — rules intentionally global (no year filter)
- **Travel Dates tab** — CRUD, year-filtered, Re-Apply Travel Rules button
- **Settings tab** — business info, default tax year

---

## WHAT'S NEXT (Priority Order from SPEC)

From `SPEC-EL-Core-Bookkeeping-Delta-v2_3.md` Section 8:

1. ✅ ~~Phase 6 — Receipt Upload + AI Extraction~~ (v1.38.12)
2. ✅ ~~Feature B — Manual Receipt Entry Form~~ (v1.38.13)
3. ✅ ~~Feature E — Receipt Auto-Match Engine~~ (v1.38.19)
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

IMPORTANT: el_bk_clients = entities that PAY Fred. el_bk_contractors (already exists) = people Fred PAYS. These are completely separate — never mix them.
IMPORTANT: module.json is currently at version 4. The migration key must be "5".
Follow handle_save_contractor() for CRUD pattern. All forms voice-friendly — no required validation, no input masks.
```

---

## KEY FILES

| File | Purpose |
|------|---------|
| `el-core/el-core.php` | Main plugin file — version number (TWO places: header + constant) |
| `el-core/modules/bookkeeping/class-bookkeeping-module.php` | All PHP logic (~2300 lines) |
| `el-core/modules/bookkeeping/module.json` | DB schema + migrations (currently v4) |
| `el-core/modules/bookkeeping/admin/views/receipts.php` | Receipts tab view |
| `el-core/modules/bookkeeping/admin/views/expenses.php` | Expenses tab view |
| `el-core/modules/bookkeeping/admin/views/income.php` | Income tab view |
| `el-core/modules/bookkeeping/admin/views/travel-dates.php` | Travel Dates tab view |
| `el-core/modules/bookkeeping/assets/js/bookkeeping.js` | All JS (~1170 lines) |
| `el-core/modules/bookkeeping/assets/css/bookkeeping.css` | All CSS (~1200 lines) |
| `build-zip.ps1` | Build script — update `$version` here too |
| `SPEC-EL-Core-Bookkeeping-Delta-v2_3.md` | **Authoritative feature spec** |

---

## CRITICAL LESSONS LEARNED

- **`module.json` edits: ALWAYS use `Write` tool, never `StrReplace`** — partial replacements leave orphaned JSON blocks that silently break the entire module loader. After editing, always verify with `ConvertFrom-Json`.
- **DB migrations go in `module.json`** — bump `"version"` and add `"migrations": { "N": "ALTER TABLE..." }`. The `EL_Database` class runs them automatically on next admin page load.
- **`module.json` `"version"` is for the DB schema** — plugin version lives in `el-core.php` (header + constant) and `build-zip.ps1`.
- **PowerShell `Compress-Archive` breaks ZIP** — always use `build-zip.ps1` (.NET ZipFile API). Never skip it.
- **PowerShell doesn't support `&&`** — use separate commands or `;`.
- **Receipt status values** are `'unmatched'` and `'matched'` — NOT `'unreviewed'`. Dashboard prefetch uses `'unmatched'`.
- **`get_receipts()` signature**: `get_receipts( string $status = '', int $tax_year = 0 )`
- **`notes` column**: added in DB migration v3 to `wp_el_bk_receipts`; editable via both manual form and the Edit row
- **`match_source` column**: added in DB migration v4 to `wp_el_bk_receipts`; currently unused (defaults `''`); Feature D (email agent) will populate `'ai_email'`; auto-match flow will populate `'auto_suggested'` in a future pass
- **Feature A migration key must be "5"** (v4 was used for match_source)
- **`get_travel_periods()` signature**: `get_travel_periods( int $tax_year = 0 )`
- **Voice input (Wispr)**: NO `required` HTML5 attributes, NO input masks anywhere in the bookkeeping module.
- **`EL_Admin_UI::notice()` takes an array**: `notice( ['message' => '...', 'type' => 'info'] )` NOT `notice('...', 'info')`.
