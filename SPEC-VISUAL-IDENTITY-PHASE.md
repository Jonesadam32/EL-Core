# Spec: Visual Identity Phase (Phase 5)

> **Status:** Finalized — March 9, 2026
> **Replaces:** Phase 2G-B Mood Board system (built in v1.20.0–v1.21.0)
> **Planned version:** v1.34.0
> **Session type:** Planning only — no code written this session
> **Next action:** Build v1.34.0 from this spec

---

## WHAT CHANGED AND WHY — READ THIS FIRST

### What existed before (Phase 2G-B, v1.20.0–v1.21.0)

The previous Visual Identity system was a **Mood Board** — an admin-curated library of
style template cards that clients voted on in the portal. Admin would create a "review
session," select templates to show, and interpret the client's votes to infer a style
direction.

**This system is being completely replaced for four reasons:**

1. **It asked the wrong questions.** "Do you like this template?" does not give a designer
   what they need. It produces vague preference signals, not actionable design requirements.

2. **The primary consumer of this data is an AI builder.** Vibe coding / AI-generated site
   builds require structured, labeled, machine-readable input — not a vote tally summary.

3. **It was complex to administer for uncertain value.** Curating templates per client,
   managing review sessions, interpreting votes — significant overhead for little payoff.

4. **The output had no direct path to execution.** There was no document a designer or
   AI builder could act on immediately.

### What replaces it

A **structured Visual Identity Intake Form** in the client portal, where stakeholders
answer targeted questions that a UX designer actually needs. Combined with an
**admin-side Brand Brief generator** that assembles all gathered data into a structured
markdown document — ready to paste directly into an AI builder (Cursor, Claude, or any
vibe coding tool) as a complete design prompt.

The ELS team maintains its own internal style theme library as an operating asset.
That is not part of EL Core — it is an internal ELS reference. EL Core's job is to
gather the information, structure it, and output it in the most useful format for
whoever builds the site.

---

## PART 1 — REMOVAL: TEMPLATE LIBRARY AND MOOD BOARD SYSTEM

This must be completed BEFORE building the new system. Remove everything, verify the
plugin still loads without errors, then proceed to Part 2.

---

### 1A. File to Delete

```
el-core/modules/expand-site/admin/views/template-library.php
```

Delete this file completely. It is the entire Template Library admin page.

---

### 1B. Capability to Remove from module.json

Remove `es_manage_templates` from:
- The `"capabilities"` array
- The `"default_role_mapping"` for all roles (administrator, editor, subscriber)

---

### 1C. Database Tables to Deprecate

**DO NOT DROP these tables.** Any data already in them should be preserved.
Simply stop creating new data in them and remove all PHP references.

Tables to deprecate (leave in DB, remove all PHP code that touches them):
- `el_es_templates` — style template card library (added in migration "6")
- `el_es_review_items` — mood board review sessions (added in migration "6")
- `el_es_review_votes` — client votes on templates (added in migration "6")
- `el_es_annotations` — annotation data tied to review items (added in migration "6")
- `el_es_brand_options` — old brand/mood board fields (added in migration "2")

Leave all migration entries in `module.json` as-is so existing installs don't break.
Just remove all PHP code that reads from or writes to these tables.

---

### 1D. PHP Methods to Remove from class-expand-site-module.php

Search for and remove every method referencing the deprecated systems.

**Methods to remove — search by name:**
- Any method containing `template` — e.g. `get_templates()`, `save_template()`,
  `delete_template()`, `get_active_templates()`
- Any method containing `review_item` or `review_session` (mood board sessions only —
  NOT the definition review system, which is separate and must stay)
- Any method containing `review_vote` or `vote`
- Any method containing `annotation`
- Any method containing `brand_option` or `mood_board`

**AJAX handlers to remove — search `add_action` registrations:**
- `es_create_review_session`
- `es_get_review_session`
- `es_submit_vote`
- `es_close_review_session`
- `es_save_template`
- `es_delete_template`
- `es_get_templates`
- Any other handler whose name contains `template`, `vote`, `mood`, or `review_session`

