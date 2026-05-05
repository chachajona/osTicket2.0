# Admin Panel Shell + IA Restructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Wire up the missing Admin↔Agent panel switcher in the SCP shell, replace the flat-sidebar `AdminLayout` with the legacy-style top-tabs + sub-strip information architecture, and align each `Admin/*/Edit.tsx` page with the legacy inner-tab layout for its surface.

**Architecture:** Expose `auth.staff.isAdmin` as a shared Inertia prop. Add a header-bar switcher in both layouts (gated on `isAdmin` for the SCP-side link). Rewrite `AdminLayout.tsx` to render the legacy 5-tab top nav (Dashboard / Settings / Manage / Emails / Agents) plus a sub-tab strip for the active tab; only the 9 Phase 2a surfaces are clickable, the rest render as disabled placeholders so the IA is visibly "in progress" without faking routes. Convert existing per-surface `Edit.tsx` files (Department, Staff, Role) from sectioned forms to inner-tabs that mirror legacy: Department → Settings/Access; Staff → Account/Access/Permissions/Teams/2FA; Role → Definition/Permissions. Filter and EmailConfig already match legacy patterns and need only minor tab-strip wrapping for consistency.

**Tech Stack:** Laravel 11 + Inertia.js, React 18 + TypeScript, Tailwind CSS, Pest (backend tests), shadcn-ui `Tabs` primitive (`@/components/ui/tabs.tsx`).

---

## File Structure

**Modify:**
- `app/Http/Middleware/HandleInertiaRequests.php` — add `isAdmin` to `auth.staff` shared prop.
- `resources/js/layouts/DashboardLayout.tsx` — render an "Admin Panel" link in the header right cluster when `auth.staff.isAdmin` is true.
- `resources/js/components/admin/AdminLayout.tsx` — replace the flat left sidebar with a top tab strip + sub-tab strip; render an "Agent Panel" link in the header right cluster.
- `resources/js/pages/Admin/Departments/Edit.tsx` — wrap sections in `Settings` / `Access` inner tabs.
- `resources/js/pages/Admin/Staff/Edit.tsx` — wrap sections in `Account` / `Access` / `Permissions` / `Teams` / `2FA` inner tabs.
- `resources/js/pages/Admin/Roles/Edit.tsx` — wrap sections in `Definition` / `Permissions` inner tabs.

**Create:**
- `resources/js/components/admin/AdminTabs.tsx` — top-tab strip + sub-tab strip; declarative IA tree.
- `resources/js/components/admin/AdminTabs.constants.ts` — single source of truth for the legacy IA tree (top tabs + their submenu items, with `enabled` flag per item).
- `tests/Feature/Inertia/SharedPropsTest.php` — covers `auth.staff.isAdmin` shared prop.

**No changes:**
- `routes/web.php` — already registers `/admin/{resource}` routes; the only fix is in `AdminLayout.tsx` hrefs (folded into Task 4).
- `app/Models/Staff.php` — already exposes `isadmin` as a model property.
- `resources/js/pages/Admin/Filters/Edit.tsx` and `resources/js/pages/Admin/EmailConfig/Edit.tsx` — already match legacy section patterns; only changes are the layout shell (covered by `AdminLayout` rewrite).

---

## IA Reference (locked)

Top-tabs + submenu, exact order from `AdminNav::getTabs()` / `AdminNav::getSubMenus()`:

| Top tab | Submenu items (Phase 2a status) |
|---|---|
| **Dashboard** | System Logs (disabled) · Audit Logs (disabled) · Information (disabled) |
| **Settings** | Company / System / Tickets / Tasks / Agents / Users / Knowledgebase (all disabled) |
| **Manage** | Help Topics ✅ · Filters ✅ · SLA ✅ · Schedules (disabled) · API (disabled) · Pages (disabled) · Forms (disabled) · Lists (disabled) · Plugins (disabled) |
| **Emails** | Emails ✅ (→ `/admin/email-config`) · Settings (disabled) · Banlist (disabled) · Templates ✅ (→ `/admin/email-config?type=template`) · Diagnostic (disabled) |
| **Agents** | Agents ✅ (→ `/admin/staff`) · Teams ✅ · Roles ✅ · Departments ✅ |

`Canned Responses` is **not** a top-level legacy admin item — in legacy it's reachable from a ticket compose flow. For Phase 2a we surface it under **Manage → Canned Responses** as an additive item (clearly labeled), since the route already exists and the team needs UI access to it. Mark this as a deliberate divergence in the constants file comment.

---

## Task 1: Add `auth.staff.isAdmin` to shared Inertia props (TDD)

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php:44-62`
- Test: `tests/Feature/Inertia/SharedPropsTest.php` (new)

- [x] **Step 1: Create the failing test**

Create `tests/Feature/Inertia/SharedPropsTest.php`:

```php
<?php

