# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** April 11, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.38.32
> **Git Status:** All committed. Clean working tree.
> **ZIP in Downloads:** `el-core-v1.38.32.zip` — ready to upload.

---

## CRITICAL: READ SPEC FILE FIRST

The authoritative build spec is in the project root:

```
SPEC-EL-Core-Bookkeeping-Delta-v2_3.md
```

It contains all table schemas, AJAX patterns, category lists, feature specs (A–E), and kickoff prompts. Do not make assumptions about DB structure — read the spec.

---

## WHAT WAS DONE THIS SESSION (April 11, 2026)

### v1.38.26 — Fix: AI match used UI-selected tax year instead of receipt's year
- Candidate query was filtering by `elBookkeeping.taxYear` (the year the user has selected in the dropdown)
- A 2024 receipt viewed while the UI shows 2025 sent `tax_year=2025` → zero candidates → AI never ran
- Fix: derive tax year from `gmdate('Y', strtotime($receipt->ai_extracted_date))` instead

### v1.38.27 — Fix: Missing `receipt_id` column migration on transactions table
- `receipt_id` was in the `el_bk_transactions` schema definition but had NO migration
- Existing installations (tables created at DB version 1) didn't have the column
- Query `receipt_id = 0` silently returned empty results — AI never ran
- Fix: added migration "5" — `ALTER TABLE el_bk_transactions ADD COLUMN IF NOT EXISTS receipt_id bigint(20) NOT NULL DEFAULT 0`
- DB version bumped to 5. **Feature A must use migration key "6"**
- JS updated to show server's diagnostic message instead of hardcoded generic text

### v1.38.28 — Fix: Date window was ±60 days (too broad, wrong direction)
- Bank charges always post ON OR AFTER the receipt date — never before
- ±60 days was pulling 25 unrelated transactions and sending them all to AI
- Fix: `date BETWEEN receipt_date AND DATE_ADD(receipt_date, INTERVAL 10 DAY)`

### v1.38.29 — Add exact name match pre-check (bypass AI for obvious hits)
- Added direct case-insensitive name match before calling AI
- AI was returning `[]` even when "MRI Imaging Specialists" was in the candidate list
- Exact matches now return immediately without an API call

### v1.38.30 — Rewrite match engine: 3-tier, AI is last resort only
- **Tier 1 (exact):** case-insensitive name match → instant high confidence, no API
- **Tier 2 (contains):** one name contains the other → instant high confidence, no API
- **Tier 3 (AI fuzzy):** only if no programmatic match; pre-filters to ≤5 candidates sharing a word with the receipt merchant; sends a single plain-English question to AI (256 tokens max)
- Eliminated the overloaded 25-candidate + long system-prompt AI call entirely
- **Feature E (Receipt Auto-Match) is now fully working**

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
| **v1.38.25** | Fix: Unwrap AJAX data wrapper before `.forEach()` | ✅ Built |
| **v1.38.26** | Fix: Match used UI tax year, not receipt's own year | ✅ Built |
| **v1.38.27** | Fix: Missing `receipt_id` migration on transactions table (DB → v5) | ✅ Built |
| **v1.38.28** | Fix: Date window was ±60 days; changed to receipt date +10 days forward | ✅ Built |
| **v1.38.29** | Fix: Add exact name match pre-check to bypass unreliable AI | ✅ Built |
| **v1.38.30** | Rewrite: 3-tier match engine — exact, contains, then focused AI fuzzy | ✅ **CURRENT** |

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
- **Find Match** — 3-tier engine: exact name → contains → AI fuzzy (≤5 candidates, plain question); confidence badge + reason; Attach button

### Income Tab
- Transaction table with inline category/bank/comments editing
- CSV upload modal
- Year-filtered
- **Date filter works** — From/To inputs filter rows by date

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
3. ✅ ~~Feature E — Receipt Auto-Match Engine~~ (v1.38.30)
4. **Feature A — 1099-NEC Client Income Tracking** ← NEXT
   - New tables: `el_bk_clients`, `el_bk_1099_nec` — module.json DB version **6**
   - Add `client_id` to `wp_el_bk_transactions`
   - Client CRUD, 1099 entry, reconciliation table in income.php
   - `el_bk_clients` = who pays Fred; `el_bk_contractors` = who Fred pays — NEVER mix
5. **Phase 7 — P&L Report Generation + Export**
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
1. Add el_bk_clients and el_bk_1099_nec tables to module.json. Bump database version to 6.
2. Add client_id bigint(20) NOT NULL DEFAULT 0 to wp_el_bk_transactions via ALTER TABLE migration.
3. Add get_clients() and get_1099s( int $tax_year ) public methods to EL_Bookkeeping_Module.
4. Register and implement: bk_save_client, bk_delete_client, bk_save_1099, bk_delete_1099, bk_match_client_deposits, bk_assign_deposit_to_client.
5. Add a Clients / 1099-NEC section to income.php with client CRUD form, 1099 entry form per client per year, and a reconciliation table (1099 Amount vs Matched Deposits vs Difference with status badge).

IMPORTANT: el_bk_clients = entities that PAY Fred. el_bk_contractors (already exists) = people Fred PAYS. Completely separate — never mix them.
IMPORTANT: module.json is currently at version 5. The migration key must be "6".
Follow handle_save_contractor() for CRUD pattern. All forms voice-friendly — no required validation, no input masks.
```

---

## KEY FILES

| File | Purpose |
|------|---------|
| `el-core/el-core.php` | Main plugin file — version number (TWO places: header + constant) |
| `el-core/modules/bookkeeping/class-bookkeeping-module.php` | All PHP logic (~2500 lines) |
| `el-core/modules/bookkeeping/module.json` | DB schema + migrations (currently v5) |
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
- **`receipt_id` column on `wp_el_bk_transactions`**: DB migration v5 — `ADD COLUMN IF NOT EXISTS receipt_id bigint(20) NOT NULL DEFAULT 0`
- **Feature A migration key must be "6"** — v5 was used for receipt_id on transactions
- **`$this->core->ai->complete()` returns an ARRAY** — `['success' => bool, 'content' => string, 'error' => string]`. NOT a raw string. Always check `$result['success']` and use `$result['content']`.
- **`EL_AJAX_Handler::success($data, $message)` wraps response** — JS receives `{ data: $data, message: $message }` as `res.data`. Always unwrap: `var items = Array.isArray(r) ? r : (r.data || [])` before calling `.forEach()`.
- **Voice input (Wispr)**: NO `required` HTML5 attributes, NO input masks anywhere in the bookkeeping module.
- **`EL_Admin_UI::notice()` takes an array**: `notice( ['message' => '...', 'type' => 'info'] )` NOT `notice('...', 'info')`.
- **Receipt match engine (v1.38.30)**: 3-tier — (1) exact name, (2) contains, (3) AI with ≤5 word-filtered candidates. Tax year derived from receipt date, not UI selector. Date window is receipt date + 10 days forward only (bank always posts after purchase). DO NOT revert to broad date windows or large AI candidate lists.
- **AI is unreliable for exact string matching** — even with an identical merchant name in the candidate list, AI returned `[]`. Use programmatic matching for tiers 1 and 2; reserve AI only for genuinely fuzzy name differences.
