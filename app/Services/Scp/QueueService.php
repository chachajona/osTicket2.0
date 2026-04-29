<?php

namespace App\Services\Scp;

use App\Models\Queue;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketPriority;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class QueueService
{
    private const FLAG_PUBLIC = 0x0001;

    private const FLAG_QUEUE = 0x0002;

    private const FLAG_DISABLED = 0x0004;

    public function __construct(private readonly LegacyQueueCriteriaParser $criteriaParser) {}

    /**
     * @return array{
     *   queues: array<int, array<string, mixed>>,
     *   personal: array<int, array<string, mixed>>,
     *   savedSearches: array<int, array{id:int,title:string}>
     * }
     */
    public function visibleQueues(int $staffId): array
    {
        try {
            return [
                'queues' => $this->fetchTree(fn (Builder $query) => $query
                    ->where('staff_id', 0)
                    ->whereRaw('(flags & ?) = ?', [self::FLAG_QUEUE, self::FLAG_QUEUE])
                    ->whereRaw('(flags & ?) = ?', [self::FLAG_PUBLIC, self::FLAG_PUBLIC])
                    ->whereRaw('(flags & ?) = 0', [self::FLAG_DISABLED])),
                'personal' => $this->fetchTree(fn (Builder $query) => $query
                    ->where('staff_id', $staffId)
                    ->whereRaw('(flags & ?) = ?', [self::FLAG_QUEUE, self::FLAG_QUEUE])
                    ->whereRaw('(flags & ?) = 0', [self::FLAG_DISABLED])),
                'savedSearches' => Queue::query()
                    ->where('staff_id', $staffId)
                    ->whereRaw('(flags & ?) = 0', [self::FLAG_QUEUE])
                    ->whereRaw('(flags & ?) = 0', [self::FLAG_DISABLED])
                    ->orderBy('title')
                    ->get(['id', 'title'])
                    ->map(fn (Queue $queue): array => [
                        'id' => (int) $queue->id,
                        'title' => (string) $queue->title,
                    ])
                    ->all(),
            ];
        } catch (QueryException) {
            return ['queues' => [], 'personal' => [], 'savedSearches' => []];
        }
    }

    public function defaultQueueId(int $staffId): ?int
    {
        try {
            $firstPublic = Queue::query()
                ->where('staff_id', 0)
                ->whereRaw('(flags & ?) = ?', [self::FLAG_QUEUE, self::FLAG_QUEUE])
                ->whereRaw('(flags & ?) = ?', [self::FLAG_PUBLIC, self::FLAG_PUBLIC])
                ->whereRaw('(flags & ?) = 0', [self::FLAG_DISABLED])
                ->orderByRaw('COALESCE(parent_id, 0)')
                ->orderBy('sort')
                ->orderBy('title')
                ->value('id');

            if ($firstPublic !== null) {
                return (int) $firstPublic;
            }

            $firstPersonal = Queue::query()
                ->where('staff_id', $staffId)
                ->whereRaw('(flags & ?) = ?', [self::FLAG_QUEUE, self::FLAG_QUEUE])
                ->whereRaw('(flags & ?) = 0', [self::FLAG_DISABLED])
                ->orderBy('sort')
                ->orderBy('title')
                ->value('id');

            if ($firstPersonal !== null) {
                return (int) $firstPersonal;
            }

            $firstSavedSearch = Queue::query()
                ->where('staff_id', $staffId)
                ->whereRaw('(flags & ?) = 0', [self::FLAG_QUEUE])
                ->whereRaw('(flags & ?) = 0', [self::FLAG_DISABLED])
                ->orderBy('title')
                ->value('id');

            return $firstSavedSearch !== null ? (int) $firstSavedSearch : null;
        } catch (QueryException) {
            return null;
        }
    }

    public function canViewQueue(Queue $queue, int $staffId): bool
    {
        $flags = (int) $queue->flags;

        if (($flags & self::FLAG_DISABLED) === self::FLAG_DISABLED) {
            return false;
        }

        if ((int) $queue->staff_id === $staffId) {
            return true;
        }

        return (int) $queue->staff_id === 0
            && ($flags & self::FLAG_PUBLIC) === self::FLAG_PUBLIC;
    }

    /**
     * @return array{
     *   tickets: array<int, array{id:int,number:string,created:?string,subject:?string,from:?string,priority:?string,assignee:?string}>,
     *   pagination: array{page:int,perPage:int,total:int},
     *   unsupported: bool,
     *   unsupportedReasons: list<string>
     * }
     */
    public function ticketRows(Queue $queue, Staff $staff, int $page): array
    {
        $perPage = $this->perPage($staff);
        $query = Ticket::query()
            ->with(['cdata', 'staff', 'user.defaultEmail'])
            ->orderByDesc('created')
            ->orderByDesc('ticket_id');

        $unsupportedReasons = $this->criteriaParser->apply($query, $queue->config, $staff);

        $paginator = $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'page',
            page: $page,
        );

        return [
            'tickets' => $this->mapRows($paginator),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'unsupported' => $unsupportedReasons !== [],
            'unsupportedReasons' => $unsupportedReasons,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTree(Closure $constraint): array
    {
        $items = Queue::query()
            ->tap($constraint)
            ->orderByRaw('COALESCE(parent_id, 0)')
            ->orderBy('sort')
            ->orderBy('title')
            ->get(['id', 'parent_id', 'title']);

        $byParent = $items->groupBy(fn (Queue $queue): int => (int) ($queue->parent_id ?? 0));

        return $this->buildTree($byParent, 0);
    }

    private function perPage(Staff $staff): int
    {
        $pageLimit = (int) ($staff->pagelimit ?? 25);

        if ($pageLimit <= 0) {
            return 25;
        }

        return max(10, min($pageLimit, 100));
    }

    /**
     * @return array<int, array{id:int,number:string,created:?string,subject:?string,from:?string,priority:?string,assignee:?string}>
     */
    private function mapRows(LengthAwarePaginator $paginator): array
    {
        /** @var Collection<int, Ticket> $tickets */
        $tickets = $paginator->getCollection();
        $priorityNames = $this->priorityNames($tickets);

        return $tickets
            ->map(fn (Ticket $ticket): array => [
                'id' => (int) $ticket->ticket_id,
                'number' => (string) $ticket->number,
                'created' => $ticket->created,
                'subject' => $ticket->cdata?->subject,
                'from' => $ticket->user?->name ?: $ticket->user?->defaultEmail?->address,
                'priority' => $priorityNames[(string) $ticket->cdata?->priority] ?? $ticket->cdata?->priority,
                'assignee' => $ticket->staff?->displayName(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return array<string, string>
     */
    private function priorityNames(Collection $tickets): array
    {
        $priorityIds = $tickets
            ->map(fn (Ticket $ticket): mixed => $ticket->cdata?->priority)
            ->filter(fn (mixed $priority): bool => is_numeric($priority))
            ->map(fn (mixed $priority): int => (int) $priority)
            ->unique()
            ->values();

        if ($priorityIds->isEmpty()) {
            return [];
        }

        try {
            return TicketPriority::query()
                ->whereIn('priority_id', $priorityIds->all())
                ->pluck('priority', 'priority_id')
                ->mapWithKeys(fn (string $name, int|string $id): array => [(string) $id => $name])
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @param  Collection<int, Collection<int, Queue>>  $byParent
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $byParent, int $parentId): array
    {
        return ($byParent->get($parentId) ?? collect())
            ->map(fn (Queue $queue): array => [
                'id' => (int) $queue->id,
                'title' => (string) $queue->title,
                'children' => $this->buildTree($byParent, (int) $queue->id),
            ])
            ->values()
            ->all();
    }
}