use App\Models\Staff;

it('exposes isAdmin=true for admin staff in the shared auth prop', function () {
    $staff = Staff::factory()->create(['isactive' => 1, 'isadmin' => 1]);

    $response = $this->actingAs($staff, 'staff')->get('/scp');

    $response->assertInertia(fn ($page) => $page->where('auth.staff.isAdmin', true));
});

it('exposes isAdmin=false for non-admin staff in the shared auth prop', function () {
    $staff = Staff::factory()->create(['isactive' => 1, 'isadmin' => 0]);

    $response = $this->actingAs($staff, 'staff')->get('/scp');

    $response->assertInertia(fn ($page) => $page->where('auth.staff.isAdmin', false));
});

it('returns null auth.staff for guests', function () {
    $response = $this->get('/scp/login');

    $response->assertInertia(fn ($page) => $page->where('auth.staff', null));
});
```

- [x] **Step 2: Run the test and confirm it fails**

Run: `vendor/bin/pest tests/Feature/Inertia/SharedPropsTest.php`
Expected: 2 failures — `auth.staff.isAdmin` does not exist (the third test passes already).

- [x] **Step 3: Add `isAdmin` to the shared prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, replace lines 47-55 (the `'staff' => …` block) with:

```php
'staff' => $staff
    ? [
        'id' => $staff->staff_id,
        'name' => trim(($staff->firstname ?? '').' '.($staff->lastname ?? '')) ?: $staff->username,
        'username' => $staff->username,
        'isAdmin' => (bool) $staff->isadmin,
        'migrationBanner' => $this->migrationBannerVisible($request, $staff),
    ]
    : null,
```

- [x] **Step 4: Run the test and confirm it passes**

Run: `vendor/bin/pest tests/Feature/Inertia/SharedPropsTest.php`
Expected: 3 passing.

- [x] **Step 5: Commit**

```bash
git add app/Http/Middleware/HandleInertiaRequests.php tests/Feature/Inertia/SharedPropsTest.php
git commit -m "feat(admin): expose isAdmin in shared Inertia auth prop"
```

---

## Task 2: Define the legacy admin IA tree as a constants module

**Files:**
- Create: `resources/js/components/admin/AdminTabs.constants.ts`

- [x] **Step 1: Create the constants file**

Create `resources/js/components/admin/AdminTabs.constants.ts`:

```ts
import {
    DashboardSquare01Icon,
    Settings01Icon,
    ToolsIcon,
    Mail01Icon,
    UserMultiple02Icon,
} from '@hugeicons/core-free-icons';

export interface AdminSubItem {
    id: string;
    label: string;
    href: string | null;
    enabled: boolean;
}

export interface AdminTopTab {
    id: string;
    label: string;
    icon: typeof DashboardSquare01Icon;
    defaultSubId: string | null;
    submenu: AdminSubItem[];
}

// Legacy IA from osTicket AdminNav::getTabs() / getSubMenus().
// Items marked enabled=false render as disabled placeholders so the IA
// shape is preserved while Phase 2a only ships 9 surfaces.
//
// Divergence from legacy:
//   - "Canned Responses" is added under Manage as an additive item
//     (legacy exposes it inline from ticket compose; we need a top-level
//     entry for admins).
export const ADMIN_TABS: AdminTopTab[] = [
    {
        id: 'dashboard',
        label: 'Dashboard',
        icon: DashboardSquare01Icon,
        defaultSubId: null,
        submenu: [
            { id: 'logs', label: 'System Logs', href: null, enabled: false },
            { id: 'audits', label: 'Audit Logs', href: null, enabled: false },
            { id: 'system', label: 'Information', href: null, enabled: false },
        ],
    },
    {
        id: 'settings',
        label: 'Settings',
        icon: Settings01Icon,
        defaultSubId: null,
        submenu: [
            { id: 'company', label: 'Company', href: null, enabled: false },
            { id: 'system', label: 'System', href: null, enabled: false },
            { id: 'tickets', label: 'Tickets', href: null, enabled: false },
            { id: 'tasks', label: 'Tasks', href: null, enabled: false },
            { id: 'agents', label: 'Agents', href: null, enabled: false },
            { id: 'users', label: 'Users', href: null, enabled: false },
            { id: 'kb', label: 'Knowledgebase', href: null, enabled: false },
        ],
    },
    {
        id: 'manage',
        label: 'Manage',
        icon: ToolsIcon,
        defaultSubId: 'help-topics',
        submenu: [
            { id: 'help-topics', label: 'Help Topics', href: '/admin/help-topics', enabled: true },
            { id: 'filters', label: 'Filters', href: '/admin/filters', enabled: true },
            { id: 'slas', label: 'SLA', href: '/admin/slas', enabled: true },
            { id: 'canned-responses', label: 'Canned Responses', href: '/admin/canned-responses', enabled: true },
            { id: 'schedules', label: 'Schedules', href: null, enabled: false },
            { id: 'api', label: 'API', href: null, enabled: false },
            { id: 'pages', label: 'Pages', href: null, enabled: false },
            { id: 'forms', label: 'Forms', href: null, enabled: false },
            { id: 'lists', label: 'Lists', href: null, enabled: false },
            { id: 'plugins', label: 'Plugins', href: null, enabled: false },
        ],
    },
    {
        id: 'emails',
        label: 'Emails',
        icon: Mail01Icon,
        defaultSubId: 'email-config',
        submenu: [
            { id: 'email-config', label: 'Emails', href: '/admin/email-config', enabled: true },
            { id: 'email-settings', label: 'Settings', href: null, enabled: false },
            { id: 'banlist', label: 'Banlist', href: null, enabled: false },
            { id: 'templates', label: 'Templates', href: null, enabled: false },
            { id: 'diagnostic', label: 'Diagnostic', href: null, enabled: false },
        ],
    },
    {
        id: 'agents',
        label: 'Agents',
        icon: UserMultiple02Icon,
        defaultSubId: 'staff',
        submenu: [
            { id: 'staff', label: 'Agents', href: '/admin/staff', enabled: true },
            { id: 'teams', label: 'Teams', href: '/admin/teams', enabled: true },
            { id: 'roles', label: 'Roles', href: '/admin/roles', enabled: true },
            { id: 'departments', label: 'Departments', href: '/admin/departments', enabled: true },
        ],
    },
];

