<?php

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Services\Scp\QueueExportService;
use App\Services\Scp\QueueService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class QueueController extends Controller
{
    private const SORTABLE_COLUMNS = ['number', 'created', 'priority', 'from', 'assignee'];

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

        $filters = $this->parseFilters($request);
        $sort = $this->parseSort($request);

        $ticketRows = $this->queues->ticketRows(
            queue: $queue,
            staff: $staff,
            page: max(1, (int) $request->query('page', 1)),
            filters: $filters,
            sort: $sort['by'],
            direction: $sort['dir'],
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
            'filters' => $filters,
            'filterOptions' => $this->queues->filterOptions(),
            'sort' => $sort,
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

    /**
     * @return array{
     *   state: list<string>,
     *   source: list<string>,
     *   priority: list<int>,
     *   created_from: ?string,
     *   created_to: ?string
     * }
     */
    private function parseFilters(Request $request): array
    {
        return [
            'state' => $this->stringList($request->query('state')),
            'source' => $this->stringList($request->query('source')),
            'priority' => $this->intList($request->query('priority')),
            'created_from' => $this->parseDate($request->query('created_from')),
            'created_to' => $this->parseDate($request->query('created_to')),
        ];
    }

    /**
     * @return array{by:string, dir:string}
     */
    private function parseSort(Request $request): array
    {
        $by = (string) $request->query('sort', 'created');
        $dir = strtolower((string) $request->query('dir', 'desc'));

        return [
            'by' => in_array($by, self::SORTABLE_COLUMNS, true) ? $by : 'created',
            'dir' => $dir === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            fn (mixed $item): string => trim((string) $item),
            $items,
        ), fn (string $item): bool => $item !== ''));
    }

    /**
     * @return list<int>
     */
    private function intList(mixed $value): array
    {
        return array_values(array_filter(array_map(
            fn (string $item): int => (int) $item,
            $this->stringList($value),
        ), fn (int $item): bool => $item > 0));
    }

    private function parseDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
