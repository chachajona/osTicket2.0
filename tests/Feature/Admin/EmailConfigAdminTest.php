<?php

declare(strict_types=1);

use App\Models\Admin\AdminAuditLog;
use App\Models\EmailAccount;
use App\Models\EmailModel;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateGroup;
use App\Models\Staff;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('email')) {
        $schema->create('email', function (Blueprint $table): void {
            $table->increments('email_id');
            $table->string('name', 128)->nullable();
            $table->string('email', 128);
            $table->unsignedInteger('dept_id')->nullable();
            $table->tinyInteger('noautoresp')->default(0);
            $table->tinyInteger('smtp_active')->default(0);
            $table->string('userid', 255)->nullable();
            $table->text('passwd')->nullable();
            $table->string('host', 255)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    $missingEmailColumns = [
        'name' => fn (Blueprint $table) => $table->string('name', 128)->nullable(),
        'dept_id' => fn (Blueprint $table) => $table->unsignedInteger('dept_id')->nullable(),
        'noautoresp' => fn (Blueprint $table) => $table->tinyInteger('noautoresp')->default(0),
        'smtp_active' => fn (Blueprint $table) => $table->tinyInteger('smtp_active')->default(0),
        'userid' => fn (Blueprint $table) => $table->string('userid', 255)->nullable(),
        'passwd' => fn (Blueprint $table) => $table->text('passwd')->nullable(),
        'host' => fn (Blueprint $table) => $table->string('host', 255)->nullable(),
        'port' => fn (Blueprint $table) => $table->unsignedInteger('port')->nullable(),
        'created' => fn (Blueprint $table) => $table->timestamp('created')->nullable(),
        'updated' => fn (Blueprint $table) => $table->timestamp('updated')->nullable(),
    ];

    foreach ($missingEmailColumns as $column => $definition) {
        if (! $schema->hasColumn('email', $column)) {
            $schema->table('email', $definition);
        }
    }

    if (! $schema->hasTable('email_account')) {
        $schema->create('email_account', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('email_id');
            $table->string('type', 32)->nullable();
            $table->text('auth_bk')->nullable();
            $table->text('auth_id')->nullable();
            $table->tinyInteger('active')->default(1);
            $table->string('host', 255)->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('folder', 255)->nullable();
            $table->string('protocol', 32)->nullable();
            $table->string('encryption', 32)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    $missingEmailAccountColumns = [
        'type' => fn (Blueprint $table) => $table->string('type', 32)->nullable(),
        'auth_bk' => fn (Blueprint $table) => $table->text('auth_bk')->nullable(),
        'auth_id' => fn (Blueprint $table) => $table->text('auth_id')->nullable(),
        'active' => fn (Blueprint $table) => $table->tinyInteger('active')->default(1),
        'host' => fn (Blueprint $table) => $table->string('host', 255)->nullable(),
        'port' => fn (Blueprint $table) => $table->unsignedInteger('port')->nullable(),
        'folder' => fn (Blueprint $table) => $table->string('folder', 255)->nullable(),
        'protocol' => fn (Blueprint $table) => $table->string('protocol', 32)->nullable(),
        'encryption' => fn (Blueprint $table) => $table->string('encryption', 32)->nullable(),
        'created' => fn (Blueprint $table) => $table->timestamp('created')->nullable(),
        'updated' => fn (Blueprint $table) => $table->timestamp('updated')->nullable(),
    ];

    foreach ($missingEmailAccountColumns as $column => $definition) {
        if (! $schema->hasColumn('email_account', $column)) {
            $schema->table('email_account', $definition);
        }
    }

    if (! $schema->hasTable('email_template_group')) {
        $schema->create('email_template_group', function (Blueprint $table): void {
            $table->increments('tpl_id');
            $table->tinyInteger('isactive')->default(1);
            $table->string('name', 128);
            $table->string('code', 128)->nullable();
            $table->string('lang', 16)->nullable();
            $table->unsignedInteger('flags')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    $missingEmailTemplateGroupColumns = [
        'isactive' => fn (Blueprint $table) => $table->tinyInteger('isactive')->default(1),
        'code' => fn (Blueprint $table) => $table->string('code', 128)->nullable(),
        'lang' => fn (Blueprint $table) => $table->string('lang', 16)->nullable(),
        'flags' => fn (Blueprint $table) => $table->unsignedInteger('flags')->default(0),
        'notes' => fn (Blueprint $table) => $table->text('notes')->nullable(),
        'created' => fn (Blueprint $table) => $table->timestamp('created')->nullable(),
        'updated' => fn (Blueprint $table) => $table->timestamp('updated')->nullable(),
    ];

    foreach ($missingEmailTemplateGroupColumns as $column => $definition) {
        if (! $schema->hasColumn('email_template_group', $column)) {
            $schema->table('email_template_group', $definition);
        }
    }

    if (! $schema->hasTable('email_template')) {
        $schema->create('email_template', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('tpl_id');
            $table->string('name', 128)->nullable();
            $table->string('code', 128)->nullable();
            $table->string('code_name', 128)->nullable();
            $table->string('subject', 255);
            $table->text('body');
            $table->text('notes')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    $missingEmailTemplateColumns = [
        'name' => fn (Blueprint $table) => $table->string('name', 128)->nullable(),
        'code' => fn (Blueprint $table) => $table->string('code', 128)->nullable(),
        'code_name' => fn (Blueprint $table) => $table->string('code_name', 128)->nullable(),
        'notes' => fn (Blueprint $table) => $table->text('notes')->nullable(),
        'created' => fn (Blueprint $table) => $table->timestamp('created')->nullable(),
        'updated' => fn (Blueprint $table) => $table->timestamp('updated')->nullable(),
    ];

    foreach ($missingEmailTemplateColumns as $column => $definition) {
        if (! $schema->hasColumn('email_template', $column)) {
            $schema->table('email_template', $definition);
        }
    }

    EmailAccount::query()->delete();
    EmailModel::query()->delete();
    EmailTemplate::query()->delete();
    EmailTemplateGroup::query()->delete();
    AdminAuditLog::query()->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function grantEmailPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $staff->fresh();
}

function emailAccountAuditPayload(EmailAccount $account): array
{
    return [
        'id' => $account->id,
        'key' => sprintf('account-%d', $account->id),
        'type' => 'account',
        'name' => $account->email?->name ?? 'Mailbox',
        'email' => $account->email?->email ?? '',
        'host' => $account->host,
        'port' => (int) $account->port,
        'protocol' => $account->protocol,
        'encryption' => $account->encryption !== '' ? $account->encryption : null,
        'username' => '[redacted]',
        'password' => '[redacted]',
        'active' => (bool) ($account->active ?? 0),
    ];
}

function emailTemplateAuditPayload(EmailTemplate $template): array
{
    return [
        'id' => $template->id,
        'key' => sprintf('template-%d', $template->id),
        'type' => 'template',
        'name' => $template->name ?: $template->code_name ?: 'Template',
        'code' => $template->code ?: $template->code_name ?: '',
        'subject' => $template->subject,
        'body' => $template->body,
        'group_id' => $template->tpl_id,
        'group_name' => $template->group?->name,
    ];
}

function emailGroupAuditPayload(EmailTemplateGroup $group): array
{
    return [
        'id' => $group->tpl_id,
        'key' => sprintf('group-%d', $group->tpl_id),
        'type' => 'group',
        'name' => $group->name,
        'code' => $group->code,
        'lang' => $group->lang,
        'template_count' => (int) ($group->templates_count ?? 0),
    ];
}

it('renders the email config index with accounts templates and groups', function (): void {
    $group = EmailTemplateGroup::query()->create([
        'name' => 'Default Replies',
        'code' => 'default',
        'lang' => 'en_US',
        'created' => now(),
        'updated' => now(),
    ]);

    $email = EmailModel::query()->create([
        'name' => 'Support Inbox',
        'email' => 'support@example.com',
        'created' => now(),
        'updated' => now(),
    ]);

    EmailAccount::query()->create([
        'email_id' => $email->email_id,
        'host' => 'imap.example.com',
        'port' => 993,
        'protocol' => 'imap',
        'encryption' => 'ssl',
        'auth_id' => 'enc-user',
        'auth_bk' => 'enc-pass',
        'active' => 1,
        'created' => now(),
        'updated' => now(),
    ]);

    EmailTemplate::query()->create([
        'tpl_id' => $group->tpl_id,
        'name' => 'Ticket Assigned',
        'code' => 'ticket.assigned',
        'code_name' => 'ticket.assigned',
        'subject' => 'A ticket was assigned',
        'body' => 'Body text',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantEmailPermissions(actingAsAdmin(), ['admin.email.update']);

    actingAs($staff, 'staff');

    get(route('admin.email-config.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/EmailConfig/Index')
            ->has('items', 3)
            ->where('summary.accounts', 1)
            ->where('summary.templates', 1)
            ->where('summary.groups', 1)
        );
});

it('forbids the email config index for unauthorized staff', function (): void {
    actingAsAgent();

    get(route('admin.email-config.index'))->assertForbidden();
});

it('renders create and edit pages for authorized admins', function (): void {
    $group = EmailTemplateGroup::query()->create([
        'name' => 'Default Replies',
        'code' => 'default',
        'lang' => 'en_US',
        'created' => now(),
        'updated' => now(),
    ]);

    $template = EmailTemplate::query()->create([
        'tpl_id' => $group->tpl_id,
        'name' => 'Ticket Assigned',
        'code' => 'ticket.assigned',
        'code_name' => 'ticket.assigned',
        'subject' => 'Assigned',
        'body' => 'Body text',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantEmailPermissions(actingAsAdmin(), ['admin.email.create', 'admin.email.update']);

    actingAs($staff, 'staff');

    get(route('admin.email-config.create').'?type=template')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/EmailConfig/Edit')
            ->where('type', 'template')
            ->where('config', null)
            ->has('templateGroups', 1)
        );

    actingAs($staff, 'staff');

    get(route('admin.email-config.edit', 'template-'.$template->id))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/EmailConfig/Edit')
            ->where('type', 'template')
            ->where('config.name', 'Ticket Assigned')
            ->where('config.group_id', $group->tpl_id)
        );
});

it('creates a mail account with encrypted credentials and redacted audit logs', function (): void {
    $staff = grantEmailPermissions(actingAsAdmin(), ['admin.email.create']);

    actingAs($staff, 'staff');

    post(route('admin.email-config.store'), [
        'type' => 'account',
        'name' => 'Support Inbox',
        'email' => 'support@example.com',
        'host' => 'imap.example.com',
        'port' => 993,
        'protocol' => 'imap',
        'encryption' => 'ssl',
        'username' => 'support-user',
        'password' => 'super-secret',
        'active' => true,
    ])->assertRedirect();

    $account = EmailAccount::query()->with('email')->firstOrFail();

    $raw = DB::connection('legacy')->table('email_account')->where('id', $account->id)->first();

    expect($raw)->not->toBeNull()
        ->and($raw->auth_id)->not->toBe('support-user')
        ->and($raw->auth_bk)->not->toBe('super-secret');

    assertDatabaseHas('email', [
        'email_id' => $account->email_id,
        'name' => 'Support Inbox',
        'email' => 'support@example.com',
    ], 'legacy');

    $log = assertAuditLogged('email.create', $account, null, emailAccountAuditPayload($account));

    expect($log->after['username'])->toBe('[redacted]')
        ->and($log->after['password'])->toBe('[redacted]');
});

it('rejects invalid email config creation payloads', function (): void {
    $staff = grantEmailPermissions(actingAsAdmin(), ['admin.email.create']);

    actingAs($staff, 'staff');

    from(route('admin.email-config.create').'?type=account')
        ->post(route('admin.email-config.store'), [
            'type' => 'account',
            'name' => '',
            'email' => 'not-an-email',
            'host' => '',
            'port' => 70000,
            'protocol' => '',
            'active' => 'nope',
        ])
        ->assertSessionHasErrors(['name', 'email', 'host', 'port', 'protocol', 'active']);

    expect(EmailAccount::query()->count())->toBe(0);
});

it('forbids unauthorized email config creation', function (): void {
    actingAsAgent();

    post(route('admin.email-config.store'), [
        'type' => 'group',
        'name' => 'Default Replies',
        'code' => 'default',
        'lang' => 'en_US',
    ])->assertForbidden();
});

it('updates a template and writes an audit log diff', function (): void {
    $oldGroup = EmailTemplateGroup::query()->create([
        'name' => 'Default Replies',
        'code' => 'default',
        'lang' => 'en_US',
        'created' => now(),
        'updated' => now(),
    ]);
    $newGroup = EmailTemplateGroup::query()->create([
        'name' => 'Escalations',
        'code' => 'escalations',
        'lang' => 'en_US',
        'created' => now(),
        'updated' => now(),
    ]);
    $template = EmailTemplate::query()->create([
        'tpl_id' => $oldGroup->tpl_id,
        'name' => 'Ticket Assigned',
        'code' => 'ticket.assigned',
        'code_name' => 'ticket.assigned',
        'subject' => 'Assigned',
        'body' => 'Old body',
        'created' => now(),
        'updated' => now(),
    ]);
    $template->load('group');

    $before = emailTemplateAuditPayload($template);
    $staff = grantEmailPermissions(actingAsAdmin(), ['admin.email.update']);

    actingAs($staff, 'staff');

    put(route('admin.email-config.update', 'template-'.$template->id), [
        'type' => 'template',
        'name' => 'Ticket Escalated',
        'code' => 'ticket.escalated',
        'subject' => 'Escalated',
        'body' => 'New body',
        'group_id' => $newGroup->tpl_id,
    ])->assertRedirect(route('admin.email-config.edit', 'template-'.$template->id));

    $template->refresh()->load('group');

    assertAuditLogged('email.update', $template, $before, emailTemplateAuditPayload($template));
});

it('deletes a template group and writes an audit log entry', function (): void {
    $group = EmailTemplateGroup::query()->create([
        'name' => 'Default Replies',
        'code' => 'default',
        'lang' => 'en_US',
        'created' => now(),
        'updated' => now(),
    ]);
    $group->loadCount('templates');
    $before = emailGroupAuditPayload($group);
    $staff = grantEmailPermissions(actingAsAdmin(), ['admin.email.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.email-config.destroy', 'group-'.$group->tpl_id))
        ->assertRedirect(route('admin.email-config.index'));

    assertDatabaseMissing('email_template_group', ['tpl_id' => $group->tpl_id], 'legacy');
    assertAuditLogged('email.delete', $group, $before, null);
});
