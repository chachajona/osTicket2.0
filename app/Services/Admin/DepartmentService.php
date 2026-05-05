<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Department;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    use NormalizesInput;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Staff $actor): Department
    {
        /** @var Department $department */
        $department = DB::connection('legacy')->transaction(function () use ($data): Department {
            return Department::query()->create($this->payload($data));
        });

        $department->load(['manager', 'sla', 'email', 'template', 'parent']);
        $this->auditLogger->record($actor, 'department.create', $department, before: null, after: $this->snapshot($department));

        return $department->fresh(['manager', 'sla', 'email', 'template', 'parent']) ?? $department;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data, Staff $actor): Department
    {
        $department->loadMissing(['manager', 'sla', 'email', 'template', 'parent']);
        $before = $this->snapshot($department);

        DB::connection('legacy')->transaction(function () use ($department, $data): void {
            $department->forceFill($this->payload($data))->save();
        });

        $department->refresh()->load(['manager', 'sla', 'email', 'template', 'parent']);
        $this->auditLogger->record($actor, 'department.update', $department, before: $before, after: $this->snapshot($department));

        return $department;
    }

    public function delete(Department $department, Staff $actor): void
    {
        $department->loadMissing(['manager', 'sla', 'email', 'template', 'parent']);
        $blockingReferences = $this->blockingReferencesFor($department);

        if ($blockingReferences !== []) {
            throw ValidationException::withMessages([
                'department' => sprintf(
                    'Department cannot be deleted because it is referenced by %s.',
                    implode(', ', $blockingReferences),
                ),
            ]);
        }

        $before = $this->snapshot($department);

        DB::connection('legacy')->transaction(function () use ($department): void {
            $department->delete();
        });

        $this->auditLogger->record($actor, 'department.delete', $department, before: $before, after: null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'sla_id' => $this->normalizeNullableInt($data['sla_id'] ?? null),
            'manager_id' => $this->normalizeNullableInt($data['manager_id'] ?? null),
            'email_id' => $this->normalizeNullableInt($data['email_id'] ?? null),
            'tpl_id' => $this->normalizeNullableInt($data['template_id'] ?? null),
            'dept_id' => $this->normalizeNullableInt($data['dept_id'] ?? null),
            'signature' => $this->normalizeNullableString($data['signature'] ?? null),
            'ispublic' => ! empty($data['ispublic']) ? 1 : 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Department $department): array
    {
        return [
            'id' => (int) $department->getKey(),
            'name' => (string) $department->name,
            'sla_id' => $department->sla_id !== null ? (int) $department->sla_id : null,
            'sla_name' => $department->sla?->name,
            'manager_id' => $department->manager_id !== null ? (int) $department->manager_id : null,
            'manager_name' => $department->manager?->displayName(),
            'email_id' => $department->email_id !== null ? (int) $department->email_id : null,
            'email_name' => $department->email?->name,
            'template_id' => $department->tpl_id !== null ? (int) $department->tpl_id : null,
            'template_name' => $department->template?->name,
            'dept_id' => $department->dept_id !== null ? (int) $department->dept_id : null,
            'parent_name' => $department->parent?->name,
            'signature' => filled($department->signature) ? (string) $department->signature : null,
            'ispublic' => (bool) ($department->ispublic ?? 0),
        ];
    }

    /**
     * @return list<string>
     */
    private function blockingReferencesFor(Department $department): array
    {
        $departmentId = (int) $department->getKey();

        $checks = [
            'child departments' => ['department', 'dept_id'],
            'tickets' => ['ticket', 'dept_id'],
            'staff records' => ['staff', 'dept_id'],
            'help topics' => ['help_topic', 'dept_id'],
            'email addresses' => ['email', 'dept_id'],
            'canned responses' => ['canned_response', 'dept_id'],
            'department access rules' => ['staff_dept_access', 'dept_id'],
            'tasks' => ['task', 'dept_id'],
        ];

        $blocking = [];

        foreach ($checks as $label => [$table, $column]) {
            if (! Schema::connection('legacy')->hasTable($table)) {
                continue;
            }

            if (DB::connection('legacy')->table($table)->where($column, $departmentId)->exists()) {
                $blocking[] = $label;
            }
        }

        return $blocking;
    }
}
