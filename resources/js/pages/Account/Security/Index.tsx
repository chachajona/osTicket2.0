import { Link, router, useForm, usePage } from '@inertiajs/react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import Sessions from '@/pages/Account/Security/Sessions';

interface TwoFactorState {
    enabled: boolean;
    pending: boolean;
    confirmedAt: string | null;
    recoveryCodesCount: number;
    qrCodeSvg: string | null;
    qrCodeUrl: string | null;
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

    return (
        <div className="min-h-screen bg-gray-50 px-4 py-10">
            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-gray-900">Account security</h1>
                        <p className="mt-2 text-sm text-gray-500">
                            Enroll app-based two-factor authentication without changing the legacy SCP login flow.
                        </p>
                    </div>
                    <Link href="/scp" className="text-sm font-medium text-blue-600 hover:underline">
                        Back to dashboard
                    </Link>
                </div>

                {props.status && (
                    <Alert className="rounded-xl border-green-200 bg-green-50 text-green-700">
                        <AlertDescription className="text-green-700">
                            {props.status}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <section className="rounded-2xl bg-white p-6 shadow-sm">
                        <Tabs defaultValue="two-factor" className="gap-6">
                            <TabsList variant="line" className="w-full justify-start gap-6 border-b border-border p-0">
                                <TabsTrigger value="two-factor" className="px-0 pb-3">
                                    Two-factor
                                </TabsTrigger>
                                <TabsTrigger value="sessions" className="px-0 pb-3">
                                    Sessions
                                </TabsTrigger>
                            </TabsList>

                            <TabsContent value="two-factor" className="mt-0">
                                <div className="space-y-4">
                                    <div className="rounded-xl border border-gray-200 p-4">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <h2 className="text-lg font-semibold text-gray-900">Authenticator app</h2>
                                                <p className="mt-1 text-sm text-gray-500">
                                                    {twoFactor.enabled
                                                        ? 'App-based two-factor authentication is enabled for your account.'
                                                        : twoFactor.pending
                                                          ? 'Setup started. Return to the wizard to finish verifying your app.'
                                                          : 'Enable TOTP to finish your Laravel-native authentication upgrade.'}
                                                </p>
                                            </div>
                                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${twoFactor.enabled ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                                                {twoFactor.enabled ? 'Enabled' : twoFactor.pending ? 'Pending confirmation' : 'Not enabled'}
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
                                        <Alert variant="warning" className="rounded-xl border-amber-200 bg-amber-50">
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
                                </div>
                            </TabsContent>

                            <TabsContent value="sessions" className="mt-0">
                                <Sessions />
                            </TabsContent>
                        </Tabs>
                    </section>

                    <aside className="space-y-6">
                        <section className="rounded-2xl bg-white p-6 shadow-sm">
                            <h2 className="text-lg font-semibold text-gray-900">Migration status</h2>
                            <p className="mt-2 text-sm text-gray-500">
                                {migration.isMigrated
                                    ? `Marked migrated via ${migration.upgradeMethod ?? 'totp'}.`
                                    : 'Your account is still using the legacy email OTP path.'}
                            </p>
                            <dl className="mt-4 space-y-3 text-sm">
                                <div className="flex items-center justify-between">
                                    <dt className="text-gray-500">Status</dt>
                                    <dd className="font-medium text-gray-900">{migration.isMigrated ? 'Migrated' : 'Legacy flow'}</dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt className="text-gray-500">Recovery codes</dt>
                                    <dd className="font-medium text-gray-900">{twoFactor.recoveryCodesCount}</dd>
                                </div>
                                <div className="flex items-center justify-between">
                                    <dt className="text-gray-500">Confirmed at</dt>
                                    <dd className="font-medium text-gray-900">{twoFactor.confirmedAt ?? 'Not confirmed'}</dd>
                                </div>
                            </dl>
                        </section>

                        {revealedRecoveryCodes.length > 0 && (
                            <section className="rounded-2xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                                <h2 className="text-lg font-semibold text-amber-900">Recovery codes</h2>
                                <p className="mt-2 text-sm text-amber-800">
                                    Store these codes securely. Each code can be used once.
                                </p>
                                <div className="mt-4 grid gap-2 sm:grid-cols-2">
                                    {revealedRecoveryCodes.map((code) => (
                                        <div key={code} className="rounded-lg bg-white px-3 py-2 font-mono text-sm text-gray-900">
                                            {code}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </aside>
                </div>
            </div>
        </div>
    );
}
