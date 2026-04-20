# osTicket Dynamic Forms Architecture Study

## Database Structures

### ost_form
- id (int, PK, auto_increment)
- pid (int, nullable)
- type (varchar(8), default 'G')
- flags (int, default 1)
- title (varchar(255))
- instructions (varchar(512), nullable)
- name (varchar(64))
- notes (text, nullable)
- created (datetime)
- updated (datetime)

### ost_form_field
- id (int, PK, auto_increment)
- form_id (int, FK to ost_form.id)
- flags (int, nullable, default 1)
- type (varchar(255), default 'text')
- label (varchar(255))
- name (varchar(64))
- configuration (text, nullable)
- sort (int, default 0)
- hint (varchar(512), nullable)
- created (datetime)
- updated (datetime)

### ost_form_entry
- id (int, PK, auto_increment)
- form_id (int, FK to ost_form.id)
- object_id (int, nullable)
- object_type (char(1), default 'T')
- sort (int, default 1)
- extra (text, nullable)
- created (datetime)
- updated (datetime)

### ost_form_entry_values
- entry_id (int, PK, FK to ost_form_entry.id)
- field_id (int, PK, FK to ost_form_field.id)
- value (text, nullable)
- value_id (int, nullable)

### ost_ticket__cdata
- ticket_id (int, PK)
- subject (mediumtext, nullable)
- priority (mediumtext, nullable)
- shortdesc (mediumtext, nullable)
- callerid (mediumtext, nullable)
- transid (mediumtext, nullable)
- transdt (mediumtext, nullable)
- ewallet (mediumtext, nullable)
- bankacc (mediumtext, nullable)
- shopaddr (mediumtext, nullable)
- provider (mediumtext, nullable)
- resolution (mediumtext, nullable)

## Legacy Code Patterns

The dynamic forms system is implemented in `osticket/include/class.dynamic_forms.php` using the VerySimpleModel ORM paradigm. Key classes:

- **DynamicForm**: Represents form templates, extends VerySimpleModel. Handles form creation, field management, and view rebuilding.
- **DynamicFormField**: Represents individual fields, extends VerySimpleModel. Manages field configuration, validation, and visibility flags.
- **DynamicFormEntry**: Represents form submissions/instances, extends VerySimpleModel. Links forms to objects (tickets, users) and manages answers.
- **DynamicFormEntryAnswer**: Represents individual field answers, extends VerySimpleModel. Stores value and value_id for each field in an entry.

Patterns include:
- Multiple inheritance via __call() delegation to Form API objects
- Signal-based updates (model.created/updated/deleted signals trigger view rebuilds)
- Soft deletion for forms and fields (flag-based instead of actual deletion)
- Configuration stored as JSON in 'configuration' field
- Flags system for permissions (view, edit, required) for staff/users

## __cdata Materialized Views

The __cdata tables are MySQL materialized views created dynamically to flatten EAV custom data into relational columns for efficient querying. 

- **Creation**: `buildDynamicDataView()` creates a table via `CREATE TABLE ... AS SELECT` using `getCrossTabQuery()`
- **Query Structure**: SELECT entry.object_id, MAX(CASE WHEN field.id='X' THEN value ELSE NULL) as field_name FROM form_entry JOIN form_entry_values ON ... GROUP BY entry.object_id
- **Updates**: Triggered by signals on answer creation/update. Uses INSERT ... ON DUPLICATE KEY UPDATE to maintain the view
- **Rebuild**: `rebuildDynamicDataViews()` drops and recreates views, called during cron or field changes
- **Purpose**: Enables SQL queries on custom fields without complex joins, used for filtering/searching

## EAV (Entity-Attribute-Value) Pattern Usage

The system uses EAV for flexible custom fields:

- **Entities**: Tickets, Users, Organizations (via object_type in entries)
- **Attributes**: DynamicFormField records define field metadata
- **Values**: Stored in ost_form_entry_values with entry_id + field_id as composite PK

