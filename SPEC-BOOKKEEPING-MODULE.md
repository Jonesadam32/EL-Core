# ELS Bookkeeping Module — SPEC.md
**Part of: EL Core WordPress Plugin**
**Owner: Fred Jones / Expanded Learning Solutions LLC**
**Last Updated: 2026-04-01**
**Plugin Version Target:** v1.34.0+

---

## CURSOR CONTEXT — READ THIS FIRST

You are adding a **new module** to the existing EL Core WordPress plugin. This is NOT a standalone app. Follow the exact same patterns used by the `expand-site` and `events` modules already in this codebase.

**Before writing any code, read these files:**
- `el-core-cursor-handoff.md` — full architecture, conventions, and lessons learned
- `includes/class-admin-ui.php` — use this for ALL admin views, never raw HTML
- `modules/expand-site/module.json` — reference for module.json structure
- `modules/expand-site/class-expand-site-module.php` — reference for module class pattern

**Critical conventions (from handoff doc):**
- Singleton pattern on all module classes
- All admin views use `EL_Admin_UI::*` methods — never raw HTML
- All AJAX goes through `el_core_ajax_{action}` hooks with nonce verification
- No `CREATE TABLE` in module class — declare in `module.json`
- No `add_shortcode()` in module class — declare in `module.json`
- CSS prefix for this module: `el-bk-`
- Table prefix for this module: `el_bk_`
- Settings stored under key: `el_mod_bookkeeping`
- Module slug: `bookkeeping`
- Class name: `EL_Bookkeeping_Module`
- Class file: `class-bookkeeping-module.php`

---

## Overview

An internal WordPress admin-side bookkeeping module for Expanded Learning Solutions LLC. Replaces the Done For You Tax web app for:
- Categorizing business expenses (CSV upload + auto-classification)
- Tracking income and deposits
- Managing contractor payments (1099 prep)
- Generating IRS Schedule C-ready Profit & Loss reports
- Auto-classifying expenses during known travel date ranges
- Uploading and attaching receipt photos to transactions via AI extraction

**Users:** Fred (Administrator) + Stephanie (Editor). No client-facing components.

---

## Module Location

```
el-core/
└── modules/
    └── bookkeeping/
        ├── module.json
        ├── class-bookkeeping-module.php
        ├── admin/
        │   └── views/
        │       ├── expenses.php
        │       ├── income.php
        │       ├── profit-loss.php
        │       ├── contractors.php
        │       ├── known-expenses.php
        │       ├── travel-dates.php
        │       ├── receipts.php
        │       └── settings.php
        ├── assets/
        │   ├── css/bookkeeping.css
        │   └── js/bookkeeping.js
        └── PROGRESS.md         ← Keep this updated after every Cursor session
```

**Admin page URL:** `wp-admin/admin.php?page=els-bookkeeping`
**Admin menu:** Submenu under EL Core parent menu

---

## module.json

