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
                    Accept: 'application/json',
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
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
            });
        } catch {
            /* silent */
        }
    }, [draftUrl]);

    const loadDraft = useCallback(async (): Promise<string> => {
        try {
            const res = await fetch(draftUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) return '';
            const data = (await res.json()) as { body?: string };
            return data.body ?? '';
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