Relationships:
- Forms contain Fields (1:many)
- Entries link Forms to Objects (tickets) (many:many via entries)
- Answers link Entries to Fields with Values (many:many via answers)

This allows arbitrary custom fields per form without schema changes, but requires complex queries for retrieval.
# __cdata Analysis Findings

## ticket__cdata Table Structure
- Columns: ticket_id (int, PK), subject (string), priority (string), shortdesc (string), callerid (string), transid (string), transdt (string), ewallet (string), bankacc (string), shopaddr (string), provider (string), resolution (string)
- Sample data shows custom fields populated with values like 'Ví điện tử gặp vấn đề' for subject, priority 2, etc.

## Population and Updates
- Based on legacy patterns, populated via materialized view rebuilds triggered by signals on form entry changes.
- In this Laravel codebase, no explicit maintenance code found; likely inherited from legacy DB.

## Other __cdata Tables
- No evidence of user__cdata, task__cdata, organization__cdata in the codebase or models.
- Only TicketCdata model exists.

## Column Naming Conventions
- Standard fields like subject, priority use human-readable names.
- Custom fields appear to use abbreviated or specific names (e.g., shortdesc, callerid), possibly based on form field labels or IDs.

## Maintenance Code
- Not present in this Laravel repo; references in notepad point to legacy class.dynamic_forms.php for buildDynamicDataView() and rebuild logic.

# Dynamic Forms Benchmark Findings (Task 0.5)

## Architecture Decision: JsonAccessorApproach (Approach B) Selected

Ran `php artisan benchmark:dynamic-forms --iterations=100` on real database. Ticket ID 159.

### Performance Results (single ticket, warm cache)
- Approach A (direct cdata): 0.567ms/iter, 2 DB queries
- Approach B (JsonAccessor warm): 0.125ms/iter, 1 DB query  ← WINNER
- Approach C (EAV warm): 0.178ms/iter, 1 DB query

### Critical Discovery: EAV vs __cdata Field Coverage
- ticket__cdata returns 8 fields for this ticket
- form_entry_values (EAV) returns only 2 fields (Subject, Priority)
- Conclusion: Most custom field values are ONLY in __cdata; EAV tables have sparse entries
- This means EAV-only approach is insufficient for production use

### EAV Value Resolution Difference
- Approach B cdata: Priority = "2" (raw integer)
- Approach C EAV: Priority = "Normal" (resolved display value via value_id)
- EAV stores both `value` (raw) and `value_id` (for select fields pointing to list items)

### Cache Strategy
- Use `file` or `redis` cache store for label map (NOT `database` — no ost_cache table in legacy DB)
- Default CACHE_STORE=database in .env causes failure; prototypes use configurable store
- Added `setCacheStore(string $store)` to both JsonAccessorApproach and EavApproach

### Prototype Files Created
- app/Prototype/DynamicForms/CdataApproach.php
- app/Prototype/DynamicForms/JsonAccessorApproach.php
- app/Prototype/DynamicForms/EavApproach.php
- app/Console/Commands/BenchmarkDynamicForms.php
- docs/dynamic-forms-strategy.md

### Task 0.6 Implementation Guidance
- Implement Approach B as Eloquent Attribute accessor on Ticket model
- Cache label map in file/redis store, NOT database store
- Flush cache on form_field changes
- For fields needing type metadata (rendering), use EavApproach::getCustomFieldsWithMeta()

# Task 1 Model Creation — Completed Learnings

## Models Created (Task 1 — 22 new models this session)

### Queue Domain (7 models)
- `Queue` — self-referential parent/children, `hasMany` QueueColumn + QueueSort
- `QueueColumn` — simple child of Queue
- `QueueColumns` — 3-column composite PK `(queue_id, column_id, staff_id)`, `$incrementing=false`
- `QueueConfig` — composite PK `(queue_id, staff_id)`, `$incrementing=false`
- `QueueExport` — simple child of Queue
- `QueueSort` — simple child of Queue
- `QueueSorts` — composite PK `(queue_id, sort_id)`, `$incrementing=false`