```json
{
    "name": "ELS Bookkeeping",
    "slug": "bookkeeping",
    "version": "1.0.0",
    "description": "Internal bookkeeping tool for expense categorization, income tracking, contractor management, receipt management, and Schedule C P&L reporting.",
    "author": "Expanded Learning Solutions",

    "requires": {
        "el_core": "1.3.0",
        "php": "8.0",
        "modules": []
    },

    "capabilities": [
        "manage_bookkeeping",
        "view_bookkeeping",
        "manage_bookkeeping_settings"
    ],

    "default_role_mapping": {
        "administrator": ["manage_bookkeeping", "view_bookkeeping", "manage_bookkeeping_settings"],
        "editor": ["view_bookkeeping", "manage_bookkeeping"]
    },

    "database": {
        "version": 1,
        "tables": {
            "el_bk_transactions": {
                "id": "bigint(20) NOT NULL AUTO_INCREMENT",
                "type": "varchar(20) NOT NULL DEFAULT 'expense'",
                "date": "date NOT NULL",
                "merchant": "varchar(500) NOT NULL DEFAULT ''",
                "amount": "decimal(10,2) NOT NULL DEFAULT 0.00",
                "category": "varchar(255) NOT NULL DEFAULT ''",
                "bank_account": "varchar(255) NOT NULL DEFAULT ''",
                "business": "varchar(255) NOT NULL DEFAULT 'Expanded Learning Solutions'",
                "status": "varchar(20) NOT NULL DEFAULT 'unclassified'",
                "comments": "text NOT NULL DEFAULT ''",
                "source_file": "varchar(255) NOT NULL DEFAULT ''",
                "tax_year": "year NOT NULL",
                "travel_period_id": "bigint(20) NOT NULL DEFAULT 0",
                "receipt_id": "bigint(20) NOT NULL DEFAULT 0",
                "created_at": "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
                "updated_at": "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
                "PRIMARY KEY": "(id)",
                "KEY idx_type_year": "(type, tax_year)",
                "KEY idx_status": "(status)",
                "KEY idx_category": "(category)",
                "KEY idx_date": "(date)"
            },
            "el_bk_rules": {
                "id": "bigint(20) NOT NULL AUTO_INCREMENT",
                "keyword": "varchar(255) NOT NULL",
                "match_type": "varchar(10) NOT NULL DEFAULT 'contains'",
                "category": "varchar(255) NOT NULL",
                "priority": "int(11) NOT NULL DEFAULT 0",
                "created_at": "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
                "PRIMARY KEY": "(id)",
                "KEY idx_priority": "(priority)"
            },
            "el_bk_travel_periods": {
                "id": "bigint(20) NOT NULL AUTO_INCREMENT",
                "label": "varchar(255) NOT NULL DEFAULT ''",
                "start_date": "date NOT NULL",
                "end_date": "date NOT NULL",
                "purpose": "varchar(500) NOT NULL DEFAULT ''",
                "created_at": "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
                "PRIMARY KEY": "(id)",
                "KEY idx_dates": "(start_date, end_date)"
            },
            "el_bk_receipts": {
                "id": "bigint(20) NOT NULL AUTO_INCREMENT",
                "transaction_id": "bigint(20) NOT NULL DEFAULT 0",
                "file_path": "varchar(500) NOT NULL DEFAULT ''",
                "file_url": "varchar(500) NOT NULL DEFAULT ''",
                "file_type": "varchar(20) NOT NULL DEFAULT ''",
                "ai_extracted_merchant": "varchar(255) NOT NULL DEFAULT ''",
                "ai_extracted_date": "date DEFAULT NULL",
                "ai_extracted_amount": "decimal(10,2) DEFAULT NULL",
                "ai_extracted_category": "varchar(255) NOT NULL DEFAULT ''",
                "ai_raw_response": "text NOT NULL DEFAULT ''",
                "status": "varchar(20) NOT NULL DEFAULT 'unmatched'",
                "created_at": "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
                "PRIMARY KEY": "(id)",
                "KEY idx_transaction": "(transaction_id)",
                "KEY idx_status": "(status)"
            },
            "el_bk_contractors": {
                "id": "bigint(20) NOT NULL AUTO_INCREMENT",
                "name": "varchar(255) NOT NULL",
                "email": "varchar(255) NOT NULL DEFAULT ''",
                "address": "text NOT NULL DEFAULT ''",
                "created_at": "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
                "PRIMARY KEY": "(id)"
            },
            "el_bk_contractor_assignments": {
                "id": "bigint(20) NOT NULL AUTO_INCREMENT",
                "transaction_id": "bigint(20) NOT NULL",
                "contractor_id": "bigint(20) NOT NULL",
                "created_at": "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
                "PRIMARY KEY": "(id)",
                "KEY idx_transaction": "(transaction_id)",
                "KEY idx_contractor": "(contractor_id)"
            }
        }
    },

    "shortcodes": [],

    "settings": [
        { "key": "business_name", "label": "Business Name", "type": "string", "default": "Expanded Learning Solutions LLC" },
        { "key": "tax_year", "label": "Default Tax Year", "type": "number", "default": 2025 },
        { "key": "home_office_pct", "label": "Home Office % (Indirect)", "type": "number", "default": 0 },
        { "key": "vehicle_mileage_rate", "label": "Vehicle Mileage Rate", "type": "number", "default": 0.67 }
    ]
}
```

---

## Navigation Tabs

Eight tabs across the top of the admin page:

