# TipTap ReplyComposer Integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the plain `<textarea>` in `ReplyComposer.tsx` with a TipTap rich-text editor wrapped in an extracted `RichTextEditor` component, preserving the existing custom toolbar and adding a BubbleMenu.

**Architecture:** A new `RichTextEditor.tsx` component owns all TipTap concerns (extensions, BubbleMenu, forwardRef handle). `ReplyComposer.tsx` keeps its toolbar and all composer-level state; toolbar buttons call editor commands via the ref. Both reply and note modes use the same editor; `format: 'html'` is sent to the backend (already accepted by `ReplyController` and `NotePostingService`).

**Tech Stack:** `@tiptap/react`, `@tiptap/starter-kit`, `@tiptap/extension-underline`, `@tiptap/extension-link`, `@tiptap/extension-placeholder`, React `forwardRef`/`useImperativeHandle`, Tailwind CSS v4.

---

## File Map

| Action | File | Responsibility |
|---|---|---|
| Create | `resources/js/components/tickets/RichTextEditor.tsx` | TipTap editor, BubbleMenu, forwardRef handle |
| Modify | `resources/css/app.css` | TipTap placeholder pseudo-element CSS |
| Modify | `resources/js/components/tickets/ReplyComposer.tsx` | Swap textarea → RichTextEditor, rewire toolbar |

No backend or test file changes needed — tests already use `format: 'html'`.

---

## Task 1: Install TipTap Packages

**Files:**
- Modify: `package.json`

- [ ] **Step 1: Install the five TipTap packages**

```bash
cd /path/to/sacramento && npm install @tiptap/react @tiptap/starter-kit @tiptap/extension-underline @tiptap/extension-link @tiptap/extension-placeholder
```

Expected: no peer-dependency errors. Packages appear in `dependencies` in `package.json`.

- [ ] **Step 2: Verify installation**

```bash
grep -E '"@tiptap' package.json
```

Expected output (versions may differ):
```
"@tiptap/extension-link": "^2.x.x",
"@tiptap/extension-placeholder": "^2.x.x",
"@tiptap/extension-underline": "^2.x.x",
"@tiptap/react": "^2.x.x",
"@tiptap/starter-kit": "^2.x.x",
```

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore(deps): install TipTap packages"
```

---

## Task 2: Add TipTap Placeholder CSS

**Files:**
- Modify: `resources/css/app.css`

TipTap's `Placeholder` extension adds a `data-placeholder` attribute to the first `<p>` element when the editor is empty. It also adds the class `is-editor-empty` to the editor root. We need a CSS rule to render it as placeholder text.

- [ ] **Step 1: Append the rule at the end of `resources/css/app.css`**

Add the following lines at the very end of the file (after the last `.prose-ticket` rule):

```css
/* TipTap placeholder */
.tiptap p.is-editor-empty:first-child::before {
    color: #A1A1AA;
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/css/app.css
git commit -m "style: add TipTap placeholder CSS"
```

---

## Task 3: Create RichTextEditor Component

**Files:**
- Create: `resources/js/components/tickets/RichTextEditor.tsx`

This component is the only place TipTap is imported. It:
- Sets up `useEditor` with StarterKit, Underline, Link, Placeholder
- Renders `<BubbleMenu>` (Bold · Italic · Underline · Link, uses existing `CIconBtn` style)
- Renders `<EditorContent>`
- Exposes an imperative handle via `forwardRef` / `useImperativeHandle` with three methods: `getEditor()`, `insertContent()`, `clearContent()`
- Emits `''` (not `<p></p>`) when editor is empty so `ReplyComposer`'s `body.trim()` checks keep working

- [ ] **Step 1: Create the file**

Create `resources/js/components/tickets/RichTextEditor.tsx` with this content:

```tsx
import { forwardRef, useImperativeHandle } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import { BubbleMenu } from '@tiptap/react/menus';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import type { Editor } from '@tiptap/core';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    TextBoldIcon,
    TextItalicIcon,
    TextUnderlineIcon,
    Link01Icon,
} from '@hugeicons/core-free-icons';
import { cn } from '@/lib/utils';

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
}

