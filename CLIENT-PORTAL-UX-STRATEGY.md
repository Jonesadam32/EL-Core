# Client Portal UX Strategy
**Expand Site v1.14.0 Redesign**

This document explains the UX design decisions for the client portal redesign.

---

## The Problem with v1.13.1

The v1.13.1 portal was **functional but had amateur UX**:

### Critical Issues

1. **Timeline Buried at Bottom**
   - The 8-stage progress bar was hidden at the bottom of the page
   - Clients had to scroll past all content to see where they were in the process
   - Progress indicator should be the PRIMARY navigation element

2. **No Progressive Disclosure**
   - Everything shown at once (description, definition, stakeholders, deliverables, feedback)
   - Information overload with no clear focus
   - Users didn't know what to pay attention to

3. **Poor Information Architecture**
   - Content wasn't organized by stage relevance
   - No stage-specific content viewer
   - Missing the "wizard" pattern users expect from multi-step processes

4. **Weak Visual Hierarchy**
   - Everything had equal visual weight
   - No clear "what matters NOW" signal
   - Stats grid competed with actual content

5. **Emoji Icons Everywhere**
   - 📍📄💬👥📋🚀 used throughout
   - Unprofessional, inconsistent rendering across devices
   - Fred specifically hates emoji in interfaces

6. **Generic Colors**
   - Dark navy/pink palette didn't convey "site building"
   - No stage-specific visual identity
   - Felt generic, not distinctive

---

## UX Principles for Redesign

### 1. **Progressive Disclosure**
> Show only what's relevant NOW. Hide complexity until needed.

**Applied:**
- Stage navigation is primary element at top
- Click a stage → see ONLY that stage's content
- Default view shows current stage only
- Past/future stages accessible but not in the way

### 2. **Clear Visual Hierarchy**
> Most important thing should be most obvious thing.

**Applied:**
- Stage navigation dominates the header
- Current stage is visually distinct (primary color, larger)
- Section headings use consistent sizing/weight
- Content cards have clear hierarchy (title → meta → actions)

### 3. **Wizard/Stepper Pattern**
> Multi-step processes should feel like a journey with clear progress.

**Applied:**
- Horizontal stage navigation (like checkout flows, onboarding)
- Visual progress indicators (completed, current, upcoming states)
- Click any stage to jump to it (no forced linearity)
- Clear completion states

### 4. **Context Over Content**
> Show what matters for THIS stage, not everything ever.

**Applied:**
- Stage-specific content sections
- Deliverables filtered by selected stage
- Feedback filtered by selected stage
- Global content (team, definition) in separate tab/section

### 5. **Affordances**
> Make interactive elements obviously clickable.

**Applied:**
- Stage buttons have clear hover states
- Button styling follows conventions (primary = action, secondary = info)
- Icons paired with labels for clarity
- Disabled states clearly communicated

---

## New Information Architecture

### Layout Structure

```
┌─────────────────────────────────────────────────────┐
│ PROJECT HEADER                                      │
│ Name, Subtitle, Role Badge                         │
└─────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────┐
│ STAGE NAVIGATION (Primary Element)                 │
│ [1] [2] [3] [4] [5] [6] [7] [8]                   │
│  ✓   ✓   ●   ○   ○   ○   ○   ○                    │
└─────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────┐
│ STAGE CONTENT AREA                                  │
│                                                     │
│ [Selected Stage Details]                           │
│ - Stage description                                │
│ - Status & deadline info                           │
│ - Deliverables for THIS stage                      │
│ - Feedback for THIS stage                          │
│                                                     │
└─────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────┐
│ GLOBAL INFORMATION (Collapsible/Secondary)         │
│ - Project Definition (if locked)                   │
│ - Project Team (stakeholders)                      │
│ - Project Notes/Description                        │
└─────────────────────────────────────────────────────┘
```

### Content Organization

**Stage-Specific Content** (filtered by selected stage):
- Stage name and description
- Status badge (active, completed, upcoming)
- Deadline information (if set)
- Deliverables for this stage
- Feedback items for this stage
- Stage-specific actions (approve, comment, etc.)

