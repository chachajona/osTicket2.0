<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;
use App\Migration\PermissionsTranslator;
use Illuminate\Support\Facades\DB;

class RoleMigrator extends AbstractMigrator
{
    public function __construct(
        private readonly PermissionsTranslator $permissionsTranslator,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function migrate(?string $fromTable = null): array
    {
        $results = parent::migrate($fromTable);

        $this->syncSpatieRoles();

        return $results;
    }

    protected function definitions(): array
    {
        return [[
            'name' => 'role',
            'source' => 'role',
            'target' => 'role',
            'primary_key' => 'id',
            'unique_by' => ['id'],
            'mapper' => static fn (array $row): array => [
                'id' => (int) $row['id'],
                'flags' => (int) ($row['flags'] ?? 0),
                'name' => (string) $row['name'],
                'permissions' => (string) ($row['permissions'] ?? '[]'),
                'notes' => $row['notes'],
                'created' => $row['created'] ?? null,
                'updated' => $row['updated'] ?? null,
            ],
        ]];
    }

    private function syncSpatieRoles(): void
    {
        $roles = DB::connection('osticket2')->table('role')->orderBy('id')->get();
        $table = (string) config('permission.table_names.roles', 'roles');

        $payload = $roles->map(static fn (object $role): array => [
            'id' => (int) $role->id,
            'name' => (string) $role->name,
            'guard_name' => 'staff',
            'created_at' => $role->created ?? now(),
            'updated_at' => $role->updated ?? now(),
        ])->all();

        if ($payload === []) {
            return;
        }

        $this->permissionsTranslator->useTargetPermissionConnection(function () use ($table, $payload): void {
            DB::connection('osticket2')->table($table)->upsert(
                $payload,
                ['id'],
                ['name', 'guard_name', 'updated_at'],
            );
        });
    }
}
