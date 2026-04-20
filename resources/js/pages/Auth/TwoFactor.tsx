import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Props {
    status?: string;
    errors?: { code?: string };
}

export default function TwoFactor({ status }: Props) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });
    const [isResending, setIsResending] = useState(false);

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/scp/2fa');
    }

    function resend() {
        router.post(
            '/scp/2fa/resend',
            {},
            {
                preserveScroll: true,
                onStart: () => setIsResending(true),
                onFinish: () => setIsResending(false),
            }
        );
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50">
            <div className="w-full max-w-md">
                <div className="rounded-2xl bg-white px-8 py-10 shadow-lg">
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-bold tracking-tight text-gray-900">{t('auth.two_factor.title')}</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {t('auth.two_factor.description')}
                        </p>
                    </div>

                    {status && (
                        <div className="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label htmlFor="code" className="block text-sm font-medium text-gray-700">
                                {t('auth.two_factor.code_label')}
                            </label>
                            <input
                                id="code"
                                type="text"
                                inputMode="numeric"
                                pattern="[0-9]{6}"
                                maxLength={6}
                                autoFocus
                                autoComplete="one-time-code"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value)}
                                className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-center text-xl tracking-widest shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                placeholder={t('auth.two_factor.code_placeholder')}
                            />
                            {errors.code && (
                                <p className="mt-1 text-xs text-red-600">{errors.code}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                        >
                            {processing ? t('auth.two_factor.verifying') : t('auth.two_factor.verify')}
                        </button>
                    </form>

                    <div className="mt-4 text-center">
                        <button
                            type="button"
                            onClick={resend}
                            disabled={processing || isResending}
                            className="text-sm text-blue-600 hover:underline disabled:opacity-50"
                        >
                            {isResending ? t('auth.two_factor.resending') : t('auth.two_factor.resend')}
                        </button>
                    </div>

                    <div className="mt-4 text-center">
                        <a href="/scp/login" className="text-sm text-gray-500 hover:underline">
                            {t('auth.two_factor.back_to_login')}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}
