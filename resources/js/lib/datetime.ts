const RELATIVE = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

const DATE = new Intl.DateTimeFormat('en', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
});

const DATE_TIME = new Intl.DateTimeFormat('en', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

const UNITS: Array<{ unit: Intl.RelativeTimeFormatUnit; ms: number }> = [
    { unit: 'year', ms: 365 * 24 * 60 * 60 * 1000 },
    { unit: 'month', ms: 30 * 24 * 60 * 60 * 1000 },
    { unit: 'week', ms: 7 * 24 * 60 * 60 * 1000 },
    { unit: 'day', ms: 24 * 60 * 60 * 1000 },
    { unit: 'hour', ms: 60 * 60 * 1000 },
    { unit: 'minute', ms: 60 * 1000 },
];

function toDate(value: string | null | undefined): Date | null {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatRelative(value: string | null | undefined, now: Date = new Date()): string | null {
    const date = toDate(value);
    if (!date) return null;

    const diff = date.getTime() - now.getTime();
    const abs = Math.abs(diff);

    for (const { unit, ms } of UNITS) {
        if (abs >= ms) {
            return RELATIVE.format(Math.round(diff / ms), unit);
        }
    }

    return RELATIVE.format(Math.round(diff / 1000), 'second');
}

export function formatDate(value: string | null | undefined): string | null {
    const date = toDate(value);
    return date ? DATE.format(date) : null;
}

export function formatDateTime(value: string | null | undefined): string | null {
    const date = toDate(value);
    return date ? DATE_TIME.format(date) : null;
}

export function formatTicketDate(value: string | null | undefined): string | null {
    const date = toDate(value);
    if (!date) return null;

    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours24 = date.getHours();
    const minutes = String(date.getMinutes()).padStart(2, '0');
    const period = hours24 >= 12 ? 'PM' : 'AM';
    const hours12 = ((hours24 + 11) % 12) + 1;

    return `${day}/${month}/${year}, ${String(hours12).padStart(2, '0')}:${minutes}${period}`;
}

export function formatBytes(bytes: number | null | undefined): string | null {
    if (bytes === null || bytes === undefined) return null;
    if (bytes === 0) return '0 B';

    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const value = bytes / Math.pow(1024, exponent);

    return `${value < 10 && exponent > 0 ? value.toFixed(1) : Math.round(value)} ${units[exponent]}`;
}
