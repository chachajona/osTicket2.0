import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { PermissionMatrix, type PermissionGroup } from '@/components/admin/PermissionMatrix';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { buttonVariants, Button } from '@/components/ui/button';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon, FloppyDiskIcon, Delete01Icon } from '@hugeicons/core-free-icons';

declare global {
    function route(name: string, params?: any): string;
}

interface Role {
    id: number;
    name: string;
    notes: string | null;
}

interface Props {
    role?: Role;
    permissions: PermissionGroup[];
    selectedPermissions: string[];
}

export default function RolesEdit({ role, permissions, selectedPermissions }: Props) {
    const isEdit = !!role;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const { data, setData, post, patch, processing, errors } = useForm({
        name: role?.name || '',
        notes: role?.notes || '',
        permissions: selectedPermissions || [],
    });

    const handleSubmit = (e: React.SyntheticEvent) => {
        e.preventDefault();
        if (isEdit) {
            patch(route('admin.roles.update', role.id));
        } else {
            post(route('admin.roles.store'));
        }
    };

    const handleDelete = () => {
        if (!role) return;
        router.delete(route('admin.roles.destroy', role.id));
    };

    return (
        <AdminLayout activeAdminNav="roles">
            <Head title={isEdit ? `Edit Role: ${role.name}` : 'Create Role'} />

            <div className="mb-6">
                <Link
                    href={route('admin.roles.index')}
                    className="inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-900 mb-4 transition-colors"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} size={16} className="mr-1" />
                    Back to Roles
                </Link>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {isEdit ? 'Edit Role' : 'Create Role'}
                        </h1>
                        <p className="text-sm text-slate-500 mt-1">
                            {isEdit ? 'Update role details and permissions.' : 'Create a new role to assign to agents.'}
                        </p>
                    </div>
                    {isEdit && (
                        <Button
                            variant="outline"
                            className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                            onClick={() => setShowDeleteConfirm(true)}
                        >
                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                            Delete Role
                        </Button>
                    )}
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <FormSection
                    title="Role Details"
                    description="Basic information about this role."
                    collapsible={false}
                >
                    <FormGrid columns={1} className="max-w-3xl">
                        <div className="space-y-2">
                            <Label htmlFor="name">Role Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g. Support Manager"
                                className={errors.name ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.name && (
                                <p className="text-sm text-red-500">{errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="notes">Internal Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                placeholder="Optional notes about when to use this role..."
                                rows={3}
                                className={errors.notes ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.notes && (
                                <p className="text-sm text-red-500">{errors.notes}</p>
                            )}
                        </div>
                    </FormGrid>
                </FormSection>

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
            </form>

            {isEdit && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={setShowDeleteConfirm}
                    title="Delete Role"
                    description={
                        <>
                            Are you sure you want to delete the <strong>{role.name}</strong> role?
                            This action cannot be undone. Agents using this role may lose access.
                        </>
                    }
                    confirmText="Delete Role"
                    variant="destructive"
                    onConfirm={handleDelete}
                />
            )}
        </AdminLayout>
    );
}
