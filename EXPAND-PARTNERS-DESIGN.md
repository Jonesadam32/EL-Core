# Expand Partners — Module Design Document
## Created: February 22, 2026
## Last Updated: February 22, 2026
## Status: Design updated after planning session. Ready for Cursor build after Expand Site Phase 2 is stable.

---

## OVERVIEW

Expand Partners is a business operations module that manages the full lifecycle of ELS partner relationships — from first contact or application through active partnership. A "partner" is an individual or organization (often an up-and-coming entrepreneur) with a product, curriculum, or program that ELS builds out, markets, and co-runs in exchange for a revenue share.

This is NOT a sellable module. Like Expand Site, it is a proprietary internal tool for Expanded Learning Solutions only. Pipeline stages are hardcoded. No configurability overhead.

**ELS is selective.** This is not a site factory. ELS only partners with people who have real potential and a worthwhile project. Volume is not the goal.

---

## HOW A PARTNER ENTERS THE SYSTEM

Two entry paths, both converging at the same first stage:

### Path A — Cold / Inbound (stranger)
Someone discovers ELS through an ad or referral → fills out a short public application form → application sits in a Pending Review queue → Fred reviews → if interested, sends Calendly link to schedule a discovery call → after the call, Fred decides to move them into the active pipeline or decline.

