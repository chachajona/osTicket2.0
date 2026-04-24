import { router, useForm, usePage } from '@inertiajs/react';
import { type ReactElement, type ReactNode } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import DashboardLayout from '@/layouts/DashboardLayout';
import Sessions from '@/pages/Account/Security/Sessions';

interface TwoFactorState {
    enabled: boolean;
    pending: boolean;
    confirmedAt: string | null;
    recoveryCodesCount: number;
    qrCodeSvg: string | null;
    setupKey: string | null;
}

interface MigrationState {
    isMigrated: boolean;
    migratedAt: string | null;
    upgradeMethod: string | null;
}

interface PageProps extends Record<string, unknown> {
    status?: string;
    twoFactor: TwoFactorState;
    migration: MigrationState;
    revealedRecoveryCodes: string[];
}

type FormSubmitHandler = NonNullable<React.ComponentProps<"form">["onSubmit"]>;

function twoFactorBadgeClass(twoFactor: TwoFactorState): string {
    if (twoFactor.enabled) return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    if (twoFactor.pending) return 'bg-amber-50 text-amber-700 border border-amber-200';
    return 'bg-[#F1F5F9] text-[#94A3B8] border border-[#E2E8F0]';
}

function twoFactorBadgeLabel(twoFactor: TwoFactorState): string {
    if (twoFactor.enabled) return 'Enabled';
    if (twoFactor.pending) return 'Pending';
    return 'Not enabled';
}

function twoFactorDescription(twoFactor: TwoFactorState): string {
    if (twoFactor.enabled) return 'App-based two-factor authentication is enabled for your account.';
    if (twoFactor.pending) return 'Setup started. Return to the wizard to finish verifying your app.';
    return 'Enable TOTP to finish your Laravel-native authentication upgrade.';
}

