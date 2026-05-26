# TipTap Rich Text Editor — ReplyComposer Integration

**Date:** 2026-05-19
**Status:** Approved

## Summary

Replace the plain `<textarea>` in `ReplyComposer.tsx` with TipTap, a headless ProseMirror-based rich text editor. Both the "Reply" and "Internal note" tabs get rich text. The backend already accepts `format: 'html'` — no backend changes required.

## Scope

- `resources/js/components/tickets/ReplyComposer.tsx` — swap textarea, rewire toolbar
- `resources/js/components/tickets/RichTextEditor.tsx` — new component (owns TipTap)
- `package.json` / `package-lock.json` — 5 new TipTap packages
- `tests/Feature/Scp/Tickets/ReplyControllerTest.php` — update format payloads
- `tests/Unit/Services/Scp/Tickets/NotePostingServiceTest.php` — update format payloads

Out of scope: `NoteComposer.tsx` (not currently used in the UI), backend services, legacy osTicket config.

## Architecture

### New: `RichTextEditor.tsx`

A focused component that owns all TipTap concerns. Props:

```ts
interface RichTextEditorProps {
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    className?: string;
}
```

Internals:
- `useEditor` with extensions: `StarterKit`, `Underline`, `Link`, `Placeholder`
- `injectCSS: false` — styling controlled entirely by Tailwind
- `editorProps.attributes.class` set to match current textarea appearance (same font, padding, min-height)
- `onUpdate` calls `onChange(editor.getHTML())`
- `<EditorContent editor={editor} />` replaces the `<textarea>`
- `<BubbleMenu editor={editor}>` — appears on text selection, contains: Bold · Italic · Underline · `|` · Link (uses existing `CIconBtn` style)
- Exposes imperative handle via `useImperativeHandle` / `forwardRef` so `ReplyComposer` toolbar buttons can call editor commands without prop-drilling

Exported ref type:
```ts
interface RichTextEditorHandle {
    chain: () => ChainedCommands;
    isEmpty: boolean;
    insertContent: (content: string) => void;
}
```

### Changes to `ReplyComposer.tsx`

| Removed | Replaced with |
|---|---|
| `<textarea ref={textareaRef} ...>` | `<RichTextEditor ref={editorRef} ...>` |
| `wrapSelection()` helper | `editorRef.current?.chain().focus().toggleBold().run()` etc. |
| `insertAtCursor()` helper | `editorRef.current?.insertContent(text)` |
| `body.trim()` empty check | `editorRef.current?.isEmpty` |
| `format: 'text'` in POST payload | `format: 'html'` in both reply and note POST payloads |

The `body` state remains a `string` — now holds HTML instead of markdown-like text. All existing state (statusId, notify, attachments, sigPref, etc.) is unchanged.

## TipTap Extensions

| Extension | Package | Purpose |
|---|---|---|
| `StarterKit` | `@tiptap/starter-kit` | Bold, Italic, Bullet list, Numbered list, Code, paragraph, undo/redo |
| `Underline` | `@tiptap/extension-underline` | Underline button |
| `Link` | `@tiptap/extension-link` | Link button — replaces `window.prompt` flow |
| `Placeholder` | `@tiptap/extension-placeholder` | Replaces textarea `placeholder` prop |

## Toolbar Mapping

Existing toolbar buttons map directly to TipTap chain commands:

| Button | TipTap command |
|---|---|
| Bold | `toggleBold()` |
| Italic | `toggleItalic()` |
| Underline | `toggleUnderline()` |
| Bullet list | `toggleBulletList()` |
| Numbered list | `toggleOrderedList()` |
| Link | `toggleLink({ href })` |
| Code | `toggleCode()` |

## Features Outside TipTap (unchanged)

- **Emoji picker** — calls `editorRef.current?.insertContent(emoji)`. No TipTap extension needed.
- **Attach file / Insert image** — hidden `<input type="file">` chips. File attachments are outside editor content.
- **Macros** — dropdown UI unchanged; inserts rich HTML via `editorRef.current?.insertContent(macroHtml)`.
- **AI Draft** — stub unchanged; inserts placeholder via `editorRef.current?.insertContent(text)`.

## BubbleMenu

Appears when the user selects text. Contains: **Bold** · **Italic** · **Underline** · divider · **Link**. Uses the existing `CIconBtn` component and the same icon set already imported. Marks active state using `editor.isActive('bold')` etc.

## Styling

- `injectCSS: false` on the editor — no TipTap default styles
- `EditorContent` wrapper gets Tailwind classes matching the removed textarea: `w-full bg-transparent p-0 font-sans text-sm leading-relaxed text-[#18181B] outline-none`
- Placeholder text styled in `resources/css/app.css` — TipTap's Placeholder extension uses a `data-placeholder` attribute on an empty `p` element: `.tiptap p.is-editor-empty:first-child::before { color: #A1A1AA; content: attr(data-placeholder); float: left; pointer-events: none; }`
- BubbleMenu styled as a compact floating pill using existing border/shadow tokens

## Format & Backend Compatibility

The `ReplyController` validates `format` as `in:html,text` — HTML is already supported. `NotePostingService` accepts the same field. No backend changes needed.

Both reply and note mode POST payloads change:
```diff
- format: 'text'
+ format: 'html'
```

Existing stored entries with `format: 'text'` are unaffected — the legacy system renders each entry according to its own stored `format` value.

## Additional ReplyComposer Wiring

- **Expanded state**: `setExpanded(true)` currently fires on `textarea onFocus`. With TipTap, this moves to the `onFocus` callback inside `useEditor` in `RichTextEditor`, which calls an `onFocus` prop passed down from `ReplyComposer`.
- **Draft save empty check**: `handleSaveDraft` checks `!body.trim()` — updated to use `editorRef.current?.isEmpty` (same guard used on the Send button).

## Error Handling

- `useEditor` can return `null` during init — all toolbar calls and ref handle methods guard with `if (!editor) return`
- Empty body check: `editor.isEmpty` (TipTap built-in) prevents sending `<p></p>` as non-empty content
- Draft save, concurrent edit detection, and status transitions are unchanged

## Packages to Install

```bash
npm install @tiptap/react @tiptap/starter-kit @tiptap/extension-underline @tiptap/extension-link @tiptap/extension-placeholder
```

## Testing

- `tests/Feature/Scp/Tickets/ReplyControllerTest.php` — change `format: 'text'` → `format: 'html'` in existing test payloads; add assertion that `format: 'text'` still passes validation (backward compat)
- `tests/Unit/Services/Scp/Tickets/NotePostingServiceTest.php` — same `format` update
- No new browser/E2E tests — backend is format-agnostic; the UI change is visual only
