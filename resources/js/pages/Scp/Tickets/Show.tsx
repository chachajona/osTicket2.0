import { Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactElement, type ReactNode } from 'react';
import { Dialog as DialogPrimitive } from '@base-ui/react/dialog';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    AlertCircleIcon,
    ArrowDown01Icon,
    Calendar01Icon,
    Cancel01Icon,
    CheckmarkCircle02Icon,
    Clock01Icon,
    Copy01Icon,
    Download01Icon,
    File01Icon,
    Image01Icon,
    Link01Icon,
    Link02Icon,
    Mail01Icon,
    Note01Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';

import { PriorityBadge } from '@/components/scp/PriorityBadge';
import { StatusBadge } from '@/components/scp/StatusBadge';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatBytes, formatDateTime, formatRelative } from '@/lib/datetime';
import { sanitizeHtml } from '@/lib/sanitize-html';
import { cn } from '@/lib/utils';

interface Ticket {
    id: number;
    number: string;
    status: string | null;
    status_state: string | null;
    priority: string | null;
    department: string | null;
    assignee: string | null;
    sla_id: number;
    duedate: string | null;
    created: string | null;
    updated: string | null;
    closed: string | null;
    subject: string | null;
    requester: string | null;
    requester_email: string | null;
}

interface ThreadEntry {
    kind: 'entry';
    id: number;
    type: string | null;
    author: string | null;
    body: string | null;
    format: string | null;
    created: string | null;
}

interface ThreadEvent {
    kind: 'event';
    id: number;
    event_id: number;
    label: string | null;
    data: string | null;
    created: string | null;
}

type TimelineItem = ThreadEntry | ThreadEvent;

interface Attachment {
    id: number;
    file_id: number;
    name: string | null;
    mime: string | null;
    size: number | null;
    download_url: string;
}

interface Collaborator {
    id: number;
    name?: string | null;
    email?: string | null;
    role?: string | null;
}

interface Referral {
    id: number;
    object_type?: string | null;
    object_id?: number | null;
    created?: string | null;
}

interface TicketShowProps {
    ticket: Ticket;
    customFields: Record<string, string | number | boolean | null>;
    timeline: TimelineItem[];
    attachments: Attachment[];
    collaborators: Collaborator[];
    referrals: Referral[];
}

const ENTRY_KIND_META: Record<string, { label: string; tone: string; icon: typeof Mail01Icon }> = {
    M: { label: 'Message', tone: 'border-sky-200 bg-sky-50 text-sky-700', icon: Mail01Icon },
    R: { label: 'Response', tone: 'border-emerald-200 bg-emerald-50 text-emerald-700', icon: Mail01Icon },
    N: { label: 'Internal note', tone: 'border-amber-200 bg-amber-50 text-amber-800', icon: Note01Icon },
};

const ENTRY_DEFAULT = {
    label: 'Entry',
    tone: 'border-[#E2E8F0] bg-[#F8FAFC] text-[#475569]',
    icon: Mail01Icon,
};

function entryMeta(type: string | null) {
    if (!type) return ENTRY_DEFAULT;
    return ENTRY_KIND_META[type.toUpperCase()] ?? ENTRY_DEFAULT;
}

function entryAnchor(id: number): string {
    return `entry-${id}`;
}

function eventAnchor(id: number): string {
    return `event-${id}`;
}

function isImage(mime: string | null | undefined): boolean {
    return typeof mime === 'string' && mime.toLowerCase().startsWith('image/');
}

function isPdf(mime: string | null | undefined): boolean {
    return typeof mime === 'string' && mime.toLowerCase() === 'application/pdf';
}

function useCopy(timeoutMs = 1500) {
    const [copied, setCopied] = useState<string | null>(null);

    useEffect(() => {
        if (copied === null) return;
        const handle = window.setTimeout(() => setCopied(null), timeoutMs);
        return () => window.clearTimeout(handle);
    }, [copied, timeoutMs]);

    async function copy(value: string, key: string): Promise<void> {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(key);
        } catch {
            /* clipboard unavailable */
        }
    }

    return { copied, copy };
}

