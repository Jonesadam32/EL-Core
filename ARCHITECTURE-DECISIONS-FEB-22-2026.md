# EL Core — Architecture Decisions Handoff
## Session Date: February 22, 2026
## Purpose: Captures all architectural decisions made this session. Cursor must read this before continuing any work.

---

## CRITICAL: WHAT CHANGED THIS SESSION

This session was a top-level architecture review. Several fundamental decisions were made that affect how modules are built going forward. Do not proceed with any build work until you have read and understood all of these.

---

## DECISION 1 — EXPAND SITE IS PROPRIETARY, NOT A SELLABLE PRODUCT

### What changed:
Expand Site will never be marketed as a standalone sellable module. It is a proprietary internal tool that gives Expanded Learning Solutions a competitive advantage. Building it as a configurable product for other agencies is wasted effort.

### What this means for how you build it:
- **Stage names become hardcoded constants** — remove the `stage_1_name` through `stage_8_name` settings from `module.json`
- **AI features are always on** — remove the `enable_ai_content_generation` and `enable_branding_ai` toggle settings
- **Multi-stakeholder is always on** — remove the `enable_multi_stakeholder` toggle setting
- **`agency_name` setting removed** — not needed for internal use
- **`default_stage_deadline_days` and `deadline_warning_days` can stay** — these are operationally useful even internally
- Stop engineering for hypothetical other agencies. Build exactly what Fred needs, as directly as possible.
- The module still lives in the EL Core module system and follows all EL Core conventions. It just doesn't need to be resale-ready.

### What does NOT change:
- The module structure, file conventions, EL_Admin_UI usage, and coding standards stay identical
- All features currently being built (stakeholders, timers, transcript processing, branding workflow, content generation) continue as planned
- CURSOR-TODO.md Phase 2 continues — just strip the configurability overhead from module settings

---

## DECISION 2 — PROJECT MANAGEMENT MODULE: TASK AGGREGATOR ONLY

### What it is:
A new standalone module (`project-management`) that provides a unified Kanban and task view across ALL program modules. It does NOT duplicate project data, pipeline data, stage data, or deliverable data from Expand Site or any other program module.

### What it does:
- Owns a shared `el_tasks` table
- Displays all tasks from all program modules in one Kanban board
- Allows manual task creation linked to any project
- Tracks task status, assignee, due date, priority

### How other modules feed into it:
Every program module (Expand Site, Expand Partners, future programs) writes tasks into the shared `el_tasks` table. Tasks include a `source_module` field and a `source_id` field so you always know which project and context a task came from. The PM module reads from one table only and stays simple forever.

### Shared `el_tasks` table structure (PM module owns this):
```
el_tasks
- id
- title
- description
- source_module (e.g. 'expand-site', 'expand-partners')
- source_id (the project ID in the source module)
- assigned_to (WP user ID)
- status (todo / in-progress / blocked / done)
- priority (low / normal / high / urgent)
- due_date
- created_by
- created_at
- completed_at
```

### What the PM module Kanban shows:
Tasks only. Not projects, not stages, not deliverables. Just the work that needs to be done, organized by status, filterable by program/assignee/project.

### Important:
- Expand Site generates tasks automatically when stages advance (e.g. "Stage 3 started — create branding options") and allows manual task creation
- The PM module does not know or care about stages, deliverables, or client approvals — that stays inside each program module
- Do NOT build a separate project list or project Kanban in the PM module — Expand Site has its own project management UI

---

## DECISION 3 — SHARED PROJECTS TABLE ARCHITECTURE

### The problem this solves:
Expand Site has `el_es_projects`. When Expand Partners is built, if it gets its own `el_ep_projects` table, anything that needs to see all projects across all programs has to query multiple schemas. Every new program adds another table to stitch together.

### The solution:
A lightweight shared `el_projects` table owned by the PM module containing only universal fields. Each program module has its own supplementary tables for program-specific data that link back to `el_projects`.

```
el_projects (PM module owns this)
- id
- name  
- program_type ('expand-site', 'expand-partners', etc.)
- status
- assigned_to
- created_at

el_es_stages (Expand Site owns — links to el_projects.id)
el_es_deliverables (Expand Site owns — links to el_projects.id)
el_es_stakeholders (Expand Site owns — links to el_projects.id)
etc.

el_ep_onboarding (Expand Partners will own — links to el_projects.id)
el_ep_revenue (Expand Partners will own — links to el_projects.id)
etc.
```

### Migration note for Expand Site:
The current `el_es_projects` table will need to be restructured. Universal fields move to `el_projects`. Expand Site-specific fields stay in a supplementary table and link back via `project_id`. This is a database migration — plan it carefully before executing.

---

## DECISION 4 — CRM: USE FLUENT CRM, NOT A CUSTOM CRM MODULE

