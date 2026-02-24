# Cursor Build Prompt — Phase 2G: Branding Workflow Expansion
# Target Version: v1.19.0

> **Read before starting — in this order:**
> 1. `START-HERE-NEXT-SESSION.md`
> 2. `el-core-cursor-handoff.md`
> 3. `el-core-admin-build-rules.md`
> 4. This file
>
> **Last Updated:** February 23, 2026
> **Status:** Spec complete — ready to build
> **Prerequisite:** v1.18.11 deployed and tested ✅
> **Plugin source:** `C:\Github\EL Core\el-core\`
> **Build script:** `C:\Github\EL Core\build-zip.ps1`

---

## WHAT THIS PHASE DOES

Phase 2G expands the EL Core brand system from a basic 5-variable setup into a full professional branding workflow. It is **core infrastructure** — not a module, not inside Expand Site.

Three things change:

1. **`class-settings.php`** — new fields added to the `el_core_brand` settings group
2. **`class-asset-loader.php`** — CSS variable output expands from 5 tokens to ~25 (full design token set)
3. **`admin/views/settings-brand.php`** — complete rebuild of the Brand Settings admin page using `EL_Admin_UI::*` components, with two new capabilities: logo AI analysis and color wheel picking

After this phase, a site admin can upload a logo, get 3 AI-generated brand palette options, pick the one they want (or set colors manually), choose fonts, set brand voice — and the entire public-facing site updates automatically via CSS custom properties.

---

## ARCHITECTURE RULES FOR THIS PHASE

### This Is Core — Not a Module

All changes go in:
- `el-core/includes/class-settings.php`
- `el-core/includes/class-asset-loader.php`
- `el-core/admin/views/settings-brand.php`
- `el-core/admin/js/admin.js`
- `el-core/admin/css/admin.css` (minor additions only if needed)

Do NOT touch module files. Do NOT create new files in `modules/`.

### Two Color Systems — Never Mix Them

**Front-end brand colors** (`--el-primary`, `--el-accent`, etc.) — what this phase expands. Injected into the public site `<head>`. Customized per installation.

**Admin UI colors** (`--el-admin-navy`, `--el-admin-teal`, etc.) — fixed palette in `admin.css`. Never changes. Never affected by brand settings.

The new Pickr color wheel pickers in the admin UI are styled with `--el-admin-*` variables. The colors they *produce* become `--el-*` front-end tokens. These are completely separate concerns.

### Admin UI Components

Every form element on the rebuilt Brand Settings page uses `EL_Admin_UI::*` static methods. No raw HTML `<table>`, `<tr>`, `<td>`, or form elements outside the framework. This is the same rule that applies to all admin views in EL Core. Read `el-core-admin-build-rules.md` before writing a single line of PHP on this page.

---

## PART 1 — NEW SETTINGS FIELDS

### File: `el-core/includes/class-settings.php`

Add the following fields to the `el_core_brand` settings group. These are stored alongside the existing `primary_color`, `secondary_color`, `accent_color`, `font_heading`, `font_body`, and `logo_url` fields.

**New fields to add:**

```php
// Logo variants
'logo_variant_dark'       => '',     // URL — white-on-dark version of the logo
'logo_variant_light'      => '',     // URL — dark-on-light version of the logo
'favicon_url'             => '',     // URL — site favicon

// Dark mode intent
'dark_mode_preference'    => false,  // boolean — records intent only, no CSS generated yet

// AI palette workflow
'ai_palette_suggestions'  => '',     // JSON string — stores 3 AI-generated palette options
'palette_selected'        => null,   // int (0/1/2) — which AI option was chosen; null = manual

