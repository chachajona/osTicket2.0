import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon, Delete01Icon, FloppyDiskIcon, PlusSignIcon } from '@hugeicons/core-free-icons';

declare global {
    function route(name: string, params?: any): string;
}

interface Option {
    id: number;
    name: string;
}

interface DepartmentAccessRow {
    dept_id: number;
    role_id: number;
}

interface StaffMember {
    id: number;
    username: string;
    firstname: string;
    lastname: string;
    email: string;
    phone: string | null;
    mobile: string | null;
    signature: string | null;
    dept_id: number;
    role_id: number | null;
    isactive: boolean;
    isadmin: boolean;
    isvisible: boolean;
    change_passwd: boolean;
    dept_access: DepartmentAccessRow[];
    teams: number[];
    two_factor: {
        enabled: boolean;
        confirmed_at: string | null;
        recovery_codes_count: number;
    };
}

interface Props {
    staffMember?: StaffMember | null;
    departmentOptions: Option[];
    roleOptions: Option[];
    teamOptions: Option[];
}

function SelectField({
    id,
    label,
    value,
    options,
    placeholder,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    options: Option[];
    placeholder: string;
    error?: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <select
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={[
                    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                    error ? 'border-red-500 focus-visible:ring-red-500' : '',
                ].join(' ')}
            >
                <option value="">{placeholder}</option>
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.name}
                    </option>
                ))}
            </select>
            {error && <p className="text-sm text-red-500">{error}</p>}
        </div>
    );
}

