# Invoicing Module — Cursor Build Handoff

> **Location:** `docs/cursor-handoff-invoicing-module.md` (canonical copy in repo)
> **Date:** February 24, 2026 | **Last updated:** March 1, 2026 (moved into repo)
> **Purpose:** Complete build spec for the invoicing module, aligned with EL Core architecture decisions.
> **Read before starting:** `START-HERE-NEXT-SESSION.md`, `ARCHITECTURE-DECISIONS-FEB-22-2026.md`, `el-core-cursor-handoff.md`
> **Prerequisite:** v1.22.0 deployed and tested
>
> **Alignment (Feb 24, 2026):** Client linking uses existing core `el_organizations` and `el_contacts` (not Fluent CRM IDs). Project linking uses `el_es_projects.id` until shared `el_projects` exists. Proposals: read-only pre-populate from `el_es_proposals.final_price`. Partial payments and denormalized totals included.

---

## OVERVIEW

The invoicing module replaces QuickBooks ($75/month) as ELS's sole invoicing tool. It handles invoice creation, payment tracking, product management, revenue reporting, and CSV export for the bookkeeper (Done For You Tax).

**Module slug:** `invoicing`
**Module type:** Proprietary internal tool (like Expand Site — NOT resale-ready)
**Table prefix:** `el_inv_`
**CSS class prefix:** `el-inv-`
**Asset files:** `invoicing.css`, `invoicing.js`

This module follows all standard EL Core conventions: `module.json` manifest, singleton pattern, `EL_` class prefix, `EL_Admin_UI::*` for all admin views, shortcodes return HTML strings.

---

## ARCHITECTURE ALIGNMENT NOTES

### Client Linking Strategy

The `el_organizations` and `el_contacts` tables already exist as **core infrastructure** (built in `class-organizations.php`, loaded from `class-el-core.php`). These are the canonical client records for all business operations modules.

**Invoices link to organizations, not WordPress users or Fluent CRM.**

```
el_inv_invoices.organization_id → el_organizations.id
el_inv_invoices.contact_id → el_contacts.id (billing contact)
el_inv_invoices.project_id → el_es_projects.id (optional — Expand Site link)
```

Fluent CRM remains the email/marketing tool. `el_organizations` is the business relationship record. No duplication — different concerns.

### Project Linking

Invoices optionally link to `el_es_projects` directly via `project_id`. The shared `el_projects` table from Architecture Decision 3 does NOT exist yet. When it's built, a migration will update `project_id` to reference the shared table instead. For now, link to `el_es_projects.id`.

### Proposals

Proposals live inside the Expand Site module (`el_es_proposals`). The invoicing module does NOT duplicate proposal data. However, when creating an invoice from a project, the UI should pre-populate amounts from the accepted proposal's `final_price` field. This is a read-only cross-module query, not a dependency.

### No Online Payments (Yet)

This module tracks manually received payments (check, ACH, wire, Zelle). No Stripe, no payment gateway. Online payment processing is a potential future add-on.

---

## DATABASE TABLES

### `el_inv_products`

Products/services that appear as line items on invoices. Seed data provided below.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| name | VARCHAR(255) NOT NULL | Product/service name |
| slug | VARCHAR(100) NOT NULL | URL-safe identifier |
| category | VARCHAR(50) DEFAULT 'service' | service, subscription, contract |
| default_price | DECIMAL(10,2) DEFAULT 0 | Pre-fill price on invoices |
| billing_cycle | VARCHAR(20) DEFAULT 'one-time' | one-time, monthly, quarterly, annual |
| status | VARCHAR(20) DEFAULT 'active' | active, inactive |
| description | TEXT | Internal notes |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### `el_inv_invoices`

