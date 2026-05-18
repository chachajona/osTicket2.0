# ReplyComposer Extensions — @-Mentions, /-Slash Commands, Auto-Save Draft

**Date:** 2026-05-19
**Status:** Approved

## Summary

Extend the TipTap-powered `ReplyComposer` with three features: `@`-mentions (staff notifications), `/`-slash commands (canned response insertion), and auto-save draft (10-second debounce, restore on mount). AI draft is out of scope for this spec — it requires a separate backend AI integration.

## Scope

**New frontend files:**
- `resources/js/components/tickets/MentionList.tsx` — floating staff autocomplete popup
- `resources/js/components/tickets/SlashCommandList.tsx` — floating canned response popup

**Modified frontend files:**
- `resources/js/components/tickets/RichTextEditor.tsx` — add Mention + slash suggestion extensions, new props
- `resources/js/components/tickets/ReplyComposer.tsx` — add auto-save hook, collect mentioned staff IDs, pass dept context down

**New frontend files (hooks):**
- `resources/js/hooks/useAutoSave.ts` — debounced draft save hook

**New backend files:**
- `app/Http/Controllers/Scp/Staff/AutocompleteController.php`
- `app/Http/Controllers/Scp/CannedResponseController.php`
- `app/Jobs/NotifyMentionedStaffJob.php`
- `app/Mail/MentionNotificationMail.php`

**Modified backend files:**
- `routes/web.php` — two new SCP routes
- `app/Services/Scp/Tickets/ReplyPostingService.php` — dispatch mention notifications
- `app/Services/Scp/Tickets/NotePostingService.php` — dispatch mention notifications
- `app/Http/Controllers/Scp/Tickets/ReplyController.php` — accept `mentioned_staff_ids[]`
- `app/Http/Controllers/Scp/Tickets/NoteController.php` — accept `mentioned_staff_ids[]`

**New test files:**
- `tests/Feature/Scp/Staff/AutocompleteControllerTest.php`
- `tests/Feature/Scp/CannedResponseControllerTest.php`

**Modified test files:**
- `tests/Feature/Scp/Tickets/ReplyControllerTest.php`
- `tests/Feature/Scp/Tickets/NoteControllerTest.php`

**Out of scope:** AI draft, file attachment upload, @-mentioning collaborators (non-staff), staff assignment via mention.

---

## Feature 1: @-Mentions

### Flow

1. Agent types `@` in the editor → Mention extension triggers suggestion popup
2. Continuing to type filters against `GET /scp/staff/autocomplete?q={query}` (300ms debounce on the `items` fetch)
3. Agent selects a name → TipTap inserts a `mention` node
4. On submit → `ReplyComposer` walks `editor.getJSON()` to collect all `mention` node `id` attrs → appends `mentioned_staff_ids[]` to the POST payload
5. Controller passes IDs to service → service dispatches `NotifyMentionedStaffJob` per ID (queued)

### Backend: `GET /scp/staff/autocomplete`

Route: `GET /scp/staff/autocomplete` — protected by `auth.staff` middleware.

Query params:
- `q` (optional string) — prefix match against `firstname`, `lastname`, `username`

Behaviour:
- Returns max 10 active staff (`isactive = 1`)
- Excludes the currently authenticated staff member
- No `q` → first 10 active staff alphabetically by firstname

Response shape:
```json
[
  { "id": 3, "name": "Ada Lovelace", "username": "ada" },
  { "id": 7, "name": "Bob Smith",    "username": "bsmith" }
]
```

### `MentionList.tsx`

`forwardRef` React component (required by `ReactRenderer` for keyboard passthrough). Props supplied by TipTap's suggestion `render()` callbacks:
- `items: { id: number; name: string; username: string }[]`
- `command: (item) => void` — called to confirm selection
- `clientRect: () => DOMRect` — used for `position: fixed` positioning