**CRITICAL:** Do NOT remove anything related to `definition_review` or
`definition_comments`. The Discovery phase (Phase 2) consensus review system is
entirely separate and must remain completely intact.

---

### 1E. Admin Menu Item to Remove

In `init_hooks()` or `register_admin_pages()` in `class-expand-site-module.php`, find
and remove the `add_submenu_page()` call that registers the Template Library page.
It will reference the slug `el-core-template-library`. Remove it entirely.

---

### 1F. Portal Code to Remove from expand-site-portal.php

The Phase 5 portal section currently renders mood board voting UI. Remove it entirely.

Search for these strings to locate the blocks to remove:
- `el-es-mood-board`
- `el-es-branding-section`
- `mood-board-results`
- `el-es-mood-board-grid`
- `el-es-mood-board-card`
- `el-es-mood-board-vote-strip`
- `el-es-dm-mood-board-actions`
- `get_review_items`

Remove the entire Phase 5 portal section. Replace with a placeholder comment:
```php
// Phase 5 — Visual Identity portal — built in v1.34.0, see SPEC-VISUAL-IDENTITY-PHASE.md
```

---

### 1G. Admin Panel Code to Remove from project-detail.php

Find the Phase 5 panel content and the "Create Review Session" modal. Remove:
- The modal with `id="create-review-modal"` (Create Mood Board Review Session)
- The template picker UI inside it (`class="es-template-picker"`)
- The Phase 5 panel content showing mood board session status
- The call to `$module->get_templates()` near the top of the file

Replace Phase 5 panel content with a placeholder comment:
```php
// Phase 5 — Visual Identity admin panel — built in v1.34.0, see SPEC-VISUAL-IDENTITY-PHASE.md
```

---

### 1H. JavaScript to Remove

**In expand-site-admin.js**, remove all handlers related to:
- Template CRUD (save, delete, edit template cards)
- Review session creation
- Template picker UI interactions

**In expand-site.js**, remove all handlers related to:
- Mood board card voting
- Mood board results modal
- Review session portal interactions

Search for: `mood`, `template`, `review-session`, `vote`, `mood-board`

---

### 1I. CSS to Remove

**In expand-site.css**, remove all CSS blocks for:
- `.el-tpl-*` classes (template library card grid)
- `.el-es-mood-board-*` classes (mood board portal UI)
- `.es-template-picker*` classes (template picker in admin modal)
- `.el-tpl-upload-preview` and related upload preview classes

---

### 1J. Verify Removal is Clean

Build a ZIP and upload to staging. Verify all of the following before proceeding:

- [ ] Plugin activates without PHP errors or warnings
- [ ] Admin menu shows no "Template Library" item
- [ ] Expand Site project list loads without errors
- [ ] Expand Site project detail loads without errors
- [ ] Client portal loads for a Phase 5 project without errors (shows placeholder comment, which renders as blank — that is correct)
- [ ] Discovery phase (Phase 2) consensus review still works — must not be affected
- [ ] No JavaScript console errors on admin or portal pages

**Do not proceed to Part 2 until all checks pass.**

---

## PART 2 — BUILD: VISUAL IDENTITY INTAKE AND BRAND BRIEF SYSTEM

---

## Pipeline Position

Phase 5 remains Visual Identity. No pipeline numbering changes in this spec.

| # | Name | Slug | has_client_gate | Deadline Days |
|---|------|------|-----------------|---------------|
| 1 | Qualification | qualification | true | 3 |
| 2 | Discovery | discovery | true | 7 |
| 3 | Proposal | proposal | true | 5 |
| 4 | User Journey | user-journey | true | 7 |
| **5** | **Visual Identity** | **visual-identity** | **true** | **10** |
| 6 | Wireframes | wireframes | true | 10 |
| 7 | Final Design | final-design | true | 10 |
| 8 | Build | build | false | 14 |
| 9 | Delivery | delivery | true | 7 |

**Phase 6 gate:** Phase 6 (Wireframes) is hard-gated until admin locks the Visual
Identity brief. No advancing until `el_es_visual_brief.locked_at IS NOT NULL`.

---

## What This Phase Accomplishes

By the end of Phase 5 the ELS team has:

