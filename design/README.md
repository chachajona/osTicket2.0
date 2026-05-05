# osTicket 2.0 Design System

## Overview

**osTicket 2.0** is a modern redesign of osTicket — an open-source support ticket system. Built as a **Laravel + Inertia.js + React** application using **Tailwind CSS v4** and **shadcn/ui** (base-maia style).

### Source Repository
- **GitHub:** `chachajona/osTicket2.0` (branch: `main`)
- **Stack:** Laravel 12, React 19, Inertia.js, Tailwind CSS v4, TypeScript
- **UI Library:** shadcn/ui with `base-maia` style variant
- **Icons:** Solar (via Iconify CDN — `https://api.iconify.design/solar/`)
- **i18n:** react-i18next with browser language detection

### Products / Surfaces
1. **Staff Console (Auth)** — Login, forgot password, 2FA, password reset. Editorial layout with gradient-bordered cards on warm cream backgrounds.
2. **Staff Console (Dashboard)** — Sidebar navigation + ticket list + stats overview.

---

## CONTENT FUNDAMENTALS

### Tone & Voice
- **Professional and terse.** Copy is minimal, functional, uppercase-tracked for labels.
- **Period-terminated headlines** — titles end with a period for editorial gravitas: "Sign in to the console."
- **Arrow suffixes** on CTAs: "Enter Console →", "Forgot →"
- **Micro-labels are UPPERCASE** with wide letter-spacing (0.1em / 1.2px): "USERNAME", "PASSWORD"
- **Dot-separated metadata:** "osTicket · Staff Console", "v2.0 · Support Suite"
- **No emoji.** No casual language. Editorial, magazine-like tone.

### Casing Rules
- Headlines: Sentence case with period ("Sign in to the console.")
- Labels/captions: ALL CAPS with tracking (0.1em)
- Buttons: ALL CAPS with tracking (1.2px letter-spacing)
- Links: ALL CAPS underlined

---

## VISUAL FOUNDATIONS

### Color System (from DESIGN.md)

**Primary — Orange**
| Token | Hex |
|-------|-----|
| primary-50 | #FFF4EC |
| primary-100 | #FEE9DA |
| primary-200 | #FDD2B4 |
| primary-300 | #FCBC8F |
| primary-400 | #FB9A57 |
| primary-500 | #F97316 (base) |
| primary-600 | #D66313 |
| primary-700 | #AE510F |
| primary-800 | #813C0B |
| primary-900 | #552707 |

**Secondary — Pink**
| Token | Hex |
|-------|-----|
| secondary-500 | #EC4899 (base) |

**Tertiary — Indigo**
| Token | Hex |
|-------|-----|
| tertiary-500 | #6366F1 (base) |

**Neutral**
| Token | Hex |
|-------|-----|
| neutral-50 | #EDEDED |
| neutral-500 | #18181B (base / foreground) |

