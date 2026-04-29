<?php

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Services\Scp\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        $staff = $request->user('staff');
        $range = $request->query('range', 'last_6_months');

        if (! in_array($range, ['last_7_days', 'last_30_days', 'last_3_months', 'last_6_months'], true)) {
            $range = 'last_6_months';
        }

        return Inertia::render('Dashboard', [
            'metrics' => $this->dashboard->summary($staff, $range),
            'range' => $range,
        ]);
    }
}