### Storage Domain (3 models)
- `File` — `hasMany` FileChunk + Attachment
- `FileChunk` — composite PK `(file_id, chunk_id)`, `$incrementing=false`
- `Attachment` — `belongsTo` File

### Lookup/System Domain (12 models)
- `Lock` — PK: `lock_id`, `belongsTo` Staff
- `ApiKey` — standard, no relations
- `Session` — PK: `session_id` (string), `$incrementing=false`, `$keyType='string'`
- `Plugin` — `hasMany` PluginInstance
- `PluginInstance` — `belongsTo` Plugin
- `Sequence` — standalone, no relations
- `Translation` — standalone, no relations
- `Draft` — `belongsTo` Staff
- `Note` — `belongsTo` Staff
- `Syslog` — PK: `log_id`, standalone
- `Event` — standalone (referenced by ThreadEvent)
- `Search` — table: `_search` (note leading underscore), composite PK `(object_type, object_id)`, `$incrementing=false`

## Key Patterns Confirmed

- All 22 models extend `LegacyModel`, connection `legacy`, `$timestamps=false`, `$guarded=[]`
- `@property` PHPDoc annotations are **necessary** on all models — legacy columns not statically declared; required for IDE type inference; mandated by project plan
- Forward-reference LSP errors ("Undefined type 'App\Models\X'") during batch creation resolve automatically once the referenced model file is written — they are expected and not actual bugs
- Pre-existing errors in `osticket/include/class.dynamic_forms.php` are unrelated legacy PHP code errors; ignore them entirely
- Table `ost__search` uses an underscore prefix in the short name: `protected $table = '_search'`
- `Session` model name is safe — Laravel's `Illuminate\Support\Facades\Session` is a facade, not a model class; no collision in `App\Models\` namespace

## Characterization Tests Created
- `tests/Characterization/EloquentQueueTest.php` — 9 tests (Queue domain)
- `tests/Characterization/EloquentStorageTest.php` — 7 tests (Storage domain)
- `tests/Characterization/EloquentSystemTest.php` — 14 tests (Lookup/System domain)
- `tests/Characterization/EloquentKBFormStaffTest.php` — 22 tests (KB/Form/Staff/Org/Email/Filter domains)

## Documentation Created
- `docs/models.md` — full relationship map for all 65+ models grouped by domain

# Task 2 — Artisan Commands for Cron Jobs

## Created Commands
1. **FetchMailCommand** (`tickets:fetch-mail`)
   - Signature: `tickets:fetch-mail {--dry-run}`
   - Stub implementation for Task 3
   - Scheduled: every 5 minutes

2. **CheckOverdueTicketsCommand** (`tickets:check-overdue`)
   - Signature: `tickets:check-overdue {--dry-run}`
   - Queries: `isoverdue=0 AND closed IS NULL AND duedate < now()`
   - Updates: `isoverdue='1'` for overdue tickets
   - Scheduled: every 5 minutes

3. **PurgeLogsCommand** (`system:purge-logs`)
   - Signature: `system:purge-logs {--dry-run} {--days=90}`
   - Queries Syslog model where `created < NOW() - INTERVAL {days} DAY`
   - Scheduled: daily at 03:00

4. **CleanupDraftsCommand** (`drafts:cleanup`)
   - Signature: `drafts:cleanup {--dry-run} {--days=30}`
   - Queries Draft model where `created < NOW() - INTERVAL {days} DAY`
   - Scheduled: daily

5. **CleanupFilesCommand** (`files:cleanup`)
   - Signature: `files:cleanup {--dry-run}`
   - Queries: File where `id NOT IN (SELECT file_id FROM attachment)`
   - Note: File table PK is `id`, not `file_id`
   - Scheduled: weekly

## Database Schema Discoveries

