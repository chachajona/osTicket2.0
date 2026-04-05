import { router } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50">
            <div className="text-center">
                <h1 className="text-3xl font-bold text-gray-900">osTicket Staff Control Panel</h1>
                <p className="mt-2 text-gray-500">Welcome back.</p>
                <div className="mt-6">
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
    );
}
