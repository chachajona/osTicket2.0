<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Queues;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Services\Scp\Queues\QueueColumnsService;
use App\Services\Scp\Tickets\ActionLogger;
use Illuminate\Http\Request;

final class QueueColumnsController extends Controller
{
    public function __construct(
        private readonly QueueColumnsService $columns,
        private readonly ActionLogger $logger,
    ) {}

    public function update(Request $request, Queue $queue): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'columns' => 'required|array|min:1',
            'columns.*.column_id' => 'required|integer',
            'columns.*.sort' => 'nullable|integer',
            'columns.*.width' => 'nullable|integer|min:50|max:1000',
            'columns.*.heading' => 'nullable|string|max:80',
        ]);

        $staff = $request->user('staff');

        $this->columns->update($staff, $queue, $data['columns']);

        $this->logger->record(
            staff: $staff,
            action: 'queue.columns_changed',
            outcome: 'success',
            httpStatus: 302,
            queueId: $queue->id,
            request: $request,
        );

        return back();
    }
}