1. **Expenses**
2. **Income & Deposits**
3. **Profit & Loss**
4. **Contractors**
5. **Known Expenses** ← AI-powered rule builder
6. **Travel Dates** ← Date-range auto-classifier
7. **Receipts** ← AI receipt scanner
8. **Settings**

---

## Tab 1: Expenses

### Purpose
Upload bank CSV exports, review transactions, assign IRS Schedule C categories.

### CSV Upload
- Upload one or multiple CSV files
- Primary format: Bank of America Business Checking (columns: Date, Description, Amount, Running Bal.)
- Generic fallback: auto-detect date, merchant, amount columns; show mapping UI if detection fails
- Deduplicate on import: match by date + amount + merchant (trim and lowercase)
- Show import summary notice: "X transactions imported, Y duplicates skipped"

### Transaction Table
Columns: `#` | `Category` (dropdown) | `Business` | `Amount` | `Merchant` | `Date` | `Bank Account` | `Receipt` | `Comments`

**Row color coding:**
- Green = `status: classified`
- Yellow = `status: suggested` (auto-match found, pending confirmation)
- Pink = `status: rejected`
- White = `status: unclassified`

**Badges on rows:**
- ✈️ = auto-classified via a Travel Date rule
- 📎 = has an attached receipt (click to view)

### Auto-Categorization on Import (Priority Order)

Run in this exact order — first match wins, mark as `suggested`:

**Step 1 — Travel Date Rules**
1. Check `el_bk_travel_periods` for any period where `start_date <= transaction_date <= end_date`
2. If match found → apply travel category mapping (see Travel Dates tab for merchant-type logic)
3. Set `travel_period_id`, set status to `suggested`, tag row with ✈️

**Step 2 — Known Expense Rules** (only if Step 1 found no match)
1. Query `el_bk_rules` ordered by priority ASC
2. `match_type = contains` → `strpos(strtolower($merchant), strtolower($keyword)) !== false`
3. `match_type = exact` → `strtolower($merchant) === strtolower($keyword)`
4. First match wins → set category, set status to `suggested`

**Step 3 — No match**
- Status stays `unclassified`

### Bulk Actions
- "Confirm All Suggestions" → sets all `suggested` to `classified`
- "Confirm Travel Suggestions" → confirms only ✈️ tagged rows
- Individual row: confirm or reject, change category via dropdown

### Filters
- By Category, Status, Date range, Bank Account, Travel Period
- Search by merchant name
- Filter: "Has Receipt" / "No Receipt"

### Export Buttons
- Download to CSV
- Download Transactions for QuickBooks
- Download Ledger

---

## Tab 2: Income & Deposits

### Purpose
Track all incoming deposits, classify as business income.

### Features
- Same CSV upload flow as Expenses
- Transactions stored with `type = income`
- Income categories: Income - Expanded Learning Solutions (default), Retreats, LMS Licensing, Professional Development, NYC SMV Tool, Other, Bank Transfer, Ignore
- Prominent total: **Business Total Income: $XXX,XXX.XX**
- Info banner: "Transactions marked Other, Bank Transfer, and Ignore have no effect on your taxes."
- Manual transaction entry, filter and search

---

## Tab 3: Profit & Loss

### Purpose
Generate an accountant-ready P&L report using IRS Schedule C expense categories.

### Date Range Controls
- From / To date pickers (default: Jan 1 – Dec 31 of current tax year)
- Quick presets: This Year, Last Year, Q1, Q2, Q3, Q4

### P&L Report Structure

