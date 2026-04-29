<?php

namespace App\Services\Scp;

use App\Models\Staff;
use App\Models\TeamMember;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class LegacyQueueCriteriaParser
{
    private const OPERATORS = [
        'exact', 'gt', 'gte', 'lt', 'lte', 'contains', 'in', 'notin', 'isnull', 'notnull', 'between',
        'includes', '!includes', 'greater', 'greater_equal', 'less', 'less_equal', 'period',
    ];

    /**
     * @var array<string, array{column:string, relation?:string}>
     */
    private const FIELD_MAP = [
        'ticket_id' => ['column' => 'ticket.ticket_id'],
        'number' => ['column' => 'ticket.number'],
        'dept_id' => ['column' => 'ticket.dept_id'],
        'staff_id' => ['column' => 'ticket.staff_id'],
        'team_id' => ['column' => 'ticket.team_id'],
        'topic_id' => ['column' => 'ticket.topic_id'],
        'status_id' => ['column' => 'ticket.status_id'],
        'source' => ['column' => 'ticket.source'],
        'isoverdue' => ['column' => 'ticket.isoverdue'],
        'isanswered' => ['column' => 'ticket.isanswered'],
        'closed' => ['column' => 'ticket.closed'],
        'created' => ['column' => 'ticket.created'],
        'updated' => ['column' => 'ticket.updated'],
        'lastupdate' => ['column' => 'ticket.lastupdate'],
        'duedate' => ['column' => 'ticket.duedate'],
        'status__state' => ['relation' => 'status', 'column' => 'state'],
        'status__name' => ['relation' => 'status', 'column' => 'name'],
        'cdata.subject' => ['relation' => 'cdata', 'column' => 'subject'],
        'cdata.priority' => ['relation' => 'cdata', 'column' => 'priority'],
    ];

    /**
     * @return list<string>
     */
    public function apply(Builder $query, ?string $config, ?Staff $staff = null): array
    {
        $criteria = $this->criteriaFromConfig($config);
        $unsupported = [];

        foreach ($criteria as $criterion) {
            try {
                $this->applyCriterion($query, $criterion, $staff);
            } catch (InvalidArgumentException $exception) {
                $unsupported[] = $exception->getMessage();
            }
        }

        return $unsupported;
    }

    /**
     * @return list<array{0:string,1:string,2:mixed}>
     */
    private function criteriaFromConfig(?string $config): array
    {
        if ($config === null || trim($config) === '') {
            return [];
        }

        $decoded = json_decode($config, true);

        if (! is_array($decoded)) {
            return [];
        }

        $rawCriteria = $this->looksLikeCriteriaList($decoded)
            ? $decoded
            : Arr::get($decoded, 'criteria', Arr::get($decoded, 'filter', []));

        if (! is_array($rawCriteria)) {
            return [];
        }

        $criteria = [];

        foreach ($rawCriteria as $item) {
            if (! is_array($item) || count($item) < 2) {
                continue;
            }

            $field = (string) ($item[0] ?? '');
            $operator = (string) ($item[1] ?? 'exact');
            $value = $item[2] ?? null;

            if ($field === '') {
                continue;
            }

            $criteria[] = [$field, $operator, $value];
        }

        return $criteria;
    }

    /**
     * @param  array<mixed>  $decoded
     */
    private function looksLikeCriteriaList(array $decoded): bool
    {
        $first = reset($decoded);

        return is_array($first)
            && isset($first[0], $first[1])
            && is_string($first[0])
            && is_string($first[1]);
    }

    /**
     * @param  array{0:string,1:string,2:mixed}  $criterion
     */
    private function applyCriterion(Builder $query, array $criterion, ?Staff $staff): void
    {
        [$field, $operator, $value] = $criterion;
        [$field, $operator] = $this->normalizeFieldAndOperator($field, $operator);

        if ($field === 'assignee') {
            $this->applyAssigneeCriterion($query, $operator, $value, $staff);

            return;
        }

        if ($field === 'thread_count') {
            $this->applyThreadCountCriterion($query, $operator, $value);

            return;
        }

        $mapping = self::FIELD_MAP[$field] ?? null;

        if ($mapping === null) {
            throw new InvalidArgumentException("Unsupported queue field [{$field}].");
        }

        $callback = function (Builder $builder) use ($mapping, $operator, $value): void {
            $this->applyOperator($builder, $mapping['column'], $operator, $value);
        };

        if (isset($mapping['relation'])) {
            $query->whereHas($mapping['relation'], $callback);

            return;
        }

        $callback($query);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalizeFieldAndOperator(string $field, string $operator): array
    {
        $operator = strtolower($operator ?: 'exact');
        $parts = explode('__', $field);
        $lastPart = end($parts);

        if (in_array($lastPart, self::OPERATORS, true)) {
            array_pop($parts);

            return [implode('__', $parts), $lastPart];
        }

        return [$field, $operator];
    }

    private function applyOperator(Builder $query, string $column, string $operator, mixed $value): void
    {
        $operator = $this->normalizeOperator($operator);

        match ($operator) {
            'exact' => $query->where($column, $value),
            'gt' => $query->where($column, '>', $value),
            'gte' => $query->where($column, '>=', $value),
            'lt' => $query->where($column, '<', $value),
            'lte' => $query->where($column, '<=', $value),
            'contains' => $query->where($column, 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $value).'%'),
            'in' => $query->whereIn($column, $this->optionValues($value)),
            'notin' => $query->whereNotIn($column, $this->optionValues($value)),
            'isnull' => $query->whereNull($column),
            'notnull' => $query->whereNotNull($column),
            'between' => $query->whereBetween($column, $this->betweenValue($value)),
            'period' => $this->applyPeriod($query, $column, (string) $value),
            default => throw new InvalidArgumentException("Unsupported queue operator [{$operator}]."),
        };
    }

    private function normalizeOperator(string $operator): string
    {
        return match (strtolower($operator)) {
            'includes' => 'in',
            '!includes' => 'notin',
            'greater' => 'gt',
            'greater_equal' => 'gte',
            'less' => 'lt',
            'less_equal' => 'lte',
            default => strtolower($operator),
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value) && str_contains($value, ',')) {
            return array_map('trim', explode(',', $value));
        }

        return [$value];
    }

    /**
     * osTicket stores select options as JSON objects keyed by actual values:
     * {"open":"Open"}, {"3":"Department"}. For those criteria, keys are the
     * values the SQL layer should compare against.
     *
     * @return array<int, mixed>
     */
    private function optionValues(mixed $value): array
    {
        if (is_array($value) && ! array_is_list($value)) {
            return array_keys($value);
        }

        return $this->arrayValue($value);
    }

    /**
     * @return array{0:mixed,1:mixed}
     */
    private function betweenValue(mixed $value): array
    {
        $values = $this->arrayValue($value);

        if (count($values) < 2) {
            throw new InvalidArgumentException('Unsupported queue between value.');
        }

        return [$values[0], $values[1]];
    }

    private function applyAssigneeCriterion(Builder $query, string $operator, mixed $value, ?Staff $staff): void
    {
        if (! $staff) {
            throw new InvalidArgumentException('Unsupported queue field [assignee] without staff context.');
        }

        $operator = strtolower($operator);
        $values = $this->optionValues($value);
        $includeMe = in_array('M', $values, true);
        $includeTeams = in_array('T', $values, true);
        $teamIds = $includeTeams ? $this->teamIds((int) $staff->staff_id) : [];

        if ($operator === 'includes') {
            if (! $includeMe && (! $includeTeams || $teamIds === [])) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function (Builder $query) use ($staff, $includeMe, $includeTeams, $teamIds): void {
                if ($includeMe) {
                    $query->orWhere('ticket.staff_id', (int) $staff->staff_id);
                }

                if ($includeTeams && $teamIds !== []) {
                    $query->orWhereIn('ticket.team_id', $teamIds);
                }
            });

            return;
        }

        if ($operator === '!includes') {
            $query->where(function (Builder $query) use ($staff, $includeMe, $includeTeams, $teamIds): void {
                if ($includeMe) {
                    $query->where(function (Builder $query) use ($staff): void {
                        $query->whereNull('ticket.staff_id')
                            ->orWhere('ticket.staff_id', '!=', (int) $staff->staff_id);
                    });
                }

                if ($includeTeams && $teamIds !== []) {
                    $query->where(function (Builder $query) use ($teamIds): void {
                        $query->whereNull('ticket.team_id')
                            ->orWhereNotIn('ticket.team_id', $teamIds);
                    });
                }
            });

            return;
        }

        throw new InvalidArgumentException("Unsupported queue operator [{$operator}] for assignee.");
    }

    private function applyThreadCountCriterion(Builder $query, string $operator, mixed $value): void
    {
        $operator = $this->normalizeOperator($operator);
        $count = (int) $value;

        match ($operator) {
            'gt' => $query->has('thread.entries', '>', $count),
            'gte' => $query->has('thread.entries', '>=', $count),
            'lt' => $query->has('thread.entries', '<', $count),
            'lte' => $query->has('thread.entries', '<=', $count),
            'exact' => $query->has('thread.entries', '=', $count),
            default => throw new InvalidArgumentException("Unsupported queue operator [{$operator}] for thread_count."),
        };
    }

    private function applyPeriod(Builder $query, string $column, string $period): void
    {
        $now = CarbonImmutable::now();

        [$start, $end] = match ($period) {
            'td' => [$now->startOfDay(), $now->endOfDay()],
            'yd' => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay()],
            'tw' => [$now->startOfWeek(), $now->endOfWeek()],
            'tm' => [$now->startOfMonth(), $now->endOfMonth()],
            'tq' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'ty' => [$now->startOfYear(), $now->endOfYear()],
            default => throw new InvalidArgumentException("Unsupported queue period [{$period}]."),
        };

        $query->whereBetween($column, [
            $start->toDateTimeString(),
            $end->toDateTimeString(),
        ]);
    }

    /**
     * @return array<int>
     */
    private function teamIds(int $staffId): array
    {
        try {
            return TeamMember::query()
                ->where('staff_id', $staffId)
                ->pluck('team_id')
                ->map(fn (mixed $teamId): int => (int) $teamId)
                ->all();
        } catch (QueryException) {
            return [];
        }
    }
}
