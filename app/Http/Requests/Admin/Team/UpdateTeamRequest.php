<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Staff;
use App\Models\Team;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team
            && parent::authorize()
            && ($this->user('staff')?->can('update', $team) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staffTable = sprintf('%s.%s', (new Staff)->getConnectionName(), (new Staff)->getTable());

        return [
            'name' => ['required', 'string', 'max:64'],
            'lead_id' => ['nullable', 'integer', Rule::exists($staffTable, 'staff_id')],
            'notes' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
            'members' => ['array'],
            'members.*' => ['integer', 'distinct', Rule::exists($staffTable, 'staff_id')],
        ];
    }
}
