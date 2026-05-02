import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { appShellLayout } from '@/layouts/AppShell';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon, Delete01Icon, FloppyDiskIcon } from '@hugeicons/core-free-icons';
import type { ReactElement } from 'react';

declare global {
    function route(name: string, params?: any): string;
}

interface Option {
    id: number;
    name: string;
}

interface Department {
    id: number;
    name: string;
    manager_id: number | null;
    manager_name: string | null;
    sla_id: number | null;
    sla_name: string | null;
    email_id: number | null;
    email_name: string | null;
    template_id: number | null;
    template_name: string | null;
    dept_id: number | null;
    parent_name: string | null;
    signature: string | null;
    ispublic: boolean;
}

interface Props {
    department?: Department | null;
    departmentOptions: Option[];
    slaOptions: Option[];
    managerOptions: Option[];
    emailOptions: Option[];
    templateOptions: Option[];
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

export default function DepartmentsEdit({
    department,
    departmentOptions,
    slaOptions,
    managerOptions,
    emailOptions,
    templateOptions,
}: Props) {
    const isEdit = !!department;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    const { data, setData, post, patch, processing, errors } = useForm({
        name: department?.name ?? '',
        dept_id: department?.dept_id ? String(department.dept_id) : '',
        sla_id: department?.sla_id ? String(department.sla_id) : '',
        manager_id: department?.manager_id ? String(department.manager_id) : '',
        email_id: department?.email_id ? String(department.email_id) : '',
        template_id: department?.template_id ? String(department.template_id) : '',
        signature: department?.signature ?? '',
        ispublic: department?.ispublic ?? true,
    });

    const handleDelete = () => {
        if (!department) return;

        setDeleteError(null);

        router.delete(route('admin.departments.destroy', department.id), {
            onError: (formErrors) => {
                setDeleteError(formErrors.department ?? 'Unable to delete this department.');
            },
        });
    };

    return (
        <>
            <Head title={isEdit && department ? `Edit Department: ${department.name}` : 'Create Department'} />

            <div className="mb-6">
                <Link
                    href={route('admin.departments.index')}
                    className="mb-4 inline-flex items-center text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} size={16} className="mr-1" />
                    Back to Departments
                </Link>

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {isEdit ? 'Edit Department' : 'Create Department'}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {isEdit
                                ? 'Update assignment defaults, routing ownership, and customer-facing signature settings.'
                                : 'Create a new department for ticket routing and operational ownership.'}
                        </p>
                    </div>

                    {isEdit && department && (
                        <Button
                            type="button"
                            variant="outline"
                            className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                            onClick={() => {
                                setDeleteError(null);
                                setShowDeleteConfirm(true);
                            }}
                        >
                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                            Delete Department
                        </Button>
                    )}
                </div>
            </div>

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
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="name">Department Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="e.g. Customer Support"
                                className={errors.name ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                        </div>

                        <SelectField
                            id="dept_id"
                            label="Parent Department"
                            value={data.dept_id}
                            options={departmentOptions}
                            placeholder="No parent department"
                            error={errors.dept_id}
                            onChange={(value) => setData('dept_id', value)}
                        />

                        <SelectField
                            id="sla_id"
                            label="Default SLA"
                            value={data.sla_id}
                            options={slaOptions}
                            placeholder="No default SLA"
                            error={errors.sla_id}
                            onChange={(value) => setData('sla_id', value)}
                        />

                        <SelectField
                            id="manager_id"
                            label="Department Manager"
                            value={data.manager_id}
                            options={managerOptions}
                            placeholder="No manager assigned"
                            error={errors.manager_id}
                            onChange={(value) => setData('manager_id', value)}
                        />

                        <SelectField
                            id="email_id"
                            label="Default Email Address"
                            value={data.email_id}
                            options={emailOptions}
                            placeholder="No default email"
                            error={errors.email_id}
                            onChange={(value) => setData('email_id', value)}
                        />

                        <SelectField
                            id="template_id"
                            label="Email Template Group"
                            value={data.template_id}
                            options={templateOptions}
                            placeholder="No template group"
                            error={errors.template_id}
                            onChange={(value) => setData('template_id', value)}
                        />

                        <div className="space-y-2">
                            <Label htmlFor="ispublic" className="block">
                                Visibility
                            </Label>
                            <div className="flex min-h-10 items-center gap-3 rounded-md border border-slate-200 px-3">
                                <Checkbox
                                    id="ispublic"
                                    checked={data.ispublic}
                                    onCheckedChange={(checked) => setData('ispublic', checked === true)}
                                />
                                <Label htmlFor="ispublic" className="cursor-pointer text-sm font-medium text-slate-700">
                                    Public department
                                </Label>
                            </div>
                            <p className="text-xs text-slate-500">
                                Public departments can be exposed for customer-facing routing and visibility.
                            </p>
                            {errors.ispublic && <p className="text-sm text-red-500">{errors.ispublic}</p>}
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="signature">Signature</Label>
                            <Textarea
                                id="signature"
                                value={data.signature}
                                onChange={(event) => setData('signature', event.target.value)}
                                rows={6}
                                placeholder="Optional email signature for this department..."
                                className={errors.signature ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.signature && <p className="text-sm text-red-500">{errors.signature}</p>}
                        </div>
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

                <div className="flex items-center justify-end gap-4 pt-4">
                    <Link href={route('admin.departments.index')} className={buttonVariants({ variant: 'outline' })}>
                        Cancel
                    </Link>
                    <Button type="submit" disabled={processing}>
                        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
                        {isEdit ? 'Save Changes' : 'Create Department'}
                    </Button>
                </div>
            </Tabs>
        </form>

            {isEdit && department && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={(open) => {
                        setShowDeleteConfirm(open);

                        if (!open) {
                            setDeleteError(null);
                        }
                    }}
                    title="Delete Department"
                    description={
                        <div className="space-y-3">
                            <p>
                                Are you sure you want to delete <strong>{department.name}</strong>?
                            </p>
                            {deleteError && <p className="text-sm font-medium text-red-600">{deleteError}</p>}
                        </div>
                    }
                    confirmText="Delete Department"
                    variant="destructive"
                    onConfirm={handleDelete}
                />
            )}
        </>
    );
}

type DepartmentsEditComponent = typeof DepartmentsEdit & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(DepartmentsEdit as DepartmentsEditComponent).layout = appShellLayout;
