# Prompt for Next Chat — Expand Site v1.22.0 UI

Copy and paste this into a new chat to continue Expand Site work:

---

Read @START-HERE-NEXT-SESSION.md and @CURSOR-TODO.md. We need to complete **Expand Site v1.22.0 — Definition Consensus Review System**. The backend is done (DB schema v8, PHP AJAX handlers). Start with the **UI that is NOT YET BUILT**:

1. **Admin UI** (project-detail.php Discovery tab):
   - Definition status badge (Draft / Sent for Review / Client Approved / Needs Revision / Locked)
   - "Send to Client for Review" button with deadline date picker
   - Per-field stakeholder comments panel (admin sees all comments while editing)
   - DM verdict summary card ("6 fields accepted, 1 needs revision")
   - Lock button always visible (override), with confirmation prompt if not yet approved

2. **Client Portal** (expand-site-portal.php Stage 1):
   - Replace current locked-only definition display with full consensus UI
   - Countdown timer, per-field comments + verdict buttons, scroll-depth gate on Approve All
   - "Submit My Input" (contributors), "Make Final Decision" (DM only)
   - Post-decision states: approved banner, needs-revision banner

3. **Admin-side JS** (expand-site-admin.js): Send for Review handler, comments panel refresh

4. **Portal-side JS** (expand-site.js): Load review data, post/reply comments, verdict buttons, scroll-depth tracker, countdown timer, DM decision submit

5. **CSS**: Review status badges, comment thread layout, verdict buttons, countdown timer, DM section

Full spec is in START-HERE-NEXT-SESSION.md under "What's IN PROGRESS — v1.22.0". Follow the release workflow: bump version, CHANGELOG, run build-zip.ps1 after any plugin changes.
