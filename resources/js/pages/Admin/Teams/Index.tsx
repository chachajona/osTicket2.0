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

interface Team {
    id: number;
    name: string;
    lead_name: string | null;
    member_count: number;
    status: boolean;
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
    teams: PaginatedData<Team>;
}

export default function TeamsIndex({ teams }: Props) {
    const [teamToDelete, setTeamToDelete] = useState<Team | null>(null);

    const handleDelete = () => {
        if (!teamToDelete) return;

        router.delete(route('admin.teams.destroy', teamToDelete.id), {
            onSuccess: () => setTeamToDelete(null),
        });
    };

    return (
        <>
            <Head title="Teams" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight text-slate-900">Teams</h1>
                    <p className="mt-1 text-sm text-slate-500">Manage team leads, members, and availability.</p>
                </div>
                <Link href={route('admin.teams.create')} className={buttonVariants({ variant: 'default' })}>
                    <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                    Create Team
                </Link>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Lead</TableHead>
                            <TableHead className="w-[140px] text-center">Members</TableHead>
                            <TableHead className="w-[140px] text-center">Status</TableHead>
                            <TableHead className="w-[100px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {teams.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={5} className="h-24 text-center text-slate-500">
                                    No teams found.
                                </TableCell>
                            </TableRow>
                        ) : (
                            teams.data.map((team) => (
                                <TableRow key={team.id}>
                                    <TableCell className="font-medium text-slate-900">{team.name}</TableCell>
                                    <TableCell className="text-slate-500">
                                        {team.lead_name ?? <span className="italic text-slate-400">Unassigned</span>}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <span className="inline-flex items-center justify-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                                            {team.member_count}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-center">
                                        <span
                                            className={[
                                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                team.status
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-slate-100 text-slate-600',
                                            ].join(' ')}
                                        >
                                            {team.status ? 'Active' : 'Disabled'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <Link
                                                href={route('admin.teams.edit', team.id)}
                                                className={buttonVariants({ variant: 'ghost', size: 'icon' })}
                                            >
                                                <HugeiconsIcon icon={PencilEdit01Icon} size={18} />
                                                <span className="sr-only">Edit</span>
                                            </Link>
                                            <button
                                                type="button"
                                                className={`${buttonVariants({ variant: 'ghost', size: 'icon' })} text-red-600 hover:bg-red-50 hover:text-red-700`}
                                                onClick={() => setTeamToDelete(team)}
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
                open={teamToDelete !== null}
                onOpenChange={(isOpen) => !isOpen && setTeamToDelete(null)}
                title="Delete Team"
                description={
                    <>
                        Are you sure you want to delete <strong>{teamToDelete?.name}</strong>? This removes all team
                        member assignments.
                    </>
                }
                confirmText="Delete Team"
                variant="destructive"
                onConfirm={handleDelete}
            />
        </>
    );
}

type TeamsIndexComponent = typeof TeamsIndex & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(TeamsIndex as TeamsIndexComponent).layout = appShellLayout;
