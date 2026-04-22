import { Link, router } from '@inertiajs/react';

import { MigrationBanner } from '@/components/auth/MigrationBanner';

export default function Dashboard() {
    return (
        <div className="min-h-screen bg-gray-50 px-4 py-10">
            <div className="mx-auto max-w-4xl space-y-6">
                <MigrationBanner />

                <div className="rounded-2xl bg-white p-10 text-center shadow-sm">
                    <h1 className="text-3xl font-bold text-gray-900">osTicket Staff Control Panel</h1>
                    <p className="mt-2 text-gray-500">Welcome back.</p>
                    <div className="mt-6 flex items-center justify-center gap-3">
                    <Link
                        href="/scp/account/security"
                        className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-white"
                    >
                        Security settings
                    </Link>
                    <button
                        type="button"
                        onClick={() => router.post('/scp/logout')}
                        className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        Sign out
                    </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
