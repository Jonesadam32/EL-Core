# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** April 2, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.38.8
> **Git Status:** All committed and pushed to GitHub. Clean working tree.
> **Backup:** Full repo backed up to `C:\EL-Core-Backup` as of v1.38.8.

---

## WHAT WAS DONE THIS SESSION (April 2, 2026)

### v1.38.5 — Auto Loan Payment category
### v1.38.6 — Credit Card Payment category
### v1.38.7 — Major UI improvements
- **Lock/unlock classifications**: Manually selecting a category now auto-sets status to `classified` (locked). Re-Classify skips locked transactions — manual picks are protected.
- **Reject button**: Red ✕ button on each suggested/classified row to reject a classification (clears category, marks `rejected` with red row styling).
- **Full search/filter bar**: Instant client-side filtering by keyword (merchant/business/comments), category dropdown, bank account dropdown, status filter (classified/suggested/unclassified/rejected), and date range. Shows count + filtered total.
- **Removed** "Confirm Travel Suggestions" button (wasn't working, caused confusion).

### v1.38.8 — Business vs Personal expense classification
- **Categories split into Business (25) and Personal (13)**:
  - **Business**: Accounting Fees, Advertising & Promotion, California FTB Payment, Computer (Hardware/Hosting/Software), Contract Labor, Dues & Subscriptions, Education & Training, Georgia Tax Payment, Health Care Insurance, Home Office Expense, Insurance-General Liability, Meals & Entertainment, Merchant Account Fees, Office Supplies, Out of pocket Medical Expenses, Parking & tolls, Professional Fees, Rent Expense, Telephone - Wireless, Travel Expense, Vehicle - Fuel, Vehicle - Repairs and Maintenance, Vehicles Insurance
  - **Personal**: Auto Loan Payment, Bank Service Charges, Credit Card Payment, Interest Expense, IRS Payment, Merrill Lynch Investment Account, Owner Draw, Owner Draw - Cleaners, Owner Draw - Entertainment, Owner Draw - Groceries, Owner Draw - Personal Meals, Owner Draw - Pet, SBA Loan
- **Summary bar** shows separate Business / Personal / Total amounts, each category has a blue B or pink P badge
- **Category dropdowns** everywhere (expenses table, known expenses) grouped into Business / Personal optgroups
- **Expense Type filter** in filter bar to show only Business or Personal transactions
- New PHP methods: `get_expense_categories_grouped()`, `get_category_type()`

---

## KEY FILES CHANGED THIS SESSION

| File | What Changed |
|------|-------------|
| `class-bookkeeping-module.php` | `get_expense_categories_grouped()`, `get_category_type()`, handle_update_transaction auto-locks, handle_reclassify skips classified, new categories |
| `admin/views/expenses.php` | Filter bar, reject button, grouped dropdowns, B/P badges, data attributes on rows, summary bar split |
| `admin/views/known-expenses.php` | Grouped category dropdowns |
| `assets/js/bookkeeping.js` | Full expense filter logic, reject handler, lock-on-classify, expense type filter |
| `assets/css/bookkeeping.css` | Filter bar styles, reject button styles, B/P badge styles |
| `el-core.php` | Version bump to 1.38.8 |

---

## WHAT'S WORKING ✅

- **Dashboard tab**: Stat cards, quick-access grid, year selector
- **Income & Deposits tab**: CSV bank statement upload (multi-file), auto-sort income/expense
- **Expenses tab**: Full transaction table with:
  - Inline category editing (grouped Business/Personal dropdowns)
  - Lock mechanism (🔒) — manual picks protected from Re-Classify
  - Reject button (✕) — reject suggestions, mark as rejected
  - Re-Classify button (only affects unclassified/suggested, skips locked)
  - Confirm All Suggestions button
  - Full search/filter bar (keyword, category, bank account, status, expense type, date range)
  - Summary bar with Business / Personal / Total breakdown
  - Color-coded rows (green=classified, yellow=suggested, red=rejected)
- **Known Expenses tab**: AI chat rule builder, CSV rule import, manual add/edit/delete, bulk delete, "All Words" match type
- **Merchant name cleaner**: Strips bank junk, preserves state codes for location-based rules
- **Auto-classification**: Runs on import and on Re-Classify, uses normalized punctuation matching
- **Travel Dates tab**: CRUD works (travel suggestion confirm button removed from expenses)
- **Contractors, Receipts, Settings, P&L tabs**: Scaffolded, basic CRUD working

---

## VERSION HISTORY (Recent)

| Version | What | Status |
|---------|------|--------|
| v1.37.2 | Merchant name cleaner for rule imports | Built |
| v1.37.3–v1.37.6 | State codes, rule matching, All Words match, rename Vehicles | Built |
| v1.37.7–v1.37.9 | Re-Classify fix, matching fixes, Owner Draw category | Built |
| v1.38.0 | Fix JS tax year to use URL year selector | Built |
| v1.38.1 | Normalize punctuation in matching | Built |
| v1.38.2 | Owner Draw sub-categories | Built |
| v1.38.3 | Tax/loan/home office categories | Built |
| v1.38.4 | Merrill Lynch Investment Account category | Built |
| v1.38.5 | Auto Loan Payment category | Built |
| v1.38.6 | Credit Card Payment category | Built |
| v1.38.7 | Lock/reject, search/filter bar, remove travel confirm | Built |
| v1.38.8 | **Business vs Personal expense classification** | **CURRENT — deployed** |

---

## NEXT STEPS (Not Started Yet)

1. **Test the full filter bar and reject flow** — user hasn't tested v1.38.7/v1.38.8 yet
2. **Print/export** — user wants to print business expenses separately from personal expenses
3. **P&L Report** — Phase 7, generate profit & loss report
4. **Receipts upload** — Phase 4, attach receipts to transactions
5. **CSV Export** — Export button exists but handler returns "not yet implemented"

---

## ARCHITECTURE NOTES

- Read `el-core-cursor-handoff.md` for full module architecture
- Read `SPEC-BOOKKEEPING-MODULE.md` for full feature spec
- **Expand Site** module is working (fixed in v1.34.7) — do not disturb
- All other modules unaffected by this work

---

## CRITICAL LESSONS LEARNED

- **`EL_Admin_UI::notice()` takes an array** — `notice( ['message' => '...', 'type' => 'info'] )` NOT `notice( '...', 'info' )`
- **PowerShell `Compress-Archive` breaks ZIP paths** — ALWAYS use .NET `ZipFile` API (see `.cursor/rules/zip-building.mdc`)
- **PowerShell doesn't support `&&`** — use separate commands or `;`
- **`wp_enqueue_style` dependency handle must exactly match** — `el-core-admin` not `el-admin`
- **JS `elBookkeeping.taxYear` must come from URL `$_GET['year']`** not stored default (caused Re-Classify to target wrong year)
- **Rule matching needs punctuation normalization** — `normalize_for_match()` strips punctuation before `str_contains`
- **Merchant cleaner must NOT strip state codes** — needed for location-based rules (e.g., "Chick-Fil-A GA")
