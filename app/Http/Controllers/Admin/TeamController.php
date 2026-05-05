<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Team\StoreTeamRequest;
use App\Http\Requests\Admin\Team\UpdateTeamRequest;
use App\Models\Staff;
use App\Models\Team;
use App\Services\Admin\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    use ProvidesModelOptions;

    public function __construct(
        private readonly TeamService $teams,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Team::class);

        $teams = Team::query()
            ->with('lead')
            ->withCount('members')
            ->orderBy('name')
            ->paginate(15)
            ->through(fn (Team $team): array => [
                'id' => (int) $team->getKey(),
                'name' => (string) $team->name,
                'lead_name' => $team->lead?->displayName(),
                'member_count' => (int) $team->members_count,
                'status' => (bool) ($team->flags ?? 0),
            ]);

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Team::class);

        return Inertia::render('Admin/Teams/Edit', [
            'team' => null,
            'staffOptions' => $this->staffOptions(),
        ]);
    }

    public function store(StoreTeamRequest $request): RedirectResponse
    {
        $this->authorize('create', Team::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $team = $this->teams->create($request->validated(), $actor);

        return redirect()
            ->route('admin.teams.edit', $team)
            ->with('status', 'Team created.');
    }

    public function edit(Team $team): Response
    {
        $this->authorize('update', $team);

        $team->loadMissing('members');

        return Inertia::render('Admin/Teams/Edit', [
            'team' => [
                'id' => (int) $team->getKey(),
                'name' => (string) $team->name,
                'lead_id' => $team->lead_id !== null ? (int) $team->lead_id : null,
                'notes' => $team->notes !== '' ? $team->notes : null,
                'status' => (bool) ($team->flags ?? 0),
                'member_ids' => $team->members
                    ->pluck('staff_id')
                    ->map(static fn (mixed $staffId): int => (int) $staffId)
                    ->sort()
                    ->values()
                    ->all(),
            ],
            'staffOptions' => $this->staffOptions(),
        ]);
    }

    public function update(UpdateTeamRequest $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->teams->update($team, $request->validated(), $actor);

        return redirect()
            ->route('admin.teams.edit', $team)
            ->with('status', 'Team updated.');
    }

    public function destroy(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->teams->delete($team, $actor);

        return redirect()
            ->route('admin.teams.index')
            ->with('status', 'Team deleted.');
    }
}
