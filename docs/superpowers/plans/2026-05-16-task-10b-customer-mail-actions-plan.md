# Task 10b — Customer Mail Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship staff reply (with optional same-transaction status change) and close-with-notify in the new Laravel SCP, consuming the ownership-swap mechanism shipped in PR #73. Customer mail flows from Laravel only when the matching `mail.event_class_owner.*` flag is `laravel`; rollback per class via env var.

**Architecture:** New `ReplyController` + `ReplyPostingService` for replies (atomic with optional status change). Extended `StatusController` + `StatusTransitionService` for close-with-notify (`notify_user` boolean). All customer mail flows through new `StaffReplyMail` / `CloseNotifyMail` (`ShouldQueueAfterCommit`) which render via `LegacyTemplateRenderer` reading legacy `ost_email_template`. Outbound Message-ID + In-Reply-To + References persisted to legacy `ost_thread_entry_email` so legacy's inbound piper threads customer replies correctly.

**Tech Stack:** Laravel 13, PHP 8.5, Pest 4, Inertia v3 React, Symfony Mailer, Spatie Permission, Spatie ActivityLog (audit via existing `ActionLogger`).

**Spec:** `docs/superpowers/specs/2026-05-16-task-10b-customer-mail-actions-design.md`

---

## File Structure

**New PHP files:**
- `app/Mail/RenderedMail.php` — value object: `subject`, `body_html`, `body_text`
- `app/Exceptions/Scp/LegacyTemplateNotFoundException.php`
- `app/Services/Scp/Mail/LegacyTemplateRenderer.php`
- `app/Services/Scp/Mail/MessageIdGenerator.php`
- `app/Services/Scp/Mail/EmailInfoPersister.php`
- `app/Mail/StaffReplyMail.php`
- `app/Mail/CloseNotifyMail.php`
- `app/Services/Scp/Tickets/ReplyPostingService.php`
- `app/Http/Controllers/Scp/Tickets/ReplyController.php`

**New tests:**
- `tests/Unit/Services/Scp/Mail/LegacyTemplateRendererTest.php`
- `tests/Unit/Services/Scp/Mail/MessageIdGeneratorTest.php`
- `tests/Unit/Services/Scp/Mail/EmailInfoPersisterTest.php`
- `tests/Unit/Mail/StaffReplyMailTest.php`
- `tests/Unit/Mail/CloseNotifyMailTest.php`
- `tests/Unit/Policies/TicketActionPolicyPostReplyTest.php`
- `tests/Unit/Services/Scp/Tickets/ReplyPostingServiceTest.php`
- `tests/Feature/Scp/Tickets/ReplyControllerTest.php`
- `tests/Feature/Scp/Tickets/CloseNotifyTest.php`
- `tests/Feature/Mail/CustomerReplyThreadingTest.php`
- `tests/Feature/Mail/QueuedMailFailureTest.php`

**Modified PHP files:**
- `app/Policies/TicketActionPolicy.php` — add `postReply` method
- `app/Services/Scp/Tickets/StatusTransitionService.php` — accept `notify_user`, queue `CloseNotifyMail`
- `app/Http/Controllers/Scp/Tickets/StatusController.php` — accept `notify_user` payload
- `app/Providers/AppServiceProvider.php` — bind new singletons
- `routes/web.php` — add `tickets.replies.store` route
- `database/seeders/PermissionSeeder.php` (or wherever permissions are seeded) — add `tickets.post-reply`

**New / modified frontend files:**
- `resources/js/components/tickets/ReplyComposer.tsx` (new)
- `resources/js/components/tickets/StatusPicker.tsx` (modify — add `notify_user` checkbox)
- `resources/js/pages/Scp/Tickets/Show.tsx` (modify — render `ReplyComposer`)

**Reused (no change):**
- `app/Services/Scp/Mail/OutboundMailDispatcher.php`, `app/Mail/OutboundMailGuard.php`, `app/Mail/EventClassHeader.php`
- `app/Services/Scp/Tickets/{ActionLogger, ThreadEventWriter, SearchIndexer, TicketCacheUpdater, NotePostingService, LockService, DraftService}`

---

## Task 1: Foundation — `RenderedMail` value object + `LegacyTemplateNotFoundException`

Two tiny classes that the renderer (Task 2) will depend on. Trivial enough to ship as one commit; no tests needed (pure data + sentinel exception).

**Files:**
- Create: `app/Mail/RenderedMail.php`
- Create: `app/Exceptions/Scp/LegacyTemplateNotFoundException.php`

- [ ] **Step 1: Create the value object**

```php
<?php

declare(strict_types=1);

namespace App\Mail;

final class RenderedMail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $bodyHtml,
        public readonly string $bodyText,
    ) {}
}
```

- [ ] **Step 2: Create the exception**

```php
<?php

declare(strict_types=1);

namespace App\Exceptions\Scp;

use RuntimeException;

final class LegacyTemplateNotFoundException extends RuntimeException
{
    public function __construct(public readonly string $codeName, public readonly int $tplId)
    {
        parent::__construct(sprintf(
            'Legacy email template "%s" not found in template group %d or system default.',
            $codeName,
            $tplId,
        ));
    }
}
```

- [ ] **Step 3: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Mail/RenderedMail.php app/Exceptions/Scp/LegacyTemplateNotFoundException.php
git commit -m "feat(mail): add RenderedMail value object and LegacyTemplateNotFoundException"
```

---

## Task 2: `LegacyTemplateRenderer`

Reads `ost_email_template` row by `code_name` + dept's `tpl_id` (fallback to default group). Substitutes `%{var}` tokens with HTML-escaping over legacy.

**Files:**
- Create: `tests/Unit/Services/Scp/Mail/LegacyTemplateRendererTest.php`
- Create: `app/Services/Scp/Mail/LegacyTemplateRenderer.php`

- [ ] **Step 1: Write the failing test file**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Mail;

use App\Exceptions\Scp\LegacyTemplateNotFoundException;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\Scp\Mail\LegacyTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    private LegacyTemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        // Default template group + dept-specific overrides
        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([
            ['tpl_id' => 0, 'isactive' => 1, 'name' => 'System Default'],
            ['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default Templates'],
            ['tpl_id' => 2, 'isactive' => 1, 'name' => 'Engineering Overrides'],
        ]);

        DB::connection('legacy')->table('email_template')->insert([
            [
                'id' => 1, 'tpl_id' => 1, 'code_name' => 'ticket.reply',
                'subject' => 'Re: %{ticket.subject} [#%{ticket.number}]',
                'body' => '<p>Hi %{ticket.name},</p><p>%{response}</p>%{signature}',
            ],
            [
                'id' => 2, 'tpl_id' => 1, 'code_name' => 'note.alert',
                'subject' => 'Ticket #%{ticket.number} update',
                'body' => '<p>%{comments}</p>',
            ],
            [
                'id' => 3, 'tpl_id' => 2, 'code_name' => 'ticket.reply',
                'subject' => '[Eng] %{ticket.subject}',
                'body' => '<p>%{response}</p>',
            ],
        ]);

        $this->renderer = app(LegacyTemplateRenderer::class);
    }

    public function test_renders_ticket_reply_with_default_template_group(): void
    {
        [$ticket, $entry] = $this->seedTicket(tplId: 1);

        $rendered = $this->renderer->render('ticket.reply', $ticket, $entry, '<p>--<br>Bob</p>');

        $this->assertSame('Re: Test subject [#000123]', $rendered->subject);
        $this->assertStringContainsString('<p>Hi Alice,</p>', $rendered->bodyHtml);
        $this->assertStringContainsString('<p>The response body</p>', $rendered->bodyHtml);
        $this->assertStringContainsString('<p>--<br>Bob</p>', $rendered->bodyHtml);
        $this->assertSame('Re: Test subject [#000123]', $rendered->subject);
        $this->assertStringContainsString('Hi Alice,', $rendered->bodyText);
        $this->assertStringNotContainsString('<p>', $rendered->bodyText);
    }

    public function test_uses_dept_template_group_when_dept_tpl_id_set(): void
    {
        [$ticket, $entry] = $this->seedTicket(tplId: 2);

        $rendered = $this->renderer->render('ticket.reply', $ticket, $entry, null);

        $this->assertSame('[Eng] Test subject', $rendered->subject);
    }

    public function test_falls_back_to_default_group_when_dept_tpl_id_zero(): void
    {
        [$ticket, $entry] = $this->seedTicket(tplId: 0);

        $rendered = $this->renderer->render('ticket.reply', $ticket, $entry, null);

        $this->assertSame('Re: Test subject [#000123]', $rendered->subject);
    }

    public function test_throws_when_template_missing(): void
    {
        [$ticket, $entry] = $this->seedTicket(tplId: 1);

        $this->expectException(LegacyTemplateNotFoundException::class);
        $this->renderer->render('does.not.exist', $ticket, $entry, null);
    }

    public function test_escapes_html_in_substitutions(): void
    {
        [$ticket, $entry] = $this->seedTicket(tplId: 1, subject: '<script>alert(1)</script>');

        $rendered = $this->renderer->render('ticket.reply', $ticket, $entry, null);

        $this->assertStringNotContainsString('<script>', $rendered->subject);
        $this->assertStringContainsString('&lt;script&gt;', $rendered->subject);
    }

    public function test_renders_note_alert_with_comments_override(): void
    {
        [$ticket, $entry] = $this->seedTicket(tplId: 1);

        $rendered = $this->renderer->render(
            'note.alert',
            $ticket,
            $entry,
            null,
            bodyOverride: 'The closing comment.',
        );

        $this->assertStringContainsString('<p>The closing comment.</p>', $rendered->bodyHtml);
    }

    /**
     * @return array{0: Ticket, 1: ThreadEntry}
     */
    private function seedTicket(int $tplId, string $subject = 'Test subject'): array
    {
        $userEmail = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['name' => 'Alice', 'default_email_id' => $userEmail->id]);
        $userEmail->update(['user_id' => $user->id]);

        $dept = Department::factory()->create(['name' => 'Support', 'tpl_id' => $tplId]);
        $staff = Staff::factory()->create(['firstname' => 'Bob', 'lastname' => 'Builder']);

        $ticket = Ticket::factory()->create([
            'ticket_id' => 123,
            'number' => '000123',
            'subject' => $subject,
            'user_id' => $user->id,
            'dept_id' => $dept->id,
        ]);

        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);
        $entry = ThreadEntry::factory()->create([
            'thread_id' => $thread->id,
            'staff_id' => $staff->staff_id,
            'type' => 'R',
            'body' => 'The response body',
            'format' => 'html',
        ]);

        return [$ticket->refresh(), $entry->refresh()];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/Scp/Mail/LegacyTemplateRendererTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement the renderer**

```php
<?php

declare(strict_types=1);

namespace App\Services\Scp\Mail;

use App\Exceptions\Scp\LegacyTemplateNotFoundException;
use App\Mail\RenderedMail;
use App\Models\Ticket;
use App\Models\ThreadEntry;
use Illuminate\Support\Facades\DB;

