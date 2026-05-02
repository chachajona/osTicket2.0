import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { appShellLayout } from '@/layouts/AppShell';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon, Delete01Icon, FloppyDiskIcon } from '@hugeicons/core-free-icons';
import type { ReactElement } from 'react';

declare global {
    function route(name: string, params?: any): string;
}

interface Sla {
    id: number;
    name: string;
    grace_period: number;
    schedule_id: number | null;
    schedule: string | null;
    notes: string | null;
    flags: number;
}

interface Props {
    sla?: Sla | null;
}

export default function SlasEdit({ sla }: Props) {
    const isEdit = !!sla;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const { data, setData, post, patch, processing, errors } = useForm({
        name: sla?.name ?? '',
        grace_period: sla?.grace_period?.toString() ?? '0',
        schedule_id: sla?.schedule_id?.toString() ?? '',
        notes: sla?.notes ?? '',
        flags: sla?.flags ?? 0,
    });

    const handleDelete = () => {
        if (!sla) return;

        router.delete(route('admin.slas.destroy', sla.id));
    };

    return (
        <>
            <Head title={isEdit && sla ? `Edit SLA: ${sla.name}` : 'Create SLA'} />

            <div className="mb-6">
                <Link
                    href={route('admin.slas.index')}
                    className="mb-4 inline-flex items-center text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} size={16} className="mr-1" />
                    Back to SLA Plans
                </Link>

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {isEdit ? 'Edit SLA' : 'Create SLA'}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {isEdit
                                ? 'Update SLA thresholds, schedule assignment, and notes.'
                                : 'Create a new SLA plan for ticket response timing.'}
                        </p>
                    </div>

                    {isEdit && (
                        <Button
                            type="button"
                            variant="outline"
                            className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                            onClick={() => setShowDeleteConfirm(true)}
                        >
                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                            Delete SLA
                        </Button>
                    )}
                </div>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();

                    if (isEdit && sla) {
                        patch(route('admin.slas.update', sla.id));

                        return;
                    }

                    post(route('admin.slas.store'));
                }}
                className="space-y-6"
            >
                <FormSection
                    title="SLA Details"
                    description="Configure the display name, grace period, and optional schedule link."
                    collapsible={false}
                >
                    <FormGrid columns={1} className="max-w-3xl">
                        <div className="space-y-2">
                            <Label htmlFor="name">Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="e.g. Business Hours Response"
                                className={errors.name ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="grace_period">Grace Period</Label>
                            <Input
                                id="grace_period"
                                type="number"
                                min="0"
                                value={data.grace_period}
                                onChange={(event) => setData('grace_period', event.target.value)}
                                className={errors.grace_period ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.grace_period && <p className="text-sm text-red-500">{errors.grace_period}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="schedule_id">Schedule ID</Label>
                            <Input
                                id="schedule_id"
                                type="number"
                                min="1"
                                value={data.schedule_id}
                                onChange={(event) => setData('schedule_id', event.target.value)}
                                placeholder="Optional schedule ID"
                                className={errors.schedule_id ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {sla?.schedule && <p className="text-sm text-slate-500">Current schedule: {sla.schedule}</p>}
                            {errors.schedule_id && <p className="text-sm text-red-500">{errors.schedule_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea
                                id="notes"
                                rows={4}
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                placeholder="Optional internal notes for this SLA plan..."
                                className={errors.notes ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.notes && <p className="text-sm text-red-500">{errors.notes}</p>}
                        </div>
                    </FormGrid>
                </FormSection>

                <div className="flex items-center justify-end gap-4 pt-4">
                    <Link href={route('admin.slas.index')} className={buttonVariants({ variant: 'outline' })}>
                        Cancel
                    </Link>
                    <Button type="submit" disabled={processing}>
                        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
                        {isEdit ? 'Save Changes' : 'Create SLA'}
                    </Button>
                </div>
            </form>

            {isEdit && sla && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={setShowDeleteConfirm}
                    title="Delete SLA"
                    description={
                        <>
                            Are you sure you want to delete <strong>{sla.name}</strong>? This action cannot be undone.
                        </>
                    }
                    confirmText="Delete SLA"
                    variant="destructive"
                    onConfirm={handleDelete}
                />
            )}
        </>
    );
}

type SlasEditComponent = typeof SlasEdit & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(SlasEdit as SlasEditComponent).layout = appShellLayout;
