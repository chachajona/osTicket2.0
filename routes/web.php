<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/scp/test-auth', function () {
    if (Auth::guard('staff')->check()) {
        $staff = Auth::guard('staff')->user();

        return response()->json([
            'authenticated' => true,
            'staff_id' => $staff->staff_id,
            'name' => $staff->firstname.' '.$staff->lastname,
            'username' => $staff->username,
        ]);
    }

    return response()->json(['authenticated' => false]);
});
