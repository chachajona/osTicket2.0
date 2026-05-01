<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\HelpTopic;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpTopicService
{
    use NormalizesInput;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Staff $actor): HelpTopic
    {
        /** @var HelpTopic $helpTopic */
        $helpTopic = DB::connection('legacy')->transaction(function () use ($data): HelpTopic {
            return HelpTopic::query()->create($this->payload($data));
        });

        $helpTopic->load(['parent', 'department', 'sla', 'staff', 'team']);
        $this->auditLogger->record($actor, 'help_topic.create', $helpTopic, before: null, after: $this->snapshot($helpTopic));

        return $helpTopic->fresh(['parent', 'department', 'sla', 'staff', 'team']) ?? $helpTopic;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(HelpTopic $helpTopic, array $data, Staff $actor): HelpTopic
    {
        $helpTopic->loadMissing(['parent', 'department', 'sla', 'staff', 'team']);
        $before = $this->snapshot($helpTopic);

        DB::connection('legacy')->transaction(function () use ($helpTopic, $data): void {
            $helpTopic->forceFill($this->payload($data, isUpdate: true))->save();
        });

        $helpTopic->refresh()->load(['parent', 'department', 'sla', 'staff', 'team']);
        $this->auditLogger->record($actor, 'help_topic.update', $helpTopic, before: $before, after: $this->snapshot($helpTopic));

        return $helpTopic;
    }

    public function delete(HelpTopic $helpTopic, Staff $actor): void
    {
        $helpTopic->loadMissing(['parent', 'department', 'sla', 'staff', 'team']);
        $before = $this->snapshot($helpTopic);

        DB::connection('legacy')->transaction(function () use ($helpTopic): void {
            $helpTopic->delete();
        });

        $this->auditLogger->record($actor, 'help_topic.delete', $helpTopic, before: $before, after: null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, bool $isUpdate = false): array
    {
        $payload = [
            'topic' => trim((string) $data['topic']),
            'topic_pid' => $this->normalizeParentId($data['topic_pid'] ?? null),
            'dept_id' => $this->normalizeNullableInt($data['dept_id'] ?? null),
            'sla_id' => $this->normalizeNullableInt($data['sla_id'] ?? null),
            'staff_id' => $this->normalizeNullableInt($data['staff_id'] ?? null),
            'team_id' => $this->normalizeNullableInt($data['team_id'] ?? null),
            'priority_id' => $this->normalizeNullableInt($data['priority_id'] ?? null),
            'ispublic' => $this->normalizeBool($data['ispublic'] ?? false),
            'isactive' => $this->normalizeBool($data['isactive'] ?? true),
            'noautoresp' => $this->normalizeBool($data['noautoresp'] ?? false),
            'notes' => $this->normalizeString($data['notes'] ?? null),
            'updated' => now(),
        ];

        if (! $isUpdate) {
            $payload['created'] = now();
        }

        if ($this->hasHelpTopicColumn('disabled')) {
            $payload['disabled'] = $payload['isactive'] === 1 ? 0 : 1;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(HelpTopic $helpTopic): array
    {
        return [
            'id' => (int) $helpTopic->getKey(),
            'topic' => (string) $helpTopic->topic,
            'topic_pid' => $this->serializeNullableInt($helpTopic->topic_pid),
            'parent_topic' => $helpTopic->parent?->topic,
            'dept_id' => $this->serializeNullableInt($helpTopic->dept_id),
            'department_name' => $helpTopic->department?->name,
            'sla_id' => $this->serializeNullableInt($helpTopic->sla_id),
            'sla_name' => $helpTopic->sla?->name,
            'staff_id' => $this->serializeNullableInt($helpTopic->staff_id),
            'staff_name' => $helpTopic->staff?->displayName(),
            'team_id' => $this->serializeNullableInt($helpTopic->team_id),
            'team_name' => $helpTopic->team?->name,
            'priority_id' => $this->serializeNullableInt($helpTopic->priority_id),
            'ispublic' => (bool) ($helpTopic->ispublic ?? 0),
            'isactive' => (bool) ($helpTopic->isactive ?? 0),
            'noautoresp' => (bool) ($helpTopic->noautoresp ?? 0),
            'notes' => $helpTopic->notes !== '' ? $helpTopic->notes : null,
        ];
    }

    private function normalizeParentId(mixed $value): int
    {
        return $value === null || $value === '' ? 0 : (int) $value;
    }

    private function serializeNullableInt(mixed $value): ?int
    {
        return $value === null || (int) $value === 0 ? null : (int) $value;
    }

    private function hasHelpTopicColumn(string $column): bool
    {
        return Schema::connection('legacy')->hasColumn((new HelpTopic)->getTable(), $column);
    }
}
