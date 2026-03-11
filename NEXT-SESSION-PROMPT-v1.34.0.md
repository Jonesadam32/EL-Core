# Next Session Prompt — v1.34.0 Debug & Deploy

> **Read this before starting.**
> **Plugin version:** v1.34.0 — built, ZIP generated, **module fails to load**
> **Goal this session:** Diagnose and fix the load error, bump to v1.34.1, deploy

---

## START WITH THIS

```
Read @START-HERE-NEXT-SESSION.md. I'm working on the Expand Site workstream — current version is v1.34.0. The module fails to load after last session's Phase 5 Visual Identity build. Fix the load error as v1.34.1.
```

---

## CONTEXT

Last session (March 10, 2026) we:
1. Completely removed the old Mood Board / Template Library system (Phase 2G-B)
2. Built the new Visual Identity intake system (Phase 5)
3. Bumped to v1.34.0 and built the ZIP

After the user uploaded the ZIP and reapplied the modules, **Expand Site fails to load**. This is the same behavior as previous module crashes — the module loader auto-deactivates modules that throw PHP fatal errors.

---

## MOST LIKELY CAUSE

During the large block removal of template/mood board methods, `add_deliverable()` was likely accidentally deleted. The AJAX handler `handle_add_deliverable` still calls `$this->add_deliverable()`. If the method is missing, it's a fatal `Call to undefined method` error that prevents the whole module from loading.

**How to verify:**
```powershell
Select-String -Path "c:\Github\EL Core\el-core\modules\expand-site\class-expand-site-module.php" -Pattern "function add_deliverable"
```
If no match → the method is missing → that's the bug.

**How to find what was deleted:**
```powershell
git diff HEAD~1 -- "el-core/modules/expand-site/class-expand-site-module.php" | Select-String "^\-.*function " | head -50
```
This shows all functions that existed in the previous commit but are now gone.

---

## FIX APPROACH

1. Identify all missing methods by comparing against the previous git commit
2. Restore the missing method(s) — either from git diff output or by reconstructing them
3. Do a final check: grep for all `$this->methodName()` calls in the class and verify each method exists
4. Bump version to v1.34.1, update `build-zip.ps1`, update `CHANGELOG.md`
5. Run `.\build-zip.ps1` to build the ZIP
6. Upload to staging, verify plugin activates without errors
7. Verify Expand Site admin page loads
8. Verify project detail page loads

---

## FILES MODIFIED LAST SESSION

| File | What Changed |
|------|-------------|
| `el-core/el-core.php` | Version: 1.33.20 → 1.34.0 |
| `build-zip.ps1` | Version: 1.33.20 → 1.34.0 |
| `CHANGELOG.md` | v1.34.0 entry added |
| `modules/expand-site/module.json` | Removed `es_manage_templates` capability; added DB migration v13 (`el_es_visual_brief` table) |
| `modules/expand-site/class-expand-site-module.php` | Removed ~15 methods + 10 AJAX hooks; added `init_visual_brief()`, `get_visual_brief()`, `generate_visual_brief()`, 6 AJAX handlers |
| `modules/expand-site/admin/views/project-detail.php` | Removed old Phase 5 mood board panel; added new 3-state Visual Identity admin panel |
| `modules/expand-site/admin/views/template-library.php` | **DELETED** |
| `modules/expand-site/shortcodes/expand-site-portal.php` | Removed mood board section; added Phase 5 portal intake form |
| `modules/expand-site/assets/js/expand-site-admin.js` | Removed Template Library IIFEs; appended Visual Identity admin JS |
| `modules/expand-site/assets/js/expand-site.js` | Removed Mood Board voting IIFEs; appended Visual Identity portal JS |
| `modules/expand-site/assets/css/expand-site.css` | Removed old template/mood board CSS; appended `.el-es-vi-*` CSS |

---

## AFTER THE BUG IS FIXED

Run the Phase 5 end-to-end checklist from `CURSOR-TODO.md` (the Phase 5 section at the bottom of the User Journey section).
