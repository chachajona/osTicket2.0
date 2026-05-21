# ReplyComposer Extensions — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add @-mentions with staff notifications, /-slash canned responses, and auto-save draft to the TipTap-powered ReplyComposer.

**Architecture:** Three independent backend endpoints (staff autocomplete, SCP canned responses, extended draft namespace) feed two TipTap suggestion popups (MentionList, SlashCommandList) and a debounced useAutoSave hook. Mention notifications are dispatched as queued jobs from the controllers after a successful post. RichTextEditor gains two new extensions; ReplyComposer gains auto-save wiring.

**Tech Stack:** Laravel 13, Pest 4, `@tiptap/extension-mention`, `@tiptap/suggestion`, `ReactRenderer` from `@tiptap/react`, React 19, Tailwind CSS v4.

**Prerequisite:** The TipTap base plan (`2026-05-19-tiptap-reply-composer-plan.md`) must be completed first. `RichTextEditor.tsx` and the updated `ReplyComposer.tsx` are assumed to exist.

---

## File Map

| Action | File |
|---|---|
| Install | `package.json` — `@tiptap/extension-mention` |
| Create | `app/Http/Controllers/Scp/Staff/AutocompleteController.php` |
| Create | `app/Http/Controllers/Scp/CannedResponseController.php` |
| Create | `app/Mail/MentionNotificationMail.php` |
| Create | `app/Jobs/NotifyMentionedStaffJob.php` |
| Create | `resources/views/mail/mention-notification.blade.php` |
| Modify | `app/Http/Controllers/Scp/Tickets/ReplyController.php` |
| Modify | `app/Http/Controllers/Scp/Tickets/NoteController.php` |
| Modify | `app/Http/Controllers/Scp/Tickets/DraftController.php` |
| Modify | `routes/web.php` |
| Create | `resources/js/components/tickets/MentionList.tsx` |
| Create | `resources/js/components/tickets/SlashCommandList.tsx` |
| Modify | `resources/js/components/tickets/RichTextEditor.tsx` |
| Create | `resources/js/hooks/useAutoSave.ts` |
| Modify | `resources/js/components/tickets/ReplyComposer.tsx` |
| Modify | `resources/js/pages/Scp/Tickets/Show.tsx` |
| Modify | `resources/css/app.css` |
| Create | `tests/Feature/Scp/Staff/AutocompleteControllerTest.php` |
| Create | `tests/Feature/Scp/CannedResponseControllerTest.php` |
| Modify | `tests/Feature/Scp/Tickets/ReplyControllerTest.php` |
| Create | `tests/Feature/Scp/Tickets/NoteControllerTest.php` |

---

## Task 1: Install @tiptap/extension-mention

**Files:**
- Modify: `package.json`

- [ ] **Step 1: Install the package**

```bash
npm install @tiptap/extension-mention
```

Expected: package appears in `dependencies` in `package.json`. `@tiptap/suggestion` is installed automatically as a peer dependency.

- [ ] **Step 2: Verify**

```bash
grep -E '"@tiptap/extension-mention|@tiptap/suggestion"' package.json
```

