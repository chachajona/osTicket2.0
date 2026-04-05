<?php

namespace App\Http\Middleware;

use App\Services\DepartmentPermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireDepartmentAccess
{
    public function __construct(private readonly DepartmentPermissionService $service) {}

    public function handle(Request $request, Closure $next, string $deptIdParam = 'dept_id'): Response
    {
        $staff = Auth::guard('staff')->user();

        if (! $staff) {
            return redirect()->route('scp.login');
        }

        $deptId = (int) $request->route($deptIdParam, $request->input($deptIdParam, 0));

        if ($deptId === 0) {
            return $next($request);
        }

        if (! $this->service->hasAccessToDepartment($staff, $deptId)) {
            abort(403, 'You do not have access to this department.');
        }

        return $next($request);
    }
}