### What changed:
The planned `crm` module from the EL-SOLUTIONS-TO-EL-CORE-MIGRATION-PLAN.md is cancelled. We are not building `el_organizations` or `el_contacts` tables.

### Why:
Fluent CRM is already installed, already has contacts, companies, tagging, segmentation, and email automation. Building a parallel contact system creates duplicate data and a permanent sync problem.

### What replaces it:
A **Client Profile module** that builds custom admin views on top of Fluent CRM data. It reads from Fluent CRM's tables using their API/hooks and combines that data with your own module tables (projects, proposals, invoices) to show a unified client profile.

### Fluent CRM remains the source of truth for:
- Contact records
- Company/organization records
- Email communication history
- Tags and segmentation

### Your modules own:
- Projects (linked to Fluent CRM contact/company IDs)
- Proposals (linked to Fluent CRM contact/company IDs)
- Invoices (linked to Fluent CRM contact/company IDs)
- Tasks (linked to projects)

---

## DECISION 5 — PUBLIC WEBSITE MODULE IS CANCELLED, REPLACED BY THEMES

### What changed:
The planned `public-website` module from the migration plan is cancelled. Public-facing pages, headers, footers, product pages, and site content belong in a theme, not a plugin.

### The theme product line (monorepo — lives alongside el-core in same repository):

**EL Theme** (Expanded Learning Solutions brand theme)
- Public-facing pages for expandedlearningsolutions.com
- Reads brand settings from EL Core via helper functions
- Potentially sellable to other professional services agencies

**EL Learning Theme** (Learning Management client theme)
- Purpose-built for LMS client installations
- Companion theme sold alongside the LMS module

### Repository structure:
```
EL Core/          (repo root)
├── el-core/      (the plugin)
├── el-theme/     (ELS business site theme)
└── el-learning-theme/  (LMS client theme)
```

---

## DECISION 6 — CLIENT PORTALS ARE NOT A SEPARATE MODULE

### What changed:
The planned `client-portals` module from the migration plan is cancelled.

### Why:
Expand Site clients and Expand Partners partners are completely different user types with completely different portal needs. Each program module owns its own portal shortcodes. There is no generic portal abstraction needed.

---

## DECISION 7 — EXPAND PARTNERS MODULE: OVERVIEW AND SCOPE

### Lifecycle (pipeline not yet fully designed — next session):
1. Business plan / scoping
2. Onboarding
3. Contract
4. System training
5. Collaborative site build
6. Active partner (ongoing — no end state)

### Key difference from Expand Site:
Ongoing relationship with no closure. After onboarding, partners enter a permanent "Active" state.

### Revenue model (Model B):
- Partners invoice their own clients and receive payment directly
- Partners pay ELS a fixed platform fee based on platform-tracked earnings
- Software tracks partner earnings, calculates ELS fee, generates receivable record
- Shared transparent financial dashboard for both parties
- ELS never handles client money

### This revenue logic stays inside Expand Partners — it is NOT a standalone module.

---

## DECISION 8 — COACHING, LMS, AND FUTURE PRODUCTS ARE SAAS SUBSCRIPTIONS

### The model:
Flat subscription fee. ELS is not in the client's revenue. A **Licensing and Subscriptions module** will handle this across all SaaS products.

---

## UPDATED MODULE ROADMAP (Business Operations Product Line)

| Module | Status | Notes |
|--------|--------|-------|
| `expand-site` | In progress | Strip configurability overhead per Decision 1 |
| `project-management` | Not yet built | Task aggregator only — shared el_tasks table |
| `expand-partners` | Not yet designed | Next session |
| `proposals` | Not yet built | Extracted from ELS monolith |
| `invoicing` | Not yet built | Extracted from ELS monolith |
| `licensing` | Not yet built | SaaS subscription management |
| ~~`crm`~~ | CANCELLED | Replaced by Fluent CRM + Client Profile views |
| ~~`client-portals`~~ | CANCELLED | Each program module owns its own portals |
| ~~`public-website`~~ | CANCELLED | Replaced by EL Theme |

---

## WHAT CURSOR SHOULD DO NEXT SESSION

1. Read this document first
2. Read START-HERE-NEXT-SESSION.md
3. Read CURSOR-TODO.md
4. Continue Phase 2 Expand Site work (next is 2E — Timer and Escalation System)

### Immediate change needed in Expand Site module:
Remove these settings from `module.json` and all references throughout the module:
- `stage_1_name` through `stage_8_name`
- `enable_ai_content_generation`
- `enable_branding_ai`
- `enable_multi_stakeholder`
- `agency_name`

Keep: `default_stage_deadline_days`, `deadline_warning_days`

Revert `get_stages()` to use hardcoded stage names from the `STAGES` constant instead of settings.

---

## SESSION CONTEXT NOTE

Architecture review completed February 22, 2026. Expand Partners pipeline design is the next planning session before any Expand Partners build work begins.