export const ADMIN_TAB_BY_SUB_ID: Record<string, { tabId: string; subId: string }> = ADMIN_TABS
    .flatMap((tab) =>
        tab.submenu.map((sub) => [sub.id, { tabId: tab.id, subId: sub.id }] as const),
    )
    .reduce<Record<string, { tabId: string; subId: string }>>((acc, [id, value]) => {
        acc[id] = value;
        return acc;
    }, {});
```

- [x] **Step 2: Verify the icons resolve**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: No errors. (If `ToolsIcon` or `Mail01Icon` does not exist in `@hugeicons/core-free-icons`, swap to the closest available — search the package's exports for `Mail` and `Wrench`/`Tools`/`Settings` and pick a sensible alternative; document the substitution in a one-line comment above the import.)

- [x] **Step 3: Commit**

```bash
git add resources/js/components/admin/AdminTabs.constants.ts
git commit -m "feat(admin): define legacy IA tree as shared constants"
```

---

## Task 3: Build the `AdminTabs` component (top tabs + sub-strip)

**Files:**
- Create: `resources/js/components/admin/AdminTabs.tsx`

- [x] **Step 1: Create the component**

Create `resources/js/components/admin/AdminTabs.tsx`:

```tsx
import { Link } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { cn } from '@/lib/utils';
import { ADMIN_TABS, type AdminTopTab, type AdminSubItem } from './AdminTabs.constants';

interface AdminTabsProps {
    activeSubId?: string;
}

function findActiveTab(activeSubId?: string): AdminTopTab | undefined {
    if (!activeSubId) return undefined;
    return ADMIN_TABS.find((tab) => tab.submenu.some((sub) => sub.id === activeSubId));
}

function topTabClasses(isActive: boolean): string {
    return cn(
        'inline-flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors',
        isActive
            ? 'border-[#5B619D] text-[#0F172A]'
            : 'border-transparent text-[#64748B] hover:border-[#CBD5E1] hover:text-[#0F172A]',
    );
}

function subItemClasses(isActive: boolean, enabled: boolean): string {
    if (!enabled) {
        return 'inline-flex cursor-not-allowed items-center px-3 py-2 text-sm font-medium text-[#94A3B8]';
    }
    return cn(
        'inline-flex items-center rounded-md px-3 py-2 text-sm font-medium transition-colors',
        isActive
            ? 'bg-[#F1F5F9] text-[#0F172A] shadow-[inset_0_0_0_1px_rgba(226,232,240,0.8)]'
            : 'text-[#64748B] hover:bg-white hover:text-[#0F172A]',
    );
}

