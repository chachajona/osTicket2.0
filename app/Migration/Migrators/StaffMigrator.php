<?php

declare(strict_types=1);

namespace App\Migration\Migrators;

use App\Migration\AbstractMigrator;
use App\Models\Staff;

/**
 * Migrates staff data from the source osTicket database schema to the target schema.
 */
class StaffMigrator extends AbstractMigrator
{
    /**
     * Get the table definitions for staff migration.
     *
     * @return list<array{name:string,source:string,target:string,primary_key:string,unique_by:list<string>}>
     */
    public function migrate(?string $fromTable = null): array
    {
        $results = parent::migrate($fromTable);
        $this->syncPrimaryRoles();

        return $results;
    }

    /**
     * Define the table migration mapping.
     *
     * @return list<array{name:string,source:string,target:string,primary_key:string,unique_by:list<string>}>
     */
    protected function definitions(): array
    {
        return [
            [
                'name' => 'staff',
                'source' => 'staff',
                'target' => 'staff',
                'primary_key' => 'staff_id',
                'unique_by' => ['staff_id'],
            ],
        ];
    }

    /**
     * Synchronizes primary roles from source staff records to the target model_has_roles table.
     */
    private function syncPrimaryRoles(): void
    {
        $table = (string) config('permission.table_names.model_has_roles', 'model_has_roles');

        $this->targetConnection()->table($table)
            ->where('model_type', Staff::class)
            ->delete();

        $payload = $this->targetConnection()->table('staff')
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
            $this->targetConnection()->table($table)->insert($payload);
        }
    }
}