**Global Content** (always visible but secondary):
- Project definition (what we're building)
- Team members (who's involved)
- Project notes (overview)

**Removed from View:**
- Stats grid (redundant with stage navigation)
- "Current Stage Deliverables" heading (obvious from context)
- Full deliverable list (filtered by stage now)

---

## Stage Navigation Design

### Visual States

1. **Completed Stage**
   - ✓ Checkmark icon
   - Green background (`--es-success-light`)
   - Green border (`--es-success`)
   - Muted text color
   - Clickable (can revisit)

2. **Current Stage**
   - Stage number in circle
   - Primary color background (`--es-primary`)
   - White text
   - Slightly larger/elevated
   - Bold label
   - Active by default

3. **Upcoming Stage**
   - Stage number in circle
   - Light gray background (`--es-gray-100`)
   - Gray text (`--es-gray-400`)
   - Not clickable (or clickable with preview mode)

### Interaction

- **Click completed stage** → Load that stage's content (see past work)
- **Click current stage** → Highlight current work
- **Click upcoming stage** → Show preview/coming soon message
- **Hover any stage** → Tooltip with stage name

### Responsive Behavior

- **Desktop:** Horizontal row (8 stages side-by-side)
- **Tablet:** Horizontal row with smaller text
- **Mobile:** Horizontal scroll or 2 rows of 4

---

## Icon System

### Replaced Emoji with SVG Line Icons

**Old (v1.13.1):**
- 📍 Stage/location
- 📄 Deliverables
- 💬 Feedback
- 👥 Team
- 📋 Definition
- 🚀 Timeline

**New (v1.14.0):**
- All inline SVG icons from Feather Icons set
- Consistent 24px size
- 2px stroke weight
- Monochrome (colored via CSS)
- Professional, clean aesthetic

### Icon Usage Guide

| Element | Icon | Feather Name |
|---------|------|--------------|
| Current stage indicator | Circle with number | `circle` |
| Completed stage | Checkmark | `check-circle` |
| Deliverables | Document | `file-text` |
| Feedback | Message bubble | `message-circle` |
| Team | Users | `users` |
| Definition | Clipboard | `clipboard` |
| Calendar/Deadline | Calendar | `calendar` |
| Status | Activity pulse | `activity` |
| Info notices | Info circle | `info` |
| Warnings | Alert triangle | `alert-triangle` |
| Success | Check circle | `check-circle` |
| Download | Download | `download` |
| External link | External link | `external-link` |

---

## Color Application

### Stage Navigation
- **Current stage background:** `--es-primary` (#6366F1 - Indigo)
- **Completed stage background:** `--es-success-light` (#D1FAE5 - Light green)
- **Upcoming stage background:** `--es-gray-100` (#F3F4F6 - Light gray)

### Content Cards
- **Card background:** `--es-white`
- **Card border:** `--es-gray-200`
- **Hover border:** `--es-primary-light`

### Badges
- **Decision Maker:** `--es-success` background
- **Contributor:** `--es-info` background
- **Active status:** `--es-primary` background
- **Completed status:** `--es-success` background
- **Overdue warning:** `--es-error` background
- **Pending warning:** `--es-warning` background

### Buttons
- **Primary action:** `--es-primary` background
- **Secondary action:** `--es-gray-100` background, `--es-gray-700` text
- **Destructive action:** `--es-error` background

### Stage Timeline (8-color palette)
Each stage gets its own color in the mini-timeline visualization:
1. Qualification: Indigo
2. Discovery: Cyan
3. Strategy: Purple
4. Design: Pink
5. Build: Orange
6. Content: Amber
7. Polish: Green
8. Launch: Blue

---

## Typography Hierarchy

### Headings
1. **Page Title (Project Name):** 2rem (32px), 600 weight, `--es-gray-900`
2. **Section Heading:** 1.25rem (20px), 600 weight, `--es-gray-900`
3. **Card Title:** 1rem (16px), 600 weight, `--es-gray-800`
4. **Label:** 0.875rem (14px), 500 weight, `--es-gray-600`

### Body Text
- **Primary body:** 1rem (16px), 400 weight, `--es-gray-700`
- **Secondary/meta:** 0.875rem (14px), 400 weight, `--es-gray-600`
- **Helper/hint:** 0.875rem (14px), 400 weight, `--es-gray-500`

### Interactive Text
- **Links:** `--es-primary`, underline on hover
- **Buttons:** Varies by button type
- **Badges:** 0.75rem (12px), 600 weight, uppercase

---

## Responsive Breakpoints

```css
/* Mobile First */
.el-es-portal { 
    /* Base styles: single column, stacked */
}

/* Tablet (768px+) */
@media (min-width: 768px) {
    /* 2-column grid for cards */
    /* Smaller stage navigation */
}

/* Desktop (1024px+) */
@media (min-width: 1024px) {
    /* 3-column grid for cards */
    /* Full stage navigation */
    /* Sidebar for global content */
}

/* Large Desktop (1280px+) */
@media (min-width: 1280px) {
    /* Max width container (1200px) */
    /* Comfortable spacing */
}
```

---

## Interaction Patterns

### Stage Navigation
```
User clicks stage → 
  1. Update active state styling
  2. Smooth scroll to top
  3. Fade out old content
  4. Fade in new content
  5. Update URL hash (for sharing/bookmarking)
```

### Collapsible Sections
```
User clicks section header →
  1. Rotate chevron icon
  2. Slide toggle content
  3. Save state to localStorage
  4. Maintain scroll position
```

### Deliverable Actions
```
User clicks "View File" →
  1. Open in new tab
  2. Track view analytics (future)

User clicks "Approve" (Decision Maker only) →
  1. Show confirmation modal
  2. Submit AJAX request
  3. Update status badge
  4. Show success toast
```

---

## Accessibility (WCAG AA)

### Color Contrast
- All text passes 4.5:1 minimum ratio
- Large text (18px+) passes 3:1 minimum
- Icon colors pass contrast requirements

### Keyboard Navigation
- All interactive elements focusable via Tab
- Stage navigation supports arrow keys
- Enter/Space activates buttons
- Escape closes modals

### Screen Readers
- Proper heading hierarchy (H1 → H2 → H3)
- ARIA labels on icon-only buttons
- ARIA live regions for status updates
- Alt text on all meaningful images

### Focus States
- Clear focus rings on all interactive elements
- Skip to content link for keyboard users
- Focus trap in modals

---

## Performance Considerations

### Initial Load
- No external icon font library (inline SVG only)
- CSS compressed and minified
- No JavaScript framework (vanilla JS)

### Interaction
- CSS transitions only (no JS animations)
- Debounced AJAX calls
- Optimistic UI updates

### Mobile
- Touch-friendly targets (44px minimum)
- No hover-dependent functionality
- Fast tap response

---

## What Changed from v1.13.1 to v1.14.0

| Element | v1.13.1 | v1.14.0 |
|---------|---------|---------|
| **Layout** | Single column, linear scroll | Stage navigation + filtered content |
| **Primary Element** | Stats grid | Stage navigation |
| **Timeline** | Bottom of page | Top of page (primary nav) |
| **Content** | All stages shown | Only selected stage shown |
| **Icons** | Emoji (📍📄💬👥) | SVG line icons |
| **Colors** | Dark navy + pink | Modern indigo + cyan |
| **Stats Grid** | 4-card grid at top | Removed (redundant) |
| **Info Architecture** | Linear scroll | Progressive disclosure |
| **Stage Colors** | Single timeline color | 8 distinct colors |
| **Visual Weight** | Everything equal | Clear hierarchy |

---

## User Flows

### Client First Visit
1. Lands on portal page
2. Sees project name and their role
3. Stage navigation shows: 2 completed (green ✓), 1 current (indigo), 5 upcoming (gray)
4. Current stage content displayed by default
5. Sees deliverables and feedback for THIS stage only
6. Can click completed stages to review past work
7. Can expand global sections (team, definition) if needed

### Decision Maker Reviewing Stage
1. Clicks stage in navigation
2. Sees stage-specific content load
3. Reviews deliverables with "View File" buttons
4. Provides feedback via feedback form (if enabled)
5. Clicks another stage to compare
6. No scrolling required to find information

### Contributor Checking Progress
1. Opens portal
2. Immediately sees progress (visual timeline)
3. Clicks current stage to see what's happening
4. Reviews deliverables
5. Adds feedback if they have contributor permissions
6. Sees info notice explaining their role limitations

---

## Success Metrics

How we'll know the redesign works:

1. **Reduced Time to Task**
   - Clients find deliverables faster
   - Less scrolling to see progress
   - Fewer "where is X?" support questions

2. **Increased Engagement**
   - Clients check portal more frequently
   - More stage navigation clicks
   - More feedback submissions

3. **Improved Comprehension**
   - Clients understand their current stage
   - Clear on what's completed vs. upcoming
   - Less confusion about process

4. **Professional Perception**
   - Portal feels modern and polished
   - Consistent with high-quality agency work
   - Fred doesn't hate emoji anymore 😉

---

## Technical Implementation Notes

### File Changes
1. `shortcodes/expand-site-portal.php` - Complete rewrite of HTML structure
2. `assets/css/expand-site.css` - New colors, new layout styles, responsive grid
3. No JavaScript changes needed (stage switching can be CSS-only with anchor links)

### Backward Compatibility
- Shortcode attributes remain the same (`project_id`)
- AJAX handlers unchanged
- Database schema unchanged
- Admin pages unchanged

### Future Enhancements (Post-v1.14.0)
- Stage content preview for upcoming stages
- Collapsible global sections
- Downloadable project timeline PDF
- Email notifications when stage advances
- Comments/discussions per stage
- File upload for clients (feedback attachments)

---

**Last Updated:** February 23, 2026  
**Status:** Ready for implementation  
**Target Version:** v1.14.0