The main invoice record. One invoice per client engagement/billing event.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| organization_id | BIGINT UNSIGNED NOT NULL | FK → `el_organizations.id` |
| contact_id | BIGINT UNSIGNED DEFAULT 0 | FK → `el_contacts.id` (billing contact) |
| project_id | BIGINT UNSIGNED DEFAULT 0 | FK → `el_es_projects.id` (optional) |
| invoice_number | VARCHAR(50) NOT NULL | Format: ELS-YYYY-NNN (auto-generated) |
| status | VARCHAR(20) DEFAULT 'draft' | draft, sent, viewed, paid, partial, overdue, cancelled |
| issue_date | DATE NULL | Date invoice was issued/sent |
| due_date | DATE NULL | Payment due date |
| paid_date | DATE NULL | Date fully paid |
| subtotal | DECIMAL(10,2) DEFAULT 0 | Sum of line items |
| tax_rate | DECIMAL(5,2) DEFAULT 0 | Tax percentage (0 for most ELS clients) |
| tax_amount | DECIMAL(10,2) DEFAULT 0 | Calculated tax |
| total | DECIMAL(10,2) DEFAULT 0 | subtotal + tax_amount |
| amount_paid | DECIMAL(10,2) DEFAULT 0 | Running total of payments received |
| balance_due | DECIMAL(10,2) DEFAULT 0 | total - amount_paid |
| notes | TEXT | Notes visible on invoice |
| internal_notes | TEXT | Admin-only notes (not shown on invoice) |
| sent_at | DATETIME NULL | When email was triggered |
| viewed_at | DATETIME NULL | When client first opened (future: tracking pixel) |
| created_by | BIGINT UNSIGNED DEFAULT 0 | WP user ID |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |
| updated_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

**Invoice number auto-generation:** On creation, query `MAX(id)` for current year, increment. Format: `ELS-2026-001`, `ELS-2026-002`, etc. Reset sequence each calendar year.

**Status transitions:**
- `draft` → `sent` (admin sends to client)
- `sent` → `viewed` (client opens — future feature)
- `sent` or `viewed` → `partial` (payment received but balance remaining)
- `sent` or `viewed` or `partial` → `paid` (balance_due = 0)
- Any status → `overdue` (automated: due_date passed, balance_due > 0)
- Any status → `cancelled`

### `el_inv_line_items`

Individual line items on an invoice. Each references a product or is freeform.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| invoice_id | BIGINT UNSIGNED NOT NULL | FK → `el_inv_invoices.id` |
| product_id | BIGINT UNSIGNED DEFAULT 0 | FK → `el_inv_products.id` (0 = freeform) |
| description | VARCHAR(500) NOT NULL | Line item description |
| quantity | DECIMAL(10,2) DEFAULT 1 | |
| unit_price | DECIMAL(10,2) DEFAULT 0 | |
| amount | DECIMAL(10,2) DEFAULT 0 | quantity × unit_price |
| sort_order | INT UNSIGNED DEFAULT 0 | Display order on invoice |

### `el_inv_payments`

Records of payments received against invoices. One invoice can have multiple partial payments.

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED AUTO_INCREMENT PK | |
| invoice_id | BIGINT UNSIGNED NOT NULL | FK → `el_inv_invoices.id` |
| amount | DECIMAL(10,2) NOT NULL | Payment amount |
| payment_method | VARCHAR(50) DEFAULT '' | check, ach, wire, zelle, other |
| payment_date | DATE NOT NULL | Date payment was received |
| reference_number | VARCHAR(100) DEFAULT '' | Check number, transaction ID, etc. |
| notes | TEXT | |
| recorded_by | BIGINT UNSIGNED DEFAULT 0 | WP user ID who logged the payment |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

---

## CAPABILITIES

| Capability | Description | Default Roles |
|------------|-------------|---------------|
| `manage_invoices` | Full CRUD, settings, reports, exports | administrator |
| `create_invoices` | Create and send invoices | administrator, editor |
| `view_invoices` | Client-side: view own invoices only | administrator, editor, subscriber |

---

## SHORTCODES

