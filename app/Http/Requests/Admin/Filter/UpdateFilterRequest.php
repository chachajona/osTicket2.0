<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Filter;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Filter;

class UpdateFilterRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $filter = $this->route('filter');

        return $filter instanceof Filter
            && parent::authorize()
            && ($this->user('staff')?->can('update', $filter) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:64'],
            'exec_order' => ['required', 'integer'],
            'isactive' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'rules' => ['array'],
            'rules.*.what' => ['required', 'string'],
            'rules.*.how' => ['required', 'string'],
            'rules.*.val' => ['required', 'string'],
            'rules.*.isactive' => ['required', 'boolean'],
            'actions' => ['array'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.sort' => ['required', 'integer'],
            'actions.*.target' => ['required', 'string'],
        ];
    }
}