class LegacyTemplateRenderer
{
    public function render(
        string $codeName,
        Ticket $ticket,
        ThreadEntry $entry,
        ?string $signatureText,
        ?string $bodyOverride = null,
    ): RenderedMail {
        $deptTplId = (int) ($ticket->dept->tpl_id ?? 0);

        $row = $this->loadTemplate($codeName, $deptTplId);

        $table = $this->substitutionTable($ticket, $entry, $signatureText, $bodyOverride);

        $subject = $this->applySubstitutions((string) $row->subject, $table, escape: true);
        $bodyHtml = $this->applySubstitutions((string) $row->body, $table, escape: true);
        $bodyText = $this->htmlToText($bodyHtml);

        return new RenderedMail(subject: $subject, bodyHtml: $bodyHtml, bodyText: $bodyText);
    }

    private function loadTemplate(string $codeName, int $deptTplId): object
    {
        if ($deptTplId > 0) {
            $row = DB::connection('legacy')->table('email_template')
                ->where('tpl_id', $deptTplId)
                ->where('code_name', $codeName)
                ->first();

            if ($row !== null) {
                return $row;
            }
        }

        $row = DB::connection('legacy')->table('email_template')
            ->where('tpl_id', 1) // System default group
            ->where('code_name', $codeName)
            ->first();

        if ($row === null) {
            throw new LegacyTemplateNotFoundException($codeName, $deptTplId);
        }

        return $row;
    }

    /**
     * @return array<string, string>
     */
    private function substitutionTable(
        Ticket $ticket,
        ThreadEntry $entry,
        ?string $signatureText,
        ?string $bodyOverride,
    ): array {
        $body = $bodyOverride ?? (string) $entry->body;
        $signature = $signatureText ?? '';

        return [
            '%{ticket.number}' => (string) $ticket->number,
            '%{ticket.subject}' => (string) $ticket->subject,
            '%{ticket.name}' => (string) ($ticket->user->name ?? ''),
            '%{ticket.email}' => (string) ($ticket->user->defaultEmail?->address ?? ''),
            '%{ticket.dept.name}' => (string) ($ticket->dept->name ?? ''),
            '%{ticket.staff.name}' => (string) ($entry->staff?->displayName() ?? ''),
            '%{response}' => $body,
            '%{comments}' => $body,
            '%{signature}' => $signature, // intentionally not escaped — admin-authored HTML
        ];
    }

    /**
     * @param array<string, string> $table
     */
    private function applySubstitutions(string $template, array $table, bool $escape): string
    {
        foreach ($table as $token => $value) {
            if ($token === '%{signature}') {
                $template = str_replace($token, $value, $template);
                continue;
            }
            $replacement = $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $value;
            $template = str_replace($token, $replacement, $template);
        }

        return $template;
    }

