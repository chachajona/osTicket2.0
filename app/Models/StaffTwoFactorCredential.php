<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffTwoFactorCredential extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'staff_two_factor';

    protected $guarded = [];

    protected $casts = [
        'two_factor_secret' => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_confirmed_at' => 'immutable_datetime',
    ];
}
