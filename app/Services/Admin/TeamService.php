<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Staff;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Support\Facades\DB;

class TeamService
{
    use NormalizesInput;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Staff $actor): Team
    {
        $memberIds = $this->normalizeMemberIds($data['members'] ?? []);

        /** @var Team $team */
        $team = DB::connection('legacy')->transaction(function () use ($data, $memberIds): Team {
            $team = Team::query()->create([
                'lead_id' => $this->normalizeNullableInt($data['lead_id'] ?? null),
                'flags' => $this->normalizeBool($data['status'] ?? true),
                'name' => trim((string) $data['name']),
                'notes' => $this->normalizeString($data['notes'] ?? null),
                'created' => now(),
                'updated' => now(),
            ]);

            $this->syncMembers($team, $memberIds);

            return $team;
        });

        $team->load('members');
        $after = $this->payload($team, $memberIds);
        $this->auditLogger->record($actor, 'team.create', $team, before: null, after: $after);

        return $team->fresh(['lead', 'members']) ?? $team;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Team $team, array $data, Staff $actor): Team
    {
        $beforeMemberIds = $this->memberIdsForTeam($team);
        $before = $this->payload($team, $beforeMemberIds);
        $memberIds = $this->normalizeMemberIds($data['members'] ?? []);

        DB::connection('legacy')->transaction(function () use ($team, $data, $memberIds): void {
            $team->forceFill([
                'lead_id' => $this->normalizeNullableInt($data['lead_id'] ?? null),
                'flags' => $this->normalizeBool($data['status'] ?? true),
                'name' => trim((string) $data['name']),
                'notes' => $this->normalizeString($data['notes'] ?? null),
                'updated' => now(),
            ])->save();

            $this->syncMembers($team, $memberIds);
        });

        $team->refresh()->load('members');
        $after = $this->payload($team, $memberIds);
        $this->auditLogger->record($actor, 'team.update', $team, before: $before, after: $after);

        return $team;
    }

    public function delete(Team $team, Staff $actor): void
    {
        $before = $this->payload($team, $this->memberIdsForTeam($team));

        DB::connection('legacy')->transaction(function () use ($team): void {
            TeamMember::query()->forTeam((int) $team->getKey())->delete();
            $team->delete();
        });

        $this->auditLogger->record($actor, 'team.delete', $team, before: $before, after: null);
    }

    /**
     * @param  list<int>  $memberIds
     * @return array<string, mixed>
     */
    private function payload(Team $team, array $memberIds): array
    {
        return [
            'id' => (int) $team->getKey(),
            'name' => (string) $team->name,
            'lead_id' => $team->lead_id !== null ? (int) $team->lead_id : null,
            'notes' => $team->notes !== '' ? $team->notes : null,
            'status' => (bool) ($team->flags ?? 0),
            'members' => $memberIds,
        ];
    }

    /**
     * @param  array<int, mixed>  $memberIds
     * @return list<int>
     */
    private function normalizeMemberIds(array $memberIds): array
    {
        $normalized = array_map(
            static fn (mixed $staffId): int => (int) $staffId,
            array_filter($memberIds, static fn (mixed $staffId): bool => $staffId !== null && $staffId !== ''),
        );

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @return list<int>
     */
    private function memberIdsForTeam(Team $team): array
    {
        return TeamMember::query()
            ->forTeam((int) $team->getKey())
            ->orderBy('staff_id')
            ->pluck('staff_id')
            ->map(static fn (mixed $staffId): int => (int) $staffId)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $memberIds
     */
    private function syncMembers(Team $team, array $memberIds): void
    {
        $teamId = (int) $team->getKey();
        $query = TeamMember::query()->forTeam($teamId);

        if ($memberIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('staff_id', $memberIds)->delete();

        $existingIds = TeamMember::query()
            ->forTeam($teamId)
            ->pluck('staff_id')
            ->map(static fn (mixed $staffId): int => (int) $staffId)
            ->all();

        foreach (array_diff($memberIds, $existingIds) as $staffId) {
            TeamMember::query()->create([
                'team_id' => $teamId,
                'staff_id' => $staffId,
                'flags' => 0,
            ]);
        }
    }
}
