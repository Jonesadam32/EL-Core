# Expand Site Color System
**Modern Tech Palette**

Complete color reference for the Expand Site client portal redesign.

---

## Brand Colors (Identity)

These define the "look and feel" of Expand Site.

| Color | Hex | Usage |
|-------|-----|-------|
| **Primary** | `#6366F1` | Main brand color - primary buttons, active states, stage highlights |
| **Primary Hover** | `#4F46E5` | Hover state for primary actions |
| **Primary Light** | `#818CF8` | Light backgrounds, subtle highlights |
| **Primary Pale** | `#E0E7FF` | Very light backgrounds, cards |
| **Secondary** | `#4F46E5` | Secondary buttons, supporting elements |
| **Accent** | `#06B6D4` | Call-to-action, important highlights, progress indicators |
| **Accent Hover** | `#0891B2` | Hover state for accent actions |
| **Accent Light** | `#22D3EE` | Light accents, notifications |
| **Accent Pale** | `#CFFAFE` | Very light backgrounds |

---

## Semantic Colors (Functional Feedback)

Universal meanings users understand instantly.

| Color | Hex | Usage |
|-------|-----|-------|
| **Success** | `#10B981` | Completed stages, approved deliverables, positive feedback |
| **Success Light** | `#D1FAE5` | Success backgrounds, completed stage cards |
| **Warning** | `#F59E0B` | Deadlines approaching, pending approvals, caution states |
| **Warning Light** | `#FEF3C7` | Warning backgrounds |
| **Error** | `#EF4444` | Overdue deadlines, errors, critical issues |
| **Error Light** | `#FEE2E2` | Error backgrounds |
| **Info** | `#3B82F6` | Informational messages, helper text, contributor role |
| **Info Light** | `#DBEAFE` | Info backgrounds |

---

## Neutral Colors (Text, Backgrounds, Borders)

The foundation that makes everything else work.

| Color | Hex | Usage |
|-------|-----|-------|
| **Gray 900** | `#111827` | Headings, primary text |
| **Gray 800** | `#1F2937` | Secondary headings |
| **Gray 700** | `#374151` | Body text |
| **Gray 600** | `#4B5563` | Secondary text |
| **Gray 500** | `#6B7280` | Helper text, placeholders |
| **Gray 400** | `#9CA3AF` | Disabled text |
| **Gray 300** | `#D1D5DB` | Borders, dividers |
| **Gray 200** | `#E5E7EB` | Light borders |
| **Gray 100** | `#F3F4F6` | Light backgrounds, alternate rows |
| **Gray 50** | `#F9FAFB` | Page backgrounds, cards |
| **White** | `#FFFFFF` | Card backgrounds, primary surfaces |

---

## Stage-Specific Colors

Each stage gets its own color for visual differentiation in the timeline.

| Stage | Name | Color | Hex | Usage |
|-------|------|-------|-----|-------|
| **1** | Qualification | Indigo | `#6366F1` | Primary brand |
| **2** | Discovery | Cyan | `#06B6D4` | Accent brand |
| **3** | Strategy | Purple | `#8B5CF6` | Planning phase |
| **4** | Design | Pink | `#EC4899` | Creative phase |
| **5** | Build | Orange | `#F97316` | Construction phase |
| **6** | Content | Amber | `#F59E0B` | Writing phase |
| **7** | Polish | Green | `#10B981` | Refinement phase |
| **8** | Launch | Blue | `#3B82F6` | Go-live phase |

**Usage Note:** Stage colors are for the timeline/progress bar only. Current stage content uses primary brand color for consistency.

---

## CSS Variables Implementation

