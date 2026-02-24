# EL Core Design Rules

> **Critical design constraints that must be followed across all modules.**

---

## Icons

### ❌ FORBIDDEN: Emoji Icons
- NO emoji icons anywhere (📍📄💬👥📋🚀❌✅)
- Emojis look unprofessional and childish
- Inconsistent rendering across browsers/OS
- Not brand-appropriate for B2B software

### ✅ REQUIRED: Line Icons or SVG
- Use outline/stroke icons (not solid fills)
- Recommended icon libraries:
  - **Heroicons** (Tailwind's icon set)
  - **Feather Icons** (clean, minimal)
  - **Font Awesome** (line/regular style only)
  - **Lucide** (Feather fork with more icons)
  
### Icon Implementation
```html
<!-- Good: Inline SVG -->
<svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
  <path d="M9 5l7 7-7 7"/>
</svg>

<!-- Good: Icon font (Font Awesome) -->
<i class="far fa-file"></i>

<!-- Bad: Emoji -->
📄 ← NEVER USE
```

### Icon Styling
- Stroke width: 1.5-2px
- Size: 20-24px for UI elements
- Color: Inherit from parent (use currentColor)
- Hover: Slight scale or color change

---

## Color Palette

### Brand Colors (from EL Core settings)
- Primary: `--el-primary` (default: #1a1a2e)
- Accent: `--el-accent` (default: #e94560)
- Text: `--el-text` (default: #333)
- Background: `--el-bg` (default: #fff)

### Semantic Colors
- Success: #10b981 (green)
- Warning: #f59e0b (yellow/orange)
- Error: #ef4444 (red)
- Info: #3b82f6 (blue)

### Neutral Palette
- Gray 50: #f9fafb
- Gray 100: #f3f4f6
- Gray 300: #d1d5db
- Gray 500: #6b7280
- Gray 700: #374151
- Gray 900: #111827

---

## Typography

### Font Stack
```css
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
```

### Scale
- Hero: 2rem (32px)
- H1: 1.5rem (24px)
- H2: 1.25rem (20px)
- H3: 1.125rem (18px)
- Body: 1rem (16px)
- Small: 0.875rem (14px)
- Tiny: 0.75rem (12px)

### Weight
- Light: 300
- Regular: 400
- Medium: 500
- Semibold: 600
- Bold: 700

### Line Height
- Headings: 1.2
- Body: 1.6
- UI labels: 1.4

---

## Spacing

### Scale (Tailwind-inspired)
- 0.5rem (8px)
- 1rem (16px)
- 1.5rem (24px)
- 2rem (32px)
- 2.5rem (40px)
- 3rem (48px)

### Component Padding
- Card: 1.5rem
- Button: 0.5rem 1rem
- Input: 0.5rem 0.75rem
- Modal: 2rem

---

## Buttons

### Variants
- Primary: Brand color bg, white text
- Secondary: Gray border, dark text
- Ghost: Transparent, hover bg
- Danger: Red bg, white text

### States
- Default: Base color
- Hover: Slightly darker/lighter + shadow
- Active: Pressed state (inset shadow)
- Disabled: 50% opacity + no pointer

### Sizes
- Small: padding 0.375rem 0.75rem
- Medium: padding 0.5rem 1rem
- Large: padding 0.75rem 1.5rem

---

## Cards

### Style
- Background: White
- Border: 1px solid #e5e7eb
- Border radius: 8-12px
- Shadow: Subtle (0 1px 3px rgba(0,0,0,0.1))
- Hover: Lift shadow (0 4px 12px rgba(0,0,0,0.1))

### Padding
- Default: 1.5rem
- Compact: 1rem
- Spacious: 2rem

---

## Forms

### Inputs
- Height: 40px minimum
- Border: 1px solid #d1d5db
- Border radius: 6px
- Focus: Brand color border + ring
- Error: Red border

### Labels
- Font weight: 500
- Margin bottom: 0.5rem
- Required indicator: Red asterisk

---

## Mobile Breakpoints

```css
/* Mobile first approach */
@media (min-width: 640px) { /* sm */ }
@media (min-width: 768px) { /* md */ }
@media (min-width: 1024px) { /* lg */ }
@media (min-width: 1280px) { /* xl */ }
```

---

## Animation

### Timing
- Fast: 150ms (hover states)
- Normal: 300ms (transitions)
- Slow: 500ms (complex animations)

### Easing
- Default: ease-in-out
- Enter: ease-out
- Exit: ease-in

---

## Accessibility

### Requirements
- Color contrast: WCAG AA minimum (4.5:1)
- Focus indicators: Visible on all interactive elements
- Keyboard navigation: Full support
- Screen readers: Proper ARIA labels
- Touch targets: 44x44px minimum

---

## Code Conventions

### CSS Classes
- Prefix: `el-` for shared, `el-es-` for Expand Site
- BEM naming: `el-card`, `el-card__header`, `el-card--primary`
- Utility classes: Avoid (use semantic classes)

### HTML Structure
- Semantic HTML5 elements
- Proper heading hierarchy (h1 → h2 → h3)
- Lists for lists, not divs
- Buttons for actions, links for navigation

---

## What NOT to Do

❌ Emoji icons (📍📄💬)
❌ Inline styles (use CSS classes)
❌ !important (fix specificity instead)
❌ Absolute positioning (use flex/grid)
❌ Fixed pixel widths (use %, rem, or max-width)
❌ Comic Sans or decorative fonts
❌ Overly bright neon colors
❌ Animations longer than 500ms
❌ Non-semantic div soup

---

**Remember**: Professional, clean, accessible, purposeful. Every design decision should serve the user's needs, not just "look pretty."
