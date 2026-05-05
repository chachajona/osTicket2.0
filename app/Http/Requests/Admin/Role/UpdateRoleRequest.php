<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Role;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\LegacyPermission;
use App\Models\Role;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            && parent::authorize()
            && ($this->user('staff')?->can('update', $role) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        $permissionsTable = sprintf('%s.%s', (new LegacyPermission)->getConnectionName(), (new LegacyPermission)->getTable());
        $rolesTable = sprintf('%s.%s', (new Role)->getConnectionName(), (new Role)->getTable());

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique($rolesTable, 'name')->ignore($role->getKey(), $role->getKeyName()),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::exists($permissionsTable, 'name')],
        ];
    }
}