    private function htmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/Scp/Mail/LegacyTemplateRendererTest.php`

Expected: PASS — 6 passed.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Scp/Mail/LegacyTemplateRenderer.php tests/Unit/Services/Scp/Mail/LegacyTemplateRendererTest.php
git commit -m "feat(mail): add LegacyTemplateRenderer for ost_email_template parity"
```

---

## Task 3: `MessageIdGenerator`

Generates `<L-{ticket_id}-{entry_id}-{16-hex}@host>`. Walks `ost_thread_entry_email` to build `References` chain and find `In-Reply-To`.

**Files:**
- Create: `tests/Unit/Services/Scp/Mail/MessageIdGeneratorTest.php`
- Create: `app/Services/Scp/Mail/MessageIdGenerator.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Mail;

use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Services\Scp\Mail\MessageIdGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MessageIdGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from.address' => 'support@example.test']);

        if (! Schema::connection('legacy')->hasTable('thread_entry_email')) {
            Schema::connection('legacy')->create('thread_entry_email', function (Blueprint $table): void {
                $table->unsignedInteger('id')->autoIncrement();
                $table->unsignedInteger('thread_entry_id');
                $table->unsignedInteger('email_id')->nullable();
                $table->string('mid', 255);
                $table->text('headers')->nullable();
                $table->index('mid');
                $table->index('thread_entry_id');
            });
        }

        DB::connection('legacy')->table('thread_entry_email')->delete();
    }

    public function test_generates_message_id_in_documented_format(): void
    {
        $generator = app(MessageIdGenerator::class);
        $ticket = Ticket::factory()->make(['ticket_id' => 42]);
        $entry = ThreadEntry::factory()->make(['id' => 99]);

        $mid = $generator->next($ticket, $entry);

        $this->assertMatchesRegularExpression('/^<L-42-99-[a-f0-9]{16}@example\.test>$/', $mid);
    }

    public function test_in_reply_to_returns_most_recent_customer_message_mid(): void
    {
        $thread = Thread::factory()->create();
        $entry1 = ThreadEntry::factory()->create(['thread_id' => $thread->id, 'type' => 'M', 'created' => '2026-01-01 10:00:00']);
        $entry2 = ThreadEntry::factory()->create(['thread_id' => $thread->id, 'type' => 'R', 'created' => '2026-01-01 11:00:00']);
        $entry3 = ThreadEntry::factory()->create(['thread_id' => $thread->id, 'type' => 'M', 'created' => '2026-01-01 12:00:00']);

        DB::connection('legacy')->table('thread_entry_email')->insert([
            ['thread_entry_id' => $entry1->id, 'mid' => '<customer-1@x>'],
            ['thread_entry_id' => $entry2->id, 'mid' => '<L-1-2@x>'],
            ['thread_entry_id' => $entry3->id, 'mid' => '<customer-2@x>'],
        ]);

        $generator = app(MessageIdGenerator::class);
        $this->assertSame('<customer-2@x>', $generator->inReplyTo($thread));
    }

    public function test_in_reply_to_returns_null_when_no_customer_messages(): void
    {
        $thread = Thread::factory()->create();
        $generator = app(MessageIdGenerator::class);
        $this->assertNull($generator->inReplyTo($thread));
    }

    public function test_references_walks_entries_in_created_order_most_recent_right(): void
    {
        $thread = Thread::factory()->create();
        $a = ThreadEntry::factory()->create(['thread_id' => $thread->id, 'created' => '2026-01-01 10:00:00']);
        $b = ThreadEntry::factory()->create(['thread_id' => $thread->id, 'created' => '2026-01-01 11:00:00']);
        $c = ThreadEntry::factory()->create(['thread_id' => $thread->id, 'created' => '2026-01-01 12:00:00']);

        DB::connection('legacy')->table('thread_entry_email')->insert([
            ['thread_entry_id' => $a->id, 'mid' => '<a@x>'],
            ['thread_entry_id' => $b->id, 'mid' => '<b@x>'],
            ['thread_entry_id' => $c->id, 'mid' => '<c@x>'],
        ]);

        $generator = app(MessageIdGenerator::class);
        $this->assertSame('<a@x> <b@x> <c@x>', $generator->references($thread));
    }

    public function test_references_returns_empty_string_when_no_prior_entries(): void
    {
        $thread = Thread::factory()->create();
        $generator = app(MessageIdGenerator::class);
        $this->assertSame('', $generator->references($thread));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/Scp/Mail/MessageIdGeneratorTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement the generator**

```php
<?php

declare(strict_types=1);

namespace App\Services\Scp\Mail;

use App\Models\Thread;
use App\Models\Ticket;
use App\Models\ThreadEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageIdGenerator
{
    public function next(Ticket $ticket, ThreadEntry $entry): string
    {
        $host = $this->host();
        $rand = bin2hex(random_bytes(8));

        return sprintf('<L-%d-%d-%s@%s>', $ticket->ticket_id, $entry->id, $rand, $host);
    }

    public function inReplyTo(Thread $thread): ?string
    {
        $row = DB::connection('legacy')->table('thread_entry')
            ->join('thread_entry_email', 'thread_entry.id', '=', 'thread_entry_email.thread_entry_id')
            ->where('thread_entry.thread_id', $thread->id)
            ->where('thread_entry.type', 'M')
            ->orderByDesc('thread_entry.created')
            ->orderByDesc('thread_entry.id')
            ->select('thread_entry_email.mid')
            ->first();

        return $row?->mid;
    }

    public function references(Thread $thread): string
    {
        $mids = DB::connection('legacy')->table('thread_entry')
            ->join('thread_entry_email', 'thread_entry.id', '=', 'thread_entry_email.thread_entry_id')
            ->where('thread_entry.thread_id', $thread->id)
            ->orderBy('thread_entry.created')
            ->orderBy('thread_entry.id')
            ->pluck('thread_entry_email.mid');

        return implode(' ', $mids->all());
    }

    private function host(): string
    {
        $address = (string) (config('mail.from.address') ?? 'osticket@localhost');
        $parts = explode('@', $address);

        return $parts[1] ?? 'osticket.local';
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/Scp/Mail/MessageIdGeneratorTest.php`

Expected: PASS — 5 passed.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Scp/Mail/MessageIdGenerator.php tests/Unit/Services/Scp/Mail/MessageIdGeneratorTest.php
git commit -m "feat(mail): add MessageIdGenerator for RFC 5322 threading"
```

---

## Task 4: `EmailInfoPersister`

INSERTs into `ost_thread_entry_email`. Pure DB writer, no business logic.

**Files:**
- Create: `tests/Unit/Services/Scp/Mail/EmailInfoPersisterTest.php`
- Create: `app/Services/Scp/Mail/EmailInfoPersister.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Mail;

use App\Models\ThreadEntry;
use App\Services\Scp\Mail\EmailInfoPersister;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class EmailInfoPersisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::connection('legacy')->hasTable('thread_entry_email')) {
            Schema::connection('legacy')->create('thread_entry_email', function (Blueprint $table): void {
                $table->unsignedInteger('id')->autoIncrement();
                $table->unsignedInteger('thread_entry_id');
                $table->unsignedInteger('email_id')->nullable();
                $table->string('mid', 255);
                $table->text('headers')->nullable();
                $table->index('mid');
                $table->index('thread_entry_id');
            });
        }

        DB::connection('legacy')->table('thread_entry_email')->delete();
    }

    public function test_records_message_id_for_thread_entry(): void
    {
        $entry = ThreadEntry::factory()->create();
        $persister = app(EmailInfoPersister::class);

        $persister->record($entry, '<test@example.com>', "From: a@b\r\nMessage-ID: <test@example.com>", emailId: 5);

        $row = DB::connection('legacy')->table('thread_entry_email')->first();
        $this->assertSame($entry->id, (int) $row->thread_entry_id);
        $this->assertSame('<test@example.com>', $row->mid);
        $this->assertSame(5, (int) $row->email_id);
        $this->assertStringContainsString('Message-ID', (string) $row->headers);
    }

    public function test_email_id_is_nullable(): void
    {
        $entry = ThreadEntry::factory()->create();
        $persister = app(EmailInfoPersister::class);

        $persister->record($entry, '<test@example.com>', 'headers', emailId: null);

        $row = DB::connection('legacy')->table('thread_entry_email')->first();
        $this->assertNull($row->email_id);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/Scp/Mail/EmailInfoPersisterTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement the persister**

```php
<?php

declare(strict_types=1);

namespace App\Services\Scp\Mail;

use App\Models\ThreadEntry;
use Illuminate\Support\Facades\DB;

class EmailInfoPersister
{
    public function record(ThreadEntry $entry, string $messageId, string $headers, ?int $emailId = null): void
    {
        DB::connection('legacy')->table('thread_entry_email')->insert([
            'thread_entry_id' => $entry->id,
            'email_id' => $emailId,
            'mid' => $messageId,
            'headers' => $headers,
        ]);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/Scp/Mail/EmailInfoPersisterTest.php`

Expected: PASS — 2 passed.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Scp/Mail/EmailInfoPersister.php tests/Unit/Services/Scp/Mail/EmailInfoPersisterTest.php
git commit -m "feat(mail): add EmailInfoPersister for ost_thread_entry_email writes"
```

---

## Task 5: `StaffReplyMail` mailable

Queued mailable, `ShouldQueueAfterCommit`, renders via `LegacyTemplateRenderer`, sets RFC 5322 headers, marks event class via `X-Ost-Event-Class` header. On `failed()`, logs `reply.mail_failed` to `ActionLogger`.

**Files:**
- Create: `tests/Unit/Mail/StaffReplyMailTest.php`
- Create: `app/Mail/StaffReplyMail.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\EventClassHeader;
use App\Mail\StaffReplyMail;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class StaffReplyMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from.address' => 'support@example.test']);
        Mail::fake();

        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([
            ['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default'],
        ]);

        DB::connection('legacy')->table('email_template')->insert([
            'tpl_id' => 1,
            'code_name' => 'ticket.reply',
            'subject' => 'Re: %{ticket.subject}',
            'body' => '<p>%{response}</p>%{signature}',
        ]);
    }

    public function test_implements_queue_after_commit(): void
    {
        $this->assertTrue(is_subclass_of(StaffReplyMail::class, ShouldQueue::class));
        $this->assertTrue(is_subclass_of(StaffReplyMail::class, ShouldQueueAfterCommit::class));
    }

    public function test_attaches_event_class_marker_and_threading_headers(): void
    {
        [$ticket, $entry, $staff] = $this->seedTicket();

        $mail = new StaffReplyMail(
            ticket: $ticket,
            entry: $entry,
            staff: $staff,
            signatureChoice: 'none',
            messageId: '<L-1-1-abc@x>',
            inReplyTo: '<prev@x>',
            references: '<a@x> <prev@x>',
        );

        Mail::to('alice@example.com')->send($mail);

        Mail::assertSent(StaffReplyMail::class, function (StaffReplyMail $sent) {
            $headers = $sent->symfonyMessage->getHeaders();
            return $headers->get(EventClassHeader::NAME)?->getBodyAsString() === EventClassHeader::REPLY
                && $headers->get('Message-ID')?->getBodyAsString() === '<L-1-1-abc@x>'
                && $headers->get('In-Reply-To')?->getBodyAsString() === '<prev@x>'
                && $headers->get('References')?->getBodyAsString() === '<a@x> <prev@x>';
        });
    }

    public function test_failed_logs_reply_mail_failed_audit_entry(): void
    {
        [$ticket, $entry, $staff] = $this->seedTicket();

        $mail = new StaffReplyMail(
            ticket: $ticket,
            entry: $entry,
            staff: $staff,
            signatureChoice: 'none',
            messageId: '<L-1-1-abc@x>',
            inReplyTo: null,
            references: '',
        );

        $mail->failed(new \RuntimeException('SMTP boom'));

        $this->assertDatabaseHas('scp_action_logs', [
            'staff_id' => $staff->staff_id,
            'action' => 'reply.mail_failed',
            'outcome' => 'failed',
            'ticket_id' => $ticket->ticket_id,
        ]);
    }

    /**
     * @return array{0: Ticket, 1: ThreadEntry, 2: Staff}
     */
    private function seedTicket(): array
    {
        $email = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['name' => 'Alice', 'default_email_id' => $email->id]);
        $email->update(['user_id' => $user->id]);

        $dept = Department::factory()->create(['name' => 'Support', 'tpl_id' => 1]);
        $staff = Staff::factory()->create(['firstname' => 'Bob']);

        $ticket = Ticket::factory()->create([
            'subject' => 'Help',
            'user_id' => $user->id,
            'dept_id' => $dept->id,
        ]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);
        $entry = ThreadEntry::factory()->create([
            'thread_id' => $thread->id,
            'staff_id' => $staff->staff_id,
            'type' => 'R',
            'body' => 'Reply body',
        ]);

        return [$ticket->refresh(), $entry->refresh(), $staff];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Mail/StaffReplyMailTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement the mailable**

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Staff;
use App\Models\Ticket;
use App\Models\ThreadEntry;
use App\Services\Scp\Mail\LegacyTemplateRenderer;
use App\Services\Scp\Tickets\ActionLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Throwable;

class StaffReplyMail extends Mailable implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ThreadEntry $entry,
        public readonly Staff $staff,
        public readonly string $signatureChoice,
        public readonly string $messageId,
        public readonly ?string $inReplyTo,
        public readonly string $references,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) ($this->ticket->dept->email->address ?? config('mail.from.address'));

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address($fromAddress, $this->staff->displayName()),
            replyTo: [new \Illuminate\Mail\Mailables\Address($fromAddress, $this->staff->displayName())],
            subject: $this->renderedSubject(),
        );
    }

    public function content(): Content
    {
        $rendered = $this->render();

        return new Content(
            htmlString: $rendered->bodyHtml,
            textString: $rendered->bodyText,
        );
    }

    public function build(): self
    {
        return $this->withSymfonyMessage(function (Email $message): void {
            $headers = $message->getHeaders();
            $headers->remove('Message-ID');
            $headers->addIdHeader('Message-ID', trim($this->messageId, '<>'));
            if ($this->inReplyTo !== null && $this->inReplyTo !== '') {
                $headers->remove('In-Reply-To');
                $headers->addIdHeader('In-Reply-To', trim($this->inReplyTo, '<>'));
            }
            if ($this->references !== '') {
                $headers->remove('References');
                $headers->addTextHeader('References', $this->references);
            }
            $headers->addTextHeader(EventClassHeader::NAME, EventClassHeader::REPLY);
        });
    }

    public function failed(Throwable $exception): void
    {
        app(ActionLogger::class)->record(
            staff: $this->staff,
            action: 'reply.mail_failed',
            outcome: 'failed',
            httpStatus: 0,
            ticketId: $this->ticket->ticket_id,
            beforeState: [
                'error_class' => $exception::class,
                'entry_id' => $this->entry->id,
                'message_id' => $this->messageId,
            ],
        );
    }

    private function renderedSubject(): string
    {
        return $this->render()->subject;
    }

    private function render(): \App\Mail\RenderedMail
    {
        $signatureText = match ($this->signatureChoice) {
            'mine' => (string) ($this->staff->signature ?? ''),
            'dept' => (string) ($this->ticket->dept->signature ?? ''),
            default => '',
        };

        return app(LegacyTemplateRenderer::class)
            ->render('ticket.reply', $this->ticket, $this->entry, $signatureText);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Mail/StaffReplyMailTest.php`

Expected: PASS — 3 passed.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Mail/StaffReplyMail.php tests/Unit/Mail/StaffReplyMailTest.php
git commit -m "feat(mail): add StaffReplyMail queued mailable with RFC 5322 threading"
```

---

## Task 6: `CloseNotifyMail` mailable

Mirrors `StaffReplyMail` but renders `note.alert` and uses `EventClassHeader::CLOSE_NOTIFY`. Comments body is passed in.

**Files:**
- Create: `tests/Unit/Mail/CloseNotifyMailTest.php`
- Create: `app/Mail/CloseNotifyMail.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\CloseNotifyMail;
use App\Mail\EventClassHeader;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class CloseNotifyMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from.address' => 'support@example.test']);
        Mail::fake();

        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([
            ['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default'],
        ]);
        DB::connection('legacy')->table('email_template')->insert([
            'tpl_id' => 1,
            'code_name' => 'note.alert',
            'subject' => 'Ticket #%{ticket.number} update',
            'body' => '<p>%{comments}</p>',
        ]);
    }

    public function test_implements_queue_after_commit(): void
    {
        $this->assertTrue(is_subclass_of(CloseNotifyMail::class, ShouldQueue::class));
        $this->assertTrue(is_subclass_of(CloseNotifyMail::class, ShouldQueueAfterCommit::class));
    }

    public function test_attaches_close_notify_event_class_marker(): void
    {
        [$ticket, $entry, $staff] = $this->seed();

        $mail = new CloseNotifyMail(
            ticket: $ticket,
            entry: $entry,
            staff: $staff,
            comments: 'Closing the ticket.',
            messageId: '<L-1-1-x@x>',
            inReplyTo: null,
            references: '',
        );

        Mail::to('alice@example.com')->send($mail);

        Mail::assertSent(CloseNotifyMail::class, function (CloseNotifyMail $sent) {
            return $sent->symfonyMessage->getHeaders()->get(EventClassHeader::NAME)?->getBodyAsString() === EventClassHeader::CLOSE_NOTIFY;
        });
    }

    public function test_failed_logs_close_mail_failed_audit_entry(): void
    {
        [$ticket, $entry, $staff] = $this->seed();

        $mail = new CloseNotifyMail(
            ticket: $ticket,
            entry: $entry,
            staff: $staff,
            comments: 'msg',
            messageId: '<m@x>',
            inReplyTo: null,
            references: '',
        );

        $mail->failed(new \RuntimeException('SMTP boom'));

        $this->assertDatabaseHas('scp_action_logs', [
            'staff_id' => $staff->staff_id,
            'action' => 'close.mail_failed',
            'outcome' => 'failed',
            'ticket_id' => $ticket->ticket_id,
        ]);
    }

    /**
     * @return array{0: Ticket, 1: ThreadEntry, 2: Staff}
     */
    private function seed(): array
    {
        $email = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['name' => 'Alice', 'default_email_id' => $email->id]);
        $email->update(['user_id' => $user->id]);

        $dept = Department::factory()->create(['tpl_id' => 1]);
        $staff = Staff::factory()->create();

        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'dept_id' => $dept->id]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);
        $entry = ThreadEntry::factory()->create([
            'thread_id' => $thread->id,
            'staff_id' => $staff->staff_id,
            'type' => 'N',
            'body' => 'Closing the ticket.',
        ]);

        return [$ticket->refresh(), $entry->refresh(), $staff];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Mail/CloseNotifyMailTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement the mailable**

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Staff;
use App\Models\Ticket;
use App\Models\ThreadEntry;
use App\Services\Scp\Mail\LegacyTemplateRenderer;
use App\Services\Scp\Tickets\ActionLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;
use Throwable;

class CloseNotifyMail extends Mailable implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ThreadEntry $entry,
        public readonly Staff $staff,
        public readonly string $comments,
        public readonly string $messageId,
        public readonly ?string $inReplyTo,
        public readonly string $references,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = (string) ($this->ticket->dept->email->address ?? config('mail.from.address'));

        return new Envelope(
            from: new Address($fromAddress, $this->staff->displayName()),
            replyTo: [new Address($fromAddress, $this->staff->displayName())],
            subject: $this->renderedSubject(),
        );
    }

