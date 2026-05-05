<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\CannedResponse;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class CannedResponseService
{
    use NormalizesInput;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function create(array $data, Staff $actor): CannedResponse
    {
        /** @var CannedResponse $cannedResponse */
        $cannedResponse = DB::connection('legacy')->transaction(function () use ($data): CannedResponse {
            return CannedResponse::query()->create($this->payload($data));
        });

        $this->auditLogger->record($actor, 'canned_response.create', $cannedResponse, before: null, after: $this->snapshot($cannedResponse));

        return $cannedResponse->fresh(['department']) ?? $cannedResponse;
    }

    public function update(CannedResponse $cannedResponse, array $data, Staff $actor): CannedResponse
    {
        $cannedResponse->loadMissing('department');
        $before = $this->snapshot($cannedResponse);

        DB::connection('legacy')->transaction(function () use ($cannedResponse, $data): void {
            $cannedResponse->forceFill($this->payload($data, isUpdate: true))->save();
        });

        $cannedResponse->refresh()->load('department');
        $this->auditLogger->record($actor, 'canned_response.update', $cannedResponse, before: $before, after: $this->snapshot($cannedResponse));

        return $cannedResponse;
    }

    public function delete(CannedResponse $cannedResponse, Staff $actor): void
    {
        $cannedResponse->loadMissing('department');
        $before = $this->snapshot($cannedResponse);

        DB::connection('legacy')->transaction(function () use ($cannedResponse): void {
            $cannedResponse->delete();
        });

        $this->auditLogger->record($actor, 'canned_response.delete', $cannedResponse, before: $before, after: null);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $data, bool $isUpdate = false): array
    {
        $payload = [
            'title' => trim((string) $data['title']),
            'response' => trim((string) $data['response']),
            'notes' => $this->normalizeNullableString($data['notes'] ?? null),
            'dept_id' => $data['dept_id'] !== null ? (int) $data['dept_id'] : null,
            'isactive' => ! empty($data['isactive']) ? 1 : 0,
            'updated' => now(),
        ];

        if (! $isUpdate) {
            $payload['created'] = now();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(CannedResponse $cannedResponse): array
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
