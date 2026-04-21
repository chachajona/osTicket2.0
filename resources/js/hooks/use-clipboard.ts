import { useCallback, useEffect, useRef, useState } from "react";

export function useClipboard(timeout = 2000) {
    const [copied, setCopied] = useState(false);
    const timerRef = useRef<number | null>(null);

    useEffect(() => {
        return () => {
            if (timerRef.current !== null) {
                window.clearTimeout(timerRef.current);
                timerRef.current = null;
            }
        };
    }, []);

    const copy = useCallback(
        async (text: string) => {
            try {
                await navigator.clipboard.writeText(text);
                setCopied(true);
                if (timerRef.current !== null) {
                    window.clearTimeout(timerRef.current);
                }
                timerRef.current = window.setTimeout(() => {
                    setCopied(false);
                    timerRef.current = null;
                }, timeout);
                return true;
            } catch {
                setCopied(false);
                return false;
            }
        },
        [timeout],
    );

    return { copied, copy };
}