export function AdminTabs({ activeSubId }: AdminTabsProps) {
    const activeTab = findActiveTab(activeSubId) ?? ADMIN_TABS[2]; // Default to Manage if nothing matches

    return (
        <nav aria-label="Admin navigation" className="border-b border-[#E2E8F0] bg-white">
            <div className="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-4 sm:px-6 xl:px-10">
                {ADMIN_TABS.map((tab) => {
                    const isActive = tab.id === activeTab.id;
                    const firstEnabled = tab.submenu.find((sub) => sub.enabled);
                    const href = firstEnabled?.href ?? null;
                    const className = topTabClasses(isActive);
                    const content = (
                        <>
                            <HugeiconsIcon icon={tab.icon} size={16} />
                            <span>{tab.label}</span>
                        </>
                    );
                    if (!href) {
                        return (
                            <button key={tab.id} type="button" disabled aria-disabled className={cn(className, 'cursor-not-allowed opacity-60')}>
                                {content}
                            </button>
                        );
                    }
                    return (
                        <Link key={tab.id} href={href} className={className} aria-current={isActive ? 'page' : undefined}>
                            {content}
                        </Link>
                    );
                })}
            </div>

            <div className="border-t border-[#E2E8F0] bg-[#F8FAFC]">
                <div className="mx-auto flex max-w-7xl items-center gap-1 overflow-x-auto px-4 py-2 sm:px-6 xl:px-10">
                    {activeTab.submenu.map((sub: AdminSubItem) => {
                        const isActive = sub.id === activeSubId;
                        const className = subItemClasses(isActive, sub.enabled);
                        if (!sub.enabled || !sub.href) {
                            return (
                                <span key={sub.id} className={className} aria-disabled title="Not yet implemented">
                                    {sub.label}
                                </span>
                            );
                        }
                        return (
                            <Link key={sub.id} href={sub.href} className={className} aria-current={isActive ? 'page' : undefined}>
                                {sub.label}
                            </Link>
                        );
                    })}
                </div>
            </div>
        </nav>
    );
}

export default AdminTabs;
```

- [x] **Step 2: Type-check**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: No errors.

- [x] **Step 3: Commit**

```bash
git add resources/js/components/admin/AdminTabs.tsx
git commit -m "feat(admin): add top-tabs + sub-strip nav component"
```

---

## Task 4: Rewrite `AdminLayout` to use top tabs + Agent Panel switcher

**Files:**
- Modify: `resources/js/components/admin/AdminLayout.tsx` (full rewrite)

- [x] **Step 1: Replace the file contents**

Replace the entire contents of `resources/js/components/admin/AdminLayout.tsx`:

```tsx
import { type ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import DashboardLayout from '@/layouts/DashboardLayout';
import { AdminTabs } from './AdminTabs';
import { buttonVariants } from '@/components/ui/button';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon } from '@hugeicons/core-free-icons';
import { cn } from '@/lib/utils';

interface AdminLayoutProps extends Omit<React.ComponentProps<typeof DashboardLayout>, 'headerActions'> {
    activeAdminNav?: string;
    children: ReactNode;
}

function AdminHeaderActions() {
    return (
        <Link
            href="/scp"
            className={cn(
                buttonVariants({ variant: 'outline', size: 'sm' }),
                'rounded-[4px] border-[#E2E8F0] bg-white text-xs font-medium uppercase tracking-[0.12em] text-[#64748B] hover:border-[#C4A5F3] hover:bg-[#F8FAFC] hover:text-[#0F172A]',
            )}
        >
            <HugeiconsIcon icon={ArrowLeft01Icon} size={14} className="mr-1.5" />
            Agent Panel
        </Link>
    );
}

export function AdminLayout({ activeAdminNav, children, contentClassName, ...props }: AdminLayoutProps) {
    return (
        <DashboardLayout
            {...props}
            activeNav="admin"
            headerActions={<AdminHeaderActions />}
            contentClassName="w-full"
        >
            <div className="-mx-4 -mt-5 sm:-mx-6 sm:-mt-6 lg:-mx-8 xl:-mx-10 xl:-mt-8">
                <AdminTabs activeSubId={activeAdminNav} />
                <div className={cn('mx-auto max-w-7xl px-4 py-6 sm:px-6 xl:px-10', contentClassName)}>
                    {children}
                </div>
            </div>
        </DashboardLayout>
    );
}

