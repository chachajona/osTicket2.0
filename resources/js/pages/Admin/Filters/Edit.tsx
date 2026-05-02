import { useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { appShellLayout } from '@/layouts/AppShell';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon, Delete01Icon, FloppyDiskIcon, PlusSignIcon } from '@hugeicons/core-free-icons';
import type { ReactElement } from 'react';

declare global {
    function route(name: string, params?: any): string;
}

interface FilterRule {
    what: string;
    how: string;
    val: string;
    isactive: boolean;
}

interface FilterAction {
    type: string;
    sort: number;
    target: string;
}

interface Filter {
    id: number;
    name: string;
    exec_order: number;
    notes: string | null;
    isactive: boolean;
    rules: FilterRule[];
    actions: FilterAction[];
}

interface Props {
    filter?: Filter | null;
}

const emptyRule = (): FilterRule => ({
    what: '',
    how: '',
    val: '',
    isactive: true,
});

const emptyAction = (): FilterAction => ({
    type: '',
    sort: 0,
    target: '',
});

export default function FiltersEdit({ filter }: Props) {
    const isEdit = !!filter;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const { data, setData, post, put, processing, errors } = useForm({
        name: filter?.name ?? '',
        exec_order: filter?.exec_order ?? 0,
        notes: filter?.notes ?? '',
        isactive: filter?.isactive ?? true,
        rules: filter?.rules ?? [],
        actions: filter?.actions ?? [],
    });

    const ruleCount = useMemo(() => data.rules.length, [data.rules.length]);
    const actionCount = useMemo(() => data.actions.length, [data.actions.length]);

    const updateRule = (index: number, field: keyof FilterRule, value: string | boolean) => {
        setData(
            'rules',
            data.rules.map((rule, ruleIndex) => (ruleIndex === index ? { ...rule, [field]: value } : rule)),
        );
    };

    const updateAction = (index: number, field: keyof FilterAction, value: string | number) => {
        setData(
            'actions',
            data.actions.map((action, actionIndex) =>
                actionIndex === index ? { ...action, [field]: value } : action,
            ),
        );
    };

    const handleDelete = () => {
        if (!filter) return;

        router.delete(route('admin.filters.destroy', filter.id));
    };

    return (
        <>
            <Head title={isEdit && filter ? `Edit Filter: ${filter.name}` : 'Create Filter'} />

            <div className="mb-6">
                <Link
                    href={route('admin.filters.index')}
                    className="mb-4 inline-flex items-center text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} size={16} className="mr-1" />
                    Back to Filters
                </Link>

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {isEdit ? 'Edit Filter' : 'Create Filter'}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {isEdit
                                ? 'Update matching rules, automated actions, and execution order.'
                                : 'Create a new filter with nested rules and actions.'}
                        </p>
                    </div>

                    {isEdit && filter && (
                        <Button
                            type="button"
                            variant="outline"
                            className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                            onClick={() => setShowDeleteConfirm(true)}
                        >
                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                            Delete Filter
                        </Button>
                    )}
                </div>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();

                    if (isEdit && filter) {
                        put(route('admin.filters.update', filter.id));

                        return;
                    }

                    post(route('admin.filters.store'));
                }}
                className="space-y-6"
            >
                <FormSection
                    title="Filter Details"
                    description="Define the filter identity, order, and status."
                    collapsible={false}
                >
                    <FormGrid columns={2} className="max-w-4xl">
                        <div className="space-y-2">
                            <Label htmlFor="name">Filter Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="e.g. VIP Routing"
                                className={errors.name ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="exec_order">Execution Order</Label>
                            <Input
                                id="exec_order"
                                type="number"
                                value={data.exec_order}
                                onChange={(event) => setData('exec_order', Number(event.target.value))}
                                className={errors.exec_order ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.exec_order && <p className="text-sm text-red-500">{errors.exec_order}</p>}
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="notes">Internal Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                rows={4}
                                placeholder="Optional notes about when this filter should be used..."
                                className={errors.notes ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.notes && <p className="text-sm text-red-500">{errors.notes}</p>}
                        </div>

                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="isactive" className="block">
                                Filter Status
                            </Label>
                            <div className="flex min-h-10 items-center gap-3 rounded-md border border-slate-200 px-3">
                                <Checkbox
                                    id="isactive"
                                    checked={data.isactive}
                                    onCheckedChange={(checked) => setData('isactive', checked === true)}
                                />
                                <Label htmlFor="isactive" className="cursor-pointer text-sm font-medium text-slate-700">
                                    Active filter
                                </Label>
                            </div>
                            {errors.isactive && <p className="text-sm text-red-500">{errors.isactive}</p>}
                        </div>
                    </FormGrid>
                </FormSection>

                <FormSection
                    title="Rules"
                    description="Add one or more conditions that determine when this filter matches."
                    collapsible={false}
                >
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-slate-700">{ruleCount} rule(s)</span>
                            <Button type="button" variant="outline" onClick={() => setData('rules', [...data.rules, emptyRule()])}>
                                <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                                Add Rule
                            </Button>
                        </div>

                        <div className="space-y-4">
                            {data.rules.map((rule, index) => (
                                <div key={`rule-${index}`} className="rounded-xl border border-slate-200 p-4">
                                    <div className="mb-4 flex items-center justify-between">
                                        <h3 className="text-sm font-semibold text-slate-900">Rule #{index + 1}</h3>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="text-red-600 hover:bg-red-50 hover:text-red-700"
                                            onClick={() =>
                                                setData(
                                                    'rules',
                                                    data.rules.length === 1
                                                        ? []
                                                        : data.rules.filter((_, ruleIndex) => ruleIndex !== index),
                                                )
                                            }
                                        >
                                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                                            Remove
                                        </Button>
                                    </div>

                                    <FormGrid columns={2}>
                                        <div className="space-y-2">
                                            <Label htmlFor={`rules.${index}.what`}>What</Label>
                                            <Input
                                                id={`rules.${index}.what`}
                                                value={rule.what}
                                                onChange={(event) => updateRule(index, 'what', event.target.value)}
                                                placeholder="e.g. email"
                                                className={errors[`rules.${index}.what`] ? 'border-red-500 focus-visible:ring-red-500' : ''}
                                            />
                                            {errors[`rules.${index}.what`] && (
                                                <p className="text-sm text-red-500">{errors[`rules.${index}.what`]}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor={`rules.${index}.how`}>How</Label>
                                            <Input
                                                id={`rules.${index}.how`}
                                                value={rule.how}
                                                onChange={(event) => updateRule(index, 'how', event.target.value)}
                                                placeholder="e.g. contains"
                                                className={errors[`rules.${index}.how`] ? 'border-red-500 focus-visible:ring-red-500' : ''}
                                            />
                                            {errors[`rules.${index}.how`] && (
                                                <p className="text-sm text-red-500">{errors[`rules.${index}.how`]}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor={`rules.${index}.val`}>Value</Label>
                                            <Input
                                                id={`rules.${index}.val`}
                                                value={rule.val}
                                                onChange={(event) => updateRule(index, 'val', event.target.value)}
                                                placeholder="e.g. @vip.example"
                                                className={errors[`rules.${index}.val`] ? 'border-red-500 focus-visible:ring-red-500' : ''}
                                            />
                                            {errors[`rules.${index}.val`] && (
                                                <p className="text-sm text-red-500">{errors[`rules.${index}.val`]}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2 md:col-span-2">
                                            <div className="flex min-h-10 items-center gap-3 rounded-md border border-slate-200 px-3">
                                                <Checkbox
                                                    id={`rules.${index}.isactive`}
                                                    checked={rule.isactive}
                                                    onCheckedChange={(checked) => updateRule(index, 'isactive', checked === true)}
                                                />
                                                <Label
                                                    htmlFor={`rules.${index}.isactive`}
                                                    className="cursor-pointer text-sm font-medium text-slate-700"
                                                >
                                                    Active rule
                                                </Label>
                                            </div>
                                            {errors[`rules.${index}.isactive`] && (
                                                <p className="text-sm text-red-500">{errors[`rules.${index}.isactive`]}</p>
                                            )}
                                        </div>
                                    </FormGrid>
                                </div>
                            ))}
                            {data.rules.length === 0 && (
                                <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                                    No rules added yet. Add at least one rule to define when this filter should match.
                                </div>
                            )}
                        </div>
                    </div>
                </FormSection>

                <FormSection
                    title="Actions"
                    description="Define which automated actions should run when the filter matches."
                    collapsible={false}
                >
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-slate-700">{actionCount} action(s)</span>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setData('actions', [...data.actions, emptyAction()])}
                            >
                                <HugeiconsIcon icon={PlusSignIcon} size={18} className="mr-2" />
                                Add Action
                            </Button>
                        </div>

                        <div className="space-y-4">
                            {data.actions.map((action, index) => (
                                <div key={`action-${index}`} className="rounded-xl border border-slate-200 p-4">
                                    <div className="mb-4 flex items-center justify-between">
                                        <h3 className="text-sm font-semibold text-slate-900">Action #{index + 1}</h3>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            className="text-red-600 hover:bg-red-50 hover:text-red-700"
                                            onClick={() =>
                                                setData(
                                                    'actions',
                                                    data.actions.length === 1
                                                        ? []
                                                        : data.actions.filter((_, actionIndex) => actionIndex !== index),
                                                )
                                            }
                                        >
                                            <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                                            Remove
                                        </Button>
                                    </div>

                                    <FormGrid columns={3}>
                                        <div className="space-y-2">
                                            <Label htmlFor={`actions.${index}.type`}>Type</Label>
                                            <Input
                                                id={`actions.${index}.type`}
                                                value={action.type}
                                                onChange={(event) => updateAction(index, 'type', event.target.value)}
                                                placeholder="e.g. reject"
                                                className={errors[`actions.${index}.type`] ? 'border-red-500 focus-visible:ring-red-500' : ''}
                                            />
                                            {errors[`actions.${index}.type`] && (
                                                <p className="text-sm text-red-500">{errors[`actions.${index}.type`]}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor={`actions.${index}.sort`}>Sort</Label>
                                            <Input
                                                id={`actions.${index}.sort`}
                                                type="number"
                                                value={action.sort}
                                                onChange={(event) => updateAction(index, 'sort', Number(event.target.value))}
                                                className={errors[`actions.${index}.sort`] ? 'border-red-500 focus-visible:ring-red-500' : ''}
                                            />
                                            {errors[`actions.${index}.sort`] && (
                                                <p className="text-sm text-red-500">{errors[`actions.${index}.sort`]}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor={`actions.${index}.target`}>Target</Label>
                                            <Input
                                                id={`actions.${index}.target`}
                                                value={action.target}
                                                onChange={(event) => updateAction(index, 'target', event.target.value)}
                                                placeholder="e.g. team:2"
                                                className={errors[`actions.${index}.target`] ? 'border-red-500 focus-visible:ring-red-500' : ''}
                                            />
                                            {errors[`actions.${index}.target`] && (
                                                <p className="text-sm text-red-500">{errors[`actions.${index}.target`]}</p>
                                            )}
                                        </div>
                                    </FormGrid>
                                </div>
                            ))}
                            {data.actions.length === 0 && (
                                <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-500">
                                    No actions added yet. Add one or more actions to run when the filter matches.
                                </div>
                            )}
                        </div>
                    </div>
                </FormSection>

                <div className="flex items-center justify-end gap-4 pt-4">
                    <Link href={route('admin.filters.index')} className={buttonVariants({ variant: 'outline' })}>
                        Cancel
                    </Link>
                    <Button type="submit" disabled={processing}>
                        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
                        {isEdit ? 'Save Changes' : 'Create Filter'}
                    </Button>
                </div>
            </form>

            {isEdit && filter && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={setShowDeleteConfirm}
                    title="Delete Filter"
                    description={
                        <>
                            Are you sure you want to delete <strong>{filter.name}</strong>? This action cannot be undone.
                        </>
                    }
                    confirmText="Delete Filter"
                    variant="destructive"
                    onConfirm={handleDelete}
                />
            )}
        </>
    );
}

type FiltersEditComponent = typeof FiltersEdit & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(FiltersEdit as FiltersEditComponent).layout = appShellLayout;
