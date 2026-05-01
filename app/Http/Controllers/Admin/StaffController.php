<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Staff\StoreStaffRequest;
use App\Http\Requests\Admin\Staff\UpdateStaffRequest;
use App\Models\Department;
use App\Models\LegacyRole;
use App\Models\Staff;
use App\Models\Team;
use App\Services\Admin\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(
        private readonly StaffService $staffService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Staff::class);

        $staff = Staff::query()
            ->with(['department', 'role'])
            ->orderBy('firstname')
            ->orderBy('lastname')
            ->orderBy('username')
            ->paginate(15)
            ->through(fn (Staff $member): array => [
                'id' => (int) $member->getKey(),
                'username' => (string) $member->username,
                'name' => $member->displayName(),
                'email' => (string) $member->email,
                'department' => $member->department?->name,
                'role' => $member->role?->name,
                'isactive' => (bool) ($member->isactive ?? 0),
            ]);

        return Inertia::render('Admin/Staff/Index', [
            'staff' => $staff,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Staff::class);

        return Inertia::render('Admin/Staff/Edit', [
            'staffMember' => null,
            ...$this->options(),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $this->authorize('create', Staff::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $staff = $this->staffService->create($request->validated(), $actor);

        return redirect()
            ->route('admin.staff.edit', $staff)
            ->with('status', 'Staff member created.');
    }

    public function edit(Staff $staff): Response
    {
        $this->authorize('update', $staff);

        $staff->loadMissing(['department', 'role', 'departmentAccesses', 'teams', 'twoFactorCredential']);

        return Inertia::render('Admin/Staff/Edit', [
            'staffMember' => [
                'id' => (int) $staff->getKey(),
                'username' => (string) $staff->username,
                'firstname' => (string) $staff->firstname,
                'lastname' => (string) $staff->lastname,
                'email' => (string) $staff->email,
                'phone' => $staff->phone !== '' ? $staff->phone : null,
                'mobile' => $staff->mobile !== '' ? $staff->mobile : null,
                'signature' => $staff->signature !== '' ? $staff->signature : null,
                'dept_id' => (int) $staff->dept_id,
                'role_id' => $staff->role_id !== null ? (int) $staff->role_id : null,
                'isactive' => (bool) ($staff->isactive ?? 0),
                'isadmin' => (bool) ($staff->isadmin ?? 0),
                'isvisible' => (bool) ($staff->isvisible ?? 0),
                'change_passwd' => (bool) ($staff->change_passwd ?? 0),
                'dept_access' => $staff->departmentAccesses
                    ->sortBy('dept_id')
                    ->map(static fn ($access): array => [
                        'dept_id' => (int) $access->dept_id,
                        'role_id' => (int) $access->role_id,
                    ])
                    ->values()
                    ->all(),
                'teams' => $staff->teams
                    ->pluck('team_id')
                    ->map(static fn (mixed $teamId): int => (int) $teamId)
                    ->sort()
                    ->values()
                    ->all(),
                'two_factor' => [
                    'enabled' => $staff->hasTotpEnabled(),
                    'confirmed_at' => $staff->two_factor_confirmed_at?->toIso8601String(),
                    'recovery_codes_count' => count($staff->recoveryCodes()),
                ],
            ],
            ...$this->options(),
        ]);
    }

    public function update(UpdateStaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->authorize('update', $staff);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->staffService->update($staff, $request->validated(), $actor);

        return redirect()
            ->route('admin.staff.edit', $staff)
            ->with('status', 'Staff member updated.');
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->staffService->delete($staff, $actor);

        return redirect()
            ->route('admin.staff.index')
            ->with('status', 'Staff member deleted.');
    }

    /**
     * @return array{departmentOptions:list<array{id:int,name:string}>,roleOptions:list<array{id:int,name:string}>,teamOptions:list<array{id:int,name:string}>}
     */
    private function options(): array
    {
        return [
            'departmentOptions' => Department::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Department $department): array => [
                    'id' => (int) $department->getKey(),
                    'name' => (string) $department->name,
                ])
                ->values()
                ->all(),
            'roleOptions' => LegacyRole::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (LegacyRole $role): array => [
                    'id' => (int) $role->getKey(),
                    'name' => (string) $role->name,
                ])
                ->values()
                ->all(),
            'teamOptions' => Team::query()
                ->orderBy('name')
                ->get(['team_id', 'name'])
                ->map(fn (Team $team): array => [
                    'id' => (int) $team->getKey(),
                    'name' => (string) $team->name,
                ])
                ->values()
                ->all(),
        ];
    }
}
