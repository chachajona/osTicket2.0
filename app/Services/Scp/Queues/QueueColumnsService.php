<?php

declare(strict_types=1);

namespace App\Services\Scp\Queues;

use App\Models\Queue;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class QueueColumnsService
{
    public function update(Staff $staff, Queue $queue, array $columns): void
    {
        foreach ($columns as $column) {
            $columnId = $column['column_id'];

            $exists = DB::connection('legacy')->table('queue_column')
                ->where('queue_id', $queue->id)
                ->where('id', $columnId)
                ->exists();

            if (!$exists) {
                throw new InvalidArgumentException(
                    "Column {$columnId} does not belong to queue {$queue->id}"
                );
            }

            DB::connection('legacy')->table('queue_columns')->updateOrInsert(
                [
                    'queue_id' => $queue->id,
                    'column_id' => $columnId,
                    'staff_id' => $staff->staff_id,
                ],
                [
                    'sort' => $column['sort'] ?? 0,
                    'width' => $column['width'] ?? 0,
                    'heading' => $column['heading'] ?? '',
                ]
            );
        }
    }
}
