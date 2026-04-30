<?php

namespace App\Models\Scp;

use Illuminate\Database\Eloquent\Model;

class ScpAccessLog extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'access_log';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
