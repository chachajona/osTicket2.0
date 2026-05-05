<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Staff;
use App\Models\StaffDeptAccess;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StaffService
{
    use NormalizesInput;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Staff $actor): Staff
    {
        $teamIds = $this->normalizeTeamIds($data['teams'] ?? []);
        $deptAccess = $this->normalizeDepartmentAccess($data['dept_access'] ?? [], (int) $data['dept_id']);

        /** @var Staff $staff */
        $staff = DB::connection('legacy')->transaction(function () use ($data, $teamIds, $deptAccess): Staff {
            $staff = Staff::query()->create($this->staffPayload($data, create: true));

            $this->syncDepartmentAccess($staff, $deptAccess);
            $this->syncTeams($staff, $teamIds);
            $this->syncPrimaryRole($staff, (int) $data['role_id']);

            return $staff;
        });

        $staff->load(['department', 'role', 'departmentAccesses', 'teams', 'twoFactorCredential']);
        $this->auditLogger->record($actor, 'staff.create', $staff, before: null, after: $this->snapshot($staff));

        return $staff->fresh(['department', 'role', 'departmentAccesses', 'teams', 'twoFactorCredential']) ?? $staff;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Staff $staff, array $data, Staff $actor): Staff
    {
        $staff->loadMissing(['department', 'role', 'departmentAccesses', 'teams', 'twoFactorCredential']);
        $before = $this->snapshot($staff);
        $teamIds = $this->normalizeTeamIds($data['teams'] ?? []);
        $deptAccess = $this->normalizeDepartmentAccess($data['dept_access'] ?? [], (int) $data['dept_id']);

        DB::connection('legacy')->transaction(function () use ($staff, $data, $teamIds, $deptAccess): void {
            $staff->forceFill($this->staffPayload($data, create: false))->save();

            $this->syncDepartmentAccess($staff, $deptAccess);
            $this->syncTeams($staff, $teamIds);
            $this->syncPrimaryRole($staff, (int) $data['role_id']);
        });

        $staff->refresh()->load(['department', 'role', 'departmentAccesses', 'teams', 'twoFactorCredential']);
        $this->auditLogger->record($actor, 'staff.update', $staff, before: $before, after: $this->snapshot($staff));

        return $staff;
    }

    public function delete(Staff $staff, Staff $actor): void
    {
        $staff->loadMissing(['department', 'role', 'departmentAccesses', 'teams', 'twoFactorCredential']);
        $before = $this->snapshot($staff);

        DB::connection('legacy')->transaction(function () use ($staff): void {
            StaffDeptAccess::query()->where('staff_id', (int) $staff->getKey())->delete();
            TeamMember::query()->where('staff_id', (int) $staff->getKey())->delete();
            $staff->syncRoles([]);
            $staff->delete();
        });

        $this->auditLogger->record($actor, 'staff.delete', $staff, before: $before, after: null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function staffPayload(array $data, bool $create): array
    {
        $password = $data['password'] ?? null;

        $payload = [
            'dept_id' => (int) $data['dept_id'],
            'role_id' => (int) $data['role_id'],
            'username' => trim((string) $data['username']),
            'firstname' => trim((string) $data['firstname']),
            'lastname' => trim((string) $data['lastname']),
            'email' => trim((string) $data['email']),
            'phone' => $this->normalizeNullableString($data['phone'] ?? null),
            'mobile' => $this->normalizeNullableString($data['mobile'] ?? null),
            'signature' => $this->normalizeNullableString($data['signature'] ?? null),
            'isactive' => ! empty($data['isactive']) ? 1 : 0,
            'isadmin' => ! empty($data['isadmin']) ? 1 : 0,
            'isvisible' => ! empty($data['isvisible']) ? 1 : 0,
            'change_passwd' => ! empty($data['change_passwd']) ? 1 : 0,
            'updated' => now(),
        ];

        if ($create) {
            $payload['created'] = now();
        }

        if (is_string($password) && trim($password) !== '') {
            $payload['passwd'] = Hash::make($password);
            $payload['passwdreset'] = now();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Staff $staff): array
    {
        $deptAccess = $staff->departmentAccesses
            ->sortBy('dept_id')
            ->map(static fn (StaffDeptAccess $access): array => [
                'dept_id' => (int) $access->dept_id,
                'role_id' => (int) $access->role_id,
            ])
            ->values()
            ->all();

        $teams = $staff->teams
            ->pluck('team_id')
            ->map(static fn (mixed $teamId): int => (int) $teamId)
            ->sort()
            ->values()
            ->all();

        return [
            'staff_id' => (int) $staff->getKey(),
            'username' => (string) $staff->username,
            'firstname' => (string) $staff->firstname,
            'lastname' => (string) $staff->lastname,
            'email' => (string) $staff->email,
            'phone' => $this->normalizeNullableString($staff->phone ?? null),
            'mobile' => $this->normalizeNullableString($staff->mobile ?? null),
            'signature' => $this->normalizeNullableString($staff->signature ?? null),
            'dept_id' => (int) $staff->dept_id,
            'department_name' => $staff->department?->name,
            'role_id' => $staff->role_id !== null ? (int) $staff->role_id : null,
            'role_name' => $staff->role?->name,
            'isactive' => (bool) ($staff->isactive ?? 0),
            'isadmin' => (bool) ($staff->isadmin ?? 0),
            'isvisible' => (bool) ($staff->isvisible ?? 0),
            'change_passwd' => (bool) ($staff->change_passwd ?? 0),
            'password' => $staff->passwd !== null && $staff->passwd !== '' ? '[redacted]' : null,
            'dept_access' => $deptAccess,
            'teams' => $teams,
            'two_factor_enabled' => $staff->hasTotpEnabled(),
            'two_factor_confirmed_at' => $staff->two_factor_confirmed_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, mixed>  $teamIds
     * @return list<int>
     */
    private function normalizeTeamIds(array $teamIds): array
    {
        $normalized = array_map(
            static fn (mixed $teamId): int => (int) $teamId,
            array_filter($teamIds, static fn (mixed $teamId): bool => $teamId !== null && $teamId !== ''),
        );

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $accesses
     * @return list<array{dept_id:int,role_id:int}>
     */
    private function normalizeDepartmentAccess(array $accesses, int $primaryDeptId): array
    {
        $normalized = [];

        foreach ($accesses as $access) {
            if (! is_array($access)) {
                continue;
            }

            $deptId = (int) ($access['dept_id'] ?? 0);
            $roleId = (int) ($access['role_id'] ?? 0);

            if ($deptId < 1 || $roleId < 1 || $deptId === $primaryDeptId) {
                continue;
            }

            $normalized[$deptId] = [
                'dept_id' => $deptId,
                'role_id' => $roleId,
            ];
        }

        ksort($normalized);

        return array_values($normalized);
    }

    /**
     * @param  list<array{dept_id:int,role_id:int}>  $deptAccess
     */
    private function syncDepartmentAccess(Staff $staff, array $deptAccess): void
    {
        $staffId = (int) $staff->getKey();
        $deptIds = array_column($deptAccess, 'dept_id');

        $query = StaffDeptAccess::query()->where('staff_id', $staffId);

        if ($deptIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('dept_id', $deptIds)->delete();

        foreach ($deptAccess as $access) {
            $updated = StaffDeptAccess::query()
                ->where('staff_id', $staffId)
                ->where('dept_id', $access['dept_id'])
                ->update([
                    'role_id' => $access['role_id'],
                    'flags' => 0,
                ]);

            if ($updated > 0) {
                continue;
            }

            StaffDeptAccess::query()->create([
                'staff_id' => $staffId,
                'dept_id' => $access['dept_id'],
                'role_id' => $access['role_id'],
                'flags' => 0,
            ]);
        }
    }

    /**
     * @param  list<int>  $teamIds
     */
    private function syncTeams(Staff $staff, array $teamIds): void
    {
        $staffId = (int) $staff->getKey();
        $query = TeamMember::query()->where('staff_id', $staffId);

        if ($teamIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('team_id', $teamIds)->delete();

        $existingIds = TeamMember::query()
            ->where('staff_id', $staffId)
            ->pluck('team_id')
            ->map(static fn (mixed $teamId): int => (int) $teamId)
            ->all();

        foreach (array_diff($teamIds, $existingIds) as $teamId) {
            TeamMember::query()->create([
                'team_id' => $teamId,
                'staff_id' => $staffId,
                'flags' => 0,
            ]);
        }
    }

    private function syncPrimaryRole(Staff $staff, int $roleId): void
    {
        $staff->syncRoles([$roleId]);
    }
}
