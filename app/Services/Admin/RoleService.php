<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\LegacyRole;
use App\Models\Role;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Coordinates role management between legacy records and spatie permissions.
 *
 * Syncs role definitions and granted permissions across both storage systems
 * while maintaining audit trails and permission cache invalidation.
 */
class RoleService
{
    use NormalizesInput;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly PermissionRegistrar $permissions,
    ) {}

    public function create(array $data, Staff $actor): Role
    {
        $permissions = $this->normalizePermissions($data['permissions'] ?? []);

        /** @var Role $role */
        $role = DB::connection('legacy')->transaction(function () use ($data, $permissions): Role {
            $role = Role::query()->create([
                'flags' => 1,
                'name' => trim((string) $data['name']),
                'permissions' => $this->encodePermissions($permissions),
                'notes' => $this->normalizeString($data['notes'] ?? null),
                'created' => now(),
                'updated' => now(),
            ]);

            $spatieRole = $this->createSpatieRole($role, (string) $role->name);

            $spatieRole->syncPermissions($permissions);

            return $role;
        });

        $after = $this->payload($role, $permissions);
        $this->auditLogger->record($actor, 'role.create', $role, before: null, after: $after);
        $this->permissions->forgetCachedPermissions();

        return $role->fresh() ?? $role;
    }

    public function update(Role $role, array $data, Staff $actor): Role
    {
        $beforePermissions = $this->permissionsForRole($role);
        $before = $this->payload($role, $beforePermissions);
        $permissions = $this->normalizePermissions($data['permissions'] ?? []);
        $originalName = (string) $role->name;

        DB::connection('legacy')->transaction(function () use ($role, $data, $permissions, $originalName): void {
            $spatieRole = $this->findOrCreateSpatieRole($role, $originalName);

            $role->forceFill([
                'name' => trim((string) $data['name']),
                'notes' => $this->normalizeString($data['notes'] ?? null),
                'permissions' => $this->encodePermissions($permissions),
                'updated' => now(),
            ])->save();

            $spatieRole->forceFill([
                'name' => (string) $role->name,
                'guard_name' => 'staff',
            ])->save();

            $spatieRole->syncPermissions($permissions);
        });

        $role->refresh();
        $after = $this->payload($role, $permissions);
        $this->auditLogger->record($actor, 'role.update', $role, before: $before, after: $after);
        $this->permissions->forgetCachedPermissions();

        return $role;
    }

    public function delete(Role $role, Staff $actor): void
    {
        $permissions = $this->permissionsForRole($role);
        $before = $this->payload($role, $permissions);
        $spatieRole = LegacyRole::query()->where('name', (string) $role->name)->first();

        DB::connection('legacy')->transaction(function () use ($role, $spatieRole): void {
            $spatieRole?->delete();
            $role->delete();
        });

        $this->auditLogger->record($actor, 'role.delete', $role, before: $before, after: null);
        $this->permissions->forgetCachedPermissions();
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return list<string>
     */
    public function normalizePermissions(array $permissions): array
    {
        $normalized = array_values(array_filter(array_map(
            static fn (mixed $permission): string => trim((string) $permission),
            $permissions,
        ), static fn (string $permission): bool => $permission !== ''));

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public function permissionsForRole(Role $role): array
    {
        $decoded = json_decode((string) ($role->permissions ?? '[]'), true);

        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $this->normalizePermissions($decoded);
        }

        return $this->normalizePermissions(array_keys(array_filter(
            $decoded,
            static fn (mixed $value): bool => (bool) $value,
        )));
    }

    /**
     * @param  list<string>  $permissions
     * @return array<string, mixed>
     */
    private function payload(Role $role, array $permissions): array
    {
        return [
            'id' => (int) $role->getKey(),
            'name' => (string) $role->name,
            'notes' => $role->notes !== '' ? $role->notes : null,
            'flags' => (int) ($role->flags ?? 0),
            'permissions' => $permissions,
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function encodePermissions(array $permissions): string
    {
        return json_encode($permissions) ?: '[]';
    }

    private function findOrCreateSpatieRole(Role $legacyRole, string $name): LegacyRole
    {
        return LegacyRole::query()->find((int) $legacyRole->getKey())
            ?? LegacyRole::query()->where('name', $name)->first()
            ?? $this->createSpatieRole($legacyRole, $name);
    }

    private function createSpatieRole(Role $legacyRole, string $name): LegacyRole
    {
        $role = new LegacyRole;
        $role->forceFill([
            'id' => (int) $legacyRole->getKey(),
            'name' => $name,
            'guard_name' => 'staff',
        ])->save();

        return $role;
    }
}
