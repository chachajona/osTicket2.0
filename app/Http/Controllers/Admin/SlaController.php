<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sla\StoreSlaRequest;
use App\Http\Requests\Admin\Sla\UpdateSlaRequest;
use App\Models\Sla;
use App\Models\Staff;
use App\Services\Admin\SlaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SlaController extends Controller
{
    public function __construct(
        private readonly SlaService $slas,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Sla::class);

        $slas = Sla::query()
            ->with('schedule')
            ->orderBy('name')
            ->paginate(15)
            ->through(function (Sla $sla): array {
                return [
                    'id' => (int) $sla->getKey(),
                    'name' => (string) $sla->name,
                    'grace_period' => (int) $sla->grace_period,
                    'schedule' => $sla->schedule?->name,
                    'schedule_id' => $sla->schedule_id !== null ? (int) $sla->schedule_id : null,
                    'notes' => $sla->notes !== '' ? $sla->notes : null,
                    'flags' => (int) ($sla->flags ?? 0),
                ];
            });

        return Inertia::render('Admin/Slas/Index', [
            'slas' => $slas,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Sla::class);

        return Inertia::render('Admin/Slas/Edit', [
            'sla' => null,
        ]);
    }

    public function store(StoreSlaRequest $request): RedirectResponse
    {
        $this->authorize('create', Sla::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $sla = $this->slas->create($request->validated(), $actor);

        return redirect()
            ->route('admin.slas.edit', $sla)
            ->with('status', 'SLA plan created.');
    }

    public function edit(Sla $sla): Response
    {
        $this->authorize('update', $sla);

        $sla->load('schedule');

        return Inertia::render('Admin/Slas/Edit', [
            'sla' => [
                'id' => (int) $sla->getKey(),
                'name' => (string) $sla->name,
                'grace_period' => (int) $sla->grace_period,
                'schedule_id' => $sla->schedule_id !== null ? (int) $sla->schedule_id : null,
                'schedule' => $sla->schedule?->name,
                'notes' => $sla->notes !== '' ? $sla->notes : null,
                'flags' => (int) ($sla->flags ?? 0),
            ],
        ]);
    }

    public function update(UpdateSlaRequest $request, Sla $sla): RedirectResponse
    {
        $this->authorize('update', $sla);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->slas->update($sla, $request->validated(), $actor);

        return redirect()
            ->route('admin.slas.edit', $sla)
            ->with('status', 'SLA plan updated.');
    }

    public function destroy(Request $request, Sla $sla): RedirectResponse
    {
        $this->authorize('delete', $sla);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->slas->delete($sla, $actor);

        return redirect()
            ->route('admin.slas.index')
            ->with('status', 'SLA plan deleted.');
    }
}
