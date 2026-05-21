import { useRef, useState } from 'react';
import type { RichTextEditorHandle } from './RichTextEditor';

interface AiDraftModalProps {
    ticketId: number;
    editorRef: React.RefObject<RichTextEditorHandle | null>;
    onClose: () => void;
}

type Status = 'idle' | 'streaming' | 'done' | 'error';

function getCsrfToken(): string {
    return document.head.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function textToHtml(text: string): string {
    return text
        .split(/\n\n+/)
        .map((para) => `<p>${escapeHtml(para).replace(/\n/g, '<br>')}</p>`)
        .join('');
}

export function AiDraftModal({ ticketId, editorRef, onClose }: AiDraftModalProps) {
    const [prompt, setPrompt] = useState('');
    const [draft, setDraft] = useState('');
    const [status, setStatus] = useState<Status>('idle');
    const [error, setError] = useState('');
    const abortRef = useRef<AbortController | null>(null);

    async function generate() {
        if (!prompt.trim()) {
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setDraft('');
        setError('');
        setStatus('streaming');

        try {
            const response = await fetch(`/scp/tickets/${ticketId}/draft/ai`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'text/event-stream',
                },
                body: JSON.stringify({ prompt }),
                signal: controller.signal,
            });

            if (!response.ok || !response.body) {
                setError('Failed to connect to AI service.');
                setStatus('error');
                return;
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();
            let lineBuffer = '';
            let accumulated = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    break;
                }

                lineBuffer += decoder.decode(value, { stream: true });

                let newlinePos: number;
                while ((newlinePos = lineBuffer.indexOf('\n')) !== -1) {
                    const line = lineBuffer.slice(0, newlinePos).trimEnd();
                    lineBuffer = lineBuffer.slice(newlinePos + 1);

                    if (!line.startsWith('data: ')) {
                        continue;
                    }

                    const payload = line.slice(6);
                    let parsed: Record<string, unknown>;
                    try {
                        parsed = JSON.parse(payload);
                    } catch {
                        continue;
                    }

                    if (typeof parsed.error === 'string') {
                        setError(parsed.error);
                        setStatus('error');
                        return;
                    }

                    if (typeof parsed.text === 'string') {
                        accumulated += parsed.text;
                        setDraft(accumulated);
                    }
                }
            }

            setStatus('done');
        } catch (err) {
            if ((err as Error).name !== 'AbortError') {
                setError('Request failed or timed out.');
                setStatus('error');
            }
        }
    }

    function handleUse() {
        if (!draft) {
            return;
        }
        editorRef.current?.insertContent(textToHtml(draft));
        handleClose();
    }

    function handleClose() {
        abortRef.current?.abort();
        onClose();
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
            role="dialog"
            aria-modal="true"
            aria-label="AI Draft"
        >
            <div className="flex w-full max-w-lg flex-col gap-4 rounded-xl bg-white p-6 shadow-xl">
                <div className="flex items-center justify-between">
                    <h2 className="text-sm font-semibold text-[#18181B]">AI Draft</h2>
                    <button
                        type="button"
                        onClick={handleClose}
                        className="text-xs text-[#71717A] hover:text-[#18181B]"
                    >
                        Close
                    </button>
                </div>

                <div className="flex gap-2">
                    <input
                        type="text"
                        value={prompt}
                        onChange={(e) => setPrompt(e.target.value)}
                        onKeyDown={(e) => e.key === 'Enter' && generate()}
                        placeholder="Describe what you want the AI to draft…"
                        maxLength={500}
                        disabled={status === 'streaming'}
                        className="flex-1 rounded-md border border-[#E4E4E7] px-3 py-2 text-sm text-[#18181B] placeholder:text-[#A1A1AA] focus:outline-none focus:ring-2 focus:ring-[#EC4899]/40 disabled:opacity-50"
                    />
                    <button
                        type="button"
                        onClick={generate}
                        disabled={status === 'streaming' || !prompt.trim()}
                        className="rounded-md bg-[#EC4899] px-4 py-2 text-xs font-medium text-white hover:bg-[#DB2777] disabled:opacity-40"
                    >
                        {status === 'streaming' ? 'Generating…' : 'Generate'}
                    </button>
                </div>

                {(draft || status === 'streaming') && (
                    <div className="max-h-64 overflow-y-auto rounded-md border border-[#E4E4E7] bg-[#FAFAF8] p-3 text-sm leading-relaxed text-[#18181B] whitespace-pre-wrap">
                        {draft}
                        {status === 'streaming' && (
                            <span className="ml-0.5 inline-block h-4 w-0.5 animate-pulse bg-[#EC4899]" />
                        )}
                    </div>
                )}

                {status === 'error' && (
                    <p className="text-xs text-red-600">{error}</p>
                )}

                <div className="flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={handleClose}
                        className="rounded-md border border-[#E4E4E7] px-3 py-1.5 text-xs text-[#71717A] hover:bg-[#FAFAF8]"
                    >
                        Discard
                    </button>
                    <button
                        type="button"
                        onClick={handleUse}
                        disabled={!draft || status === 'streaming'}
                        className="rounded-md bg-[#18181B] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#27272A] disabled:opacity-40"
                    >
                        Use this draft
                    </button>
                </div>
            </div>
        </div>
    );
}