```
[Business Name]
Profit and Loss — [Date Range]

Income Total                              $XXX,XXX.XX
Distributions total from Expenses:        $XX,XXX.XX
Net Owner Funding (Contributions - Dist): -$XX,XXX.XX

Expenses
  Supplies and Materials                  $X,XXX.XX
  Repairs                                 $0.00
  Utilities                               $0.00
  Shipping and Postage                    $XX.XX
  Telephone and Communication             $X,XXX.XX
  Continuing Education                    $XXX.XX
  Outside Services                        $XX,XXX.XX
  Travel                                  $XX,XXX.XX
  Office Supplies                         $X,XXX.XX
  Advertising and Marketing               $XXX.XX
  Interest Charges                        $0.00
  Professional Fees                       $X,XXX.XX
  Membership and Subscription             $X,XXX.XX
  Software and Application Fees           $XX,XXX.XX
  Bank Fees and Services                  $XX.XX
  Payroll Taxes                           $0.00
  Salary and Wages                        $XXX.XX
  Vehicle Expense - Gas                   $X,XXX.XX
  Vehicle Expense Total                   $X,XXX.XX
  Health Insurance                        $0.00
  Home Office - Rent                      $XX,XXX.XX
  Home Office - Indirect                  $X,XXX.XX
  Meals                                   $XX,XXX.XX
  Taxes and Licenses                      $X,XXX.XX
  COGS                                    $0.00
  Contract Labor                          $XX,XXX.XX
  Office Furniture                        $XXX.XX
  Rental Equipment                        $0.00
  Commercial Office - Rent                $0.00
  Refunds                                 $X,XXX.XX
  Foreign Labor                           $XXX.XX
  Payroll Fees                            $XXX.XX
  Equipment                               $0.00
  Office Expense                          $XXX.XX
  Distributions                           $0.00
  Shareholder Loan                        $0.00

Expenses Total                            $XXX,XXX.XX
NET INCOME                                $XX,XXX.XX
TOTAL NET INCOME                          $XX,XXX.XX
```

### Export Buttons
- Download P&L PDF, CSV, QuickBooks, Ledger
- Balance Sheet Request (placeholder — future)

---

## Tab 4: Contractors

### Purpose
Track Contract Labor transactions, assign to named contractors for 1099 prep.

- Manage Contractors modal: Name, Email, Address
- Auto-populated from transactions where `category = 'Contract Labor'`
- Table: `#` | `Date` | `Description` | `Bank Account` | `Business` | `Amount` | `Assign to Contractor`
- Totals panel: Contractor Totals (left) | Business Totals (right)
- Export to Spreadsheet

---

## Tab 5: Known Expenses (AI Rule Builder)

### Purpose
Fred defines merchant → category rules via natural language chat OR manual table. Rules auto-classify on CSV import.

### AI Chat Interface

Fred types naturally:
> "Adobe is Software and Application Fees, Fathom is Software and Application Fees, Google Workspace is Membership and Subscription"

**Flow:**
1. User types into chat input
2. POST to `el_core_ajax_bk_process_rules`
3. PHP calls `EL_AI_Client->complete()` with system prompt below
4. Claude returns JSON rules + plain English confirmation
5. PHP saves rules to `el_bk_rules`, displays confirmation in chat

**System prompt:**
```
You are a bookkeeping assistant for Expanded Learning Solutions LLC.
Extract merchant→category pairs from the user's message and respond with TWO parts:

PART 1 — JSON array only, no markdown fences:
[{"merchant": "Adobe", "category": "Software and Application Fees"}, ...]

PART 2 — Plain English confirmation:
"Got it! Added 3 rules: Adobe → Software and Application Fees, ..."

Valid categories (use ONLY these exact strings):
Supplies and Materials, Repairs, Utilities, Shipping and Postage,
Telephone and Communication, Continuing Education, Outside Services,
Travel, Office Supplies, Advertising and Marketing, Interest Charges,
Professional Fees, Membership and Subscription, Software and Application Fees,
Bank Fees and Services, Payroll Taxes, Salary and Wages, Vehicle Expense - Gas,
Vehicle Expense Total, Health Insurance, Home Office - Rent, Home Office - Indirect,
Meals, Taxes and Licenses, COGS, Contract Labor, Office Furniture,
Rental Equipment, Commercial Office - Rent, Refunds, Foreign Labor,
Payroll Fees, Equipment, Office Expense, Distributions, Shareholder Loan

Map unrecognized categories to the closest valid one and note the mapping.
```

### Manual Rules Table
- Columns: `Merchant/Keyword` | `Match Type` (exact/contains) | `Category` | `Actions`
- Add, edit, delete, drag-to-reorder by priority
- Import/export rules as CSV

---

## Tab 6: Travel Dates

