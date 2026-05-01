<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Sla;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class SlaService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Staff $actor): Sla
    {
        /** @var Sla $sla */
        $sla = DB::connection('legacy')->transaction(function () use ($data): Sla {
            return Sla::query()->create([
                'name' => trim((string) $data['name']),
                'grace_period' => (int) $data['grace_period'],
                'schedule_id' => $this->normalizeScheduleId($data['schedule_id'] ?? null),
                'notes' => $this->normalizeNotes($data['notes'] ?? null),
                'flags' => $this->normalizeFlags($data['flags'] ?? null),
                'created' => now(),
                'updated' => now(),
            ]);
        });

        $this->auditLogger->record($actor, 'sla.create', $sla, before: null, after: $this->payload($sla));

        return $sla->fresh(['schedule']) ?? $sla;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Sla $sla, array $data, Staff $actor): Sla
    {
        $sla->loadMissing('schedule');
        $before = $this->payload($sla);

        DB::connection('legacy')->transaction(function () use ($sla, $data): void {
            $sla->forceFill([
                'name' => trim((string) $data['name']),
                'grace_period' => (int) $data['grace_period'],
                'schedule_id' => $this->normalizeScheduleId($data['schedule_id'] ?? null),
                'notes' => $this->normalizeNotes($data['notes'] ?? null),
                'flags' => $this->normalizeFlags($data['flags'] ?? null),
                'updated' => now(),
            ])->save();
        });

        $sla->refresh()->load('schedule');
        $this->auditLogger->record($actor, 'sla.update', $sla, before: $before, after: $this->payload($sla));

        return $sla;
    }

    public function delete(Sla $sla, Staff $actor): void
    {
        $sla->loadMissing('schedule');
        $before = $this->payload($sla);

        DB::connection('legacy')->transaction(function () use ($sla): void {
            $sla->delete();
        });

        $this->auditLogger->record($actor, 'sla.delete', $sla, before: $before, after: null);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Sla $sla): array
    {
        return [
            'id' => (int) $sla->getKey(),
            'name' => (string) $sla->name,
            'grace_period' => (int) $sla->grace_period,
            'schedule_id' => $sla->schedule_id !== null ? (int) $sla->schedule_id : null,
            'schedule' => $sla->schedule?->name,
            'notes' => $sla->notes !== '' ? $sla->notes : null,
            'flags' => (int) ($sla->flags ?? 0),
        ];
    }

    private function normalizeScheduleId(mixed $scheduleId): ?int
    {
        if ($scheduleId === null || $scheduleId === '') {
            return null;
        }

        return (int) $scheduleId;
    }

    private function normalizeNotes(mixed $notes): string
    {
        return trim((string) ($notes ?? ''));
    }

    private function normalizeFlags(mixed $flags): int
    {
        return (int) ($flags ?? 0);
    }
}