| Tag | File | Description |
|-----|------|-------------|
| `el_invoice_list` | `shortcodes/invoice-list.php` | Admin/staff: all invoices with filters and stats |
| `el_client_invoices` | `shortcodes/client-invoices.php` | Client portal: their invoices and payment status |
| `el_invoice_view` | `shortcodes/invoice-view.php` | Single invoice detail (printable/PDF-ready) |
| `el_revenue_dashboard` | `shortcodes/revenue-dashboard.php` | Revenue reporting with breakdowns |

**Shortcode function naming (per module loader convention):**
- `el_invoice_list` → `el_shortcode_invoice_list`
- `el_client_invoices` → `el_shortcode_client_invoices`
- `el_invoice_view` → `el_shortcode_invoice_view`
- `el_revenue_dashboard` → `el_shortcode_revenue_dashboard`

---

## ADMIN UI

All admin pages use `EL_Admin_UI::*` exclusively. No raw HTML.

### Admin Menu Structure

Register under EL Core main menu:
- **Invoices** (submenu) → Invoice list page
- **Products** (submenu under Invoices or separate) → Product management
- **Revenue** (submenu) → Revenue dashboard

### Invoice List Page (`admin/views/invoice-list.php`)

- Filter bar: status (all/draft/sent/paid/overdue/cancelled), date range, client/organization
- Stats row at top: total outstanding, total overdue, collected this month, collected this year
- Table columns: Invoice #, Client (org name), Amount, Status (color-coded badge), Issue Date, Due Date, Balance Due, Actions
- Actions per row: View, Edit, Duplicate, Record Payment, Send/Resend, Cancel
- Bulk actions: Send reminders (overdue only), Export selected, Mark paid
- "Create Invoice" button → opens invoice editor

### Invoice Editor Page (`admin/views/invoice-edit.php`)

