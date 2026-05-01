import { useState } from 'react';
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

interface Option {
    id: number;
    name: string;
}

interface HelpTopic {
    id: number;
    topic: string;
    topic_pid: number | null;
    parent_topic: string | null;
    dept_id: number | null;
    department_name: string | null;
    sla_id: number | null;
    sla_name: string | null;
    staff_id: number | null;
    staff_name: string | null;
    team_id: number | null;
    team_name: string | null;
    priority_id: number | null;
    ispublic: boolean;
    isactive: boolean;
    noautoresp: boolean;
    notes: string | null;
}

interface FormField {
    id: number;
    label: string;
    name: string;
    type: string;
}

interface FormMapping {
    id: number;
    title: string;
    name: string | null;
    source: string;
    field_count: number;
    fields: FormField[];
}

interface Props {
    helpTopic?: HelpTopic | null;
    parentTopicOptions: Option[];
    departmentOptions: Option[];
    slaOptions: Option[];
    staffOptions: Option[];
    teamOptions: Option[];
    priorityOptions: Option[];
    formMappings: FormMapping[];
}

const selectClassName =
    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2';

export default function HelpTopicsEdit({
    helpTopic,
    parentTopicOptions,
    departmentOptions,
    slaOptions,
    staffOptions,
    teamOptions,
    priorityOptions,
    formMappings,
}: Props) {
    const isEdit = !!helpTopic;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const { data, setData, post, patch, processing, errors } = useForm({
        topic: helpTopic?.topic ?? '',
        topic_pid: helpTopic?.topic_pid ? String(helpTopic.topic_pid) : '',
        dept_id: helpTopic?.dept_id ? String(helpTopic.dept_id) : '',
        sla_id: helpTopic?.sla_id ? String(helpTopic.sla_id) : '',
        staff_id: helpTopic?.staff_id ? String(helpTopic.staff_id) : '',
        team_id: helpTopic?.team_id ? String(helpTopic.team_id) : '',
        priority_id: helpTopic?.priority_id ? String(helpTopic.priority_id) : '',
        notes: helpTopic?.notes ?? '',
        ispublic: helpTopic?.ispublic ?? true,
        isactive: helpTopic?.isactive ?? true,
        noautoresp: helpTopic?.noautoresp ?? false,
    });

    const handleDelete = () => {
        if (!helpTopic) return;

        router.delete(route('admin.help-topics.destroy', helpTopic.id));
    };

    return (
        <AdminLayout activeAdminNav="help-topics">
            <Head title={isEdit && helpTopic ? `Edit Help Topic: ${helpTopic.topic}` : 'Create Help Topic'} />

            <div className="mb-6">
                <Link
                    href={route('admin.help-topics.index')}
                    className="mb-4 inline-flex items-center text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} size={16} className="mr-1" />
                    Back to Help Topics
                </Link>

                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {isEdit ? 'Edit Help Topic' : 'Create Help Topic'}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {isEdit
                                ? 'Update ticket routing defaults, visibility, and ownership settings.'
                                : 'Create a help topic with routing defaults for incoming tickets.'}
                        </p>
                    </div>

                    {isEdit && helpTopic && (
                        <Button
                            type="button"
                            variant="outline"
                            className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                            onClick={() => setShowDeleteConfirm(true)}
                        >
                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                            Delete Help Topic
                        </Button>
                    )}
                </div>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();

                    if (isEdit && helpTopic) {
                        patch(route('admin.help-topics.update', helpTopic.id));

                        return;
                    }

                    post(route('admin.help-topics.store'));
                }}
                className="space-y-6"
            >
                <FormSection
                    title="Topic Details"
                    description="Configure the label, parent relationship, and routing defaults."
                    collapsible={false}
                >
                    <FormGrid columns={2} className="max-w-5xl">
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="topic">Topic</Label>
                            <Input
                                id="topic"
                                value={data.topic}
                                onChange={(event) => setData('topic', event.target.value)}
                                placeholder="e.g. Billing Question"
                                className={errors.topic ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.topic && <p className="text-sm text-red-500">{errors.topic}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="topic_pid">Parent Topic</Label>
                            <select
                                id="topic_pid"
                                value={data.topic_pid}
                                onChange={(event) => setData('topic_pid', event.target.value)}
                                className={[selectClassName, errors.topic_pid ? 'border-red-500 focus-visible:ring-red-500' : ''].join(' ')}
                            >
                                <option value="">No parent topic</option>
                                {parentTopicOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            {errors.topic_pid && <p className="text-sm text-red-500">{errors.topic_pid}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="priority_id">Priority</Label>
                            <select
                                id="priority_id"
                                value={data.priority_id}
                                onChange={(event) => setData('priority_id', event.target.value)}
                                className={[selectClassName, errors.priority_id ? 'border-red-500 focus-visible:ring-red-500' : ''].join(' ')}
                            >
                                <option value="">No default priority</option>
                                {priorityOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            {errors.priority_id && <p className="text-sm text-red-500">{errors.priority_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="dept_id">Department</Label>
                            <select
                                id="dept_id"
                                value={data.dept_id}
                                onChange={(event) => setData('dept_id', event.target.value)}
                                className={[selectClassName, errors.dept_id ? 'border-red-500 focus-visible:ring-red-500' : ''].join(' ')}
                            >
                                <option value="">No department</option>
                                {departmentOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            {errors.dept_id && <p className="text-sm text-red-500">{errors.dept_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="sla_id">SLA</Label>
                            <select
                                id="sla_id"
                                value={data.sla_id}
                                onChange={(event) => setData('sla_id', event.target.value)}
                                className={[selectClassName, errors.sla_id ? 'border-red-500 focus-visible:ring-red-500' : ''].join(' ')}
                            >
                                <option value="">No SLA</option>
                                {slaOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            {errors.sla_id && <p className="text-sm text-red-500">{errors.sla_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="staff_id">Assigned Staff</Label>
                            <select
                                id="staff_id"
                                value={data.staff_id}
                                onChange={(event) => setData('staff_id', event.target.value)}
                                className={[selectClassName, errors.staff_id ? 'border-red-500 focus-visible:ring-red-500' : ''].join(' ')}
                            >
                                <option value="">No staff default</option>
                                {staffOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            {errors.staff_id && <p className="text-sm text-red-500">{errors.staff_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="team_id">Assigned Team</Label>
                            <select
                                id="team_id"
                                value={data.team_id}
                                onChange={(event) => setData('team_id', event.target.value)}
                                className={[selectClassName, errors.team_id ? 'border-red-500 focus-visible:ring-red-500' : ''].join(' ')}
                            >
                                <option value="">No team default</option>
                                {teamOptions.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name}
                                    </option>
                                ))}
                            </select>
                            {errors.team_id && <p className="text-sm text-red-500">{errors.team_id}</p>}
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="notes">Internal Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                rows={4}
                                placeholder="Optional notes for agents and administrators..."
                                className={errors.notes ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.notes && <p className="text-sm text-red-500">{errors.notes}</p>}
                        </div>
                    </FormGrid>
                </FormSection>

                <FormSection
                    title="Visibility & Automation"
                    description="Choose whether this topic is public, active, and exempt from auto responses."
                    collapsible={false}
                >
                    <FormGrid columns={3} className="max-w-5xl">
                        {[
                            {
                                key: 'ispublic' as const,
                                label: 'Public topic',
                                description: 'Available to end users when creating tickets.',
                            },
                            {
                                key: 'isactive' as const,
                                label: 'Active topic',
                                description: 'Disabled topics stay hidden from new ticket selection.',
                            },
                            {
                                key: 'noautoresp' as const,
                                label: 'Disable auto response',
                                description: 'Suppress automatic acknowledgements for this topic.',
                            },
                        ].map((item) => (
                            <div key={item.key} className="space-y-2">
                                <Label htmlFor={item.key}>{item.label}</Label>
                                <div className="flex min-h-10 items-center gap-3 rounded-md border border-slate-200 px-3">
                                    <Checkbox
                                        id={item.key}
                                        checked={data[item.key]}
                                        onCheckedChange={(checked) => setData(item.key, checked === true)}
                                    />
                                    <div>
                                        <Label htmlFor={item.key} className="cursor-pointer text-sm font-medium text-slate-700">
                                            {item.label}
                                        </Label>
                                        <p className="text-xs text-slate-500">{item.description}</p>
                                    </div>
                                </div>
                                {errors[item.key] && <p className="text-sm text-red-500">{errors[item.key]}</p>}
                            </div>
                        ))}
                    </FormGrid>
                </FormSection>

                <FormSection
                    title="Form Mapping"
                    description="Associated forms are displayed for reference only. Editing form definitions is not available here."
                    collapsible={false}
                >
                    <div className="space-y-4 max-w-5xl">
                        {formMappings.length === 0 ? (
                            <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                                No forms are mapped to this help topic.
                            </div>
                        ) : (
                            formMappings.map((mapping) => (
                                <div key={mapping.id} className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <h3 className="text-sm font-semibold text-slate-900">{mapping.title}</h3>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {mapping.source}
                                                {mapping.name ? ` · ${mapping.name}` : ''}
                                                {` · ${mapping.field_count} field${mapping.field_count === 1 ? '' : 's'}`}
                                            </p>
                                        </div>
                                        <span className="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-slate-600">
                                            Read only
                                        </span>
                                    </div>

                                    <div className="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white">
                                        <table className="w-full text-left text-sm">
                                            <thead className="bg-slate-50 text-slate-500">
                                                <tr>
                                                    <th className="px-4 py-2 font-medium">Label</th>
                                                    <th className="px-4 py-2 font-medium">Name</th>
                                                    <th className="px-4 py-2 font-medium">Type</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {mapping.fields.map((field) => (
                                                    <tr key={field.id} className="border-t border-slate-100">
                                                        <td className="px-4 py-2 text-slate-900">{field.label}</td>
                                                        <td className="px-4 py-2 text-slate-500">{field.name}</td>
                                                        <td className="px-4 py-2 text-slate-500">{field.type}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </FormSection>

                <div className="flex items-center justify-end gap-4 pt-4">
                    <Link href={route('admin.help-topics.index')} className={buttonVariants({ variant: 'outline' })}>
                        Cancel
                    </Link>
                    <Button type="submit" disabled={processing}>
                        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
                        {isEdit ? 'Save Changes' : 'Create Help Topic'}
                    </Button>
                </div>
            </form>

            {isEdit && helpTopic && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={setShowDeleteConfirm}
                    title="Delete Help Topic"
                    description={
                        <>
                            Are you sure you want to delete <strong>{helpTopic.topic}</strong>? This action cannot be undone.
                        </>
                    }
                    confirmText="Delete Help Topic"
                    variant="destructive"
                    onConfirm={handleDelete}
                />
            )}
        </AdminLayout>
    );
}
