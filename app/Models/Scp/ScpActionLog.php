<?php

declare(strict_types=1);

namespace App\Models\Scp;

use Illuminate\Database\Eloquent\Model;

class ScpActionLog extends Model
{
    protected $table = 'scp_action_log';

    public $timestamps = false;

    protected $fillable = [
        'staff_id',
        'ticket_id',
        'thread_id',
        'queue_id',
        'action',
        'outcome',
        'http_status',
        'before_state',
        'after_state',
        'lock_id',
        'request_id',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'created_at' => 'datetime',
    ];
}
