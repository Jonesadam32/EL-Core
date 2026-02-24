# Cursor Prompt — Branding System Fix (v1.19.1)

> Read START-HERE-NEXT-SESSION.md and ARCHITECTURE-DECISIONS-FEB-22-2026.md before starting.
> Do not build anything until you have read this entire document.

---

## WHAT HAPPENED AND WHY WE'RE HERE

v1.19.0 was built with the AI logo analysis, palette generation, and Pickr color pickers inside **Settings → Brand** (the WordPress admin page). That was wrong.

The admin brand settings page is **Fred's tool for configuring ELS's own brand**. It should be simple: upload a logo, pick colors, set fonts. That's it.

The AI palette generation, stakeholder voting on palettes, and client color selection belong **inside the Expand Site client portal** — they're part of the project workflow where clients and stakeholders make decisions. That work is Phase 2G-B and it hasn't been built yet.

---

## YOUR TASK FOR THIS SESSION

You have three jobs. Do them in order. Do not skip ahead.

---

## JOB 1 — REVERT THE ADMIN BRAND PAGE

The current `admin/views/settings-brand.php` was rebuilt with features that don't belong there. You need to simplify it back to a clean admin tool.

**Remove entirely from settings-brand.php:**
- The "two-path" radio toggle (Analyze My Logo vs Pick My Own Colors)
- The "Analyze Logo" button and loading state
- The 3 palette swatch cards
- The "Use This Palette" button
- Pickr color wheel pickers (the CDN JS and CSS for Pickr)
- The `el_analyze_logo` AJAX handler (remove from class-el-core.php)
- The `el_save_brand_selection` AJAX handler (remove from class-el-core.php)
- The Pickr CDN enqueue (remove from class-el-core.php)
- The palette card CSS from admin.css
- The palette/analysis JavaScript from admin.js

**What the admin brand page SHOULD have (keep or rebuild these):**
- Section 1 — Logo: Primary logo upload, logo variant dark, logo variant light, favicon
- Section 2 — Brand Colors: Three simple hex text input fields for primary, secondary, accent. Use `EL_Admin_UI::form_row()` with type `text`. No color wheel, no AI.
- Section 3 — Typography: Heading font select + body font select (the existing 8-option lists)
- Section 4 — Brand Voice: Tone select, audience text field, values textarea
- Section 5 — Dark Mode: Single checkbox

Everything in these sections should use `EL_Admin_UI::*` components. No raw HTML.

**Keep in class-settings.php (these new fields are correct):**
- `logo_variant_dark`
- `logo_variant_light`
- `favicon_url`
- `dark_mode_preference`
- `brand_tone`
- `brand_audience`
- `brand_values`
- `ai_palette_suggestions` — keep the field, it will be populated later by the client portal workflow, not the admin page
- `palette_selected` — keep the field for the same reason

---

## JOB 2 — KEEP THE CSS TOKEN EXPANSION (this part was correct)

The work done in `class-asset-loader.php` to expand from 5 to ~25 CSS tokens is **correct and should stay**. Do not revert it.

The `generate_full_token_set()` method that calculates:
- `--el-primary-dark`, `--el-primary-text` (and equivalents for secondary/accent)
- Neutral scale (6 values: `--el-white`, `--el-bg`, `--el-border`, `--el-muted`, `--el-text`, `--el-dark`)
- Semantic colors (`--el-success`, `--el-warning`, `--el-error`, `--el-info`)

This should stay exactly as built. Verify it still works correctly after the admin page changes.

Also keep in `class-ai-client.php`:
- `complete_with_image()` and `call_anthropic_vision()` — these will be used by the Expand Site portal later

---

## JOB 3 — VERIFY AND DEPLOY

After the admin page is simplified and the AJAX handlers are removed:

- [ ] Confirm settings-brand.php renders without errors
- [ ] Confirm all 5 sections save correctly
- [ ] Confirm the CSS token expansion still outputs correctly on the frontend (check browser inspector for `--el-*` variables in `<head>`)
- [ ] Confirm no JS console errors on the brand page
- [ ] Confirm Expand Site module still renders correctly (no broken CSS variables)
- [ ] Bump to v1.19.1
- [ ] Update CHANGELOG.md
- [ ] Run build-zip.ps1
- [ ] Report back to Fred with what was changed and confirmation it's ready to upload

---

## WHAT IS NOT YOUR JOB THIS SESSION

Do not build:
- The AI logo analysis in the client portal
- The palette voting system
- The stakeholder review UI
- Anything in Phase 2G-B

That work is coming in a future session with its own spec. Your only job right now is to clean up v1.19.0 so the admin brand page is simple and correct, and the token expansion is working.

---

## FILE REFERENCE

Files you will touch:
- `el-core/admin/views/settings-brand.php` — simplify
- `el-core/admin/js/admin.js` — remove Pickr init, palette rendering, analyze logo handler
- `el-core/admin/css/admin.css` — remove palette card styles
- `el-core/includes/class-el-core.php` — remove AJAX handlers, remove Pickr enqueue
- `el-core/includes/class-settings.php` — keep new fields, no changes needed
- `el-core/includes/class-asset-loader.php` — keep token expansion, no changes needed
- `el-core/includes/class-ai-client.php` — keep vision methods, no changes needed
- `el-core/CHANGELOG.md` — add v1.19.1 entry
- `el-core/el-core.php` — bump version to 1.19.1