// Brand voice
'brand_tone'              => 'professional',  // string: professional|friendly|inspirational|bold|calm
'brand_audience'          => '',              // free text
'brand_values'            => '',             // textarea — 2-3 sentences
```

**Sanitization rules:**
- All URL fields: `esc_url_raw()`
- `dark_mode_preference`: cast to boolean
- `ai_palette_suggestions`: `wp_kses_post()` (it's JSON, preserve as-is)
- `palette_selected`: `absint()` or null
- `brand_tone`: `sanitize_key()`, validate against allowed list
- `brand_audience`: `sanitize_text_field()`
- `brand_values`: `sanitize_textarea_field()`

---

## PART 2 — CSS TOKEN EXPANSION

### File: `el-core/includes/class-asset-loader.php`

**Current behavior:** Outputs 5 CSS custom properties on every frontend page:
```css
--el-primary, --el-secondary, --el-accent, --el-font-heading, --el-font-body
```

**New behavior:** Output ~25 CSS custom properties covering brand colors, auto-generated variants, a neutral scale, semantic colors, and typography.

### New Method: `generate_full_token_set()`

Add this private method to `EL_Asset_Loader`. Call it from wherever the current CSS variable output happens.

```php
private function generate_full_token_set( array $brand ): array
```

**Input:** the `el_core_brand` settings array (primary, secondary, accent hex values + fonts)

**Output:** associative array of CSS variable name => value, covering everything below

**Calculations to implement:**

#### Brand color variants
For each of primary, secondary, accent:
- `--el-{color}-dark`: darken the hex by 12% (reduce HSL lightness by 12 points)
- `--el-{color}-text`: white (`#FFFFFF`) if luminance < 0.4, else near-black (`#1a1a1a`)

Luminance formula (relative luminance per WCAG):
```
Convert hex → RGB (0-1 range)
Each channel: if c <= 0.03928 then c/12.92 else ((c+0.055)/1.055)^2.4
Luminance = 0.2126*R + 0.7152*G + 0.0722*B
```

Darken formula:
```
Convert hex → HSL
Subtract 12 from L (clamp to 0)
Convert back to hex
```

#### Neutral scale
Derive from the primary color — desaturate it heavily (set HSL saturation to 8%), then step lightness:

| Variable | Lightness |
|----------|-----------|
| `--el-white` | Always `#FFFFFF` |
| `--el-bg` | 97% |
| `--el-border` | 88% |
| `--el-muted` | 60% |
| `--el-text` | 20% |
| `--el-dark` | 8% |

#### Semantic colors
Hue-shift the primary color to fixed hues, preserving approximate saturation and lightness:

| Variable | Target Hue |
|----------|-----------|
| `--el-success` | 145° (green) |
| `--el-warning` | 45° (amber) |
| `--el-error` | 5° (red) |
| `--el-info` | 210° (blue) |

Algorithm: take primary HSL, replace H with target hue, keep S (clamped to 50–70%), keep L (clamped to 40–55%), convert to hex.

#### Typography
```
--el-font-heading: {font_heading setting value}
--el-font-body: {font_body setting value}
```

### Full CSS Token List (Output)

```css
/* Brand */
--el-primary
--el-primary-dark
--el-primary-text

--el-secondary
--el-secondary-dark
--el-secondary-text

--el-accent
--el-accent-dark
--el-accent-text

/* Neutrals */
--el-white
--el-bg
--el-border
--el-muted
--el-text
--el-dark

/* Semantic */
--el-success
--el-warning
--el-error
--el-info

/* Typography */
--el-font-heading
--el-font-body
```

### Backward Compatibility

All existing CSS in `el-core.css` and `expand-site.css` uses `var(--el-primary)`, `var(--el-accent)`, etc. Those tokens still exist in the new output — they just have additional tokens alongside them. Nothing breaks.

---

## PART 3 — ADMIN BRAND SETTINGS PAGE REBUILD

### File: `el-core/admin/views/settings-brand.php`

Rebuild this file completely using `EL_Admin_UI::*` components. The existing page works but uses older raw HTML patterns. This rebuild serves two purposes: adds new functionality AND brings the page into compliance with the admin framework.

The page is structured into 5 sections, rendered in order. Each section uses `EL_Admin_UI::card()` as a wrapper with a section heading inside.

---

### Section 1 — Logo & Identity

**Fields:**

**Primary Logo**
- Existing media uploader button (keep current pattern — it already works)
- Preview of current logo if set
- Helper text: "Used in the site header and email templates"

**Logo — Dark Background Variant**
- URL field + media uploader button
- Helper text: "White or light version of your logo for dark backgrounds"

**Logo — Light Background Variant**
- URL field + media uploader button
- Helper text: "Dark version of your logo for light or white backgrounds"

**Favicon**
- URL field + media uploader button
- Helper text: "Square image, 512×512px recommended. Appears in browser tabs."

All four fields use `EL_Admin_UI::form_row()`.

---

### Section 2 — Brand Colors

This is the most complex section. It has two paths: **AI Analysis** and **Manual Picker**. A radio toggle switches between them. Both paths ultimately produce the same output: three hex values stored as `primary_color`, `secondary_color`, `accent_color` in settings.

