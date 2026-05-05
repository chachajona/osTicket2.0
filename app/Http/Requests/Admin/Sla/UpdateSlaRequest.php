<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Sla;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Schedule;
use App\Models\Sla;
use Illuminate\Validation\Rule;

class UpdateSlaRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $sla = $this->route('sla');

        return $sla instanceof Sla
            && parent::authorize()
            && ($this->user('staff')?->can('update', $sla) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Sla $sla */
        $sla = $this->route('sla');

        $slasTable = sprintf('%s.%s', (new Sla)->getConnectionName(), (new Sla)->getTable());
        $schedulesTable = sprintf('%s.%s', (new Schedule)->getConnectionName(), (new Schedule)->getTable());

        return [
            'name' => [
                'required',
                'string',
                'max:64',
                Rule::unique($slasTable, 'name')->ignore($sla->getKey(), $sla->getKeyName()),
            ],
            'grace_period' => ['required', 'integer', 'min:0'],
            'schedule_id' => ['nullable', 'integer', Rule::exists($schedulesTable, 'id')],
            'notes' => ['nullable', 'string', 'max:255'],
            'flags' => ['nullable', 'integer'],
        ];
    }
}
