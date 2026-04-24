<?php

namespace App\Http\Middleware;

use App\Models\Staff;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var Staff|null $staff */
        $staff = $request->user('staff');

        return [
            ...parent::share($request),
            'status' => fn () => $request->session()->get('status'),
            'auth' => fn () => [
                'staff' => $staff
                    ? [
                        'id' => $staff->staff_id,
                        'name' => trim(($staff->firstname ?? '').' '.($staff->lastname ?? '')) ?: $staff->username,
                        'username' => $staff->username,
                        'migrationBanner' => $this->migrationBannerVisible($request, $staff),
                    ]
                    : null,
                'throttle' => [
                    'attemptsRemaining' => $request->session()->get('throttle.attemptsRemaining'),
                    'secondsUntilRetry' => $request->session()->get('throttle.secondsUntilRetry'),
                    'username' => $request->session()->get('throttle.username'),
                ],
            ],
        ];
    }

    private function shouldShowMigrationBanner(Staff $staff): bool
    {
        $staff->loadMissing(['authMigration', 'twoFactorCredential']);
        $migration = $staff->authMigration;

        if (! $migration || is_null($migration->migrated_at)) {
            return false;
        }

        return is_null($migration->dismissed_migration_banner_at) && ! $staff->hasTotpEnabled();
    }

    private function migrationBannerVisible(Request $request, Staff $staff): bool
    {
        if (! $request->routeIs('scp.dashboard')) {
            return false;
        }

        $key = "auth.migration_banner.{$staff->staff_id}";

        if ($request->session()->has($key)) {
            return (bool) $request->session()->get($key);
        }

        $visible = $this->shouldShowMigrationBanner($staff);

        if ($visible) {
            $request->session()->put($key, true);
        } else {
            $request->session()->forget($key);
        }

        return $visible;
    }
}