interface SlaProgress {
    label: string;
    percent: number;
    tone: 'safe' | 'warn' | 'danger' | 'overdue' | 'closed';
    helper: string | null;
}

function computeSlaProgress(ticket: Ticket, now: Date): SlaProgress | null {
    if (!ticket.duedate) return null;
    const due = new Date(ticket.duedate);
    const created = ticket.created ? new Date(ticket.created) : null;

    if (Number.isNaN(due.getTime())) return null;

    if (ticket.closed) {
        return {
            label: 'Closed',
            percent: 100,
            tone: 'closed',
            helper: formatDateTime(ticket.closed),
        };
    }

    const dueMs = due.getTime();
    const nowMs = now.getTime();

    if (nowMs >= dueMs) {
        return {
            label: 'Overdue',
            percent: 100,
            tone: 'overdue',
            helper: `Was due ${formatRelative(ticket.duedate)}`,
        };
    }

    if (created && !Number.isNaN(created.getTime())) {
        const total = dueMs - created.getTime();
        const elapsed = nowMs - created.getTime();
        const percent = total > 0 ? Math.max(0, Math.min(100, (elapsed / total) * 100)) : 0;
        const tone: SlaProgress['tone'] = percent >= 75 ? 'danger' : percent >= 50 ? 'warn' : 'safe';

        return {
            label: 'On track',
            percent,
            tone,
            helper: `Due ${formatRelative(ticket.duedate)}`,
        };
    }

    return {
        label: 'On track',
        percent: 0,
        tone: 'safe',
        helper: `Due ${formatRelative(ticket.duedate)}`,
    };
}

