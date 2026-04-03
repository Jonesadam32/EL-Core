# EL Core — Start Here Next Session

> **PURPOSE:** This is the shared handoff document between Claude and Cursor.
> Read this FIRST every session. Update it LAST before finishing.
>
> **Last Updated:** April 2, 2026
> **Updated By:** Cursor
> **Current Plugin Version:** v1.37.1 — Fixed bidirectional rule matching + added Re-Classify Expenses button.
>
> **NEXT TASK: Build merchant name cleaner for rule importer (Path 2)**
>
> **Problem:** When importing prior-year expense CSVs (from raw bank statements) as rules on the Known Expenses tab, the descriptions are full of bank junk like `CHECKCARD 0103 MICROSOFT*SUBSCRIPTION MSBILL.INFO WA XXXXX9930XXXXXXXXXX3036 RECURRING`. These get saved as rule keywords verbatim, which makes matching unreliable.
>
> **What to build:** A `clean_merchant_name( string $raw )` PHP function in `class-bookkeeping-module.php` that strips bank metadata from raw descriptions and extracts just the merchant name. Patterns to handle:
> - Strip leading `CHECKCARD`, `PURCHASE`, `DEBIT` prefixes
> - Strip the 4-digit date code after the prefix (e.g., `0103`, `1121`)
> - Strip `XXXXX` masked card/account numbers (e.g., `XXXXX9930XXXXXXXXXX3036`)
> - Strip `CKCD XXXX` codes (e.g., `CKCD 5734`)
> - Strip trailing `XXXXXXXXXX` sequences (e.g., `XXXXXXXXXX247000`, `XXXXXXXXXX632166`)
> - Strip `RECURRING` keyword
> - Strip `INTERNATIONAL TRANSACTION FEE` (skip these rows entirely or flag them)
> - Strip state codes at end (2-letter, e.g., `WA`, `CA`, `NY`) — careful not to strip merchant names
> - Strip phone numbers (e.g., `757-965-6600`, `XXX-XX35753`)
> - Strip long numeric sequences (e.g., `24492153325717520805374`)
> - Handle `*` separators (e.g., `MICROSOFT*SUBSCRIPTION` → `Microsoft Subscription`, `DRI*Hitpaw.net` → `Hitpaw.net`)
> - Handle `HTTPS` URLs (e.g., `HTTPSACUITYSCNY` → `Acuity Scheduling`, `HTTPSWWW.LOOMCA` → `Loom`)
> - Handle `WEB*` prefix (e.g., `WEB*Hostgator.com` → `Hostgator.com`)
> - Title-case the result
>
> **Where to integrate:**
> - Call `clean_merchant_name()` in `handle_import_rules_csv()` before passing keywords to `bulk_save_rules()`
> - Also call it in `handle_import_ledger()` before creating rules
> - Do NOT use it during bank statement import (`handle_csv_import`) — those descriptions are already clean (Claude-cleaned CSVs)
>
> **Test examples from actual data:**
> - `CHECKCARD 0103 MICROSOFT*SUBSCRIPTION MSBILL.INFO WA XXXXX9930XXXXXXXXXX3036 RECURRING` → `Microsoft Subscription`
> - `PURCHASE 0102 ACUITYSCHEDULING.COM HTTPSACUITYSCNY XXXXX1630XXXXXXXXXX5373 RECURRING CKCD 5734 XXXXXXXXXX247000` → `Acuity Scheduling`
> - `CHECKCARD 0131 WEB*Hostgator.com 713-5285287 MA 24906414030192603044303 RECURRING CKCD 4816 XXXXXXXXXXXX5897` → `Hostgator.com`
> - `DRI*Hitpaw.net` → `Hitpaw.net`
> - `CHECKCARD 0821 ADOBE *CREATIVE CLOUD CA XXXXX1532XXXXXXXXXX1822 CKCD 5734 XXXXXXXXXX632166` → `Adobe Creative Cloud`
> - `PURCHASE 0823 ZAPIER.COM/CHARGE ZAPIER.COM CA XXXXX3432XXXXXXXXXX3999 CKCD 5734 XXXXXXXXXX632166` → `Zapier.com`
>
> **Sample raw CSV for testing:** `g:\My Drive\Fred Business\Taxes\2023\Expenses\New CSV\Computer Software-2023.csv`
>
> **Also:** User needs to delete all existing messy rules and re-import from the raw CSVs after this feature is built. The bulk delete on Known Expenses tab already supports this.
>
> **After that:** User will re-classify expenses on the Expenses tab using the Re-Classify button.
>
> **Bump to v1.37.2 when done.**
>
> **SWITCHING COMPUTERS:** Repo backed up to GitHub. On the other machine: `git pull origin main`