- Organization selector (autocomplete from `el_organizations` — same pattern as Expand Site project creation)
- Billing contact selector (populated from org's contacts after org is selected)
- Optional project link (autocomplete from `el_es_projects` filtered by selected org)
- Invoice date + due date (date pickers, due date defaults to +30 days)
- Line items section:
  - "Add Line Item" button
  - Each row: Product dropdown (auto-fills description + price) OR freeform description, quantity, unit price, calculated amount
  - Drag-to-reorder (sort_order)
  - Delete line item (X button)
  - Auto-calculated subtotal
- Tax rate field (default 0, most ELS clients don't charge tax)
- Calculated total
- Notes field (visible on invoice)
- Internal notes field (admin only)
- Payment terms (pre-populated from Expand Site `default_payment_terms` setting if available)
- Action buttons: Save Draft, Send Invoice, Preview

### Product Management Page (`admin/views/product-list.php`)

- Card grid or table of products
- Each: name, category badge, default price, billing cycle, status toggle
- "Add Product" modal: name, slug (auto-generated from name), category select, default price, billing cycle, description
- Edit/Delete per product
- Seed data button (first time only) — creates the 6 default products listed below

### Payment Recording Modal

When "Record Payment" is clicked on an invoice:
- Amount field (pre-filled with balance_due)
- Payment method dropdown (Check, ACH, Wire, Zelle, Other)
- Payment date (date picker, defaults to today)
- Reference number (optional — check number, transaction ID)
- Notes (optional)
- On save: create `el_inv_payments` record, update invoice `amount_paid` and `balance_due`, if `balance_due` = 0 set status to `paid` and `paid_date`

---

## CLIENT-FACING FEATURES

### Client Invoice Portal (`[el_client_invoices]`)

Shows invoices for the current logged-in user's organization. Lookup chain:
1. Get current user ID
2. Find their `el_contacts` record(s)
3. Get the `organization_id` from their contact record
4. Query invoices for that organization

Display:
- Outstanding balance summary at top
- Invoice table: number, date, amount, status badge, balance due
- Click invoice → expand or navigate to detail view
- Status badges: Draft (gray), Sent (blue), Paid (green), Overdue (red), Partial (amber)

### Invoice Detail View (`[el_invoice_view]`)

- Professional invoice layout suitable for printing / PDF
- ELS branding: logo (from `el_core_get_logo_url()`), org name, colors
- Header: "INVOICE" + invoice number + date + due date
- Bill To: organization name, billing contact name/email
- Line items table: description, quantity, unit price, amount
- Subtotal, tax (if any), total
- Payment history (if any payments recorded)
- Balance due (prominent)
- Payment terms text
- Footer: ELS contact info
- Print button (CSS print styles)
- Future: PDF download button (same PDF generation pattern as Certificates module)

---

## REVENUE DASHBOARD (`[el_revenue_dashboard]`)

Admin-only view. All data from `el_inv_invoices`, `el_inv_line_items`, `el_inv_payments`, `el_inv_products`.

### Overall Metrics (top row cards)
- Total revenue this month (sum of payments received)
- Total revenue this quarter
- Total revenue this year
- Prior year comparison (% change)
- Total outstanding (unpaid invoice balances)
- Total overdue (overdue invoice balances)
- Average days to payment (from issue_date to paid_date)

### Revenue by Product (chart + table)
- Join `el_inv_line_items` → `el_inv_products` → aggregate by product
- Bar chart or horizontal bar: revenue per product
- Table: product name, total revenue, % of total, trend indicator (vs prior period)

### Revenue by Client (table)
- Join `el_inv_invoices` → `el_organizations`
- Table: org name, total invoiced, total paid, outstanding balance, lifetime value
- Sortable columns
- Top 10 clients highlighted

### Revenue by Time (line chart)
- Monthly revenue trend (last 12 months)
- Option to toggle: collected revenue vs. invoiced revenue
- Year-over-year comparison line

---

## CSV EXPORT FOR BOOKKEEPER

One-click export from the invoice list page. This is what Fred sends to Done For You Tax each month.

### Monthly Export
**Filename:** `els-invoices-YYYY-MM.csv`
**Columns:**
- Date (issue_date)
- Invoice Number
- Client Name (organization name)
- Description (concatenated line item descriptions)
- Amount (invoice total)
- Payment Status (paid/partial/unpaid)
- Payment Date (paid_date or latest payment date)
- Payment Method (from payment record)
- Amount Paid
- Balance Due

### Quarterly/Annual Summary Export
**Filename:** `els-revenue-summary-YYYY-QN.csv` or `els-revenue-summary-YYYY.csv`
**Columns:**
- Period (month)
- Total Invoiced
- Total Collected
- Outstanding
- Number of Invoices
- Number of Payments

### Export AJAX Handler
- `inv_export_csv` — accepts `period` (month/quarter/year), `start_date`, `end_date`
- Returns CSV file download (proper headers: `Content-Type: text/csv`, `Content-Disposition: attachment`)
- Requires `manage_invoices` capability

---

## AJAX HANDLERS

All handlers follow EL Core AJAX convention. All require authentication (no `nopriv` variants needed — invoicing is admin/staff only, except client portal views).

### Invoice CRUD
| Action | Method | Capability | Notes |
|--------|--------|------------|-------|
| `inv_create_invoice` | POST | `create_invoices` | Creates invoice + line items |
| `inv_update_invoice` | POST | `create_invoices` | Updates invoice + line items |
| `inv_delete_invoice` | POST | `manage_invoices` | Soft delete (set cancelled) |
| `inv_get_invoice` | GET | `view_invoices` | Returns invoice + line items + payments |
| `inv_duplicate_invoice` | POST | `create_invoices` | Copies invoice with new number, draft status |
| `inv_send_invoice` | POST | `create_invoices` | Marks sent, triggers email via FluentSMTP |

### Payment
| Action | Method | Capability | Notes |
|--------|--------|------------|-------|
| `inv_record_payment` | POST | `manage_invoices` | Creates payment, updates invoice totals |
| `inv_delete_payment` | POST | `manage_invoices` | Removes payment, recalculates invoice totals |

### Product CRUD
| Action | Method | Capability | Notes |
|--------|--------|------------|-------|
| `inv_create_product` | POST | `manage_invoices` | |
| `inv_update_product` | POST | `manage_invoices` | |
| `inv_delete_product` | POST | `manage_invoices` | |
| `inv_get_products` | GET | `create_invoices` | For invoice editor product dropdown |
| `inv_seed_products` | POST | `manage_invoices` | One-time: creates default products |

### Reporting & Export
| Action | Method | Capability | Notes |
|--------|--------|------------|-------|
| `inv_get_revenue_data` | GET | `manage_invoices` | Dashboard data with date range params |
| `inv_export_csv` | GET | `manage_invoices` | CSV file download |

### Client Portal (needs `nopriv` if clients aren't WP users — but they ARE per Expand Site pattern)
| Action | Method | Capability | Notes |
|--------|--------|------------|-------|
| `inv_get_client_invoices` | GET | `view_invoices` | Filtered to current user's org |

---

## OVERDUE AUTOMATION

On admin page load (or via WP Cron if preferred):
1. Query invoices where `status` IN ('sent', 'viewed', 'partial') AND `due_date` < today AND `balance_due` > 0
2. Update status to `overdue`
3. Optionally flag for reminder email (future: FluentCRM automation trigger)

This is the same pattern as Expand Site's deadline flagging system — check on page load, update status, show badges.

---

## SEED DATA: DEFAULT PRODUCTS

Create these on first activation or via "Seed Products" button:

| Name | Slug | Category | Default Price | Billing Cycle |
|------|------|----------|--------------|---------------|
| LMS Platform Licensing | lms-licensing | subscription | 0 | monthly |
| Professional Development Training | pd-training | service | 0 | one-time |
| Coaching Services | coaching | service | 0 | one-time |
| Retreat Facilitation | retreat | service | 0 | one-time |
| Website / Tech Services — Expand Site | expand-site | service | 0 | one-time |
| NYC SMV Tool | nyc-smv | contract | 0 | one-time |

Default prices are 0 because each engagement has custom pricing. Products exist to categorize revenue, not to set fixed prices.

---

## INTEGRATION POINTS

| System | How Invoicing Connects |
|--------|----------------------|
| `el_organizations` (core) | `organization_id` on invoices — client identity |
| `el_contacts` (core) | `contact_id` on invoices — billing contact |
| `el_es_projects` (Expand Site) | `project_id` on invoices — optional project link |
| `el_es_proposals` (Expand Site) | Read-only: pre-populate invoice amount from accepted proposal `final_price` |
| FluentSMTP | Send invoice emails (use `wp_mail()` — FluentSMTP intercepts it) |
| Done For You Tax | Monthly CSV export |
| EL Core Brand | Invoice display uses `el_core_get_logo_url()`, `el_core_get_org_name()`, brand colors |

---

## WHAT THIS MODULE DOES NOT DO

- ❌ Bank reconciliation (Done For You Tax)
- ❌ Expense tracking (Done For You Tax from bank statements)
- ❌ P&L / balance sheet / cash flow (Done For You Tax)
- ❌ Tax calculations (Done For You Tax)
- ❌ Online payment processing (future Phase 2 add-on with Stripe)
- ❌ Recurring invoice auto-generation (Phase 2 — manual creation for now)
- ❌ PDF generation (Phase 2 — uses print styles for now, PDF pattern comes with Certificates module)

---

## FILE STRUCTURE

```
modules/invoicing/
├── module.json
├── class-invoicing-module.php
├── shortcodes/
│   ├── invoice-list.php          # [el_invoice_list]
│   ├── client-invoices.php       # [el_client_invoices]
│   ├── invoice-view.php          # [el_invoice_view]
│   └── revenue-dashboard.php     # [el_revenue_dashboard]
├── admin/
│   └── views/
│       ├── invoice-list.php      # Admin invoice list + filters
│       ├── invoice-edit.php      # Invoice create/edit form
│       └── product-list.php      # Product management
└── assets/
    ├── css/
    │   └── invoicing.css         # All invoicing styles (el-inv- prefix)
    └── js/
        └── invoicing.js          # Invoice editor, payment modal, export, charts
```

---

## BUILD ORDER

Build in this order with deployment checkpoints:

### Step 1 — Database + Module Skeleton (one version bump)
- Create `module.json` with all table declarations, capabilities, shortcodes, settings
- Create `class-invoicing-module.php` skeleton (singleton, init_hooks, AJAX handler registrations)
- Create empty shortcode files (return placeholder HTML)
- **Deploy checkpoint:** Module activates, tables created, admin menu appears

### Step 2 — Product Management
- Build `product-list.php` admin view with `EL_Admin_UI::*`
- AJAX handlers: create, update, delete, seed
- Seed default products
- **Deploy checkpoint:** Products page works, seed data created

### Step 3 — Invoice CRUD
- Build `invoice-list.php` admin view
- Build `invoice-edit.php` admin view (org autocomplete, line items, calculations)
- AJAX handlers: create, update, delete, duplicate, get
- Auto-increment invoice numbers
- **Deploy checkpoint:** Create invoice, add line items, save, view in list

### Step 4 — Payment Recording
- Payment modal in admin
- AJAX handlers: record payment, delete payment
- Auto-update invoice totals and status
- Overdue detection on page load
- **Deploy checkpoint:** Record payment, verify status changes, overdue flagging works

### Step 5 — Send & Client Portal
- Send invoice (mark sent, trigger wp_mail with invoice summary)
- Build `[el_client_invoices]` shortcode
- Build `[el_invoice_view]` shortcode with print styles
- **Deploy checkpoint:** Send invoice, client sees it in portal, print looks professional

### Step 6 — Revenue Dashboard + Export
- Build `[el_revenue_dashboard]` shortcode with charts and breakdowns
- CSV export handler
- **Deploy checkpoint:** Dashboard shows accurate data, CSV exports correctly

---

## SETTINGS (module.json)

```json
"settings": [
    {
        "key": "default_due_days",
        "label": "Default Payment Due (days from issue)",
        "type": "number",
        "default": 30
    },
    {
        "key": "default_tax_rate",
        "label": "Default Tax Rate (%)",
        "type": "number",
        "default": 0
    },
    {
        "key": "invoice_prefix",
        "label": "Invoice Number Prefix",
        "type": "string",
        "default": "ELS"
    },
    {
        "key": "company_address",
        "label": "Company Address (shown on invoices)",
        "type": "string",
        "default": ""
    },
    {
        "key": "company_phone",
        "label": "Company Phone (shown on invoices)",
        "type": "string",
        "default": ""
    },
    {
        "key": "company_email",
        "label": "Company Email (shown on invoices)",
        "type": "string",
        "default": ""
    },
    {
        "key": "default_invoice_notes",
        "label": "Default Invoice Footer Notes",
        "type": "string",
        "default": ""
    }
]
```

---

## CRITICAL REMINDERS FOR CURSOR

1. **Module loader handles shortcodes** — NEVER add `add_shortcode()` in the module class
2. **All admin views use `EL_Admin_UI::*`** — no raw HTML tables or forms
3. **Textarea fields use `sanitize_textarea_field( wp_unslash( $_POST['field'] ) )`** — not `sanitize_text_field()`
4. **CSS class names must match across PHP, CSS, and JS** — canonical names defined in CSS first
5. **Guest AJAX not needed** — all invoice operations require authentication
6. **Client portal AJAX uses `el_core_ajax_` hooks** (not `nopriv`) — clients have WP user accounts per Expand Site pattern
7. **`$wpdb->update()` returns 0 when data unchanged** — treat as success, not error
8. **Always bump version** for every deployment — no exceptions
9. **Invoice totals are denormalized** — `subtotal`, `total`, `amount_paid`, `balance_due` are stored on the invoice record AND recalculated on every line item or payment change. This avoids expensive joins for list views.
10. **Organization/contact autocomplete** — reuse the existing pattern from Expand Site's `es_search_organizations` handler in `class-organizations.php`