### ost_ticket Table
- Primary key: `ticket_id`
- Status tracking: `status_id` (not just `status`)
- Overdue flag: `isoverdue` (0 = not overdue, 1 = overdue)
- Closure tracking: `closed` field (NULL when open, populated when closed)

### ost_file Table
- Primary key: `id` (not `file_id`)
- Columns: id, ft, bk, type, size, key, signature, name, attrs, created
- File chunks: `file_chunk` with `(file_id, chunk_id)` composite PK

### ost_attachment Table
- Primary key: `id`
- Foreign key: `file_id` references `ost_file.id`
- Links files to objects (tickets, etc.)

## Command Pattern in Laravel
- All commands extend `Illuminate\Console\Command`
- Signature: `protected $signature = 'command:name {--option}'`
- Option access: `$this->option('option-name')`
- Dry-run pattern: Check `$this->option('dry-run')` before destructive operations
- Output: Use `$this->info()`, `$this->line()`, `$this->comment()` for CLI feedback
- Status codes: Return `self::SUCCESS` or `self::FAILURE`

## Schedule Registration
All commands registered in `routes/console.php`:
- Every 5 minutes: `->everyFiveMinutes()`
- Daily: `->daily()`
- Daily at specific time: `->dailyAt('HH:MM')`
- Weekly: `->weekly()`

## Testing Results
- All 5 commands tested with `--dry-run` flag
- All queries validated against actual database
- Dry-run mode lists what would be changed without persisting
- No errors in final implementation

# Task 3 — Email-to-Ticket Pipeline

## Package Installed
- `webklex/laravel-imap` ^6.2 — wraps PHP IMAP extension into a clean OO API

## EmailAccount Table Schema
- `type` column: `'mailbox'` for IMAP fetch accounts, `'smtp'` for outgoing
- `active` column: 1 = enabled, 0 = disabled
- `host` can be empty string for disabled accounts; must filter `host != ''`
- `postfetch` column: `'delete'` to delete after processing; otherwise archive if `archivefolder` is set
- `auth_id` = username, `auth_bk` = password
- `protocol` = 'IMAP' (uppercase in DB)
- `encryption` values: 'ssl', 'tls', '' (none)

## thread_entry_email Is the Key for Thread Matching
- `ost_thread_entry_email.mid` stores the Message-ID of each email sent/received
- Thread matching: look up `In-Reply-To` and `References` header values in `thread_entry_email.mid`
- If found → reply to existing thread; otherwise → create new ticket

## thread_entry Column Notes
- `type` = 'M' for user messages, 'R' for staff response, 'N' for internal note
- `staff_id` = 0 for user-originated messages
- `poster` = display name of sender
- `source` = 'Email' for email-originated entries
- `format` = 'html' or 'text' depending on email body

## Ticket Table Notes
- `source` = 'Email' for email-created tickets
- `email_id` FK to ost_email to track which mailbox received it
- `lastupdate` used to track last message time (not `lastmessage`/`lastresponse` in this schema)

## Sequence Table Notes
- `id=1` is the General Ticket sequence
- `padding=0` means no zero-padding in this installation (use raw number)
- `next` + `increment` atomically updated with `lockForUpdate()`

## File Storage Pattern
- `ost_file.key` used for deduplication (md5 hash of content)
- `ost_file.ft` = 'P' for permanently stored files
- `ost_attachment.object_type` = 'H' for thread entry attachments
- `ost_attachment.object_id` = thread_entry.id

## Bounce Detection Patterns
- `Auto-Submitted: auto-replied|auto-generated` header
- `X-Auto-Response-Suppress` header
- `Content-Type: multipart/report; report-type=delivery-status`
- `Content-Type: message/delivery-status`
- From address matching `/^(mailer-daemon|postmaster|noreply|no-reply)@/i`

## User Resolution
- Check `ost_user_email.address` first to find existing user
- If not found, create `ost_user` + `ost_user_email` rows
- `ost_user.default_email_id` can be 0 initially (update later if needed)
