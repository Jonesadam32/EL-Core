# Next Chat Prompt — v1.27.1 Bugfix Session

Paste this entire prompt at the start of the next chat:

---

Read @START-HERE-NEXT-SESSION.md and @CURSOR-TODO.md. We are fixing 4 bugs found during testing of v1.27.0. All fixes go into a single build: v1.27.1. Do not build the ZIP until all 4 fixes are done.

## Bug 1 — "View Project" opens wrong project
**File:** `el-core/modules/expand-site/shortcodes/expand-site-portal.php`
**Problem:** When `?project_id=X` is passed in the URL, line ~74 checks `$module->is_stakeholder( $project_id )` to authorize the user. Users set as Decision Maker via the `decision_maker_id` field on the project (not via the stakeholders table) fail this check. The portal then nulls `$project` and falls back to auto-detecting a different project — opening the wrong one.
**Fix:** Add `|| $module->is_decision_maker( $project_id )` to the authorization check so DMs can access their project via direct URL.

## Bug 2 — Project Definition consensus UI not rendering in portal
**File:** `el-core/modules/expand-site/shortcodes/expand-site-portal.php` + `el-core/modules/expand-site/assets/js/expand-site.js`
**Problem:** When `review_status = pending_review`, the portal renders a `<div class="el-es-definition-review-loading">Loading…</div>` placeholder. The JS is supposed to fire an AJAX call (`es_get_definition_review`) on page load to replace it with the full consensus UI (countdown timer, per-field comments, verdict buttons). This is NOT happening — the loading div stays, nothing renders, no countdown timer appears.
**Likely causes to investigate:**
- `ELCore.ajax` may not be defined on the portal page (check if the core JS object is localized for frontend)
- The `es_get_definition_review` AJAX action may not be registered with a `nopriv` hook (needed for logged-in non-admin users on the frontend)
- The JS block that initializes the definition review may be wrapped in an admin-only guard
- Check browser console for JS errors when the portal loads

## Bug 3 — "View as" in Clients contact list must be real "Log in as"
**File:** `el-core/includes/class-organizations.php` (contact list rendering) + `el-core/admin/views/client-profile.php`
**Problem:** The "View as" button currently only appends `?el_view_as=$user_id` to the invoices page URL — it is a preview mode for invoices only, not a real session switch. It does not log the admin in as that user.
**Fix:** Replace the "View as" button with a "Log in as" button that:
1. Posts to a handler that calls `wp_set_auth_cookie( $target_user_id )` to switch the session to the client user
2. Stores the original admin user ID in a WordPress transient (keyed to the new session) so we can switch back
3. Redirects the admin to the client dashboard page after switching
**Note:** Rename all "View as" labels to "Log in as" throughout the admin UI.

## Bug 4 — "Switch back to admin" button missing from WP toolbar
**Files:** `el-core/includes/class-el-core.php` or a new `includes/class-login-as.php`
**Problem:** After using "Log in as" to switch to a client's session, there is no way to return to the admin account without logging out completely.
**Fix:** 
1. Hook into `admin_bar_menu` to add a red "Switch back to [Admin Name]" button to the WP toolbar whenever the current session was initiated via "Log in as" (detect via the transient set in Bug 3 fix)
2. Clicking it calls the handler that restores the original admin session via `wp_set_auth_cookie( $original_admin_id )` and clears the transient
3. The button should be visible on both the frontend and admin bar

## After all 4 fixes:
- Bump version to v1.27.1 in: `el-core/el-core.php` (header + constant) and `build-zip.ps1`
- Update CHANGELOG.md with all 4 fixes under `[1.27.1]`
- Run `build-zip.ps1` from repo root
- Commit and push to GitHub
- Upload `el-core-v1.27.1.zip` from Downloads to WordPress Admin → Plugins → Add New → Upload Plugin
- Re-run testing guide sections 2D, 3B, 3C, 3D, 3E from `V1.27.0-TESTING-GUIDE.md`
