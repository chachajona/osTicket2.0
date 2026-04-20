# Legacy osTicket Eloquent Model Relationships

All models extend `App\Models\LegacyModel` (connection: `legacy`, `$timestamps = false`, `$guarded = []`).
Table prefix `ost_` is applied via `config/database.php` — models use short table names.

---

## Ticket Domain

### `Ticket` — table: `ticket`, PK: `ticket_id`
- `belongsTo` **Staff** via `staff_id`
- `belongsTo` **Department** via `dept_id`
- `belongsTo` **Team** via `team_id`
- `belongsTo` **TicketStatus** via `status_id`
- `belongsTo` **TicketPriority** via `priority_id`
- `belongsTo` **HelpTopic** via `topic_id`
- `belongsTo` **Sla** via `sla_id`
- `hasOne` **Thread** (polymorphic, `object_type='T'`)
- `hasOne` **TicketCdata** via `ticket_id`

### `TicketCdata` — table: `ticket__cdata`, PK: `ticket_id`
- `belongsTo` **Ticket** via `ticket_id`

### `TicketStatus` — table: `ticket_status`, PK: `id`

### `TicketPriority` — table: `ticket_priority`, PK: `priority_id`

### `Task` — table: `task`, PK: `id`
- `belongsTo` **Staff** via `staff_id`
- `belongsTo` **Department** via `dept_id`
- `belongsTo` **Team** via `team_id`
- `hasOne` **Thread** (polymorphic, `object_type='A'`)
- `hasOne` **TaskCdata** via `task_id`

### `TaskCdata` — table: `task__cdata`, PK: `task_id`
- `belongsTo` **Task** via `task_id`

### `TicketComplaint` — table: `ticket_complaint`, PK: `id`
### `TicketReason` — table: `ticket_reason`, PK: `id`
### `TicketResolution` — table: `ticket_resolution`, PK: `id`
### `TicketService` — table: `ticket_service`, PK: `id`

### `ComplaintResolution` — table: `complaint_resolution`, composite PK: `(service_id, complaint_id, resolution_id, source)`
- `$incrementing = false`

---

## Thread Domain

### `Thread` — table: `thread`, PK: `id`
- `hasMany` **ThreadEntry** via `thread_id`
- `hasMany` **ThreadEvent** via `thread_id`
- `hasMany` **ThreadCollaborator** via `thread_id`

### `ThreadEntry` — table: `thread_entry`, PK: `id`
- `belongsTo` **Thread** via `thread_id`
- `hasOne` **ThreadEntryEmail** via `id`
- `hasOne` **ThreadEntryMerge** via `id`

### `ThreadCollaborator` — table: `thread_collaborator`, PK: `id`
- `belongsTo` **Thread** via `thread_id`
- `belongsTo` **LegacyUser** via `user_id`

### `ThreadReferral` — table: `thread_referral`, PK: `id`
- `belongsTo` **Thread** via `thread_id`

### `ThreadEvent` — table: `thread_event`, PK: `id`
- `belongsTo` **Thread** via `thread_id`
- `belongsTo` **Event** via `event_id`
- `belongsTo` **Staff** via `staff_id`

### `ThreadEntryEmail` — table: `thread_entry_email`, PK: `id`
- `belongsTo` **ThreadEntry** via `id`

### `ThreadEntryMerge` — table: `thread_entry_merge`, PK: `id`
- `belongsTo` **ThreadEntry** via `id`

---

## Staff / User Domain

### `Staff` — table: `staff`, PK: `staff_id`
- `belongsTo` **Department** via `dept_id`
- `hasMany` **Ticket** (assigned) via `staff_id`

### `Department` — table: `department`, PK: `id`
- `hasMany` **Staff** via `dept_id`
- `hasMany` **Ticket** via `dept_id`

### `Team` — table: `team`, PK: `team_id`
- `belongsTo` **Staff** (lead) via `lead_id`
- `belongsToMany` **Staff** via `team_member` pivot

### `TeamMember` — table: `team_member`, composite PK: `(team_id, staff_id)`
- `$incrementing = false`

### `Role` — table: `role`, PK: `id`

### `Group` — table: `group`, PK: `id`
- `belongsTo` **Role** via `role_id`