    public function content(): Content
    {
        $rendered = $this->render();

        return new Content(htmlString: $rendered->bodyHtml, textString: $rendered->bodyText);
    }

    public function build(): self
    {
        return $this->withSymfonyMessage(function (Email $message): void {
            $headers = $message->getHeaders();
            $headers->remove('Message-ID');
            $headers->addIdHeader('Message-ID', trim($this->messageId, '<>'));
            if ($this->inReplyTo !== null && $this->inReplyTo !== '') {
                $headers->remove('In-Reply-To');
                $headers->addIdHeader('In-Reply-To', trim($this->inReplyTo, '<>'));
            }
            if ($this->references !== '') {
                $headers->remove('References');
                $headers->addTextHeader('References', $this->references);
            }
            $headers->addTextHeader(EventClassHeader::NAME, EventClassHeader::CLOSE_NOTIFY);
        });
    }

    public function failed(Throwable $exception): void
    {
        app(ActionLogger::class)->record(
            staff: $this->staff,
            action: 'close.mail_failed',
            outcome: 'failed',
            httpStatus: 0,
            ticketId: $this->ticket->ticket_id,
            beforeState: [
                'error_class' => $exception::class,
                'entry_id' => $this->entry->id,
                'message_id' => $this->messageId,
            ],
        );
    }

    private function renderedSubject(): string
    {
        return $this->render()->subject;
    }

    private function render(): RenderedMail
    {
        return app(LegacyTemplateRenderer::class)
            ->render('note.alert', $this->ticket, $this->entry, null, bodyOverride: $this->comments);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Mail/CloseNotifyMailTest.php`

Expected: PASS — 3 passed.

- [ ] **Step 5: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Mail/CloseNotifyMail.php tests/Unit/Mail/CloseNotifyMailTest.php
git commit -m "feat(mail): add CloseNotifyMail using legacy note.alert template"
```

---

## Task 7: Add `tickets.post-reply` permission + `TicketActionPolicy::postReply`

**Files:**
- Create: `tests/Unit/Policies/TicketActionPolicyPostReplyTest.php`
- Modify: `app/Policies/TicketActionPolicy.php`
- Modify: permission seeder (path TBD — see step 1)

- [ ] **Step 1: Locate the permission seeder**

Run: `grep -rn "tickets.post-note" database/seeders/`

Open the file that contains the existing `tickets.post-note` entry and note its path. The new permission `tickets.post-reply` must be added to the same place.

- [ ] **Step 2: Write the failing policy test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Staff;
use App\Models\Ticket;
use App\Policies\TicketActionPolicy;
use App\Services\DepartmentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class TicketActionPolicyPostReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'tickets.post-reply', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_post_reply_allows_staff_with_permission_and_dept_access(): void
    {
        $ticket = Ticket::factory()->create(['dept_id' => 5]);
        $staff = Staff::factory()->create();
        $staff->givePermissionTo('tickets.post-reply');

        $deptService = Mockery::mock(DepartmentPermissionService::class);
        $deptService->shouldReceive('hasAccessToDepartment')->with($staff, 5)->andReturnTrue();

        $policy = new TicketActionPolicy($deptService);
        $this->assertTrue($policy->postReply($staff, $ticket));
    }

    public function test_post_reply_denies_staff_without_permission(): void
    {
        $ticket = Ticket::factory()->create(['dept_id' => 5]);
        $staff = Staff::factory()->create();

        $deptService = Mockery::mock(DepartmentPermissionService::class);
        $deptService->shouldReceive('hasAccessToDepartment')->andReturnTrue();

        $policy = new TicketActionPolicy($deptService);
        $this->assertFalse($policy->postReply($staff, $ticket));
    }

    public function test_post_reply_denies_staff_without_dept_access(): void
    {
        $ticket = Ticket::factory()->create(['dept_id' => 5]);
        $staff = Staff::factory()->create();
        $staff->givePermissionTo('tickets.post-reply');

        $deptService = Mockery::mock(DepartmentPermissionService::class);
        $deptService->shouldReceive('hasAccessToDepartment')->with($staff, 5)->andReturnFalse();

        $policy = new TicketActionPolicy($deptService);
        $this->assertFalse($policy->postReply($staff, $ticket));
    }
}
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Policies/TicketActionPolicyPostReplyTest.php`

Expected: FAIL — method `postReply` not defined on `TicketActionPolicy`.

- [ ] **Step 4: Add the policy method**

In `app/Policies/TicketActionPolicy.php`, add after the `postNote` method:

```php
    public function postReply(Staff $staff, Ticket $ticket): bool
    {
        return $staff->can('tickets.post-reply') && $this->deptService->hasAccessToDepartment($staff, $ticket->dept_id);
    }
```

- [ ] **Step 5: Add the permission to the seeder**

In the seeder file located in Step 1, alongside the entry for `tickets.post-note`, add a line creating `tickets.post-reply` with `guard_name: 'staff'`. Match the existing entry's exact shape (likely `Permission::firstOrCreate(['name' => 'tickets.post-reply', 'guard_name' => 'staff'])` or a config-array equivalent).

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Policies/TicketActionPolicyPostReplyTest.php`

Expected: PASS — 3 passed.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Policies/TicketActionPolicy.php database/seeders/ tests/Unit/Policies/TicketActionPolicyPostReplyTest.php
git commit -m "feat(authz): add tickets.post-reply permission and policy method"
```

---

## Task 8: `ReplyPostingService`

Orchestrates: lock + concurrency check → write thread_entry → MessageId + email_info + threading headers → optional status transition → audit/search/cache → queue `StaffReplyMail`.

**Files:**
- Create: `tests/Unit/Services/Scp/Tickets/ReplyPostingServiceTest.php`
- Create: `app/Services/Scp/Tickets/ReplyPostingService.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Mail\StaffReplyMail;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\Scp\Tickets\ReplyPostingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ReplyPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // Provision thread_entry_email if it doesn't exist (sqlite test connection)
        if (! Schema::connection('legacy')->hasTable('thread_entry_email')) {
            Schema::connection('legacy')->create('thread_entry_email', function (Blueprint $table): void {
                $table->unsignedInteger('id')->autoIncrement();
                $table->unsignedInteger('thread_entry_id');
                $table->unsignedInteger('email_id')->nullable();
                $table->string('mid', 255);
                $table->text('headers')->nullable();
                $table->index('mid');
                $table->index('thread_entry_id');
            });
        }

        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([
            ['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default'],
        ]);
        DB::connection('legacy')->table('email_template')->insertOrIgnore([
            ['tpl_id' => 1, 'code_name' => 'ticket.reply', 'subject' => 'Re: %{ticket.subject}', 'body' => '<p>%{response}</p>'],
        ]);
    }

    public function test_writes_thread_entry_email_info_and_queues_mail(): void
    {
        [$ticket, $thread, $staff] = $this->seed();

        $service = app(ReplyPostingService::class);
        $entry = $service->post(
            ticket: $ticket,
            thread: $thread,
            staff: $staff,
            body: 'My reply',
            format: 'html',
            signatureChoice: 'none',
            replyStatusId: null,
            expectedUpdated: (string) $ticket->updated,
        );

        $this->assertSame('R', $entry->type);
        $this->assertSame('My reply', $entry->body);
        $this->assertDatabaseHas('thread_entry_email', [
            'thread_entry_id' => $entry->id,
        ], connection: 'legacy');
        Mail::assertQueued(StaffReplyMail::class, 1);
    }

    public function test_concurrency_check_throws_and_rolls_back(): void
    {
        [$ticket, $thread, $staff] = $this->seed();
        $service = app(ReplyPostingService::class);

        $this->expectException(TicketModifiedConcurrentlyException::class);
        try {
            $service->post(
                ticket: $ticket,
                thread: $thread,
                staff: $staff,
                body: 'My reply',
                format: 'html',
                signatureChoice: 'none',
                replyStatusId: null,
                expectedUpdated: 'wrong-timestamp',
            );
        } finally {
            $this->assertDatabaseMissing('thread_entry', ['thread_id' => $thread->id, 'type' => 'R'], connection: 'legacy');
            Mail::assertNothingQueued();
        }
    }

    public function test_post_with_status_change_transitions_in_same_transaction(): void
    {
        [$ticket, $thread, $staff] = $this->seed();
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 2, 'name' => 'Resolved', 'state' => 'open'],
        ]);

        $service = app(ReplyPostingService::class);
        $entry = $service->post(
            ticket: $ticket,
            thread: $thread,
            staff: $staff,
            body: 'Done',
            format: 'text',
            signatureChoice: 'none',
            replyStatusId: 2,
            expectedUpdated: (string) $ticket->updated,
        );

        $this->assertNotNull($entry);
        $this->assertDatabaseHas('ticket', [
            'ticket_id' => $ticket->ticket_id,
            'status_id' => 2,
        ], connection: 'legacy');
        Mail::assertQueued(StaffReplyMail::class, 1);
    }

    /**
     * @return array{0: Ticket, 1: Thread, 2: Staff}
     */
    private function seed(): array
    {
        $email = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['default_email_id' => $email->id]);
        $email->update(['user_id' => $user->id]);

        $dept = Department::factory()->create(['tpl_id' => 1]);
        $staff = Staff::factory()->create();

        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
        ]);

        $ticket = Ticket::factory()->create([
            'user_id' => $user->id,
            'dept_id' => $dept->id,
            'status_id' => 1,
        ]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        return [$ticket->refresh(), $thread, $staff];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/Scp/Tickets/ReplyPostingServiceTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement the service**

```php
<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Mail\StaffReplyMail;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Services\Scp\Mail\EmailInfoPersister;
use App\Services\Scp\Mail\MessageIdGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class ReplyPostingService
{
    public function __construct(
        private readonly ThreadEventWriter $threadEvents,
        private readonly SearchIndexer $searchIndexer,
        private readonly TicketCacheUpdater $ticketCacheUpdater,
        private readonly StatusTransitionService $statusTransitions,
        private readonly MessageIdGenerator $messageIds,
        private readonly EmailInfoPersister $emailInfo,
    ) {}

    public function post(
        Ticket $ticket,
        Thread $thread,
        Staff $staff,
        string $body,
        string $format,
        string $signatureChoice,
        ?int $replyStatusId,
        string $expectedUpdated,
    ): ThreadEntry {
        return DB::connection('legacy')->transaction(function () use ($ticket, $thread, $staff, $body, $format, $signatureChoice, $replyStatusId, $expectedUpdated): ThreadEntry {
            $current = $this->lockCurrentTicket($ticket);

            if ((string) $current->updated !== $expectedUpdated) {
                throw new TicketModifiedConcurrentlyException($current->ticket_id, (string) $current->updated);
            }

            $entry = ThreadEntry::on('legacy')->create([
                'thread_id' => $thread->id,
                'staff_id' => $staff->staff_id,
                'type' => 'R',
                'format' => $format,
                'body' => $body,
                'title' => '',
                'poster' => $staff->displayName(),
                'created' => now(),
                'updated' => now(),
            ]);

            $messageId = $this->messageIds->next($current, $entry);
            $inReplyTo = $this->messageIds->inReplyTo($thread);
            $references = $this->messageIds->references($thread);

            $this->emailInfo->record(
                entry: $entry,
                messageId: $messageId,
                headers: $this->buildHeadersBlock($messageId, $inReplyTo, $references),
                emailId: $this->resolveDeptEmailId($current),
            );

            if ($replyStatusId !== null) {
                $this->statusTransitions->transition(
                    ticket: $current,
                    thread: $thread,
                    caller: $staff,
                    targetStatusId: $replyStatusId,
                    comments: null,
                    expectedUpdated: $expectedUpdated,
                    notifyUser: false,
                );
            }

            $this->threadEvents->record($thread, 'created', $entry->id, $staff, ['entry_id' => $entry->id]);
            $this->searchIndexer->index('THE', $entry->id, '', (string) $entry->body);
            $this->ticketCacheUpdater->touch($current, $thread);

            Mail::to($this->customerEmailFor($current))->queue(new StaffReplyMail(
                ticket: $current,
                entry: $entry,
                staff: $staff,
                signatureChoice: $signatureChoice,
                messageId: $messageId,
                inReplyTo: $inReplyTo,
                references: $references,
            ));

            return $entry;
        });
    }

    private function lockCurrentTicket(Ticket $ticket): Ticket
    {
        $query = Ticket::on('legacy')
            ->withoutGlobalScopes()
            ->whereKey($ticket->ticket_id);

        if (DB::connection('legacy')->getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        return $query->findOrFail($ticket->ticket_id);
    }

    private function buildHeadersBlock(string $messageId, ?string $inReplyTo, string $references): string
    {
        $lines = ['Message-ID: ' . $messageId];
        if ($inReplyTo !== null && $inReplyTo !== '') {
            $lines[] = 'In-Reply-To: ' . $inReplyTo;
        }
        if ($references !== '') {
            $lines[] = 'References: ' . $references;
        }
        return implode("\r\n", $lines);
    }

    private function resolveDeptEmailId(Ticket $ticket): ?int
    {
        return $ticket->dept?->email?->email_id;
    }

    private function customerEmailFor(Ticket $ticket): string
    {
        return (string) ($ticket->user?->defaultEmail?->address ?? '');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/Scp/Tickets/ReplyPostingServiceTest.php`

Expected: PASS — 3 passed.

- [ ] **Step 5: Bind the service as a singleton**

In `app/Providers/AppServiceProvider.php` inside `register()`, near the existing `OutboundMailDispatcher` binding, add:

```php
$this->app->singleton(\App\Services\Scp\Mail\LegacyTemplateRenderer::class);
$this->app->singleton(\App\Services\Scp\Mail\MessageIdGenerator::class);
$this->app->singleton(\App\Services\Scp\Mail\EmailInfoPersister::class);
$this->app->singleton(\App\Services\Scp\Tickets\ReplyPostingService::class);
```

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Scp/Tickets/ReplyPostingService.php app/Providers/AppServiceProvider.php tests/Unit/Services/Scp/Tickets/ReplyPostingServiceTest.php
git commit -m "feat(tickets): add ReplyPostingService orchestrating reply transaction"
```

> Note: this task adds a new parameter `notifyUser` to `StatusTransitionService::transition`. Task 9 extends that service. If running tasks strictly in order, Task 9 must follow immediately or the service breaks. Implementers should treat Tasks 8 and 9 as a unit, but they're kept separate for review clarity.

---

## Task 9: Extend `StatusTransitionService` for `notify_user`

Adds the `notifyUser` parameter (already referenced by `ReplyPostingService` in Task 8) and the queue dispatch for `CloseNotifyMail`.

**Files:**
- Modify: `app/Services/Scp/Tickets/StatusTransitionService.php`
- Modify: `tests/Unit/Services/Scp/Tickets/StatusTransitionServiceTest.php` (extend)

- [ ] **Step 1: Append failing tests for the new branch**

Open `tests/Unit/Services/Scp/Tickets/StatusTransitionServiceTest.php` and append the following test methods (assume existing `setUp` is reusable; if not, mirror its schema setup including `thread_entry_email`):

```php
    public function test_notify_user_true_with_comments_queues_close_notify_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\DB::connection('legacy')->table('email_template_group')->insertOrIgnore([
            ['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default'],
        ]);
        \Illuminate\Support\Facades\DB::connection('legacy')->table('email_template')->insertOrIgnore([
            ['tpl_id' => 1, 'code_name' => 'note.alert', 'subject' => 'X', 'body' => '<p>%{comments}</p>'],
        ]);

        if (! \Illuminate\Support\Facades\Schema::connection('legacy')->hasTable('thread_entry_email')) {
            \Illuminate\Support\Facades\Schema::connection('legacy')->create('thread_entry_email', function (\Illuminate\Database\Schema\Blueprint $t): void {
                $t->unsignedInteger('id')->autoIncrement();
                $t->unsignedInteger('thread_entry_id');
                $t->unsignedInteger('email_id')->nullable();
                $t->string('mid', 255);
                $t->text('headers')->nullable();
            });
        }

        [$ticket, $thread, $staff] = $this->seedTicketReadyForClose(); // existing helper in the test

        $this->service->transition(
            ticket: $ticket,
            thread: $thread,
            caller: $staff,
            targetStatusId: 2, // closed
            comments: 'Resolving the ticket.',
            expectedUpdated: (string) $ticket->updated,
            notifyUser: true,
        );

        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\CloseNotifyMail::class, 1);
    }

    public function test_notify_user_false_does_not_queue_mail(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        [$ticket, $thread, $staff] = $this->seedTicketReadyForClose();

        $this->service->transition(
            ticket: $ticket,
            thread: $thread,
            caller: $staff,
            targetStatusId: 2,
            comments: 'Quietly closing.',
            expectedUpdated: (string) $ticket->updated,
            notifyUser: false,
        );

        \Illuminate\Support\Facades\Mail::assertNothingQueued();
    }
