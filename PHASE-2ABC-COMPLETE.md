# Phase 2A+2B+2C Complete - v1.9.0

## Summary

I've completed **Phase 2A (Database Schema), Phase 2B (Capabilities), and Phase 2C (Module Settings)** for the Expand Site module. The plugin is now at **v1.9.0** and ready for Checkpoint B deployment.

---

## What Was Built

### Phase 2A - Database Schema ✅

**New Tables (5):**
1. `el_es_stakeholders` - Multi-stakeholder project access control
2. `el_es_project_definition` - Structured discovery data from transcripts
3. `el_es_brand_options` - Branding workflow with AI color palettes
4. `el_es_user_workflows` - Client-submitted workflow descriptions
5. `el_es_deadlines` - Stage-based deadline tracking

**New Columns Added:**
- `el_es_projects`: 9 columns (decision_maker_id, deadline, deadline_stage, flagged_at, flag_reason, project_type, project_goal, discovery_transcript, discovery_extracted_at)
- `el_es_pages`: 3 columns (ai_draft_content, client_review_status, content_blocks)

**Migration System:**
- All schema changes configured in `module.json` database version 2
- Migrations run automatically on plugin activation via `class-database.php`
- Existing data safe - new columns have safe defaults (NULL or empty)

### Phase 2B - Capabilities ✅

**New Capabilities:**
- `es_decision_maker` - Client role with lock/approve authority
- `es_contributor` - Client role with input-only access

**Permission System:**
- Added 3 helper methods to module class:
  - `is_decision_maker()` - Check if user can lock/approve things
  - `is_stakeholder()` - Check if user is on project team (via stakeholders table)
  - `can_contribute()` - Check if user can provide input

**Updated Shortcodes:**
- All 3 client-facing shortcodes now support multi-stakeholder model:
  - `[el_project_portal]`
  - `[el_page_review]`
  - `[el_feedback_form]`
- Auto-detect project from stakeholders table (new) OR client_user_id (legacy)
- Permission checks use new stakeholder-based system

**Role Mappings:**
- Administrators: All capabilities including es_decision_maker, es_contributor
- Editors: view_expand_site, submit_feedback, es_contributor
- Subscribers: view_expand_site, submit_feedback, es_contributor

### Phase 2C - Module Settings ✅

**New Settings (17 total):**
- **Stage Names**: 8 customizable names (stage_1_name through stage_8_name)
- **Deadline Defaults**: default_stage_deadline_days (7), deadline_warning_days (2)
- **Feature Toggles**:
  - enable_ai_content_generation (true)
  - enable_branding_ai (true)
  - enable_multi_stakeholder (true)
  - enable_client_portal (true)
- **Agency Settings**: agency_name, default_budget_low (3000), default_budget_high (10000)

**Settings Admin Page:**
- Created `admin/views/settings.php` - comprehensive settings UI
- Registered at: EL Core → Expand Site Settings
- 4 sections: Stage Names, Deadlines & Escalation, Feature Toggles, Agency Settings
- Saves to core settings system via `$core->settings`

**Dynamic Stage Names:**
- Converted `get_stage_name()` from static to instance method
- Reads stage names from settings instead of hardcoded constants
- Falls back to hardcoded defaults if settings not set
- Updated all references throughout:
  - Admin views (project-list.php, project-detail.php)
  - Shortcodes (project-portal.php)
  - AJAX handlers (advance_stage)

**Resale-Ready:**
- Other agencies can customize workflow without touching code
- Stage names, deadlines, feature toggles all configurable
- Agency branding (name) built in

---

## Files Changed

### Core Module Files
- `el-core/modules/expand-site/module.json` - Database v2, new caps, 17 settings
- `el-core/modules/expand-site/class-expand-site-module.php` - Permission helpers, dynamic stages, settings page registration

### Admin Views
- `el-core/modules/expand-site/admin/views/settings.php` - **NEW** settings page
- `el-core/modules/expand-site/admin/views/project-list.php` - Use dynamic stage names
- `el-core/modules/expand-site/admin/views/project-detail.php` - Use dynamic stage names

### Shortcodes
- `el-core/modules/expand-site/shortcodes/project-portal.php` - Multi-stakeholder support, dynamic stages
- `el-core/modules/expand-site/shortcodes/page-review.php` - Multi-stakeholder support
- `el-core/modules/expand-site/shortcodes/feedback-form.php` - Multi-stakeholder support

### Plugin Core
- `el-core/el-core.php` - Version bumped to 1.9.0
- `CHANGELOG.md` - Added v1.9.0 entry
- `build-zip.ps1` - Version updated to 1.9.0

### Documentation
- `CURSOR-TODO.md` - Phases 2A, 2B, 2C checked off
- `START-HERE-NEXT-SESSION.md` - Session progress documented

---

## Checkpoint B - What to Test

### Upload & Activate
1. Upload `C:\Github\EL Core\releases\el-core-v1.9.0.zip` to staging site
2. Via: WordPress Admin → Plugins → Add New → Upload Plugin
3. Click "Replace current with uploaded"
4. Verify no activation errors

### Verify Migrations Ran
Run this SQL query in phpMyAdmin or WP-CLI:
```sql
SHOW TABLES LIKE 'wp_el_es_%';
```

Should show 10 tables total (5 existing + 5 new):
- `wp_el_es_projects` (existing, now has 9 new columns)
- `wp_el_es_stage_history` (existing)
- `wp_el_es_deliverables` (existing)
- `wp_el_es_feedback` (existing)
- `wp_el_es_pages` (existing, now has 3 new columns)
- `wp_el_es_stakeholders` ← NEW
- `wp_el_es_project_definition` ← NEW
- `wp_el_es_brand_options` ← NEW
- `wp_el_es_user_workflows` ← NEW
- `wp_el_es_deadlines` ← NEW

### Test Settings Page
1. Go to: **EL Core → Expand Site Settings**
2. Page should load without errors
3. Change "Stage 1 Name" from "Qualification" to "Discovery Call"
4. Click "Save Settings"
5. Go to: **EL Core → Expand Site** (project list)
6. Verify stage name appears as "Discovery Call" in project table

### Test Existing Functionality
1. Go to: **EL Core → Expand Site**
2. Click on an existing project (or create a test project if none exist)
3. Verify project detail page loads
4. Verify all tabs work: Overview, Stage History, Deliverables, Pages, Feedback

### Test Capabilities (Optional)
1. Go to: **Users → Add New**
2. Create a test user with Subscriber role
3. Go to: **EL Core → Roles**
4. Verify "es_contributor" and "es_decision_maker" appear in capabilities list

---

## Rollback Plan (If Needed)

If v1.9.0 causes issues:

1. **Quick rollback**: Re-upload `old-versions/v1.7.0/el-core-v1.7.0.zip`
2. **Database state**: New tables and columns remain (harmless), but won't be used by v1.7.0
3. **Settings**: New settings remain in database (harmless)
4. **Fix & retry**: Review error logs, fix issues, deploy v1.9.1

---

## Next Steps

**After Checkpoint B passes:**
- Continue with Phase 2D (Multi-Stakeholder System)
  - Add Stakeholders tab to project detail page
  - AJAX handlers: add/remove/change stakeholder role
  - Update project portal to show DM vs Contributor controls

**Estimated Progress:**
- Phase 2: 3 of 11 sub-phases complete (2A, 2B, 2C)
- Remaining: 2D through 2K (8 more sub-phases)

---

**Built:** February 21, 2026  
**Version:** 1.9.0  
**Ready for:** Checkpoint B deployment
