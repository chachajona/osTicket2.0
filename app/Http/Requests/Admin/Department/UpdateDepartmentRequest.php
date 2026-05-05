<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Department;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Department;
use App\Models\EmailModel;
use App\Models\EmailTemplateGroup;
use App\Models\Sla;
use App\Models\Staff;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            && parent::authorize()
            && ($this->user('staff')?->can('update', $department) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');

        $departmentsTable = sprintf('%s.%s', (new Department)->getConnectionName(), (new Department)->getTable());
        $staffTable = sprintf('%s.%s', (new Staff)->getConnectionName(), (new Staff)->getTable());
        $slasTable = sprintf('%s.%s', (new Sla)->getConnectionName(), (new Sla)->getTable());
        $emailsTable = sprintf('%s.%s', (new EmailModel)->getConnectionName(), (new EmailModel)->getTable());
        $templateGroupsTable = sprintf('%s.%s', (new EmailTemplateGroup)->getConnectionName(), (new EmailTemplateGroup)->getTable());

        return [
            'name' => ['required', 'string', 'max:128'],
            'sla_id' => ['nullable', 'integer', Rule::exists($slasTable, 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists($staffTable, 'staff_id')],
            'email_id' => ['nullable', 'integer', Rule::exists($emailsTable, 'email_id')],
            'template_id' => ['nullable', 'integer', Rule::exists($templateGroupsTable, 'tpl_id')],
            'dept_id' => [
                'nullable',
                'integer',
                Rule::exists($departmentsTable, 'id'),
                Rule::notIn([(int) $department->getKey()]),
            ],
            'ispublic' => ['required', 'boolean'],
            'signature' => ['nullable', 'string'],
        ];
    }
}