export default AdminLayout;
```

- [x] **Step 2: Type-check**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: No errors.

- [x] **Step 3: Build to verify Vite picks up the new components**

Run: `npm run build`
Expected: Build succeeds.

- [x] **Step 4: Commit**

```bash
git add resources/js/components/admin/AdminLayout.tsx
git commit -m "feat(admin): replace flat sidebar with legacy 5-tab IA + agent panel switcher"
```

---

## Task 5: Add the SCP-side "Admin Panel" header link (gated on isAdmin)

**Files:**
- Modify: `resources/js/layouts/DashboardLayout.tsx` (header action area)

The current `DashboardLayout` renders `<DefaultHeaderActions />` (My Queue + New Ticket) when no `headerActions` prop is supplied. We will inject an "Admin Panel" link **before** those default actions when the authenticated staff member is an admin. The `usePage().props.auth.staff` shape now includes `isAdmin` (Task 1).

- [x] **Step 1: Add `usePage` import + admin-gated link**

In `resources/js/layouts/DashboardLayout.tsx`:

1. Add `usePage` to the existing `@inertiajs/react` import on line 2:

```ts
import { Link, router, usePage } from '@inertiajs/react';
```

2. Add the icon import (find the existing `@hugeicons/core-free-icons` block on lines 5-22 and add `ShieldCheckIcon` if not already present — if `ShieldCheck` from line 20 is already there, reuse it).

3. Replace the `DefaultHeaderActions` function (lines 128-148) with:

```tsx
function DefaultHeaderActions() {
    const { t } = useTranslation();
    const { props } = usePage<{ auth?: { staff?: { isAdmin?: boolean } | null } }>();
    const isAdmin = props.auth?.staff?.isAdmin === true;

    return (
        <>
            {isAdmin && (
                <Link
                    href="/admin/help-topics"
                    className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), "rounded-[4px] border-[#E2E8F0] bg-white text-xs font-medium uppercase tracking-[0.12em] text-[#64748B] hover:border-[#C4A5F3] hover:bg-[#F8FAFC] hover:text-[#0F172A]")}
                >
                    <HugeiconsIcon icon={ShieldCheck} size={14} className="mr-1.5" color="#94A3B8" />
                    {t('dashboard.layout.admin_panel', { defaultValue: 'Admin Panel' })}
                </Link>
            )}
            <Link
                href="/scp/queues"
                className={cn(buttonVariants({ variant: 'outline', size: 'sm' }), "rounded-[4px] border-[#E2E8F0] bg-white text-xs font-medium uppercase tracking-[0.12em] text-[#64748B] hover:border-[#C4A5F3] hover:bg-[#F8FAFC] hover:text-[#0F172A]")}
            >
                {t('dashboard.layout.my_queue')}
            </Link>
            <button
                type="button"
                disabled
                className={cn(buttonVariants({ size: 'sm' }), "rounded-[4px] bg-[#5B619D] px-4 text-xs font-medium uppercase tracking-[0.12em] text-white hover:bg-[#4F548C] disabled:cursor-not-allowed disabled:opacity-60 shadow-[0_10px_25px_-20px_rgba(91,97,157,0.7)]")}
            >
                {t('dashboard.layout.new_ticket')}
            </button>
        </>
    );
}
```

The "Admin Panel" link points at `/admin/help-topics` (Manage tab default — first enabled surface in legacy order). When the user lands there, `AdminLayout` shows the full top-tab strip and they can navigate from there.

- [x] **Step 2: Type-check**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: No errors.

- [x] **Step 3: Commit**

```bash
git add resources/js/layouts/DashboardLayout.tsx
git commit -m "feat(scp): show Admin Panel link in header for admin staff"
```

---

## Task 6: Convert `Departments/Edit.tsx` to Settings/Access inner tabs

**Files:**
- Modify: `resources/js/pages/Admin/Departments/Edit.tsx`

Currently the form is one long page with a single `Department Details` `FormSection`. Legacy splits this into a `Settings` tab (department info, signature, manager, SLA, email, public flag) and an `Access` tab (members and per-dept role overrides). For Phase 2a we have data only for the Settings side — so the Access tab renders a placeholder pointing the user to the Staff page for now.

- [x] **Step 1: Wrap the form in `<Tabs>`**

In `resources/js/pages/Admin/Departments/Edit.tsx`:

1. Add the import at line 12 (after the existing `Textarea` import):

```ts
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
```

2. Replace the `<form>` body (lines 169-295). The full replacement:

```tsx
<form
    onSubmit={(event) => {
        event.preventDefault();

        if (isEdit && department) {
            patch(route('admin.departments.update', department.id));
            return;
        }

        post(route('admin.departments.store'));
    }}
    className="space-y-6"
>
    <Tabs defaultValue="settings" className="space-y-6">
        <TabsList>
            <TabsTrigger value="settings">Settings</TabsTrigger>
            <TabsTrigger value="access">Access</TabsTrigger>
        </TabsList>

        <TabsContent value="settings">
            <FormSection
                title="Department Details"
                description="Configure ownership, routing defaults, and the department signature used in responses."
                collapsible={false}
            >
                <FormGrid columns={2} className="max-w-5xl">
                    {/* keep all existing form fields here — name, dept_id, sla_id, manager_id, email_id, template_id, ispublic, signature */}
                    {/* (lines 188-283 of the original file, unchanged) */}
                </FormGrid>
            </FormSection>
        </TabsContent>

        <TabsContent value="access">
            <FormSection
                title="Department Access"
                description="Per-staff department access and role overrides are managed from the Staff page."
                collapsible={false}
            >
                <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                    To grant or change a specific staff member's access to this department,
                    open the staff member's profile under{' '}
                    <Link href={route('admin.staff.index')} className="font-medium text-[#5B619D] underline">
                        Agents → Staff
                    </Link>{' '}
                    and use the <strong>Department Access</strong> section.
                </div>
            </FormSection>
        </TabsContent>
    </Tabs>

    <div className="flex items-center justify-end gap-4 pt-4">
        <Link href={route('admin.departments.index')} className={buttonVariants({ variant: 'outline' })}>
            Cancel
        </Link>
        <Button type="submit" disabled={processing}>
            <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
            {isEdit ? 'Save Changes' : 'Create Department'}
        </Button>
    </div>
