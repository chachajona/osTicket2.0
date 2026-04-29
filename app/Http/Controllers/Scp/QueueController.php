<?php

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Services\Scp\QueueExportService;
use App\Services\Scp\QueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QueueController extends Controller
{
    public function __construct(
        private readonly QueueService $queues,
        private readonly QueueExportService $exports,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        $staffId = $this->staffId($request);
        $defaultId = $this->queues->defaultQueueId($staffId);

        if ($defaultId !== null) {
            return redirect()->route('scp.queues.show', ['queue' => $defaultId]);
        }

        return Inertia::render('Scp/Queues/Index', [
            'navigation' => $this->queues->visibleQueues($staffId),
        ]);
    }

    public function show(Request $request, Queue $queue): Response
    {
        $staff = $request->user('staff');

        abort_unless($this->queues->canViewQueue($queue, (int) $staff->staff_id), 404);

        $ticketRows = $this->queues->ticketRows(
            queue: $queue,
            staff: $staff,
            page: max(1, (int) $request->query('page', 1)),
        );

        return Inertia::render('Scp/Queues/Show', [
            'navigation' => $this->queues->visibleQueues((int) $staff->staff_id),
            'queue' => [
                'id' => (int) $queue->id,
                'title' => (string) $queue->title,
            ],
            'tickets' => $ticketRows['tickets'],
            'pagination' => $ticketRows['pagination'],
            'unsupported' => $ticketRows['unsupported'],
            'unsupportedReasons' => $ticketRows['unsupportedReasons'],
        ]);
    }

    public function export(Request $request, Queue $queue): StreamedResponse
    {
        abort_unless($this->queues->canViewQueue($queue, $this->staffId($request)), 404);

        return $this->exports->stream($queue, $request->user('staff'));
    }

    private function staffId(Request $request): int
    {
        return (int) $request->user('staff')->staff_id;
    }
}