```

If `seedTicketReadyForClose` doesn't exist in the test class, add it. It should create a ticket with status 1 (open) and a closeable status 2 (closed) in `ticket_status`, plus the standard thread + user + dept fixtures.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Services/Scp/Tickets/StatusTransitionServiceTest.php`

Expected: FAIL — `notifyUser` parameter not defined on `transition()`.

- [ ] **Step 3: Extend the service**

In `app/Services/Scp/Tickets/StatusTransitionService.php`:

1. Update the `ALLOWED_STATES` constant to include `'closed'`:

```php
private const ALLOWED_STATES = ['open', 'onhold', 'closed'];
```

2. Inject the new dependencies in the constructor:

```php
public function __construct(
    private readonly ThreadEventWriter $threadEvents,
    private readonly NotePostingService $notes,
    private readonly TicketCacheUpdater $ticketCacheUpdater,
    private readonly \App\Services\Scp\Mail\MessageIdGenerator $messageIds,
    private readonly \App\Services\Scp\Mail\EmailInfoPersister $emailInfo,
) {}
```

3. Update the `transition` signature and body:

```php
public function transition(
    Ticket $ticket,
    Thread $thread,
    Staff $caller,
    int $targetStatusId,
    ?string $comments,
    string $expectedUpdated,
    bool $notifyUser = false,
): void {
    DB::connection('legacy')->transaction(function () use ($ticket, $thread, $caller, $targetStatusId, $comments, $expectedUpdated, $notifyUser): void {
        $currentTicket = $this->lockCurrentTicket($ticket);

        if ((string) $currentTicket->updated !== $expectedUpdated) {
            throw new TicketModifiedConcurrentlyException($currentTicket->ticket_id, (string) $currentTicket->updated);
        }

        /** @var TicketStatus $from */
        $from = TicketStatus::on('legacy')->findOrFail($currentTicket->status_id);
        /** @var TicketStatus $to */
        $to = TicketStatus::on('legacy')->findOrFail($targetStatusId);

        if (! $this->transitionAllowed((string) $from->state, (string) $to->state)) {
            throw new ForbiddenStatusTransition((string) $from->state, (string) $to->state);
        }

        $currentTicket->forceFill(['status_id' => $to->getKey()])->save();

        $noteEntry = null;
        if ($comments !== null && trim($comments) !== '') {
            $noteEntry = $this->notes->post(
                ticket: $currentTicket,
                thread: $thread,
                staff: $caller,
                body: $comments,
                format: 'text',
                expectedUpdated: $expectedUpdated,
            );
        }

        $this->threadEvents->record(
            thread: $thread,
            eventName: 'status',
            entryId: null,
            staff: $caller,
            data: ['from' => $this->statusData($from), 'to' => $this->statusData($to)],
        );

        $this->ticketCacheUpdater->touch($currentTicket, $thread);

        if ($notifyUser && $noteEntry !== null && $to->state === 'closed') {
            $messageId = $this->messageIds->next($currentTicket, $noteEntry);
            $inReplyTo = $this->messageIds->inReplyTo($thread);
            $references = $this->messageIds->references($thread);

            $this->emailInfo->record(
                entry: $noteEntry,
                messageId: $messageId,
                headers: 'Message-ID: ' . $messageId,
                emailId: $currentTicket->dept?->email?->email_id,
            );

            \Illuminate\Support\Facades\Mail::to((string) ($currentTicket->user?->defaultEmail?->address ?? ''))
                ->queue(new \App\Mail\CloseNotifyMail(
                    ticket: $currentTicket,
                    entry: $noteEntry,
                    staff: $caller,
                    comments: (string) $comments,
                    messageId: $messageId,
                    inReplyTo: $inReplyTo,
                    references: $references,
                ));
        }
    });
}
```

Note: `NotePostingService::post` currently returns `ThreadEntry`. Verify by reading `app/Services/Scp/Tickets/NotePostingService.php:29-54`; if it already returns `ThreadEntry`, no change needed (it does, per the existing return type).

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Services/Scp/Tickets/StatusTransitionServiceTest.php`

Expected: PASS — all original tests plus the 2 new ones.

- [ ] **Step 5: Run the prior task's test to ensure nothing broke**

Run: `php artisan test --compact tests/Unit/Services/Scp/Tickets/ReplyPostingServiceTest.php`

Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Scp/Tickets/StatusTransitionService.php tests/Unit/Services/Scp/Tickets/StatusTransitionServiceTest.php
git commit -m "feat(tickets): extend StatusTransitionService with notify_user + CloseNotifyMail dispatch"
```