1. All brand assets uploaded, or a clear note that they need to be created
2. Answers to every visual and structural question a UX designer needs before starting
3. A generated Brand Brief markdown document — structured, labeled, ready to paste
   into an AI builder as a complete design prompt
4. Brief locked by admin, Phase 6 unlocked

---

## Database Schema

### Migration version: 12 → 13

Add one new table in migration "13" in `module.json`. Do not modify any existing tables.

---

### New Table: `el_es_visual_brief`

One row per project. Created automatically when Phase 5 is first activated.

```sql
CREATE TABLE {prefix}el_es_visual_brief (
    id                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id                BIGINT UNSIGNED NOT NULL UNIQUE,

    -- Logo situation
    has_logo                  TINYINT(1) NOT NULL DEFAULT 0,
    logo_url                  TEXT NULL,
    logo_needs_creation       TINYINT(1) NOT NULL DEFAULT 0,
    logo_notes                TEXT NULL,

    -- Existing brand colors
    has_brand_colors          TINYINT(1) NOT NULL DEFAULT 0,
    color_primary             VARCHAR(7) DEFAULT '',
    color_secondary           VARCHAR(7) DEFAULT '',
    color_accent              VARCHAR(7) DEFAULT '',
    color_neutral             VARCHAR(7) DEFAULT '',
    color_notes               TEXT NULL,

    -- Typography
    has_brand_fonts           TINYINT(1) NOT NULL DEFAULT 0,
    font_heading              VARCHAR(100) DEFAULT '',
    font_body                 VARCHAR(100) DEFAULT '',
    font_notes                TEXT NULL,

    -- Existing materials
    has_existing_materials    TINYINT(1) NOT NULL DEFAULT 0,
    existing_materials_url    TEXT NULL,
    existing_materials_notes  TEXT NULL,

    -- Visual personality
    audience_description      TEXT NULL,
    tone_feel                 TEXT NULL,
    sites_they_like           TEXT NULL,
    sites_to_avoid            TEXT NULL,

    -- Content and structure
    pages_needed              LONGTEXT NULL,
    has_photography           TINYINT(1) NOT NULL DEFAULT 0,
    photography_url           TEXT NULL,
    needs_stock_photography   TINYINT(1) NOT NULL DEFAULT 0,
    photography_notes         TEXT NULL,

    -- Constraints
    has_parent_org_brand      TINYINT(1) NOT NULL DEFAULT 0,
    parent_org_brand_notes    TEXT NULL,
    accessibility_required    TINYINT(1) NOT NULL DEFAULT 0,
    accessibility_standard    VARCHAR(50) DEFAULT '',
    multilingual              TINYINT(1) NOT NULL DEFAULT 0,
    languages                 VARCHAR(255) DEFAULT '',
    other_constraints         TEXT NULL,

    -- Additional notes
    additional_notes          TEXT NULL,

    -- Status tracking
    portal_submitted_at       DATETIME NULL,
    portal_submitted_by       BIGINT UNSIGNED NULL,
    generated_brief           LONGTEXT NULL,
    generated_at              DATETIME NULL,
    locked_at                 DATETIME NULL,
    locked_by                 BIGINT UNSIGNED NULL,

    created_at                DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_project (project_id)
)
```

**Column notes:**

- `has_logo` / `logo_needs_creation` — mutual states. If `has_logo = 1`, client uploads.
  If `logo_needs_creation = 1`, ELS will create it. Both can be 0 if undecided.
- `pages_needed` — JSON array of page name strings:
  `["Homepage","About Us","Programs","Events","Contact"]`
  These flow into Phase 6 (Wireframes) as the starting page list.
- `generated_brief` — the full markdown Brand Brief, stored after admin generates it.
  Regeneratable at any time before locking.
- `portal_submitted_at` — set when client submits the intake form. Admin notified.
- `locked_at` — set by admin when satisfied with the brief. Gates Phase 6 advance.

---

## Phase Initialization

When the admin advances a project from Phase 4 (User Journey) to Phase 5 (Visual
Identity), the system automatically:

1. Creates a row in `el_es_visual_brief` for the project with all defaults
2. Pre-populates `pages_needed` from the `implied_pages` field collected across all
   locked `el_es_user_journeys` for the project (parse JSON, merge arrays, deduplicate)

This logic lives in the stage-advance AJAX handler, gated on `$new_stage === 5`.

If no journey `implied_pages` exist, insert an empty JSON array `[]` and let the
client fill in the page list during the intake form.

---

## The Visual Identity Intake Form (Client Portal — Phase 5)

### Who sees what

- **Decision Maker:** Sees the full interactive form, can save and submit
- **Contributors:** See the form in read-only mode after DM submits; see "waiting" state
  before submission
- **Admin viewing portal:** Same as contributors — read-only

### Intro text

> "Before we begin designing your site, we need to gather some information about your
> organization's visual identity. Answer as many questions as you can — the more detail
> you provide, the better we can build something that truly represents your organization."

### Auto-save behavior

The form auto-saves on field blur (not on every keystroke). Fires `es_save_visual_brief`
AJAX. Shows a subtle "Saved" indicator for 2 seconds. No manual "Save Draft" button needed.

### Conditional show/hide

Radio button selections control which follow-up fields are visible. Use JavaScript
show/hide — no page reloads. Fields are always saved even if currently hidden
(server saves whatever is submitted).

---

### Section 1 — Logo

**Question:** Does your organization have a logo?

Radio options:
- "Yes, we have a logo"
- "No, we need one created"
- "We're not sure yet"

If **"Yes, we have a logo"** selected:
- File upload: "Upload your logo" → stores to WP media library, saves URL to `logo_url`
- Textarea: "Any notes about logo usage? (file formats available, color variations, restrictions, etc.)" → `logo_notes`

If **"No, we need one created"** selected:
- Textarea: "Describe what you'd like the logo to represent or feel like." → `logo_notes`

If **"We're not sure yet"** selected:
- No additional fields.

---

### Section 2 — Brand Colors

**Question:** Does your organization have established brand colors?

Radio options:
- "Yes, we have brand colors"
- "No, we don't have established colors"

If **"Yes"** selected:
- Text field: "Primary color (hex code or color name)" → `color_primary`
- Text field: "Secondary color" → `color_secondary`
- Text field: "Accent color (optional)" → `color_accent`
- Textarea: "Any notes about your colors? (Pantone/hex codes, colors to avoid, parent
  organization requirements, etc.)" → `color_notes`

If **"No"** selected:
- Textarea: "Are there colors you're drawn to, or colors that represent your
  organization's personality? Describe freely." → `color_notes`

---

### Section 3 — Typography

**Question:** Does your organization use specific fonts?

Radio options:
- "Yes, we use specific fonts"
- "No, we don't have brand fonts"

If **"Yes"** selected:
- Text field: "Heading font name" → `font_heading`
- Text field: "Body font name" → `font_body`
- Textarea: "Any notes? (where to find the fonts, license info, usage restrictions)" → `font_notes`

If **"No"** selected:
- No additional fields. ELS team will select appropriate fonts.

---

### Section 4 — Existing Materials

**Question:** Do you have any existing marketing materials we should reference for consistency?
(Letterhead, brochures, printed pieces, a previous website, etc.)

Radio options:
- "Yes, we have existing materials"
- "No, we're starting fresh"

If **"Yes"** selected:
- URL field: "Link to a shared folder, Google Drive, or website we can reference" → `existing_materials_url`
- Textarea: "What should we pay attention to in these materials?" → `existing_materials_notes`

---

### Section 5 — Visual Personality

No radio gates — all fields always visible.

**Question 1:** Who is the primary audience for this site?
- Textarea → `audience_description`
- Example hint (italic, always visible below field):
  *"For example: Middle school students and their parents, program staff, and district administrators"*

**Question 2:** How should the site feel?
- Textarea → `tone_feel`
- Example hint:
  *"For example: Energetic and approachable, not corporate or formal. Warm and community-focused."*

**Question 3:** Are there any websites you admire — not necessarily in education — that feel right for you?
- Textarea (URLs or descriptions) → `sites_they_like`
- Example hint:
  *"For example: We love the look of khanacademy.org — clean, bright, focused on learning."*

