<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Models\Role;
use App\Models\Staff;
use App\Services\Admin\RoleService;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->orderBy('name')
            ->paginate(15)
            ->through(function (Role $role): array {
                $permissions = $this->roles->permissionsForRole($role);

                return [
                    'id' => (int) $role->getKey(),
                    'name' => (string) $role->name,
                    'notes' => $role->notes !== '' ? $role->notes : null,
                    'flags' => (int) ($role->flags ?? 0),
                    'permissions_count' => count($permissions),
                ];
            });

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => null,
            'permissions' => $this->permissionGroups(),
            'selectedPermissions' => [],
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $role = $this->roles->create($request->validated(), $actor);

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('status', 'Role created.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role): Response
    {
        $this->authorize('update', $role);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => (int) $role->getKey(),
                'name' => (string) $role->name,
                'notes' => $role->notes !== '' ? $role->notes : null,
                'flags' => (int) ($role->flags ?? 0),
            ],
            'permissions' => $this->permissionGroups(),
            'selectedPermissions' => $this->roles->permissionsForRole($role),
        ]);
    }

    /**
     * Update role in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->roles->update($role, $request->validated(), $actor);

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('status', 'Role updated.');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->roles->delete($role, $actor);

        return redirect()
            ->route('admin.roles.index')
            ->with('status', 'Role deleted.');
    }

    /**
     * Get permission groups for roles.
     *
     * @return list<array{id:string,name:string,permissions:list<array{id:string,name:string}>}>
     */
    private function permissionGroups(): array
    {
        $groups = [];

        foreach (PermissionCatalogSeeder::permissionGroups() as $group => $permissions) {
            $groups[] = [
                'id' => $group,
                'name' => str($group)->headline()->toString(),
                'permissions' => array_map(fn (string $permission): array => [
                    'id' => $permission,
                    'name' => str(str_replace(['.', '_'], ' ', $permission))->headline()->toString(),
                ], $permissions),
            ];
        }

        return $groups;
    }
}