---

## 🚨 IMMEDIATE PRIORITY — BOOKKEEPING TAB CRITICAL ERROR

**The Dashboard tab works great. Every other tab (Income, Expenses, Contractors, P&L, Travel Dates, Known Expenses, Receipts, Settings) crashes with "There has been a critical error on this website."**

### ROOT CAUSE — IDENTIFIED, NOT YET FIXED

`EL_Admin_UI::notice()` signature changed at some point to take an **array** argument:

```php
// CURRENT signature (class-admin-ui.php line 262):
public static function notice( array $args ): string {
    $message = $args['message'] ?? '';
    $type    = $args['type']    ?? 'info';
    ...
}
```

But every single bookkeeping view is calling it the OLD way with positional string arguments:

```php
// WRONG — causes PHP 8 TypeError fatal:
EL_Admin_UI::notice( __( 'Some message', 'el-core' ), 'info' )

// CORRECT:
EL_Admin_UI::notice( [ 'message' => __( 'Some message', 'el-core' ), 'type' => 'info' ] )
```

This causes a `TypeError: EL_Admin_UI::notice(): Argument #1 ($args) must be of type array, string given` on PHP 8, which fatals inside the `ob_start` buffer and produces the critical error message.

### ALL AFFECTED CALLS (fix all of these):

| File | Line(s) | Current (wrong) | Fix to |
|------|---------|-----------------|--------|
| `admin/views/income.php` | 38, 42, 71 | `notice( string, string )` | `notice( ['message'=>..., 'type'=>...] )` |
| `admin/views/expenses.php` | 99 | same | same |
| `admin/views/contractors.php` | 158 | same | same |
| `admin/views/travel-dates.php` | 58 | same | same |
| `admin/views/settings.php` | 11, 31 | same | same |
| `admin/views/receipts.php` | 21, 67 | same | same |
| `admin/views/known-expenses.php` | 46 | same | same |

### THE FIX

Do a global search-and-replace across all files in `el-core/modules/bookkeeping/admin/views/`. The pattern is:

```
EL_Admin_UI::notice( __( 'MESSAGE', 'el-core' ), 'TYPE' )
```

Replace with:

```
EL_Admin_UI::notice( [ 'message' => __( 'MESSAGE', 'el-core' ), 'type' => 'TYPE' ] )
```

For multi-line calls like in `receipts.php` and `income.php`, read the file carefully and convert each one.

After fixing, bump to **v1.35.3**, build ZIP, push.

---

## CURRENT STATE — BOOKKEEPING MODULE

### What's Working ✅
- Plugin CSS now loads correctly (was broken before v1.35.2 — wrong `el-admin` handle, now `el-core-admin`)
- Tab navigation: pill-style buttons render correctly, active state highlighted
- **Dashboard tab**: fully functional — stat cards (4 metrics), quick-access card grid (8 module links), year selector
- Year selector: dropdown above tabs, persists year across tab switches
- DB pre-fetch pattern: data fetched before `ob_start()` so fatals surface cleanly (instead of mid-page critical error)
- All view files redesigned per reference site
- **All tabs render without critical error** — `EL_Admin_UI::notice()` calls fixed in v1.35.3

### What Was Fixed in v1.35.3 ✅
- All 11 `EL_Admin_UI::notice()` calls across 7 view files converted from positional args to array syntax
- Files fixed: income.php, expenses.php, contractors.php, travel-dates.php, settings.php, receipts.php, known-expenses.php

### Remaining CSS Issue
- Tabs still visually render as plain links (CSS specificity issue — scoped under `.el-admin-wrap` but WP admin still wins on `<a>` tags in some themes)

### Version History for This Work
| Version | What | Status |
|---------|------|--------|
| v1.34.8 | Bookkeeping module Phase 1 foundation | Built |
| v1.34.9 | Fix `$wpdb->prepare()` fatal + add year selector | Built |
| v1.35.0 | Full UI redesign: Dashboard, tab pills, all views | Built |
| v1.35.1 | CSS specificity fix (scope under .el-admin-wrap) | Built |
| v1.35.2 | **Fix CSS never loading (wrong handle `el-admin` → `el-core-admin`)** | Built |
| v1.35.3 | **Fix `EL_Admin_UI::notice()` wrong call signature in all 7 view files** | Built |
| v1.35.4 | **AI Rule Builder + CSV rule import on Known Expenses tab** | Built |
| v1.35.5 | **Update expense categories to match actual accounting books** | Built |
| v1.35.6 | **CSV Transaction Import + Category mapping for rule import** | **CURRENT — deployed** |

