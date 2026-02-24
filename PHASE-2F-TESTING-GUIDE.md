# Phase 2F Testing Guide - v1.12.0
## Discovery Transcript System

## BEFORE YOU START

This guide walks you through testing the new AI-powered transcript processing feature. You'll paste a discovery call transcript, have AI extract project requirements, and lock the definition.

**Time needed:** About 5-10 minutes

**Prerequisites:** 
- You have at least one test project created (from previous testing)
- You have access to the OpenAI API (configured in EL Core settings)

---

## WHAT'S NEW IN v1.12.0

Phase 2F adds a **Discovery tab** to project detail pages where you can:

1. **Paste meeting transcripts** (Fathom summaries, notes, or any text)
2. **Process with AI** to automatically extract:
   - Site description
   - Primary goal
   - Secondary goals
   - Target customers
   - User types
   - Site type
3. **Edit extracted data** manually if AI missed anything
4. **Lock the definition** once finalized (prevents further changes)

---

## STEP 1: Upload the Plugin

### 1.1 - Download the ZIP
- Location: `C:\Github\EL Core\releases\el-core-v1.12.0.zip`
- This file was just built and is ready to upload

### 1.2 - Go to WordPress Admin
- Open your staging site: https://qd19d0iehj-staging.wpdns.site/wp-admin
- Log in with your admin credentials

### 1.3 - Upload Plugin
1. In the left sidebar, click **Plugins**
2. Click the **Add New** button at the top
3. Click the **Upload Plugin** button at the top
4. Click **Choose File**
5. Navigate to `C:\Github\EL Core\releases\` and select `el-core-v1.12.0.zip`
6. Click **Install Now**

### 1.4 - Replace Active Plugin
- You'll see: "Replacing active with uploaded"
- Click the **Replace active with uploaded** button

### 1.5 - Success Check
✅ **GOOD**: You see "Plugin updated successfully."
❌ **BAD**: You see any error messages, warnings, or white screen

**If you see errors, STOP and tell me what the error message says.**

---

## STEP 2: Verify the Discovery Tab Appears

### 2.1 - Navigate to Expand Site
1. In the left sidebar, click **EL Core**
2. Click **Expand Site**
3. You should see your project list

### 2.2 - Open a Project
1. Click on any project name to open the project detail page
2. Look at the tab navigation below the project header

### 2.3 - Find the Discovery Tab
You should see these tabs in order:
- Overview
- Stakeholders
- **Discovery** ← **NEW TAB**
- Stage History
- Deliverables
- Pages
- Feedback

✅ **GOOD**: Discovery tab appears in the correct position
❌ **BAD**: Discovery tab is missing or in wrong position

---

## STEP 3: Test AI Transcript Processing

### 3.1 - Click the Discovery Tab
You should see:
- A section titled "Meeting Transcript" at the top
- A large textarea (12 rows, monospace font)
- A blue "Process with AI" button
- Below that, a "Project Definition" section with empty form fields

### 3.2 - Paste a Sample Transcript
Copy and paste this sample transcript into the textarea:

```
Discovery Call - Bright Horizons Academy
Date: February 15, 2026

Client: Sarah Martinez, Head of Admissions
Present: Tom Chen (Director), Lisa Park (IT Manager)

Sarah: We need a new website for our private K-12 school. The current site is outdated and doesn't reflect our modern curriculum.

Tom: Our main goal is to increase enrollment by showcasing our STEM programs and college prep track. We want parents to see why we're different from public schools.

Lisa: We also need an alumni network section and a portal where parents can see their child's grades and upcoming events.

Sarah: Our target audience is affluent families in the metro area, typically professionals aged 35-50 looking for quality education for their kids.

Tom: We have three main user types: prospective parents who are researching schools, current parents who need access to portals and resources, and alumni who want to stay connected.

