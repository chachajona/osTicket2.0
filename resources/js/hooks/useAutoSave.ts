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
    const savedToIdleTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const bodyRef = useRef(body);
    bodyRef.current = body;

    const draftUrl = `/scp/tickets/${ticketId}/draft?type=${type}`;

    const clearSavedToIdleTimer = useCallback((): void => {
        if (savedToIdleTimerRef.current) {
            clearTimeout(savedToIdleTimerRef.current);
            savedToIdleTimerRef.current = null;
        }
    }, []);

    const scheduleSavedToIdle = useCallback((): void => {
        clearSavedToIdleTimer();
        savedToIdleTimerRef.current = setTimeout(() => {
            onStateChange('idle');
            savedToIdleTimerRef.current = null;
        }, 2000);
    }, [clearSavedToIdleTimer, onStateChange]);

    const save = useCallback(async (): Promise<void> => {
        if (!bodyRef.current.trim()) return;

        clearSavedToIdleTimer();
        onStateChange('saving');
        try {
            const response = await fetch(draftUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ body: bodyRef.current }),
            });
            if (!response.ok) {
                onStateChange('idle');
                return;
            }

            onStateChange('saved');
            scheduleSavedToIdle();
        } catch {
            onStateChange('idle');
        }
    }, [clearSavedToIdleTimer, draftUrl, onStateChange, scheduleSavedToIdle]);

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
        if (timerRef.current) clearTimeout(timerRef.current);
        if (!body.trim()) return;

        timerRef.current = setTimeout(save, delay);

        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
        };
    }, [body, delay, save]);

    useEffect(() => {
        return () => {
            if (timerRef.current) clearTimeout(timerRef.current);
            clearSavedToIdleTimer();
        };
    }, [clearSavedToIdleTimer]);

    return { forceSave: save, deleteDraft, loadDraft };
}
