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
