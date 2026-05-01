<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Staff;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Department;
use App\Models\LegacyRole;
use App\Models\Staff;
use App\Models\Team;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof Staff
            && parent::authorize()
            && ($this->user('staff')?->can('update', $staff) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Staff $staff */
        $staff = $this->route('staff');

        $staffTable = sprintf('%s.%s', (new Staff)->getConnectionName(), (new Staff)->getTable());
        $departmentTable = sprintf('%s.%s', (new Department)->getConnectionName(), (new Department)->getTable());
        $roleTable = sprintf('%s.%s', (new LegacyRole)->getConnectionName(), (new LegacyRole)->getTable());
        $teamTable = sprintf('%s.%s', (new Team)->getConnectionName(), (new Team)->getTable());

        return [
            'username' => ['required', 'string', 'max:32', Rule::unique($staffTable, 'username')->ignore($staff->getKey(), 'staff_id')],
            'firstname' => ['required', 'string', 'max:64'],
            'lastname' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:128'],
            'dept_id' => ['required', 'integer', Rule::exists($departmentTable, 'id')],
            'role_id' => ['required', 'integer', Rule::exists($roleTable, 'id')],
            'password' => ['nullable', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'signature' => ['nullable', 'string'],
            'isactive' => ['required', 'boolean'],
            'isadmin' => ['required', 'boolean'],
            'isvisible' => ['required', 'boolean'],
            'change_passwd' => ['nullable', 'boolean'],
            'dept_access' => ['array'],
            'dept_access.*.dept_id' => ['required', 'integer', 'distinct', Rule::exists($departmentTable, 'id')],
            'dept_access.*.role_id' => ['required', 'integer', Rule::exists($roleTable, 'id')],
            'teams' => ['array'],
            'teams.*' => ['integer', 'distinct', Rule::exists($teamTable, 'team_id')],
        ];
    }
}
