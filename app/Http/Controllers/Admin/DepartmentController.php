<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Department\StoreDepartmentRequest;
use App\Http\Requests\Admin\Department\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\Staff;
use App\Services\Admin\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    use ProvidesModelOptions;

    public function __construct(
        private readonly DepartmentService $departments,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Department::class);

        $departments = Department::query()
            ->with(['manager', 'sla'])
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Department $department): array => $this->serializeDepartment($department));

        return Inertia::render('Admin/Departments/Index', [
            'departments' => $departments,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Department::class);

        return Inertia::render('Admin/Departments/Edit', [
            'department' => null,
            'departmentOptions' => $this->departmentOptions(),
            'slaOptions' => $this->slaOptions(),
            'managerOptions' => $this->staffOptions(),
            'emailOptions' => $this->emailOptions(),
            'templateOptions' => $this->templateOptions(),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $this->authorize('create', Department::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $department = $this->departments->create($request->validated(), $actor);

        return redirect()
            ->route('admin.departments.edit', $department)
            ->with('status', 'Department created.');
    }

    public function edit(Department $department): Response
    {
        $this->authorize('update', $department);

        $department->loadMissing(['manager', 'sla', 'email', 'template', 'parent']);

        return Inertia::render('Admin/Departments/Edit', [
            'department' => $this->serializeDepartment($department, detailed: true),
            'departmentOptions' => $this->departmentOptions(excludeId: (int) $department->getKey()),
            'slaOptions' => $this->slaOptions(),
            'managerOptions' => $this->staffOptions(),
            'emailOptions' => $this->emailOptions(),
            'templateOptions' => $this->templateOptions(),
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->authorize('update', $department);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->departments->update($department, $request->validated(), $actor);

        return redirect()
            ->route('admin.departments.edit', $department)
            ->with('status', 'Department updated.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->departments->delete($department, $actor);

        return redirect()
            ->route('admin.departments.index')
            ->with('status', 'Department deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDepartment(Department $department, bool $detailed = false): array
    {
        $payload = [
            'id' => (int) $department->getKey(),
            'name' => (string) $department->name,
            'manager_id' => $department->manager_id !== null ? (int) $department->manager_id : null,
            'manager_name' => $department->manager?->displayName(),
            'sla_id' => $department->sla_id !== null ? (int) $department->sla_id : null,
            'sla_name' => $department->sla?->name,
            'ispublic' => (bool) ($department->ispublic ?? 0),
        ];

        if (! $detailed) {
            return $payload;
        }

        return [
            ...$payload,
            'dept_id' => $department->dept_id !== null ? (int) $department->dept_id : null,
            'parent_name' => $department->parent?->name,
            'email_id' => $department->email_id !== null ? (int) $department->email_id : null,
            'email_name' => $department->email?->name,
            'template_id' => $department->tpl_id !== null ? (int) $department->tpl_id : null,
            'template_name' => $department->template?->name,
            'signature' => filled($department->signature) ? (string) $department->signature : null,
        ];
    }
}