**Application form fields (keep it short — don't scare them off):**
- Full name
- Email
- Organization / project name
- One sentence describing their project or product
- Website (if exists)
- How they heard about ELS

Applications are stored but do NOT create a WordPress user account yet. That happens at onboarding (Stage 4).

### Path B — Known Contact
Fred reaches out to someone he knows, or they reach out to him → Fred sends a Fathom meeting link directly → discovery call happens → Fred decides to move them into the pipeline or not.

For Path B, Fred manually creates the partner record in the admin. No application form needed.

**Both paths converge at Stage 1 — Discovery Call.**

---

## PIPELINE STAGES

Eight stages. Hardcoded. Active Partner is a permanent state with no end — partner stays there indefinitely unless the relationship ends.

```
[APPLICATION or MANUAL ENTRY] → STAGE 1 → STAGE 2 → STAGE 3 → STAGE 4 → STAGE 5 → STAGE 6 → STAGE 7 → ACTIVE
```

---

### Stage 1 — Discovery Call

The first official stage. A Fathom-recorded call where Fred and the potential partner discuss the project idea in depth. A call script is used to ensure all key information is captured.

**What happens:**
- Fathom call conducted and recorded (same pattern as Expand Site)
- After the call, Fred uploads or pastes the Fathom transcript
- AI processes the transcript against the call script structure
- AI generates a structured synopsis: project description, partner background, product idea, target audience, goals, initial impressions
- Fred reviews the synopsis and decides whether to advance

**AI touchpoint:** Transcript → structured Partner Synopsis

**Advance condition:** Fred approves synopsis and decides to move forward

---

### Stage 2 — Internal Strategic Planning

Fred and his PM work internally — no partner involvement yet. This is the ideation and strategy phase where ELS figures out what the partner's product should look like and how to market it before presenting anything.

**What happens:**
- Fred reviews the Stage 1 synopsis, any submitted materials, and the partner's curriculum/content
- AI assists in drafting a strategic plan covering:
  - Recommended product structure (courses, coaching, digital downloads, etc.)
  - Site structure and key pages
  - Mission and positioning statement
  - Target audience definition
  - Marketing channel strategy (YouTube, Instagram, Twitter, email, etc.)
  - Content approach
- Fred and PM refine the plan internally until it's ready to present

**AI touchpoint:** Synopsis + curriculum/content → draft Strategic Plan

**Advance condition:** Fred marks the internal plan as ready to present

---

### Stage 3 — Plan Presentation Call

A second Fathom-recorded call where Fred presents the strategic plan to the partner and captures their feedback.

**What happens:**
- Fred presents the draft strategic plan on a Fathom call
- Partner gives feedback — what they like, what they want to change, what's missing
- Fathom transcript captured and processed by AI
- AI extracts feedback and suggested changes, updates the plan accordingly
- Fred reviews and finalizes the plan
- Both Fred and partner confirm the finalized plan before advancing

**AI touchpoint:** Feedback transcript → updated Strategic Plan

**Advance condition:** Finalized plan confirmed by Fred (and partner verbally)

---

### Stage 4 — Proposal & Contract

AI drafts the proposal from the finalized Strategic Plan and discovery transcript. Fred edits terms, sends to partner, negotiates, and gets a signature.

**What happens:**
- AI auto-drafts the proposal using: Strategic Plan + Partner Synopsis + term sheet defaults (50% product / 70% training splits)
- Fred reviews and adjusts all terms — everything is negotiable:
  - Revenue split percentages (product sales, live trainings)
  - Advance / minimum guarantee (if applicable)
  - Exclusivity scope
  - Timeline
  - Any other negotiated terms
- Proposal sent to partner for review
- Back-and-forth negotiation tracked with notes
- Once agreed, contract generated with final terms
- Contract sent for signature externally (DocuSign or similar) — system tracks signature status
- Fred marks contract as signed and advances

**AI touchpoint:** Strategic Plan + Synopsis → draft Proposal

**Advance condition:** Contract marked as signed by Fred

---

### Stage 5 — Onboarding & Asset Collection

Structured intake. ELS collects everything needed to begin building the partner's site. Partner gets their first portal access here.

**What happens:**
- WordPress user account created for the partner
- Partner gains access to their portal for the first time
- Structured intake checklist — Fred can see what's submitted vs. outstanding:
  - Brand assets (logo, colors, fonts)
  - Professional headshots / photos
  - Bio (short and long form)
  - Curriculum and course content
  - Existing content (videos, articles, presentations, resources)
  - Any existing website or social media links
- AI assist: if partner uploads a pitch deck or existing website, AI can extract brand elements and content to pre-fill fields

**Advance condition:** All required checklist items submitted and confirmed by Fred

---

### Stage 6 — Build

Fred or PM builds the partner's site. HTML mockups are created first and shared with the partner for design approval before anything goes live. Mirrors the Expand Site review process.

**What happens:**
- ELS builds the site: WordPress + EL Core + Expand Partners theme + partner brand customization
- HTML mockups of each key page created first (AI-assisted using Strategic Plan + collected assets)
- Mockups shared with partner through the portal for review and feedback
- Partner approves design direction
- Content applied to pages
- Partner reviews content page by page (same approval mechanism as Expand Site)
- Partner requests changes → ELS makes changes → re-review
- Build milestone tracking (not a full 8-stage system like Expand Site — simpler)

**AI touchpoints:** Strategic Plan + assets → page content generation (same pattern as Expand Site Phase 2I)

**Advance condition:** Fred marks build complete and partner has signed off on the site

---

### Stage 7 — Launch Prep & Training

Site is ready. Partner learns how to use the system, the payment infrastructure is configured, and everything is prepared for launch.

**What happens:**
- Payment infrastructure configured on partner's site (ELS controls this — see Revenue Model)
- Partner trained on their dashboard: how to view revenue, log invoices for training sessions, communicate with ELS
- Training resources delivered through the portal (ties into Tutorials module when built)
- Training completion tracked
- Final launch checklist completed
- Partner activates into Active status

**Advance condition:** Training completion confirmed and Fred approves launch

---

### Stage 8 — Active Partner (permanent)

The partner is live and generating revenue. No end state — partner stays here indefinitely.

**What the ongoing relationship looks like:**
- Revenue flows through ELS-controlled payment infrastructure on partner's site
- ELS sees all revenue in real time — no self-reporting needed
- Payouts calculated automatically based on negotiated rates
- Partners paid out promptly per their percentage
- Partners can log invoices for live training sessions (separate from platform product sales)
- Monthly revenue reconciliation
- Partner has full dashboard visibility into their earnings and payouts
- Quarterly check-in calls (tracked in system)
- Ongoing product/content updates as needed
- Performance reporting available to both partner and Fred

---

## REVENUE MODEL

### Philosophy
ELS runs the payment infrastructure on each partner's site. Revenue flows through ELS systems, so earnings are always known — no partner self-reporting required for product sales. ELS pays out partner percentages promptly and automatically.

For live trainings and professional development sessions (which happen outside the platform), partners log those invoices manually in the system.

### Rate Structure
Rates are stored per-partner record — defaults apply but everything is negotiable at Stage 4.

| Revenue Type | Partner Keeps | ELS Retains |
|---|---|---|
| Standalone product sales (subscriptions, licenses, per-seat) | 50% | 50% |
| Live trainings / professional development sessions | 70% | 30% |

### How Revenue Tracking Works

**Product Sales (auto-tracked):**
- ELS payment system on partner site captures all transactions
- Revenue flows into ELS master account
- System calculates partner's share automatically
- Partner sees earnings in real time on their dashboard
- ELS processes payouts on agreed schedule

**Live Trainings (manually logged):**
- Partner logs training invoice in their portal: client name, date, amount
- System calculates ELS platform fee at their negotiated rate
- Appears on both partner dashboard and Fred's admin view
- Fred invoices partner for the platform fee externally (system tracks status)
- Fred marks payment received → balance updates

### Partner Dashboard (what they see)
- Total product revenue (all time and by period)
- Total training revenue logged
- ELS platform fees owed (training only — product fees handled automatically)
- Payout history
- Training invoice log with status
- Site performance metrics

### Admin Dashboard (what Fred sees)
- All partners: name, project, current stage, status, last activity, outstanding balance
- Per-partner: revenue, payouts, fees owed, fees paid, last invoice date
- Flags for partners who haven't had activity in 60+ days
- Master revenue view across all partners

---

## DATA MODEL

### Shared Tables (PM Module owns)

```sql
el_projects
- id
- name
- program_type  ('expand-partners')
- status
- assigned_to
- created_at
```

### Expand Partners Tables (ep_ prefix)

```sql
el_ep_applications
- id
- full_name
- email
- project_name
- description  (one sentence)
- website
- referral_source
- status  (pending / declined / converted)
- decline_reason
- applied_at
- reviewed_at
- reviewed_by

el_ep_partners
- id
- project_id  (links to el_projects)
- user_id  (WP user — created at Stage 5 onboarding)
- application_id  (nullable — null if Path B / manual entry)
- current_stage  (1-8, or 'active')
- stage_advanced_at
- product_revenue_rate  (decimal — ELS share of product sales, default 0.50)
- training_revenue_rate  (decimal — ELS share of training revenue, default 0.30)
- contract_signed_at
- contract_notes
- status  (pending_review / active_pipeline / active_partner / paused / ended)
- created_at
- created_by

el_ep_stage_history
- id
- partner_id
- from_stage
- to_stage
- advanced_by
- notes
- advanced_at

el_ep_transcripts
- id
- partner_id
- stage  (1 = discovery, 3 = plan presentation)
- raw_transcript  (LONGTEXT — pasted Fathom content)
- ai_synopsis  (LONGTEXT — AI-extracted summary)
- processed_at
- created_at

el_ep_strategic_plan
- id
- partner_id
- version  (increments on each update)
- product_structure  (TEXT)
- site_structure  (TEXT)
- mission_statement  (TEXT)
- target_audience  (TEXT)
- marketing_channels  (JSON — array of selected channels)
- content_approach  (TEXT)
- additional_notes  (TEXT)
- status  (draft / presented / finalized)
- finalized_at
- created_at
- updated_at

el_ep_proposals
- id
- partner_id
- ai_draft  (LONGTEXT — AI-generated draft)
- final_content  (LONGTEXT — Fred's edited version)
- product_rate  (decimal — negotiated ELS share)
- training_rate  (decimal — negotiated ELS share)
- advance_amount  (decimal, nullable)
- exclusivity_scope  (TEXT, nullable)
- timeline_notes  (TEXT)
- status  (draft / sent / negotiating / agreed / signed)
- sent_at
- signed_at
- created_at

el_ep_onboarding_checklist
- id
- partner_id
- item_key  (e.g. 'logo', 'brand_colors', 'bio', 'headshot', 'curriculum', 'existing_content')
- item_label
- required  (boolean)
- submitted_at
- submitted_by
- file_url  (nullable)
- notes
- status  (pending / submitted / confirmed)

el_ep_invoices
- id
- partner_id
- invoice_type  (product / training)
- client_name  (nullable — for training invoices)
- invoice_date
- amount  (decimal 10,2)
- els_fee_rate  (snapshot of rate at time of invoice)
- els_fee_amount  (calculated on save)
- status  (logged / fee_invoiced / fee_paid)
- fee_invoiced_at
- fee_paid_at
- notes
- created_at
- created_by

el_ep_messages
- id
- partner_id
- sender_id  (WP user)
- message  (TEXT)
- read_at  (nullable)
- created_at
```

---

## PORTAL EXPERIENCE

### Partner Portal (frontend shortcode)

Single dashboard with tabbed sections. Content adapts based on current stage.

**Overview tab**
- Current stage with progress indicator
- Outstanding action items
- Quick message button to contact ELS

**Revenue tab** (Active partners only)
- Total product revenue and payouts
- Log New Training Invoice button
- Training invoice history with status
- ELS fees owed/paid history
- Site performance metrics

**Project tab** (Stages 6-7)
- Build progress and milestone status
- Links to page review/approval
- Design mockup review

**Resources tab**
- Training materials (ties into Tutorials module when built)
- Downloads (contract, brand guide, strategic plan)

**Messages tab**
- Threaded messaging with ELS team

**Support tab** (Active partners only)
- Submit support request
- View open tickets

### Shortcodes

- `[el_partner_portal]` — main partner dashboard (all tabs, adapts to current stage)
- `[el_partner_apply]` — public application form (pre-pipeline, Path A entry)

---

## ADMIN EXPERIENCE

Fred sees:
- Partner list: name, project, current stage, status, last activity, balance owed
- Pending applications queue (separate from active pipeline)
- Per-partner detail view with all pipeline data and admin-only controls
- Advance stage button with required notes
- Revenue master view across all partners
- Message inbox per partner
- Flag system for partners needing attention

---

## AI TOUCHPOINTS

1. **Stage 1 — Discovery Transcript Processing:** Paste Fathom transcript → AI extracts structured Partner Synopsis (project, goals, audience, product idea)
2. **Stage 2 — Strategic Plan Draft:** Synopsis + curriculum/content → AI drafts Strategic Plan (product structure, site structure, marketing channels, mission)
3. **Stage 3 — Feedback Processing:** Plan presentation transcript → AI extracts partner feedback → updates Strategic Plan
4. **Stage 4 — Proposal Draft:** Strategic Plan + Synopsis + rate defaults → AI drafts Proposal document
5. **Stage 6 — Content Generation:** Strategic Plan + collected assets → AI generates page content blocks (same as Expand Site)

---

## INTEGRATION POINTS

- **PM Module:** Expand Partners writes to shared `el_projects` and `el_tasks` tables. PM Kanban picks up tasks automatically.
- **Support Agent Module:** Partner support requests route through support tickets when that module is built.
- **Tutorials Module:** Training resources in Stage 7 and Active partner portal pull from tutorials when built.
- **Fluent CRM:** Partner contact records live in Fluent CRM. Partner WP user linked by email.

---

## WHAT THIS MODULE DOES NOT DO

- Does not manage the partner's private client relationships
- Does not replace Fluent CRM for contact management
- Does not handle generic project management (that's the PM module's Kanban)

---

## OPEN QUESTIONS (resolve before build)

1. Does the Stage 6 site build use the full Expand Site page review system (pages, client approval, revision cycles), or a lighter version specific to Expand Partners?
2. Should messaging be its own shared module (since Expand Site will also need it), or built per-module for now?
3. When a partner relationship ends, what happens to their data — archived, anonymized, or kept as-is?
4. What payment processor does ELS use on partner sites to capture product sales? (Stripe? WooCommerce? This affects how revenue auto-tracking is implemented.)

---

## BUILD ORDER RECOMMENDATION

Build after Expand Site Phase 2 is fully stable and tested. Expand Partners reuses patterns from Expand Site extensively.

Suggested Cursor phases:
- **Phase A:** Database schema, module skeleton, application form (`[el_partner_apply]`), pending applications queue in admin
- **Phase B:** Pipeline Stages 1-3 — transcript processing, strategic plan builder, plan presentation workflow
- **Phase C:** Stage 4 — proposal builder with editable terms, contract tracking
- **Phase D:** Stage 5 — onboarding portal, asset collection checklist, partner WP user creation
- **Phase E:** Stage 6-7 — build tracking, page review/approval, training checklist
- **Phase F:** Active Partner — revenue dashboard, training invoice logging, payout tracking
- **Phase G:** Messaging system
- **Phase H:** Full portal shortcodes and frontend experience
