<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /** @var class-string<Permission> $permissionModel */
        $permissionModel = config('permission.models.permission', Permission::class);

        foreach (self::permissionGroups() as $permissions) {
            foreach ($permissions as $permission) {
                $permissionModel::query()->firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'staff',
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, list<string>>
     */
    public static function permissionGroups(): array
    {
        return [
            'tickets' => [
                'ticket.create',
                'ticket.edit',
                'ticket.assign',
                'ticket.transfer',
                'ticket.reply',
                'ticket.close',
                'ticket.delete',
                'ticket.release',
                'ticket.markanswered',
            ],
            'tasks' => [
                'task.create',
                'task.edit',
                'task.assign',
                'task.transfer',
                'task.reply',
                'task.close',
                'task.delete',
            ],
            'users' => [
                'user.create',
                'user.edit',
                'user.delete',
                'user.manage',
                'user.dir',
            ],
            'organizations' => [
                'org.create',
                'org.edit',
                'org.delete',
            ],
            'knowledgebase' => [
                'kb.premade',
                'kb.faq',
            ],
            'visibility' => [
                'visibility.agents',
                'visibility.departments',
                'visibility.private',
            ],
            'admin' => [
                'admin.access',
                'admin.role.create',
                'admin.role.update',
                'admin.role.delete',
                'admin.staff.create',
                'admin.staff.update',
                'admin.staff.delete',
                'admin.department.create',
                'admin.department.update',
                'admin.department.delete',
                'admin.team.create',
                'admin.team.update',
                'admin.team.delete',
                'admin.sla.create',
                'admin.sla.update',
                'admin.sla.delete',
                'admin.canned.create',
                'admin.canned.update',
                'admin.canned.delete',
                'admin.helptopic.create',
                'admin.helptopic.update',
                'admin.helptopic.delete',
                'admin.filter.create',
                'admin.filter.update',
                'admin.filter.delete',
                'admin.email.create',
                'admin.email.update',
                'admin.email.delete',
            ],
        ];
    }
}