UI:
- Floating pill list anchored to cursor via `position: fixed` + `clientRect()`
- Each row: avatar initials circle + full name + `@username` muted
- ↑↓ to navigate, Enter/Tab to select, Escape to dismiss
- Loading spinner while fetch is in-flight
- "No staff found" empty state

### `RichTextEditor.tsx` additions

New props:
```ts
ticketDeptId?: number;
onMentionedStaff?: (ids: number[]) => void; // not used here — collection happens in ReplyComposer
```

Extension config:
```ts
Mention.configure({
    HTMLAttributes: { class: 'mention' },
    renderText: ({ node }) => `@${node.attrs.label}`,
    deleteTriggerWithBackspace: true,
    suggestion: {
        char: '@',
        items: async ({ query }) => fetchStaffAutocomplete(query), // defined in RichTextEditor.tsx, calls GET /scp/staff/autocomplete
        render: () => ReactRenderer-based popup using MentionList,
    },
})
```

### `ReplyComposer.tsx` additions

On submit (reply and note branches), before building the payload:
```ts
const mentionedIds = editor.getJSON().content
    ?.flatMap(node => node.content ?? [])
    .filter(node => node.type === 'mention')
    .map(node => node.attrs?.id as number) ?? [];
```
Add `mentioned_staff_ids: mentionedIds` to the POST payload (empty array if none).

### `NotifyMentionedStaffJob`

Queued job. Receives: `ticketId`, `entryId`, `mentionerStaffId`, `mentionedStaffId`.
Sends a notification email to the mentioned staff member linking to the ticket.
Dispatches `Mail::to($mentionedStaff->email)->queue(new MentionNotificationMail($ticket, $entry, $mentioner))`. `MentionNotificationMail` is a new Mailable linking to the ticket.

---

## Feature 2: /-Slash Commands (Canned Responses)

### Flow

1. Agent types `/` → custom suggestion Extension triggers the slash command popup
2. Continuing to type filters by `title` via `GET /scp/canned-responses?dept_id={id}&q={query}` (300ms debounce)
3. Agent selects a response → trigger text (`/query`) is deleted, `response` HTML is inserted at cursor
4. Nothing is stored in the TipTap document — no payload additions on submit

### Backend: `GET /scp/canned-responses`

Route: `GET /scp/canned-responses` — protected by `auth.staff`.

Query params:
- `dept_id` (optional int) — filter to responses where `dept_id` matches OR `dept_id IS NULL`
- `q` (optional string) — prefix/contains match against `title`

Behaviour:
- Returns max 10 enabled responses (`isenabled = 1`)
- Ordered by `title` ascending

Response shape:
```json
[
  { "id": 12, "title": "Greeting",  "response": "<p>Hello, thank you for contacting us…</p>" },
  { "id": 15, "title": "Closing",   "response": "<p>Let us know if there's anything else…</p>" }
]
```

### `SlashCommandList.tsx`

Same `forwardRef` + `ReactRenderer` pattern as `MentionList`. Props:
- `items: { id: number; title: string; response: string }[]`
- `command: (item) => void`
- `clientRect: () => DOMRect`

UI:
- Title as primary label
- Response preview: first 60 chars with HTML tags stripped
- Same keyboard nav and dismiss behaviour as `MentionList`
- "No responses found" empty state

### `RichTextEditor.tsx` additions

Custom extension using TipTap's `Suggestion` utility:
```ts
Extension.create({
    name: 'slashCommand',
    addProseMirrorPlugins() {
        return [Suggestion({
            char: '/',
            startOfLine: false,
            allowSpaces: false,
            editor: this.editor,
            items: async ({ query }) => fetchCannedResponses(ticketDeptId, query), // defined in RichTextEditor.tsx, calls GET /scp/canned-responses
            command: ({ editor, range, props }) => {
                editor.chain().focus().deleteRange(range).insertContent(props.response).run();
            },
            render: () => ReactRenderer-based popup using SlashCommandList,
        })];
    },
})
```

