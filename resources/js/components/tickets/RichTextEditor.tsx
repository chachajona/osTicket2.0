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

function makeSuggestionRenderer<
    H extends { onKeyDown: (p: { event: KeyboardEvent }) => boolean },
    I,
>(
    Component: React.ForwardRefExoticComponent<
        React.RefAttributes<H> & { items: I[]; command: (item: I) => void }
    >
) {
    return () => {
        let reactRenderer: ReactRenderer<H>;
        let containerEl: HTMLElement;

        return {
            onStart: (props: any) => {
                reactRenderer = new ReactRenderer(Component as any, { props, editor: props.editor });
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

async function fetchStaff(
    query: string
): Promise<{ id: number; name: string; username: string }[]> {
    try {
        const res = await fetch(`/scp/staff/autocomplete?q=${encodeURIComponent(query)}`);
        if (!res.ok) {
            return [];
        }
        return res.json() as Promise<{ id: number; name: string; username: string }[]>;
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
        if (deptId) {
            params.set('dept_id', String(deptId));
        }
        const res = await fetch(`/scp/canned-responses?${params.toString()}`);
        if (!res.ok) {
            return [];
        }
        return res.json() as Promise<{ id: number; title: string; response: string }[]>;
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
                        items: ({ query }: { query: string }) => fetchStaff(query),
                        render: makeSuggestionRenderer<
                            MentionListHandle,
                            { id: number; name: string; username: string }
                        >(MentionList),
                        command: ({ editor: e, range, props }: any) => {
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
                                items: ({ query }: { query: string }) =>
                                    fetchCannedResponses(ticketDeptId, query),
                                render: makeSuggestionRenderer<
                                    SlashCommandListHandle,
                                    { id: number; title: string; response: string }
                                >(SlashCommandList),
                                command: ({ editor: e, range, props }: any) => {
                                    e.chain()
                                        .focus()
                                        .deleteRange(range)
                                        .insertContent(props.response)
                                        .run();
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

        useImperativeHandle(
            ref,
            () => ({
                getEditor: () => editor ?? null,
                insertContent: (content: string) => {
                    editor?.chain().focus().insertContent(content).run();
                },
                clearContent: () => {
                    editor?.commands.clearContent(true);
                },
            }),
            [editor]
        );

        if (!editor) {
            return null;
        }

        return (
            <div className="relative">
                <BubbleMenu editor={editor}>
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
                                        editor
                                            .chain()
                                            .focus()
                                            .setLink({ href: url, target: '_blank' })
                                            .run();
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