#### Path Toggle

```
( ) Analyze My Logo   (•) Pick My Own Colors
```

Radio input at the top of the section. The selected path shows; the other hides. Default to "Pick My Own Colors" if no AI suggestions are stored, or "Analyze My Logo" if suggestions are already in `ai_palette_suggestions`.

---

#### Path A — Logo AI Analysis

**Step 1: Upload or use existing logo**
- If a logo is already set in Section 1, show it here with a note: "Using logo from above."
- Option to upload a different image specifically for analysis (some logos need a cleaner version)
- "Analyze Logo" button (calls `el_analyze_logo` AJAX handler)

**Loading state:** While AI processes, show a spinner and text: "Analyzing your logo…"

**Step 2: Palette options display**
After AI responds, render 3 palette option cards side by side. Each card shows:
- Three color circles: primary (large), secondary (medium), accent (small)
- Heading font name
- Body font name
- Rationale (1 sentence from AI)
- "Use This Palette" button

"Use This Palette" click:
- Populates the 3 Pickr color pickers (Path B) with the selected palette's hex values
- Saves `palette_selected` index (0, 1, or 2)
- Switches the view to Path B so the user can see/adjust the colors they just selected
- Palette cards stay visible above the pickers (collapsed or dimmed) so user can reference them

If `ai_palette_suggestions` already has data (from a previous analysis), show the 3 cards immediately on page load without needing to re-analyze.

---

#### Path B — Manual Color Picker

Three color pickers using **Pickr JS library**:
- Primary Color
- Secondary Color
- Accent Color

**Pickr configuration:**
- CDN: `https://cdnjs.cloudflare.com/ajax/libs/pickr/1.8.4/pickr.min.js`
- CSS: `https://cdnjs.cloudflare.com/ajax/libs/pickr/1.8.4/themes/classic.min.css`
- Theme: `classic`
- Components: `hue: true, opacity: false, interaction: { hex: true, input: true, save: true }`
- Default value: current saved hex from settings, or a sensible default (`#3B82F6`, `#1E40AF`, `#F59E0B`)

**Live semantic preview:**
Below the 3 pickers, show a read-only preview strip of the 4 semantic colors (success, warning, error, info) as colored swatches with labels. These update in real time as any color picker changes — calculate the semantic colors client-side using the same hue-shift logic as the PHP `generate_full_token_set()`. This gives the user immediate visual feedback on how their brand color choice affects the system palette.

**Label:** "System colors — auto-generated from your brand palette. These are used for success messages, warnings, and alerts."

---

### Section 3 — Typography

**Heading Font**
- `<select>` dropdown with these options:
  - Inter
  - Poppins
  - Montserrat
  - Raleway
  - Playfair Display
  - Merriweather
  - Lora
  - Source Serif Pro
- Below the select: free-text input field labeled "Or enter a custom font name"
- Helper text: "If using a custom font, make sure it's loaded by your theme."

**Body Font**
- Same select options
- Same free-text fallback field
- Helper text: "Used for paragraphs and general content text."

Both fields use `EL_Admin_UI::form_row()`.

---

### Section 4 — Brand Voice

These fields feed future AI generation (proposals, content, etc.) with tone context. No functionality built on them yet — just collect and save.

**Tone**
- `<select>` dropdown: Professional | Friendly | Inspirational | Bold | Calm
- Default: Professional
- Helper text: "How should your written content sound to your audience?"

**Target Audience**
- Text input
- Placeholder: "K-12 educators and district administrators"
- Helper text: "Describe who you're primarily speaking to."

**Brand Values**
- Textarea (3 rows)
- Placeholder: "2–3 sentences describing what your organization stands for and what makes you different."
- Helper text: "Used to guide AI-generated content to match your voice."

All three use `EL_Admin_UI::form_row()`.

---

### Section 5 — Dark Mode

**Dark Mode Support**
- Single checkbox: "This installation should support dark mode"
- Helper text: "Records your preference for future dark theme support. No dark styles are generated yet — this setting will be used in a future update."

This is intentionally minimal. One checkbox, one explanation. Do not build dark CSS. Do not generate dark tokens. Just save the boolean.

---

### Save Button

Standard WordPress Settings API save button at the bottom of the page. All fields submit together via the existing `el_core_brand` option group. The Settings API handles the save — no custom AJAX needed for form submission.