**Question 4:** Are there websites or styles you've seen that feel wrong for your organization?
- Textarea → `sites_to_avoid`
- Example hint:
  *"For example: Too corporate, clip-art heavy, or overly childish. We don't want to look like a generic government site."*

---

### Section 6 — Site Pages

**Question:** What pages does your site need?

- Tag-input field (or simple textarea, one page per line) → `pages_needed` (stored as JSON array)
- Pre-populated from Phase 4 implied pages on form load
- Client can add or remove pages freely
- Example hint:
  *"For example: Homepage, About Us, Programs, Events, Staff Directory, Contact, Login, Student Dashboard"*

---

### Section 7 — Photography

**Question:** Does your organization have photos we can use on the site?

Radio options:
- "Yes, we have our own photos"
- "No, we need stock photography"
- "Both — we have some photos and will need stock too"

If **"Yes"** or **"Both"** selected:
- URL field: "Link to a photo folder or gallery we can access" → `photography_url`
- Textarea: "Any notes about the photos? (subject matter, what's off-limits, etc.)" → `photography_notes`

If **"No"** or **"Both"** selected:
- Sets `needs_stock_photography = 1`
- Textarea: "Describe the type of imagery you'd like. (e.g., diverse youth, classroom
  settings, outdoor after-school activities)" → `photography_notes` (append or merge)

---

### Section 8 — Constraints

**Question 1:** Is there a parent organization (like a school district or county office) whose
logo or colors must appear on the site?

Radio: "Yes" / "No"

If **"Yes"** selected:
- Textarea: "Describe the requirements — what must be included, any brand standards
  we need to follow." → `parent_org_brand_notes`

---

**Question 2:** Does your organization have accessibility requirements?

Radio options:
- "Yes — WCAG 2.1 AA required" → sets `accessibility_standard = 'WCAG 2.1 AA'`
- "Yes — a different standard" → shows text field for specification → `accessibility_standard`
- "Not sure — please use best practices" → sets `accessibility_standard = 'best_practices'`
- "No specific requirement"

---

**Question 3:** Does your site need to support multiple languages?

Radio: "Yes" / "No"

If **"Yes"** selected:
- Text field: "Which languages?" → `languages`
- Example hint: *"For example: English and Spanish"*

---

**Question 4:** Any other constraints or requirements we should know about?
- Textarea (optional) → `other_constraints`

---

### Section 9 — Additional Notes

**Question:** Anything else that would help us design your site?
- Textarea (optional, freeform) → `additional_notes`
- Helper text: "Feel free to add anything that didn't fit the questions above."

---

### Submit Button

- Label: "Submit Visual Identity Information"
- Only visible and active for the Decision Maker
- Disabled until Section 1 (logo question) has been answered — minimum viable completion
- Confirmation dialog before submit:
  > "Once submitted, our team will begin building your Brand Brief. You can still
  > request changes by contacting your project manager."

**On submit:**
- Saves all fields to `el_es_visual_brief`
- Sets `portal_submitted_at = NOW()` and `portal_submitted_by = current user ID`
- Sends admin notification (WordPress admin email, same pattern as other portal submissions)
- Shows success state replacing the submit button:
  > "Thank you! Our team will review your responses and build your Brand Brief.
  > We'll be in touch if we have any questions."

**After submission:** All stakeholders see all answers in read-only format.
The DM sees answers read-only (no more editing via portal — changes go through admin).

---

## Admin Panel — Phase 5 in project-detail.php

The Phase 5 panel in `project-detail.php` has three distinct states.

---

### State A: Awaiting Client Input

Shown when `portal_submitted_at IS NULL`.

Content:
- Info notice: "Waiting for the Decision Maker to complete the Visual Identity intake
  form in the client portal."
- Detail row showing which stakeholder is the Decision Maker for reference

---

### State B: Client Submitted

Shown when `portal_submitted_at IS NOT NULL` and `locked_at IS NULL`.

**Top section — Read-only intake summary:**

Display all answered fields organized by section, using `EL_Admin_UI::detail_row()`
or card layout. Each section has a header.