export default function SecurityIndex({ twoFactor, migration, revealedRecoveryCodes }: PageProps) {
    const { props } = usePage<PageProps>();
    const regenerateForm = useForm({});
    const disableForm = useForm({});

    const regenerateCodes: FormSubmitHandler = (event) => {
        event.preventDefault();
        regenerateForm.post('/scp/account/security/two-factor/regenerate-codes');
    };

    const disableTwoFactor: FormSubmitHandler = (event) => {
        event.preventDefault();
        disableForm.delete('/scp/account/security/two-factor');
    };

    const migrationBadgeClass = migration.isMigrated
        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
        : 'bg-amber-50 text-amber-700 border border-amber-200';

    const migrationStatusRows = [
        { label: 'Status', value: migration.isMigrated ? 'Migrated' : 'Legacy flow' },
        { label: 'Recovery codes', value: String(twoFactor.recoveryCodesCount) },
        { label: 'Confirmed at', value: twoFactor.confirmedAt ?? 'Not confirmed' },
    ];

    return (
        <>
            {props.status && (
                <div className="rounded-md border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#0F172A] mb-6">
                    {props.status}
                </div>
            )}

            <div className="grid gap-6 lg:grid-cols-[1.4fr_0.6fr] pb-12">
                <section className="auth-shell">
                    <div className="auth-shell-inner p-6">
                        <Tabs defaultValue="two-factor" className="gap-6">
                            <TabsList variant="line" className="w-full justify-start gap-6 border-b border-border p-0">
                                <TabsTrigger value="two-factor" className="px-0 pb-3">
                                    Two-factor
                                </TabsTrigger>
                                <TabsTrigger value="sessions" className="px-0 pb-3">
                                    Sessions
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="two-factor" className="mt-6 space-y-4">
                                <div className="rounded-md border border-[#E2E8F0] bg-white p-5">
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h2 className="text-lg font-semibold text-gray-900">Authenticator app</h2>
                                            <p className="mt-1 text-sm text-gray-500">
                                                {twoFactorDescription(twoFactor)}
                                            </p>
                                        </div>
                                        <span className={`inline-flex items-center gap-1.5 rounded-sm px-2 py-0.5 text-[10px] font-medium uppercase tracking-wider ${twoFactorBadgeClass(twoFactor)}`}>
                                            {twoFactorBadgeLabel(twoFactor)}
                                        </span>
                                    </div>

                                    {twoFactor.confirmedAt && (
                                        <p className="mt-3 text-xs text-gray-500">
                                            Confirmed {new Date(twoFactor.confirmedAt).toLocaleString()}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-gray-500">
                                        {twoFactor.recoveryCodesCount} recovery codes remaining
                                    </p>
                                </div>

                                {revealedRecoveryCodes.length > 0 && (
                                    <Alert variant="warning" className="rounded-md border-amber-200 bg-amber-50">
                                        <AlertDescription className="text-amber-900">
                                            New recovery codes: <span className="font-mono">{revealedRecoveryCodes.join(' · ')}</span>. Save them somewhere safe.
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="flex flex-wrap gap-3">
                                    <Button
                                        type="button"
                                        onClick={() => router.get('/scp/account/security/two-factor')}
                                    >
                                        {twoFactor.enabled || twoFactor.pending ? 'Manage 2FA' : 'Enable 2FA'}
                                    </Button>

                                    {twoFactor.enabled && (
                                        <>
                                            <form onSubmit={regenerateCodes}>
                                                <Button
                                                    variant="outline"
                                                    type="submit"
                                                    disabled={regenerateForm.processing}
                                                >
                                                    {regenerateForm.processing ? 'Regenerating…' : 'Regenerate recovery codes'}
                                                </Button>
                                            </form>

                                            <form onSubmit={disableTwoFactor}>
                                                <Button
                                                    variant="destructive"
                                                    type="submit"
                                                    disabled={disableForm.processing}
                                                >
                                                    {disableForm.processing ? 'Disabling…' : 'Disable 2FA'}
                                                </Button>
                                            </form>
                                        </>
                                    )}
                                </div>
                            </TabsContent>

                            <TabsContent value="sessions" className="mt-6">
                                <Sessions />
                            </TabsContent>
                        </Tabs>
                    </div>
                </section>

                <aside className="space-y-6">
                    <section className="rounded-md border border-[#E2E8F0] bg-[#F8FAFC] p-5">
                        <div className="auth-eyebrow mb-3">MIGRATION STATUS</div>
                        <div className="mb-4">
                            <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-sm text-[10px] font-medium uppercase tracking-wider ${migrationBadgeClass}`}>
                                {migration.isMigrated ? 'Migrated' : 'Legacy flow'}
                            </span>
                        </div>
                        <dl>
                            {migrationStatusRows.map(({ label, value }) => (
                                <div key={label} className="flex items-center justify-between py-2 border-b border-[#E2E8F0] last:border-b-0">
                                    <dt className="font-body text-xs text-[#94A3B8]">{label}</dt>
                                    <dd className="font-body text-xs font-medium text-[#0F172A]">{value}</dd>
                                </div>
                            ))}
                        </dl>
                    </section>

                    {revealedRecoveryCodes.length === 0 && (
                        <section className="rounded-md border border-amber-200 bg-amber-50 p-5">
                            <div className="auth-eyebrow text-amber-900! mb-2">RECOVERY CODES</div>
                            <p className="text-xs text-amber-800 mb-3">
                                Store these codes securely. Each code can be used once.
                            </p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {revealedRecoveryCodes.map((code) => (
                                    <div key={code} className="rounded-md bg-white px-2 py-1.5 font-mono text-xs text-gray-900 border border-amber-100">
                                        {code}
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}
                </aside>
            </div>
        </>
    );
}

type SecurityPageComponent = typeof SecurityIndex & {
    layout?: (page: ReactElement) => ReactNode;
};

(SecurityIndex as SecurityPageComponent).layout = (page: ReactElement) => (
    <DashboardLayout
        title="Account Security"
        subtitle="Manage two-factor authentication and active sessions."
        eyebrow="Security Settings"
        activeNav="security"
        headerActions={null}
    >
        {page}
    </DashboardLayout>
);