</form>
```

Note: in the actual edit, leave all existing form fields verbatim inside the `<TabsContent value="settings">` `<FormGrid>` block — do NOT delete them. Only the wrapping changes.

- [x] **Step 2: Type-check + run existing department test to make sure controller still binds correctly**

Run: `npx tsc --noEmit -p tsconfig.json && vendor/bin/pest tests/Feature/Admin/DepartmentAdminTest.php`
Expected: No type errors; all department tests pass (we did not change form field names, only wrapping).

- [x] **Step 3: Commit**

```bash
git add resources/js/pages/Admin/Departments/Edit.tsx
git commit -m "feat(admin/departments): split edit form into Settings/Access inner tabs"
```

---

## Task 7: Convert `Staff/Edit.tsx` to Account/Access/Permissions/Teams/2FA inner tabs

**Files:**
- Modify: `resources/js/pages/Admin/Staff/Edit.tsx`

Current sections: Basic Info / Account / Department Access / Teams. Legacy tabs: Account / Access / Permissions / Teams / 2FA. We map:

- **Account tab** = current Basic Info + Account sections (combined; legacy Account contains identity + auth).
- **Access tab** = current Department Access section.
- **Permissions tab** = placeholder ("Permissions are managed via the staff member's role — see Agents → Roles") since per-staff permission overrides are not in scope.
- **Teams tab** = current Teams section.
- **2FA tab** = the existing 2FA status panel pulled out of the Account section.

- [x] **Step 1: Add `Tabs` import + restructure**

In `resources/js/pages/Admin/Staff/Edit.tsx`:

1. Add the import after the existing `Textarea` import on line 11:

```ts
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
```

2. Replace the form body — find the opening `<form` (line 191) through the closing `</form>` (line 392). Replace **only the `space-y-6` wrapper's children** (i.e. between `className="space-y-6"` and the final submit-button `<div>`) with:

```tsx
<Tabs defaultValue="account" className="space-y-6">
    <TabsList>
        <TabsTrigger value="account">Account</TabsTrigger>
        <TabsTrigger value="access">Access</TabsTrigger>
        <TabsTrigger value="permissions">Permissions</TabsTrigger>
        <TabsTrigger value="teams">Teams</TabsTrigger>
        <TabsTrigger value="2fa">2FA</TabsTrigger>
    </TabsList>

    <TabsContent value="account" className="space-y-6">
        {/* MOVE HERE: the existing "Basic Info" FormSection (lines 205-248) */}
        {/* MOVE HERE: the existing "Account" FormSection (lines 250-317),
             but REMOVE the 2FA panel block (lines 283-295) — it goes to the 2FA tab */}
    </TabsContent>

    <TabsContent value="access" className="space-y-6">
        {/* MOVE HERE: the existing "Department Access" FormSection (lines 319-358) */}
    </TabsContent>

    <TabsContent value="permissions">
        <FormSection
            title="Permissions"
            description="Per-staff permission overrides are not configurable here."
            collapsible={false}
        >
            <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                Permissions are inherited from this staff member's <strong>Role</strong>.
                To change permissions, edit the role under{' '}
                <Link href={route('admin.roles.index')} className="font-medium text-[#5B619D] underline">
                    Agents → Roles
                </Link>{' '}
                or assign a different role on the <strong>Account</strong> tab.
            </div>
        </FormSection>
    </TabsContent>

    <TabsContent value="teams" className="space-y-6">
        {/* MOVE HERE: the existing "Teams" FormSection (lines 360-381) */}
    </TabsContent>

    <TabsContent value="2fa">
        <FormSection
            title="Two-factor Authentication"
            description="Two-factor authentication status for this staff member."
            collapsible={false}
        >
            <div className="rounded-lg border border-slate-200 p-4">
                <p className="text-sm font-medium text-slate-900">Two-factor authentication</p>
                <p className="mt-1 text-sm text-slate-500">
                    {staffMember?.two_factor.enabled
                        ? `Enabled${staffMember.two_factor.confirmed_at ? ` · Confirmed ${new Date(staffMember.two_factor.confirmed_at).toLocaleString()}` : ''}`
                        : 'Not enabled'}
                </p>
                {staffMember && (
                    <p className="mt-1 text-xs text-slate-500">
                        Recovery codes: {staffMember.two_factor.recovery_codes_count}
                    </p>
                )}
            </div>
        </FormSection>
    </TabsContent>