### `StaffDeptAccess` — table: `staff_dept_access`, composite PK: `(staff_id, dept_id)`
- `$incrementing = false`

### `LegacyUser` — table: `user`, PK: `id`
- `hasOne` **UserEmail** (default) via `default_email_id` (inverse)
- `hasMany` **UserEmail** via `user_id`
- `hasOne` **UserAccount** via `user_id`
- `hasOne` **UserCdata** via `user_id`
- `belongsTo` **Organization** via `org_id`
- `hasMany` **Ticket** via `user_id`

### `UserEmail` — table: `user_email`, PK: `id`
- `belongsTo` **LegacyUser** via `user_id`

### `UserAccount` — table: `user_account`, PK: `id`
- `belongsTo` **LegacyUser** via `user_id`

### `UserCdata` — table: `user__cdata`, PK: `user_id`
- `belongsTo` **LegacyUser** via `user_id`

---

## Organization Domain

### `Organization` — table: `organization`, PK: `id`
- `hasOne` **OrganizationCdata** via `org_id`
- `hasMany` **LegacyUser** via `org_id`

### `OrganizationCdata` — table: `organization__cdata`, PK: `org_id`
- `belongsTo` **Organization** via `org_id`

---

## KB / Form / Help Domain

### `HelpTopic` — table: `help_topic`, PK: `topic_id`
- `belongsTo` **HelpTopic** (self, parent) via `topic_pid`
- `hasMany` **HelpTopic** (children) via `topic_pid`
- `belongsTo` **Department** via `dept_id`
- `belongsTo` **Staff** via `staff_id`
- `belongsTo` **Team** via `team_id`
- `belongsTo` **Sla** via `sla_id`
- `belongsToMany` **Faq** via `faq_topic` pivot
- `hasMany` **HelpTopicForm** via `topic_id`

### `HelpTopicForm` — table: `help_topic_form`, PK: `id`
- `belongsTo` **HelpTopic** via `topic_id`
- `belongsTo` **DynamicForm** (form) via `form_id`

### `Faq` — table: `faq`, PK: `faq_id`
- `belongsTo` **FaqCategory** via `category_id`
- `belongsToMany` **HelpTopic** via `faq_topic` pivot

### `FaqCategory` — table: `faq_category`, PK: `category_id`
- `belongsTo` **FaqCategory** (self, parent) via `parent_id`
- `hasMany` **FaqCategory** (children) via `parent_id`
- `hasMany` **Faq** via `category_id`

### `FaqTopic` — table: `faq_topic`, composite PK: `(faq_id, topic_id)`
- `$incrementing = false`

### `DynamicForm` — table: `form`, PK: `id`
- `hasMany` **FormField** via `form_id`
- `hasMany` **FormEntry** via `form_id`

### `FormField` — table: `form_field`, PK: `id`
- `belongsTo` **DynamicForm** via `form_id`

### `FormEntry` — table: `form_entry`, PK: `id`
- `belongsTo` **DynamicForm** via `form_id`
- `hasMany` **FormEntryValues** via `entry_id`

### `FormEntryValues` — table: `form_entry_values`, composite PK: `(entry_id, field_id)`
- `$incrementing = false`
- `belongsTo` **FormEntry** via `entry_id`

### `FormMapping` — table: `form_mapping`, composite PK: `(service_id, complaint_id, reason_id, source)`
- `$incrementing = false`

### `DynamicList` — table: `list`, PK: `id`
- `hasMany` **ListItem** via `list_id`

### `ListItem` — table: `list_items`, PK: `id`
- `belongsTo` **DynamicList** via `list_id`

---

## System Domain

### `Sla` — table: `sla`, PK: `id`
- `belongsTo` **Schedule** via `schedule_id`
- `hasMany` **Ticket** via `sla_id`

### `Schedule` — table: `schedule`, PK: `id`
- `hasMany` **ScheduleEntry** via `schedule_id`

### `ScheduleEntry` — table: `schedule_entry`, PK: `id`
- `belongsTo` **Schedule** via `schedule_id`

### `EmailModel` — table: `email`, PK: `email_id`
- `belongsTo` **Department** via `dept_id`
- `belongsTo` **EmailAccount** via `mail_accountid` / `smtp_accountid`

