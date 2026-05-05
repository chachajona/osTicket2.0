<?php

declare(strict_types=1);

namespace App\Services\Scp\Queues;

use App\Models\Queue;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

final class QueueConfigService
{
    public function update(Staff $staff, Queue $queue, array $config): void
    {
        DB::connection('legacy')->table('queue_config')->updateOrInsert(
            [
                'queue_id' => $queue->id,
                'staff_id' => $staff->staff_id,
            ],
            [
                'setting' => json_encode($config),
            ]
        );
    }
}
