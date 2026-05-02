import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { appShellLayout } from '@/layouts/AppShell';
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
import type { ReactElement } from 'react';

declare global {
    function route(name: string, params?: any): string;
}

interface Sla {
    id: number;
    name: string;
    grace_period: number;
    schedule: string | null;
    schedule_id: number | null;
    notes: string | null;
    flags: number;
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
    slas: PaginatedData<Sla>;
}

export default function SlasIndex({ slas }: Props) {
    const [slaToDelete, setSlaToDelete] = useState<Sla | null>(null);

    const handleDelete = () => {
        if (!slaToDelete) return;

        router.delete(route('admin.slas.destroy', slaToDelete.id), {
            onSuccess: () => setSlaToDelete(null),
        });
    };

    return (
        <>
            <Head title="SLA Plans" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">SLA Plans</h1>
                    <p className="mt-1 text-sm text-slate-500">Manage response grace periods and schedule assignments.</p>
                </div>
                <Link href={route('admin.slas.create')} className={buttonVariants({ variant: 'default' })}>
                    <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                    Create SLA
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[220px]">Name</TableHead>
                            <TableHead className="w-[140px]">Grace Period</TableHead>
                            <TableHead>Schedule</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {slas.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={4} className="h-24 text-center text-slate-500">
                                    No SLA plans found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            slas.data.map((sla) => (
                                <TableRow key={sla.id}>
                                    <TableCell className="font-medium text-slate-900">{sla.name}</TableCell>
                                    <TableCell className="text-slate-600">{sla.grace_period}</TableCell>
                                    <TableCell className="text-slate-500">
                                        {sla.schedule ?? <span className="italic text-slate-400">No schedule</span>}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.slas.edit', sla.id)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`${buttonVariants({ variant: 'ghost', size: 'icon' })} text-red-600 hover:bg-red-50 hover:text-red-700`}
                                                onClick={() => setSlaToDelete(sla)}
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
                open={slaToDelete !== null}
                onOpenChange={(isOpen) => !isOpen && setSlaToDelete(null)}
                title="Delete SLA"
                description={
                    <>
                        Are you sure you want to delete <strong>{slaToDelete?.name}</strong>? This action cannot be undone.
                    </>
                }
                confirmText="Delete SLA"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </>
    );
}

type SlasIndexComponent = typeof SlasIndex & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(SlasIndex as SlasIndexComponent).layout = appShellLayout;