export const RichTextEditor = forwardRef<RichTextEditorHandle, RichTextEditorProps>(
    function RichTextEditor({ value = '', onChange, placeholder, onFocus }, ref) {
        const editor = useEditor({
            extensions: [
                StarterKit,
                Underline,
                Link.configure({ openOnClick: false, defaultProtocol: 'https' }),
                Placeholder.configure({ placeholder: placeholder ?? '' }),
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
                <BubbleMenu
                    editor={editor}
                    tippyOptions={{ duration: 100 }}
                >
                    <div className="inline-flex items-center gap-0.5 rounded-md border border-[#E2E0D8] bg-white px-1 py-1 shadow-[0_4px_12px_-2px_rgba(24,24,27,0.12)]">
                        <button
                            type="button"
                            onClick={() => editor.chain().focus().toggleBold().run()}
                            title="Bold"
                            className={cn(
                                'inline-flex h-6 w-6 items-center justify-center rounded-sm transition-all',
                                editor.isActive('bold')
                                    ? 'bg-[#FAFAF8] text-[#18181B]'
                                    : 'text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
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
                                editor.isActive('italic')
                                    ? 'bg-[#FAFAF8] text-[#18181B]'
                                    : 'text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
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
                                editor.isActive('underline')
                                    ? 'bg-[#FAFAF8] text-[#18181B]'
                                    : 'text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
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
                                editor.isActive('link')
                                    ? 'bg-[#FAFAF8] text-[#18181B]'
                                    : 'text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
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

- [ ] **Step 2: Commit**

```bash
git add resources/js/components/tickets/RichTextEditor.tsx
git commit -m "feat(editor): add RichTextEditor TipTap component"
```

---

## Task 4: Update ReplyComposer to Use RichTextEditor

**Files:**
- Modify: `resources/js/components/tickets/ReplyComposer.tsx`

This task has several sub-steps. Make all changes then commit once at the end.

- [ ] **Step 1: Add import and remove unused imports**

At the top of `ReplyComposer.tsx`, add the import for `RichTextEditor`:

```tsx
import { RichTextEditor, type RichTextEditorHandle } from './RichTextEditor';
```

Remove these imports that are no longer needed (the helpers `wrapSelection` and `insertAtCursor` are being deleted, and `textareaRef` is replaced):
- `useRef` stays (still used for other refs)
- No import changes needed for icons — all existing icon imports remain

- [ ] **Step 2: Delete the `wrapSelection` and `insertAtCursor` helper functions**

Delete lines 136–153 (the two helper functions):

```tsx
// DELETE this entire function:
function wrapSelection(textarea: HTMLTextAreaElement, before: string, after: string = before): void {
    ...
}

// DELETE this entire function:
function insertAtCursor(textarea: HTMLTextAreaElement, text: string): void {
    ...
}
```

- [ ] **Step 3: Replace `textareaRef` with `editorRef`**

In the component body, find:
```tsx
const textareaRef = useRef<HTMLTextAreaElement>(null);
```

Replace with:
```tsx
const editorRef = useRef<RichTextEditorHandle>(null);
```

- [ ] **Step 4: Rewrite `formatText()` to use TipTap chain commands**

Find the entire `formatText` function and replace it:

```tsx
const formatText = (action: string) => {
    const editor = editorRef.current?.getEditor();
    if (!editor) return;
    switch (action) {
        case 'bold':
            editor.chain().focus().toggleBold().run();
            break;
        case 'italic':
            editor.chain().focus().toggleItalic().run();
            break;
        case 'underline':
            editor.chain().focus().toggleUnderline().run();
            break;
        case 'bullet':
            editor.chain().focus().toggleBulletList().run();
            break;
        case 'number':
            editor.chain().focus().toggleOrderedList().run();
            break;
        case 'link': {
            const url = window.prompt('Enter URL:');
            if (url) {
                editor.chain().focus().setLink({ href: url, target: '_blank' }).run();
            }
            break;
        }
        case 'code':
            editor.chain().focus().toggleCode().run();
            break;
    }
};
```

- [ ] **Step 5: Update `insertEmoji` to use the editor**

Find:
```tsx
const insertEmoji = (emoji: string) => {
    const ta = textareaRef.current;
    if (!ta) return;
    insertAtCursor(ta, emoji);
    setBody(ta.value);
    setEmojiOpen(false);
};
```

Replace with:
```tsx
const insertEmoji = (emoji: string) => {
    editorRef.current?.insertContent(emoji);
    setEmojiOpen(false);
};
```

- [ ] **Step 6: Update AI Draft stub to use the editor**

Find:
```tsx
onClick={() => {
    const prompt = window.prompt('Describe what you want the AI to draft:');
    if (prompt) {
        setBody((prev) => prev + `\n\n[AI Draft: ${prompt}]\n`);
    }
}}
```

Replace with:
```tsx
onClick={() => {
    const aiPrompt = window.prompt('Describe what you want the AI to draft:');
    if (aiPrompt) {
        editorRef.current?.insertContent(`<p>[AI Draft: ${aiPrompt}]</p>`);
    }
}}
```

- [ ] **Step 7: Update `handleSend` — change `format` and clear editor on success**

In `handleSend`, for the note branch, find:
```tsx
router.post(
    `/scp/tickets/${ticketId}/notes`,
    {
        body,
        format: 'text',
        expected_updated: expectedUpdated,
    },
    {
        preserveScroll: true,
        onSuccess: () => {
            setBody('');
            setMacro(null);
            setAttachments([]);
            onSuccess?.();
        },
        onFinish: () => setIsSubmitting(false),
    }
);
```

Replace with:
```tsx
router.post(
    `/scp/tickets/${ticketId}/notes`,
    {
        body,
        format: 'html',
        expected_updated: expectedUpdated,
    },
    {
        preserveScroll: true,
        onSuccess: () => {
            editorRef.current?.clearContent();
            setBody('');
            setMacro(null);
            setAttachments([]);
            onSuccess?.();
        },
        onFinish: () => setIsSubmitting(false),
    }
);
```

In the same function, for the reply branch, find:
```tsx
const payload: Record<string, any> = {
    body,
    format: 'text',
    signature: sigPref,
    reply_status_id: statusId ? Number(statusId) : null,
    expected_updated: expectedUpdated,
};
```

Replace with:
```tsx
const payload: Record<string, any> = {
    body,
    format: 'html',
    signature: sigPref,
    reply_status_id: statusId ? Number(statusId) : null,
    expected_updated: expectedUpdated,
};
```

Also in the reply branch `onSuccess` callback, add `editorRef.current?.clearContent();` as the first line:
```tsx
onSuccess: () => {
    editorRef.current?.clearContent();
    setBody('');
    setStatusId(null);
    setMacro(null);
    setAttachments([]);
    onSuccess?.();
},
```

- [ ] **Step 8: Replace the `<textarea>` with `<RichTextEditor>`**

Find the entire textarea block:
```tsx
<textarea
    ref={textareaRef}
    value={body}
    onChange={(e) => setBody(e.target.value)}
    onFocus={() => setExpanded(true)}
    placeholder={
        isNote
            ? 'Type an internal note…'
            : 'Type your reply… use "/" for canned responses, "@" to mention.'
    }
    rows={expanded ? 6 : 2}
    className={cn(
        'w-full resize-y border-none bg-transparent p-0 font-sans text-sm leading-relaxed text-[#18181B] outline-none placeholder:text-[#A1A1AA]',
        expanded ? 'min-h-[120px]' : 'min-h-[44px]'
    )}
/>
```

Replace with:
```tsx
<div className={expanded ? 'min-h-[120px]' : 'min-h-[44px]'}>
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
    />
</div>
```

- [ ] **Step 9: Update Send button and Save Draft button disabled checks**

The `body.trim()` checks still work correctly because `RichTextEditor` emits `''` (not `<p></p>`) when empty. No changes needed to the disabled props:
- `disabled={isSubmitting || !body.trim()}` on Send button — correct as-is
- `disabled={saveDraftState === 'saving' || !body.trim()}` on Save Draft — correct as-is

Verify these are unchanged.

- [ ] **Step 10: Remove the now-unused `fileInputRef` / `imageInputRef` refs if `textareaRef` was the only removed ref**

`textareaRef` is the only ref being removed. `fileInputRef`, `imageInputRef`, `ccWrapRef`, `macroWrapRef`, `emojiWrapRef`, `sendOptionsRef` all remain. No additional removals needed.

- [ ] **Step 11: Run the PHP test suite to verify no regressions**

```bash
php artisan test --compact --filter=ReplyControllerTest
```

Expected: All tests pass (the tests already send `format: 'html'`).

```bash
php artisan test --compact --filter=NotePostingServiceTest
```

Expected: All tests pass.

- [ ] **Step 12: Commit**

```bash
git add resources/js/components/tickets/ReplyComposer.tsx
git commit -m "feat(composer): replace textarea with TipTap RichTextEditor"
```

---

## Task 5: Verify Build

- [ ] **Step 1: Run the Vite build**

```bash
npm run build
```

Expected: no TypeScript errors, no module resolution errors, build succeeds.

---

## Self-Review Checklist

- [x] Spec section "Architecture" → Task 3 (RichTextEditor) + Task 4 (ReplyComposer)
- [x] Spec section "TipTap Extensions" → Task 1 (packages) + Task 3 (useEditor config)
- [x] Spec section "Toolbar Mapping" → Task 4 Step 4 (formatText rewrite)
- [x] Spec section "Features Outside TipTap" → Task 4 Steps 5–6 (emoji, AI Draft)
- [x] Spec section "BubbleMenu" → Task 3 (BubbleMenu in RichTextEditor)
- [x] Spec section "Styling" → Task 2 (placeholder CSS) + Task 3 (editorProps.attributes.class)
- [x] Spec section "Format & Backend Compatibility" → Task 4 Step 7 (format: 'html')
- [x] Spec section "Additional ReplyComposer Wiring" → Task 4 Steps 7–8 (clearContent, onFocus)
- [x] Spec section "Error Handling" → Task 3 (early return on null editor) + Task 4 Step 9 (isEmpty via body)
- [x] No test file changes needed — ReplyControllerTest and NotePostingServiceTest already use `format: 'html'`
- [x] No backend changes needed
