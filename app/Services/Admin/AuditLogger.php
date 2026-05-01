<?php

namespace App\Services\Admin;

use App\Models\Admin\AdminAuditLog;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use ReflectionClass;

class AuditLogger
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function record(
        Staff $actor,
        string $action,
        Model $subject,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
    ): AdminAuditLog {
        $excluded = $this->excludedFields($subject);

        return AdminAuditLog::create([
            'actor_id' => (int) $actor->getKey(),
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => (int) $subject->getKey(),
            'before' => $before === null ? null : $this->redact($before, $excluded),
            'after' => $after === null ? null : $this->redact($after, $excluded),
            'metadata' => $this->metadata($metadata),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->userAgent(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function excludedFields(Model $subject): array
    {
        $reflection = new ReflectionClass($subject);

        while ($reflection !== false) {
            if ($reflection->hasProperty('auditExcluded')) {
                $defaults = $reflection->getDefaultProperties();
                $excluded = $defaults['auditExcluded'] ?? [];

                return is_array($excluded) ? array_values($excluded) : [];
            }

            $reflection = $reflection->getParentClass();
        }

        return [];
    }

    private function redact(array $payload, array $excluded): array
    {
        foreach ($payload as $key => $value) {
            if (in_array((string) $key, $excluded, true)) {
                $payload[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $payload[$key] = $this->redact($value, $excluded);
            }
        }

        return $payload;
    }

    private function metadata(?array $metadata): ?array
    {
        $requestId = $this->request->headers->get('X-Request-Id')
            ?? $this->request->attributes->get('request_id');

        if (! is_string($requestId) || $requestId === '') {
            return $metadata;
        }

        $payload = $metadata ?? [];
        $payload['request_id'] ??= $requestId;

        return $payload;
    }

    private function userAgent(): ?string
    {
        $userAgent = $this->request->userAgent();

        if (! is_string($userAgent) || $userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 255);
    }
}
