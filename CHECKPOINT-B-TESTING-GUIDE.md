# Checkpoint B Testing Guide - v1.9.0

## BEFORE YOU START

This guide assumes you're starting fresh with no test projects. I'll walk you through creating test data and verifying everything works.

**Time needed:** About 10-15 minutes

---

## STEP 1: Upload the Plugin

### 1.1 - Download the ZIP
- Location: `C:\Github\EL Core\releases\el-core-v1.9.0.zip`
- Right-click → Copy (or just remember the location)

### 1.2 - Go to WordPress Admin
- Open your staging site: https://qd19d0iehj-staging.wpdns.site/wp-admin
- Log in with your admin credentials

### 1.3 - Upload Plugin
1. In the left sidebar, click **Plugins**
2. Click the **Add New** button at the top
3. Click the **Upload Plugin** button at the top
4. Click **Choose File**
5. Navigate to `C:\Github\EL Core\releases\` and select `el-core-v1.9.0.zip`
6. Click **Install Now**

### 1.4 - What You Should See
- You'll see: "Unpacking the package..."
- Then: "Installing the plugin..."
- Then you'll see a message saying: **"Replacing active with uploaded"** (because EL Core is already active)
- Click the **Replace active with uploaded** button

### 1.5 - Success Check
✅ **GOOD**: You see "Plugin updated successfully."
❌ **BAD**: You see any error messages, warnings, or white screen

**If you see errors, STOP and tell me what the error message says.**

---

## STEP 2: Verify the Plugin Activated

### 2.1 - Check Admin Menu
Look at the left sidebar in WordPress admin. You should see:
- **EL Core** (with the EL icon)
  - Dashboard
  - Brand
  - Modules
  - Roles
  - **Expand Site** ← Should be here
  - **Expand Site Settings** ← NEW - should be here now

### 2.2 - Success Check
✅ **GOOD**: You see both "Expand Site" and "Expand Site Settings" in the menu
❌ **BAD**: One or both are missing, or you see error messages

**If you don't see these menu items, STOP and tell me.**

---

## STEP 3: Check Database Tables (Optional but Recommended)

### 3.1 - Access Database
**Option A - If you have phpMyAdmin:**
1. Log into your hosting control panel
2. Find phpMyAdmin
3. Select your WordPress database (probably starts with "qd19d0iehj_")
4. Click the **SQL** tab at the top

**Option B - If you have WP-CLI access:**
Open terminal/command prompt and run:
```bash
wp db query "SHOW TABLES LIKE 'wp_el_es_%';"
```

### 3.2 - Run This SQL Query
Copy and paste this exact query:
```sql
SHOW TABLES LIKE 'wp_el_es_%';
```
Click **Go** (in phpMyAdmin) or press Enter (in WP-CLI)

### 3.3 - What You Should See
You should see a list of **10 tables**:
```
wp_el_es_brand_options          ← NEW
wp_el_es_deadlines              ← NEW
wp_el_es_deliverables           (existing)
wp_el_es_feedback               (existing)
wp_el_es_pages                  (existing)
wp_el_es_project_definition     ← NEW
wp_el_es_projects               (existing)
wp_el_es_stage_history          (existing)
wp_el_es_stakeholders           ← NEW
wp_el_es_user_workflows         ← NEW
```

### 3.4 - Success Check
✅ **GOOD**: You see all 10 tables, including the 5 new ones
❌ **BAD**: You see fewer than 10 tables, or no tables at all

**If you see fewer than 10 tables, STOP and tell me how many you see.**

---

## STEP 4: Test the Settings Page

### 4.1 - Open Settings Page
1. In WordPress admin, click **EL Core** in the left sidebar
2. Click **Expand Site Settings**

### 4.2 - What You Should See
You should see a page with 4 sections:
1. **Stage Names** - 8 text fields (Stage 1 Name through Stage 8 Name)
2. **Deadlines & Escalation** - 2 number fields
3. **Feature Toggles** - 4 checkboxes
4. **Agency Settings** - 3 fields (Agency Name, Default Budget Range)

### 4.3 - Default Values Check
Look at the Stage Names section. The default values should be:
- Stage 1 Name: **Qualification**
- Stage 2 Name: **Discovery**
- Stage 3 Name: **Scope Lock**
- Stage 4 Name: **Visual Identity**
- Stage 5 Name: **Wireframes**
- Stage 6 Name: **Build**
- Stage 7 Name: **Review**
- Stage 8 Name: **Delivery**

### 4.4 - Success Check
✅ **GOOD**: Page loads, you see all 4 sections, default values are correct
❌ **BAD**: Page shows errors, fields are blank, or page won't load

**If the page won't load or shows errors, STOP and tell me what you see.**

---

## STEP 5: Test Changing a Setting

### 5.1 - Change Stage 1 Name
1. Find the **"Stage 1 Name"** field (should currently say "Qualification")
2. Clear it and type: **"Discovery Call"**
3. Scroll to the bottom
4. Click the blue **Save Settings** button

### 5.2 - What You Should See
At the top of the page, you should see a green success message:
```
✓ Settings saved!
```

### 5.3 - Verify the Change
1. Look at the Stage 1 Name field again
2. It should now say: **"Discovery Call"**

### 5.4 - Success Check
✅ **GOOD**: Green success message appears, field shows "Discovery Call"
❌ **BAD**: No success message, field reverted to "Qualification", or error message

---

## STEP 6: Create a Test Project

### 6.1 - Go to Project List
1. Click **EL Core** in the left sidebar
2. Click **Expand Site**

### 6.2 - What You Should See
Either:
- An empty project list (if you've never created projects), OR
- A list of existing projects

### 6.3 - Create New Project
1. Click the **Add New Project** button at the top
2. Fill in the form:
   - **Project Name**: "ABC School Website"
   - **Client Name**: "ABC School"
   - **Status**: Active (should be default)
   - **Budget Range Low**: 3000
   - **Budget Range High**: 8000
   - **Notes**: "Test project for v1.9.0" (optional)
3. Click the blue **Create Project** button

### 6.4 - What You Should See
- You should be redirected to the project detail page
- At the top, you should see "ABC School Website" as the title
- You should see stats showing: **1/8** for the current stage

### 6.5 - Success Check
✅ **GOOD**: Project created, detail page loads, you see project info
❌ **BAD**: Error message appears, page won't load, or stuck on form

---

## STEP 7: Verify Dynamic Stage Name

### 7.1 - Check Stage Name on Project Detail
On the ABC School Website project detail page, look at the stats at the top.

You should see the first stat showing:
- **Number**: 1/8
- **Label**: **"Discovery Call"** ← This is the custom name you set in Step 5

### 7.2 - Check Overview Tab
Scroll down to the **Overview** section (should be the default tab).

You should see a row that says:
- **Current Stage**: 1. Discovery Call

### 7.3 - Success Check
✅ **GOOD**: Stage name shows "Discovery Call" (your custom name)
❌ **BAD**: Stage name shows "Qualification" (the old default)

**If it shows "Qualification", the dynamic stage names aren't working.**

---

## STEP 8: Go Back to Project List

### 8.1 - Return to List
1. Click **EL Core** in the sidebar
2. Click **Expand Site**

### 8.2 - What You Should See
You should see a table with your test project:
- **Name**: ABC School Website
- **Client**: ABC School
- **Stage**: Badge showing "1. Discovery Call" ← Your custom stage name
- **Status**: Active (green badge)
- **Budget**: $3,000 – $8,000

### 8.3 - Success Check
✅ **GOOD**: Project appears in list, stage shows "Discovery Call"
❌ **BAD**: Project missing, or stage shows "Qualification"

---

## STEP 9: Test Reverting Stage Name (Optional)

This tests that settings changes work both ways.

### 9.1 - Go Back to Settings
1. Click **EL Core** → **Expand Site Settings**
2. Find **Stage 1 Name**
3. Change it back from "Discovery Call" to **"Qualification"**
4. Click **Save Settings**

### 9.2 - Check Project List Again
1. Click **EL Core** → **Expand Site**
2. Look at the ABC School Website project
3. The stage should now show: **"1. Qualification"** (reverted to original)

### 9.3 - Success Check
✅ **GOOD**: Stage name changes immediately when you update settings
❌ **BAD**: Stage name doesn't change, or you need to refresh the page

---

## CHECKPOINT B RESULTS

### ✅ ALL TESTS PASSED
If you got green checkmarks on ALL steps:
- **Reply with:** "Checkpoint B passed - all tests successful"
- I'll continue with Phase 2D (Multi-Stakeholder System)

### ❌ SOME TESTS FAILED
If ANY step showed a red X:
- **Reply with:**
  1. Which step number failed (e.g., "Step 3 failed")
  2. What you saw instead of what was expected
  3. Copy any error messages exactly

### 🤔 CONFUSED AT ANY STEP
If you got stuck or confused:
- **Reply with:** "Stuck at Step X - [describe what's confusing]"
- I'll clarify

---

## Clean Up (After Testing)

Once you confirm everything works, you can:
1. Keep the test project, OR
2. Delete it: Go to project list → hover over "ABC School Website" → click "Delete"

---

**Questions?** Tell me exactly which step you're on and what you're seeing.
