import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

export default function TwoFactorApp() {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/scp/2fa-app');
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
            <div className="w-full max-w-md rounded-2xl bg-white px-8 py-10 shadow-lg">
                <div className="mb-8 text-center">
                    <h1 className="text-2xl font-bold tracking-tight text-gray-900">App verification required</h1>
                    <p className="mt-2 text-sm text-gray-500">
                        Enter the 6-digit code from your authenticator app or use one of your recovery codes.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <div>
                        <label htmlFor="code" className="block text-sm font-medium text-gray-700">
                            Authentication code or recovery code
                        </label>
                        <input
                            id="code"
                            type="text"
                            autoFocus
                            autoComplete="one-time-code"
                            value={data.code}
                            onChange={(event) => setData('code', event.target.value)}
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        {errors.code && <p className="mt-1 text-xs text-red-600">{errors.code}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        {processing ? 'Verifying…' : 'Verify and continue'}
                    </button>
                </form>

                <div className="mt-4 text-center">
                    <Link href="/scp/login" className="text-sm text-gray-500 hover:underline">
                        Back to login
                    </Link>
                </div>
            </div>
        </div>
    );
}