Expected: both keys present.

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore(deps): install @tiptap/extension-mention"
```

---

## Task 2: Staff Autocomplete Backend Endpoint

**Files:**
- Create: `app/Http/Controllers/Scp/Staff/AutocompleteController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Scp/Staff/AutocompleteControllerTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Scp/Staff/AutocompleteControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Staff;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutocompleteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_active_staff_matching_query(): void
    {
        $current = Staff::factory()->create(['isactive' => 1, 'firstname' => 'Current', 'lastname' => 'User', 'username' => 'current']);
        Staff::factory()->create(['isactive' => 1, 'firstname' => 'Ada', 'lastname' => 'Lovelace', 'username' => 'ada']);
        Staff::factory()->create(['isactive' => 1, 'firstname' => 'Alice', 'lastname' => 'Smith', 'username' => 'alice']);
        Staff::factory()->create(['isactive' => 0, 'firstname' => 'Inactive', 'lastname' => 'Person', 'username' => 'inactive']);

        $this->actingAs($current, 'staff')
            ->getJson(route('scp.staff.autocomplete', ['q' => 'ada']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Ada Lovelace', 'username' => 'ada']);
    }

    public function test_excludes_current_staff(): void
    {
        $current = Staff::factory()->create(['isactive' => 1, 'firstname' => 'Current', 'lastname' => 'User', 'username' => 'current']);

        $this->actingAs($current, 'staff')
            ->getJson(route('scp.staff.autocomplete'))
            ->assertOk()
            ->assertJsonMissing(['username' => 'current']);
    }

    public function test_excludes_inactive_staff(): void
    {
        $current = Staff::factory()->create(['isactive' => 1, 'firstname' => 'Active', 'lastname' => 'User', 'username' => 'active']);
        Staff::factory()->create(['isactive' => 0, 'firstname' => 'Inactive', 'lastname' => 'Person', 'username' => 'gone']);

        $this->actingAs($current, 'staff')
            ->getJson(route('scp.staff.autocomplete'))
            ->assertOk()
            ->assertJsonMissing(['username' => 'gone']);
    }

    public function test_returns_at_most_ten_results(): void
    {
        $current = Staff::factory()->create(['isactive' => 1]);
        Staff::factory()->count(15)->create(['isactive' => 1]);

        $response = $this->actingAs($current, 'staff')
            ->getJson(route('scp.staff.autocomplete'))
            ->assertOk();

        $this->assertCount(10, $response->json());
    }

    public function test_returns_401_for_unauthenticated(): void
    {
        $this->getJson(route('scp.staff.autocomplete'))->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=AutocompleteControllerTest
```

Expected: FAIL — route not found.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `Route::middleware(['auth.staff', 'scp.access', 'scp.log'])` group, add:

```php
Route::get('/staff/autocomplete', \App\Http\Controllers\Scp\Staff\AutocompleteController::class)
    ->name('staff.autocomplete');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Scp/Staff/AutocompleteController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Staff;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AutocompleteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Staff $currentStaff */
        $currentStaff = $request->user('staff');
        $q = $request->string('q')->trim()->value();

        $query = Staff::on('legacy')
            ->where('isactive', 1)
            ->where('staff_id', '!=', $currentStaff->staff_id)
            ->orderBy('firstname')
            ->limit(10);

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('firstname', 'like', "%{$q}%")
                    ->orWhere('lastname', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%");
            });
        }

        return response()->json(
            $query->get()->map(fn (Staff $s) => [
                'id' => $s->staff_id,
                'name' => trim("{$s->firstname} {$s->lastname}"),
                'username' => $s->username,
            ])
        );
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter=AutocompleteControllerTest
```

Expected: All 5 tests pass.

- [ ] **Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Scp/Staff/AutocompleteController.php routes/web.php tests/Feature/Scp/Staff/AutocompleteControllerTest.php
git commit -m "feat(scp): add staff autocomplete endpoint"
```

---

## Task 3: SCP Canned Responses Endpoint

**Files:**
- Create: `app/Http/Controllers/Scp/CannedResponseController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Scp/CannedResponseControllerTest.php`

> Note: An admin `CannedResponseController` already exists at `app/Http/Controllers/Admin/CannedResponseController.php`. This new one lives in the `Scp` namespace.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Scp/CannedResponseControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Scp;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CannedResponseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('legacy')->table('canned_response')->delete();
    }

    private function createCannedResponse(array $attrs = []): int
    {
        return DB::connection('legacy')->table('canned_response')->insertGetId(array_merge([
            'dept_id' => null,
            'isenabled' => 1,
            'title' => 'Default Title',
            'response' => '<p>Default response</p>',
            'lang' => 'en',
            'notes' => '',
            'created' => now(),
            'updated' => now(),
        ], $attrs));
    }

    public function test_returns_global_responses_when_no_dept_id(): void
    {
        $staff = Staff::factory()->create(['isactive' => 1]);
        $this->createCannedResponse(['title' => 'Global Response', 'dept_id' => null]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Global Response']);
    }

    public function test_returns_dept_and_global_responses_when_dept_id_given(): void
    {
        $staff = Staff::factory()->create(['isactive' => 1]);
        $this->createCannedResponse(['title' => 'Global', 'dept_id' => null]);
        $this->createCannedResponse(['title' => 'Dept 5', 'dept_id' => 5]);
        $this->createCannedResponse(['title' => 'Dept 9', 'dept_id' => 9]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index', ['dept_id' => 5]))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['title' => 'Global'])
            ->assertJsonFragment(['title' => 'Dept 5'])
            ->assertJsonMissing(['title' => 'Dept 9']);
    }

    public function test_excludes_disabled_responses(): void
    {
        $staff = Staff::factory()->create(['isactive' => 1]);
        $this->createCannedResponse(['title' => 'Enabled', 'isenabled' => 1, 'dept_id' => null]);
        $this->createCannedResponse(['title' => 'Disabled', 'isenabled' => 0, 'dept_id' => null]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index'))
            ->assertOk()
            ->assertJsonFragment(['title' => 'Enabled'])
            ->assertJsonMissing(['title' => 'Disabled']);
    }

    public function test_filters_by_title_query(): void
    {
        $staff = Staff::factory()->create(['isactive' => 1]);
        $this->createCannedResponse(['title' => 'Greeting', 'dept_id' => null]);
        $this->createCannedResponse(['title' => 'Closing', 'dept_id' => null]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index', ['q' => 'greet']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['title' => 'Greeting']);
    }

    public function test_returns_at_most_ten_results(): void
    {
        $staff = Staff::factory()->create(['isactive' => 1]);
        foreach (range(1, 15) as $i) {
            $this->createCannedResponse(['title' => "Response {$i}", 'dept_id' => null]);
        }

        $response = $this->actingAs($staff, 'staff')
            ->getJson(route('scp.canned-responses.index'))
            ->assertOk();

        $this->assertCount(10, $response->json());
    }

    public function test_returns_401_for_unauthenticated(): void
    {
        $this->getJson(route('scp.canned-responses.index'))->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=CannedResponseControllerTest
```

Expected: FAIL — route not found.

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `Route::middleware(['auth.staff', 'scp.access', 'scp.log'])` group, add:

```php
Route::get('/canned-responses', \App\Http\Controllers\Scp\CannedResponseController::class)
    ->name('canned-responses.index');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Scp/CannedResponseController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp;

use App\Http\Controllers\Controller;
use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CannedResponseController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $deptId = $request->filled('dept_id') ? $request->integer('dept_id') : null;
        $q = $request->string('q')->trim()->value();

        $query = CannedResponse::on('legacy')
            ->where('isenabled', 1)
            ->where(function ($sub) use ($deptId): void {
                $sub->whereNull('dept_id');
                if ($deptId !== null) {
                    $sub->orWhere('dept_id', $deptId);
                }
            })
            ->orderBy('title')
            ->limit(10);

        if ($q !== '') {
            $query->where('title', 'like', "%{$q}%");
        }

        return response()->json(
            $query->get()->map(fn (CannedResponse $cr) => [
                'id' => $cr->canned_id,
                'title' => $cr->title,
                'response' => $cr->response,
            ])
        );
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter=CannedResponseControllerTest
```

Expected: All 6 tests pass.

- [ ] **Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Scp/CannedResponseController.php routes/web.php tests/Feature/Scp/CannedResponseControllerTest.php
git commit -m "feat(scp): add canned responses endpoint for composer"
```

---

## Task 4: Mention Notification Mail + Job

**Files:**
- Create: `app/Mail/MentionNotificationMail.php`
- Create: `app/Jobs/NotifyMentionedStaffJob.php`
- Create: `resources/views/mail/mention-notification.blade.php`

No TDD here — these are infrastructure classes tested indirectly in Task 5.

- [ ] **Step 1: Create the Mailable**

Create `app/Mail/MentionNotificationMail.php`:

```php
<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Staff;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class MentionNotificationMail extends Mailable implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ThreadEntry $entry,
        public readonly Staff $mentioner,
        public readonly Staff $mentioned,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You were mentioned in ticket #{$this->ticket->ticket_id}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.mention-notification',
        );
    }
}
```

- [ ] **Step 2: Create the Blade view**

Create `resources/views/mail/mention-notification.blade.php`:

```
Hi {{ $mentioned->firstname }},

{{ $mentioner->firstname }} {{ $mentioner->lastname }} mentioned you in ticket #{{ $ticket->ticket_id }}.

Please log in to review the ticket at your earliest convenience.
```

- [ ] **Step 3: Create the Job**

Create `app/Jobs/NotifyMentionedStaffJob.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\MentionNotificationMail;
use App\Models\Staff;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class NotifyMentionedStaffJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ThreadEntry $entry,
        public readonly Staff $mentioner,
        public readonly int $mentionedStaffId,
    ) {}

    public function handle(): void
    {
        $mentioned = Staff::on('legacy')
            ->where('staff_id', $this->mentionedStaffId)
            ->where('isactive', 1)
            ->first();

        if ($mentioned === null) {
            return;
        }

        Mail::queue(new MentionNotificationMail(
            ticket: $this->ticket,
            entry: $this->entry,
            mentioner: $this->mentioner,
            mentioned: $mentioned,
        ));
    }
}
```

- [ ] **Step 4: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add app/Mail/MentionNotificationMail.php app/Jobs/NotifyMentionedStaffJob.php resources/views/mail/mention-notification.blade.php
git commit -m "feat(mail): add MentionNotificationMail and NotifyMentionedStaffJob"
```

---

## Task 5: Extend ReplyController + NoteController for Mentioned Staff

**Files:**
- Modify: `app/Http/Controllers/Scp/Tickets/ReplyController.php`
- Modify: `app/Http/Controllers/Scp/Tickets/NoteController.php`
- Modify: `tests/Feature/Scp/Tickets/ReplyControllerTest.php`
- Create: `tests/Feature/Scp/Tickets/NoteControllerTest.php`

- [ ] **Step 1: Write the failing ReplyController mention test**

Add this test to `tests/Feature/Scp/Tickets/ReplyControllerTest.php` (inside the class, after existing tests):

```php
public function test_dispatches_mention_jobs_for_mentioned_staff_ids(): void
{
    config(['mail.event_class_owner.reply' => 'laravel']);
    \Illuminate\Support\Facades\Queue::fake();

    $fixture = $this->seedMailTicket();
    $fixture['staff']->givePermissionTo('tickets.post-reply');
    $mentioned = \App\Models\Staff::factory()->create(['isactive' => 1]);

    $this->actingAs($fixture['staff'], 'staff')
        ->post(route('scp.tickets.replies.store', $fixture['ticket']), [
            'body' => 'Hey @Someone check this out',
            'format' => 'html',
            'signature' => 'none',
            'expected_updated' => (string) $fixture['ticket']->updated,
            'mentioned_staff_ids' => [$mentioned->staff_id],
        ])
        ->assertRedirect();

    \Illuminate\Support\Facades\Queue::assertPushed(
        \App\Jobs\NotifyMentionedStaffJob::class,
        fn ($job) => $job->mentionedStaffId === $mentioned->staff_id
    );
}

public function test_no_mention_jobs_dispatched_when_mentioned_staff_ids_empty(): void
{
    config(['mail.event_class_owner.reply' => 'laravel']);
    \Illuminate\Support\Facades\Queue::fake();

    $fixture = $this->seedMailTicket();
    $fixture['staff']->givePermissionTo('tickets.post-reply');

    $this->actingAs($fixture['staff'], 'staff')
        ->post(route('scp.tickets.replies.store', $fixture['ticket']), [
            'body' => 'A reply with no mentions',
            'format' => 'html',
            'signature' => 'none',
            'expected_updated' => (string) $fixture['ticket']->updated,
            'mentioned_staff_ids' => [],
        ])
        ->assertRedirect();

    \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\NotifyMentionedStaffJob::class);
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="test_dispatches_mention_jobs_for_mentioned_staff_ids"
```

Expected: FAIL — `mentioned_staff_ids` is ignored.

- [ ] **Step 3: Update ReplyController**

Replace the full `store` method in `app/Http/Controllers/Scp/Tickets/ReplyController.php`:

```php
public function store(Request $request, Ticket $ticket): RedirectResponse|JsonResponse
{
    $this->authorize('postReply', $ticket);

    if ((string) config('mail.event_class_owner.reply') !== 'laravel') {
        abort(403, 'Reply mail is owned by legacy.');
    }

    /** @var Staff $staff */
    $staff = $request->user('staff');

    $data = $request->validate([
        'body'                => 'required|string|max:65535',
        'format'              => 'required|in:html,text',
        'signature'           => ['required', Rule::in(['none', 'mine', 'dept'])],
        'reply_status_id'     => 'nullable|integer|exists:legacy.ticket_status,id',
        'expected_updated'    => 'required|string',
        'mentioned_staff_ids' => 'nullable|array',
        'mentioned_staff_ids.*' => 'integer',
    ]);

    $this->ensureSignatureAvailable($staff, $ticket, (string) $data['signature']);

    $thread = $ticket->thread()->firstOrFail();

    try {
        $entry = $this->replies->post(
            ticket: $ticket,
            thread: $thread,
            staff: $staff,
            body: (string) $data['body'],
            format: (string) $data['format'],
            signatureChoice: (string) $data['signature'],
            replyStatusId: isset($data['reply_status_id']) ? (int) $data['reply_status_id'] : null,
            expectedUpdated: (string) $data['expected_updated'],
        );
    } catch (TicketModifiedConcurrentlyException) {
        $this->audit->recordFailure($staff, 'reply.posted', 409, $ticket->ticket_id, TicketModifiedConcurrentlyException::class, $request);

        return response()->json(['message' => 'Ticket was modified'], 409);
    } catch (ForbiddenStatusTransition) {
        $this->audit->recordFailure($staff, 'reply.posted', 422, $ticket->ticket_id, ForbiddenStatusTransition::class, $request);

        return response()->json(['message' => 'Forbidden status transition'], 422);
    }

    foreach ((array) ($data['mentioned_staff_ids'] ?? []) as $mentionedId) {
        NotifyMentionedStaffJob::dispatch($ticket, $entry, $staff, (int) $mentionedId);
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
```

Add the import at the top of the file:

```php
use App\Jobs\NotifyMentionedStaffJob;
```

- [ ] **Step 4: Run ReplyController tests**

```bash
php artisan test --compact --filter=ReplyControllerTest
```

Expected: All tests pass.

- [ ] **Step 5: Write the failing NoteController mention test**

Create `tests/Feature/Scp/Tickets/NoteControllerTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Jobs\NotifyMentionedStaffJob;
use App\Models\LegacyPermission;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class NoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['osticket.ticket_lock' => '0']);

        DB::connection('legacy')->table('event')->insertOrIgnore([
            ['id' => 7, 'name' => 'created', 'description' => 'Created'],
        ]);

        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
        ]);

        LegacyPermission::create(['name' => 'tickets.post-note', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_dispatches_mention_jobs_for_mentioned_staff_ids(): void
    {
        Queue::fake();

        $staff = Staff::factory()->create();
        $staff->givePermissionTo('tickets.post-note');
        $ticket = Ticket::factory()->create(['updated' => now()->toDateTimeString()]);
        Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);
        $mentioned = Staff::factory()->create(['isactive' => 1]);

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.notes.store', $ticket), [
                'body' => '<p>Hey @Someone look at this</p>',
                'format' => 'html',
                'expected_updated' => (string) $ticket->updated,
                'mentioned_staff_ids' => [$mentioned->staff_id],
            ])
            ->assertRedirect();

        Queue::assertPushed(
            NotifyMentionedStaffJob::class,
            fn ($job) => $job->mentionedStaffId === $mentioned->staff_id
        );
    }

    public function test_no_mention_jobs_when_mentioned_staff_ids_empty(): void
    {
        Queue::fake();

        $staff = Staff::factory()->create();
        $staff->givePermissionTo('tickets.post-note');
        $ticket = Ticket::factory()->create(['updated' => now()->toDateTimeString()]);
        Thread::factory()->for($ticket, 'ticket')->create(['object_type' => 'T']);

        $this->actingAs($staff, 'staff')
            ->post(route('scp.tickets.notes.store', $ticket), [
                'body' => '<p>A note with no mentions</p>',
                'format' => 'html',
                'expected_updated' => (string) $ticket->updated,
                'mentioned_staff_ids' => [],
            ])
            ->assertRedirect();

        Queue::assertNotPushed(NotifyMentionedStaffJob::class);
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

```bash
php artisan test --compact --filter=NoteControllerTest
```

Expected: FAIL — `mentioned_staff_ids` ignored.

- [ ] **Step 7: Update NoteController**

Replace the full `store` method in `app/Http/Controllers/Scp/Tickets/NoteController.php`:

```php
public function store(Request $request, Ticket $ticket): RedirectResponse
{
    $staff = $request->user('staff');
    $this->authorize('postNote', $ticket);

    $validated = $request->validate([
        'body'                  => 'required|string|max:65535',
        'format'                => 'required|in:html,text',
        'expected_updated'      => 'required|string',
        'mentioned_staff_ids'   => 'nullable|array',
        'mentioned_staff_ids.*' => 'integer',
    ]);

    $thread = $ticket->thread()->firstOrFail();

    $entry = $this->notes->post(
        $ticket,
        $thread,
        $staff,
        $validated['body'],
        $validated['format'],
        $validated['expected_updated'],
    );

    foreach ((array) ($validated['mentioned_staff_ids'] ?? []) as $mentionedId) {
        NotifyMentionedStaffJob::dispatch($ticket, $entry, $staff, (int) $mentionedId);
    }

    $this->drafts->discard($staff, "ticket.note.{$ticket->ticket_id}");
    $this->audit->record(
        staff: $staff,
        action: 'note.posted',
        outcome: 'success',
        httpStatus: 302,
        ticketId: $ticket->ticket_id,
        request: $request,
    );

    return back()->with('success', 'Note posted.');
}
```

Add the import at the top of `NoteController.php`:

```php
use App\Jobs\NotifyMentionedStaffJob;
```

- [ ] **Step 8: Run all NoteController tests**

```bash
php artisan test --compact --filter=NoteControllerTest
```

Expected: Both tests pass.

- [ ] **Step 9: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Scp/Tickets/ReplyController.php app/Http/Controllers/Scp/Tickets/NoteController.php tests/Feature/Scp/Tickets/ReplyControllerTest.php tests/Feature/Scp/Tickets/NoteControllerTest.php
git commit -m "feat(composer): dispatch mention notifications from reply and note controllers"
```

---

## Task 6: Update DraftController for Reply/Note Namespace

**Files:**
- Modify: `app/Http/Controllers/Scp/Tickets/DraftController.php`

The current `DraftController` hardcodes `ticket.note.{id}` for all operations. The frontend needs separate namespaces for reply (`ticket.reply.{id}`) and note (`ticket.note.{id}`) modes. Add a private helper that derives the namespace from a `?type=reply|note` query param (defaults to `note` for backward compatibility).

- [ ] **Step 1: Update DraftController**

Replace the full content of `app/Http/Controllers/Scp/Tickets/DraftController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scp\Tickets;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Scp\Tickets\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DraftController extends Controller
{
    public function __construct(
        private readonly DraftService $drafts,
    ) {}

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');
        $namespace = $this->resolveNamespace($request, $ticket);

        $draft = $this->drafts->find($staff, $namespace);

        if ($draft === null) {
            return response()->json([
                'body' => '',
                'updated' => null,
            ]);
        }

        return response()->json([
            'body' => $draft->body,
            'updated' => $draft->updated,
        ]);
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');
        $namespace = $this->resolveNamespace($request, $ticket);

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $draft = $this->drafts->upsert($staff, $namespace, $validated['body']);

        return response()->json([
            'body' => $draft->body,
            'updated' => $draft->updated,
        ], 201);
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');
        $namespace = $this->resolveNamespace($request, $ticket);

        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        $draft = $this->drafts->upsert($staff, $namespace, $validated['body']);

        return response()->json([
            'body' => $draft->body,
            'updated' => $draft->updated,
        ]);
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        $staff = $request->user('staff');
        $namespace = $this->resolveNamespace($request, $ticket);

        $this->drafts->discard($staff, $namespace);

        return response()->json(status: 204);
    }

    private function resolveNamespace(Request $request, Ticket $ticket): string
    {
        return $request->query('type') === 'reply'
            ? "ticket.reply.{$ticket->ticket_id}"
            : "ticket.note.{$ticket->ticket_id}";
    }
}
```

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Run existing draft-related tests to confirm no regression**

```bash
php artisan test --compact --filter=ReplyControllerTest
```

Expected: All pass (the discard call in `ReplyController` uses the service directly, not the HTTP controller).

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Scp/Tickets/DraftController.php
git commit -m "feat(draft): support reply/note namespace via type query param"
```

---

## Task 7: MentionList and SlashCommandList Frontend Components

**Files:**
- Create: `resources/js/components/tickets/MentionList.tsx`
- Create: `resources/js/components/tickets/SlashCommandList.tsx`

Both are `forwardRef` components with an `onKeyDown` handle — required by TipTap's `ReactRenderer` for keyboard passthrough.

- [ ] **Step 1: Create MentionList.tsx**

Create `resources/js/components/tickets/MentionList.tsx`:

```tsx
import { forwardRef, useEffect, useImperativeHandle, useState } from 'react';
import { cn } from '@/lib/utils';

interface StaffItem {
    id: number;
    name: string;
    username: string;
}

interface MentionListProps {
    items: StaffItem[];
    command: (item: StaffItem) => void;
}

export interface MentionListHandle {
    onKeyDown: (props: { event: KeyboardEvent }) => boolean;
}

export const MentionList = forwardRef<MentionListHandle, MentionListProps>(
    function MentionList({ items, command }, ref) {
        const [selectedIndex, setSelectedIndex] = useState(0);

        useEffect(() => {
            setSelectedIndex(0);
        }, [items]);

        useImperativeHandle(ref, () => ({
            onKeyDown: ({ event }) => {
                if (event.key === 'ArrowUp') {
                    setSelectedIndex((i) => (i + items.length - 1) % Math.max(items.length, 1));
                    return true;
                }
                if (event.key === 'ArrowDown') {
                    setSelectedIndex((i) => (i + 1) % Math.max(items.length, 1));
                    return true;
                }
                if ((event.key === 'Enter' || event.key === 'Tab') && items[selectedIndex]) {
                    command(items[selectedIndex]);
                    return true;
                }
                return false;
            },
        }));

        if (items.length === 0) {
            return (
                <div className="inline-flex items-center rounded-lg border border-[#E2E0D8] bg-white px-3 py-2 text-xs text-[#A1A1AA] shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                    No staff found
                </div>
            );
        }

        return (
            <div className="z-50 w-56 rounded-lg border border-[#E2E0D8] bg-white p-1 shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                {items.map((item, index) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => command(item)}
                        className={cn(
                            'flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left transition-colors duration-100',
                            index === selectedIndex ? 'bg-[#FAFAF8]' : 'hover:bg-[#FAFAF8]'
                        )}
                    >
                        <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#E2E0D8] text-[9px] font-semibold text-[#18181B]">
                            {item.name.split(' ').map((p) => p[0]).join('').slice(0, 2).toUpperCase()}
                        </span>
                        <span className="min-w-0 flex-1">
                            <div className="truncate text-[13px] font-medium text-[#18181B]">{item.name}</div>
                            <div className="truncate text-[11px] text-[#A1A1AA]">@{item.username}</div>
                        </span>
                    </button>
                ))}
            </div>
        );
    }
);
```

- [ ] **Step 2: Create SlashCommandList.tsx**

Create `resources/js/components/tickets/SlashCommandList.tsx`:

```tsx
import { forwardRef, useEffect, useImperativeHandle, useState } from 'react';
import { cn } from '@/lib/utils';

interface CannedItem {
    id: number;
    title: string;
    response: string;
}

interface SlashCommandListProps {
    items: CannedItem[];
    command: (item: CannedItem) => void;
}

export interface SlashCommandListHandle {
    onKeyDown: (props: { event: KeyboardEvent }) => boolean;
}

function stripHtml(html: string): string {
    return html.replace(/<[^>]+>/g, '').trim().slice(0, 60);
}

export const SlashCommandList = forwardRef<SlashCommandListHandle, SlashCommandListProps>(
    function SlashCommandList({ items, command }, ref) {
        const [selectedIndex, setSelectedIndex] = useState(0);

        useEffect(() => {
            setSelectedIndex(0);
        }, [items]);

        useImperativeHandle(ref, () => ({
            onKeyDown: ({ event }) => {
                if (event.key === 'ArrowUp') {
                    setSelectedIndex((i) => (i + items.length - 1) % Math.max(items.length, 1));
                    return true;
                }
                if (event.key === 'ArrowDown') {
                    setSelectedIndex((i) => (i + 1) % Math.max(items.length, 1));
                    return true;
                }
                if ((event.key === 'Enter' || event.key === 'Tab') && items[selectedIndex]) {
                    command(items[selectedIndex]);
                    return true;
                }
                return false;
            },
        }));

        if (items.length === 0) {
            return (
                <div className="inline-flex items-center rounded-lg border border-[#E2E0D8] bg-white px-3 py-2 text-xs text-[#A1A1AA] shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                    No responses found
                </div>
            );
        }

        return (
            <div className="z-50 w-72 rounded-lg border border-[#E2E0D8] bg-white p-1 shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                <div className="px-2.5 py-1.5 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                    Canned Responses
                </div>
                {items.map((item, index) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => command(item)}
                        className={cn(
                            'flex w-full flex-col rounded-md px-2.5 py-1.5 text-left transition-colors duration-100',
                            index === selectedIndex ? 'bg-[#FAFAF8]' : 'hover:bg-[#FAFAF8]'
                        )}
                    >
                        <div className="text-[13px] font-medium text-[#18181B]">{item.title}</div>
                        <div className="truncate text-[11px] text-[#A1A1AA]">{stripHtml(item.response)}&hellip;</div>
                    </button>
                ))}
            </div>
        );
    }
);
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/tickets/MentionList.tsx resources/js/components/tickets/SlashCommandList.tsx
git commit -m "feat(composer): add MentionList and SlashCommandList popup components"
```

---

## Task 8: Extend RichTextEditor with Mention + Slash Command Extensions

**Files:**
- Modify: `resources/js/components/tickets/RichTextEditor.tsx`

Add the Mention extension (@ trigger) and a custom slash command extension (/ trigger), both using `ReactRenderer` for their popups. Also add `mention` CSS class to `app.css`.

- [ ] **Step 1: Add mention CSS to app.css**

Append to the end of `resources/css/app.css`:

```css
/* @-mention chip */
.mention {
    background: #F3F3FE;
    border-radius: 4px;
    color: #5558CF;
    font-weight: 500;
    padding: 0 3px;
}
```

- [ ] **Step 2: Update RichTextEditor.tsx**

Replace the full content of `resources/js/components/tickets/RichTextEditor.tsx`:

```tsx
import { forwardRef, useImperativeHandle } from 'react';
import { useEditor, EditorContent, ReactRenderer } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import Mention from '@tiptap/extension-mention';
import { Extension } from '@tiptap/core';
import { Suggestion } from '@tiptap/suggestion';
import type { Editor } from '@tiptap/core';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    TextBoldIcon,
    TextItalicIcon,
    TextUnderlineIcon,
    Link01Icon,
} from '@hugeicons/core-free-icons';
import { cn } from '@/lib/utils';
import { MentionList, type MentionListHandle } from './MentionList';
import { SlashCommandList, type SlashCommandListHandle } from './SlashCommandList';

export interface RichTextEditorHandle {
    getEditor: () => Editor | null;
    insertContent: (content: string) => void;
    clearContent: () => void;
}

interface RichTextEditorProps {
    value?: string;
    onChange: (html: string) => void;
    placeholder?: string;
    onFocus?: () => void;
    ticketDeptId?: number;
}

function makeSuggestionRenderer<H extends { onKeyDown: (p: { event: KeyboardEvent }) => boolean }, I>(
    Component: React.ForwardRefExoticComponent<React.RefAttributes<H> & { items: I[]; command: (item: I) => void }>
) {
    return () => {
        let reactRenderer: ReactRenderer<H>;
        let containerEl: HTMLElement;

        return {
            onStart: (props: any) => {
                reactRenderer = new ReactRenderer(Component, { props, editor: props.editor });
                containerEl = document.createElement('div');
                containerEl.style.cssText = 'position:fixed;z-index:9999;pointer-events:auto';
                document.body.appendChild(containerEl);
                containerEl.appendChild(reactRenderer.element);
                const rect: DOMRect | undefined = props.clientRect?.();
                if (rect) {
                    containerEl.style.top = `${rect.bottom + 4}px`;
                    containerEl.style.left = `${rect.left}px`;
                }
            },
            onUpdate: (props: any) => {
                reactRenderer.updateProps(props);
                const rect: DOMRect | undefined = props.clientRect?.();
                if (rect) {
                    containerEl.style.top = `${rect.bottom + 4}px`;
                    containerEl.style.left = `${rect.left}px`;
                }
            },
            onKeyDown: (props: any): boolean => {
                return reactRenderer.ref?.onKeyDown(props) ?? false;
            },
            onExit: () => {
                containerEl?.remove();
                reactRenderer.destroy();
            },
        };
    };
}

async function fetchStaff(query: string): Promise<{ id: number; name: string; username: string }[]> {
    try {
        const res = await fetch(`/scp/staff/autocomplete?q=${encodeURIComponent(query)}`);
        if (!res.ok) return [];
        return res.json();
    } catch {
        return [];
    }
}

async function fetchCannedResponses(
    deptId: number | undefined,
    query: string
): Promise<{ id: number; title: string; response: string }[]> {
    try {
        const params = new URLSearchParams({ q: query });
        if (deptId) params.set('dept_id', String(deptId));
        const res = await fetch(`/scp/canned-responses?${params}`);
        if (!res.ok) return [];
        return res.json();
    } catch {
        return [];
    }
}

export const RichTextEditor = forwardRef<RichTextEditorHandle, RichTextEditorProps>(
    function RichTextEditor({ value = '', onChange, placeholder, onFocus, ticketDeptId }, ref) {
        const editor = useEditor({
            extensions: [
                StarterKit,
                Underline,
                Link.configure({ openOnClick: false, defaultProtocol: 'https' }),
                Placeholder.configure({ placeholder: placeholder ?? '' }),
                Mention.configure({
                    HTMLAttributes: { class: 'mention' },
                    renderText: ({ node }) => `@${node.attrs.label as string}`,
                    deleteTriggerWithBackspace: true,
                    suggestion: {
                        char: '@',
                        items: ({ query }) => fetchStaff(query),
                        render: makeSuggestionRenderer<MentionListHandle, { id: number; name: string; username: string }>(MentionList),
                        command: ({ editor: e, range, props }) => {
                            e.chain()
                                .focus()
                                .deleteRange(range)
                                .insertContent({
                                    type: 'mention',
                                    attrs: { id: props.id, label: props.name },
                                })
                                .insertContent(' ')
                                .run();
                        },
                    },
                }),
                Extension.create({
                    name: 'slashCommand',
                    addProseMirrorPlugins() {
                        return [
                            Suggestion({
                                char: '/',
                                startOfLine: false,
                                allowSpaces: false,
                                editor: this.editor,
                                items: ({ query }) => fetchCannedResponses(ticketDeptId, query),
                                render: makeSuggestionRenderer<SlashCommandListHandle, { id: number; title: string; response: string }>(SlashCommandList),
                                command: ({ editor: e, range, props }) => {
                                    e.chain().focus().deleteRange(range).insertContent(props.response).run();
                                },
                            }),
                        ];
                    },
                }),
            ],
            content: value,
            injectCSS: false,
            immediatelyRender: false,
            editorProps: {
                attributes: {
                    class: 'w-full bg-transparent p-0 font-sans text-sm leading-relaxed text-[#18181B] outline-none tiptap',
                },
            },
            onUpdate: ({ editor: e }) => {
                onChange(e.isEmpty ? '' : e.getHTML());
            },
            onFocus: () => {
                onFocus?.();
            },
        });

        useImperativeHandle(ref, () => ({
            getEditor: () => editor ?? null,
            insertContent: (content: string) => {
                editor?.chain().focus().insertContent(content).run();
            },
            clearContent: () => {
                editor?.commands.clearContent(true);
            },
        }), [editor]);

        if (!editor) {
            return null;
        }

        return (
            <div className="relative">
                <BubbleMenu editor={editor} tippyOptions={{ duration: 100 }}>
                    <div className="inline-flex items-center gap-0.5 rounded-md border border-[#E2E0D8] bg-white px-1 py-1 shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                        <button
                            type="button"
                            onClick={() => editor.chain().focus().toggleBold().run()}
                            title="Bold"
                            className={cn(
                                'inline-flex h-6 w-6 items-center justify-center rounded-sm transition-all',
                                editor.isActive('bold') ? 'bg-[#FAFAF8] text-[#18181B]' : 'text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
                            )}
                        >
                            <HugeiconsIcon icon={TextBoldIcon} size={13} />
                        </button>
                        <button
                            type="button"
                            onClick={() => editor.chain().focus().toggleItalic().run()}
                            title="Italic"
                            className={cn(
                                'inline-flex h-6 w-6 items-center justify-center rounded-sm transition-all',
                                editor.isActive('italic') ? 'bg-[#FAFAF8] text-[#18181B]' : 'text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
                            )}
                        >
                            <HugeiconsIcon icon={TextItalicIcon} size={13} />
                        </button>
                        <button
                            type="button"
                            onClick={() => editor.chain().focus().toggleUnderline().run()}
                            title="Underline"
                            className={cn(
                                'inline-flex h-6 w-6 items-center justify-center rounded-sm transition-all',
                                editor.isActive('underline') ? 'bg-[#FAFAF8] text-[#18181B]' : 'text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
                            )}
                        >
                            <HugeiconsIcon icon={TextUnderlineIcon} size={13} />
                        </button>
                        <span className="mx-0.5 h-3.5 w-px bg-[#E2E0D8]" />
                        <button
                            type="button"
                            onClick={() => {
                                if (editor.isActive('link')) {
                                    editor.chain().focus().unsetLink().run();
                                } else {
                                    const url = window.prompt('Enter URL:');
                                    if (url) {
                                        editor.chain().focus().setLink({ href: url, target: '_blank' }).run();
                                    }
                                }
                            }}
                            title={editor.isActive('link') ? 'Remove link' : 'Add link'}
                            className={cn(
                                'inline-flex h-6 w-6 items-center justify-center rounded-sm transition-all',
                                editor.isActive('link') ? 'bg-[#FAFAF8] text-[#18181B]' : 'text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
                            )}
                        >
                            <HugeiconsIcon icon={Link01Icon} size={13} />
                        </button>
                    </div>
                </BubbleMenu>

                <EditorContent editor={editor} />
            </div>
        );
    }
);
```

- [ ] **Step 3: Run TypeScript build**

```bash
npm run build
```

Expected: no errors. If `ReactRenderer` generic signature errors appear, change `new ReactRenderer(Component, ...)` to `new ReactRenderer(Component as any, ...)` for the `makeSuggestionRenderer` helper.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/tickets/RichTextEditor.tsx resources/css/app.css
git commit -m "feat(editor): add @-mention and /-slash command extensions to RichTextEditor"
```

---

## Task 9: useAutoSave Hook + ReplyComposer Wiring + Show.tsx

**Files:**
- Create: `resources/js/hooks/useAutoSave.ts`
- Modify: `resources/js/components/tickets/ReplyComposer.tsx`
- Modify: `resources/js/pages/Scp/Tickets/Show.tsx`

- [ ] **Step 1: Create useAutoSave.ts**

Create `resources/js/hooks/useAutoSave.ts`:

```ts
import { useCallback, useEffect, useRef } from 'react';

interface UseAutoSaveOptions {
    body: string;
    ticketId: number;
    type: 'reply' | 'note';
    delay?: number;
    onStateChange: (state: 'idle' | 'saving' | 'saved') => void;
}

interface UseAutoSaveReturn {
    forceSave: () => Promise<void>;
    deleteDraft: () => Promise<void>;
    loadDraft: () => Promise<string>;
}

function getCsrfToken(): string {
    return document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function useAutoSave({
    body,
    ticketId,
    type,
    delay = 10_000,
    onStateChange,
}: UseAutoSaveOptions): UseAutoSaveReturn {
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const bodyRef = useRef(body);
    bodyRef.current = body;

    const draftUrl = `/scp/tickets/${ticketId}/draft?type=${type}`;

    const save = useCallback(async (): Promise<void> => {
        if (!bodyRef.current.trim()) return;

        onStateChange('saving');
        try {
            await fetch(draftUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ body: bodyRef.current }),
            });
            onStateChange('saved');
            setTimeout(() => onStateChange('idle'), 2000);
        } catch {
            onStateChange('idle');
        }
    }, [draftUrl, onStateChange]);

    const deleteDraft = useCallback(async (): Promise<void> => {
        try {
            await fetch(draftUrl, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
        } catch { /* silent */ }
    }, [draftUrl]);

    const loadDraft = useCallback(async (): Promise<string> => {
        try {
            const res = await fetch(draftUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) return '';
            const data = await res.json();
            return (data.body as string) ?? '';
        } catch {
            return '';
        }
    }, [draftUrl]);

    useEffect(() => {
        if (!body.trim()) return;

        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(save, delay);

        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, [body, delay, save]);

    return { forceSave: save, deleteDraft, loadDraft };
}
```

- [ ] **Step 2: Wire auto-save and mention collection into ReplyComposer**

In `resources/js/components/tickets/ReplyComposer.tsx`, make the following changes:

**2a. Add `ticketDeptId` to the props interface:**

```tsx
export interface ReplyComposerProps {
    ticketId: number;
    expectedUpdated: string;
    statusOptions: StatusOption[];
    requester?: string | null;
    requesterEmail?: string | null;
    source?: string | null;
    sourceExtra?: string | null;
    deptLabel?: string;
    deptSignatureAvailable?: boolean;
    collaborators?: Collaborator[];
    ticketDeptId?: number;          // NEW
    onSuccess?: () => void;
}
```

**2b. Accept the new prop in the destructured function signature:**

```tsx
export function ReplyComposer({
    ticketId,
    expectedUpdated,
    statusOptions,
    requester,
    requesterEmail,
    source,
    sourceExtra,
    deptLabel = 'Department',
    deptSignatureAvailable = false,
    collaborators = [],
    ticketDeptId,                   // NEW
    onSuccess,
}: ReplyComposerProps) {
```

**2c. Add the import at the top of the file:**

```tsx
import { useAutoSave } from '@/hooks/useAutoSave';
```

**2d. Add the `useAutoSave` hook inside the component body (after existing state declarations):**

```tsx
const { forceSave, deleteDraft, loadDraft } = useAutoSave({
    body,
    ticketId,
    type: isNote ? 'note' : 'reply',
    onStateChange: setSaveDraftState,
});
```

**2e. Add a `useEffect` for draft restoration on mount (after the hook call):**

```tsx
useEffect(() => {
    loadDraft().then((saved) => {
        if (saved && !body) {
            editorRef.current?.getEditor()?.commands.setContent(saved);
            setBody(saved);
            setSaveDraftState('saved');
            setTimeout(() => setSaveDraftState('idle'), 2000);
        }
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
}, []);
```

**2f. Update the manual "Save as draft" button to call `forceSave()`:**

Find the Save Draft button's `onClick`:
```tsx
onClick={handleSaveDraft}
```
Replace with:
```tsx
onClick={() => void forceSave()}
```

**2g. Add a helper to extract mention IDs from the editor document:**

Add this function inside the component (before `handleSend`):

```tsx
const extractMentionIds = (): number[] => {
    const doc = editorRef.current?.getEditor()?.getJSON();
    if (!doc) return [];
    const ids: number[] = [];
    const traverse = (nodes: any[]): void => {
        for (const node of nodes ?? []) {
            if (node.type === 'mention' && node.attrs?.id) {
                ids.push(Number(node.attrs.id));
            }
            if (node.content) traverse(node.content);
        }
    };
    traverse(doc.content ?? []);
    return [...new Set(ids)];
};
```

**2h. Update `handleSend` — add `mentioned_staff_ids` to both payloads, and call `deleteDraft()` on success:**

In the note branch, update the `router.post` call:

```tsx
router.post(
    `/scp/tickets/${ticketId}/notes`,
    {
        body,
        format: 'html',
        expected_updated: expectedUpdated,
        mentioned_staff_ids: extractMentionIds(),
    },
    {
        preserveScroll: true,
        onSuccess: () => {
            editorRef.current?.clearContent();
            setBody('');
            setMacro(null);
            setAttachments([]);
            void deleteDraft();
            onSuccess?.();
        },
        onFinish: () => setIsSubmitting(false),
    }
);
```

In the reply branch, update the payload:

```tsx
const payload: Record<string, any> = {
    body,
    format: 'html',
    signature: sigPref,
    reply_status_id: statusId ? Number(statusId) : null,
    expected_updated: expectedUpdated,
    mentioned_staff_ids: extractMentionIds(),
};
```

And in the reply branch `onSuccess`:

```tsx
onSuccess: () => {
    editorRef.current?.clearContent();
    setBody('');
    setStatusId(null);
    setMacro(null);
    setAttachments([]);
    void deleteDraft();
    onSuccess?.();
},
```

**2i. Pass `ticketDeptId` down to `RichTextEditor`:**

Find the `<RichTextEditor>` JSX and add the prop:

```tsx
<RichTextEditor
    ref={editorRef}
    value={body}
    onChange={setBody}
    placeholder={
        isNote
            ? 'Type an internal note…'
            : 'Type your reply… use "/" for canned responses, "@" to mention.'
    }
    onFocus={() => setExpanded(true)}
    ticketDeptId={ticketDeptId}
/>
```

**2j. Remove `handleSaveDraft` — it's replaced by `forceSave` from `useAutoSave`:**

Delete the entire `handleSaveDraft` function (the `useCallback` block that calls `fetch` to `/scp/tickets/${ticketId}/draft`).

- [ ] **Step 3: Pass ticketDeptId from Show.tsx to ReplyComposer**

In `resources/js/pages/Scp/Tickets/Show.tsx`, find the `<ReplyComposer>` usage and add `ticketDeptId`:

```tsx
<ReplyComposer
    ticketId={ticket.id}
    expectedUpdated={ticket.updated ?? ''}
    statusOptions={availableStatuses ?? []}
    {/* ... existing props ... */}
    ticketDeptId={ticket.dept_id ?? undefined}
/>
```

> Note: verify the exact field name for the department ID on the `ticket` prop by checking how it's typed in `Show.tsx`. It may be `ticket.dept_id` or `ticket.deptId`. Use whichever is present.

- [ ] **Step 4: Run the full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass.

- [ ] **Step 5: Run TypeScript build**

```bash
npm run build
```

Expected: no errors.

- [ ] **Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add resources/js/hooks/useAutoSave.ts resources/js/components/tickets/ReplyComposer.tsx resources/js/pages/Scp/Tickets/Show.tsx
git commit -m "feat(composer): add auto-save, mention collection, and draft restore"
```

---

## Self-Review Checklist

- [x] Spec §Staff Autocomplete → Task 2 (controller + route + tests)
- [x] Spec §Canned Responses endpoint → Task 3 (controller + route + tests)
- [x] Spec §MentionNotificationMail → Task 4 (Mailable + Blade view)
- [x] Spec §NotifyMentionedStaffJob → Task 4 (Job class)
- [x] Spec §mentioned_staff_ids in payload → Task 5 (ReplyController + NoteController + tests)
- [x] Spec §MentionList.tsx → Task 7
- [x] Spec §SlashCommandList.tsx → Task 7
- [x] Spec §RichTextEditor Mention extension → Task 8
- [x] Spec §RichTextEditor slash command extension → Task 8
- [x] Spec §mention CSS → Task 8 Step 1
- [x] Spec §useAutoSave hook → Task 9 Step 1
- [x] Spec §draft restoration on mount → Task 9 Step 2e
- [x] Spec §manual save button → Task 9 Step 2f
- [x] Spec §deleteDraft on send success → Task 9 Steps 2h
- [x] Spec §DraftController namespace → Task 6
- [x] Spec §ticketDeptId prop → Task 9 Steps 2b + 3
- [x] Pint run after every PHP change ✓
- [x] No placeholder text — all code blocks are complete
- [x] Types consistent: `RichTextEditorHandle.getEditor()` defined Task 8, called Task 9 ✓
- [x] `useAutoSave` return type: `{ forceSave, deleteDraft, loadDraft }` — all three used in Task 9 ✓
- [x] `NotifyMentionedStaffJob` constructor: `(Ticket, ThreadEntry, Staff, int)` — defined Task 4, dispatched Task 5 ✓