</Tabs>
```

Form field names stay identical so no controller/test changes are needed.

- [x] **Step 2: Type-check + run staff tests**

Run: `npx tsc --noEmit -p tsconfig.json && vendor/bin/pest tests/Feature/Admin/StaffAdminTest.php`
Expected: No type errors; all staff tests pass.

- [x] **Step 3: Commit**

```bash
git add resources/js/pages/Admin/Staff/Edit.tsx
git commit -m "feat(admin/staff): split edit form into Account/Access/Permissions/Teams/2FA inner tabs"
```

---

## Task 8: Convert `Roles/Edit.tsx` to Definition/Permissions inner tabs

**Files:**
- Modify: `resources/js/pages/Admin/Roles/Edit.tsx`

Legacy splits role editing into `Definition` (name, notes) and `Permissions` (the matrix). Current page renders both as sequential sections. Wrap in tabs.

- [x] **Step 1: Add `Tabs` import + restructure**

In `resources/js/pages/Admin/Roles/Edit.tsx`:

1. Add the import after the existing `Button` import on line 11:

```ts
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
```

2. Replace the form body. Find the opening `<form onSubmit={handleSubmit}` on line 89 through the closing `</form>` on line 154. Replace lines 90-153 (the `<form>` children) with:

```tsx
<Tabs defaultValue="definition" className="space-y-6">
    <TabsList>
        <TabsTrigger value="definition">Definition</TabsTrigger>
        <TabsTrigger value="permissions">Permissions</TabsTrigger>
    </TabsList>

    <TabsContent value="definition">
        <FormSection
            title="Role Details"
            description="Basic information about this role."
            collapsible={false}
        >
            {/* KEEP UNCHANGED: the existing FormGrid with name + notes fields (lines 95-124) */}
        </FormSection>
    </TabsContent>

    <TabsContent value="permissions">
        <FormSection
            title="Permissions"
            description="Select the permissions granted to agents with this role."
            collapsible={false}
        >
            <PermissionMatrix
                groups={permissions}
                selectedPermissions={data.permissions}
                onChange={(selected) => setData('permissions', selected)}
            />
            {errors.permissions && (
                <p className="text-sm text-red-500 mt-4">{errors.permissions}</p>
            )}
        </FormSection>
    </TabsContent>
</Tabs>

<div className="flex items-center justify-end gap-4 pt-4">
    <Link
        href={route('admin.roles.index')}
        className={buttonVariants({ variant: 'outline' })}
    >
        Cancel
    </Link>
    <Button type="submit" disabled={processing}>
        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
        {isEdit ? 'Save Changes' : 'Create Role'}
    </Button>
