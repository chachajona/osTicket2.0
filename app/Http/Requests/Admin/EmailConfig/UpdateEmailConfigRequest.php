<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\EmailConfig;

use App\Http\Requests\Admin\AdminFormRequest;
use App\Models\EmailTemplateGroup;
use Illuminate\Validation\Rule;

class UpdateEmailConfigRequest extends AdminFormRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && ($this->user('staff')?->hasPermissionTo('admin.email.update') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $templateGroupTable = sprintf('%s.%s', (new EmailTemplateGroup)->getConnectionName(), (new EmailTemplateGroup)->getTable());

        return [
            'type' => ['required', 'string', Rule::in(['account', 'template', 'group'])],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required_if:type,account', 'nullable', 'email:rfc', 'max:255'],
            'host' => ['required_if:type,account', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:type,account', 'nullable', 'integer', 'between:1,65535'],
            'protocol' => ['required_if:type,account', 'nullable', 'string', 'max:32'],
            'encryption' => ['nullable', 'string', 'max:32'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'active' => ['required_if:type,account', 'boolean'],
            'code' => ['required_if:type,template,group', 'nullable', 'string', 'max:255'],
            'subject' => ['required_if:type,template', 'nullable', 'string', 'max:255'],
            'body' => ['required_if:type,template', 'nullable', 'string'],
            'group_id' => ['required_if:type,template', 'nullable', 'integer', Rule::exists($templateGroupTable, 'tpl_id')],
            'lang' => ['required_if:type,group', 'nullable', 'string', 'max:16'],
        ];
    }
}