**Surfaces:** White (#FFFFFF) background, warm cream (#F4F2EB) for cards/surfaces/sidebar active.
**Borders:** #E2E0D8 (warm gray).
**Text secondary:** #A1A1AA.
**Brand gradient:** `linear-gradient(to right bottom, #FB923C, #EC4899, #6366F1)` — orange → pink → indigo.

### Typography
**Inter-only system.** Single font family for everything.
- **Font:** `'Inter', sans-serif` with OpenType features `ss01`, `cv11`
- **Mono:** `'Geist Mono', ui-monospace, monospace` (for ticket IDs, code)

### Type Scale
| Role | Size | Weight | Tracking | Leading | Transform |
|------|------|--------|----------|---------|-----------|
| Display XL | 128px | 500 | -0.05em | 128px | — |
| Display LG | 88px | 500 | -0.05em | 88px | — |
| Display MD | 48px | 500 | -0.025em | 48px | — |
| Display SM | 36px | 500 | -0.05em | 40px | — |
| Body MD | 14px | 400 | — | 22.75px | — |
| Body SM | 12px | 400 | — | 19.5px | — |
| Caption | 10px | 500 | 0.1em | 15px | UPPERCASE |
| Button | 12px | 500 | 1.2px | 16px | UPPERCASE |

### Spacing & Layout
- **Page padding:** 40px desktop
- **Sidebar width:** 260px
- **Card padding:** 40px
- **Field group gap:** 24px
- **4px base unit** — spacing follows 4/8/12/16/24/32/48/64/96px scale

### Borders & Corners
| Element | Radius |
|---------|--------|
| Auth inputs | 4px |
| Buttons (primary) | 3px |
| Cards / shells | 8px outer, 7px inner |
| Status badges | 3px |
| Nav items (sidebar) | 8px |
| Tabs (pill) | 32px (full round) |
| Avatars | 50% |

### Shadows
- **Auth card shell:** `rgba(236,72,153,0.10) 0 10px 15px -3px, rgba(236,72,153,0.10) 0 4px 6px -4px`
- **Auth submit:** `rgba(24,24,27,0.1) 0 2px 4px -2px, rgba(24,24,27,0.05) 0 1px 2px 0`
- **Focus ring:** `0 0 0 3px rgba(249,115,22,0.18)` (orange tint)

### Backgrounds & Textures
- **Gradient mesh:** Radial gradients of orange (top-right), indigo (bottom-left), pink (center) at 10–20% opacity.
- **Paper grain:** Dot-pattern texture at 0.6 opacity with multiply blend. 3px grid.

### Animation
- **Entry:** translateY(10px) → 0, opacity 0 → 1. Duration 0.55s, `cubic-bezier(0.4, 0, 0.2, 1)`. Staggered 60–80ms.
- **Transitions:** 150ms ease for interactions.
- **Press:** `scale(0.99)` on active.
- **Respects `prefers-reduced-motion`.**

### Interactive Patterns
- **Gradient-bordered card shell:** 1px gradient border wrapping cream inner card.
- **Eyebrow labels:** Colored dot + uppercase text.
- **Rule/divider:** Thin border, partial gradient accent on left.
- **Buttons:** Cream background (#F4F2EB), uppercase tracked text. Secondary: underline style.
- **Hover on links:** Color shifts to pink (#EC4899).

---

## ICONOGRAPHY

### Icon System
- **Solar** icon set via **Iconify CDN**
- URL pattern: `https://api.iconify.design/solar/{icon-name}.svg`
- Style: Bold variant preferred for navigation. 20px default size.
- Example icons: `home-2-bold`, `ticket-bold`, `settings-bold`, `users-group-two-rounded-bold`
- Custom inline SVGs for small UI (arrows, checkmarks).

### Logo
- Text-based: "osTicket" in caption style with gradient dot prefix.
- Brand identifier: "osTicket · Staff Console"

---

## FILES INDEX

```
├── README.md                    ← This file
├── SKILL.md                     ← Agent skill definition
├── colors_and_type.css          ← CSS custom properties (all design tokens)
├── preview/                     ← Design System tab preview cards
│   ├── primary-colors.html
│   ├── neutral-colors.html
│   ├── auth-accent-colors.html
│   ├── chart-colors.html
│   ├── semantic-colors.html
│   ├── type-sans-heading.html
│   ├── type-body-mono.html
│   ├── type-caption-scale.html
│   ├── radius-tokens.html
│   ├── shadow-tokens.html
│   ├── spacing-tokens.html
│   ├── button-components.html
│   ├── input-components.html
│   ├── alert-components.html
│   ├── auth-card-shell.html
│   ├── tabs-component.html
│   └── eyebrow-labels.html
├── ui_kits/
│   └── staff-console/
│       ├── README.md
│       ├── index.html           ← Interactive login → dashboard prototype
│       ├── AuthLayout.jsx
│       ├── LoginScreen.jsx
│       ├── ForgotPasswordScreen.jsx
│       ├── DashboardScreen.jsx
│       └── Components.jsx       ← Button, Input, Alert, NavItem, Tabs, etc.
└── resources/                   ← Imported source reference files
```