---

## BOOKKEEPING MODULE — WHAT WAS BUILT (Phase 1)

### Files
- `el-core/modules/bookkeeping/class-bookkeeping-module.php` — main class
- `el-core/modules/bookkeeping/module.json` — DB schema (6 tables), capabilities, settings
- `el-core/modules/bookkeeping/admin/views/dashboard.php` — stat cards + quick-access grid
- `el-core/modules/bookkeeping/admin/views/expenses.php` — Schedule C summary bar + transaction table
- `el-core/modules/bookkeeping/admin/views/income.php` — info banners + business total card
- `el-core/modules/bookkeeping/admin/views/profit-loss.php` — P&L text report
- `el-core/modules/bookkeeping/admin/views/contractors.php` — two-panel summary + assignment table
- `el-core/modules/bookkeeping/admin/views/known-expenses.php` — AI chat + rules table
- `el-core/modules/bookkeeping/admin/views/travel-dates.php` — travel period CRUD
- `el-core/modules/bookkeeping/admin/views/receipts.php` — upload zone + receipt grid
- `el-core/modules/bookkeeping/admin/views/settings.php` — business info + Schedule C settings
- `el-core/modules/bookkeeping/assets/css/bookkeeping.css` — all styles (scoped under .el-admin-wrap)
- `el-core/modules/bookkeeping/assets/js/bookkeeping.js` — JS stubs

### Database Tables (in module.json)
- `el_bk_transactions` — all expense and income transactions
- `el_bk_rules` — AI auto-classification rules (Known Expenses)
- `el_bk_travel_periods` — travel date ranges for auto-categorization
- `el_bk_receipts` — uploaded receipts
- `el_bk_contractors` — contractor records
- `el_bk_contractor_assignments` — transaction-to-contractor links

### Capabilities
- `view_bookkeeping` — view the bookkeeping admin page
- `manage_bookkeeping` — full access
- `manage_bookkeeping_settings` — access to Settings tab

### Key Architecture Decisions
- Default tab is Dashboard (not Expenses)
- Year selector (`?year=YYYY`) persists across tab switches
- All DB calls happen BEFORE `ob_start()` to prevent swallowed fatals
- Pre-fetched data available to views as `$prefetch_expenses`, `$prefetch_income`, `$prefetch_contractors`, `$prefetch_receipts`, `$prefetch_contract_labor`
- `$tax_year` available to all views as a direct variable
- CSS scoped under `.el-admin-wrap` to beat WP admin specificity

---

## AFTER FIXING THE NOTICE CALLS — NEXT STEPS

1. **Test all tabs** — each should render without critical error
2. **Upload CSV** — Phase 2 will implement actual CSV parsing and import
3. **Known Expenses AI tab** — wire up `EL_AI_Client` for the chat interface (Phase 3)
4. **Travel Dates** — CRUD already scaffolded, verify it works end-to-end
5. **Receipts upload** — Phase 4

---

## CRITICAL LESSONS LEARNED THIS SESSION

- **`EL_Admin_UI::notice()` takes an array** — `notice( ['message' => '...', 'type' => 'info'] )` NOT `notice( '...', 'info' )`
- **`wp_enqueue_style` dependency handle matters** — the handle must EXACTLY match what was registered. `el-admin` was never registered; the correct handle is `el-core-admin`. A wrong dependency silently prevents the stylesheet from loading at all.
- **WordPress admin CSS overrides everything with high specificity** — all module CSS must be scoped under `.el-admin-wrap` AND use `!important` on key properties (color, border, background, text-decoration) for `<a>` tags that WordPress aggressively overrides.
- **`$wpdb->prepare()` requires variadic args** — use `...$values` spread, NOT `array_merge($values, ...)` as second argument.

---

## ARCHITECTURE NOTES (unchanged from before)

- Read `el-core-cursor-handoff.md` for full module architecture
- Read `SPEC-BOOKKEEPING-MODULE.md` for full feature spec
- Read `CURSOR-TODO.md` for complete build checklist
- **Expand Site** module is working (fixed in v1.34.7) — do not disturb
- All other modules unaffected by this work
