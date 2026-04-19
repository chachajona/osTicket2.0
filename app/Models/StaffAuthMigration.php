<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffAuthMigration extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'staff_auth_migrations';

    protected $guarded = [];

    protected $casts = [
        'migrated_at' => 'immutable_datetime',
        'must_upgrade_after' => 'immutable_datetime',
    ];
}
