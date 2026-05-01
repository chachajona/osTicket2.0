<?php

declare(strict_types=1);

namespace App\Migration;

use App\Models\LegacyPermission;
use App\Models\LegacyRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class PermissionsTranslator
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function translate(): array
    {
        $startedAt = microtime(true);

        /** @var array<string, mixed> $result */
        $result = $this->useTargetPermissionConnection(function (): array {
            $this->permissionRegistrar->forgetCachedPermissions();

            $validPermissions = LegacyPermission::query()
                ->where('guard_name', 'staff')
                ->pluck('name')
                ->all();

            $translated = 0;

            foreach (DB::connection('osticket2')->table('role')->orderBy('id')->get() as $roleRow) {
                $permissions = json_decode((string) ($roleRow->permissions ?? '[]'), true);
                $granted = [];

                if (is_array($permissions)) {
                    foreach ($permissions as $permission => $value) {
                        if ($value === true && in_array($permission, $validPermissions, true)) {
                            $granted[] = $permission;
                        }
                    }
                }

                /** @var LegacyRole $spatieRole */
                $spatieRole = LegacyRole::query()->updateOrCreate(
                    ['id' => (int) $roleRow->id],
                    [
                        'name' => (string) $roleRow->name,
                        'guard_name' => 'staff',
                        'created_at' => $roleRow->created ?? now(),
                        'updated_at' => $roleRow->updated ?? now(),
                    ],
                );

                $spatieRole->syncPermissions($granted);
                $translated++;
            }

            $this->permissionRegistrar->forgetCachedPermissions();

            return [
                'table' => 'permissions',
                'status' => 'translated',
                'translated_roles' => $translated,
                'duration_seconds' => null,
                'notes' => null,
            ];
        });

        $result['duration_seconds'] = round(microtime(true) - $startedAt, 3);

        return $result;
    }

    public function useTargetPermissionConnection(callable $callback): mixed
    {
        $originalConnection = config('permission.connection');

        config()->set('permission.connection', 'osticket2');

        try {
            return $callback();
        } finally {
            config()->set('permission.connection', $originalConnection);
            $this->permissionRegistrar->forgetCachedPermissions();
        }
    }
}