export default function StaffEdit({ staffMember, departmentOptions, roleOptions, teamOptions }: Props) {
    const isEdit = !!staffMember;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const { data, setData, post, patch, processing, errors } = useForm({
        username: staffMember?.username ?? '',
        firstname: staffMember?.firstname ?? '',
        lastname: staffMember?.lastname ?? '',
        email: staffMember?.email ?? '',
        phone: staffMember?.phone ?? '',
        mobile: staffMember?.mobile ?? '',
        signature: staffMember?.signature ?? '',
        dept_id: staffMember?.dept_id ? String(staffMember.dept_id) : '',
        role_id: staffMember?.role_id ? String(staffMember.role_id) : '',
        password: '',
        isactive: staffMember?.isactive ?? true,
        isadmin: staffMember?.isadmin ?? false,
        isvisible: staffMember?.isvisible ?? true,
        change_passwd: staffMember?.change_passwd ?? false,
        dept_access: staffMember?.dept_access.map((access) => ({
            dept_id: String(access.dept_id),
            role_id: String(access.role_id),
        })) ?? [],
        teams: staffMember?.teams.map(String) ?? [],
    });

    const handleDelete = () => {
        if (!staffMember) return;

        router.delete(route('admin.staff.destroy', staffMember.id));
    };

    const addDepartmentAccess = () => {
        setData('dept_access', [...data.dept_access, { dept_id: '', role_id: '' }]);
    };

    const updateDepartmentAccess = (index: number, field: 'dept_id' | 'role_id', value: string) => {
        setData(
            'dept_access',
            data.dept_access.map((access, currentIndex) =>
                currentIndex === index ? { ...access, [field]: value } : access,
            ),
        );
    };

    const removeDepartmentAccess = (index: number) => {
        setData(
            'dept_access',
            data.dept_access.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    return (
        <AdminLayout activeAdminNav="staff">
            <Head title={isEdit && staffMember ? `Edit Staff: ${staffMember.username}` : 'Create Staff'} />

            <div className="mb-6">
                <Link
                    href={route('admin.staff.index')}
                    className="mb-4 inline-flex items-center text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} size={16} className="mr-1" />
                    Back to Staff
                </Link>

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {isEdit ? 'Edit Staff Member' : 'Create Staff Member'}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Manage core profile data, access, team membership, and password settings.
                        </p>
                    </div>

                    {isEdit && staffMember && (
                        <Button
                            type="button"
                            variant="outline"
                            className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                            onClick={() => setShowDeleteConfirm(true)}
                        >
                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                            Delete Staff
                        </Button>
                    )}
                </div>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();

                    if (isEdit && staffMember) {
                        patch(route('admin.staff.update', staffMember.id));

                        return;
                    }

                    post(route('admin.staff.store'));
                }}
                className="space-y-6"
            >
                <FormSection title="Basic Info" description="Core identity and contact details." collapsible={false}>
                    <FormGrid columns={2} className="max-w-5xl">
                        <div className="space-y-2">
                            <Label htmlFor="username">Username</Label>
                            <Input id="username" value={data.username} onChange={(e) => setData('username', e.target.value)} />
                            {errors.username && <p className="text-sm text-red-500">{errors.username}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            {errors.email && <p className="text-sm text-red-500">{errors.email}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="firstname">First Name</Label>
                            <Input id="firstname" value={data.firstname} onChange={(e) => setData('firstname', e.target.value)} />
                            {errors.firstname && <p className="text-sm text-red-500">{errors.firstname}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="lastname">Last Name</Label>
                            <Input id="lastname" value={data.lastname} onChange={(e) => setData('lastname', e.target.value)} />
                            {errors.lastname && <p className="text-sm text-red-500">{errors.lastname}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="phone">Phone</Label>
                            <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                            {errors.phone && <p className="text-sm text-red-500">{errors.phone}</p>}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="mobile">Mobile</Label>
                            <Input id="mobile" value={data.mobile} onChange={(e) => setData('mobile', e.target.value)} />
                            {errors.mobile && <p className="text-sm text-red-500">{errors.mobile}</p>}
                        </div>
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="signature">Signature</Label>
                            <Textarea
                                id="signature"
                                rows={4}
                                value={data.signature}
                                onChange={(e) => setData('signature', e.target.value)}
                            />
                            {errors.signature && <p className="text-sm text-red-500">{errors.signature}</p>}
                        </div>
                    </FormGrid>
                </FormSection>

                <FormSection title="Account" description="Primary role, password, visibility, and admin flags." collapsible={false}>
                    <FormGrid columns={2} className="max-w-5xl">
                        <SelectField
                            id="dept_id"
                            label="Primary Department"
                            value={data.dept_id}
                            options={departmentOptions}
                            placeholder="Select a department"
                            error={errors.dept_id}
                            onChange={(value) => setData('dept_id', value)}
                        />
                        <SelectField
                            id="role_id"
                            label="Role"
                            value={data.role_id}
                            options={roleOptions}
                            placeholder="Select a role"
                            error={errors.role_id}
                            onChange={(value) => setData('role_id', value)}
                        />

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="password">{isEdit ? 'Set / Reset Password' : 'Password'}</Label>
                            <Input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder={isEdit ? 'Leave blank to keep the current password' : 'Minimum 8 characters'}
                            />
                            {errors.password && <p className="text-sm text-red-500">{errors.password}</p>}
                        </div>

                        <div className="rounded-lg border border-slate-200 p-4 md:col-span-2">
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

                        {[
                            ['isactive', 'Active account'],
                            ['isadmin', 'Admin access'],
                            ['isvisible', 'Visible in staff lists'],
                            ['change_passwd', 'Require password change'],
                        ].map(([field, label]) => (
                            <div key={field} className="flex min-h-10 items-center gap-3 rounded-md border border-slate-200 px-3">
                                <Checkbox
                                    id={field}
                                    checked={data[field as keyof typeof data] as boolean}
                                    onCheckedChange={(checked) =>
                                        setData(field as 'isactive' | 'isadmin' | 'isvisible' | 'change_passwd', checked === true)
                                    }
                                />
                                <Label htmlFor={field} className="cursor-pointer text-sm font-medium text-slate-700">
                                    {label}
                                </Label>
                            </div>
                        ))}
                    </FormGrid>
                </FormSection>

                <FormSection
                    title="Department Access"
                    description="Add department-specific role overrides beyond the primary department."
                    collapsible={false}
                >
                    <div className="max-w-5xl space-y-4">
                        {data.dept_access.map((access, index) => (
                            <div key={`${index}-${access.dept_id}-${access.role_id}`} className="grid gap-4 rounded-lg border border-slate-200 p-4 md:grid-cols-[1fr_1fr_auto]">
                                <SelectField
                                    id={`dept_access_${index}_dept_id`}
                                    label="Department"
                                    value={access.dept_id}
                                    options={departmentOptions.filter((department) => String(department.id) !== data.dept_id)}
                                    placeholder="Select department"
                                    error={errors[`dept_access.${index}.dept_id`]}
                                    onChange={(value) => updateDepartmentAccess(index, 'dept_id', value)}
                                />
                                <SelectField
                                    id={`dept_access_${index}_role_id`}
                                    label="Role Override"
                                    value={access.role_id}
                                    options={roleOptions}
                                    placeholder="Select role"
                                    error={errors[`dept_access.${index}.role_id`]}
                                    onChange={(value) => updateDepartmentAccess(index, 'role_id', value)}
                                />
                                <div className="flex items-end">
                                    <Button type="button" variant="outline" onClick={() => removeDepartmentAccess(index)}>
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        ))}

                        <Button type="button" variant="outline" onClick={addDepartmentAccess}>
                            <HugeiconsIcon icon={PlusSignIcon} size={16} className="mr-2" />
                            Add Department Access
                        </Button>
                    </div>
                </FormSection>

                <FormSection title="Teams" description="Assign team memberships for this staff member." collapsible={false}>
                    <div className="max-w-5xl space-y-2">
                        <Label htmlFor="teams">Team Membership</Label>
                        <select
                            id="teams"
                            multiple
                            value={data.teams}
                            onChange={(event) => setData('teams', Array.from(event.target.selectedOptions, (option) => option.value))}
                            className="flex min-h-56 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            {teamOptions.map((team) => (
                                <option key={team.id} value={team.id}>
                                    {team.name}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-slate-500">
                            Hold Command on macOS or Control on Windows/Linux to select multiple teams.
                        </p>
                        {errors.teams && <p className="text-sm text-red-500">{errors.teams}</p>}
                    </div>
                </FormSection>

                <div className="flex items-center justify-end gap-4 pt-4">
                    <Link href={route('admin.staff.index')} className={buttonVariants({ variant: 'outline' })}>
                        Cancel
                    </Link>
                    <Button type="submit" disabled={processing}>
                        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
                        {isEdit ? 'Save Changes' : 'Create Staff'}
                    </Button>
                </div>
            </form>

            {isEdit && staffMember && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={setShowDeleteConfirm}
                    title="Delete Staff Member"
                    description={
                        <>
                            Are you sure you want to delete <strong>{staffMember.username}</strong>? This action cannot be
                            undone.
                        </>
                    }
                    confirmText="Delete Staff"
                    variant="destructive"
                    onConfirm={handleDelete}
                />
            )}
        </AdminLayout>
    );
}