</div>
```

- [x] **Step 2: Type-check + run role tests**

Run: `npx tsc --noEmit -p tsconfig.json && vendor/bin/pest tests/Feature/Admin/RoleAdminTest.php`
Expected: No type errors; all role tests pass.

- [x] **Step 3: Commit**

```bash
git add resources/js/pages/Admin/Roles/Edit.tsx
git commit -m "feat(admin/roles): split edit form into Definition/Permissions inner tabs"
```

---

## Task 9: Manual UI verification

**Files:** none — interactive verification only.

The earlier tasks ran type checks and PHP feature tests. Frontend layout changes need a browser pass.

- [x] **Step 1: Start the dev server**

Run: `npm run dev` (in one terminal) and `php artisan serve` (in another).
Wait until both are listening.

- [x] **Step 2: Verify the Agent → Admin switcher**

1. Log in as an admin staff member (`isadmin = 1`) — pick one from the seed/factory.
2. Land on `/scp` (dashboard).
3. Confirm the **Admin Panel** button appears in the header right cluster, before "My Queue".
4. Log out, log in as a non-admin staff member (`isadmin = 0`).
5. Confirm the **Admin Panel** button is NOT shown.

- [x] **Step 3: Verify the Admin → Agent switcher**

1. As an admin, click **Admin Panel**. You land on `/admin/help-topics`.
2. Confirm the header shows an **Agent Panel** button (replacing My Queue / New Ticket).
3. Click it; you return to `/scp`.

- [x] **Step 4: Verify the IA tabs and sub-strip**

1. Navigate to `/admin/staff`.
2. Confirm the **Agents** top tab is active (highlighted) and the **Agents** row in the sub-strip shows Agents/Teams/Roles/Departments with **Agents** highlighted.
3. Click each top tab — Dashboard / Settings / Manage / Emails / Agents — and confirm the sub-strip changes accordingly.
4. Confirm disabled items (System Logs, Audit Logs, Information, all Settings items, Schedules, API, Pages, Forms, Lists, Plugins, Settings/Banlist/Templates/Diagnostic under Emails) are visually muted and not clickable.
5. Confirm clicking an enabled sub-item navigates correctly: e.g., Manage → Filters loads `/admin/filters`.

- [x] **Step 5: Verify the inner tabs on each Edit page**

For each surface, open the **edit** page (visit the `/admin/{resource}` index, click into one row), and confirm:

| Page | Tabs | Behavior |
|---|---|---|
| `/admin/departments/{id}/edit` | Settings · Access | Settings has the form; Access has the placeholder |
| `/admin/staff/{id}/edit` | Account · Access · Permissions · Teams · 2FA | Each tab renders the relevant section; Permissions is the placeholder |
| `/admin/roles/{id}/edit` | Definition · Permissions | Definition has name+notes; Permissions has the matrix |
| `/admin/filters/{id}/edit` | (no inner tabs — single-form layout, kept) | Three FormSections render in order |
| `/admin/email-config/{id}/edit?type=account` | (no inner tabs) | Renders correct section per type |
| `/admin/help-topics/{id}/edit` | (no inner tabs — single-form layout, kept) | Form renders |
| `/admin/teams/{id}/edit` | (no inner tabs) | Form renders |
| `/admin/slas/{id}/edit` | (no inner tabs) | Form renders |
| `/admin/canned-responses/{id}/edit` | (no inner tabs) | Form renders |

- [x] **Step 6: Verify form submissions on a sample of pages**

Edit and save:
- A department (changes name) → save → list shows updated name.
- A staff member (toggles `isactive`) → save → list reflects.
- A role (renames) → save → list reflects.

Confirm no console errors and audit log entries are written (`SELECT * FROM scp_admin_audit_log ORDER BY id DESC LIMIT 5`).

- [x] **Step 7: If everything works, do not commit (no code change here). If anything is broken, fix and commit per the affected task.**

---

## Self-Review

**Spec coverage:**
- ✅ Panel switcher Agent → Admin (Task 1 + Task 5).
- ✅ Panel switcher Admin → Agent (Task 4).
- ✅ Replace flat sidebar with legacy 5-tab + sub-strip IA (Task 2 + Task 3 + Task 4).
- ✅ Fix dead `/scp/admin/...` hrefs (eliminated by Task 4 replacement; the old `ADMIN_NAV_ITEMS` array goes away).
- ✅ Edit-page inner tabs alignment for Department, Staff, Role (Tasks 6-8).
- ✅ Filter and EmailConfig retain current form structure (already matches legacy patterns; covered by Task 9 verification).
- ✅ Out-of-scope items render as disabled placeholders, not 404s (Task 2 constants `enabled: false`).
- ✅ Canned Responses divergence from legacy noted in constants comment (Task 2).

**Placeholder scan:** No "TODO", "TBD", or "implement later" tokens. All code blocks contain real code. Test code in Task 1 is concrete. Each Edit-page task names exact line ranges to move.

**Type consistency:**
- `auth.staff.isAdmin` is a boolean in HandleInertiaRequests (Task 1) and read as boolean in DashboardLayout (Task 5). ✓
- `AdminTopTab` and `AdminSubItem` interfaces from `AdminTabs.constants.ts` (Task 2) are used unchanged in `AdminTabs.tsx` (Task 3). ✓
- `activeAdminNav` prop on `AdminLayout` (Task 4) maps to `activeSubId` on `AdminTabs` (Task 3) — they are the same string namespace (`'staff'`, `'roles'`, `'help-topics'`, etc.). All existing pages set `activeAdminNav="staff"`, `"roles"`, `"departments"`, `"help-topics"`, `"filters"`, `"slas"`, `"teams"`, `"canned-responses"`, `"email-config"`. These IDs all appear in `ADMIN_TABS` submenus. ✓

---

## Out of scope

- New admin surfaces (Settings, Schedules, API, Pages, Forms, Lists, Plugins, Banlist, Diagnostic, Audit Logs UI). They render as disabled placeholders only.
- Browser/E2E tests for the new layout. Existing Pest feature tests cover the controller side. Manual verification (Task 9) covers the UI side per the Phase 2a testing strategy.
- Translations for new strings (`Admin Panel`, `Agent Panel`, tab labels). Defaults are inline; i18n keys can be added in a follow-up.
- Mobile sub-strip overflow polish beyond `overflow-x-auto`.

---

## Execution Handoff

**Plan complete and saved to `docs/superpowers/plans/2026-05-02-admin-panel-shell-and-ia.md`. Two execution options:**

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

**Which approach?**