---

## Task 10: `ReplyController` + route + Inertia shared signature props

Wires HTTP layer to `ReplyPostingService`. Adds the `default_signature_type` Inertia shared prop so the React composer can default correctly.

**Files:**
- Create: `app/Http/Controllers/Scp/Tickets/ReplyController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (expose `staff.default_signature_type` + signature option availability)
- Create: `tests/Feature/Scp/Tickets/ReplyControllerTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Mail\StaffReplyMail;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ReplyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Permission::firstOrCreate(['name' => 'tickets.post-reply', 'guard_name' => 'staff']);
        Permission::firstOrCreate(['name' => 'tickets.set-status', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! Schema::connection('legacy')->hasTable('thread_entry_email')) {
            Schema::connection('legacy')->create('thread_entry_email', function (Blueprint $t): void {
                $t->unsignedInteger('id')->autoIncrement();
                $t->unsignedInteger('thread_entry_id');
                $t->unsignedInteger('email_id')->nullable();
                $t->string('mid', 255);
                $t->text('headers')->nullable();
            });
        }
        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([
            ['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default'],
        ]);
        DB::connection('legacy')->table('email_template')->insertOrIgnore([
            ['tpl_id' => 1, 'code_name' => 'ticket.reply', 'subject' => 'Re: %{ticket.subject}', 'body' => '<p>%{response}</p>'],
        ]);
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
        ]);
    }

    public function test_post_replies_writes_thread_entry_and_queues_mail_when_owner_laravel(): void
    {
        config(['mail.event_class_owner.reply' => 'laravel']);
        [$staff, $ticket] = $this->seed();

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.replies.store', $ticket), [
                'body' => 'My reply',
                'format' => 'html',
                'signature' => 'none',
                'expected_updated' => (string) $ticket->updated,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('thread_entry', [
            'thread_id' => $ticket->thread->id,
            'type' => 'R',
            'body' => 'My reply',
        ], connection: 'legacy');
        Mail::assertQueued(StaffReplyMail::class, 1);
    }

    public function test_post_replies_returns_403_when_owner_legacy(): void
    {
        config(['mail.event_class_owner.reply' => 'legacy']);
        [$staff, $ticket] = $this->seed();

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.replies.store', $ticket), [
                'body' => 'My reply',
                'format' => 'html',
                'signature' => 'none',
                'expected_updated' => (string) $ticket->updated,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('thread_entry', ['type' => 'R'], connection: 'legacy');
        Mail::assertNothingQueued();
    }

    public function test_returns_409_when_expected_updated_mismatches(): void
    {
        config(['mail.event_class_owner.reply' => 'laravel']);
        [$staff, $ticket] = $this->seed();

        $this->actingAs($staff, 'staff')
            ->postJson(route('scp.tickets.replies.store', $ticket), [
                'body' => 'My reply',
                'format' => 'html',
                'signature' => 'none',
                'expected_updated' => 'stale-timestamp',
            ])
            ->assertStatus(409);
    }

    public function test_returns_422_when_signature_option_unavailable_to_staff(): void
    {
        config(['mail.event_class_owner.reply' => 'laravel']);
        [$staff, $ticket] = $this->seed(staffSignature: null);

        $this->actingAs($staff, 'staff')
            ->postJson(route('scp.tickets.replies.store', $ticket), [
                'body' => 'My reply',
                'format' => 'html',
                'signature' => 'mine',
                'expected_updated' => (string) $ticket->updated,
            ])
            ->assertStatus(422);
    }

    /**
     * @return array{0: Staff, 1: Ticket}
     */
    private function seed(?string $staffSignature = '<p>--<br>Bob</p>'): array
    {
        $email = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['default_email_id' => $email->id]);
        $email->update(['user_id' => $user->id]);

        $dept = Department::factory()->create(['tpl_id' => 1]);
        $staff = Staff::factory()->create(['signature' => $staffSignature]);
        $staff->givePermissionTo('tickets.post-reply');

        $ticket = Ticket::factory()->create([
            'user_id' => $user->id,
            'dept_id' => $dept->id,
            'status_id' => 1,
        ]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        return [$staff, $ticket->refresh()];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Scp/Tickets/ReplyControllerTest.php`

Expected: FAIL — route or controller not found.

- [ ] **Step 3: Create the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Exceptions\ForbiddenStatusTransition;
use App\Exceptions\TicketModifiedConcurrentlyException;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\Tickets\ActionLogger;
use App\Services\Scp\Tickets\DraftService;
use App\Services\Scp\Tickets\ReplyPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ReplyController extends Controller
{
    public function __construct(
        private readonly ReplyPostingService $replies,
        private readonly DraftService $drafts,
        private readonly ActionLogger $audit,
    ) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
    {
        $this->authorize('postReply', $ticket);

        if ((string) config('mail.event_class_owner.reply') !== 'laravel') {
            abort(403, 'Reply mail is owned by legacy.');
        }

        $staff = $request->user('staff');

        $data = $request->validate([
            'body' => 'required|string|max:65535',
            'format' => 'required|in:html,text',
            'signature' => ['required', Rule::in(['none', 'mine', 'dept'])],
            'reply_status_id' => 'nullable|integer|exists:legacy.ticket_status,id',
            'expected_updated' => 'required|string',
        ]);

        $this->ensureSignatureAvailable($staff, $ticket, $data['signature']);

        $thread = $ticket->thread()->firstOrFail();

        try {
            $this->replies->post(
                ticket: $ticket,
                thread: $thread,
                staff: $staff,
                body: $data['body'],
                format: $data['format'],
                signatureChoice: $data['signature'],
                replyStatusId: $data['reply_status_id'] ?? null,
                expectedUpdated: $data['expected_updated'],
            );
        } catch (TicketModifiedConcurrentlyException) {
            $this->audit->recordFailure($staff, 'reply.posted', 409, $ticket->ticket_id, TicketModifiedConcurrentlyException::class, $request);
            return response()->json(['message' => 'Ticket was modified'], 409);
        } catch (ForbiddenStatusTransition) {
            $this->audit->recordFailure($staff, 'reply.posted', 422, $ticket->ticket_id, ForbiddenStatusTransition::class, $request);
            return response()->json(['message' => 'Forbidden status transition'], 422);
        }

        $this->drafts->discard($staff, "ticket.reply.{$ticket->ticket_id}");

        $this->audit->record(
            staff: $staff,
            action: 'reply.posted',
            outcome: 'success',
            httpStatus: 302,
            ticketId: $ticket->ticket_id,
            request: $request,
        );

        return back()->with('success', 'Reply sent.');
    }

    private function ensureSignatureAvailable($staff, Ticket $ticket, string $choice): void
    {
        if ($choice === 'mine' && (string) ($staff->signature ?? '') === '') {
            abort(response()->json(['errors' => ['signature' => ['Signature not configured for staff member.']]], 422));
        }
        if ($choice === 'dept' && (string) ($ticket->dept?->signature ?? '') === '') {
            abort(response()->json(['errors' => ['signature' => ['Department signature unavailable.']]], 422));
        }
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, immediately after the `tickets.notes.store` route (around line 99 in the SCP group), add:

```php
        Route::post('/tickets/{ticket}/replies', [\App\Http\Controllers\Scp\Tickets\ReplyController::class, 'store'])
            ->middleware('scp.ticket-lock')
            ->name('tickets.replies.store');
```

(Note: route name should match the existing SCP group's prefix convention. If the group already uses `name('scp.')`, the route name `tickets.replies.store` resolves as `scp.tickets.replies.store`. Verify by inspecting the route group around line 90 in `routes/web.php`.)

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Scp/Tickets/ReplyControllerTest.php`

Expected: PASS — 4 passed.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Scp/Tickets/ReplyController.php routes/web.php tests/Feature/Scp/Tickets/ReplyControllerTest.php
git commit -m "feat(scp): add ReplyController and POST /scp/tickets/{ticket}/replies route"
```

---

## Task 11: Extend `StatusController` for `notify_user`

**Files:**
- Modify: `app/Http/Controllers/Scp/Tickets/StatusController.php`
- Create: `tests/Feature/Scp/Tickets/CloseNotifyTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Mail\CloseNotifyMail;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class CloseNotifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        Permission::firstOrCreate(['name' => 'tickets.set-status', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! Schema::connection('legacy')->hasTable('thread_entry_email')) {
            Schema::connection('legacy')->create('thread_entry_email', function (Blueprint $t): void {
                $t->unsignedInteger('id')->autoIncrement();
                $t->unsignedInteger('thread_entry_id');
                $t->unsignedInteger('email_id')->nullable();
                $t->string('mid', 255);
                $t->text('headers')->nullable();
            });
        }
        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([
            ['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default'],
        ]);
        DB::connection('legacy')->table('email_template')->insertOrIgnore([
            ['tpl_id' => 1, 'code_name' => 'note.alert', 'subject' => 'Ticket update', 'body' => '<p>%{comments}</p>'],
        ]);
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
            ['id' => 2, 'name' => 'Closed', 'state' => 'closed'],
        ]);
    }

    public function test_notify_user_true_with_comments_queues_close_notify_mail(): void
    {
        config(['mail.event_class_owner.close_notify' => 'laravel']);
        [$staff, $ticket] = $this->seed();

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.status.store', $ticket), [
                'status_id' => 2,
                'comments' => 'Closing the ticket.',
                'notify_user' => true,
                'expected_updated' => (string) $ticket->updated,
            ])
            ->assertRedirect();

        Mail::assertQueued(CloseNotifyMail::class, 1);
    }

    public function test_notify_user_true_with_empty_comments_returns_422(): void
    {
        config(['mail.event_class_owner.close_notify' => 'laravel']);
        [$staff, $ticket] = $this->seed();

        $this->actingAs($staff, 'staff')
            ->postJson(route('scp.tickets.status.store', $ticket), [
                'status_id' => 2,
                'comments' => '',
                'notify_user' => true,
                'expected_updated' => (string) $ticket->updated,
            ])
            ->assertStatus(422);

        Mail::assertNothingQueued();
    }

    public function test_notify_user_true_with_non_closed_status_returns_422(): void
    {
        config(['mail.event_class_owner.close_notify' => 'laravel']);
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
        ]);
        [$staff, $ticket] = $this->seed();

        $this->actingAs($staff, 'staff')
            ->postJson(route('scp.tickets.status.store', $ticket), [
                'status_id' => 3,
                'comments' => 'Pausing',
                'notify_user' => true,
                'expected_updated' => (string) $ticket->updated,
            ])
            ->assertStatus(422);
    }

    public function test_notify_user_false_does_not_queue_mail(): void
    {
        config(['mail.event_class_owner.close_notify' => 'laravel']);
        [$staff, $ticket] = $this->seed();

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.status.store', $ticket), [
                'status_id' => 2,
                'comments' => 'Quietly closing.',
                'notify_user' => false,
                'expected_updated' => (string) $ticket->updated,
            ])
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_notify_user_true_with_legacy_ownership_returns_403(): void
    {
        config(['mail.event_class_owner.close_notify' => 'legacy']);
        [$staff, $ticket] = $this->seed();

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.status.store', $ticket), [
                'status_id' => 2,
                'comments' => 'Closing',
                'notify_user' => true,
                'expected_updated' => (string) $ticket->updated,
            ])
            ->assertStatus(403);
    }

    /**
     * @return array{0: Staff, 1: Ticket}
     */
    private function seed(): array
    {
        $email = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['default_email_id' => $email->id]);
        $email->update(['user_id' => $user->id]);

        $dept = Department::factory()->create(['tpl_id' => 1]);
        $staff = Staff::factory()->create();
        $staff->givePermissionTo('tickets.set-status');

        $ticket = Ticket::factory()->create([
            'user_id' => $user->id,
            'dept_id' => $dept->id,
            'status_id' => 1,
        ]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        return [$staff, $ticket->refresh()];
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Scp/Tickets/CloseNotifyTest.php`

Expected: FAIL — `notify_user` parameter not accepted by controller; tests assert on behavior not yet implemented.

- [ ] **Step 3: Extend the controller**

In `app/Http/Controllers/Scp/Tickets/StatusController.php`, update the `store` method:

```php
public function store(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
{
    $this->authorize('setStatus', $ticket);

    $data = $request->validate([
        'status_id' => 'required|integer|exists:legacy.ticket_status,id',
        'comments' => 'nullable|string|max:65535',
        'notify_user' => 'sometimes|boolean',
        'expected_updated' => 'required|string',
    ]);

    $notifyUser = (bool) ($data['notify_user'] ?? false);

    if ($notifyUser) {
        if ((string) config('mail.event_class_owner.close_notify') !== 'laravel') {
            abort(403, 'Close-notify mail is owned by legacy.');
        }

        if (trim((string) ($data['comments'] ?? '')) === '') {
            return response()->json([
                'message' => 'Comments required when notifying user.',
                'errors' => ['comments' => ['A note is required when notifying the customer.']],
            ], 422);
        }

        $targetState = (string) \DB::connection('legacy')->table('ticket_status')
            ->where('id', $data['status_id'])
            ->value('state');
        if ($targetState !== 'closed') {
            return response()->json([
                'message' => 'notify_user only valid when transitioning to a closed status.',
                'errors' => ['notify_user' => ['Only allowed when closing.']],
            ], 422);
        }
    }

    $staff = $request->user('staff');
    $thread = $ticket->thread()->firstOrFail();

    try {
        $this->transitions->transition(
            ticket: $ticket,
            thread: $thread,
            caller: $staff,
            targetStatusId: $data['status_id'],
            comments: $data['comments'] ?? null,
            expectedUpdated: $data['expected_updated'],
            notifyUser: $notifyUser,
        );

        $this->logger->record(
            staff: $staff,
            action: 'status.changed',
            outcome: 'success',
            httpStatus: 302,
            ticketId: $ticket->ticket_id,
            beforeState: ['notify_user' => $notifyUser, 'mail_queued' => $notifyUser],
            request: $request,
        );

        return back();
    } catch (ForbiddenStatusTransition) {
        $this->logger->recordFailure(
            staff: $staff,
            action: 'status.changed',
            httpStatus: 422,
            ticketId: $ticket->ticket_id,
            errorClass: ForbiddenStatusTransition::class,
            request: $request,
        );
        return response()->json(['message' => 'Forbidden status transition'], 422);
    } catch (TicketModifiedConcurrentlyException) {
        $this->logger->recordFailure(
            staff: $staff,
            action: 'status.changed',
            httpStatus: 409,
            ticketId: $ticket->ticket_id,
            errorClass: TicketModifiedConcurrentlyException::class,
            request: $request,
        );
        return response()->json(['message' => 'Ticket was modified'], 409);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Scp/Tickets/CloseNotifyTest.php`

Expected: PASS — 5 passed.

- [ ] **Step 5: Run all touched test suites for regression**

Run: `php artisan test --compact tests/Feature/Scp/Tickets/ tests/Unit/Services/Scp/Tickets/ tests/Unit/Mail/`

Expected: PASS — full suite green.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Scp/Tickets/StatusController.php tests/Feature/Scp/Tickets/CloseNotifyTest.php
git commit -m "feat(scp): extend StatusController with notify_user for close-with-notify"
```

---

## Task 12: `ReplyComposer.tsx` React component

Pure component, no test (project doesn't ship JS tests yet — browser smoke in Task 15 covers it).

**Files:**
- Create: `resources/js/components/tickets/ReplyComposer.tsx`

- [ ] **Step 1: Create the component**

```tsx
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { ChannelPill, FromPill, IconBtn } from './TicketDetailComponents';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    TextBoldIcon,
    SmileIcon,
    Attachment01Icon,
    Mic01Icon as Microphone01Icon,
    RefreshIcon,
} from '@hugeicons/core-free-icons';

type SignatureChoice = 'none' | 'mine' | 'dept';

interface StatusOption {
    id: number;
    name: string;
    state: string;
}

interface ReplyComposerProps {
    ticketId: number;
    expectedUpdated: string;
    statusOptions: StatusOption[];
    onSuccess?: () => void;
}

interface SharedProps {
    mail_event_owner: Record<string, string>;
    staff: {
        default_signature_type?: SignatureChoice;
        has_signature?: boolean;
    };
    ticket?: {
        dept_signature_available?: boolean;
    };
    [key: string]: unknown;
}

export function ReplyComposer({ ticketId, expectedUpdated, statusOptions, onSuccess }: ReplyComposerProps) {
    const { props } = usePage<SharedProps>();
    const ownerLaravel = props.mail_event_owner?.reply === 'laravel';

    if (!ownerLaravel) {
        return null;
    }

    const defaultSig: SignatureChoice = props.staff?.default_signature_type ?? 'none';
    const canMine = props.staff?.has_signature ?? false;
    const canDept = props.ticket?.dept_signature_available ?? false;

    const [body, setBody] = useState('');
    const [signature, setSignature] = useState<SignatureChoice>(defaultSig);
    const [replyStatusId, setReplyStatusId] = useState<number | ''>('');
    const [submitting, setSubmitting] = useState(false);

    const submit = () => {
        if (!body.trim()) return;
        setSubmitting(true);
        router.post(`/scp/tickets/${ticketId}/replies`, {
            body,
            format: 'text',
            signature,
            reply_status_id: replyStatusId === '' ? null : Number(replyStatusId),
            expected_updated: expectedUpdated,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setBody('');
                onSuccess?.();
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const submitLabel = replyStatusId === ''
        ? 'Reply'
        : `Reply and ${statusOptions.find(s => s.id === Number(replyStatusId))?.name ?? 'change status'}`;

    return (
        <div className="shrink-0 border-t border-[#E2E0D8] bg-white px-8 py-4">
            <div className="rounded-lg border border-[#E2E0D8] bg-white p-4 shadow-[0_-2px_8px_rgba(0,0,0,0.03)]">
                <div className="mb-2.5 flex items-center gap-2.5">
                    <span className="text-xs text-[#71717A]">Via</span>
                    <ChannelPill channel="Email" />
                    <span className="text-xs text-[#71717A]">From</span>
                    <FromPill from="Reply to Customer" />
                    <div className="ml-auto flex items-center gap-2">
                        <select
                            value={signature}
                            onChange={e => setSignature(e.target.value as SignatureChoice)}
                            className="rounded border border-[#E2E0D8] px-2 py-1 text-xs"
                            data-testid="reply-signature-select"
                        >
                            <option value="none">No signature</option>
                            {canMine && <option value="mine">My signature</option>}
                            {canDept && <option value="dept">Dept signature</option>}
                        </select>
                        <select
                            value={replyStatusId}
                            onChange={e => setReplyStatusId(e.target.value === '' ? '' : Number(e.target.value))}
                            className="rounded border border-[#E2E0D8] px-2 py-1 text-xs"
                            data-testid="reply-status-select"
                        >
                            <option value="">Keep status</option>
                            {statusOptions.map(opt => (
                                <option key={opt.id} value={opt.id}>{opt.name}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <textarea
                    value={body}
                    onChange={e => setBody(e.target.value)}
                    placeholder="Type your reply to the customer..."
                    className="w-full min-h-[96px] resize-vertical bg-transparent py-2 font-sans text-sm text-[#18181B] outline-none placeholder:text-[#A1A1AA]"
                    data-testid="reply-body"
                />

                <div className="mt-2 flex items-center justify-between border-t border-[#E2E0D8] pt-2">
                    <div className="flex items-center gap-0.5">
                        {[TextBoldIcon, SmileIcon, Attachment01Icon, Microphone01Icon].map((ic, i) => (
                            <IconBtn key={i} icon={ic} size={28} className="border-none shadow-none" />
                        ))}
                    </div>
                    <button
                        onClick={submit}
                        disabled={submitting || !body.trim()}
                        className="rounded bg-[#18181B] px-4 py-1.5 text-sm font-medium text-white disabled:opacity-50"
                        data-testid="reply-submit"
                    >
                        {submitting ? 'Sending…' : submitLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Verify the build doesn't error**

Run: `npm run build`

Expected: build succeeds without TypeScript errors. If `HugeiconsIcon` is unused, remove the unused import to satisfy the lint rules already in place.

- [ ] **Step 3: Format (skip Pint; no PHP changes) and commit**

```bash
git add resources/js/components/tickets/ReplyComposer.tsx
git commit -m "feat(scp): add ReplyComposer React component gated on mail_event_owner"
```

---

## Task 13: Extend `StatusPicker.tsx` for `notify_user`

**Files:**
- Modify: `resources/js/components/tickets/StatusPicker.tsx`

- [ ] **Step 1: Read the existing component**

Read `resources/js/components/tickets/StatusPicker.tsx` end-to-end to locate (a) the existing `comments` field, (b) the existing submit handler, and (c) how the target status is represented (likely as an ID + state string).

- [ ] **Step 2: Add `notify_user` state + checkbox**

Inside the component:

1. Pull the shared prop:

```tsx
import { usePage } from '@inertiajs/react';
// ...
const { props } = usePage<{ mail_event_owner?: Record<string, string> }>();
const closeNotifyOwnerLaravel = props.mail_event_owner?.close_notify === 'laravel';
```

2. Add state next to the existing `comments`:

```tsx
const [notifyUser, setNotifyUser] = useState(false);
```

3. Render the checkbox immediately below the `comments` textarea, gated on (a) the target status state being `closed` AND (b) ownership being laravel. The exact `targetState` variable name is determined by inspecting the existing component — adapt accordingly:

```tsx
{targetState === 'closed' && closeNotifyOwnerLaravel && (
    <label className="flex items-center gap-2 text-sm" data-testid="notify-user-label">
        <input
            type="checkbox"
            checked={notifyUser}
            onChange={e => setNotifyUser(e.target.checked)}
            data-testid="notify-user-checkbox"
        />
        Notify customer with the comment above
    </label>
)}
```

4. When the checkbox is checked, require `comments` non-empty in the client-side validation block. Add to the existing submit guard:

```tsx
if (notifyUser && comments.trim() === '') {
    alert('A note is required when notifying the customer.');
    return;
}
```

5. Add `notify_user: notifyUser` to the submit payload object.

- [ ] **Step 3: Verify the build**

Run: `npm run build`

Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/tickets/StatusPicker.tsx
git commit -m "feat(scp): extend StatusPicker with notify_user toggle for close-with-notify"
```

---

## Task 14: Wire `ReplyComposer` into `Show.tsx`

**Files:**
- Modify: `resources/js/pages/Scp/Tickets/Show.tsx`

- [ ] **Step 1: Locate where NoteComposer is rendered**

Run: `grep -n "NoteComposer" resources/js/pages/Scp/Tickets/Show.tsx`

Note the line number where `<NoteComposer ... />` appears.

- [ ] **Step 2: Import and render `ReplyComposer`**

At the imports block in `Show.tsx`, add:

```tsx
import { ReplyComposer } from '@/components/tickets/ReplyComposer';
```

Above (or next to) the existing `<NoteComposer ... />` render site, add:

```tsx
<ReplyComposer
    ticketId={ticket.ticket_id}
    expectedUpdated={ticket.updated}
    statusOptions={statusOptions}
    onSuccess={() => router.reload({ only: ['ticket', 'thread'] })}
/>
```

Where `statusOptions` is the existing prop the page already loads for the status picker. If the prop name differs (e.g., `availableStatuses`), use that.

- [ ] **Step 3: Verify the build**

Run: `npm run build`

Expected: build succeeds.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/Scp/Tickets/Show.tsx
git commit -m "feat(scp): render ReplyComposer on ticket detail page"
```

---

## Task 15: Failure-path integration tests

**Files:**
- Create: `tests/Feature/Mail/CustomerReplyThreadingTest.php`
- Create: `tests/Feature/Mail/QueuedMailFailureTest.php`

- [ ] **Step 1: Write the threading test**

Create `tests/Feature/Mail/CustomerReplyThreadingTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\StaffReplyMail;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserEmail;
use App\Services\Scp\Tickets\ReplyPostingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class CustomerReplyThreadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mail.from.address' => 'support@example.test', 'mail.event_class_owner.reply' => 'laravel']);

        if (! Schema::connection('legacy')->hasTable('thread_entry_email')) {
            Schema::connection('legacy')->create('thread_entry_email', function (Blueprint $t): void {
                $t->unsignedInteger('id')->autoIncrement();
                $t->unsignedInteger('thread_entry_id');
                $t->unsignedInteger('email_id')->nullable();
                $t->string('mid', 255);
                $t->text('headers')->nullable();
            });
        }
        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default']]);
        DB::connection('legacy')->table('email_template')->insertOrIgnore([
            ['tpl_id' => 1, 'code_name' => 'ticket.reply', 'subject' => 'Re: %{ticket.subject}', 'body' => '<p>%{response}</p>'],
        ]);
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([['id' => 1, 'name' => 'Open', 'state' => 'open']]);
    }

    public function test_outbound_reply_persists_mid_in_thread_entry_email(): void
    {
        $email = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['default_email_id' => $email->id]);
        $email->update(['user_id' => $user->id]);
        $dept = Department::factory()->create(['tpl_id' => 1]);
        $staff = Staff::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'dept_id' => $dept->id, 'status_id' => 1]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        $entry = app(ReplyPostingService::class)->post(
            ticket: $ticket->refresh(),
            thread: $thread,
            staff: $staff,
            body: 'Reply',
            format: 'text',
            signatureChoice: 'none',
            replyStatusId: null,
            expectedUpdated: (string) $ticket->updated,
        );

        $row = DB::connection('legacy')->table('thread_entry_email')->where('thread_entry_id', $entry->id)->first();
        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression('/^<L-\d+-\d+-[a-f0-9]{16}@example\.test>$/', $row->mid);
    }

    public function test_in_reply_to_links_to_most_recent_customer_message(): void
    {
        $email = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['default_email_id' => $email->id]);
        $email->update(['user_id' => $user->id]);
        $dept = Department::factory()->create(['tpl_id' => 1]);
        $staff = Staff::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'dept_id' => $dept->id, 'status_id' => 1]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);

        $customerEntry = ThreadEntry::factory()->create([
            'thread_id' => $thread->id,
            'type' => 'M',
            'created' => '2026-01-01 10:00:00',
        ]);
        DB::connection('legacy')->table('thread_entry_email')->insert([
            'thread_entry_id' => $customerEntry->id,
            'mid' => '<customer-original@x>',
            'headers' => 'Message-ID: <customer-original@x>',
        ]);

        app(ReplyPostingService::class)->post(
            ticket: $ticket->refresh(),
            thread: $thread,
            staff: $staff,
            body: 'My reply',
            format: 'text',
            signatureChoice: 'none',
            replyStatusId: null,
            expectedUpdated: (string) $ticket->updated,
        );

        Mail::assertQueued(StaffReplyMail::class, function (StaffReplyMail $m) {
            return $m->inReplyTo === '<customer-original@x>';
        });
    }
}
```

- [ ] **Step 2: Write the failure-path test**

Create `tests/Feature/Mail/QueuedMailFailureTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\StaffReplyMail;
use App\Mail\CloseNotifyMail;
use App\Models\Department;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class QueuedMailFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.from.address' => 'support@example.test']);

        if (! Schema::connection('legacy')->hasTable('thread_entry_email')) {
            Schema::connection('legacy')->create('thread_entry_email', function (Blueprint $t): void {
                $t->unsignedInteger('id')->autoIncrement();
                $t->unsignedInteger('thread_entry_id');
                $t->unsignedInteger('email_id')->nullable();
                $t->string('mid', 255);
                $t->text('headers')->nullable();
            });
        }
        DB::connection('legacy')->table('email_template_group')->insertOrIgnore([['tpl_id' => 1, 'isactive' => 1, 'name' => 'Default']]);
        DB::connection('legacy')->table('email_template')->insertOrIgnore([
            ['tpl_id' => 1, 'code_name' => 'ticket.reply', 'subject' => 'X', 'body' => '<p>%{response}</p>'],
            ['tpl_id' => 1, 'code_name' => 'note.alert', 'subject' => 'Y', 'body' => '<p>%{comments}</p>'],
        ]);
    }

    public function test_staff_reply_mail_failed_logs_audit_entry(): void
    {
        [$ticket, $entry, $staff] = $this->seed();

        $mail = new StaffReplyMail(
            ticket: $ticket, entry: $entry, staff: $staff,
            signatureChoice: 'none', messageId: '<m@x>',
            inReplyTo: null, references: '',
        );

        $mail->failed(new \RuntimeException('SMTP exhausted'));

        $this->assertDatabaseHas('scp_action_logs', [
            'staff_id' => $staff->staff_id,
            'action' => 'reply.mail_failed',
            'outcome' => 'failed',
            'ticket_id' => $ticket->ticket_id,
        ]);
    }

    public function test_close_notify_mail_failed_logs_audit_entry(): void
    {
        [$ticket, $entry, $staff] = $this->seed();

        $mail = new CloseNotifyMail(
            ticket: $ticket, entry: $entry, staff: $staff,
            comments: 'msg', messageId: '<m@x>',
            inReplyTo: null, references: '',
        );

        $mail->failed(new \RuntimeException('SMTP exhausted'));

        $this->assertDatabaseHas('scp_action_logs', [
            'staff_id' => $staff->staff_id,
            'action' => 'close.mail_failed',
            'outcome' => 'failed',
            'ticket_id' => $ticket->ticket_id,
        ]);
    }

    /**
     * @return array{0: Ticket, 1: ThreadEntry, 2: Staff}
     */
    private function seed(): array
    {
        $email = UserEmail::factory()->create(['address' => 'alice@example.com']);
        $user = User::factory()->create(['default_email_id' => $email->id]);
        $email->update(['user_id' => $user->id]);
        $dept = Department::factory()->create(['tpl_id' => 1]);
        $staff = Staff::factory()->create();
        $ticket = Ticket::factory()->create(['user_id' => $user->id, 'dept_id' => $dept->id]);
        $thread = Thread::factory()->create(['object_id' => $ticket->ticket_id]);
        $entry = ThreadEntry::factory()->create(['thread_id' => $thread->id, 'staff_id' => $staff->staff_id, 'type' => 'R']);
        return [$ticket->refresh(), $entry->refresh(), $staff];
    }
}
```

- [ ] **Step 3: Run both tests**

Run: `php artisan test --compact tests/Feature/Mail/CustomerReplyThreadingTest.php tests/Feature/Mail/QueuedMailFailureTest.php`

Expected: PASS — 4 passed.

- [ ] **Step 4: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add tests/Feature/Mail/CustomerReplyThreadingTest.php tests/Feature/Mail/QueuedMailFailureTest.php
git commit -m "test(mail): add threading parity and queued-mail-failure integration tests"
```

