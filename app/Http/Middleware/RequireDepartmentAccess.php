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

        $rawDeptId = $request->route($deptIdParam, $request->input($deptIdParam));

        if ($rawDeptId === null || $rawDeptId === '') {
            return $next($request);
        }

        $deptId = filter_var($rawDeptId, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($deptId === false) {
            abort(403, 'Invalid department context.');
        }

        if (! $this->service->hasAccessToDepartment($staff, $deptId)) {
            abort(403, 'You do not have access to this department.');
        }

        return $next($request);
    }
}
