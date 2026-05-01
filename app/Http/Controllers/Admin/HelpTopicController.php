<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HelpTopic\StoreHelpTopicRequest;
use App\Http\Requests\Admin\HelpTopic\UpdateHelpTopicRequest;
use App\Models\DynamicForm;
use App\Models\HelpTopic;
use App\Models\HelpTopicForm;
use App\Models\Staff;
use App\Models\TicketPriority;
use App\Services\Admin\HelpTopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HelpTopicController extends Controller
{
    use ProvidesModelOptions;

    public function __construct(
        private readonly HelpTopicService $helpTopics,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', HelpTopic::class);

        $helpTopics = HelpTopic::query()
            ->with(['department', 'sla'])
            ->orderBy('topic')
            ->paginate(15)
            ->through(fn (HelpTopic $helpTopic): array => [
                'id' => (int) $helpTopic->getKey(),
                'topic' => (string) $helpTopic->topic,
                'department_name' => $helpTopic->department?->name,
                'sla_name' => $helpTopic->sla?->name,
                'isactive' => (bool) ($helpTopic->isactive ?? 0),
            ]);

        return Inertia::render('Admin/HelpTopics/Index', [
            'helpTopics' => $helpTopics,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', HelpTopic::class);

        return Inertia::render('Admin/HelpTopics/Edit', [
            'helpTopic' => null,
            'parentTopicOptions' => $this->parentTopicOptions(),
            'departmentOptions' => $this->departmentOptions(),
            'slaOptions' => $this->slaOptions(),
            'staffOptions' => $this->staffOptions(),
            'teamOptions' => $this->teamOptions(),
            'priorityOptions' => $this->priorityOptions(),
            'formMappings' => [],
        ]);
    }

    public function store(StoreHelpTopicRequest $request): RedirectResponse
    {
        $this->authorize('create', HelpTopic::class);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $helpTopic = $this->helpTopics->create($request->validated(), $actor);

        return redirect()
            ->route('admin.help-topics.edit', $helpTopic)
            ->with('status', 'Help topic created.');
    }

    public function edit(HelpTopic $helpTopic): Response
    {
        $this->authorize('update', $helpTopic);

        $helpTopic->loadMissing(['parent', 'department', 'sla', 'staff', 'team', 'form.fields', 'formAssignments.form.fields']);

        return Inertia::render('Admin/HelpTopics/Edit', [
            'helpTopic' => $this->serializeHelpTopic($helpTopic),
            'parentTopicOptions' => $this->parentTopicOptions($helpTopic),
            'departmentOptions' => $this->departmentOptions(),
            'slaOptions' => $this->slaOptions(),
            'staffOptions' => $this->staffOptions(),
            'teamOptions' => $this->teamOptions(),
            'priorityOptions' => $this->priorityOptions(),
            'formMappings' => $this->formMappings($helpTopic),
        ]);
    }

    public function update(UpdateHelpTopicRequest $request, HelpTopic $helpTopic): RedirectResponse
    {
        $this->authorize('update', $helpTopic);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->helpTopics->update($helpTopic, $request->validated(), $actor);

        return redirect()
            ->route('admin.help-topics.edit', $helpTopic)
            ->with('status', 'Help topic updated.');
    }

    public function destroy(Request $request, HelpTopic $helpTopic): RedirectResponse
    {
        $this->authorize('delete', $helpTopic);

        /** @var Staff $actor */
        $actor = $request->user('staff');
        $this->helpTopics->delete($helpTopic, $actor);

        return redirect()
            ->route('admin.help-topics.index')
            ->with('status', 'Help topic deleted.');
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function parentTopicOptions(?HelpTopic $current = null): array
    {
        $query = HelpTopic::query()->orderBy('topic');

        if ($current !== null) {
            $query->whereKeyNot($current->getKey());
        }

        return $query
            ->get(['topic_id', 'topic'])
            ->map(fn (HelpTopic $helpTopic): array => [
                'id' => (int) $helpTopic->getKey(),
                'name' => (string) $helpTopic->topic,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    private function priorityOptions(): array
    {
        return TicketPriority::query()
            ->orderBy('priority_urgency')
            ->orderBy('priority')
            ->get(['priority_id', 'priority'])
            ->map(fn (TicketPriority $priority): array => [
                'id' => (int) $priority->getKey(),
                'name' => (string) $priority->priority,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHelpTopic(HelpTopic $helpTopic): array
    {
        return [
            'id' => (int) $helpTopic->getKey(),
            'topic' => (string) $helpTopic->topic,
            'topic_pid' => (int) ($helpTopic->topic_pid ?? 0) !== 0 ? (int) $helpTopic->topic_pid : null,
            'parent_topic' => $helpTopic->parent?->topic,
            'dept_id' => $helpTopic->dept_id !== null ? (int) $helpTopic->dept_id : null,
            'department_name' => $helpTopic->department?->name,
            'sla_id' => $helpTopic->sla_id !== null ? (int) $helpTopic->sla_id : null,
            'sla_name' => $helpTopic->sla?->name,
            'staff_id' => $helpTopic->staff_id !== null ? (int) $helpTopic->staff_id : null,
            'staff_name' => $helpTopic->staff?->displayName(),
            'team_id' => $helpTopic->team_id !== null ? (int) $helpTopic->team_id : null,
            'team_name' => $helpTopic->team?->name,
            'priority_id' => $helpTopic->priority_id !== null ? (int) $helpTopic->priority_id : null,
            'ispublic' => (bool) ($helpTopic->ispublic ?? 0),
            'isactive' => (bool) ($helpTopic->isactive ?? 0),
            'noautoresp' => (bool) ($helpTopic->noautoresp ?? 0),
            'notes' => $helpTopic->notes !== '' ? $helpTopic->notes : null,
        ];
    }

    /**
     * @return list<array{id:int,title:string,name:string|null,source:string,field_count:int,fields:list<array{id:int,label:string,name:string,type:string}>}>
     */
    private function formMappings(HelpTopic $helpTopic): array
    {
        $forms = collect();

        if ($helpTopic->form instanceof DynamicForm) {
            $forms->push([
                'id' => (int) $helpTopic->form->getKey(),
                'title' => (string) $helpTopic->form->title,
                'name' => $helpTopic->form->name !== '' ? $helpTopic->form->name : null,
                'source' => 'Default form',
                'fields' => $helpTopic->form->fields,
            ]);
        }

        $helpTopic->formAssignments
            ->filter(fn (HelpTopicForm $assignment): bool => $assignment->form instanceof DynamicForm)
            ->each(function (HelpTopicForm $assignment) use ($forms): void {
                /** @var DynamicForm $form */
                $form = $assignment->form;

                $forms->push([
                    'id' => (int) $form->getKey(),
                    'title' => (string) $form->title,
                    'name' => $form->name !== '' ? $form->name : null,
                    'source' => 'Attached form',
                    'fields' => $form->fields,
                ]);
            });

        return $forms
            ->unique('id')
            ->values()
            ->map(fn (array $form): array => [
                'id' => $form['id'],
                'title' => $form['title'],
                'name' => $form['name'],
                'source' => $form['source'],
                'field_count' => $form['fields']->count(),
                'fields' => $form['fields']
                    ->map(fn ($field): array => [
                        'id' => (int) $field->getKey(),
                        'label' => (string) $field->label,
                        'name' => (string) $field->name,
                        'type' => (string) $field->type,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
