# osTicket Staff Console — UI Kit

## Overview
Interactive click-through prototype of the osTicket 2.0 staff console. Covers the auth flow (login, forgot password) and a dashboard shell.

## Screens
1. **Login** — Editorial auth layout with gradient-bordered card, username/password fields, remember checkbox
2. **Forgot Password** — Same layout, email field, back to login link
3. **Dashboard** — Sidebar navigation + ticket list + stats overview

## Components
- `AuthLayout.jsx` — Full auth page wrapper (mesh bg, grain, gradient shell, header/footer)
- `LoginScreen.jsx` — Login form with all field states
- `ForgotPasswordScreen.jsx` — Password reset form
- `DashboardScreen.jsx` — Main app shell with sidebar + ticket list
- `Components.jsx` — Shared UI primitives (Button, Input, Alert, Field, Tabs, etc.)

## Source
Recreated from `chachajona/osTicket2.0` codebase (Laravel + React + Tailwind + shadcn/ui base-maia).
