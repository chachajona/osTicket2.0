import { useMemo, useState } from 'react';
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
import { Delete01Icon, PencilEdit01Icon, PlusSignIcon } from '@hugeicons/core-free-icons';
import type { ReactElement } from 'react';

declare global {
    function route(name: string, params?: any): string;
}

type ConfigType = 'all' | 'account' | 'template' | 'group';

interface EmailConfigItem {
    id: number;
    key: string;
    type: Exclude<ConfigType, 'all'>;
    type_label: string;
    name: string;
    description: string;
    status: string;
}

interface Summary {
    accounts: number;
    templates: number;
    groups: number;
    total: number;
}

interface Props {
    items: EmailConfigItem[];
    summary: Summary;
    createUrls: Record<'account' | 'template' | 'group', string>;
}

const FILTERS: { key: ConfigType; label: string }[] = [
    { key: 'all', label: 'All' },
    { key: 'account', label: 'Mail Accounts' },
    { key: 'template', label: 'Templates' },
    { key: 'group', label: 'Template Groups' },
];

export default function EmailConfigIndex({ items, summary, createUrls }: Props) {
    const [filter, setFilter] = useState<ConfigType>('all');
    const [itemToDelete, setItemToDelete] = useState<EmailConfigItem | null>(null);

    const visibleItems = useMemo(
        () => (filter === 'all' ? items : items.filter((item) => item.type === filter)),
        [filter, items],
    );

    const handleDelete = () => {
        if (!itemToDelete) return;

        router.delete(route('admin.email-config.destroy', itemToDelete.key), {
            onSuccess: () => setItemToDelete(null),
        });
    };

    return (
        <>
            <Head title="Email Config" />

            <PageHeader
                title="Email Config"
                subtitle="Manage mail accounts, templates, and template groups from one admin surface."
                headerActions={
                    <div className="flex flex-wrap gap-2">
                        <Link href={createUrls.account} className={buttonVariants({ variant: 'default' })}>
                            <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                            New Mail Account
                        </Link>
                        <Link href={createUrls.template} className={buttonVariants({ variant: 'outline' })}>
                            New Template
                        </Link>
                        <Link href={createUrls.group} className={buttonVariants({ variant: 'outline' })}>
                            New Group
                        </Link>
                    </div>
                }
            />

            <div className="mb-6 grid gap-4 md:grid-cols-4">
                {[
                    { label: 'Total', value: summary.total },
                    { label: 'Mail Accounts', value: summary.accounts },
                    { label: 'Templates', value: summary.templates },
                    { label: 'Template Groups', value: summary.groups },
                ].map((card) => (
                    <div key={card.label} className="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-500">{card.label}</p>
                        <p className="mt-2 text-2xl font-semibold text-slate-900">{card.value}</p>
                    </div>
                ))}
            </div>

            <div className="mb-4 flex flex-wrap gap-2">
                {FILTERS.map((option) => {
                    const active = filter === option.key;

                    return (
                        <button
                            key={option.key}
                            type="button"
                            onClick={() => setFilter(option.key)}
                            className={[
                                'rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                                active ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                            ].join(' ')}
                        >
                            {option.label}
                        </button>
                    );
                })}
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-[180px]">Type</TableHead>
                            <TableHead className="w-[260px]">Name</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead className="w-[140px]">Status</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {visibleItems.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="h-24 text-center text-slate-500">
                                    No email config entries found for this filter.
                                </TableCell>
                            </TableRow>
                        ) : (
                            visibleItems.map((item) => (
                                <TableRow key={item.key}>
                                    <TableCell>
                                        <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                            {item.type_label}
                                        </span>
                                    </TableCell>
                                    <TableCell className="font-medium text-slate-900">{item.name}</TableCell>
                                    <TableCell className="text-slate-500">{item.description || '—'}</TableCell>
                                    <TableCell className="text-slate-500">{item.status}</TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.email-config.edit', item.key)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`${buttonVariants({ variant: 'ghost', size: 'icon' })} text-red-600 hover:bg-red-50 hover:text-red-700`}
                                                onClick={() => setItemToDelete(item)}
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
                open={itemToDelete !== null}
                onOpenChange={(open) => !open && setItemToDelete(null)}
                title="Delete Email Config"
                description={
                    <>
                        Are you sure you want to delete <strong>{itemToDelete?.name}</strong>?
                    </>
                }
                confirmText="Delete"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </>
    );
}

type EmailConfigIndexComponent = typeof EmailConfigIndex & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(EmailConfigIndex as EmailConfigIndexComponent).layout = appShellLayout;
