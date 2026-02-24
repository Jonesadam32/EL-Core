# Cursor Prompt — Payment Terms & T&C Settings (Phase 2F-D)

> **Task:** Add default Payment Terms and Terms & Conditions as editable settings in the Expand Site module settings page. Every new proposal should inherit these defaults automatically. Fred can edit them per-proposal if needed.
>
> **Target version:** v1.17.0
> **Prerequisite:** v1.16.0 must be uploaded and tested before starting this.

---

## CONTEXT

The proposal system (built in v1.15.0–v1.16.0) already has `payment_terms` and `terms_conditions` columns in the `el_es_proposals` table. Right now those fields are blank when a new proposal is created. The goal is to:

1. Store default boilerplate text in module settings (one place to manage)
2. Auto-populate every new proposal with those defaults on creation
3. Add two new textarea fields to the Expand Site settings page so Fred can update the boilerplate anytime

---

## WHAT TO BUILD

### Step 1 — Add settings to `module.json`

Add two new entries to the `"settings"` array in `modules/expand-site/module.json`:

```json
{
    "key": "default_payment_terms",
    "label": "Default Payment Terms",
    "type": "string",
    "default": ""
},
{
    "key": "default_terms_conditions",
    "label": "Default Terms & Conditions",
    "type": "string",
    "default": ""
}
```

### Step 2 — Seed default values on module activation

In `modules/expand-site/class-expand-site-module.php`, find or create an `activate()` method (or wherever the module handles first-run initialization). On activation, if `default_payment_terms` is empty, write the default text below into the setting. Same for `default_terms_conditions`.

Use:
```php
$this->core->settings->get( 'mod_expand-site', 'default_payment_terms', '' )
```
to check if already set, and:
```php
$this->core->settings->update( 'mod_expand-site', 'default_payment_terms', $default_text )
```
to write it if empty. This ensures existing installs aren't overwritten if Fred has already customized them.

**Default Payment Terms text to seed:**

```
Payment Schedule

This project will be invoiced in two payments:

First Payment (25%) is due upon client approval of the wireframes. Approval is recorded when the authorized Decision Maker formally accepts the wireframe deliverable through the project portal. An invoice will be issued automatically at that time.

Final Payment (75%) is due upon delivery and client review of the completed website. An invoice will be issued when the project reaches final delivery.

Accepted Payment Methods

Payment may be made by check or ACH bank transfer. Invoices are due within 30 days of issuance unless a separate payment schedule has been established with your organization's procurement department.

Late Payments

Invoices not paid within 30 days of the due date are subject to a 1.5% monthly finance charge. Expanded Learning Solutions reserves the right to pause work on any project with an outstanding balance of 30 days or more.

Project Inactivity

If a project is delayed due to lack of client response or action for 90 or more consecutive days, Expanded Learning Solutions reserves the right to formally close the project. In this case, an invoice will be issued for all work completed to date, calculated as a proportional share of the total project investment. The project may be reopened by mutual agreement, which may require a new proposal depending on the scope of time elapsed.
```

**Default Terms & Conditions text to seed:**

```
1. Scope of Work
This proposal defines the agreed-upon scope of work. Requests that fall outside this scope will be discussed and quoted separately before any additional work begins.

2. Client Responsibilities
The client agrees to provide timely feedback, required content (text, images, logos, documents), and decisions necessary to keep the project on schedule. Delays caused by the client may result in revised project timelines.

3. Intellectual Property
Upon receipt of final payment, the client receives full ownership of all custom deliverables created specifically for this project, including website pages, written content, and custom graphics. Expanded Learning Solutions retains ownership of any proprietary tools, frameworks, code libraries, or platform infrastructure used to build the project. Third-party tools, plugins, or licensed assets remain subject to their respective license terms.

4. Confidentiality
Both parties agree to keep confidential any proprietary information, data, or materials shared during the course of this project. This obligation survives the completion or termination of the agreement.

5. Platform & Hosting
Unless otherwise specified in the scope, ongoing hosting, maintenance, and platform licensing are not included in this proposal. A separate service agreement will be provided for any ongoing services.

6. Limitation of Liability
Expanded Learning Solutions' total liability under this agreement shall not exceed the total amount paid by the client for the project. ELS is not liable for indirect, incidental, or consequential damages of any kind.

7. Termination
Either party may terminate this agreement with 14 days written notice. Upon termination, the client is responsible for payment of all work completed to the date of termination, invoiced as a proportional share of the total project investment.

8. Governing Law
This agreement is governed by the laws of the State of Georgia. Any disputes shall be resolved through good-faith negotiation, and if necessary, binding arbitration.

9. Entire Agreement
This proposal, once accepted, constitutes the entire agreement between the parties and supersedes all prior discussions or representations.
```

