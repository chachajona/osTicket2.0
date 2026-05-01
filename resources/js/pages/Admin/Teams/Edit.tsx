import { useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AdminLayout } from '@/components/admin/AdminLayout';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon, Delete01Icon, FloppyDiskIcon } from '@hugeicons/core-free-icons';

declare global {
    function route(name: string, params?: any): string;
}

interface StaffOption {
    id: number;
    name: string;
}

interface Team {
    id: number;
    name: string;
    lead_id: number | null;
    notes: string | null;
    status: boolean;
    member_ids: number[];
}

interface Props {
    team?: Team | null;
    staffOptions: StaffOption[];
}

export default function TeamsEdit({ team, staffOptions }: Props) {
    const isEdit = !!team;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const { data, setData, post, patch, processing, errors } = useForm({
        name: team?.name ?? '',
        lead_id: team?.lead_id ? String(team.lead_id) : '',
        notes: team?.notes ?? '',
        status: team?.status ?? true,
        members: team?.member_ids.map(String) ?? [],
    });

    const selectedMemberCount = useMemo(() => data.members.length, [data.members.length]);

    const handleDelete = () => {
        if (!team) return;

        router.delete(route('admin.teams.destroy', team.id));
    };

    return (
        <AdminLayout activeAdminNav="teams">
            <Head title={isEdit && team ? `Edit Team: ${team.name}` : 'Create Team'} />

            <div className="mb-6">
                <Link
                    href={route('admin.teams.index')}
                    className="mb-4 inline-flex items-center text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} size={16} className="mr-1" />
                    Back to Teams
                </Link>

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {isEdit ? 'Edit Team' : 'Create Team'}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {isEdit
                                ? 'Update the team lead, membership, and availability.'
                                : 'Create a new team for routing and assignment.'}
                        </p>
                    </div>

                    {isEdit && team && (
                        <Button
                            type="button"
                            variant="outline"
                            className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                            onClick={() => setShowDeleteConfirm(true)}
                        >
                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                            Delete Team
                        </Button>
                    )}
                </div>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();

                    if (isEdit && team) {
                        patch(route('admin.teams.update', team.id));

                        return;
                    }

                    post(route('admin.teams.store'));
                }}
                className="space-y-6"
            >
                <FormSection
                    title="Team Details"
                    description="Define the team name, lead, and internal notes."
                    collapsible={false}
                >
                    <FormGrid columns={2} className="max-w-4xl">
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="name">Team Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="e.g. Escalations"
                                className={errors.name ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="lead_id">Team Lead</Label>
                            <select
                                id="lead_id"
                                value={data.lead_id}
                                onChange={(event) => setData('lead_id', event.target.value)}
                                className={[
                                    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                    errors.lead_id ? 'border-red-500 focus-visible:ring-red-500' : '',
                                ].join(' ')}
                            >
                                <option value="">No lead assigned</option>
                                {staffOptions.map((staff) => (
                                    <option key={staff.id} value={staff.id}>
                                        {staff.name}
                                    </option>
                                ))}
                            </select>
                            {errors.lead_id && <p className="text-sm text-red-500">{errors.lead_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="status" className="block">
                                Team Status
                            </Label>
                            <div className="flex min-h-10 items-center gap-3 rounded-md border border-slate-200 px-3">
                                <Checkbox
                                    id="status"
                                    checked={data.status}
                                    onCheckedChange={(checked) => setData('status', checked === true)}
                                />
                                <Label htmlFor="status" className="cursor-pointer text-sm font-medium text-slate-700">
                                    Active team
                                </Label>
                            </div>
                            {errors.status && <p className="text-sm text-red-500">{errors.status}</p>}
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="notes">Internal Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                placeholder="Optional notes about routing or ownership..."
                                rows={4}
                                className={errors.notes ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.notes && <p className="text-sm text-red-500">{errors.notes}</p>}
                        </div>
                    </FormGrid>
                </FormSection>

                <FormSection
                    title="Members"
                    description="Assign one or more active agents to this team."
                    collapsible={false}
                >
                    <div className="max-w-4xl space-y-2">
                        <div className="flex items-center justify-between">
                            <Label htmlFor="members">Assigned Members</Label>
                            <span className="text-sm text-slate-500">{selectedMemberCount} selected</span>
                        </div>
                        <select
                            id="members"
                            multiple
                            value={data.members}
                            onChange={(event) =>
                                setData(
                                    'members',
                                    Array.from(event.target.selectedOptions, (option) => option.value),
                                )
                            }
                            className={[
                                'flex min-h-56 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                errors.members ? 'border-red-500 focus-visible:ring-red-500' : '',
                            ].join(' ')}
                        >
                            {staffOptions.map((staff) => (
                                <option key={staff.id} value={staff.id}>
                                    {staff.name}
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-slate-500">
                            Hold Command on macOS or Control on Windows/Linux to select multiple members.
                        </p>
                        {errors.members && <p className="text-sm text-red-500">{errors.members}</p>}
                        {errors['members.0'] && <p className="text-sm text-red-500">{errors['members.0']}</p>}
                    </div>
                </FormSection>

                <div className="flex items-center justify-end gap-4 pt-4">
                    <Link href={route('admin.teams.index')} className={buttonVariants({ variant: 'outline' })}>
                        Cancel
                    </Link>
                    <Button type="submit" disabled={processing}>
                        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
                        {isEdit ? 'Save Changes' : 'Create Team'}
                    </Button>
                </div>
            </form>

            {isEdit && team && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={setShowDeleteConfirm}
                    title="Delete Team"
                    description={
                        <>
                            Are you sure you want to delete <strong>{team.name}</strong>? This action cannot be undone.
                        </>
                    }
                    confirmText="Delete Team"
                    variant="destructive"
                    onConfirm={handleDelete}
                />
            )}
        </AdminLayout>
    );
}