### `EmailAccount` — table: `email_account`, PK: `id`
- `belongsTo` **EmailModel** via `email_id`

### `EmailTemplate` — table: `email_template`, PK: `id`
- `belongsTo` **EmailTemplateGroup** via `tpl_id`

### `EmailTemplateGroup` — table: `email_template_group`, PK: `tpl_id`
- `hasMany` **EmailTemplate** via `tpl_id`

### `Filter` — table: `filter`, PK: `id`
- `hasMany` **FilterRule** via `filter_id`
- `hasMany` **FilterAction** via `filter_id`

### `FilterRule` — table: `filter_rule`, PK: `id`
- `belongsTo` **Filter** via `filter_id`

### `FilterAction` — table: `filter_action`, PK: `id`
- `belongsTo` **Filter** via `filter_id`

### `Config` — table: `config`, PK: `id`
- Scope: `namespace($ns)` for filtering by namespace

### `Content` — table: `content`, PK: `id`

### `CannedResponse` — table: `canned_response`, PK: `canned_id`
- `belongsTo` **Department** via `dept_id`

---

## Queue Domain

### `Queue` — table: `queue`, PK: `id`
- `belongsTo` **Queue** (self, parent) via `parent_id`
- `hasMany` **Queue** (children) via `parent_id`
- `hasMany` **QueueColumn** via `queue_id`
- `hasMany` **QueueSort** via `queue_id`

### `QueueColumn` — table: `queue_column`, PK: `id`
- `belongsTo` **Queue** via `queue_id`

### `QueueColumns` — table: `queue_columns`, composite PK: `(queue_id, column_id, staff_id)`
- `$incrementing = false`
- `belongsTo` **Queue** via `queue_id`
- `belongsTo` **QueueColumn** via `column_id`
- `belongsTo` **Staff** via `staff_id`

### `QueueConfig` — table: `queue_config`, composite PK: `(queue_id, staff_id)`
- `$incrementing = false`
- `belongsTo` **Queue** via `queue_id`
- `belongsTo` **Staff** via `staff_id`

### `QueueExport` — table: `queue_export`, PK: `id`
- `belongsTo` **Queue** via `queue_id`

### `QueueSort` — table: `queue_sort`, PK: `id`
- `belongsTo` **Queue** via `queue_id`

### `QueueSorts` — table: `queue_sorts`, composite PK: `(queue_id, sort_id)`
- `$incrementing = false`
- `belongsTo` **Queue** via `queue_id`
- `belongsTo` **QueueSort** via `sort_id`

---

## Storage Domain

### `File` — table: `file`, PK: `id`
- `hasMany` **FileChunk** via `file_id`
- `hasMany` **Attachment** via `file_id`

### `FileChunk` — table: `file_chunk`, composite PK: `(file_id, chunk_id)`
- `$incrementing = false`
- `belongsTo` **File** via `file_id`

### `Attachment` — table: `attachment`, PK: `id`
- `belongsTo` **File** via `file_id`

---

## Lookup / System Domain

### `Lock` — table: `lock`, PK: `lock_id`
- `belongsTo` **Staff** via `staff_id`

### `ApiKey` — table: `api_key`, PK: `id`

### `Session` — table: `session`, PK: `session_id` (string)
- `$incrementing = false`, `$keyType = 'string'`
- `belongsTo` **Staff** via `staff_id`

### `Plugin` — table: `plugin`, PK: `id`
- `hasMany` **PluginInstance** via `plugin_id`

### `PluginInstance` — table: `plugin_instance`, PK: `id`
- `belongsTo` **Plugin** via `plugin_id`

### `Sequence` — table: `sequence`, PK: `id`

### `Translation` — table: `translation`, PK: `id`

### `Draft` — table: `draft`, PK: `id`
- `belongsTo` **Staff** via `staff_id`

### `Note` — table: `note`, PK: `id`
- `belongsTo` **Staff** via `staff_id`

### `Syslog` — table: `syslog`, PK: `log_id`

### `Event` — table: `event`, PK: `id`

### `Search` — table: `_search`, composite PK: `(object_type, object_id)`
- `$incrementing = false`
