import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { appShellLayout } from '@/layouts/AppShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { buttonVariants } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { HugeiconsIcon } from '@hugeicons/react';
import { PlusSignIcon, PencilEdit01Icon, Delete01Icon } from '@hugeicons/core-free-icons';
import type { ReactElement } from 'react';

declare global {
    function route(name: string, params?: any): string;
}

interface Role {
    id: number;
    name: string;
    notes: string | null;
    permissions_count: number;
}

interface PaginatedData<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    roles: PaginatedData<Role>;
}

export default function RolesIndex({ roles }: Props) {
    const [roleToDelete, setRoleToDelete] = useState<Role | null>(null);

    const handleDelete = () => {
        if (!roleToDelete) return;

        router.delete(route('admin.roles.destroy', roleToDelete.id), {
            onSuccess: () => setRoleToDelete(null),
        });
    };

    return (
        <>
            <Head title="Roles" />

            <PageHeader
                title="Roles"
                subtitle="Manage system roles and their permissions."
                headerActions={
                    <Link href={route('admin.roles.create')} className={buttonVariants({ variant: 'default' })}>
                        <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                        Create Role
                    </Link>
                }
            />

            <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[200px]">Name</TableHead>
                            <TableHead>Notes</TableHead>
                            <TableHead className="w-[150px] text-center">Permissions</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {roles.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={4} className="h-24 text-center text-slate-500">
                                    No roles found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            roles.data.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell className="font-medium text-slate-900">
                                        {role.name}
                                    </TableCell>
                                    <TableCell className="text-slate-500 truncate max-w-[300px]">
                                        {role.notes || <span className="italic text-slate-400">No notes</span>}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <div className="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800">
                                            {role.permissions_count}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.roles.edit', role.id)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' }) + " text-red-600 hover:text-red-700 hover:bg-red-50"}
                                                onClick={() => setRoleToDelete(role)}
                                            >
                                                <HugeiconsIcon icon={Delete01Icon} size={18} />
                                                <span className="sr-only">Delete</span>
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>

            <ConfirmDialog
                open={roleToDelete !== null}
                onOpenChange={(isOpen) => !isOpen && setRoleToDelete(null)}
                title="Delete Role"
                description={
                    <>
                        Are you sure you want to delete the <strong>{roleToDelete?.name}</strong> role?
                        This action cannot be undone and may affect users assigned to this role.
                    </>
                }
                confirmText="Delete Role"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </>
    );
}

type RolesIndexComponent = typeof RolesIndex & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(RolesIndex as RolesIndexComponent).layout = appShellLayout;
