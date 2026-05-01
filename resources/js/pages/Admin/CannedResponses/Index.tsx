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

interface CannedResponse {
    id: number;
    title: string;
    department_name: string | null;
    isactive: boolean;
    notes: string | null;
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
    cannedResponses: PaginatedData<CannedResponse>;
}

export default function CannedResponsesIndex({ cannedResponses }: Props) {
    const [cannedResponseToDelete, setCannedResponseToDelete] = useState<CannedResponse | null>(null);

    const handleDelete = () => {
        if (!cannedResponseToDelete) return;

        router.delete(route('admin.canned-responses.destroy', cannedResponseToDelete.id), {
            onSuccess: () => setCannedResponseToDelete(null),
        });
    };

    return (
        <AdminLayout activeAdminNav="canned-responses">
            <Head title="Canned Responses" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Canned Responses</h1>
                    <p className="mt-1 text-sm text-slate-500">Manage reusable canned responses for agents.</p>
                </div>
                <Link
                    href={route('admin.canned-responses.create')}
                    className={buttonVariants({ variant: 'default' })}
                >
                    <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                    Create Canned Response
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[260px]">Title</TableHead>
                            <TableHead className="w-[180px]">Department</TableHead>
                            <TableHead className="w-[120px]">Status</TableHead>
                            <TableHead>Notes</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {cannedResponses.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="h-24 text-center text-slate-500">
                                    No canned responses found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            cannedResponses.data.map((cannedResponse) => (
                                <TableRow key={cannedResponse.id}>
                                    <TableCell className="font-medium text-slate-900">{cannedResponse.title}</TableCell>
                                    <TableCell className="text-slate-500">
                                        {cannedResponse.department_name ?? (
                                            <span className="italic text-slate-400">All Departments</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <span
                                            className={[
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                cannedResponse.isactive
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-slate-100 text-slate-600',
                                            ].join(' ')}
                                        >
                                            {cannedResponse.isactive ? 'Active' : 'Inactive'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="max-w-[320px] truncate text-slate-500">
                                        {cannedResponse.notes ?? <span className="italic text-slate-400">No notes</span>}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.canned-responses.edit', cannedResponse.id)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`${buttonVariants({ variant: 'ghost', size: 'icon' })} text-red-600 hover:bg-red-50 hover:text-red-700`}
                                                onClick={() => setCannedResponseToDelete(cannedResponse)}
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
                open={cannedResponseToDelete !== null}
                onOpenChange={(isOpen) => !isOpen && setCannedResponseToDelete(null)}
                title="Delete Canned Response"
                description={
                    <>
                        Are you sure you want to delete <strong>{cannedResponseToDelete?.title}</strong>?
                        This action cannot be undone.
                    </>
                }
                confirmText="Delete Canned Response"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </AdminLayout>
    );
}