---

## PART 4 — AJAX HANDLERS

### File: `el-core/includes/class-ajax-handler.php` or hooked from core boot

Both handlers are **authenticated only** (admin-only actions). No `nopriv` variant needed.

---

### Handler: `el_analyze_logo`

**Triggered by:** "Analyze Logo" button on brand settings page

**Request payload:**
```js
{
  el_action: 'el_analyze_logo',
  nonce: elCore.nonce,
  logo_url: 'https://...'   // URL of image to analyze
}
```

**PHP handler steps:**

1. Verify nonce and `manage_options` capability
2. Validate `logo_url` — must be a valid URL
3. Fetch the image and convert to base64 (use `wp_remote_get()` + `base64_encode()`)
4. Detect MIME type from URL extension or response headers
5. Call `el_core_ai_complete()` (or `EL_AI_Client` directly) with:
   - A vision-capable model (claude-3-5-sonnet or equivalent — check what's configured)
   - Image as base64 in the message content
   - System prompt: see below
6. Parse the JSON response from AI
7. Save raw JSON to `el_core_brand.ai_palette_suggestions` via settings
8. Return the 3 parsed options to JS

**AI prompt (system):**
```
You are a professional brand designer. Analyze the provided logo image and generate exactly 3 distinct brand palette options that would complement it. Each option must feel cohesive with the logo's existing colors and aesthetic.

Return ONLY valid JSON — no markdown, no code fences, no explanation. The response must be parseable by json_decode() with nothing before or after it.

JSON format:
{
  "options": [
    {
      "primary": "#hex",
      "secondary": "#hex",
      "accent": "#hex",
      "font_heading": "Font Name",
      "font_body": "Font Name",
      "rationale": "One sentence explaining the aesthetic direction."
    },
    { ... },
    { ... }
  ]
}

Rules:
- primary: dominant brand color (used for buttons, headings, key UI elements)
- secondary: supporting color (used for backgrounds, cards, secondary UI)
- accent: highlight color (used for CTAs, badges, links)
- Ensure strong contrast between colors for WCAG AA accessibility
- Font names must be real Google Fonts or system fonts
- Each option should represent a meaningfully different aesthetic direction
- Do not repeat the same hex values across options
```

**Error handling:**
- If AI returns malformed JSON: try stripping markdown fences (` ```json `, ` ``` `) then parse again
- If still invalid: return error to JS with message "AI response was not valid JSON. Please try again."
- If AI call fails: return WP error message
- If image can't be fetched: return "Could not load image from URL. Try uploading the image to the media library first."

**Success response:**
```json
{
  "success": true,
  "options": [
    { "primary": "#...", "secondary": "#...", "accent": "#...", "font_heading": "...", "font_body": "...", "rationale": "..." },
    { ... },
    { ... }
  ]
}
```

---

### Handler: `el_save_brand_selection`

**Triggered by:** "Use This Palette" button on a palette card

**Request payload:**
```js
{
  el_action: 'el_save_brand_selection',
  nonce: elCore.nonce,
  palette_index: 0,          // int: which AI option (0, 1, or 2)
  primary: '#hex',
  secondary: '#hex',
  accent: '#hex'
}
```

**PHP handler steps:**
1. Verify nonce and `manage_options` capability
2. Sanitize hex values — validate format (`#` + 6 hex chars)
3. Save `primary_color`, `secondary_color`, `accent_color` to `el_core_brand`
4. Save `palette_selected` index to `el_core_brand`
5. Return success

**Success response:**
```json
{ "success": true }
```

Note: The full settings save (including fonts, voice, etc.) still goes through the Settings API form submit. This handler only saves color selection from the AI palette cards — it's a convenience shortcut, not a replacement for the main save.

---

## PART 5 — JAVASCRIPT

### File: `el-core/admin/js/admin.js`

Add all new brand page JS to this file. Keep in `elAdmin.*` namespace. All new code should be scoped to only run when the brand settings page is active (check for a page-specific element on init).

#### Pickr initialization

```js
elAdmin.initBrandColorPickers = function() {
    const pickerConfigs = [
        { el: '#el-color-primary', settingKey: 'primary_color' },
        { el: '#el-color-secondary', settingKey: 'secondary_color' },
        { el: '#el-color-accent', settingKey: 'accent_color' }
    ];

    pickerConfigs.forEach(config => {
        const picker = Pickr.create({
            el: config.el,
            theme: 'classic',
            default: document.querySelector(`input[name="${config.settingKey}"]`).value || '#3B82F6',
            components: {
                preview: true,
                hue: true,
                interaction: { hex: true, input: true, save: true }
            }
        });

        picker.on('save', (color) => {
            const hex = color.toHEXA().toString();
            document.querySelector(`input[name="${config.settingKey}"]`).value = hex;
            elAdmin.updateSemanticPreview();
            picker.hide();
        });
    });
};
```

#### Semantic color preview (live update)

```js
elAdmin.updateSemanticPreview = function() {
    const primary = document.querySelector('input[name="primary_color"]').value;
    if (!primary || primary.length !== 7) return;

    const semanticColors = elAdmin.calculateSemanticColors(primary);

    document.querySelector('.el-semantic-preview-success').style.backgroundColor = semanticColors.success;
    document.querySelector('.el-semantic-preview-warning').style.backgroundColor = semanticColors.warning;
    document.querySelector('.el-semantic-preview-error').style.backgroundColor = semanticColors.error;
    document.querySelector('.el-semantic-preview-info').style.backgroundColor = semanticColors.info;
};

elAdmin.calculateSemanticColors = function(primaryHex) {
    // Convert primary to HSL, then hue-shift to semantic hues
    // Target hues: success=145, warning=45, error=5, info=210
    // Keep S clamped 50-70%, L clamped 40-55%
    const hsl = elAdmin.hexToHSL(primaryHex);
    return {
        success: elAdmin.hslToHex(145, Math.min(70, Math.max(50, hsl.s)), Math.min(55, Math.max(40, hsl.l))),
        warning: elAdmin.hslToHex(45,  Math.min(70, Math.max(50, hsl.s)), Math.min(55, Math.max(40, hsl.l))),
        error:   elAdmin.hslToHex(5,   Math.min(70, Math.max(50, hsl.s)), Math.min(55, Math.max(40, hsl.l))),
        info:    elAdmin.hslToHex(210, Math.min(70, Math.max(50, hsl.s)), Math.min(55, Math.max(40, hsl.l)))
    };
};
```

Include `hexToHSL()` and `hslToHex()` helper functions. Standard implementations — nothing custom.

#### Path toggle (AI vs Manual)

```js
elAdmin.initColorPathToggle = function() {
    const radios = document.querySelectorAll('input[name="el_color_path"]');
    radios.forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelector('.el-color-path-ai').style.display =
                radio.value === 'ai' ? 'block' : 'none';
            document.querySelector('.el-color-path-manual').style.display =
                radio.value === 'manual' ? 'block' : 'none';
        });
    });
};
```

#### Logo analysis handler

```js
elAdmin.handleAnalyzeLogo = function() {
    const logoUrl = document.querySelector('input[name="logo_url"]').value;
    if (!logoUrl) {
        alert('Please upload a logo first.');
        return;
    }

    const btn = document.querySelector('#el-analyze-logo-btn');
    const resultsArea = document.querySelector('.el-palette-results');

    btn.disabled = true;
    btn.textContent = 'Analyzing…';
    resultsArea.innerHTML = '<p class="el-analyzing-msg">Analyzing your logo…</p>';

    elAdmin.ajax('el_analyze_logo', { logo_url: logoUrl }, function(response) {
        btn.disabled = false;
        btn.textContent = 'Analyze Logo';

        if (response.success) {
            elAdmin.renderPaletteOptions(response.options);
        } else {
            resultsArea.innerHTML = `<p class="el-error-msg">${response.message || 'Analysis failed. Please try again.'}</p>`;
        }
    });
};
```

#### Palette card rendering

```js
elAdmin.renderPaletteOptions = function(options) {
    const resultsArea = document.querySelector('.el-palette-results');
    resultsArea.innerHTML = '';

    const labels = ['Option A', 'Option B', 'Option C'];

    options.forEach((option, index) => {
        const card = document.createElement('div');
        card.className = 'el-palette-card';
        card.innerHTML = `
            <div class="el-palette-swatches">
                <span class="el-swatch el-swatch-primary" style="background:${option.primary}" title="Primary: ${option.primary}"></span>
                <span class="el-swatch el-swatch-secondary" style="background:${option.secondary}" title="Secondary: ${option.secondary}"></span>
                <span class="el-swatch el-swatch-accent" style="background:${option.accent}" title="Accent: ${option.accent}"></span>
            </div>
            <strong>${labels[index]}</strong>
            <p class="el-palette-rationale">${option.rationale}</p>
            <p class="el-palette-fonts">${option.font_heading} / ${option.font_body}</p>
            <button type="button" class="button el-use-palette-btn" data-index="${index}"
                data-primary="${option.primary}"
                data-secondary="${option.secondary}"
                data-accent="${option.accent}">
                Use This Palette
            </button>
        `;
        resultsArea.appendChild(card);
    });

    // Bind "Use This Palette" buttons
    document.querySelectorAll('.el-use-palette-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            elAdmin.applyPaletteSelection(
                parseInt(btn.dataset.index),
                btn.dataset.primary,
                btn.dataset.secondary,
                btn.dataset.accent
            );
        });
    });
};

elAdmin.applyPaletteSelection = function(index, primary, secondary, accent) {
    // Update hidden inputs (Settings API picks these up on save)
    document.querySelector('input[name="primary_color"]').value = primary;
    document.querySelector('input[name="secondary_color"]').value = secondary;
    document.querySelector('input[name="accent_color"]').value = accent;

    // Save selection index via AJAX
    elAdmin.ajax('el_save_brand_selection', {
        palette_index: index,
        primary: primary,
        secondary: secondary,
        accent: accent
    }, function(response) {
        if (response.success) {
            // Switch to manual path view so user sees the populated pickers
            document.querySelector('input[name="el_color_path"][value="manual"]').click();
            elAdmin.updateSemanticPreview();
        }
    });
};
```

---

## PART 6 — FILES TOUCHED

| File | Change Type | Description |
|------|-------------|-------------|
| `el-core/includes/class-settings.php` | Modify | Add new fields + sanitization to `el_core_brand` group |
| `el-core/includes/class-asset-loader.php` | Modify | Add `generate_full_token_set()`, expand CSS variable output |
| `el-core/admin/views/settings-brand.php` | Full rebuild | 5 sections using `EL_Admin_UI::*`, Pickr pickers, palette cards |
| `el-core/admin/js/admin.js` | Add | Pickr init, path toggle, logo analysis, palette rendering, semantic preview |
| `el-core/admin/css/admin.css` | Minor | Palette card styles, swatch styles, analyzing spinner — all `--el-admin-*` variables only |
| `el-core/el-core.php` | Modify | Version bump to v1.19.0 |
| `CHANGELOG.md` | Modify | New v1.19.0 entry |

**Do NOT modify:**
- Any module files
- `el-core.css` (frontend styles — only touched if a new `--el-*` token needs a default fallback)
- `class-el-core.php` (no new boot steps needed)

---

## PART 7 — BUILD ORDER

Build in this sequence. Deploy and test each step before continuing to the next.

### Step 1 — Settings fields only (no UI changes)

- Add all new fields to `class-settings.php`
- Add sanitization for all new fields
- No UI changes yet
- Deploy, verify settings page still loads and saves without errors
- **Test:** Save settings form — confirm no PHP errors, existing fields still save correctly

### Step 2 — CSS token expansion

- Add `generate_full_token_set()` to `class-asset-loader.php`
- Replace current 5-variable output with full token set output
- Deploy, open browser inspector on the frontend
- **Test:** Verify all ~25 `--el-*` variables appear in `<head>` style block
- **Test:** Verify existing module CSS still renders correctly (no broken layout or missing colors)
- **Test:** Confirm `--el-primary`, `--el-accent` etc. still exist (backward compatible — not renamed)
- **Test:** Verify `--el-primary-dark` is visually darker than `--el-primary` in inspector
- **Test:** Verify semantic colors look like green/amber/red/blue respectively

### Step 3 — Brand settings page rebuild

- Rebuild `admin/views/settings-brand.php` using `EL_Admin_UI::*`
- All 5 sections: Logo, Brand Colors (both paths), Typography, Brand Voice, Dark Mode
- No Pickr yet — use plain text inputs for color fields in this step
- No AJAX yet — just the form structure saving via Settings API
- Deploy
- **Test:** All existing fields (primary color, secondary, accent, fonts, logo) still save and reload correctly
- **Test:** New fields (logo variants, favicon, brand voice, dark mode) save and reload correctly
- **Test:** Page looks correct — no raw HTML leaking outside components

### Step 4 — Pickr color pickers

- Enqueue Pickr JS and CSS from CDN on brand settings page only
- Replace plain color text inputs with Pickr-powered pickers
- Add semantic preview strip (read-only swatches, updates live on color change)
- Deploy
- **Test:** Open brand settings — 3 color pickers render correctly
- **Test:** Pick a new primary color — semantic preview strip updates live
- **Test:** Save settings — hex values save to database correctly
- **Test:** Reload page — pickers initialize with saved color values

### Step 5 — Logo AI analysis

- Add `el_analyze_logo` AJAX handler
- Add `el_save_brand_selection` AJAX handler
- Add path toggle (AI vs Manual radio)
- Add "Analyze Logo" button and palette card rendering JS
- Deploy
- **Test:** Upload a logo, click Analyze Logo — 3 palette cards appear with correct colors and rationale
- **Test:** Click "Use This Palette" on a card — 3 color pickers update with selected colors
- **Test:** Palette cards are still visible after selection (for reference)
- **Test:** Save settings — selected palette colors persist
- **Test:** Reload page — previously generated palette cards re-display from saved `ai_palette_suggestions`
- **Test:** AI error handling — try with a non-image URL, verify graceful error message

### Step 6 — Final version bump and changelog

- Bump version to v1.19.0 in both plugin header and `EL_CORE_VERSION` constant
- Update `CHANGELOG.md`
- Build ZIP
- **Checkpoint F: Fred tests full branding workflow end-to-end ✅**

---

## TESTING CHECKLIST (Full)

- [ ] Upload logo → primary logo saves and previews
- [ ] Upload dark/light logo variants → save correctly
- [ ] Upload favicon → saves correctly
- [ ] Open brand settings → 3 Pickr color pickers render
- [ ] Change primary color → semantic preview strip updates live
- [ ] Save settings → reload → all color values persist
- [ ] Frontend: browser inspector shows all ~25 `--el-*` variables in `<head>`
- [ ] Frontend: `--el-primary-dark` is visibly darker than `--el-primary`
- [ ] Frontend: semantic colors are recognizably green / amber / red / blue
- [ ] Existing Expand Site module CSS renders correctly (no broken variables)
- [ ] Click Analyze Logo → loading state shows → 3 palette cards appear
- [ ] Palette cards show correct color swatches, rationale, font names
- [ ] Click "Use This Palette" → pickers update → switching to manual path shows populated pickers
- [ ] Save after palette selection → selected colors persist in database
- [ ] Reload brand settings → saved palette cards re-display without re-analyzing
- [ ] Switch to manual path → pick 3 colors manually → save → reload → correct values
- [ ] Typography: select heading and body fonts → save → reload → correct values
- [ ] Custom font free-text field → saves and reloads correctly
- [ ] Brand voice fields (tone, audience, values) → save and reload correctly
- [ ] Dark mode toggle → save as checked → reload → still checked
- [ ] Version shows v1.19.0 in WordPress admin → Plugins

---

## VERSION

**Target:** v1.19.0
**Type:** MINOR (new features, backward compatible)
**CHANGELOG entry format:**

```
## [1.19.0] — 2026-02-XX
### Added
- Brand settings: logo variant fields (dark, light, favicon)
- Brand settings: dark mode preference toggle
- Brand settings: AI palette suggestions storage
- Brand settings: brand voice fields (tone, audience, values)
- Asset loader: generate_full_token_set() — expands CSS output from 5 to 25 tokens
- CSS tokens: --el-primary-dark, --el-primary-text (and secondary/accent variants)
- CSS tokens: full neutral scale (--el-white, --el-bg, --el-border, --el-muted, --el-text, --el-dark)
- CSS tokens: semantic colors (--el-success, --el-warning, --el-error, --el-info)
- Brand settings page: rebuilt using EL_Admin_UI framework
- Brand settings page: Pickr JS color wheel pickers for primary/secondary/accent
- Brand settings page: live semantic color preview strip
- Brand settings page: AI logo analysis with 3-palette output
- Brand settings page: path toggle (AI analysis vs manual picker)
- AJAX handler: el_analyze_logo — sends logo to Claude vision API, returns 3 palette options
- AJAX handler: el_save_brand_selection — saves AI palette selection index

### Changed
- Brand settings page HTML rebuilt from raw markup to EL_Admin_UI components
- CSS variable output now generates full design token set (was 5 variables, now 25)

### Fixed
- (none)
```