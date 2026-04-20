<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmPassword;

class ConfirmPasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    public function store(
        Request $request,
        ConfirmPassword $confirmPassword,
        StatefulGuard $guard,
    ): RedirectResponse {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var Staff $staff */
        $staff = $request->user('staff');

        if (! $confirmPassword($guard, $staff, $validated['password'])) {
            return back()->withErrors([
                'password' => 'The provided password was incorrect.',
            ]);
        }

        $staff->rehashPasswordIfNeeded($validated['password']);
        $request->session()->passwordConfirmed();

        return redirect()
            ->intended(route('scp.account.security'))
            ->with('status', 'Password confirmed.');
    }
}
