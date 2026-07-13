---
version: alpha
name: AI Flow
description: Warm developer-tool aesthetic for structured GitHub workflow reports — workflows, not prompts.
colors:
  primary: "#181714"
  secondary: "#6f6c64"
  tertiary: "#f26334"
  tertiary-action: "#bd4a24"
  neutral: "#f7f5f0"
  surface: "#ffffff"
  line: "#dfdcd3"
  on-primary: "#ffffff"
  on-tertiary: "#ffffff"
  tertiary-action-hover: "#a84320"
  error: "#b93838"
  error-surface: "#fef4f4"
  success: "#1f7355"
  success-surface: "#e7f5ee"
  section-dark: "#1d1c19"
  section-dark-muted: "#8e8a81"
  tone-orange-text: "#b84824"
  tone-orange-bg: "#fff0ea"
  tone-blue-text: "#2f6db0"
  tone-blue-bg: "#eaf4ff"
  tone-purple-text: "#7a58bd"
  tone-purple-bg: "#f1ebff"
  tone-green-text: "#1f7355"
  tone-green-bg: "#e7f6ef"
  severity-high-text: "#b8382c"
  severity-high-bg: "#ffe8e4"
  severity-medium-text: "#a35624"
  severity-medium-bg: "#fff0df"
  severity-low-text: "#3a6299"
  severity-low-bg: "#eaf1fa"
typography:
  headline-display:
    fontFamily: Manrope
    fontSize: 75px
    fontWeight: 800
    lineHeight: 1.01
    letterSpacing: -0.065em
  headline-lg:
    fontFamily: Manrope
    fontSize: 53px
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: -0.055em
  headline-md:
    fontFamily: Manrope
    fontSize: 24px
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: -0.04em
  body-lg:
    fontFamily: Manrope
    fontSize: 17px
    fontWeight: 400
    lineHeight: 1.7
  body-md:
    fontFamily: Manrope
    fontSize: 14px
    fontWeight: 400
    lineHeight: 1.6
  body-sm:
    fontFamily: Manrope
    fontSize: 11px
    fontWeight: 400
    lineHeight: 1.7
  label-caps:
    fontFamily: DM Mono
    fontSize: 11px
    fontWeight: 500
    lineHeight: 1
    letterSpacing: 0.12em
  label-mono:
    fontFamily: DM Mono
    fontSize: 9px
    fontWeight: 500
    lineHeight: 1
    letterSpacing: 0.08em
rounded:
  sm: 4px
  md: 8px
  lg: 12px
  xl: 15px
  full: 9999px
spacing:
  xs: 4px
  sm: 8px
  md: 15px
  lg: 20px
  xl: 32px
  section: 100px
  gutter: 15px
  max-width: 1180px
  topbar-height: 74px
components:
  page-shell:
    backgroundColor: "{colors.neutral}"
    textColor: "{colors.primary}"
  button-primary:
    backgroundColor: "{colors.tertiary-action}"
    textColor: "{colors.on-tertiary}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.md}"
    height: 53px
    padding: 0 16px
  button-primary-hover:
    backgroundColor: "{colors.tertiary-action-hover}"
    textColor: "{colors.on-tertiary}"
    rounded: "{rounded.md}"
  button-inverse:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.on-primary}"
    typography: "{typography.body-sm}"
    rounded: "{rounded.md}"
    padding: 11px 16px
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.secondary}"
    rounded: 7px
    padding: 9px 14px
  card-launcher:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    rounded: "{rounded.xl}"
    padding: 23px
  card-workflow:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    rounded: "{rounded.lg}"
    padding: 22px
  divider:
    backgroundColor: "{colors.line}"
    textColor: "{colors.primary}"
  eyebrow:
    backgroundColor: "{colors.neutral}"
    textColor: "{colors.tertiary-action}"
  eyebrow-hover:
    backgroundColor: "{colors.neutral}"
    textColor: "{colors.tertiary-action-hover}"
  input-url:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    rounded: "{rounded.md}"
    height: 52px
    padding: 0 15px
  input-error:
    backgroundColor: "{colors.error-surface}"
    textColor: "{colors.error}"
    rounded: "{rounded.md}"
    padding: 7px 0 0
  badge-success:
    backgroundColor: "{colors.success-surface}"
    textColor: "{colors.success}"
    typography: "{typography.label-mono}"
    rounded: "{rounded.sm}"
    padding: 4px 7px
  section-dark-band:
    backgroundColor: "{colors.section-dark}"
    textColor: "{colors.on-primary}"
  section-dark-copy:
    backgroundColor: "{colors.section-dark}"
    textColor: "{colors.section-dark-muted}"
  workflow-icon-orange:
    backgroundColor: "{colors.tone-orange-bg}"
    textColor: "{colors.tone-orange-text}"
    rounded: "{rounded.md}"
  workflow-icon-blue:
    backgroundColor: "{colors.tone-blue-bg}"
    textColor: "{colors.tone-blue-text}"
    rounded: "{rounded.md}"
  workflow-icon-purple:
    backgroundColor: "{colors.tone-purple-bg}"
    textColor: "{colors.tone-purple-text}"
    rounded: "{rounded.md}"
  workflow-icon-green:
    backgroundColor: "{colors.tone-green-bg}"
    textColor: "{colors.tone-green-text}"
    rounded: "{rounded.md}"
  badge-severity-high:
    backgroundColor: "{colors.severity-high-bg}"
    textColor: "{colors.severity-high-text}"
    typography: "{typography.label-mono}"
    rounded: "{rounded.sm}"
    padding: 4px 7px
  badge-severity-medium:
    backgroundColor: "{colors.severity-medium-bg}"
    textColor: "{colors.severity-medium-text}"
    typography: "{typography.label-mono}"
    rounded: "{rounded.sm}"
    padding: 4px 7px
  badge-severity-low:
    backgroundColor: "{colors.severity-low-bg}"
    textColor: "{colors.severity-low-text}"
    typography: "{typography.label-mono}"
    rounded: "{rounded.sm}"
    padding: 4px 7px
