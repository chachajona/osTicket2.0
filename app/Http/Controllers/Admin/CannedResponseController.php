<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CannedResponse\StoreCannedResponseRequest;
use App\Http\Requests\Admin\CannedResponse\UpdateCannedResponseRequest;
use App\Models\CannedResponse;
use App\Models\Department;
use App\Models\Staff;
use App\Services\Admin\CannedResponseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CannedResponseController extends Controller
{
    public function __construct(
        private readonly CannedResponseService $cannedResponses,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', CannedResponse::class);

        $cannedResponses = CannedResponse::query()
            ->with('department')
            ->orderBy('title')
            ->paginate(15)
            ->through(fn (CannedResponse $cannedResponse): array => $this->serializeCannedResponse($cannedResponse));

        return Inertia::render('Admin/CannedResponses/Index', [
            'cannedResponses' => $cannedResponses,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', CannedResponse::class);

        return Inertia::render('Admin/CannedResponses/Edit', [
            'cannedResponse' => null,
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function store(StoreCannedResponseRequest $request): RedirectResponse
    {
        $this->authorize('create', CannedResponse::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $cannedResponse = $this->cannedResponses->create($request->validated(), $actor);

        return redirect()
            ->route('admin.canned-responses.edit', $cannedResponse)
            ->with('status', 'Canned response created.');
    }

    public function edit(CannedResponse $cannedResponse): Response
    {
        $this->authorize('update', $cannedResponse);

        $cannedResponse->loadMissing('department');

        return Inertia::render('Admin/CannedResponses/Edit', [
            'cannedResponse' => $this->serializeCannedResponse($cannedResponse),
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function update(UpdateCannedResponseRequest $request, CannedResponse $cannedResponse): RedirectResponse
    {
        $this->authorize('update', $cannedResponse);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->cannedResponses->update($cannedResponse, $request->validated(), $actor);

        return redirect()
            ->route('admin.canned-responses.edit', $cannedResponse)
            ->with('status', 'Canned response updated.');
    }

    public function destroy(Request $request, CannedResponse $cannedResponse): RedirectResponse
    {
        $this->authorize('delete', $cannedResponse);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->cannedResponses->delete($cannedResponse, $actor);

        return redirect()
            ->route('admin.canned-responses.index')
            ->with('status', 'Canned response deleted.');
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function departmentOptions(): array
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $department): array => [
                'id' => (int) $department->getKey(),
                'name' => (string) $department->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCannedResponse(CannedResponse $cannedResponse): array
    {
        return [
            'id' => (int) $cannedResponse->getKey(),
            'title' => (string) $cannedResponse->title,
            'response' => (string) $cannedResponse->response,
            'notes' => $cannedResponse->notes !== '' ? $cannedResponse->notes : null,
            'dept_id' => $cannedResponse->dept_id !== null ? (int) $cannedResponse->dept_id : null,
            'department_name' => $cannedResponse->department?->name,
            'isactive' => (bool) ($cannedResponse->isactive ?? 0),
        ];
    }
}
