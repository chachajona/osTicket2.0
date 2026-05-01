import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
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
import { Delete01Icon, PencilEdit01Icon, PlusSignIcon } from '@hugeicons/core-free-icons';

declare global {
    function route(name: string, params?: any): string;
}

interface Department {
    id: number;
    name: string;
    manager_id: number | null;
    manager_name: string | null;
    sla_id: number | null;
    sla_name: string | null;
    ispublic: boolean;
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
    departments: PaginatedData<Department>;
}

export default function DepartmentsIndex({ departments }: Props) {
    const [departmentToDelete, setDepartmentToDelete] = useState<Department | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    const handleDelete = () => {
        if (!departmentToDelete) return;

        setDeleteError(null);

        router.delete(route('admin.departments.destroy', departmentToDelete.id), {
            onSuccess: () => {
                setDepartmentToDelete(null);
                setDeleteError(null);
            },
            onError: (errors) => {
                setDeleteError(errors.department ?? 'Unable to delete this department.');
            },
        });
    };

    return (
        <AdminLayout activeAdminNav="departments">
            <Head title="Departments" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Departments</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Manage department routing, managers, and default SLA assignments.
                    </p>
                </div>
                <Link href={route('admin.departments.create')} className={buttonVariants({ variant: 'default' })}>
                    <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                    Create Department
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Manager</TableHead>
                            <TableHead>SLA</TableHead>
                            <TableHead className="w-[140px] text-center">Visibility</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {departments.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="h-24 text-center text-slate-500">
                                    No departments found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            departments.data.map((department) => (
                                <TableRow key={department.id}>
                                    <TableCell className="font-medium text-slate-900">{department.name}</TableCell>
                                    <TableCell className="text-slate-500">
                                        {department.manager_name ?? (
                                            <span className="italic text-slate-400">Unassigned</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-slate-500">
                                        {department.sla_name ?? (
                                            <span className="italic text-slate-400">No SLA</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <span
                                            className={[
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                department.ispublic
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-amber-100 text-amber-700',
                                            ].join(' ')}
                                        >
                                            {department.ispublic ? 'Public' : 'Private'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.departments.edit', department.id)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`${buttonVariants({ variant: 'ghost', size: 'icon' })} text-red-600 hover:bg-red-50 hover:text-red-700`}
                                                onClick={() => {
                                                    setDeleteError(null);
                                                    setDepartmentToDelete(department);
                                                }}
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
                open={departmentToDelete !== null}
                onOpenChange={(isOpen) => {
                    if (!isOpen) {
                        setDepartmentToDelete(null);
                        setDeleteError(null);
                    }
                }}
                title="Delete Department"
                description={
                    <div className="space-y-3">
                        <p>
                            Are you sure you want to delete <strong>{departmentToDelete?.name}</strong>?
                        </p>
                        {deleteError && <p className="text-sm font-medium text-red-600">{deleteError}</p>}
                    </div>
                }
                confirmText="Delete Department"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </AdminLayout>
    );
}