Special rendering:
- Logo section: Show uploaded logo image inline if `logo_url` is set
  (small preview, max 200px wide)
- Color fields: Show a small filled square color swatch next to each hex value.
  Use an inline `<span>` with `background-color` style.
- Empty / unanswered fields: Show "Not provided" in muted text

**Bottom section — Brand Brief generation:**

After the intake summary, show:

```
[ Generate Brand Brief ]   ← primary button
```

On click:
- Button changes to loading state: spinner + "Generating..."
- Fires `es_generate_visual_brief` AJAX
- On success: button area is replaced by the brief display (below)
- On error: show error notice, restore button

**Brand Brief display** (shown after generation):

```
┌─────────────────────────────────────────────────┐
│  Brand Brief — Generated March 9, 2026          │
│  [Copy to Clipboard]  [Regenerate]              │
│                                                 │
│  # Brand Brief — Organization Name             │
│  **Project:** Project Name                     │
│  ...                                            │
│  (full markdown in styled pre/code block)       │
└─────────────────────────────────────────────────┘

[ Lock Brief ]   ← primary button, shown after generation
```

"Regenerate" reruns the generator and overwrites the displayed brief and
`generated_brief` column. Shows confirmation: "Regenerate will overwrite the current
brief. Continue?"

"Lock Brief" fires `es_lock_visual_brief`. Shows confirmation: "Locking the brief will
enable Phase 6 (Wireframes). You can unlock it later if needed."

---

### State C: Locked

Shown when `locked_at IS NOT NULL`.

- Green "Brief Locked" banner: "Brand Brief locked on [date] by [admin name]"
- Read-only intake summary (same as State B)
- Read-only brief display with "Copy to Clipboard" button
- Small secondary "Unlock Brief" button — clicking fires `es_unlock_visual_brief`,
  confirmation required, returns panel to State B
- Phase 6 advance button is now enabled in the phase bar

---

## Brand Brief Generation — PHP Logic

### Method signature

```php
private function generate_visual_brief( int $project_id ): string
```

### Data sources

Pull from three tables:
- `el_es_visual_brief` — all intake answers
- `el_es_projects` — project name, client name
- `el_es_project_definition` — site_description, primary_goal, target_customers, site_type

### Output

Structured markdown string. Every non-empty field gets a line.
If a field is empty/null, either omit it or write "Not provided — ELS to determine."

### Document structure

```markdown
# Brand Brief — {client_name}
**Project:** {project_name}
**Generated:** {current_date}

---

## Organization

- **Name:** {client_name}
- **Site Type:** {site_type}
- **Primary Goal:** {primary_goal}
- **Target Audience:** {target_customers}

---

## Brand Assets

### Logo
- Status: Existing logo provided  ← or "Needs to be created by ELS" or "To be determined"
- File: {logo_url}
- Notes: {logo_notes}

### Colors
- Primary: {color_primary}
- Secondary: {color_secondary}
- Accent: {color_accent}
- Neutral/Background: {color_neutral}
- Notes: {color_notes}
← If has_brand_colors = 0: "No established colors — ELS to propose palette."

### Typography
- Heading Font: {font_heading}
- Body Font: {font_body}
- Notes: {font_notes}
← If has_brand_fonts = 0: "No brand fonts — ELS to select appropriate pairing."

### Existing Materials
- Reference: {existing_materials_url}
- Notes: {existing_materials_notes}
← If has_existing_materials = 0: "No existing materials — starting fresh."

---

## Visual Direction

### Audience
{audience_description}

### Tone and Feel
{tone_feel}

### Reference Sites (Likes)
{sites_they_like}

### Sites / Styles to Avoid
{sites_to_avoid}

---

## Site Structure

### Pages Required
1. {page_1}
2. {page_2}
...
*Source: Compiled from Phase 4 User Journey implied pages + client additions.*

---

## Photography

- Own photos: Yes / No
- Photo library: {photography_url}
- Stock photography needed: Yes / No
- Notes: {photography_notes}

---

## Constraints

### Parent Organization Branding
{parent_org_brand_notes}
← If has_parent_org_brand = 0: "None — no parent organization brand requirements."

### Accessibility
{accessibility_standard}

### Language Support
{languages}
← If multilingual = 0: "English only."

### Other
{other_constraints}

---

## Additional Notes

{additional_notes}

---

*This brief was generated from client intake responses collected in the EL Core
Expand Site portal. Use it as the primary design prompt for AI-assisted site building.*
```