```css
:root {
    /* Brand Colors */
    --es-primary: #6366F1;
    --es-primary-hover: #4F46E5;
    --es-primary-light: #818CF8;
    --es-primary-pale: #E0E7FF;
    --es-secondary: #4F46E5;
    --es-accent: #06B6D4;
    --es-accent-hover: #0891B2;
    --es-accent-light: #22D3EE;
    --es-accent-pale: #CFFAFE;
    
    /* Semantic Colors */
    --es-success: #10B981;
    --es-success-light: #D1FAE5;
    --es-warning: #F59E0B;
    --es-warning-light: #FEF3C7;
    --es-error: #EF4444;
    --es-error-light: #FEE2E2;
    --es-info: #3B82F6;
    --es-info-light: #DBEAFE;
    
    /* Neutral Colors */
    --es-gray-900: #111827;
    --es-gray-800: #1F2937;
    --es-gray-700: #374151;
    --es-gray-600: #4B5563;
    --es-gray-500: #6B7280;
    --es-gray-400: #9CA3AF;
    --es-gray-300: #D1D5DB;
    --es-gray-200: #E5E7EB;
    --es-gray-100: #F3F4F6;
    --es-gray-50: #F9FAFB;
    --es-white: #FFFFFF;
    
    /* Stage Colors */
    --es-stage-1: #6366F1; /* Qualification */
    --es-stage-2: #06B6D4; /* Discovery */
    --es-stage-3: #8B5CF6; /* Strategy */
    --es-stage-4: #EC4899; /* Design */
    --es-stage-5: #F97316; /* Build */
    --es-stage-6: #F59E0B; /* Content */
    --es-stage-7: #10B981; /* Polish */
    --es-stage-8: #3B82F6; /* Launch */
}
```

---

## Component Color Usage Guide

### Portal Header
- **Title:** `--es-gray-900`
- **Subtitle:** `--es-gray-600`
- **Decision Maker Badge:** `--es-success` background, white text
- **Contributor Badge:** `--es-info` background, white text

### Stage Navigation (Primary Element)
- **Current Stage Button:** `--es-primary` background, white text
- **Completed Stage:** `--es-success-light` background, `--es-success` border
- **Upcoming Stage:** `--es-gray-100` background, `--es-gray-400` text
- **Stage Number:** Uses stage-specific color from timeline

### Content Cards
- **Background:** `--es-white`
- **Border:** `--es-gray-200`
- **Hover Border:** `--es-primary-light`
- **Card Heading:** `--es-gray-900`
- **Card Text:** `--es-gray-700`

### Buttons
- **Primary Action:** `--es-primary` background, hover to `--es-primary-hover`
- **Secondary Action:** `--es-gray-100` background, `--es-gray-700` text
- **Destructive Action:** `--es-error` background
- **Disabled:** `--es-gray-400` background, `--es-gray-500` text

### Badges & Labels
- **Decision Maker:** `--es-success` background
- **Contributor:** `--es-info` background
- **Overdue:** `--es-error` background
- **Pending:** `--es-warning` background
- **Completed:** `--es-success` background

### Progress Indicators
- **Active Progress:** `--es-accent` (cyan for energy/movement)
- **Completed Progress:** `--es-success`
- **Incomplete Progress:** `--es-gray-300`

### Backgrounds
- **Page Background:** `--es-gray-50`
- **Card Background:** `--es-white`
- **Section Background:** `--es-gray-100` (alternate sections)
- **Hover Background:** `--es-primary-pale`

---

## Design Principles

1. **High Contrast:** Text must pass WCAG AA (4.5:1 ratio minimum)
2. **Consistent Hierarchy:** Darker = more important
3. **Color Meaning:** Never use color alone (include icons/text)
4. **Accessible States:** All interactive elements have clear hover/focus states
5. **Semantic First:** Use semantic colors for their intended purpose only

---

## Migration from Current Colors

| Old Variable | Old Color | New Variable | New Color |
|--------------|-----------|--------------|-----------|
| `--el-primary` | `#1a1a2e` | `--es-primary` | `#6366F1` |
| `--el-secondary` | `#16213e` | `--es-secondary` | `#4F46E5` |
| `--el-accent` | `#e94560` | `--es-accent` | `#06B6D4` |

**Note:** This is a module-specific palette. Core EL colors remain unchanged for other modules.

---

## File Updates Required

1. ✅ **This document** - Color reference (done)
2. ⏳ `expand-site.css` - Update all color values
3. ⏳ `expand-site-portal.php` - Remove emoji, add proper class structure
4. ⏳ Icon library integration (Feather Icons or Heroicons)

---

**Last Updated:** February 23, 2026  
**Status:** Ready for implementation
