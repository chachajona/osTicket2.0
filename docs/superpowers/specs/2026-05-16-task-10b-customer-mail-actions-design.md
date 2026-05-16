# Task 10b — Customer Mail Actions (Reply + Close-with-Notify) Design Spec

## Context

Task 10b (issue #56) ships the first Laravel-side surfaces in the new SCP that emit customer-visible email. Without atomic ownership transfer from legacy osTicket PHP, the customer receives duplicate emails. The mechanism layer was shipped in PR #73 (`config/mail.event_class_owner` map, `OutboundMailDispatcher`, `OutboundMailGuard` marker-header allow branch, `mail_event_owner` Inertia shared prop). This plan consumes that mechanism to deliver two of the six sub-features in #56:

1. **Staff reply** with optional same-transaction status change
2. **Close-with-notify** (status change + customer notification)
3. **Inbound-reply threading parity** so customer replies still thread back to the right ticket regardless of who originated the outbound mail

The other four #56 sub-features (file attachments, canned responses, field edits with reopen behavior, dynamic-form `__cdata` writes) are deferred to follow-up plans. They don't touch customer mail and can ship without the ownership flip.

## Design

### Architecture overview

**HTTP layer:**

| Endpoint | Status | Notes |
|---|---|---|
| `POST /scp/tickets/{ticket}/replies` | new (`ReplyController::store`) | Reply with optional status change |
| `POST /scp/tickets/{ticket}/status` | extended (`StatusController::store`) | New `notify_user` boolean payload field |

**Services (new):**

- `App\Services\Scp\Tickets\ReplyPostingService` — orchestrates thread-entry write + optional status change in one transaction
- `App\Services\Scp\Mail\LegacyTemplateRenderer` — reads `ost_email_template` row, performs variable substitution
- `App\Services\Scp\Mail\MessageIdGenerator` — deterministic Message-ID + `In-Reply-To` + `References` chain
- `App\Services\Scp\Mail\EmailInfoPersister` — INSERTs into legacy `ost_email_info` so inbound piper's priority-1 lookup matches our outbound

**Services (extended):**

- `App\Services\Scp\Tickets\StatusTransitionService` — `transition(...)` accepts `notify_user: bool`; queues `CloseNotifyMail` when true
- `App\Services\Scp\Mail\OutboundMailDispatcher` — unchanged interface; mailables now implement `ShouldQueueAfterCommit`

**Mailables (new, both `ShouldQueue` + `ShouldQueueAfterCommit`):**

- `App\Mail\StaffReplyMail` — uses legacy template `ticket.reply`
- `App\Mail\CloseNotifyMail` — uses legacy template `note.alert` (legacy has no dedicated `ticket.closed`; close-with-notify alerts route through `note.alert`)

**React (new + extended):**

- `resources/js/components/tickets/ReplyComposer.tsx` (new) — body + format + signature + reply_status_id
- `resources/js/components/tickets/StatusPicker.tsx` (extended) — adds `notify_user` toggle + required comments when notifying
- `resources/js/pages/Scp/Tickets/Show.tsx` (extended) — renders `ReplyComposer` alongside existing `NoteComposer`

**Existing pieces relied on, unchanged:**

- `OutboundMailDispatcher` validates ownership and attaches `X-Ost-Event-Class` marker header
- `OutboundMailGuard` already allows marker-tagged mail when class is laravel-owned
- Inertia `mail_event_owner` shared prop hides UI when class is legacy-owned
- `ActionLogger` audit pattern
- `LockService` + `expected_updated` concurrency token (Task 10a)
- Legacy `ost_email_template` (read), `ost_email_info` (write)

**Out of scope (deferred to follow-up plans):**

- File attachments / S3 writer
- Canned response selector
- Field edits (subject / department / priority / help-topic) and reopen behavior
- Dynamic-form `__cdata` writes for `form_entry_values`
- Inbound mail piper changes (legacy continues to own inbound; our outbound persistence to `email_info` is enough to keep threading correct)

### Data flow: staff reply

**Request:** `POST /scp/tickets/{ticket}/replies`

Inertia form payload:

```
body: string                  required, max 65535
format: 'html' | 'text'       required
signature: 'none' | 'mine' | 'dept'   server-defaulted from staff prefs
reply_status_id: int | null   optional same-tx status change
expected_updated: string      required, concurrency token
```

**`ReplyController::store`:**

1. `authorize('postReply', $ticket)` — new policy method
2. Abort 403 if `config('mail.event_class_owner.reply') !== 'laravel'` (defense-in-depth)
3. Validate payload; reject signature options not currently available to this staff (e.g., `mine` when staff has no signature, `dept` when dept disallows)
4. Delegate to `ReplyPostingService::post(...)`
5. Translate exceptions: `TicketModifiedConcurrentlyException` → 409 JSON; `ForbiddenStatusTransition` → 422 JSON
6. `ActionLogger->record('reply.posted', ...)` on success / `recordFailure(...)` on each failure branch
7. Redirect back with flash on success

Return type: `RedirectResponse|JsonResponse` — same dual-return pattern as existing `StatusController::store`.

**`ReplyPostingService::post(Ticket, Thread, Staff, body, format, signature, reply_status_id, expected_updated)`:**

Inside `DB::connection('legacy')->transaction(...)`:

1. Lock current ticket row; assert `updated == expected_updated` or throw `TicketModifiedConcurrentlyException` (same pattern as `NotePostingService`)
2. Create `thread_entry` row with `type='R'`, `staff_id`, `poster=$staff->displayName()`, `body` (raw, no signature appended — matches legacy), `format`, timestamps
3. Generate Message-ID via `MessageIdGenerator::next($ticket, $entry)` → `<L-{ticket_id}-{entry_id}-{16-hex}@host>`
4. Build `In-Reply-To` (last customer `type='M'` entry's `mid` from `email_info`, or null) and `References` chain via `MessageIdGenerator::references($thread)` (walks prior entries' `email_info`, most-recent on right)
5. `EmailInfoPersister::record($entry, $messageId, $headers, $references)` — INSERTs into legacy `ost_email_info`
6. If `reply_status_id` present: call `StatusTransitionService::transition(...)` with `notify_user=false, comments=null` (silent — the reply mail covers customer notification)
7. `ThreadEventWriter->record('created', ...)`, `SearchIndexer->index('THE', ...)`, `TicketCacheUpdater->touch(...)` — same pattern as `NotePostingService`
8. Resolve `StaffReplyMail` with `$ticket, $entry, $signatureChoice, $messageId, $inReplyTo, $references`
9. `Mail::to($ticket->user->email)->queue($mail)` — `ShouldQueueAfterCommit` defers dispatch until tx commits
10. Return the new `ThreadEntry`

**`StaffReplyMail`** (runs on the queue worker, after commit):

1. Resolve signature text:
   - `'none'` → empty string
   - `'mine'` → `$staff->signature`
   - `'dept'` → `$ticket->dept->signature`
2. `$rendered = $renderer->render('ticket.reply', $ticket, $entry, $signatureText)` → `RenderedMail { subject, body_html, body_text }`
3. `withSymfonyMessage(...)` attaches:
   - `Message-ID: <precomputed>` (override Laravel default)
   - `In-Reply-To: <precomputed>` if set
   - `References: <precomputed>` if set
   - `X-Ost-Event-Class: reply` (so `OutboundMailGuard` allows it)
4. `envelope()` sets `from = $ticket->dept->email->address` with display name = `$entry->staff->displayName()`; `replyTo` same as from (matches legacy `Ticket::replyEmail()`)
5. `content()` ships `body_html` (html) + `body_text` (text)
6. `failed(Throwable)` calls `ActionLogger->record('reply.mail_failed', ...)` with exception class + ticket/entry context; job lands in `failed_jobs` for ops triage

**Atomicity guarantees:**

- All DB writes (thread_entry + email_info + optional status transition) in one transaction
- Mail dispatched only after commit (via `ShouldQueueAfterCommit`); on rollback no mail is queued
- Mail send retried 5x with exponential backoff (10s, 30s, 60s, 120s, 300s); persistent failure observable in `failed_jobs` + audit log
- This strictly improves on legacy, which persists the thread_entry, swallows SMTP errors to `ost_syslog`, and has no retry

### Data flow: close-with-notify

**Request:** `POST /scp/tickets/{ticket}/status` (existing endpoint, extended payload)

Inertia form payload (existing fields plus new `notify_user`):

```
status_id: int                required, must exist
comments: string | null       max 65535; required non-empty when notify_user=true
notify_user: bool             new, default false
expected_updated: string      required
```

**`StatusController::store` (extended):**

1. `authorize('setStatus', $ticket)` — unchanged
2. Validate; when `notify_user=true`: require `comments` non-empty AND target `status_id` must resolve to a closed state (defense-in-depth — UI only shows the toggle on close, but reject here too)
3. Abort 403 if `notify_user=true && config('mail.event_class_owner.close_notify') !== 'laravel'`
4. Delegate to `StatusTransitionService::transition(...)` with `notify_user` passed through
5. On success: redirect back (mail dispatch is async via queue)
6. Existing exception branches unchanged

**`StatusTransitionService::transition(...)` (extended signature):**

Inside the existing transaction:

1. Existing concurrency check (`expected_updated`)
2. Existing permission + transition validity check (`ForbiddenStatusTransition`)
3. Existing status row update + `ThreadEventWriter->record('status.changed', ...)`
4. If `notify_user=true`:
   - Write a `thread_entry` of `type='N'` for the comment (visible in the staff thread; mirrors legacy `Ticket::logNote` from the `setStatus` path)
   - Generate Message-ID via `MessageIdGenerator::next($ticket, $entry)`
   - `EmailInfoPersister::record(...)` so customer replies thread back correctly
   - Build `In-Reply-To` + `References` (same helpers as reply path)
   - `Mail::to($ticket->user->email)->queue(new CloseNotifyMail($ticket, $entry, $comments, $messageId, $inReplyTo, $references))`
5. `ActionLogger->record('status.changed', ...)` with `notify_user` and `mail_queued` flags in audit context

**`CloseNotifyMail`** mirrors `StaffReplyMail`: `ShouldQueue` + `ShouldQueueAfterCommit`, renders via `LegacyTemplateRenderer` using legacy template `note.alert`, `failed()` logs to `ActionLogger` as `close.mail_failed`.

**Why the comment is required when notifying:** The customer mail body comes from the comment (via `%{comments}` substitution). A close-with-notify with empty body would deliver a content-less email — bad UX. Legacy implicitly requires this because the `note.alert` template substitutes `%{comments}` into the body.

### Legacy template rendering

**`LegacyTemplateRenderer::render(string $code, Ticket $ticket, ThreadEntry $entry, ?string $signatureText, ?string $bodyOverride = null): RenderedMail`**

`$code` ∈ `{'ticket.reply', 'note.alert'}`. Returns `RenderedMail { subject, body_html, body_text }`.

**Resolution:**

1. Look up `ost_email_template` row by `code_name = $code` filtered through the dept's template group: `tpl_id = $ticket->dept->tpl_id` if non-zero, else system default group (matches legacy fallback chain)
2. Throw `LegacyTemplateNotFoundException` (controller surfaces as 500 — this is a config integrity error, not user error)

**Substitution table (per template):**

| Variable | Source |
|---|---|
| `%{ticket.number}` | `$ticket->number` |
| `%{ticket.subject}` | `$ticket->subject` |
| `%{ticket.name}` | `$ticket->user->name` |
| `%{ticket.email}` | `$ticket->user->email` |
| `%{ticket.dept.name}` | `$ticket->dept->name` |
| `%{ticket.staff.name}` | `$entry->staff->displayName()` |
| `%{response}` | `$entry->body` (reply path) |
| `%{comments}` | `$bodyOverride ?? $entry->body` (close-notify path) |
| `%{signature}` | resolved `$signatureText` (raw HTML from admin-configured signature) |

Implementation: a single `applySubstitutions(string $body, array $table): string` helper does the regex pass. No template-engine adoption — we mimic legacy's plain-string replace.

**Body text generation:** Schema has `subject` + `body` only (no `body_text` column). Generate text version via `strip_tags($body)` + entity decode — matches legacy's `Format::html2text` fallback at send time.

**Hardening over legacy:** All substitution values are HTML-escaped via `htmlspecialchars` for `body_html` and raw for `body_text`. The single intentional exception is `%{signature}`, which is admin-authored HTML by design. Legacy does NOT escape substitutions, which is a latent XSS surface; the Laravel-side path closes that gap.

**Deferred variables** (not in MVP): `%{attachments}`, `%{ticket.thread}`, `%{recipient.ticket_link}`. None of these are required for `ticket.reply` or `note.alert` baseline rendering.

### Message-ID and RFC 5322 threading

**Outbound headers** (set by mailables via `withSymfonyMessage`):

- `Message-ID: <L-{ticket_id}-{entry_id}-{16-hex}@{config.mail.from.address-host}>` — generated deterministically up front in the transaction so we can persist it before queueing
- `In-Reply-To: <prior-mid>` — Message-ID of the most recent inbound (`type='M'`) thread entry, looked up from `email_info` by `thread_entry_id`. Null if no prior customer message.
- `References: <chain>` — space-separated chain of all prior thread entries' `mid` values, most recent on the right. Built by `MessageIdGenerator::references($thread)` walking entries in `created` order.

**Persistence to `ost_email_info`:**

Inside the same transaction as the thread_entry write:

```sql
INSERT INTO ost_email_info (thread_entry_id, email_id, mid, headers, recipients)
VALUES (:entry, :dept_email, :mid, :headers, :recipient)
```

**Contract:** a row keyed by `thread_entry_id` with the generated `mid` so legacy's inbound piper priority-1 lookup (`email_info__mid` match) finds it when the customer replies. The exact column list (`headers`, `recipients`, `email_id`, etc.) is read from the `ost_email_info` migration during implementation via Laravel Boost's `database-schema` tool; the spec does not lock the column names because they're an implementation detail of mirroring the legacy table.

**Inbound contract (legacy, unchanged):** customer reply hits legacy mail piper → pipes through priority chain: (1) direct `email_info` mid lookup → (2) `In-Reply-To` / `References` header parse → (3) decoded legacy-format Message-ID → (4) embedded body tag → (5) passive threading → (6) `[#1234]` subject match. Our outbound writes a row that satisfies priority (1), so customer replies thread regardless of which system sent the original.

### Authorization

- `TicketPolicy::postReply(Staff, Ticket): bool` — new method, same shape as existing `postNote`: staff is assignee, in ticket's dept, or has global access
- `TicketPolicy::setStatus` — existing, covers close-with-notify (the `notify_user` toggle adds no new auth surface; staff who can close can notify)
- Both controllers re-check `mail.event_class_owner.*` as defense in depth. Inertia hides UI; policy is auth-only; controller is the final ownership gate.

### Audit

`ActionLogger` records each action with success/failure outcome:

- `reply.posted` — success at controller return; `outcome='success'`, `httpStatus=302`
- `reply.mail_failed` — from `StaffReplyMail::failed()` after queue retries exhausted; includes exception class
- `status.changed` — existing entry, extended context: `notify_user`, `mail_queued`
- `close.mail_failed` — from `CloseNotifyMail::failed()`
- Failure controller branches record via `recordFailure(...)` with `errorClass` and matching `httpStatus`

Each carries `ticket_id` and (where applicable) `entry_id`.

### Frontend integration

`ReplyComposer.tsx` (new):

- 3-way `<Select>` for signature, options filtered server-side per current staff: always includes `none`; `mine` only when staff has a signature; `dept` only when dept allows append AND has one
- Initial value: `staff.default_signature_type` from Inertia page props (falls back to `none` when unset)
- Body editor: existing markdown/html composer used by `NoteComposer`
- Format toggle: same as `NoteComposer`
- Optional status-change dropdown (`reply_status_id`) populated from the existing status options the page already loads
- Submit button labeled "Reply" when no status change; "Reply and [Status]" when one is selected (matches legacy verbiage)
- Hidden from page render when `mail_event_owner.reply !== 'laravel'`

`StatusPicker.tsx` (extended):

- Existing dialog gains a `notify_user` checkbox **only** when the selected target is a closed state AND `mail_event_owner.close_notify === 'laravel'`
- When checked, the existing `comments` field becomes required (client-side message: "A note is required when notifying the customer")

`Show.tsx` (extended):

- Imports and renders `ReplyComposer` next to the existing `NoteComposer`
- Existing affordances unchanged

### Rollout / rollback

Reuses the env-var mechanism from PR #73:

1. Merge this plan → deploy with `MAIL_OWNER_REPLY=legacy`, `MAIL_OWNER_CLOSE_NOTIFY=legacy`. UI buttons hidden, controllers return 403 if hit directly. Zero customer-visible change.
2. Flip `MAIL_OWNER_REPLY=laravel` in staging → 24h soak → flip in prod. Manual canary per `docs/runbooks/phase-2c-mail-swap.md`. Reply button appears in new SCP; reply mail now flows from Laravel only.
3. Repeat for `MAIL_OWNER_CLOSE_NOTIFY=laravel` once reply is stable.
4. Rollback per class = env var back to `legacy` + redeploy. UI button disappears; controller 403s; in-flight queued mail still dispatches (acceptable — the marker header + guard already cleared those for send before the redeploy).

**Pre-flip parity check (per runbook):** send one reply via legacy SCP and one via new SCP to the same test customer inbox, side-by-side compare rendered HTML. If they don't match byte-for-byte (modulo Message-ID and timestamps), debug `LegacyTemplateRenderer` before flipping prod.

## Critical files

**New:**

- `app/Http/Controllers/Scp/Tickets/ReplyController.php`
- `app/Services/Scp/Tickets/ReplyPostingService.php`
- `app/Services/Scp/Mail/LegacyTemplateRenderer.php`
- `app/Services/Scp/Mail/MessageIdGenerator.php`
- `app/Services/Scp/Mail/EmailInfoPersister.php`
- `app/Exceptions/Scp/LegacyTemplateNotFoundException.php`
- `app/Mail/StaffReplyMail.php`
- `app/Mail/CloseNotifyMail.php`
- `app/Mail/RenderedMail.php` (value object: subject + body_html + body_text)
- `postReply` method added to existing `app/Policies/TicketPolicy.php` (matches existing `postNote` convention)
- `resources/js/components/tickets/ReplyComposer.tsx`
- Tests listed in Verification

**Modified:**

- `app/Http/Controllers/Scp/Tickets/StatusController.php` — accept `notify_user` payload
- `app/Services/Scp/Tickets/StatusTransitionService.php` — accept `notify_user`, queue `CloseNotifyMail` when true
- `app/Policies/TicketPolicy.php` — add `postReply` method
- `app/Providers/AppServiceProvider.php` — bind new services as singletons (mirrors existing dispatcher bind)
- `routes/web.php` (or appropriate routes file) — add `POST /scp/tickets/{ticket}/replies`
- `resources/js/pages/Scp/Tickets/Show.tsx` — render `ReplyComposer`
- `resources/js/components/tickets/StatusPicker.tsx` — add `notify_user` checkbox + required-comments enforcement

**Reused (no change):**

- `app/Services/Scp/Mail/OutboundMailDispatcher.php`
- `app/Mail/OutboundMailGuard.php`
- `app/Mail/EventClassHeader.php`
- `app/Services/Scp/Tickets/ActionLogger.php`
- `app/Services/Scp/Tickets/DraftService.php` (drafts for reply composer use the same key pattern: `ticket.reply.{ticket_id}`)
- `app/Services/Scp/Tickets/LockService.php`
- `app/Services/Scp/Tickets/ThreadEventWriter.php`
- `app/Services/Scp/Tickets/SearchIndexer.php`
- `app/Services/Scp/Tickets/TicketCacheUpdater.php`
- `app/Http/Middleware/HandleInertiaRequests.php` (already exposes `mail_event_owner`)

## Verification

### Unit / feature tests

```
tests/Feature/Tickets/ReplyControllerTest.php
  - posts a reply with reply-only fields → 302, thread_entry written, mail queued
  - posts a reply with reply_status_id → thread_entry + status change in one tx
  - posts when mail.event_class_owner.reply == 'legacy' → 403, no thread_entry written
  - concurrent reply (mismatched expected_updated) → 409 JSON
  - forbidden status transition + reply → 422 JSON, NO thread_entry (rollback)
  - validates signature option against staff/dept availability → 422 on invalid

tests/Feature/Tickets/CloseNotifyTest.php
  - status=closed + notify_user=true + comments → thread_entry (N) + mail queued
  - status=closed + notify_user=true + EMPTY comments → 422
  - status=closed + notify_user=false → no mail dispatch
  - status=closed + notify_user=true + ownership=legacy → 403
  - notify_user=true + non-closed status_id → 422

tests/Unit/Services/Scp/Mail/LegacyTemplateRendererTest.php
  - renders ticket.reply with the full variable surface (Pest dataset)
  - renders note.alert with %{comments} substitution
  - falls back to default template group when dept.tpl_id=0
  - throws LegacyTemplateNotFoundException when no matching row
  - escapes <script> in subject → not present in rendered body_html
  - synthesizes body_text from body via strip_tags + entity decode

tests/Unit/Services/Scp/Mail/MessageIdGeneratorTest.php
  - generates <L-{ticket}-{entry}-{16-hex}@host> matching the documented format
  - References chain walks email_info entries in created order, most-recent on right
  - In-Reply-To selects last type='M' entry's mid, null when none

tests/Feature/Mail/CustomerReplyThreadingTest.php
  - end-to-end: post reply, assert email_info row written with our mid,
    assert Message-ID/In-Reply-To/References on rendered email match
  - given a customer reply with In-Reply-To matching our mid, legacy lookup
    returns the right thread_entry (fixture-driven, no real piper run)

tests/Feature/Mail/QueuedMailFailureTest.php
  - simulates SMTP failure on StaffReplyMail → after retries exhausted,
    audit row reply.mail_failed written, job in failed_jobs
  - simulates SMTP failure on CloseNotifyMail → close.mail_failed audit row

tests/Browser/ReplyComposerSmoke.php (Pest 4 browser)
  - logs in as staff, opens ticket, posts reply with signature='mine'
    and reply_status_id=closed → success toast, thread updated, no JS errors
  - signature dropdown only shows 'mine' when staff has signature configured
  - notify_user checkbox only visible when target status is closed
```

### Manual canary (per runbook)

1. Staging: set `MAIL_OWNER_REPLY=laravel`, post a reply via new SCP, verify customer inbox receives exactly one email, legacy SCP shows no parallel send
2. Staging: post a reply with `reply_status_id` set to a closed status, verify single customer email AND status transition recorded
3. Staging: set `MAIL_OWNER_CLOSE_NOTIFY=laravel`, transition a ticket to closed with `notify_user=true` and comments, verify exactly one customer email rendered with the comments body
4. Rollback test: set both flags back to `legacy`, redeploy, confirm Reply button disappears from new SCP and `notify_user` checkbox disappears from the close dialog

### Smoke checks post-deploy

- `php artisan config:show mail.event_class_owner` reflects the deployed flag values
- `php artisan tinker --execute 'app(\App\Services\Scp\Mail\LegacyTemplateRenderer::class);'` returns the singleton
- `php artisan queue:work --once` processes a queued mail without error in staging

## Scope guard

This plan deliberately covers only **reply** and **close-with-notify** mail-touching actions, plus the threading parity that goes with them. The remaining four #56 sub-features each become their own plan:

- File attachments + S3 writer
- Canned response selector (depends on Stage 2.B canned-response CRUD)
- Field edits (subject / department / priority / help-topic) with controlled reopen behavior
- Dynamic-form `__cdata` writes for `form_entry_values`

These are independent of the mail ownership flip and can ship before or after Task 10b proper.
