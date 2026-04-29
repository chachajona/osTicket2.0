<?php

namespace App\Services\Scp;

use App\Models\ThreadEvent;
use App\Models\Staff;
use App\Models\Ticket;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

class DashboardService
{
    /**
     * @return array{
     *     open:int,
     *     assignedToMe:int,
     *     unassigned:int,
     *     overdue:int,
     *     trend:array<string, array{previous:int, change:int, percent:float|null, direction:string}>,
     *     statusComparison:array{
     *         rangeStart:string,
     *         rangeEnd:string,
     *         openTotal:int,
     *         solvedTotal:int,
     *         months:array<int, array{month:string, label:string, open:int, solved:int}>
     *     },
     *     channelDistribution:array{
     *         rangeStart:string,
     *         rangeEnd:string,
     *         total:int,
     *         channels:array<int, array{key:string, label:string, count:int, percent:float}>
     *     },
     *     recentActivity:array<int, array<string, mixed>>,
     *     generatedAt:string
     * }
     */
    public function summary(Staff $staff, string $range = 'last_6_months'): array
    {
        try {
            $staffId = (int) $staff->staff_id;
            $currentCounts = $this->countsFor($this->currentOpenTickets(), $staffId);
            $previousCounts = $this->countsFor(
                $this->previousMonthOpenTickets(now()->subMonthNoOverflow()->endOfMonth()),
                $staffId,
            );

            return [
                ...$currentCounts,
                'trend' => $this->trends($currentCounts, $previousCounts),
                'statusComparison' => $this->statusComparison($range),
                'channelDistribution' => $this->channelDistribution($range),
                'recentActivity' => ThreadEvent::query()
                    ->with(['event', 'thread.ticket.cdata'])
                    ->whereHas('thread', fn ($query) => $query->where('object_type', 'T'))
                    ->whereHas('thread.ticket')
                    ->latest('timestamp')
                    ->limit(10)
                    ->get()
                    ->map(fn (ThreadEvent $event): array => [
                        'id' => $event->id,
                        'thread_id' => $event->thread_id,
                        'event_id' => $event->event_id,
                        'event' => $event->event?->name,
                        'ticket_id' => $event->thread?->ticket?->ticket_id,
                        'ticket_number' => $event->thread?->ticket?->number,
                        'ticket_subject' => $event->thread?->ticket?->cdata?->subject,
                        'username' => $event->username,
                        'timestamp' => $event->timestamp,
                    ])
                    ->all(),
                'generatedAt' => now()->toIso8601String(),
            ];
        } catch (QueryException) {
            return [
                'open' => 0,
                'assignedToMe' => 0,
                'unassigned' => 0,
                'overdue' => 0,
                'trend' => $this->emptyTrends(),
                'statusComparison' => $this->emptyStatusComparison(),
                'channelDistribution' => $this->emptyChannelDistribution(),
                'recentActivity' => [],
                'generatedAt' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * @return Builder<Ticket>
     */
    private function currentOpenTickets(): Builder
    {
        return Ticket::query()
            ->whereHas('status', fn ($query) => $query->where('state', 'open'));
    }

    /**
     * Reconstructs the previous month backlog from legacy created/closed columns.
     *
     * @return Builder<Ticket>
     */
    private function previousMonthOpenTickets(CarbonInterface $asOf): Builder
    {
        $asOfDate = $asOf->toDateTimeString();

        return Ticket::query()
            ->whereNotNull('created')
            ->where('created', '<=', $asOfDate)
            ->where(function (Builder $query) use ($asOfDate): void {
                $query->whereNull('closed')
                    ->orWhere('closed', '>', $asOfDate);
            });
    }

    /**
     * @param Builder<Ticket> $tickets
     *
     * @return array{open:int, assignedToMe:int, unassigned:int, overdue:int}
     */
    private function countsFor(Builder $tickets, int $staffId): array
    {
        return [
            'open' => (clone $tickets)->count(),
            'assignedToMe' => (clone $tickets)
                ->where('staff_id', $staffId)
                ->count(),
            'unassigned' => (clone $tickets)
                ->where('staff_id', 0)
                ->where('team_id', 0)
                ->count(),
            'overdue' => (clone $tickets)
                ->where('isoverdue', 1)
                ->count(),
        ];
    }

    /**
     * @param array{open:int, assignedToMe:int, unassigned:int, overdue:int} $current
     * @param array{open:int, assignedToMe:int, unassigned:int, overdue:int} $previous
     *
     * @return array<string, array{previous:int, change:int, percent:float|null, direction:string}>
     */
    private function trends(array $current, array $previous): array
    {
        return [
            'open' => $this->trend($current['open'], $previous['open']),
            'assignedToMe' => $this->trend($current['assignedToMe'], $previous['assignedToMe']),
            'unassigned' => $this->trend($current['unassigned'], $previous['unassigned']),
            'overdue' => $this->trend($current['overdue'], $previous['overdue']),
        ];
    }

    /**
     * @return array{previous:int, change:int, percent:float|null, direction:string}
     */
    private function trend(int $current, int $previous): array
    {
        $change = $current - $previous;

        if ($previous === 0) {
            return [
                'previous' => 0,
                'change' => $change,
                'percent' => $current === 0 ? 0.0 : null,
                'direction' => $current === 0 ? 'flat' : 'new',
            ];
        }

        return [
            'previous' => $previous,
            'change' => $change,
            'percent' => round(($change / $previous) * 100, 1),
            'direction' => match (true) {
                $change > 0 => 'up',
                $change < 0 => 'down',
                default => 'flat',
            },
        ];
    }

    /**
     * @return array<string, array{previous:int, change:int, percent:float|null, direction:string}>
     */
    private function emptyTrends(): array
    {
        return $this->trends(
            ['open' => 0, 'assignedToMe' => 0, 'unassigned' => 0, 'overdue' => 0],
            ['open' => 0, 'assignedToMe' => 0, 'unassigned' => 0, 'overdue' => 0],
        );
    }

    private function rangeStart(string $range): Carbon
    {
        $today = now();

        return match ($range) {
            'last_7_days'    => $today->copy()->subDays(6)->startOfDay(),
            'last_30_days'   => $today->copy()->subDays(29)->startOfDay(),
            'last_3_months'  => $today->copy()->startOfMonth()->subMonthsNoOverflow(2),
            default          => $today->copy()->startOfMonth()->subMonthsNoOverflow(5),
        };
    }

    /**
     * @return array{
     *     rangeStart:string,
     *     rangeEnd:string,
     *     openTotal:int,
     *     solvedTotal:int,
     *     months:array<int, array{month:string, label:string, open:int, solved:int}>
     * }
     */
    private function statusComparison(string $range = 'last_6_months'): array
    {
        $today = now();
        $rangeStart = $this->rangeStart($range);
        $months = [];

        for ($cursor = $rangeStart->copy()->startOfMonth(); $cursor <= $today; $cursor->addMonthNoOverflow()) {
            $month = $cursor->copy()->startOfMonth();
            $months[$month->toDateString()] = [
                'month' => $month->toDateString(),
                'label' => $month->format('M'),
                'open' => 0,
                'solved' => 0,
            ];
        }

        foreach ($this->openCreatedCountsByMonth($rangeStart, $today) as $month => $count) {
            if (isset($months[$month])) {
                $months[$month]['open'] = $count;
            }
        }

        foreach ($this->solvedCountsByMonth($rangeStart, $today) as $month => $count) {
            if (isset($months[$month])) {
                $months[$month]['solved'] = $count;
            }
        }

        return [
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $today->toDateString(),
            'openTotal' => array_sum(array_column($months, 'open')),
            'solvedTotal' => array_sum(array_column($months, 'solved')),
            'months' => array_values($months),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function openCreatedCountsByMonth(CarbonInterface $start, CarbonInterface $end): array
    {
        $counts = [];

        Ticket::query()
            ->whereHas('status', fn ($query) => $query->where('state', 'open'))
            ->whereNotNull('created')
            ->whereBetween('created', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->pluck('created')
            ->each(function (string $created) use (&$counts): void {
                $month = Carbon::parse($created)->startOfMonth()->toDateString();
                $counts[$month] = ($counts[$month] ?? 0) + 1;
            });

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function solvedCountsByMonth(CarbonInterface $start, CarbonInterface $end): array
    {
        $counts = [];

        Ticket::query()
            ->whereNotNull('closed')
            ->whereBetween('closed', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->pluck('closed')
            ->each(function (string $closed) use (&$counts): void {
                $month = Carbon::parse($closed)->startOfMonth()->toDateString();
                $counts[$month] = ($counts[$month] ?? 0) + 1;
            });

        return $counts;
    }

    /**
     * @return array{
     *     rangeStart:string,
     *     rangeEnd:string,
     *     openTotal:int,
     *     solvedTotal:int,
     *     months:array<int, array{month:string, label:string, open:int, solved:int}>
     * }
     */
    private function emptyStatusComparison(): array
    {
        $today = now();
        $rangeStart = $today->copy()->startOfMonth()->subMonthsNoOverflow(5);
        $months = [];

        for ($cursor = $rangeStart->copy(); $cursor <= $today; $cursor->addMonthNoOverflow()) {
            $month = $cursor->copy()->startOfMonth();
            $months[] = [
                'month' => $month->toDateString(),
                'label' => $month->format('M'),
                'open' => 0,
                'solved' => 0,
            ];
        }

        return [
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $today->copy()->startOfMonth()->toDateString(),
            'openTotal' => 0,
            'solvedTotal' => 0,
            'months' => $months,
        ];
    }

    /**
     * @return array{
     *     rangeStart:string,
     *     rangeEnd:string,
     *     total:int,
     *     channels:array<int, array{key:string, label:string, count:int, percent:float}>
     * }
     */
    private function channelDistribution(string $range = 'last_6_months'): array
    {
        $today = now();
        $rangeStart = $this->rangeStart($range);
        $counts = [];
        $labels = [];

        Ticket::query()
            ->whereNotNull('created')
            ->whereBetween('created', [$rangeStart->toDateTimeString(), $today->toDateTimeString()])
            ->pluck('source')
            ->each(function (?string $source) use (&$counts, &$labels): void {
                $label = $this->channelLabel($source);
                $key = $this->channelKey($label);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $labels[$key] = $label;
            });

        arsort($counts);
        $total = array_sum($counts);

        return [
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $today->toDateString(),
            'total' => $total,
            'channels' => array_map(fn (string $key, int $count): array => [
                'key' => $key,
                'label' => $labels[$key],
                'count' => $count,
                'percent' => $total > 0 ? round(($count / $total) * 100, 1) : 0.0,
            ], array_keys($counts), array_values($counts)),
        ];
    }

    private function channelLabel(?string $source): string
    {
        $label = trim((string) $source);

        if ($label === '') {
            return 'Other';
        }

        $knownLabels = [
            'api' => 'API',
            'ccm' => 'CCM',
            'email' => 'Email',
            'mms' => 'MMS',
            'phone' => 'Phone',
            'sms' => 'SMS',
            'web' => 'Web',
        ];
        $normalized = strtolower($label);

        if (isset($knownLabels[$normalized])) {
            return $knownLabels[$normalized];
        }

        if ($label === $normalized) {
            return collect(explode(' ', $label))
                ->map(fn (string $word): string => ucfirst($word))
                ->implode(' ');
        }

        return $label;
    }

    private function channelKey(string $label): string
    {
        $key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label) ?? '');
        $key = trim($key, '_');

        return $key === '' ? 'other' : $key;
    }

    /**
     * @return array{
     *     rangeStart:string,
     *     rangeEnd:string,
     *     total:int,
     *     channels:array<int, array{key:string, label:string, count:int, percent:float}>
     * }
     */
    private function emptyChannelDistribution(): array
    {
        $today = now();

        return [
            'rangeStart' => $today->copy()->startOfMonth()->subMonthsNoOverflow(5)->toDateString(),
            'rangeEnd' => $today->copy()->startOfMonth()->toDateString(),
            'total' => 0,
            'channels' => [],
        ];
    }
}
