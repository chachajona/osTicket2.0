<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Filter\StoreFilterRequest;
use App\Http\Requests\Admin\Filter\UpdateFilterRequest;
use App\Models\Filter;
use App\Models\FilterAction;
use App\Models\FilterRule;
use App\Models\Staff;
use App\Services\Admin\FilterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FilterController extends Controller
{
    public function __construct(
        private readonly FilterService $filters,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Filter::class);

        $filters = Filter::query()
            ->withCount(['rules', 'actions'])
            ->orderBy('execorder')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Filter $filter): array => [
                'id' => (int) $filter->getKey(),
                'name' => (string) $filter->name,
                'exec_order' => (int) ($filter->execorder ?? 0),
                'isactive' => (bool) ($filter->isactive ?? 0),
                'rules_count' => (int) $filter->rules_count,
                'actions_count' => (int) $filter->actions_count,
            ]);

        return Inertia::render('Admin/Filters/Index', [
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Filter::class);

        return Inertia::render('Admin/Filters/Edit', [
            'filter' => null,
        ]);
    }

    public function store(StoreFilterRequest $request): RedirectResponse
    {
        $this->authorize('create', Filter::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $filter = $this->filters->create($request->validated(), $actor);

        return redirect()
            ->route('admin.filters.edit', $filter)
            ->with('status', 'Filter created.');
    }

    public function edit(Filter $filter): Response
    {
        $this->authorize('update', $filter);

        $filter->loadMissing([
            'rules' => fn ($query) => $query->orderBy('id'),
            'actions' => fn ($query) => $query->orderBy('sort')->orderBy('id'),
        ]);

        return Inertia::render('Admin/Filters/Edit', [
            'filter' => $this->serializeFilter($filter),
        ]);
    }

    public function update(UpdateFilterRequest $request, Filter $filter): RedirectResponse
    {
        $this->authorize('update', $filter);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->filters->update($filter, $request->validated(), $actor);

        return redirect()
            ->route('admin.filters.edit', $filter)
            ->with('status', 'Filter updated.');
    }

    public function destroy(Request $request, Filter $filter): RedirectResponse
    {
        $this->authorize('delete', $filter);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->filters->delete($filter, $actor);

        return redirect()
            ->route('admin.filters.index')
            ->with('status', 'Filter deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFilter(Filter $filter): array
    {
        return [
            'id' => (int) $filter->getKey(),
            'name' => (string) $filter->name,
            'exec_order' => (int) ($filter->execorder ?? 0),
            'notes' => $filter->notes !== '' ? $filter->notes : null,
            'isactive' => (bool) ($filter->isactive ?? 0),
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
}