### Purpose
Fred defines business travel date ranges. Any transaction during those dates is auto-tagged as a business expense using smart category mapping, then marked as `suggested` for Fred to review.

### Travel Period Management

**Add Travel Period form:**
- Label (e.g., "NYC Trip — TARC Conference")
- Start Date / End Date
- Purpose / Notes (free text — for IRS documentation)
- Save button

**Travel Periods Table:**
- Columns: `Label` | `Start Date` | `End Date` | `Purpose` | `Transactions Tagged` (count) | `Actions` (edit/delete)

### Travel Category Mapping Logic

When a transaction falls within a travel period, classify it based on merchant keyword patterns:

| Merchant contains... | Auto-category |
|---|---|
| AIRLINE, DELTA, UNITED, AMERICAN, SOUTHWEST, SPIRIT, JETBLUE, FRONTIER | Travel |
| HOTEL, MARRIOTT, HILTON, HYATT, IHG, WESTIN, AIRBNB, VRBO | Travel |
| UBER, LYFT, TAXI, CAB, PARKING, GARAGE | Travel |
| RESTAURANT, CAFE, COFFEE, MCDONALD, CHICK-FIL, SUBWAY, STARBUCKS, DUNKIN, DOORDASH, GRUBHUB, UBEREATS | Meals |
| GAS, SHELL, EXXON, CHEVRON, BP, SUNOCO | Vehicle Expense - Gas |
| All other merchants during travel period | Travel (default) |

This mapping is hardcoded as a sensible default. Fred can still override any individual transaction after review.

### Re-Apply Travel Rules Button
- "Re-Apply Travel Rules to All Transactions" → re-runs travel date logic across all imported transactions
- Useful after adding a new travel period retroactively
- Only changes `unclassified` transactions — does NOT override already `classified` rows

---

## Tab 7: Receipts (AI Receipt Scanner)

### Purpose
Fred uploads receipt photos (jpg, png, pdf). Claude reads the receipt using vision, extracts merchant/date/amount/category, and Fred confirms or adjusts before attaching it to a transaction.

### Receipt Upload Flow

**Step 1 — Upload**
- Drag-and-drop or file picker
- Accepts: jpg, jpeg, png, pdf
- Multiple receipts can be uploaded at once
- Files stored in WordPress uploads: `/wp-content/uploads/els-bookkeeping/receipts/YYYY/MM/`
- File path and URL saved to `el_bk_receipts`

**Step 2 — AI Extraction**
After upload, immediately call `EL_AI_Client->complete()` with the image (base64) and this system prompt:

```
You are a receipt reading assistant for Expanded Learning Solutions LLC.
Analyze this receipt image and extract the following in JSON format:

{
  "merchant": "Business name on the receipt",
  "date": "YYYY-MM-DD format",
  "amount": 00.00,
  "suggested_category": "Most appropriate IRS Schedule C category",
  "confidence": "high | medium | low",
  "notes": "Anything unusual or worth flagging"
}

Valid categories:
Supplies and Materials, Repairs, Utilities, Shipping and Postage,
Telephone and Communication, Continuing Education, Outside Services,
Travel, Office Supplies, Advertising and Marketing, Interest Charges,
Professional Fees, Membership and Subscription, Software and Application Fees,
Bank Fees and Services, Payroll Taxes, Salary and Wages, Vehicle Expense - Gas,
Vehicle Expense Total, Health Insurance, Home Office - Rent, Home Office - Indirect,
Meals, Taxes and Licenses, COGS, Contract Labor, Office Furniture,
Rental Equipment, Commercial Office - Rent, Refunds, Foreign Labor,
Payroll Fees, Equipment, Office Expense, Distributions, Shareholder Loan

If you cannot read a field clearly, set it to null.
Return JSON only — no markdown, no explanation.
```

**Step 3 — Review & Confirm**
After AI extraction, show a review card for each uploaded receipt:
- Thumbnail of the receipt image
- Extracted fields (editable): Merchant, Date, Amount, Category
- Confidence indicator (high/medium/low)
- "Attach to Transaction" dropdown — searches existing transactions by date ± 3 days and amount
- "Create New Transaction" button — creates a manual transaction from extracted data and attaches receipt
- "Save Receipt Only" — saves without attaching (can attach later)

