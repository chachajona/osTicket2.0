import { useEffect, useState } from "react";

/**
 * Countdown ticking once per second from `seconds` down to 0.
 * Returns 0 when finished. Resetting `seconds` restarts the countdown.
 */
export function useCountdown(seconds: number): number {
    const [remaining, setRemaining] = useState(seconds);

    useEffect(() => {
        setRemaining(seconds);
        if (seconds <= 0) return;
        const id = window.setInterval(() => {
            setRemaining((s) => {
                if (s <= 1) {
                    window.clearInterval(id);
                    return 0;
                }
                return s - 1;
            });
        }, 1000);
        return () => window.clearInterval(id);
    }, [seconds]);

    return remaining;
}

export function formatCountdown(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    if (m === 0) return `${s}s`;
    return `${m}m ${s.toString().padStart(2, "0")}s`;
}
