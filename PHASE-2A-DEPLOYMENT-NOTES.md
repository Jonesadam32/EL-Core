# Phase 2A Deployment Notes - v1.8.0

## What Changed

**Database Schema Expansion for Expand Site Module**

The Expand Site module database version has been bumped from 1 to 2. This adds support for:
- Multi-stakeholder project management
- Stage-based deadlines and escalation tracking
- Discovery transcript processing and structured project definitions
- Branding workflow with AI-generated color options
- User workflow definition collection
- AI-powered content generation per page

## Database Changes

### New Columns Added to Existing Tables

**`el_es_projects`** (9 new columns):
- `decision_maker_id` - Which stakeholder has final approval authority
- `deadline` - Current stage deadline
- `deadline_stage` - Which stage the deadline applies to
- `flagged_at` - When project was flagged for attention
- `flag_reason` - Why the project is flagged
- `project_type` - Type of site being built
- `project_goal` - Primary business objective
- `discovery_transcript` - Raw meeting notes/Fathom summary
- `discovery_extracted_at` - When AI extracted structured data from transcript

**`el_es_pages`** (3 new columns):
- `ai_draft_content` - AI-generated page content draft
- `client_review_status` - Page approval status (pending/approved/needs_revision)
- `content_blocks` - JSON array of content blocks for section-by-section review

### New Tables Created

1. **`el_es_stakeholders`**
   - Tracks all stakeholders per project (beyond just one client)
   - Supports Decision Maker vs Contributor roles
   - Links to WordPress user accounts

2. **`el_es_project_definition`**
   - Structured discovery data extracted from transcripts
   - Site description, goals, target customers, user types
   - Locked when ready for client to review

3. **`el_es_brand_options`**
   - Mood board image URL
   - AI-generated color palette options (stored as JSON)
   - Selected colors and fonts
   - Lock state when client approves

4. **`el_es_user_workflows`**
   - Client-submitted workflow descriptions per user type
   - Supports iterative refinement (initial + revisions)
   - Lock state when Decision Maker approves

5. **`el_es_deadlines`**
   - Stage-based deadline tracking
   - Extension history
   - Met/missed status

## How Migrations Work

The EL Core database system (`class-database.php`) automatically detects version changes:

1. Module Loader reads `module.json` during activation
2. Compares `database.version` (now 2) with installed version (currently 1)
3. Runs all SQL statements in `migrations["2"]` array
4. Updates stored version in `wp_options` table
5. Logs any errors to WordPress debug.log

## What to Test After Deployment

### Critical Tests (Must Pass)
1. **Existing projects still load** - Go to EL Core → Expand Site, verify project list appears
2. **Project detail page loads** - Click on an existing project, verify no errors
3. **New columns exist** - All new columns should have safe default values (NULL or empty)
4. **New tables created** - Check phpMyAdmin or run `SHOW TABLES LIKE 'wp_el_es_%'`

### Expected Behavior
- Existing projects: All new columns will be NULL/empty (safe defaults)
- No data loss: All existing project data remains intact
- Module still functional: All Phase 1 features (stages, deliverables, feedback) work as before

### If Errors Occur
1. Check WordPress debug.log at `/wp-content/debug.log`
2. Look for lines containing "EL Core: Migration error"
3. Report the specific SQL statement that failed
4. Check database manually - might need to run failed statements via phpMyAdmin

## Rollback Plan (If Needed)

If v1.8.0 causes critical issues:

1. **Quick rollback**: Upload `old-versions/v1.7.0/el-core-v1.7.0.zip` via WordPress Admin
2. **Database state**: New columns and tables remain (harmless), but won't be used by v1.7.0
3. **Fix issues**: Review error logs, adjust migrations, try v1.8.1 deployment

## Files Changed

- `el-core/el-core.php` - Version bumped to 1.8.0
- `el-core/modules/expand-site/module.json` - Database version 2, new schema, migrations
- `CHANGELOG.md` - Added v1.8.0 entry
- `build-zip.ps1` - Version updated to 1.8.0
- `CURSOR-TODO.md` - Phase 2A checked off
- `START-HERE-NEXT-SESSION.md` - Updated with session progress

## Next Steps After Checkpoint A

Once Fred confirms v1.8.0 deployed successfully and all tests pass:

**Phase 2B - Capabilities**
- Add `es_decision_maker` and `es_contributor` capabilities
- Update permission checks in AJAX handlers

**Phase 2C - Module Settings**
- Add configurable stage names, deadline defaults, AI feature toggles
- Make module resale-ready (not hardcoded to Fred's workflow)

---

**Built:** February 21, 2026
**Ready for Checkpoint A deployment**