### Storing the result

After generating, save the markdown string to `el_es_visual_brief.generated_brief` and
set `generated_at = NOW()`. Return the string to the AJAX caller for display.

---

## AJAX Handlers

All registered in `class-expand-site-module.php` following the existing `el_core_action`
pattern. Include `nopriv` variants for all portal-facing handlers.

| Action | Who | Description |
|--------|-----|-------------|
| `es_save_visual_brief` | DM (portal) | Auto-save individual fields to `el_es_visual_brief` on blur |
| `es_submit_visual_brief` | DM (portal) | Final submit — sets `portal_submitted_at`, sends admin email notification |
| `es_get_visual_brief` | Stakeholders (portal) | Returns all brief fields for portal rendering |
| `es_generate_visual_brief` | Admin | Runs PHP generation, saves `generated_brief`, returns markdown |
| `es_lock_visual_brief` | Admin | Sets `locked_at`, `locked_by`; enables Phase 6 advance |
| `es_unlock_visual_brief` | Admin | Clears `locked_at` — escape hatch, returns to State B |

---

## Status State Machine

```
[Admin advances project to Phase 5]
    ↓ System auto-creates el_es_visual_brief row
    ↓ Pre-populates pages_needed from journey implied_pages
State A — Awaiting Client Input
    ↓ DM completes and submits portal intake form
State B — Client Submitted
    ↓ Admin clicks "Generate Brand Brief"
    ↓ (can regenerate multiple times)
State B — Brief Generated (still State B, just with brief visible)
    ↓ Admin clicks "Lock Brief"
State C — Locked ✓ → Phase 6 advance enabled
```

**Escape hatch:** Admin can unlock at any time, returning to State B.
Brief is retained but can be regenerated.

---

## Phase 6 Gate

In the phase advance logic, check before allowing advance to Phase 6:

```php
if ( $new_stage === 6 ) {
    $brief = $wpdb->get_row( $wpdb->prepare(
        "SELECT locked_at FROM {$wpdb->prefix}el_es_visual_brief WHERE project_id = %d",
        $project_id
    ) );
    if ( ! $brief || ! $brief->locked_at ) {
        return EL_AJAX_Handler::error( 'Lock the Brand Brief before advancing to Wireframes.' );
    }
}
```

The "Advance Stage" button in the admin phase bar should also show a disabled state
with tooltip "Lock the Brand Brief first" when the project is in Phase 5 and
`locked_at IS NULL`.

---

## Pages_Needed → Wireframes Handoff

The `pages_needed` JSON array from `el_es_visual_brief` is the starting page inventory
for Phase 6 (Wireframes). When Phase 6 initializes, read this array and pre-populate
the wireframe page list. This connection should be noted in the Phase 6 spec.
For this build, ensure the data is stored correctly — the Phase 6 consumption of it
will be built when Phase 6 is specced.

---

## CSS Classes

All new classes use `el-es-` prefix, added to `expand-site.css`.

| Class | Purpose |
|-------|---------|
| `.el-es-vi-section` | Intake form section wrapper |
| `.el-es-vi-section-title` | Section heading (e.g., "Logo", "Colors") |
| `.el-es-vi-field-group` | Question + answer group |
| `.el-es-vi-question` | Question label text (bold) |
| `.el-es-vi-answer` | Answer display in read-only mode |
| `.el-es-vi-hint` | Example hint text (muted, italic, always visible) |
| `.el-es-vi-conditional` | Fields shown/hidden based on radio selection |
| `.el-es-brief-output` | Generated brief display (monospace font, scrollable, max-height) |
| `.el-es-brief-actions` | Copy/Regenerate button row above brief |
| `.el-es-color-swatch` | Small filled square showing a color value inline |
| `.el-es-logo-preview` | Inline logo image display in admin panel |
| `.el-es-vi-submitted-badge` | "Submitted by [Name] on [Date]" badge |

