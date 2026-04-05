export default function Dashboard() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50">
            <div className="text-center">
                <h1 className="text-3xl font-bold text-gray-900">osTicket Staff Control Panel</h1>
                <p className="mt-2 text-gray-500">Welcome back.</p>
                <form method="POST" action="/scp/logout" className="mt-6">
                    <input type="hidden" name="_token" value="" />
                    <button
                        type="submit"
                        className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    );
}