Lisa: This is definitely an educational website with some e-commerce features for donations and event tickets.
```

### 3.3 - Click "Process with AI"
1. Click the blue "Process with AI" button
2. Button text should change to "Processing with AI..."
3. Wait 5-10 seconds for the AI to process

### 3.4 - Verify Extracted Data
After processing completes, you should see an alert: "Transcript processed successfully! Review the extracted data below and make any needed edits."

Check that the form fields below are now filled in:

**Site Description** should contain something like:
- "A new website for Bright Horizons Academy, a private K-12 school showcasing modern curriculum and STEM programs"

**Primary Goal** should contain something like:
- "Increase enrollment by showcasing STEM programs and college prep track"

**Secondary Goals** should contain:
- "Alumni network section, parent portal for grades and events, donations and event tickets"

**Target Customers** should contain:
- "Affluent families in the metro area, professionals aged 35-50 seeking quality education"

**User Types** should contain:
- "Prospective parents, Current parents, Alumni"

**Site Type** should contain:
- "Educational website" or "Educational Portal"

✅ **GOOD**: All fields populated with relevant extracted data
❌ **BAD**: Fields are empty, wrong data, or AI error message

**If extraction fails, check:**
- Is OpenAI API configured in EL Core settings?
- Is your API key valid and has credits?
- Check browser console for error messages

---

## STEP 4: Test Manual Editing

### 4.1 - Edit a Field
1. Click in the "Secondary Goals" textarea
2. Add a new line: "Virtual tour feature for campus"
3. The field should be editable (not disabled)

### 4.2 - Save the Definition
1. Scroll down and click the "Save Definition" button (gray button with save icon)
2. Button should say "Saving..." briefly
3. You should see an alert: "Definition saved successfully!"

✅ **GOOD**: Save works, alert appears, no errors
❌ **BAD**: Error message, button stays disabled, no response

---

## STEP 5: Test Definition Locking

### 5.1 - Lock the Definition
1. Scroll to the bottom of the Project Definition form
2. You should see two buttons:
   - "Save Definition" (gray, secondary)
   - "Confirm & Lock Definition" (blue, primary, with lock icon)
3. Click "Confirm & Lock Definition"
4. A confirmation dialog should appear asking if you're sure
5. Click OK to confirm

### 5.2 - Verify Locked State
After locking, the page should reload. You should now see:

**At the top of the Discovery tab:**
- A green notice box saying: "Definition Locked — Locked by [Your Name] on Feb 22, 2026 [time]. Changes cannot be made."

**In the Project Definition section:**
- All form fields should be read-only (grayed out, cannot edit)
- The "Save Definition" button should be gone
- The "Confirm & Lock Definition" button should be gone

**The transcript textarea should be hidden**
- The "Meeting Transcript" section should not be visible at all when locked

✅ **GOOD**: All edit controls hidden, fields read-only, locked notice shows
❌ **BAD**: Can still edit fields, buttons still appear, no locked notice

---

## STEP 6: Verify Lock Persists

### 6.1 - Reload the Page
1. Click the browser refresh button
2. Navigate to the Discovery tab again

### 6.2 - Check Locked State Remains
- Green "Definition Locked" notice should still be at the top
- All fields should still be read-only
- No edit buttons visible

✅ **GOOD**: Lock persists after reload
❌ **BAD**: Fields become editable again, buttons reappear

---

## STEP 7: Test on Fresh Project (Optional)

### 7.1 - Create a New Test Project
1. Go back to the project list
2. Click "Create Project"
3. Fill in:
   - Name: "Mountain View Community Center"
   - Client Name: "Jane Thompson"
   - Budget: $5,000 - $15,000
4. Click Create Project

### 7.2 - Open Discovery Tab
1. You should be redirected to the new project detail page
2. Click the Discovery tab

### 7.3 - Verify Empty State
- Transcript textarea should be empty
- All definition fields should be empty
- "Process with AI" button should be visible
- No "locked" notice should appear

### 7.4 - Test Without AI (Manual Entry)
1. Leave the transcript textarea empty
2. Manually type into the definition fields:
   - Site Description: "A community center website"
   - Primary Goal: "Promote events and memberships"
   - (Fill in a few other fields)
3. Click "Save Definition"
4. Should save successfully without requiring a transcript

✅ **GOOD**: Can save definition without processing a transcript
❌ **BAD**: Requires transcript to save

---

## COMMON ISSUES

### Issue: AI Processing Fails

**Symptoms:** Error message after clicking "Process with AI"

**Possible Causes:**
1. OpenAI API not configured
2. Invalid API key
3. No API credits remaining
4. Network timeout

**Solution:**
- Go to EL Core → Settings → AI Integration
- Verify API key is entered correctly
- Test connection with OpenAI
- Check browser console for detailed error

### Issue: Definition Won't Lock

**Symptoms:** Clicking "Lock Definition" does nothing or shows error

**Possible Causes:**
1. No definition data saved yet
2. JavaScript error
3. Permission issue

**Solution:**
- Make sure definition fields have data
- Click "Save Definition" first, then try locking
- Check browser console for errors
- Verify you're logged in as admin

### Issue: Transcript Section Doesn't Hide When Locked

**Symptoms:** Can still see transcript textarea after locking

**Possible Causes:**
- PHP template logic error
- Cache issue

**Solution:**
- Hard refresh the page (Ctrl+Shift+R)
- Clear browser cache
- Report this bug

---

## SUCCESS CRITERIA

Phase 2F is working correctly if:

✅ Discovery tab appears on project detail page
✅ Can paste transcript into textarea
✅ AI extracts data into definition fields
✅ Can manually edit extracted data
✅ Can save definition without locking
✅ Can lock definition after saving
✅ Locked state shows notice and makes fields read-only
✅ Lock persists after page reload
✅ Transcript input hides when definition is locked
✅ Can create definition manually without AI processing

---

## WHAT TO TEST NEXT

After Phase 2F passes testing, we'll move to:

**Phase 2G - Branding Workflow**
- Upload mood boards
- AI generates color palette options
- Client selects brand option
- Lock brand choices

---

## NOTES FOR FRED

**What Changed in This Version:**
- Added Discovery tab with AI transcript processing
- New AJAX handlers: `es_process_transcript`, `es_save_definition`, `es_lock_definition`
- Uses GPT-4 with temperature 0.3 for consistent extraction
- Definition stored in `el_es_project_definition` table
- Transcript saved to `el_es_projects.discovery_transcript`

**Database:**
- No new migrations needed (tables already exist from v1.11.x)
- Schema version still 2

**Known Limitations:**
- AI extraction quality depends on transcript clarity
- No "unlock" feature (lock is permanent - by design)
- No client-facing interface yet (admin only for now)

**Performance:**
- AI processing takes 5-10 seconds depending on transcript length
- No caching of AI responses (processes fresh each time)
- Transcript stored as LONGTEXT (no size limit)

---

**Questions or Issues?**

If anything doesn't work as described in this guide, note:
1. What step you were on
2. What you expected to happen
3. What actually happened
4. Any error messages (exact text)
5. Browser console errors (F12 → Console tab)

I'll help debug!