### Step 3 — Add fields to the settings admin view

The Expand Site module settings page is rendered somewhere in the admin views. Find where the existing settings (default_budget_low, default_budget_high, deadline_warning_days, enable_client_portal) are rendered and add two new textarea fields below them using `EL_Admin_UI::form_row()`.

Each textarea should:
- Use `EL_Admin_UI::form_row()` with `type => 'textarea'`
- Have `rows => 12` so the full text is readable
- Have a help text note explaining it populates every new proposal automatically
- Save via the existing settings save mechanism (same as other module settings)

Example structure:
```php
EL_Admin_UI::form_row( [
    'id'        => 'default_payment_terms',
    'label'     => 'Default Payment Terms',
    'type'      => 'textarea',
    'value'     => $this->core->settings->get( 'mod_expand-site', 'default_payment_terms', '' ),
    'rows'      => 12,
    'help'      => 'This text is automatically applied to every new proposal. Edit per-proposal if needed.',
] );

EL_Admin_UI::form_row( [
    'id'        => 'default_terms_conditions',
    'label'     => 'Default Terms & Conditions',
    'type'      => 'textarea',
    'value'     => $this->core->settings->get( 'mod_expand-site', 'default_terms_conditions', '' ),
    'rows'      => 20,
    'help'      => 'This text is automatically applied to every new proposal. Edit per-proposal if needed.',
] );
```

### Step 4 — Auto-populate new proposals on creation

In `class-expand-site-module.php`, find `handle_create_proposal()` (the `es_create_proposal` AJAX handler). When inserting the new proposal record, pull the default values from settings and include them in the insert:

```php
$payment_terms    = $this->core->settings->get( 'mod_expand-site', 'default_payment_terms', '' );
$terms_conditions = $this->core->settings->get( 'mod_expand-site', 'default_terms_conditions', '' );
```

Then include `payment_terms` and `terms_conditions` in the `$this->core->database->insert()` call for `el_es_proposals`.

### Step 5 — Future invoice trigger (note only — do not build yet)

When the Decision Maker accepts the wireframe stage in the client portal, the system should eventually flag that Invoice 1 is due. The hooks for this already exist (proposal acceptance triggers stage advancement). A future phase will add:
- An "Invoice Due" flag or notification when wireframe stage is approved
- Automatic invoice generation tied to stage advancement

**Do not build this in v1.17.0.** Just add a `// TODO: Invoice trigger — Phase 2F-E` comment in `handle_accept_proposal()` near the stage advancement logic so it's easy to find later.

---

## VERSION & DEPLOYMENT

- Bump plugin version to **v1.17.0**
- Update `EL_CORE_VERSION` constant in `el-core.php`
- Update version in `build-zip.ps1`
- Add CHANGELOG entry:
  ```
  v1.17.0 — Payment Terms & T&C Settings
  - Added default_payment_terms and default_terms_conditions to Expand Site module settings
  - Default text auto-seeded on activation (does not overwrite existing customizations)
  - New proposals automatically inherit default payment terms and T&C on creation
  - Two new textarea fields added to Expand Site settings admin page
  - TODO comment added in handle_accept_proposal() for future invoice trigger (Phase 2F-E)
  ```
- Run `build-zip.ps1` from repo root
- Upload `el-core.zip` via WordPress Admin → Plugins → Add New → Upload Plugin

---

## TESTING CHECKLIST

- [ ] Go to EL Core → Expand Site Settings — both new textarea fields appear with the default text pre-filled
- [ ] Edit the payment terms text, save, reload — changes persist
- [ ] Create a new proposal on any project — payment_terms and terms_conditions fields are pre-populated with the default text
- [ ] Verify the client portal proposal view still renders correctly with the populated text
- [ ] Verify existing proposals are not affected (only new proposals get defaults on creation)
