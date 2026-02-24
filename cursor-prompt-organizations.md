# Cursor Prompt — Organizations & Client Management (Phase 2F-E)

> **Task:** Add a proper Organizations/Clients data layer as shared core infrastructure. Currently projects store `client_name` as a plain text string — there's no central client record, no contacts table, no way to see all projects for one organization. This adds the missing layer.
>
> **Target version:** v1.18.0
> **Prerequisite:** v1.17.0 must be uploaded and tested before starting this.

---

## CONTEXT

The monolith (`el-solutions.php`) has a two-tier client system:
- `els_organizations` — the client entity (name, type, status, address, phone)
- `els_contacts` — people within that org (first/last name, email, phone, primary flag, portal access flags, linked WP user)
- `els_projects` — linked to org via `organization_id`

EL Core currently has none of this. Projects just store `client_name` as text and `client_user_id` as a single WP user. The stakeholders table handles per-project permissions, but there's no shared client record.

**Why this is core infrastructure (not a module):** Organizations are referenced by Expand Site projects, will be referenced by Expand Partners, by invoicing, and by the `[el_client_dashboard]` shortcode. Tables use `el_` prefix.

---

## WHAT TO BUILD

### Step 1 — Database tables (core level)

Add two new tables to the core database schema in `includes/class-database.php`. These are NOT module tables — they're core infrastructure like `el_settings`.

**`el_organizations`:**
```sql
CREATE TABLE el_organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(20) DEFAULT 'nonprofit',
    status VARCHAR(20) DEFAULT 'prospect',
    address TEXT,
    phone VARCHAR(50) DEFAULT '',
    website VARCHAR(500) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

Type values: `nonprofit`, `for_profit`, `government`, `education`
Status values: `prospect`, `active`, `inactive`

**`el_contacts`:**
```sql
CREATE TABLE el_contacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT '',
    title VARCHAR(100) DEFAULT '',
    is_primary TINYINT(1) DEFAULT 0,
    user_id BIGINT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)
