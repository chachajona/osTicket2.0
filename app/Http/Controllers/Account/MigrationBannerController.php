<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MigrationBannerController extends Controller
{
    public function dismiss(Request $request): RedirectResponse
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');

        $migration = $staff->authMigration()->first();

        if (! $migration || is_null($migration->migrated_at)) {
            return back();
        }

        $migration->forceFill([
            'dismissed_migration_banner_at' => now(),
        ])->save();

        return back();
    }
}
