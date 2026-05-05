<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\CannedResponse;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\CannedResponse;
use App\Models\Department;
use Illuminate\Validation\Rule;

class StoreCannedResponseRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user('staff')?->can('create', CannedResponse::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $departmentsTable = sprintf('%s.%s', (new Department)->getConnectionName(), (new Department)->getTable());

        return [
            'title' => ['required', 'string', 'max:255'],
            'response' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:255'],
            'dept_id' => ['nullable', 'integer', Rule::exists($departmentsTable, 'id')],
            'isactive' => ['required', 'boolean'],
        ];
    }
}