**Step 4 — Attached State**
Once attached, the transaction row in the Expenses tab shows a 📎 badge. Clicking it opens a modal with:
- Receipt thumbnail
- "View Full Size" link
- Extracted data summary
- "Detach Receipt" button

### Receipts Tab View

**Unmatched Receipts panel** (top) — receipts not yet attached to a transaction:
- Thumbnail grid
- Extracted merchant, date, amount, suggested category
- Quick-attach or quick-create buttons

**All Receipts table** (below):
- Columns: `Thumbnail` | `Merchant` | `Date` | `Amount` | `Category` | `Attached To` (transaction link or "Unattached") | `Actions`
- Filter by: Status (attached/unattached), Date range, Category
- Bulk delete

### Receipt Storage
- Physical files: `/wp-content/uploads/els-bookkeeping/receipts/YYYY/MM/filename.jpg`
- Use `wp_handle_upload()` for secure file handling
- Validate file type server-side (MIME check, not just extension)
- Max file size: 10MB per receipt

---

## Tab 8: Settings

- **Business Name** (text, default: "Expanded Learning Solutions LLC")
- **Default Tax Year** (number)
- **Known Bank Accounts** (add/remove list with friendly names)
- **Home Office % Indirect** (number)
- **Vehicle Mileage Rate** (number, default: 0.67)
- **Export Logo** (media uploader for PDF header)
- **Receipt Storage Path** (read-only display of where receipts are stored)

Note: Anthropic API key is managed globally by EL Core AI settings — not duplicated here.

---

## IRS Schedule C Categories — Complete List

These are the ONLY valid values for the `category` field:

```
Supplies and Materials, Repairs, Utilities, Shipping and Postage,
Telephone and Communication, Continuing Education, Outside Services,
Travel, Office Supplies, Advertising and Marketing, Interest Charges,
Professional Fees, Membership and Subscription, Software and Application Fees,
Bank Fees and Services, Payroll Taxes, Salary and Wages, Vehicle Expense - Gas,
Vehicle Expense Total, Health Insurance, Home Office - Rent, Home Office - Indirect,
Meals, Taxes and Licenses, COGS, Contract Labor, Office Furniture,
Rental Equipment, Commercial Office - Rent, Refunds, Foreign Labor,
Payroll Fees, Equipment, Office Expense, Distributions, Shareholder Loan
```

---

## AI Integration

Use the existing `EL_AI_Client` in `includes/class-ai-client.php`. Never call Anthropic directly.

```php
$core = EL_Core::instance();

// Text completion (Known Expenses rule builder)
$response = $core->ai_client->complete(
    $system_prompt,
    $user_message,
    'claude-sonnet-4-20250514',
    1500
);

// Vision / image completion (Receipt scanner)
// EL_AI_Client->complete() may need a vision-capable override.
// If EL_AI_Client does not support image input yet, add a
// complete_with_image($system, $user_text, $base64_image, $media_type)
// method to class-ai-client.php following the same pattern.
```

---

## AJAX Actions to Register

```php
// Expenses
add_action('el_core_ajax_bk_import_csv',           [$this, 'handle_csv_import']);
add_action('el_core_ajax_bk_update_transaction',    [$this, 'handle_update_transaction']);
add_action('el_core_ajax_bk_bulk_confirm',          [$this, 'handle_bulk_confirm']);
add_action('el_core_ajax_bk_export_csv',            [$this, 'handle_export_csv']);
add_action('el_core_ajax_bk_export_pl',             [$this, 'handle_export_pl']);

// Known Expenses
add_action('el_core_ajax_bk_process_rules',         [$this, 'handle_process_rules']);
add_action('el_core_ajax_bk_save_rule',             [$this, 'handle_save_rule']);
add_action('el_core_ajax_bk_delete_rule',           [$this, 'handle_delete_rule']);
add_action('el_core_ajax_bk_reorder_rules',         [$this, 'handle_reorder_rules']);

// Travel Dates
add_action('el_core_ajax_bk_save_travel_period',    [$this, 'handle_save_travel_period']);
add_action('el_core_ajax_bk_delete_travel_period',  [$this, 'handle_delete_travel_period']);
add_action('el_core_ajax_bk_reapply_travel_rules',  [$this, 'handle_reapply_travel_rules']);

// Receipts
add_action('el_core_ajax_bk_upload_receipt',        [$this, 'handle_upload_receipt']);
add_action('el_core_ajax_bk_attach_receipt',        [$this, 'handle_attach_receipt']);
add_action('el_core_ajax_bk_detach_receipt',        [$this, 'handle_detach_receipt']);
add_action('el_core_ajax_bk_delete_receipt',        [$this, 'handle_delete_receipt']);

// Contractors
add_action('el_core_ajax_bk_save_contractor',       [$this, 'handle_save_contractor']);
add_action('el_core_ajax_bk_delete_contractor',     [$this, 'handle_delete_contractor']);
add_action('el_core_ajax_bk_assign_contractor',     [$this, 'handle_assign_contractor']);
```