---

## Feature 3: Auto-Save Draft

### Flow

1. `body` state changes → `useAutoSave` hook resets a 10-second `setTimeout`
2. After 10 seconds of inactivity with non-empty body → auto-save fires
3. First save: `POST /scp/tickets/{ticket}/draft` → stores `draftId` in hook state
4. Subsequent saves: `PATCH /scp/tickets/{ticket}/draft` using stored `draftId`
5. `saveDraftState` transitions `idle → saving → saved → idle` — existing status line renders this with no UI changes
6. On successful reply/note send → `DELETE /scp/tickets/{ticket}/draft` clears the draft
7. Manual "Save as draft" button forces immediate save (cancels pending debounce, fires instantly)

### Draft Restoration on Mount

When `ReplyComposer` mounts:
1. `useEffect` fires once → calls `GET /scp/tickets/{ticket}/draft`
2. If draft exists and `body` is empty → `editorRef.current?.getEditor()?.commands.setContent(draft.body)` + `setBody(draft.body)` + store `draftId`
3. Status line shows "Saved" for 2 seconds (reuses existing `saveDraftState = 'saved'` state — no new UI state needed)
4. If no draft → no-op

### Draft Namespace

Following legacy osTicket convention:
- Reply mode: `ticket.response`
- Note mode: `ticket.staff.note`

The existing `DraftService` stores `namespace` on the `Draft` model. `ReplyComposer` derives the namespace from `mode` and passes it with each draft API call as a query param or request field.

### `useAutoSave` Hook

Extracted to keep `ReplyComposer` readable. Signature:

```ts
function useAutoSave(options: {
    body: string;
    ticketId: number;
    namespace: string;
    onStateChange: (state: 'idle' | 'saving' | 'saved') => void;
}): {
    forceSave: () => void;
    deleteDraft: () => void;
}
```

Implemented with `useEffect` + `useRef` for the timer and `draftId` — no external packages.

---

## Styling

**`.mention` class** (add to `app.css`):
```css
.mention {
    background: #F3F3FE;
    border-radius: 4px;
    color: #5558CF;
    font-weight: 500;
    padding: 0 3px;
}
```

Matches the existing `MacroChip` tertiary colour palette already used in the composer.

**Suggestion popups** share the same shadow and border tokens as the existing BubbleMenu:
`border border-[#E2E0D8] bg-white shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]`

---

## Error Handling

- Autocomplete fetch fails → popup shows "Could not load suggestions" and closes after 2s; editor is unaffected
- Canned response fetch fails → same pattern
- Auto-save fails → `saveDraftState` stays `'idle'`; next keystroke will re-trigger the debounce
- Draft restore fails → silent no-op; composer starts empty as normal
- `NotifyMentionedStaffJob` failure → job retries via Laravel's default queue retry policy; does not affect reply submission

---

## Testing

### New feature tests

**`tests/Feature/Scp/Staff/AutocompleteControllerTest.php`**
- Returns active staff matching `q`
- Excludes current authenticated staff
- Returns at most 10 results
- Returns 401 for unauthenticated request

**`tests/Feature/Scp/CannedResponseControllerTest.php`**
- Returns responses for matching `dept_id` + globals (`dept_id IS NULL`)
- Excludes responses for a different department
- Filters by `q` against title
- Returns at most 10 results
- Returns only enabled responses (`isenabled = 1`)

### Modified feature tests

**`tests/Feature/Scp/Tickets/ReplyControllerTest.php`**
- Add: posting with `mentioned_staff_ids: [3, 7]` dispatches `NotifyMentionedStaffJob` twice
- Add: posting with empty `mentioned_staff_ids` dispatches no jobs

**`tests/Feature/Scp/Tickets/NoteControllerTest.php`** (if exists, else create)
- Same mention dispatch assertions for note submissions
