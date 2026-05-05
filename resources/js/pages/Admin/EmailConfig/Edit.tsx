import { type ComponentProps, useMemo, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { appShellLayout } from '@/layouts/AppShell';
import { PageHeader } from '@/components/layout/PageHeader';
import { ConfirmDialog } from '@/components/admin/ConfirmDialog';
import { FormGrid } from '@/components/admin/FormGrid';
import { FormSection } from '@/components/admin/FormSection';
import { Button, buttonVariants } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { HugeiconsIcon } from '@hugeicons/react';
import { Delete01Icon, FloppyDiskIcon } from '@hugeicons/core-free-icons';
import type { ReactElement } from 'react';

declare global {
    function route(name: string, params?: any): string;
}

type EmailConfigType = 'account' | 'template' | 'group';

interface TemplateGroupOption {
    id: number;
    name: string;
}

interface EmailConfigRecord {
    id: number;
    key: string;
    type: EmailConfigType;
    name: string;
    email?: string | null;
    host?: string | null;
    port?: number | null;
    protocol?: string | null;
    encryption?: string | null;
    username?: string | null;
    active?: boolean;
    code?: string | null;
    subject?: string | null;
    body?: string | null;
    group_id?: number | null;
    lang?: string | null;
}

interface Props {
    mode: 'create' | 'edit';
    type: EmailConfigType;
    config?: EmailConfigRecord | null;
    templateGroups: TemplateGroupOption[];
}

function FieldError({ error }: { error?: string }) {
    return error ? <p className="text-sm text-red-500">{error}</p> : null;
}

export default function EmailConfigEdit({ mode, type, config, templateGroups }: Props) {
    const isEdit = mode === 'edit' && !!config;
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

    const form = useForm({
        type,
        name: config?.name ?? '',
        email: config?.email ?? '',
        host: config?.host ?? '',
        port: config?.port ? String(config.port) : '',
        protocol: config?.protocol ?? 'imap',
        encryption: config?.encryption ?? 'ssl',
        username: config?.username ?? '',
        password: '',
        active: config?.active ?? true,
        code: config?.code ?? '',
        subject: config?.subject ?? '',
        body: config?.body ?? '',
        group_id: config?.group_id ? String(config.group_id) : '',
        lang: config?.lang ?? 'en_US',
    });

    const title = useMemo(() => {
        if (type === 'account') return isEdit ? 'Edit Mail Account' : 'Create Mail Account';
        if (type === 'template') return isEdit ? 'Edit Email Template' : 'Create Email Template';
        return isEdit ? 'Edit Template Group' : 'Create Template Group';
    }, [isEdit, type]);

    const submit: NonNullable<ComponentProps<'form'>['onSubmit']> = (event) => {
        event.preventDefault();

        if (isEdit && config) {
            form.put(route('admin.email-config.update', config.key));
            return;
        }

        form.post(route('admin.email-config.store'));
    };

    const deleteConfig = () => {
        if (!config) return;

        router.delete(route('admin.email-config.destroy', config.key));
    };

    return (
        <>
            <Head title={title} />

            <PageHeader
                title={title}
                subtitle={
                    type === 'account'
                        ? 'Store mailbox connection settings with encrypted credentials at rest.'
                        : type === 'template'
                          ? 'Manage template content and assign it to a template group.'
                          : 'Organize related templates by code and locale.'
                }
                headerActions={
                    <>
                        <Link
                            href={route('admin.email-config.index')}
                            className="inline-flex h-7 items-center gap-1.5 rounded-[3px] border border-[#E2E0D8] bg-white px-3 text-[12px] font-medium uppercase leading-4 tracking-[1.2px] text-[#27272A] transition-colors hover:border-[#18181B] hover:bg-[#FAFAF8] hover:text-[#18181B]"
                        >
                            <span aria-hidden>&larr;</span>
                            Back to Email Config
                        </Link>
                        {isEdit && config && (
                            <Button
                                type="button"
                                variant="outline"
                                className="border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                onClick={() => setShowDeleteConfirm(true)}
                            >
                                <HugeiconsIcon icon={Delete01Icon} size={18} className="mr-2" />
                                Delete
                            </Button>
                        )}
                    </>
                }
            />

            <form onSubmit={submit} className="space-y-6">
                {type === 'account' && (
                    <FormSection title="Mail Account" description="Connection details and encrypted login credentials." collapsible={false}>
                        <FormGrid columns={2} className="max-w-5xl">
                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <FieldError error={form.errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="email">Email Address</Label>
                                <Input id="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                <FieldError error={form.errors.email} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="host">Host</Label>
                                <Input id="host" value={form.data.host} onChange={(e) => form.setData('host', e.target.value)} />
                                <FieldError error={form.errors.host} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="port">Port</Label>
                                <Input id="port" value={form.data.port} onChange={(e) => form.setData('port', e.target.value)} />
                                <FieldError error={form.errors.port} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="protocol">Protocol</Label>
                                <Input id="protocol" value={form.data.protocol} onChange={(e) => form.setData('protocol', e.target.value)} />
                                <FieldError error={form.errors.protocol} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="encryption">Encryption</Label>
                                <Input id="encryption" value={form.data.encryption} onChange={(e) => form.setData('encryption', e.target.value)} />
                                <FieldError error={form.errors.encryption} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="username">Username</Label>
                                <Input id="username" value={form.data.username} onChange={(e) => form.setData('username', e.target.value)} />
                                <FieldError error={form.errors.username} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="password">Password</Label>
                                <Input id="password" type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                                <FieldError error={form.errors.password} />
                            </div>
                            <div className="md:col-span-2 flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                                <Checkbox id="active" checked={form.data.active} onCheckedChange={(checked) => form.setData('active', checked === true)} />
                                <div>
                                    <Label htmlFor="active" className="cursor-pointer text-sm font-medium text-slate-700">Active</Label>
                                    <p className="text-xs text-slate-500">Inactive accounts stay configured but are not used.</p>
                                    <FieldError error={form.errors.active} />
                                </div>
                            </div>
                        </FormGrid>
                    </FormSection>
                )}

                {type === 'template' && (
                    <FormSection title="Email Template" description="Edit the subject/body and assign the template group." collapsible={false}>
                        <FormGrid columns={2} className="max-w-5xl">
                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <FieldError error={form.errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="code">Code</Label>
                                <Input id="code" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                                <FieldError error={form.errors.code} />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="subject">Subject</Label>
                                <Input id="subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                                <FieldError error={form.errors.subject} />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="group_id">Template Group</Label>
                                <select
                                    id="group_id"
                                    value={form.data.group_id}
                                    onChange={(e) => form.setData('group_id', e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">Select a group</option>
                                    {templateGroups.map((group) => (
                                        <option key={group.id} value={String(group.id)}>
                                            {group.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError error={form.errors.group_id} />
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="body">Body</Label>
                                <Textarea id="body" rows={12} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} />
                                <FieldError error={form.errors.body} />
                            </div>
                        </FormGrid>
                    </FormSection>
                )}

                {type === 'group' && (
                    <FormSection title="Template Group" description="Locale-aware grouping for related templates." collapsible={false}>
                        <FormGrid columns={2} className="max-w-4xl">
                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                                <FieldError error={form.errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="code">Code</Label>
                                <Input id="code" value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                                <FieldError error={form.errors.code} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="lang">Language</Label>
                                <Input id="lang" value={form.data.lang} onChange={(e) => form.setData('lang', e.target.value)} />
                                <FieldError error={form.errors.lang} />
                            </div>
                        </FormGrid>
                    </FormSection>
                )}

                <div className="flex items-center justify-end gap-4 pt-4">
                    <Link href={route('admin.email-config.index')} className={buttonVariants({ variant: 'outline' })}>
                        Cancel
                    </Link>
                    <Button type="submit" disabled={form.processing}>
                        <HugeiconsIcon icon={FloppyDiskIcon} size={18} className="mr-2" />
                        {isEdit ? 'Save Changes' : 'Create'}
                    </Button>
                </div>
            </form>

            {isEdit && config && (
                <ConfirmDialog
                    open={showDeleteConfirm}
                    onOpenChange={setShowDeleteConfirm}
                    title="Delete Email Config"
                    description={
                        <>
                            Are you sure you want to delete <strong>{config.name}</strong>?
                        </>
                    }
                    confirmText="Delete"
                    variant="destructive"
                    onConfirm={deleteConfig}
                />
            )}
        </>
    );
}

type EmailConfigEditComponent = typeof EmailConfigEdit & {
    layout?: (page: ReactElement) => React.ReactNode;
};

(EmailConfigEdit as EmailConfigEditComponent).layout = appShellLayout;
