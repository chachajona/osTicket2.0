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

interface HelpTopic {
    id: number;
    topic: string;
    department_name: string | null;
    sla_name: string | null;
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
    helpTopics: PaginatedData<HelpTopic>;
}

export default function HelpTopicsIndex({ helpTopics }: Props) {
    const [helpTopicToDelete, setHelpTopicToDelete] = useState<HelpTopic | null>(null);

    const handleDelete = () => {
        if (!helpTopicToDelete) return;

        router.delete(route('admin.help-topics.destroy', helpTopicToDelete.id), {
            onSuccess: () => setHelpTopicToDelete(null),
        });
    };

    return (
        <AdminLayout activeAdminNav="help-topics">
            <Head title="Help Topics" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Help Topics</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Manage ticket help topics, department defaults, and SLA routing.
                    </p>
                </div>
                <Link href={route('admin.help-topics.create')} className={buttonVariants({ variant: 'default' })}>
                    <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                    Create Help Topic
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Topic</TableHead>
                            <TableHead>Department</TableHead>
                            <TableHead>SLA</TableHead>
                            <TableHead className="w-[140px] text-center">Status</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {helpTopics.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="h-24 text-center text-slate-500">
                                    No help topics found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            helpTopics.data.map((helpTopic) => (
                                <TableRow key={helpTopic.id}>
                                    <TableCell className="font-medium text-slate-900">{helpTopic.topic}</TableCell>
                                    <TableCell className="text-slate-500">
                                        {helpTopic.department_name ?? (
                                            <span className="italic text-slate-400">Not assigned</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-slate-500">
                                        {helpTopic.sla_name ?? <span className="italic text-slate-400">No SLA</span>}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <span
                                            className={[
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                helpTopic.isactive
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-600',
                                            ].join(' ')}
                                        >
                                            {helpTopic.isactive ? 'Active' : 'Disabled'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.help-topics.edit', helpTopic.id)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`${buttonVariants({ variant: 'ghost', size: 'icon' })} text-red-600 hover:bg-red-50 hover:text-red-700`}
                                                onClick={() => setHelpTopicToDelete(helpTopic)}
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
                open={helpTopicToDelete !== null}
                onOpenChange={(isOpen) => !isOpen && setHelpTopicToDelete(null)}
                title="Delete Help Topic"
                description={
                    <>
                        Are you sure you want to delete <strong>{helpTopicToDelete?.topic}</strong>? This action cannot be
                        undone.
                    </>
                }
                confirmText="Delete Help Topic"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </AdminLayout>
    );
}