---

# AI Flow

## Overview

AI Flow is a developer tool that turns GitHub URLs into structured workflow reports. The visual identity should feel **warm, precise, and editorial** — closer to a premium broadsheet than a chat interface.

The product follows finite views (`Home` → `Running` → `Report`), not message threads. Design choices should reinforce **clarity, scanability, and trust**: generous whitespace, strong typographic hierarchy, and a single confident accent color for the one action that matters on each screen.

Target audience: software engineers, maintainers, and technical reviewers who want polished output without prompt engineering.

## Colors

The palette is built on warm neutrals and a single interaction accent.

- **Primary (#181714):** Deep ink for headlines, logo marks, inverse buttons, and primary text. Evokes permanence and readability.
- **Secondary (#6F6C64):** Warm slate for body copy, captions, and secondary labels.
- **Tertiary (#F26334):** Bright flow orange for focus rings and `<em>` emphasis.
- **Tertiary action (#BD4A24):** Darker orange for filled buttons — meets WCAG AA with white label text. Hover #A84320.
- **Neutral (#F7F5F0):** Warm limestone page background. Softer and more organic than pure white.
- **Surface (#FFFFFF):** Cards, inputs, tables, and report panels sit on white for containment.
- **Line (#DFDCD3):** Borders, dividers, and card outlines.

**Launcher icon tones** (workflow differentiation, not interaction):

| Tone   | Text    | Background |
|--------|---------|------------|
| Orange | #B84824 | #FFF0EA    |
| Blue   | #2F6DB0 | #EAF4FF    |
| Purple | #7A58BD | #F1EBFF    |
| Green  | #1F7355 | #E7F6EF    |

**Severity badges** (report findings): high (#B8382C on #FFE8E4), medium (#A35624 on #FFF0DF), low (#3A6299 on #EAF1FA).

**Dark accent sections** (`.how-section`, `.cta-band`): background #1D1C19, muted text #8E8A81. These are intentional contrast bands, not a dark-mode theme.

## Typography

Three typefaces divide roles:

- **Manrope** (400–800): Default UI. Headlines are extra-bold with tight negative tracking. Body stays at 14–17px for comfortable reading.
- **DM Mono** (400, 500): Eyebrows, step numbers, badges, metadata, and file references. Often uppercase with generous letter-spacing.
- **Playfair Display** (italic 600): Used only inside `<em>` within headlines for a serif accent in Flow orange — e.g. “Put your GitHub URLs *in flow.*”

**Hierarchy:**

- Hero `h1`: `clamp(49px, 5.5vw, 75px)`, weight 800, tracking −0.065em
- Section `h2`: `clamp(38px, 4vw, 53px)`, tracking −0.055em
- Card `h3`: 16px, tracking −0.02em
- Eyebrow / kicker: 11px DM Mono, uppercase, tracking 0.12em, color tertiary

## Layout

Content uses a **fixed-max-width grid** centered at **1180px** (sections, footer, topbar padding). Narrower layouts: 900px dashboard, 780px running, 1120px report.

- **Topbar:** 74px height, three-column grid (logo | nav | actions)
- **Section padding:** ~100–105px vertical on desktop, ~75px at ≤800px
- **Card grids:** 15px gap (workflows, features); 8px gap for form micro-layouts
- **Hero / auth texture:** 20×20px dot grid (`radial-gradient` #C9C5BA) with radial fade overlay
- **Responsive breakpoint:** 800px — nav collapses to hamburger dropdown

Spacing follows an informal scale: 4px micro, 8px tight, 15px default gutter, 20–32px section rhythm.

## Elevation & Depth

Depth is conveyed through **tonal layers and soft shadows**, not heavy drop shadows.

- Page sits on warm neutral (#F7F5F0); content cards use pure white.
- Primary cards (launcher, auth) use a compound shadow: `0 18px 50px rgba(40,35,25,0.09)` plus a subtle inset highlight.
- Hover on workflow/feature cards: `translateY(-4px)` + `0 16px 30px rgba(30,28,24,0.08)`.
- Selected workflow card: 2px orange outline at 16% opacity.
- Primary buttons carry a tertiary-tinted shadow: `0 5px 16px rgba(242,99,52,0.23)` and lift 1px on hover.

Flat borders (#DFDCD3, #D6D3CA) separate elements where shadow would be excessive.

## Shapes

The shape language balances **soft cards** with **architectural brand marks**.

- Badges and severity chips: 4px radius
- Buttons, inputs, selects: 8px radius
- Standard cards: 12px radius
- Launcher / auth cards: 15px radius
- Logo mark and feature icons: asymmetric radius `9px 9px 9px 3px` (signature corner cut)
- CTA band mark: `15px 15px 15px 5px`
- Circular: step numbers, avatars, clear-input buttons

Icons use **lucide-react** with `strokeWidth={2}`.

## Components

### Buttons

| Variant    | Class            | Style                                      |
|------------|------------------|--------------------------------------------|
| Primary    | `.launch-button` | Tertiary fill, weight 800, full-width CTA  |
| Inverse    | `.header-cta`    | Ink fill, white text                       |
| Secondary  | `.header-auth-btn` | White fill, #D6D2C9 border               |
| Ghost nav  | `.nav button`    | No border; #5B5953 → ink on hover          |

Disabled buttons: `opacity: 0.55`, `cursor: not-allowed`.

### Forms & inputs

- URL input group: `.url-box` — icon left, 52px height, orange focus ring (`box-shadow: 0 0 0 3px rgba(242,99,52,0.1)`)
- Error state: `.has-error` border #C54343; `.input-error` text #B93838 at 11px
- Provider fields: 2-column grid (`1fr 2fr`), 42px control height
- Checkboxes: `.checklist` with `accent-color: var(--orange)`
- Selects: custom chevron, `appearance: none`

### Cards

| Class            | Use                          |
|------------------|------------------------------|
| `.launcher-card` | Hero launch form (max 650px) |
| `.workflow-card` | Launcher library grid        |
| `.feature-card`  | Marketing feature grid       |
| `.finding`       | Report finding with severity |
| `.summary-box`   | Report summary (3px orange left border) |
| `.auth-card`     | Sign-in / check-email (max 420px) |

Auth uses **full-page centered cards**, not overlay modals.

### Status & navigation

- `.status-badge`: DM Mono uppercase chips for run states (completed, failed, running, queued)
- `.tab.active`: bottom border in tertiary
- Report sidebar TOC: `.active` item gets orange left border

### Motion

- Transitions: 0.15–0.2s on borders, transforms, colors
- Loading: `.spin` rotation on `Loader2` icon
- Hover lifts: primary buttons −1px Y; cards −4px Y

## Do's and Don'ts

- Do use tertiary orange for the single most important action per screen (launch, submit, active tab)
- Do keep report layouts structured — severity badges, file refs, suggestions, checklists — never chat bubbles
- Do use DM Mono for metadata, timestamps, and technical labels; Manrope for narrative text
- Do preserve the warm neutral background; avoid stark #FFFFFF page backgrounds
- Do maintain WCAG AA contrast (4.5:1) for body text on surfaces
- Don't introduce a user-toggleable dark mode; dark sections are fixed marketing bands only
- Don't mix more than two font weights on a single card
- Don't use tertiary orange for decorative fills outside actions, eyebrows, and `<em>` emphasis
- Don't add modal overlays for auth — use centered page cards on the dot-grid background
- Don't adopt Tailwind or component libraries for new UI; extend `backend/resources/css/app.css`