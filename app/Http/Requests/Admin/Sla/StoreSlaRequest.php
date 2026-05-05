<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Sla;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Schedule;
use App\Models\Sla;
use Illuminate\Validation\Rule;

class StoreSlaRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user('staff')?->can('create', Sla::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $slasTable = sprintf('%s.%s', (new Sla)->getConnectionName(), (new Sla)->getTable());
        $schedulesTable = sprintf('%s.%s', (new Schedule)->getConnectionName(), (new Schedule)->getTable());

        return [
            'name' => ['required', 'string', 'max:64', Rule::unique($slasTable, 'name')],
            'grace_period' => ['required', 'integer', 'min:0'],
            'schedule_id' => ['nullable', 'integer', Rule::exists($schedulesTable, 'id')],
            'notes' => ['nullable', 'string', 'max:255'],
            'flags' => ['nullable', 'integer'],
        ];
    }
}