```

The `title` field stores the person's role at their organization (e.g. "Executive Director", "IT Manager", "Board Chair") — NOT their EL Core role.

The `user_id` field links to a WordPress user account. NULL/0 means no portal access. When a contact needs portal access, a WP user is created automatically.

### Step 2 — Add `organization_id` to projects

Add a migration to `el_es_projects`:

```sql
ALTER TABLE el_es_projects ADD COLUMN organization_id BIGINT UNSIGNED DEFAULT 0 AFTER client_user_id
```

Bump the Expand Site module `module.json` database version to 5 and add the migration.

**Data migration:** For each existing project where `organization_id` is 0 and `client_name` is not empty:
1. Check if an organization with that name already exists
2. If not, create one (type=nonprofit, status=active)
3. Set the project's `organization_id` to the org ID

This should run as a PHP migration in the module class (not a raw SQL migration) since it needs conditional logic. Add a method `migrate_projects_to_organizations()` that runs once on version 5 upgrade.

### Step 3 — Core AJAX handlers for organizations

Add these handlers to `class-ajax-handler.php` or a new `class-organizations.php` in `includes/`:

**`el_create_organization`** — Admin only. Fields: name, type, status, address, phone, website. Returns org ID.

**`el_update_organization`** — Admin only. Fields: org_id + same fields. Returns success.

**`el_delete_organization`** — Admin only. Cascades: deletes all contacts for the org. Does NOT delete linked projects (sets `organization_id` to 0 instead). Returns success.

**`el_get_organization`** — Admin only. Returns org data for edit modal population.

**`el_search_organizations`** — Admin only. Accepts search term, returns matching orgs (for autocomplete in project creation). Limit 10 results.

**`el_add_contact`** — Admin only. Fields: organization_id, first_name, last_name, email, phone, title, is_primary. If portal access is needed (determined by caller), also creates a WP user:
- Check if WP user with that email already exists
- If not, create one with `wp_create_user()` using email as username, random password
- Set first_name, last_name, display_name on the WP user
- Store the WP user ID in the contact's `user_id` field
- Add appropriate capabilities to the WP user

**`el_update_contact`** — Admin only. Same fields + contact_id. Updates WP user info if `user_id` is set.

**`el_delete_contact`** — Admin only. Does NOT delete the linked WP user (just sets `user_id` to 0 on the contact before deleting). Returns success.

**`el_get_contact`** — Admin only. Returns contact data for edit modal population.

### Step 4 — Admin UI: Clients page

Register a "Clients" submenu page under the EL Core admin menu (between Dashboard and Modules).

**Client list view:**
- Card grid layout (like the monolith's client cards)
- Each card shows: org name, type badge, status badge, contact count, project count
- Cards are clickable — link to client profile page
- "Add Client" button at top opens modal
- Use `EL_Admin_UI` components where possible

**Add Client modal:**
- Fields: Name (required), Type (select), Status (select), Address (textarea), Phone, Website
- On submit: AJAX `el_create_organization`, then reload page

**Client profile page** (when `client_id` is in URL query):
- Back link to client list
- Header: org name, type badge, status badge, Edit and Delete buttons

- **Client Details card:** address, phone, website, "client since" date

- **Contacts card:** 
  - List of contacts with name (+ primary badge), email, phone, title
  - "Add Contact" button opens modal
  - Edit/Delete buttons per contact
  - When adding a contact: first name, last name, email, phone, title, is_primary checkbox

- **Projects card:**
  - List of all projects linked to this organization (across all modules)
  - Each row: project name (linked to project detail), status badge, stage, date
  - If no projects: empty state message

### Step 5 — Update project creation flow

In `handle_create_project()` in `class-expand-site-module.php`:

1. Accept `organization_id` parameter (in addition to existing `client_name`)
2. If `organization_id` is provided, look up the org and set `client_name` from `org->name`
3. If `organization_id` is 0 but `client_name` is provided, create a new organization automatically (name = client_name, type = nonprofit, status = active), then set `organization_id`

In the admin project creation form (`admin/views/project-list.php` or wherever the Create Project modal lives):
- Replace the plain text `client_name` input with an organization search/select
- Autocomplete: as Fred types, search `el_organizations` and show matches
- "Create New Client" option if no match found — opens inline or creates on the fly
- When an org is selected, auto-fill the client name field

In the project list view:
- Show organization name (linked to client profile) instead of plain text `client_name`

### Step 6 — Auto-add primary contact as stakeholder

When a project is created with an `organization_id`:
1. Look up the primary contact for that organization
2. If the primary contact has a `user_id` (WP account), auto-add them as a Decision Maker stakeholder
3. This replaces the manual step of adding stakeholders after project creation

---

## VERSION & DEPLOYMENT

- Bump plugin version to **v1.18.0**
- Update `EL_CORE_VERSION` constant in `el-core.php`
- Update version in `build-zip.ps1`
- Add CHANGELOG entry:
  ```
  v1.18.0 — Organizations & Client Management
  - New core tables: el_organizations and el_contacts
  - Clients admin page with card grid, client profile, contacts management
  - Projects now linked to organizations via organization_id column
  - Auto-migration: existing projects get organizations created from client_name
  - WP user auto-creation for contacts needing portal access
  - Project creation: org search/select replaces plain text client name input
  - Primary contact auto-added as Decision Maker stakeholder on project creation
  ```
- Run `build-zip.ps1` from repo root

---

## TESTING CHECKLIST

- [ ] Go to EL Core → Clients — page loads with empty state
- [ ] Create a new organization (nonprofit, prospect) — appears in card grid
- [ ] Click into the org — client profile page loads with details card
- [ ] Add a contact (first name, last name, email, phone, title, primary) — appears in contacts list
- [ ] Add a second contact marked as primary — verify primary badge moves
- [ ] Edit the organization — changes persist
- [ ] Edit a contact — changes persist
- [ ] Go to Expand Site → Create Project — org search/select appears instead of text field
- [ ] Type an org name — autocomplete shows matching orgs
- [ ] Select an org and create project — project linked to org, client_name auto-filled
- [ ] Check project list — org name shows (linked to client profile)
- [ ] Go back to client profile — new project appears in Projects card
- [ ] Verify existing projects still work (org created from client_name during migration)
- [ ] Delete a contact — WP user not deleted, just unlinked
- [ ] Delete an organization — contacts cascade-deleted, projects get organization_id set to 0

---

## REFERENCE: Monolith Code Locations

For implementation reference, these are the key sections in `el-solutions.php`:

| What | Line Range |
|------|-----------|
| `els_organizations` table schema | ~164-181 |
| `els_contacts` table schema | ~184-205 |
| Client list page (card grid) | ~1196-1283 |
| Add Organization modal | ~1286-1340 |
| Client profile page | ~1500-1710 |
| Add Contact modal (with portal access) | ~1724-1789 |
| Edit org modal (AJAX-populated) | ~1852-1892 |
| `handle_add_organization()` | ~1974-1995 |
| `handle_edit_organization()` | ~2000-2022 |
| `handle_delete_organization()` | ~2027-2045 |
| `handle_add_contact()` (with WP user creation) | ~2050-2109 |
| `handle_edit_contact()` | ~2114-2160 |
| `create_or_update_portal_user()` | ~2222-2282 |
