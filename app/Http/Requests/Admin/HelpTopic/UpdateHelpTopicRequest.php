<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\HelpTopic;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\Department;
use App\Models\HelpTopic;
use App\Models\Sla;
use App\Models\Staff;
use App\Models\Team;
use Illuminate\Validation\Rule;

class UpdateHelpTopicRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        $helpTopic = $this->route('help_topic');

        return $helpTopic instanceof HelpTopic
            && parent::authorize()
            && ($this->user('staff')?->can('update', $helpTopic) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var HelpTopic $helpTopic */
        $helpTopic = $this->route('help_topic');

        $helpTopicsTable = sprintf('%s.%s', (new HelpTopic)->getConnectionName(), (new HelpTopic)->getTable());
        $departmentsTable = sprintf('%s.%s', (new Department)->getConnectionName(), (new Department)->getTable());
        $slasTable = sprintf('%s.%s', (new Sla)->getConnectionName(), (new Sla)->getTable());
        $staffTable = sprintf('%s.%s', (new Staff)->getConnectionName(), (new Staff)->getTable());
        $teamsTable = sprintf('%s.%s', (new Team)->getConnectionName(), (new Team)->getTable());

        return [
            'topic' => ['required', 'string', 'max:128'],
            'topic_pid' => [
                'nullable',
                'integer',
                Rule::exists($helpTopicsTable, 'topic_id'),
                Rule::notIn([(int) $helpTopic->getKey()]),
            ],
            'dept_id' => ['nullable', 'integer', Rule::exists($departmentsTable, 'id')],
            'sla_id' => ['nullable', 'integer', Rule::exists($slasTable, 'id')],
            'staff_id' => ['nullable', 'integer', Rule::exists($staffTable, 'staff_id')],
            'team_id' => ['nullable', 'integer', Rule::exists($teamsTable, 'team_id')],
            'priority_id' => ['nullable', 'integer'],
            'ispublic' => ['required', 'boolean'],
            'isactive' => ['required', 'boolean'],
            'noautoresp' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
