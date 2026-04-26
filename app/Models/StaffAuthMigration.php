<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAuthMigration extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'staff_auth_migrations';

    protected $fillable = [
        'staff_id',
        'migrated_at',
        'must_upgrade_after',
        'upgrade_method',
        'dismissed_migration_banner_at',
    ];

    protected $casts = [
        'migrated_at' => 'immutable_datetime',
        'must_upgrade_after' => 'immutable_datetime',
        'dismissed_migration_banner_at' => 'immutable_datetime',
    ];
}
