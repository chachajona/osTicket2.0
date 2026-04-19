import { Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface PageProps extends Record<string, unknown> {
    status?: string;
}

export default function ConfirmPassword() {
    const { props } = usePage<PageProps>();
    const { data, setData, post, processing, errors } = useForm({
        password: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        post('/scp/account/security/confirm-password');
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4">
            <div className="w-full max-w-md rounded-2xl bg-white px-8 py-10 shadow-lg">
                <div className="mb-8 text-center">
                    <h1 className="text-2xl font-bold tracking-tight text-gray-900">Confirm your password</h1>
                    <p className="mt-2 text-sm text-gray-500">
                        Confirm your password before changing two-factor authentication settings.
                    </p>
                </div>

                {props.status && (
                    <div className="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">
                        {props.status}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5">
                    <div>
                        <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                            Password
                        </label>
                        <input
                            id="password"
                            type="password"
                            autoComplete="current-password"
                            autoFocus
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        />
                        {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        {processing ? 'Confirming…' : 'Confirm password'}
                    </button>
                </form>

                <div className="mt-4 text-center">
                    <Link href="/scp/account/security" className="text-sm text-gray-500 hover:underline">
                        Back to security settings
                    </Link>
                </div>
            </div>
        </div>
    );
}
