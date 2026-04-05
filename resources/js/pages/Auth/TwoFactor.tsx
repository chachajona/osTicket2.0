import { useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

interface Props {
    status?: string;
    errors?: { code?: string };
}

export default function TwoFactor({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/scp/2fa');
    }

    function resend() {
        post('/scp/2fa/resend');
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50">
            <div className="w-full max-w-md">
                <div className="rounded-2xl bg-white px-8 py-10 shadow-lg">
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-bold tracking-tight text-gray-900">Two-Factor Verification</h1>
                        <p className="mt-1 text-sm text-gray-500">
                            Enter the 6-digit code sent to your email address.
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
                                Verification Code
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
                                placeholder="000000"
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
                            {processing ? 'Verifying…' : 'Verify'}
                        </button>
                    </form>

                    <div className="mt-4 text-center">
                        <button
                            type="button"
                            onClick={resend}
                            disabled={processing}
                            className="text-sm text-blue-600 hover:underline disabled:opacity-50"
                        >
                            Resend code
                        </button>
                    </div>

                    <div className="mt-4 text-center">
                        <a href="/scp/login" className="text-sm text-gray-500 hover:underline">
                            Back to login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}