function Metric({ label, value, helper }: { label: string; value: ReactNode; helper?: string | null }) {
    return (
        <div>
            <dt className="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#94A3B8]">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-[#0F172A]">{value || <span className="text-[#94A3B8]">—</span>}</dd>
            {helper && <p className="mt-0.5 text-xs text-[#94A3B8]">{helper}</p>}
        </div>
    );
}

function SlaBar({ progress }: { progress: SlaProgress }) {
    const trackColor = progress.tone === 'overdue'
        ? 'bg-red-500'
        : progress.tone === 'danger'
        ? 'bg-amber-500'
        : progress.tone === 'warn'
        ? 'bg-yellow-400'
        : progress.tone === 'closed'
        ? 'bg-zinc-400'
        : 'bg-emerald-500';

    const labelTone = progress.tone === 'overdue'
        ? 'text-red-600'
        : progress.tone === 'danger'
        ? 'text-amber-600'
        : progress.tone === 'warn'
        ? 'text-yellow-700'
        : progress.tone === 'closed'
        ? 'text-zinc-500'
        : 'text-emerald-600';

    return (
        <div className="rounded-md bg-[#F8FAFC] px-3 py-2.5">
            <div className="flex items-center justify-between gap-3 text-xs">
                <span className={cn('font-semibold uppercase tracking-[0.14em]', labelTone)}>{progress.label}</span>
                {progress.helper && <span className="text-[#64748B]">{progress.helper}</span>}
            </div>
            <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-[#E2E8F0]">
                <div className={cn('h-full rounded-full transition-all', trackColor)} style={{ width: `${progress.percent}%` }} />
            </div>
        </div>
    );
}

function TicketHeader({ ticket }: { ticket: Ticket }) {
    const { copied, copy } = useCopy();
    const [now, setNow] = useState<Date>(() => new Date());

    useEffect(() => {
        const id = window.setInterval(() => setNow(new Date()), 60_000);
        return () => window.clearInterval(id);
    }, []);

    const sla = computeSlaProgress(ticket, now);

    return (
        <header className="rounded-[18px] border border-[#E2E8F0] bg-white p-6 shadow-sm shadow-[#0F172A]/[0.03] xl:p-8">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 flex-1">
                    <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-1.5 text-xs text-[#94A3B8]">
                        <Link href="/scp/queues" className="hover:text-[#0F172A]">Tickets</Link>
                        <span className="text-[#CBD5E1]">/</span>
                        <button
                            type="button"
                            onClick={() => copy(`#${ticket.number}`, 'number')}
                            className="inline-flex items-center gap-1 font-mono text-[#0F172A] hover:text-[#5B619D]"
                            title="Copy ticket number"
                        >
                            <span>#{ticket.number}</span>
                            <HugeiconsIcon
                                icon={copied === 'number' ? CheckmarkCircle02Icon : Copy01Icon}
                                size={11}
                                className={cn('transition-colors', copied === 'number' ? 'text-emerald-500' : 'text-[#94A3B8]')}
                            />
                        </button>
                        {ticket.created && (
                            <>
                                <span className="text-[#CBD5E1]">·</span>
                                <span title={formatDateTime(ticket.created) ?? undefined}>
                                    Created {formatRelative(ticket.created)}
                                </span>
                            </>
                        )}
                        {ticket.requester && (
                            <>
                                <span className="text-[#CBD5E1]">·</span>
                                <span>by {ticket.requester}</span>
                            </>
                        )}
                    </nav>
                    <h1 className="mt-2 font-display text-2xl font-medium tracking-tight text-[#0F172A]">
                        {ticket.subject ?? `Ticket ${ticket.number}`}
                    </h1>
                    {ticket.requester_email && (
                        <a href={`mailto:${ticket.requester_email}`} className="mt-1 inline-flex items-center gap-1.5 text-xs text-[#5B619D] hover:underline">
                            <HugeiconsIcon icon={Mail01Icon} size={12} />
                            {ticket.requester_email}
                        </a>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge status={ticket.status} state={ticket.status_state} />
                    <PriorityBadge priority={ticket.priority} />
                </div>
            </div>

            <dl className="mt-6 grid grid-cols-2 gap-4 border-t border-[#F1F5F9] pt-6 sm:grid-cols-4">
                <Metric label="Department" value={ticket.department} />
                <Metric label="Assignee" value={ticket.assignee} />
                <Metric
                    label="Due date"
                    value={ticket.duedate ? formatDateTime(ticket.duedate) : null}
                    helper={ticket.duedate ? formatRelative(ticket.duedate) : null}
                />
                <Metric
                    label="Updated"
                    value={ticket.updated ? formatRelative(ticket.updated) : null}
                    helper={ticket.updated ? formatDateTime(ticket.updated) : null}
                />
            </dl>

            {sla && (
                <div className="mt-5">
                    <SlaBar progress={sla} />
                </div>
            )}
        </header>
    );
}

function TimelineEntry({ item }: { item: ThreadEntry }) {
    const meta = entryMeta(item.type);
    const { copied, copy } = useCopy();
    const anchorId = entryAnchor(item.id);

    const safeHtml = useMemo(() => {
        if (!item.body) return '';
        if ((item.format ?? '').toLowerCase() === 'html') {
            return sanitizeHtml(item.body);
        }
        return null;
    }, [item.body, item.format]);

    function shareLink() {
        const url = `${window.location.pathname}#${anchorId}`;
        copy(url, anchorId);
    }

    return (
        <article id={anchorId} className="group relative scroll-mt-24 rounded-[14px] border border-[#E2E8F0] bg-white p-5 shadow-sm shadow-[#0F172A]/[0.02]">
            <header className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]', meta.tone)}>
                        <HugeiconsIcon icon={meta.icon} size={10} />
                        {meta.label}
                    </span>
                    {item.author && <span className="text-sm font-medium text-[#0F172A]">{item.author}</span>}
                </div>
                <div className="flex items-center gap-2 text-xs text-[#94A3B8]">
                    {item.created && (
                        <time
                            dateTime={item.created}
                            title={formatDateTime(item.created) ?? undefined}
                            className="inline-flex items-center gap-1"
                        >
                            <HugeiconsIcon icon={Clock01Icon} size={11} />
                            {formatRelative(item.created)}
                        </time>
                    )}
                    <button
                        type="button"
                        onClick={shareLink}
                        title="Copy link to this entry"
                        className="grid h-6 w-6 place-items-center rounded text-[#94A3B8] opacity-0 transition-all hover:bg-[#F1F5F9] hover:text-[#5B619D] group-hover:opacity-100 focus-visible:opacity-100"
                    >
                        <HugeiconsIcon
                            icon={copied === anchorId ? CheckmarkCircle02Icon : Link02Icon}
                            size={12}
                            className={copied === anchorId ? 'text-emerald-500' : undefined}
                        />
                    </button>
                </div>
            </header>
            {safeHtml ? (
                <div
                    className="prose-ticket mt-4 text-sm leading-relaxed text-[#0F172A]"
                    dangerouslySetInnerHTML={{ __html: safeHtml }}
                />
            ) : item.body ? (
                <p className="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-[#0F172A]">{item.body}</p>
            ) : (
                <p className="mt-4 text-sm italic text-[#94A3B8]">No content.</p>
            )}
        </article>
    );
}

function TimelineEvent({ item }: { item: ThreadEvent }) {
    const anchorId = eventAnchor(item.id);

    return (
        <div id={anchorId} className="flex scroll-mt-24 items-start gap-3 rounded-md border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
            <div className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-white text-[#5B619D] ring-1 ring-[#E2E8F0]">
                <HugeiconsIcon icon={Calendar01Icon} size={12} />
            </div>
            <div className="flex-1 text-xs text-[#64748B]">
                <p className="font-medium text-[#0F172A]">{item.label ?? 'Event'}</p>
                {item.created && (
                    <p className="mt-0.5">
                        <time dateTime={item.created} title={formatDateTime(item.created) ?? undefined}>
                            {formatRelative(item.created)}
                        </time>
                    </p>
                )}
            </div>
        </div>
    );
}

function Timeline({ items }: { items: TimelineItem[] }) {
    const latestEntry = useMemo(() => {
        for (let index = items.length - 1; index >= 0; index--) {
            const item = items[index];
            if (item.kind === 'entry') return item;
        }
        return null;
    }, [items]);

    function jumpToLatest() {
        if (!latestEntry) return;
        const target = document.getElementById(entryAnchor(latestEntry.id));
        target?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    return (
        <section className="rounded-[18px] border border-[#E2E8F0] bg-white p-6 shadow-sm shadow-[#0F172A]/[0.03] xl:p-8">
            <header className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <h2 className="font-display text-lg font-medium text-[#0F172A]">Timeline</h2>
                    <span className="rounded-full bg-[#F1F5F9] px-2 py-0.5 text-[10px] font-semibold text-[#64748B]">
                        {items.length} {items.length === 1 ? 'item' : 'items'}
                    </span>
                </div>
                {latestEntry && items.length > 2 && (
                    <button
                        type="button"
                        onClick={jumpToLatest}
                        className="inline-flex items-center gap-1.5 rounded-md border border-[#E2E8F0] bg-white px-2.5 py-1.5 text-xs font-medium text-[#64748B] transition-colors hover:border-[#CBD5E1] hover:text-[#0F172A]"
                    >
                        <HugeiconsIcon icon={ArrowDown01Icon} size={12} />
                        Jump to latest
                    </button>
                )}
            </header>
            {items.length === 0 ? (
                <p className="mt-5 text-sm text-[#64748B]">No thread entries or events were found.</p>
            ) : (
                <ol className="mt-6 space-y-4">
                    {items.map((item) => (
                        <li key={`${item.kind}-${item.id}`}>
                            {item.kind === 'entry' ? <TimelineEntry item={item} /> : <TimelineEvent item={item} />}
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}

function CustomFieldsPanel({ fields }: { fields: TicketShowProps['customFields'] }) {
    const entries = Object.entries(fields);

    return (
        <SidebarPanel title="Custom Fields">
            {entries.length === 0 ? (
                <PanelEmpty text="No custom fields were captured." />
            ) : (
                <dl className="space-y-3 text-sm">
                    {entries.map(([key, value]) => (
                        <div key={key}>
                            <dt className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#94A3B8]">{key}</dt>
                            <dd className="mt-1 break-words text-[#0F172A]">
                                {value === null || value === '' ? <span className="text-[#94A3B8]">—</span> : String(value)}
                            </dd>
                        </div>
                    ))}
                </dl>
            )}
        </SidebarPanel>
    );
}

function AttachmentsPanel({ attachments }: { attachments: Attachment[] }) {
    const [preview, setPreview] = useState<Attachment | null>(null);

    return (
        <SidebarPanel title="Attachments" count={attachments.length}>
            {attachments.length === 0 ? (
                <PanelEmpty text="No attachments." />
            ) : (
                <ul className="space-y-2">
                    {attachments.map((attachment) => {
                        const previewable = isImage(attachment.mime) || isPdf(attachment.mime);
                        const Icon = isImage(attachment.mime) ? Image01Icon : File01Icon;
                        return (
                            <li key={attachment.id}>
                                <div className="group flex items-start gap-3 rounded-md border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2.5 text-sm transition-colors hover:bg-white">
                                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-white text-[#5B619D] ring-1 ring-[#E2E8F0]">
                                        <HugeiconsIcon icon={Icon} size={14} />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium text-[#0F172A]">
                                            {attachment.name ?? `File ${attachment.file_id}`}
                                        </p>
                                        <p className="mt-0.5 flex items-center gap-2 text-xs text-[#94A3B8]">
                                            {attachment.mime && <span className="truncate">{attachment.mime}</span>}
                                            {formatBytes(attachment.size) && (
                                                <>
                                                    <span className="text-[#CBD5E1]">·</span>
                                                    <span>{formatBytes(attachment.size)}</span>
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex flex-col gap-1">
                                        {previewable && (
                                            <button
                                                type="button"
                                                onClick={() => setPreview(attachment)}
                                                className="grid h-7 w-7 place-items-center rounded text-[#94A3B8] transition-colors hover:bg-[#F1F5F9] hover:text-[#5B619D]"
                                                title="Preview"
                                                aria-label="Preview attachment"
                                            >
                                                <HugeiconsIcon icon={Image01Icon} size={13} />
                                            </button>
                                        )}
                                        <a
                                            href={attachment.download_url}
                                            className="grid h-7 w-7 place-items-center rounded text-[#94A3B8] transition-colors hover:bg-[#F1F5F9] hover:text-[#5B619D]"
                                            title="Download"
                                            aria-label="Download attachment"
                                            download
                                        >
                                            <HugeiconsIcon icon={Download01Icon} size={13} />
                                        </a>
                                    </div>
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
            <AttachmentPreview attachment={preview} onClose={() => setPreview(null)} />
        </SidebarPanel>
    );
}

function AttachmentPreview({ attachment, onClose }: { attachment: Attachment | null; onClose: () => void }) {
    const open = attachment !== null;

    return (
        <DialogPrimitive.Root open={open} onOpenChange={(value) => { if (!value) onClose(); }}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Backdrop className="fixed inset-0 z-40 bg-[#0F172A]/70 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
                <DialogPrimitive.Popup className="fixed inset-0 z-50 grid place-items-center p-4 outline-none">
                    {attachment && (
                        <div className="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-white shadow-2xl">
                            <div className="flex items-center justify-between gap-3 border-b border-[#E2E8F0] bg-white px-4 py-3">
                                <div className="min-w-0 flex-1">
                                    <DialogPrimitive.Title className="truncate text-sm font-medium text-[#0F172A]">
                                        {attachment.name ?? `File ${attachment.file_id}`}
                                    </DialogPrimitive.Title>
                                    <p className="text-xs text-[#94A3B8]">
                                        {attachment.mime} {formatBytes(attachment.size) ? `· ${formatBytes(attachment.size)}` : ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <a
                                        href={attachment.download_url}
                                        download
                                        className="inline-flex items-center gap-1.5 rounded-md border border-[#E2E8F0] bg-white px-2.5 py-1.5 text-xs font-medium text-[#64748B] transition-colors hover:border-[#CBD5E1] hover:text-[#0F172A]"
                                    >
                                        <HugeiconsIcon icon={Download01Icon} size={12} />
                                        Download
                                    </a>
                                    <DialogPrimitive.Close
                                        className="grid h-8 w-8 place-items-center rounded-md text-[#64748B] transition-colors hover:bg-[#F1F5F9] hover:text-[#0F172A]"
                                        aria-label="Close preview"
                                    >
                                        <HugeiconsIcon icon={Cancel01Icon} size={16} />
                                    </DialogPrimitive.Close>
                                </div>
                            </div>
                            <div className="flex flex-1 items-center justify-center overflow-auto bg-[#F1F5F9]">
                                {isImage(attachment.mime) ? (
                                    <img
                                        src={attachment.download_url}
                                        alt={attachment.name ?? ''}
                                        className="max-h-[80vh] max-w-full object-contain"
                                    />
                                ) : isPdf(attachment.mime) ? (
                                    <iframe
                                        src={attachment.download_url}
                                        title={attachment.name ?? 'Attachment preview'}
                                        className="h-[80vh] w-full bg-white"
                                    />
                                ) : (
                                    <p className="p-12 text-sm text-[#64748B]">No preview available for this file type.</p>
                                )}
                            </div>
                        </div>
                    )}
                </DialogPrimitive.Popup>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

function CollaboratorsPanel({ collaborators }: { collaborators: Collaborator[] }) {
    return (
        <SidebarPanel title="Collaborators" count={collaborators.length}>
            {collaborators.length === 0 ? (
                <PanelEmpty text="No collaborators." />
            ) : (
                <ul className="space-y-3">
                    {collaborators.map((collaborator) => (
                        <li key={collaborator.id} className="flex items-start gap-3">
                            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#F1F5F9] text-[#5B619D]">
                                <HugeiconsIcon icon={UserGroupIcon} size={14} />
                            </span>
                            <div className="min-w-0 flex-1 text-sm">
                                <p className="truncate font-medium text-[#0F172A]">
                                    {collaborator.name ?? collaborator.email ?? 'Unknown'}
                                </p>
                                {collaborator.email && collaborator.name && (
                                    <p className="truncate text-xs text-[#94A3B8]">{collaborator.email}</p>
                                )}
                                {collaborator.role && (
                                    <span className="mt-1 inline-flex items-center rounded-full bg-[#F3ECFF] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#5B619D]">
                                        {collaborator.role}
                                    </span>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </SidebarPanel>
    );
}

function ReferralsPanel({ referrals }: { referrals: Referral[] }) {
    return (
        <SidebarPanel title="Referrals" count={referrals.length}>
            {referrals.length === 0 ? (
                <PanelEmpty text="No referrals." />
            ) : (
                <ul className="space-y-2 text-sm">
                    {referrals.map((referral) => (
                        <li key={referral.id} className="flex items-center justify-between rounded-md border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2">
                            <span className="flex items-center gap-2 font-medium text-[#0F172A]">
                                <HugeiconsIcon icon={Link01Icon} size={12} className="text-[#5B619D]" />
                                {referral.object_type ?? 'object'} #{referral.object_id ?? '?'}
                            </span>
                            {referral.created && (
                                <time dateTime={referral.created} className="text-xs text-[#94A3B8]" title={formatDateTime(referral.created) ?? undefined}>
                                    {formatRelative(referral.created)}
                                </time>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </SidebarPanel>
    );
}

function SidebarPanel({ title, count, children }: { title: string; count?: number; children: ReactNode }) {
    return (
        <section className="rounded-[18px] border border-[#E2E8F0] bg-white p-5 shadow-sm shadow-[#0F172A]/[0.03]">
            <header className="mb-4 flex items-center justify-between">
                <h3 className="font-display text-base font-medium text-[#0F172A]">{title}</h3>
                {count !== undefined && (
                    <span className="rounded-full bg-[#F1F5F9] px-2 py-0.5 text-[10px] font-semibold text-[#64748B]">{count}</span>
                )}
            </header>
            {children}
        </section>
    );
}

function PanelEmpty({ text }: { text: string }) {
    return <p className="text-sm text-[#64748B]">{text}</p>;
}

function StatusSummary({ ticket }: { ticket: Ticket }) {
    return (
        <SidebarPanel title="Summary">
            <dl className="space-y-3 text-sm">
                <SummaryRow icon={CheckmarkCircle02Icon} label="Status" value={ticket.status ?? '—'} />
                <SummaryRow icon={AlertCircleIcon} label="Priority" value={ticket.priority ?? '—'} />
                <SummaryRow icon={UserGroupIcon} label="Department" value={ticket.department ?? '—'} />
                <SummaryRow icon={Calendar01Icon} label="Created" value={(ticket.created ? formatDateTime(ticket.created) : null) ?? '—'} />
            </dl>
        </SidebarPanel>
    );
}

function SummaryRow({ icon, label, value }: { icon: typeof Mail01Icon; label: string; value: string }) {
    return (
        <div className="flex items-start gap-2.5">
            <span className="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md bg-[#F1F5F9] text-[#5B619D]">
                <HugeiconsIcon icon={icon} size={11} />
            </span>
            <div className="min-w-0 flex-1">
                <dt className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#94A3B8]">{label}</dt>
                <dd className="mt-0.5 break-words text-[#0F172A]">{value}</dd>
            </div>
        </div>
    );
}

export default function TicketShow({ ticket, customFields, timeline, attachments, collaborators, referrals }: TicketShowProps) {
    return (
        <div className="space-y-6">
            <TicketHeader ticket={ticket} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <Timeline items={timeline} />

                <aside className="space-y-6 xl:sticky xl:top-6 xl:self-start">
                    <StatusSummary ticket={ticket} />
                    <CustomFieldsPanel fields={customFields} />
                    <AttachmentsPanel attachments={attachments} />
                    <CollaboratorsPanel collaborators={collaborators} />
                    <ReferralsPanel referrals={referrals} />
                </aside>
            </div>
        </div>
    );
}

function TicketHeaderTitle() {
    const { props } = usePage<{ ticket?: Ticket }>();
    const number = props.ticket?.number ?? '';

    return (
        <div className="flex items-baseline gap-2.5">
            <h1 className="font-display text-xl font-medium tracking-[-0.02em] text-[#0F172A]">
                #{number}
            </h1>
            <span className="text-[#94A3B8]">·</span>
            <span className="font-body text-[11px] font-medium uppercase tracking-[0.12em] text-[#94A3B8]">
                Tickets
            </span>
        </div>
    );
}

type TicketShowComponent = typeof TicketShow & {
    layout?: (page: ReactElement) => ReactNode;
};

(TicketShow as TicketShowComponent).layout = (page) => (
    <DashboardLayout
        headerLeft={<TicketHeaderTitle />}
        activeNav="queues"
        contentClassName="w-full max-w-7xl mx-auto"
        headerActions={
            <Link
                href="/scp/queues"
                className="inline-flex items-center gap-2 rounded-md border border-[#E2E8F0] bg-white px-3 py-2 text-xs font-medium text-[#64748B] transition-colors hover:text-[#0F172A]"
            >
                Back to tickets
            </Link>
        }
    >
        {page}
    </DashboardLayout>
);
