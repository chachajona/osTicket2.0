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

interface StaffRow {
    id: number;
    username: string;
    name: string;
    email: string;
    department: string | null;
    role: string | null;
    isactive: boolean;
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
    staff: PaginatedData<StaffRow>;
}

export default function StaffIndex({ staff }: Props) {
    const [staffToDelete, setStaffToDelete] = useState<StaffRow | null>(null);

    const handleDelete = () => {
        if (!staffToDelete) return;

        router.delete(route('admin.staff.destroy', staffToDelete.id), {
            onSuccess: () => setStaffToDelete(null),
        });
    };

    return (
        <AdminLayout activeAdminNav="staff">
            <Head title="Staff" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Staff</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Manage agent accounts, department coverage, and team membership.
                    </p>
                </div>
                <Link href={route('admin.staff.create')} className={buttonVariants({ variant: 'default' })}>
                    <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                    Create Staff
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Username</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>Role</TableHead>
                            <TableHead className="w-[120px] text-center">Status</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {staff.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={7} className="h-24 text-center text-slate-500">
                                    No staff found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            staff.data.map((member) => (
                                <TableRow key={member.id}>
                                    <TableCell className="font-medium text-slate-900">{member.username}</TableCell>
                                    <TableCell>{member.name}</TableCell>
                                    <TableCell className="text-slate-500">{member.email}</TableCell>
                                    <TableCell>{member.department ?? '—'}</TableCell>
                                    <TableCell>{member.role ?? '—'}</TableCell>
                                    <TableCell className="text-center">
                                        <span
                                            className={[
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                member.isactive
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-600',
                                            ].join(' ')}
                                        >
                                            {member.isactive ? 'Active' : 'Inactive'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.staff.edit', member.id)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`${buttonVariants({ variant: 'ghost', size: 'icon' })} text-red-600 hover:bg-red-50 hover:text-red-700`}
                                                onClick={() => setStaffToDelete(member)}
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
                open={staffToDelete !== null}
                onOpenChange={(isOpen) => !isOpen && setStaffToDelete(null)}
                title="Delete Staff Member"
                description={
                    <>
                        Are you sure you want to delete <strong>{staffToDelete?.name}</strong>? This removes department
                        access and team membership.
                    </>
                }
                confirmText="Delete Staff"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </AdminLayout>
    );
}
