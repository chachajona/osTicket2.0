import { Link, useForm, usePage } from '@inertiajs/react';

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
    const enableForm = useForm({ force: false });
    const confirmForm = useForm({ code: '' });
    const regenerateForm = useForm({});
    const disableForm = useForm({});

    const enableTwoFactor: FormSubmitHandler = (event) => {
        event.preventDefault();
        enableForm.post('/scp/account/security/two-factor/enable');
    };

    const confirmTwoFactor: FormSubmitHandler = (event) => {
        event.preventDefault();
        confirmForm.post('/scp/account/security/two-factor/confirm');
    };

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
                    <div className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                        {props.status}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                    <section className="rounded-2xl bg-white p-6 shadow-sm">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">Authenticator app</h2>
                                <p className="mt-1 text-sm text-gray-500">
                                    {twoFactor.enabled
                                        ? 'App-based two-factor authentication is enabled for your account.'
                                        : 'Enable TOTP to finish your Laravel-native authentication upgrade.'}
                                </p>
                            </div>
                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${twoFactor.enabled ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'}`}>
                                {twoFactor.enabled ? 'Enabled' : twoFactor.pending ? 'Pending confirmation' : 'Not enabled'}
                            </span>
                        </div>

                        {!twoFactor.pending && !twoFactor.enabled && (
                            <form onSubmit={enableTwoFactor} className="mt-6">
                                <button
                                    type="submit"
                                    disabled={enableForm.processing}
                                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {enableForm.processing ? 'Starting…' : 'Enable two-factor'}
                                </button>
                            </form>
                        )}

                        {(twoFactor.pending || twoFactor.enabled) && (
                            <div className="mt-6 space-y-5">
                                {twoFactor.qrCodeSvg && (
                                    <div className="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                        <p className="text-sm font-medium text-gray-700">Scan this QR code with your authenticator app.</p>
                                        <div
                                            className="mt-4 flex justify-center rounded-lg bg-white p-4"
                                            dangerouslySetInnerHTML={{ __html: twoFactor.qrCodeSvg }}
                                        />
                                    </div>
                                )}

                                {twoFactor.setupKey && (
                                    <div className="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                        <p className="text-sm font-medium text-gray-700">Manual setup key</p>
                                        <p className="mt-2 break-all font-mono text-sm text-gray-900">{twoFactor.setupKey}</p>
                                    </div>
                                )}

                                {!twoFactor.enabled && (
                                    <form onSubmit={confirmTwoFactor} className="space-y-3">
                                        <div>
                                            <label htmlFor="code" className="block text-sm font-medium text-gray-700">
                                                Confirm with a code from your app
                                            </label>
                                             <input
                                                 id="code"
                                                 type="text"
                                                 inputMode="numeric"
                                                 pattern="[0-9]*"
                                                 maxLength={6}
                                                 autoComplete="one-time-code"
                                                 aria-invalid={!!confirmForm.errors.code}
                                                 value={confirmForm.data.code}
                                                 onChange={(event) => confirmForm.setData('code', event.target.value)}
                                                 className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                             />
                                             {confirmForm.errors.code && (
                                                 <p role="alert" className="mt-1 text-xs text-red-600">{confirmForm.errors.code}</p>
                                             )}
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={confirmForm.processing}
                                            className="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-50"
                                        >
                                            {confirmForm.processing ? 'Confirming…' : 'Confirm setup'}
                                        </button>
                                    </form>
                                )}

                                {twoFactor.enabled && (
                                    <div className="flex flex-wrap gap-3">
                                        <Link
                                            href="/scp/account/security/confirm-password"
                                            className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            Confirm password
                                        </Link>

                                        <form onSubmit={regenerateCodes}>
                                            <button
                                                type="submit"
                                                disabled={regenerateForm.processing}
                                                className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                            >
                                                {regenerateForm.processing ? 'Regenerating…' : 'Regenerate recovery codes'}
                                            </button>
                                        </form>

                                        <form onSubmit={disableTwoFactor}>
                                            <button
                                                type="submit"
                                                disabled={disableForm.processing}
                                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                                            >
                                                {disableForm.processing ? 'Disabling…' : 'Disable two-factor'}
                                            </button>
                                        </form>
                                    </div>
                                )}
                            </div>
                        )}
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
