<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Scp\StaffPreference;
use App\Models\Staff;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private const MIGRATION_BANNER_CACHE_TTL_SECONDS = 300;

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
        $routeName = $request->route()?->getName() ?? '';

        return [
            ...parent::share($request),
            'status' => fn () => $request->session()->get('status'),
            'currentPanel' => fn () => $this->getCurrentPanel($routeName, $staff),
            'currentPanelNav' => fn () => $this->getCurrentPanelNav($routeName),
            'auth' => fn () => [
                'staff' => $staff
                    ? $this->buildStaffAuthData($staff, $request)
                    : null,
                'throttle' => [
                    'attemptsRemaining' => $request->session()->get('throttle.attemptsRemaining'),
                    'secondsUntilRetry' => $request->session()->get('throttle.secondsUntilRetry'),
                    'username' => $request->session()->get('throttle.username'),
                ],
            ],
        ];
    }

    private function buildStaffAuthData(Staff $staff, Request $request): array
    {
        $preferences = StaffPreference::forStaff($staff->staff_id);

        return [
            'id' => $staff->staff_id,
            'name' => trim(($staff->firstname ?? '').' '.($staff->lastname ?? '')) ?: $staff->username,
            'username' => $staff->username,
            'isAdmin' => (bool) $staff->isadmin,
            'canAccessAdminPanel' => $staff->canAccessAdminPanel(),
            'migrationBanner' => $this->migrationBannerVisible($request, $staff),
            'lastActivePanel' => $preferences->last_active_panel,
            'defaultScpTab' => $preferences->default_scp_tab,
            'defaultAdminTab' => $preferences->default_admin_tab,
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
        $cached = $request->session()->get($key);

        if ($this->hasFreshMigrationBannerCache($cached)) {
            return $cached['visible'];
        }

        $visible = $this->shouldShowMigrationBanner($staff);
        $request->session()->put($key, [
            'visible' => $visible,
            'cached_at' => now()->timestamp,
        ]);

        return $visible;
    }

    private function hasFreshMigrationBannerCache(mixed $cached): bool
    {
        if (! is_array($cached) || ! array_key_exists('visible', $cached) || ! array_key_exists('cached_at', $cached)) {
            return false;
        }

        if (! is_bool($cached['visible']) || ! is_numeric($cached['cached_at'])) {
            return false;
        }

        return now()->timestamp - (int) $cached['cached_at'] < self::MIGRATION_BANNER_CACHE_TTL_SECONDS;
    }

    private function getCurrentPanel(string $routeName, ?Staff $staff): ?string
    {
        if (! $staff) {
            return null;
        }

        if (str_starts_with($routeName, 'admin.')) {
            return 'admin';
        }

        return 'scp';
    }

    private function getCurrentPanelNav(string $routeName): array
    {
        $subId = null;

        if (str_starts_with($routeName, 'admin.')) {
            // Extract subId from route name like 'admin.help-topics.index' -> 'help-topics'
            $parts = explode('.', $routeName);
            if (count($parts) >= 2) {
                $subId = $parts[1];
            }
        }

        return ['subId' => $subId];
    }
}
