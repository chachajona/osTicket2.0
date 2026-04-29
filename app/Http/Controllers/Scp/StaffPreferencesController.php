<?php

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Models\Scp\StaffPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StaffPreferencesController extends Controller
{
    public function show(Request $request): Response
    {
        $staffId = (int) $request->user('staff')->staff_id;
        $preferences = StaffPreference::firstOrCreate(
            ['staff_id' => $staffId],
            StaffPreference::defaults($staffId),
        );

        return Inertia::render('Scp/Preferences/Index', [
            'preferences' => $preferences->only(['theme', 'language', 'timezone', 'notifications']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $staffId = (int) $request->user('staff')->staff_id;
        $validated = $request->validate([
            'theme' => ['required', Rule::in(['light', 'dark', 'system'])],
            'language' => ['nullable', 'string', 'max:16'],
            'timezone' => ['nullable', 'timezone'],
            'notifications' => ['array'],
            'notifications.desktop' => ['boolean'],
            'notifications.email' => ['boolean'],
            'notifications.sound' => ['boolean'],
        ]);

        $defaults = StaffPreference::defaults($staffId);
        $validated['notifications'] = array_merge(
            $defaults['notifications'],
            $validated['notifications'] ?? [],
        );

        StaffPreference::updateOrCreate(
            ['staff_id' => $staffId],
            $validated,
        );

        return back()->with('status', 'Preferences saved.');
    }
}
