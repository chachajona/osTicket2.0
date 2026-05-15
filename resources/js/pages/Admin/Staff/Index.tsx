import { Head, Link } from '@inertiajs/react';
import { appShellLayout } from '@/layouts/AppShell';
import { PageHeader } from '@/components/layout/PageHeader';
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
import { PencilEdit01Icon, PlusSignIcon } from '@hugeicons/core-free-icons';
import type { ReactElement } from 'react';

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
    return (
        <>
            <Head title="Staff" />

            <PageHeader
                title="Staff"
                subtitle="Manage agent accounts, department coverage, and team membership."
                headerActions={
                    <Link href={route('admin.staff.create')} className={buttonVariants({ variant: 'default' })}>
                        <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                        Create Staff
                    </Link>
                }
            />

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
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))
                        )}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

type StaffIndexComponent = typeof StaffIndex & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(StaffIndex as StaffIndexComponent).layout = appShellLayout;