---

## User Roles & Access

- **Administrator:** Full access to all tabs including Settings
- **Editor (Stephanie):** All tabs except Settings

Use `el_core_can('manage_bookkeeping_settings')` to gate the Settings tab.

---

## Build Order (Recommended)

### Phase 1 — Foundation
- `module.json` with full DB schema
- `class-bookkeeping-module.php` skeleton: singleton, hooks, admin menu
- Admin page shell with 8-tab navigation
- Initialize `PROGRESS.md`

### Phase 2 — Expenses Tab
- CSV upload (BofA format + generic fallback)
- Transaction table with inline category editing and color coding
- Filters, search, manual entry

### Phase 3 — Known Expenses Tab
- Manual rules table (CRUD + drag reorder)
- AI chat interface → AJAX → EL_AI_Client → parse JSON → save rules

### Phase 4 — Auto-Classification
- Wire Known Expense rules into CSV import
- Bulk confirm button

### Phase 5 — Travel Dates Tab
- Travel period CRUD
- Travel category mapping logic
- Wire into CSV import (Step 1 of auto-classification)
- ✈️ badge on expense rows
- Re-apply button

### Phase 6 — Receipts Tab
- File upload handler with MIME validation
- AI vision extraction via EL_AI_Client
- Review/confirm UI
- Attach to transaction
- 📎 badge on expense rows + modal viewer
- Unmatched receipts panel

### Phase 7 — P&L Tab
- Date range + presets
- P&L report generation
- PDF and CSV export

### Phase 8 — Income & Deposits Tab
- CSV upload, income categories, totals

### Phase 9 — Contractors Tab
- Contractor CRUD, assignment, spreadsheet export

### Phase 10 — Settings Tab
- Settings form using EL_Admin_UI framework

---

## PROGRESS.md Template

Create at `modules/bookkeeping/PROGRESS.md` and update after every Cursor session:

```markdown
# Bookkeeping Module — Build Progress

## Current Phase: [X]
## Last Updated: [date]
## Plugin Version: [x.x.x]

## Completed
- [ ] Phase 1: Foundation
- [ ] Phase 2: Expenses Tab
- [ ] Phase 3: Known Expenses Tab
- [ ] Phase 4: Auto-Classification (Known Expense Rules)
- [ ] Phase 5: Travel Dates Tab + Auto-Classification
- [ ] Phase 6: Receipts Tab
- [ ] Phase 7: P&L Tab
- [ ] Phase 8: Income & Deposits Tab
- [ ] Phase 9: Contractors Tab
- [ ] Phase 10: Settings Tab

## Current Status
[What works right now]

## Next Steps
[Exactly what to build next session]

## Known Issues
[Any bugs or blockers]
```

---

## Cursor Kickoff Prompt

> "Read `el-core-cursor-handoff.md` for full architecture context, then read `SPEC-BOOKKEEPING-MODULE.md` for the feature requirements. Build the ELS Bookkeeping Module as a new EL Core module following the exact same patterns as the `expand-site` module. Start with Phase 1 (Foundation): module.json with the full database schema, class-bookkeeping-module.php skeleton with singleton pattern, admin page shell with 8-tab navigation. Initialize PROGRESS.md in modules/bookkeeping/ and update it when Phase 1 is complete."

---

*End of SPEC-BOOKKEEPING-MODULE.md*