---

## JavaScript Notes

**Portal JS (expand-site.js):**

- Auto-save on field blur — debounced, fires `es_save_visual_brief` with field name + value
- Radio button change handlers — show/hide conditional field groups
- File upload handling for logo — use WP media uploader or simple file input
- Submit button confirmation dialog
- Loading state on submit ("Saving...")
- After submission: render all answers in read-only mode, hide form controls

**Admin JS (expand-site-admin.js):**

- "Generate Brand Brief" — AJAX call, loading state, render brief on success
- "Copy to Clipboard" — `navigator.clipboard.writeText()` on the `generated_brief` content
- "Regenerate" — confirmation dialog, same AJAX call, replace displayed brief
- "Lock Brief" — confirmation dialog, AJAX, reload panel to State C
- "Unlock Brief" — confirmation dialog, AJAX, reload panel to State B

---

## Build Order for v1.34.0

Complete steps in order. Do not skip ahead.

### Step 1 — Part 1: Complete all removal (1A through 1J)
Build ZIP after removal. Verify all 1J checks pass before continuing.

### Step 2 — DB migration version 13
Add `el_es_visual_brief` table to `module.json` migrations. Deploy, verify table exists.

### Step 3 — Phase initialization logic
In stage-advance handler, gate on `$new_stage === 5`: create `el_es_visual_brief` row,
pre-populate `pages_needed` from locked journey implied pages.

### Step 4 — Portal intake form (State: Awaiting)
Build the full 9-section intake form in `expand-site-portal.php` Phase 5 section.
All conditional show/hide logic. Auto-save on blur. Submit flow with confirmation.

### Step 5 — Portal AJAX handlers
`es_save_visual_brief`, `es_submit_visual_brief`, `es_get_visual_brief`.
Test: DM can complete and submit form. Data saves correctly to all columns.

### Step 6 — Portal read-only state (State: Submitted)
After submission, all stakeholders see all answers read-only. Verify correct rendering.

### Step 7 — Admin panel State A and State B (intake display)
Read-only display of all intake answers in admin. Logo preview. Color swatches.
State A: "Awaiting client input" notice. State B: full summary.

### Step 8 — Brand Brief generator
PHP `generate_visual_brief()` method — all sections, all conditional logic.
AJAX handler `es_generate_visual_brief`. Brief display in admin with copy button.
Test: generate brief for a test project, verify all populated fields appear correctly.

### Step 9 — Lock / Unlock and Phase 6 gate
`es_lock_visual_brief`, `es_unlock_visual_brief`.
Phase 6 gate check in stage-advance handler.
Phase bar advance button disabled state with tooltip.

### Step 10 — CSS and JS polish
All new CSS classes. Conditional portal show/hide. Color swatches. Logo preview.
Copy to clipboard. Admin loading states. Mobile-responsive intake form.

### Step 11 — End-to-end test
Run through complete Phase 5 flow on staging:
- [ ] Project advances from Phase 4 to Phase 5 → brief row created
- [ ] DM completes all sections, all auto-saves fire correctly
- [ ] DM submits → admin receives notification
- [ ] Admin sees full intake summary with logo preview and color swatches
- [ ] Admin generates brand brief → markdown appears correctly formatted
- [ ] Copy to clipboard works
- [ ] Admin locks brief → Phase 6 advance button enables
- [ ] Unlock → returns to State B
- [ ] Attempting to advance to Phase 6 without locking → blocked with message

---

## Open Questions (Deferred — do not block build)

- Should admin be able to edit individual intake fields directly in the admin panel,
  without going through the portal? For now: admin edits by unlocking and asking DM
  to resubmit, or directly via database if urgent. A future pass can add admin-side editing.
- Should the generated brief be emailable directly from the admin panel? Deferred.
  Copy to clipboard is sufficient for now.
- Should there be a version history for regenerated briefs? Deferred — overwrite is
  sufficient for now.
- Should the logo upload use the WP media uploader (media library picker) or a plain
  file input? Use the WP media uploader for consistency with the Template Library pattern
  that was removed — the JS pattern already exists in the codebase, just repurpose it.
