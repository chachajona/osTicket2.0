<?php

declare(strict_types=1);

namespace App\Http\Controllers\Panels;

use App\Http\Controllers\Controller;
use App\Models\Scp\StaffPreference;
use App\Models\Staff;
use App\Services\Panels\PanelLandingResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PanelSwitchController extends Controller
{
    public function __invoke(Request $request, PanelLandingResolver $resolver): RedirectResponse
    {
        /** @var Staff $staff */
        $staff = $request->user('staff');

        $validated = $request->validate([
            'panel' => ['required', 'string', Rule::in(['scp', 'admin'])],
        ]);

        $panel = $validated['panel'];

        abort_if($panel === 'admin' && ! $staff->canAccessAdminPanel(), 403);

        StaffPreference::forStaff((int) $staff->getAuthIdentifier())
            ->update(['last_active_panel' => $panel]);

        return redirect()->to($resolver->resolve($staff, $panel));
    }
}
