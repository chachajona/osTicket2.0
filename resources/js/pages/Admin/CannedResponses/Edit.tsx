import { useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { appShellLayout } from '@/layouts/AppShell';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowLeft01Icon, Delete01Icon, FloppyDiskIcon } from '@hugeicons/core-free-icons';
import type { ReactElement } from 'react';

declare global {
    function route(name: string, params?: any): string;
}

interface DepartmentOption {
    id: number;
    name: string;
}

interface CannedResponse {
    id: number;
    title: string;
    response: string;
    notes: string | null;
    dept_id: number | null;
    isactive: boolean;
}

interface Props {
    cannedResponse?: CannedResponse | null;
    departments: DepartmentOption[];
}

export default function CannedResponsesEdit({ cannedResponse, departments }: Props) {
    const isEdit = !!cannedResponse;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const { data, setData, post, put, processing, errors, transform } = useForm({
        title: cannedResponse?.title ?? '',
        dept_id: cannedResponse?.dept_id !== null && cannedResponse?.dept_id !== undefined ? String(cannedResponse.dept_id) : 'none',
        response: cannedResponse?.response ?? '',
        notes: cannedResponse?.notes ?? '',
        isactive: cannedResponse?.isactive ?? true,
    });

    const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        transform((data) => ({
            ...data,
            dept_id: data.dept_id === 'none' ? null : Number(data.dept_id),
        }));

        if (isEdit && cannedResponse) {
            put(route('admin.canned-responses.update', cannedResponse.id));

            return;
        }

        post(route('admin.canned-responses.store'));
    };

    const handleDelete = () => {
        if (!cannedResponse) return;

        router.delete(route('admin.canned-responses.destroy', cannedResponse.id));
    };

    return (
        <>
            <Head title={isEdit && cannedResponse ? `Edit Canned Response: ${cannedResponse.title}` : 'Create Canned Response'} />

            <div className="mb-6">
                <Link
                    href={route('admin.canned-responses.index')}
                    className="mb-4 inline-flex items-center text-sm font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <HugeiconsIcon icon={ArrowLeft01Icon} size={16} className="mr-1" />
                    Back to Canned Responses
                </Link>
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight text-slate-900">
                            {isEdit ? 'Edit Canned Response' : 'Create Canned Response'}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {isEdit
                                ? 'Update the canned response content and targeting.'
                                : 'Create a reusable canned response for agents.'}
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
                            Delete Canned Response
                        </Button>
                    )}
                </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <FormSection
                    title="Canned Response Details"
                    description="Basic information and response content."
                    collapsible={false}
                >
                    <FormGrid columns={1} className="max-w-3xl">
                        <div className="space-y-2">
                            <Label htmlFor="title">Title</Label>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(event) => setData('title', event.target.value)}
                                placeholder="e.g. Password reset instructions"
                                className={errors.title ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.title && <p className="text-sm text-red-500">{errors.title}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="dept_id">Department</Label>
                            <Select value={data.dept_id} onValueChange={(value) => value && setData('dept_id', value)}>
                                <SelectTrigger id="dept_id" className="w-full">
                                    <SelectValue placeholder="All Departments" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">All Departments</SelectItem>
                                    {departments.map((department) => (
                                        <SelectItem key={department.id} value={String(department.id)}>
                                            {department.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.dept_id && <p className="text-sm text-red-500">{errors.dept_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="response">Response</Label>
                            <Textarea
                                id="response"
                                value={data.response}
                                onChange={(event) => setData('response', event.target.value)}
                                rows={10}
                                placeholder="Write the canned response content..."
                                className={errors.response ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.response && <p className="text-sm text-red-500">{errors.response}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="notes">Internal Notes</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(event) => setData('notes', event.target.value)}
                                rows={3}
                                placeholder="Optional notes for admins..."
                                className={errors.notes ? 'border-red-500 focus-visible:ring-red-500' : ''}
                            />
                            {errors.notes && <p className="text-sm text-red-500">{errors.notes}</p>}
                        </div>

                        <div className="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <Checkbox
                                id="isactive"
                                checked={data.isactive}
                                onCheckedChange={(checked) => setData('isactive', checked === true)}
                                className="mt-0.5"
                            />
                            <div className="space-y-1">
                                <Label htmlFor="isactive" className="cursor-pointer text-sm font-medium text-slate-700">
                                    Active
                                </Label>
                                <p className="text-xs text-slate-500">
                                    Inactive canned responses remain in the system but are hidden from use.
                                </p>
                                {errors.isactive && <p className="text-sm text-red-500">{errors.isactive}</p>}
                            </div>
                        </div>
                    </FormGrid>
                </FormSection>

                <div className="flex items-center justify-end gap-4 pt-4">
                    <Link
                        href={route('admin.canned-responses.index')}
                        className={buttonVariants({ variant: 'outline' })}
                    >
                        Cancel
                    </Link>
                    <Button type="submit" disabled={processing}>
                        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
                        {isEdit ? 'Save Changes' : 'Create Canned Response'}
                    </Button>
                </div>
            </form>

            {isEdit && cannedResponse && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={setShowDeleteConfirm}
                    title="Delete Canned Response"
                    description={
                        <>
                            Are you sure you want to delete <strong>{cannedResponse.title}</strong>?
                            This action cannot be undone.
                        </>
                    }
                    confirmText="Delete Canned Response"
                    variant="destructive"
                    onConfirm={handleDelete}
                />
            )}
        </>
    );
}

type CannedResponsesEditComponent = typeof CannedResponsesEdit & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(CannedResponsesEdit as CannedResponsesEditComponent).layout = appShellLayout;
