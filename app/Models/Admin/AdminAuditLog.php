<?php

namespace App\Models\Admin;

use DateTime;
use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'admin_audit_log';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    public static function pruneBefore(DateTime $date): int
    {
        return static::query()
            ->where('created_at', '<', $date->format('Y-m-d H:i:s'))
            ->delete();
    }
}
