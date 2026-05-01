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

interface Filter {
    id: number;
    name: string;
    exec_order: number;
    isactive: boolean;
    rules_count: number;
    actions_count: number;
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
    filters: PaginatedData<Filter>;
}

export default function FiltersIndex({ filters }: Props) {
    const [filterToDelete, setFilterToDelete] = useState<Filter | null>(null);

    const handleDelete = () => {
        if (!filterToDelete) return;

        router.delete(route('admin.filters.destroy', filterToDelete.id), {
            onSuccess: () => setFilterToDelete(null),
        });
    };

    return (
        <AdminLayout activeAdminNav="filters">
            <Head title="Filters" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Filters</h1>
                    <p className="mt-1 text-sm text-slate-500">Manage automated filters, rule matching, and actions.</p>
                </div>
                <Link href={route('admin.filters.create')} className={buttonVariants({ variant: 'default' })}>
                    <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                    Create Filter
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead className="w-[120px] text-center">Exec Order</TableHead>
                            <TableHead className="w-[120px] text-center">Status</TableHead>
                            <TableHead className="w-[120px] text-center">Rules</TableHead>
                            <TableHead className="w-[120px] text-center">Actions</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {filters.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={6} className="h-24 text-center text-slate-500">
                                    No filters found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            filters.data.map((filter) => (
                                <TableRow key={filter.id}>
                                    <TableCell className="font-medium text-slate-900">{filter.name}</TableCell>
                                    <TableCell className="text-center text-slate-600">{filter.exec_order}</TableCell>
                                    <TableCell className="text-center">
                                        <span
                                            className={[
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                filter.isactive
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-600',
                                            ].join(' ')}
                                        >
                                            {filter.isactive ? 'Active' : 'Disabled'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <span className="inline-flex items-center justify-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                                            {filter.rules_count}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <span className="inline-flex items-center justify-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                                            {filter.actions_count}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.filters.edit', filter.id)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`${buttonVariants({ variant: 'ghost', size: 'icon' })} text-red-600 hover:bg-red-50 hover:text-red-700`}
                                                onClick={() => setFilterToDelete(filter)}
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
                open={filterToDelete !== null}
                onOpenChange={(isOpen) => !isOpen && setFilterToDelete(null)}
                title="Delete Filter"
                description={
                    <>
                        Are you sure you want to delete <strong>{filterToDelete?.name}</strong>? This removes all nested
                        rules and actions.
                    </>
                }
                confirmText="Delete Filter"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </AdminLayout>
    );
}
