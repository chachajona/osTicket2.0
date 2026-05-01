<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class StaffMigrator extends AbstractMigrator
{
    /**
     * @return list<array<string, mixed>>
     */
    public function migrate(?string $fromTable = null): array
    {
        $results = parent::migrate($fromTable);
        $this->syncPrimaryRoles();

        return $results;
    }

    protected function definitions(): array
    {
        return [[
            'name' => 'staff',
            'source' => 'staff',
            'target' => 'staff',
            'primary_key' => 'staff_id',
            'unique_by' => ['staff_id'],
        ]];
    }

    private function syncPrimaryRoles(): void
    {
        $table = (string) config('permission.table_names.model_has_roles', 'model_has_roles');

        DB::connection('osticket2')->table($table)
            ->where('model_type', Staff::class)
            ->delete();

        $payload = DB::connection('osticket2')->table('staff')
            ->whereNotNull('role_id')
            ->where('role_id', '>', 0)
            ->orderBy('staff_id')
            ->get()
            ->map(static fn (object $staff): array => [
                'role_id' => (int) $staff->role_id,
                'model_type' => Staff::class,
                'model_id' => (int) $staff->staff_id,
            ])
            ->all();

        if ($payload !== []) {
            DB::connection('osticket2')->table($table)->insert($payload);
        }
    }
}
