<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Filter;
use App\Models\FilterAction;
use App\Models\FilterRule;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class FilterService
{
    use NormalizesInput;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Staff $actor): Filter
    {
        $rules = $this->normalizeRules($data['rules'] ?? []);
        $actions = $this->normalizeActions($data['actions'] ?? []);

        /** @var Filter $filter */
        $filter = DB::connection('legacy')->transaction(function () use ($data, $rules, $actions): Filter {
            $filter = Filter::query()->create([
                'name' => trim((string) $data['name']),
                'execorder' => (int) $data['exec_order'],
                'isactive' => ! empty($data['isactive']) ? 1 : 0,
                'notes' => $this->normalizeString($data['notes'] ?? null),
                'created' => now(),
                'updated' => now(),
            ]);

            $this->syncRules($filter, $rules);
            $this->syncActions($filter, $actions);

            return $filter;
        });

        $filter->load([
            'rules' => fn ($query) => $query->orderBy('id'),
            'actions' => fn ($query) => $query->orderBy('sort')->orderBy('id'),
        ]);

        $this->auditLogger->record($actor, 'filter.create', $filter, before: null, after: $this->snapshot($filter));

        return $filter->fresh([
            'rules' => fn ($query) => $query->orderBy('id'),
            'actions' => fn ($query) => $query->orderBy('sort')->orderBy('id'),
        ]) ?? $filter;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Filter $filter, array $data, Staff $actor): Filter
    {
        $filter->load([
            'rules' => fn ($query) => $query->orderBy('id'),
            'actions' => fn ($query) => $query->orderBy('sort')->orderBy('id'),
        ]);

        $before = $this->snapshot($filter);
        $rules = $this->normalizeRules($data['rules'] ?? []);
        $actions = $this->normalizeActions($data['actions'] ?? []);

        DB::connection('legacy')->transaction(function () use ($filter, $data, $rules, $actions): void {
            $filter->forceFill([
                'name' => trim((string) $data['name']),
                'execorder' => (int) $data['exec_order'],
                'isactive' => ! empty($data['isactive']) ? 1 : 0,
                'notes' => $this->normalizeString($data['notes'] ?? null),
                'updated' => now(),
            ])->save();

            $this->syncRules($filter, $rules);
            $this->syncActions($filter, $actions);
        });

        $filter->refresh()->load([
            'rules' => fn ($query) => $query->orderBy('id'),
            'actions' => fn ($query) => $query->orderBy('sort')->orderBy('id'),
        ]);

        $this->auditLogger->record($actor, 'filter.update', $filter, before: $before, after: $this->snapshot($filter));

        return $filter;
    }

    public function delete(Filter $filter, Staff $actor): void
    {
        $filter->load([
            'rules' => fn ($query) => $query->orderBy('id'),
            'actions' => fn ($query) => $query->orderBy('sort')->orderBy('id'),
        ]);

        $before = $this->snapshot($filter);

        DB::connection('legacy')->transaction(function () use ($filter): void {
            FilterRule::query()->where('filter_id', (int) $filter->getKey())->delete();
            FilterAction::query()->where('filter_id', (int) $filter->getKey())->delete();
            $filter->delete();
        });

        $this->auditLogger->record($actor, 'filter.delete', $filter, before: $before, after: null);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Filter $filter): array
    {
        return [
            'id' => (int) $filter->getKey(),
            'name' => (string) $filter->name,
            'exec_order' => (int) ($filter->execorder ?? 0),
            'isactive' => (bool) ($filter->isactive ?? 0),
            'notes' => $filter->notes !== '' ? $filter->notes : null,
            'rules' => $filter->rules
                ->map(fn (FilterRule $rule): array => [
                    'what' => (string) $rule->what,
                    'how' => (string) $rule->how,
                    'val' => (string) $rule->val,
                    'isactive' => (bool) ($rule->isactive ?? 0),
                ])
                ->values()
                ->all(),
            'actions' => $filter->actions
                ->map(fn (FilterAction $action): array => [
                    'type' => (string) $action->type,
                    'sort' => (int) ($action->sort ?? 0),
                    'target' => (string) ($action->target ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, mixed>  $rules
     * @return list<array{what:string,how:string,val:string,isactive:int}>
     */
    private function normalizeRules(array $rules): array
    {
        return array_values(array_map(
            fn (mixed $rule): array => [
                'what' => trim((string) data_get($rule, 'what')),
                'how' => trim((string) data_get($rule, 'how')),
                'val' => trim((string) data_get($rule, 'val')),
                'isactive' => (bool) data_get($rule, 'isactive') ? 1 : 0,
            ],
            $rules,
        ));
    }

    /**
     * @param  array<int, mixed>  $actions
     * @return list<array{type:string,sort:int,target:string}>
     */
    private function normalizeActions(array $actions): array
    {
        return array_values(array_map(
            fn (mixed $action): array => [
                'type' => trim((string) data_get($action, 'type')),
                'sort' => (int) data_get($action, 'sort'),
                'target' => trim((string) data_get($action, 'target')),
            ],
            $actions,
        ));
    }

    /**
     * @param  list<array{what:string,how:string,val:string,isactive:int}>  $rules
     */
    private function syncRules(Filter $filter, array $rules): void
    {
        FilterRule::query()->where('filter_id', (int) $filter->getKey())->delete();

        foreach ($rules as $rule) {
            FilterRule::query()->create([
                'filter_id' => (int) $filter->getKey(),
                'what' => $rule['what'],
                'how' => $rule['how'],
                'val' => $rule['val'],
                'isactive' => $rule['isactive'],
                'created' => now(),
                'updated' => now(),
            ]);
        }
    }

    /**
     * @param  list<array{type:string,sort:int,target:string}>  $actions
     */
    private function syncActions(Filter $filter, array $actions): void
    {
        FilterAction::query()->where('filter_id', (int) $filter->getKey())->delete();

        foreach ($actions as $action) {
            FilterAction::query()->create([
                'filter_id' => (int) $filter->getKey(),
                'type' => $action['type'],
                'sort' => $action['sort'],
                'target' => $action['target'],
                'updated' => now(),
            ]);
        }
    }
}
