<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Queues;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Services\Scp\Queues\QueueConfigService;
use App\Services\Scp\Tickets\ActionLogger;
use Illuminate\Http\Request;

final class QueueConfigController extends Controller
{
    public function __construct(
        private readonly QueueConfigService $configs,
        private readonly ActionLogger $logger,
    ) {}

    public function update(Request $request, Queue $queue): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'sort_id' => 'nullable|integer',
            'filter' => 'nullable|string|max:255',
            'criteria_inheritance' => 'nullable|boolean',
            'columns_inheritance' => 'nullable|boolean',
            'sort_inheritance' => 'nullable|boolean',
        ]);

        $staff = $request->user('staff');

        $this->configs->update($staff, $queue, $data);

        $this->logger->record(
            staff: $staff,
            action: 'queue.config_changed',
            outcome: 'success',
            httpStatus: 302,
            queueId: $queue->id,
            request: $request,
        );

        return back();
    }
}
