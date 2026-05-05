<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Role;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\LegacyPermission;
use App\Models\Role;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user('staff')?->can('create', Role::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permissionsTable = sprintf('%s.%s', (new LegacyPermission)->getConnectionName(), (new LegacyPermission)->getTable());
        $rolesTable = sprintf('%s.%s', (new Role)->getConnectionName(), (new Role)->getTable());

        return [
            'name' => ['required', 'string', 'max:64', Rule::unique($rolesTable, 'name')],
            'notes' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'distinct', Rule::exists($permissionsTable, 'name')],
        ];
    }
}
