<?php

namespace App\Services\Scp;

use App\Models\Search;
use App\Models\Ticket;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SearchService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function tickets(string $query, int $limit = 25): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        try {
            $searchRows = $this->searchRows($query, $limit * 3);
            $ids = collect($searchRows)->pluck('object_id')->map(fn ($id): int => (int) $id)->unique()->values();

            if ($ids->isEmpty()) {
                return [];
            }

            $tickets = Ticket::query()
                ->with(['cdata', 'user.defaultEmail'])
                ->whereIn('ticket_id', $ids->all())
                ->get()
                ->keyBy('ticket_id');

            return collect($searchRows)
                ->filter(fn ($row): bool => $tickets->has((int) $row->object_id))
                ->take($limit)
                ->map(function ($row) use ($tickets): array {
                    /** @var Ticket $ticket */
                    $ticket = $tickets->get((int) $row->object_id);

                    return [
                        'id' => (int) $ticket->ticket_id,
                        'number' => (string) $ticket->number,
                        'title' => $row->title ?: $ticket->cdata?->subject,
                        'subject' => $ticket->cdata?->subject,
                        'requester' => $ticket->user?->name ?: $ticket->user?->defaultEmail?->address,
                        'score' => (float) ($row->score ?? 0),
                        'created' => $ticket->created,
                    ];
                })
                ->values()
                ->all();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return Collection<int, object>
     */
    private function searchRows(string $query, int $limit)
    {
        if (DB::connection('legacy')->getDriverName() === 'mysql') {
            try {
                return DB::connection('legacy')
                    ->table('_search')
                    ->selectRaw('object_type, object_id, title, MATCH(title, content) AGAINST(? IN BOOLEAN MODE) AS score', [$query])
                    ->where('object_type', 'T')
                    ->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$query])
                    ->orderByDesc('score')
                    ->orderByDesc('object_id')
                    ->limit($limit)
                    ->get();
            } catch (QueryException) {
                return $this->fallbackSearchRows($query, $limit);
            }
        }

        return $this->fallbackSearchRows($query, $limit);
    }

    /**
     * @return Collection<int, object>
     */
    private function fallbackSearchRows(string $query, int $limit)
    {
        return Search::query()
            ->where('object_type', 'T')
            ->where(function ($builder) use ($query): void {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $query);
                $pattern = "%{$escaped}%";

                $builder->whereRaw('title like ? escape ?', [$pattern, '\\'])
                    ->orWhereRaw('content like ? escape ?', [$pattern, '\\']);
            })
            ->orderByDesc('object_id')
            ->limit($limit)
            ->get()
            ->map(function (Search $search): object {
                return (object) [
                    'object_type' => $search->object_type,
                    'object_id' => $search->object_id,
                    'title' => $search->title,
                    'score' => 1,
                ];
            });
    }
}
