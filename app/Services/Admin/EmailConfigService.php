<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Department;
use App\Models\EmailAccount;
use App\Models\EmailModel;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateGroup;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles CRUD operations for email configuration entities.
 *
 * Manages email accounts, templates, and template groups with redaction
 * of sensitive credentials during reads and audit logging for all changes.
 */
class EmailConfigService
{
    use NormalizesInput;

    private const string REDACTED_CREDENTIAL = '[redacted]';

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function indexItems(): array
    {
        $accounts = EmailAccount::query()
            ->with('email')
            ->orderBy('id')
            ->get()
            ->map(fn (EmailAccount $account): array => $this->serializeIndexAccount($account));

        $templates = EmailTemplate::query()
            ->with('group')
            ->orderBy('id')
            ->get()
            ->map(fn (EmailTemplate $template): array => $this->serializeIndexTemplate($template));

        $groups = EmailTemplateGroup::query()
            ->withCount('templates')
            ->orderBy('tpl_id')
            ->get()
            ->map(fn (EmailTemplateGroup $group): array => $this->serializeIndexGroup($group));

        return $accounts
            ->concat($templates)
            ->concat($groups)
            ->sortBy([['type_label', 'asc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * @return array{accounts:int,templates:int,groups:int,total:int}
     */
    public function summary(): array
    {
        $accounts = EmailAccount::query()->count();
        $templates = EmailTemplate::query()->count();
        $groups = EmailTemplateGroup::query()->count();

        return [
            'accounts' => $accounts,
            'templates' => $templates,
            'groups' => $groups,
            'total' => $accounts + $templates + $groups,
        ];
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function templateGroupOptions(): array
    {
        return EmailTemplateGroup::query()
            ->orderBy('name')
            ->get(['tpl_id', 'name'])
            ->map(fn (EmailTemplateGroup $group): array => [
                'id' => (int) $group->getKey(),
                'name' => (string) $group->name,
            ])
            ->all();
    }

    public function normalizeType(string $type): string
    {
        return in_array($type, ['account', 'template', 'group'], true) ? $type : 'account';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    public function create(array $data, Staff $actor): array
    {
        return match ($this->normalizeType((string) Arr::get($data, 'type', 'account'))) {
            'account' => $this->createAccount($data, $actor),
            'template' => $this->createTemplate($data, $actor),
            'group' => $this->createGroup($data, $actor),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    public function update(string $routeKey, array $data, Staff $actor): array
    {
        $resolved = $this->resolveForRoute($routeKey);
        $type = $this->normalizeType((string) Arr::get($data, 'type', $resolved['type']));

        if ($type !== $resolved['type']) {
            throw ValidationException::withMessages([
                'type' => 'Email config type cannot be changed once created.',
            ]);
        }

        return match ($type) {
            'account' => $this->updateAccount($resolved['subject'], $data, $actor),
            'template' => $this->updateTemplate($resolved['subject'], $data, $actor),
            'group' => $this->updateGroup($resolved['subject'], $data, $actor),
        };
    }

    public function delete(string $routeKey, Staff $actor): void
    {
        $resolved = $this->resolveForRoute($routeKey);

        match ($resolved['type']) {
            'account' => $this->deleteAccount($resolved['subject'], $actor),
            'template' => $this->deleteTemplate($resolved['subject'], $actor),
            'group' => $this->deleteGroup($resolved['subject'], $actor),
        };
    }

    /**
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    public function resolveForRoute(string $routeKey): array
    {
        [$type, $id] = $this->parseRouteKey($routeKey);

        return match ($type) {
            'account' => $this->resolvedAccount($id),
            'template' => $this->resolvedTemplate($id),
            'group' => $this->resolvedGroup($id),
        };
    }

    /**
     * @return array{0:string,1:int}
     */
    private function parseRouteKey(string $routeKey): array
    {
        if (! preg_match('/^(account|template|group)-(\d+)$/', $routeKey, $matches)) {
            throw new NotFoundHttpException;
        }

        return [$matches[1], (int) $matches[2]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function createAccount(array $data, Staff $actor): array
    {
        /** @var EmailAccount $account */
        $account = DB::connection('legacy')->transaction(function () use ($data): EmailAccount {
            $email = EmailModel::query()->create($this->accountEmailPayload($data, false));

            return EmailAccount::query()->create([
                ...$this->accountPayload($data, false),
                'email_id' => (int) $email->getKey(),
            ]);
        });

        $account->load('email');
        $this->auditLogger->record($actor, 'email.create', $account, before: null, after: $this->serializeEditAccount($account));

        return $this->resolvedAccount((int) $account->getKey());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function updateAccount(Model $subject, array $data, Staff $actor): array
    {
        /** @var EmailAccount $account */
        $account = $subject;
        $account->loadMissing('email');
        $before = $this->serializeEditAccount($account);

        DB::connection('legacy')->transaction(function () use ($account, $data): void {
            $email = $account->email;

            if (! $email instanceof EmailModel) {
                $email = EmailModel::query()->create($this->accountEmailPayload($data, false));
                $account->email_id = (int) $email->getKey();
            } else {
                $email->forceFill($this->accountEmailPayload($data, true))->save();
            }

            $account->forceFill($this->accountPayload($data, true))->save();
        });

        $account->refresh()->load('email');
        $this->auditLogger->record($actor, 'email.update', $account, before: $before, after: $this->serializeEditAccount($account));

        return $this->resolvedAccount((int) $account->getKey());
    }

    private function deleteAccount(Model $subject, Staff $actor): void
    {
        /** @var EmailAccount $account */
        $account = $subject;
        $account->loadMissing('email');
        $before = $this->serializeEditAccount($account);

        if ($account->email_id !== null && Department::query()->where('email_id', (int) $account->email_id)->exists()) {
            throw ValidationException::withMessages([
                'email_config' => 'Email account cannot be deleted while departments still reference it.',
            ]);
        }

        DB::connection('legacy')->transaction(function () use ($account): void {
            $email = $account->email;
            $account->delete();

            if ($email instanceof EmailModel) {
                $email->delete();
            }
        });

        $this->auditLogger->record($actor, 'email.delete', $account, before: $before, after: null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function createTemplate(array $data, Staff $actor): array
    {
        /** @var EmailTemplate $template */
        $template = DB::connection('legacy')->transaction(function () use ($data): EmailTemplate {
            return EmailTemplate::query()->create($this->templatePayload($data, false));
        });

        $template->load('group');
        $this->auditLogger->record($actor, 'email.create', $template, before: null, after: $this->serializeEditTemplate($template));

        return $this->resolvedTemplate((int) $template->getKey());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function updateTemplate(Model $subject, array $data, Staff $actor): array
    {
        /** @var EmailTemplate $template */
        $template = $subject;
        $template->loadMissing('group');
        $before = $this->serializeEditTemplate($template);

        DB::connection('legacy')->transaction(function () use ($template, $data): void {
            $template->forceFill($this->templatePayload($data, true))->save();
        });

        $template->refresh()->load('group');
        $this->auditLogger->record($actor, 'email.update', $template, before: $before, after: $this->serializeEditTemplate($template));

        return $this->resolvedTemplate((int) $template->getKey());
    }

    private function deleteTemplate(Model $subject, Staff $actor): void
    {
        /** @var EmailTemplate $template */
        $template = $subject;
        $template->loadMissing('group');
        $before = $this->serializeEditTemplate($template);

        DB::connection('legacy')->transaction(function () use ($template): void {
            $template->delete();
        });

        $this->auditLogger->record($actor, 'email.delete', $template, before: $before, after: null);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function createGroup(array $data, Staff $actor): array
    {
        /** @var EmailTemplateGroup $group */
        $group = DB::connection('legacy')->transaction(function () use ($data): EmailTemplateGroup {
            return EmailTemplateGroup::query()->create($this->groupPayload($data, false));
        });

        $group->loadCount('templates');
        $this->auditLogger->record($actor, 'email.create', $group, before: null, after: $this->serializeEditGroup($group));

        return $this->resolvedGroup((int) $group->getKey());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function updateGroup(Model $subject, array $data, Staff $actor): array
    {
        /** @var EmailTemplateGroup $group */
        $group = $subject;
        $group->loadCount('templates');
        $before = $this->serializeEditGroup($group);

        DB::connection('legacy')->transaction(function () use ($group, $data): void {
            $group->forceFill($this->groupPayload($data, true))->save();
        });

        $group->refresh()->loadCount('templates');
        $this->auditLogger->record($actor, 'email.update', $group, before: $before, after: $this->serializeEditGroup($group));

        return $this->resolvedGroup((int) $group->getKey());
    }

    private function deleteGroup(Model $subject, Staff $actor): void
    {
        /** @var EmailTemplateGroup $group */
        $group = $subject;
        $group->loadCount('templates');

        if (($group->templates_count ?? 0) > 0 || EmailTemplate::query()->where('tpl_id', (int) $group->getKey())->exists()) {
            throw ValidationException::withMessages([
                'email_config' => 'Template group cannot be deleted while templates still belong to it.',
            ]);
        }

        if (Department::query()->where('tpl_id', (int) $group->getKey())->exists()) {
            throw ValidationException::withMessages([
                'email_config' => 'Template group cannot be deleted while departments still reference it.',
            ]);
        }

        $before = $this->serializeEditGroup($group);

        DB::connection('legacy')->transaction(function () use ($group): void {
            $group->delete();
        });

        $this->auditLogger->record($actor, 'email.delete', $group, before: $before, after: null);
    }

    /**
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function resolvedAccount(int $id): array
    {
        $account = EmailAccount::query()->with('email')->findOrFail($id);

        return [
            'key' => $this->routeKey('account', $id),
            'type' => 'account',
            'subject' => $account,
            'config' => $this->serializeEditAccount($account),
        ];
    }

    /**
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function resolvedTemplate(int $id): array
    {
        $template = EmailTemplate::query()->with('group')->findOrFail($id);

        return [
            'key' => $this->routeKey('template', $id),
            'type' => 'template',
            'subject' => $template,
            'config' => $this->serializeEditTemplate($template),
        ];
    }

    /**
     * @return array{key:string,type:string,subject:Model,config:array<string,mixed>}
     */
    private function resolvedGroup(int $id): array
    {
        $group = EmailTemplateGroup::query()->withCount('templates')->findOrFail($id);

        return [
            'key' => $this->routeKey('group', $id),
            'type' => 'group',
            'subject' => $group,
            'config' => $this->serializeEditGroup($group),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function accountEmailPayload(array $data, bool $isUpdate): array
    {
        $payload = [
            'name' => trim((string) $data['name']),
            'email' => trim((string) $data['email']),
        ];

        if ($this->hasColumn('email', 'updated')) {
            $payload['updated'] = now();
        }

        if (! $isUpdate && $this->hasColumn('email', 'created')) {
            $payload['created'] = now();
        }

        if ($this->hasColumn('email', 'smtp_active')) {
            $payload['smtp_active'] = ! empty($data['active']) ? 1 : 0;
        }

        if ($this->hasColumn('email', 'userid')) {
            if (! $isUpdate || $data['username'] !== self::REDACTED_CREDENTIAL) {
                $payload['userid'] = $this->normalizeNullableString($data['username'] ?? null);
            }
        }

        if ($this->hasColumn('email', 'passwd')) {
            if (! $isUpdate || ($data['password'] !== self::REDACTED_CREDENTIAL && $this->normalizeNullableString($data['password'] ?? null) !== null)) {
                $payload['passwd'] = $this->normalizeNullableString($data['password'] ?? null);
            }
        }

        if ($this->hasColumn('email', 'host')) {
            $payload['host'] = trim((string) $data['host']);
        }

        if ($this->hasColumn('email', 'port')) {
            $payload['port'] = (int) $data['port'];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function accountPayload(array $data, bool $isUpdate): array
    {
        $payload = [
            'host' => trim((string) $data['host']),
            'port' => (int) $data['port'],
            'protocol' => trim((string) $data['protocol']),
            'encryption' => $this->normalizeNullableString($data['encryption'] ?? null),
            'active' => ! empty($data['active']) ? 1 : 0,
        ];

        if (! $isUpdate || $data['username'] !== self::REDACTED_CREDENTIAL) {
            $payload['auth_id'] = $this->normalizeNullableString($data['username'] ?? null);
        }

        if (! $isUpdate || ($data['password'] !== self::REDACTED_CREDENTIAL && $this->normalizeNullableString($data['password'] ?? null) !== null)) {
            $payload['auth_bk'] = $this->normalizeNullableString($data['password'] ?? null);
        }

        if ($this->hasColumn('email_account', 'updated')) {
            $payload['updated'] = now();
        }

        if (! $isUpdate && $this->hasColumn('email_account', 'created')) {
            $payload['created'] = now();
        }

        if ($this->hasColumn('email_account', 'folder')) {
            $payload['folder'] = 'INBOX';
        }

        if ($this->hasColumn('email_account', 'type')) {
            $payload['type'] = 'mailbox';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function templatePayload(array $data, bool $isUpdate): array
    {
        $payload = [
            'tpl_id' => (int) $data['group_id'],
            'subject' => trim((string) $data['subject']),
            'body' => (string) $data['body'],
            'notes' => null,
        ];

        if ($this->hasColumn('email_template', 'name')) {
            $payload['name'] = trim((string) $data['name']);
        }

        if ($this->hasColumn('email_template', 'code')) {
            $payload['code'] = trim((string) $data['code']);
        }

        if ($this->hasColumn('email_template', 'code_name')) {
            $payload['code_name'] = trim((string) $data['code']);
        }

        if ($this->hasColumn('email_template', 'updated')) {
            $payload['updated'] = now();
        }

        if (! $isUpdate && $this->hasColumn('email_template', 'created')) {
            $payload['created'] = now();
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function groupPayload(array $data, bool $isUpdate): array
    {
        $payload = [
            'name' => trim((string) $data['name']),
            'lang' => trim((string) $data['lang']),
        ];

        if ($this->hasColumn('email_template_group', 'code')) {
            $payload['code'] = trim((string) $data['code']);
        }

        if ($this->hasColumn('email_template_group', 'updated')) {
            $payload['updated'] = now();
        }

        if (! $isUpdate && $this->hasColumn('email_template_group', 'created')) {
            $payload['created'] = now();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEditAccount(EmailAccount $account): array
    {
        return [
            'id' => (int) $account->getKey(),
            'key' => $this->routeKey('account', (int) $account->getKey()),
            'type' => 'account',
            'name' => (string) ($account->email?->name ?? 'Mailbox'),
            'email' => (string) ($account->email?->email ?? ''),
            'host' => (string) ($account->host ?? ''),
            'port' => (int) ($account->port ?? 0),
            'protocol' => (string) ($account->protocol ?? ''),
            'encryption' => $this->normalizeNullableString($account->encryption),
            'username' => $this->normalizeNullableString($account->getRawOriginal('auth_id')) !== null ? self::REDACTED_CREDENTIAL : null,
            'active' => (bool) ($account->active ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEditTemplate(EmailTemplate $template): array
    {
        return [
            'id' => (int) $template->getKey(),
            'key' => $this->routeKey('template', (int) $template->getKey()),
            'type' => 'template',
            'name' => (string) ($template->getAttribute('name') ?: $template->getAttribute('code_name') ?: 'Template'),
            'code' => (string) ($template->getAttribute('code') ?: $template->getAttribute('code_name') ?: ''),
            'subject' => (string) ($template->subject ?? ''),
            'body' => (string) ($template->body ?? ''),
            'group_id' => $template->tpl_id !== null ? (int) $template->tpl_id : null,
            'group_name' => $template->group?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEditGroup(EmailTemplateGroup $group): array
    {
        return [
            'id' => (int) $group->getKey(),
            'key' => $this->routeKey('group', (int) $group->getKey()),
            'type' => 'group',
            'name' => (string) ($group->name ?? ''),
            'code' => $this->normalizeNullableString($group->getAttribute('code')),
            'lang' => (string) ($group->lang ?? ''),
            'template_count' => (int) ($group->templates_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIndexAccount(EmailAccount $account): array
    {
        return [
            'id' => (int) $account->getKey(),
            'key' => $this->routeKey('account', (int) $account->getKey()),
            'type' => 'account',
            'type_label' => 'Mail Account',
            'name' => (string) ($account->email?->name ?? 'Mailbox'),
            'description' => trim(implode(' • ', array_filter([
                $account->email?->email,
                $account->host,
                $account->protocol,
            ]))),
            'status' => (bool) ($account->active ?? 0) ? 'Active' : 'Inactive',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIndexTemplate(EmailTemplate $template): array
    {
        return [
            'id' => (int) $template->getKey(),
            'key' => $this->routeKey('template', (int) $template->getKey()),
            'type' => 'template',
            'type_label' => 'Template',
            'name' => (string) ($template->getAttribute('name') ?: $template->getAttribute('code_name') ?: 'Template'),
            'description' => trim(implode(' • ', array_filter([
                $template->subject,
                $template->group?->name,
            ]))),
            'status' => 'Template',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIndexGroup(EmailTemplateGroup $group): array
    {
        return [
            'id' => (int) $group->getKey(),
            'key' => $this->routeKey('group', (int) $group->getKey()),
            'type' => 'group',
            'type_label' => 'Template Group',
            'name' => (string) ($group->name ?? ''),
            'description' => trim(implode(' • ', array_filter([
                $group->getAttribute('code'),
                $group->lang,
                sprintf('%d templates', (int) ($group->templates_count ?? 0)),
            ]))),
            'status' => 'Group',
        ];
    }

    private function routeKey(string $type, int $id): string
    {
        return sprintf('%s-%d', $type, $id);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::connection('legacy')->hasColumn($table, $column);
    }
}