---

## Final verification

After all tasks complete:

- [ ] **Full mail + tickets suite green**

Run: `php artisan test --compact tests/Feature/Mail/ tests/Unit/Mail/ tests/Feature/Scp/Tickets/ tests/Unit/Services/Scp/`

Expected: PASS across all touched files.

- [ ] **Ownership-swap mechanism tests still green**

Run: `php artisan test --compact --filter='OutboundMail|MailOwnership'`

Expected: PASS — no regression in the PR #73 mechanism.

- [ ] **Static checks**

Run: `php artisan config:show mail.event_class_owner`

Expected: shows current ownership values.

Run: `php artisan tinker --execute 'app(\App\Services\Scp\Tickets\ReplyPostingService::class);'`

Expected: returns singleton without throwing.

- [ ] **Build the frontend**

Run: `npm run build`

Expected: successful build.

- [ ] **Manual canary** (gated on staff with `tickets.post-reply` permission seeded + `MAIL_OWNER_REPLY=laravel` in `.env`)

1. Visit `/scp/tickets/{id}` as a staff member
2. Verify `ReplyComposer` renders below the existing thread
3. Post a reply → confirm thread updates, no JS errors, one mail queued
4. Set `MAIL_OWNER_REPLY=legacy` in `.env`, `php artisan config:clear`, reload → confirm `ReplyComposer` disappears
5. Repeat for close-with-notify per `docs/runbooks/phase-2c-mail-swap.md`

---

## Scope guard

This plan ships the **reply + close-with-notify + threading parity** subset of issue #56. The remaining four sub-features (attachments, canned responses, field edits, dynamic-form `__cdata`) are independent — each gets its own brainstorm → spec → plan cycle.
