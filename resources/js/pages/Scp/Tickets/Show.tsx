import { Link } from '@inertiajs/react';
import type { ReactElement, ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Calendar01Icon,
    Clock01Icon,
    File01Icon,
    Mail01Icon,
    Note01Icon,
    UserGroupIcon,
} from '@hugeicons/core-free-icons';

import { PriorityBadge } from '@/components/scp/PriorityBadge';
import { StatusBadge } from '@/components/scp/StatusBadge';
import DashboardLayout from '@/layouts/DashboardLayout';
import { formatBytes, formatDateTime, formatRelative } from '@/lib/datetime';
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

function stripHtml(value: string | null | undefined): string {
    if (!value) return '';
    return value
        .replace(/<style[\s\S]*?<\/style>/gi, '')
        .replace(/<script[\s\S]*?<\/script>/gi, '')
        .replace(/<br\s*\/?>(\s|&nbsp;)*/gi, '\n')
        .replace(/<\/p>\s*/gi, '\n\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function entryMeta(type: string | null) {
    if (!type) return ENTRY_DEFAULT;
    return ENTRY_KIND_META[type.toUpperCase()] ?? ENTRY_DEFAULT;
}

function Metric({ label, value, helper }: { label: string; value: ReactNode; helper?: string }) {
    return (
        <div>
            <dt className="text-[10px] font-semibold uppercase tracking-[0.16em] text-[#94A3B8]">{label}</dt>
            <dd className="mt-1.5 text-sm font-medium text-[#0F172A]">{value || <span className="text-[#94A3B8]">—</span>}</dd>
            {helper && <p className="mt-0.5 text-xs text-[#94A3B8]">{helper}</p>}
        </div>
    );
}

function TicketHeader({ ticket }: { ticket: Ticket }) {
    return (
        <header className="rounded-[18px] border border-[#E2E8F0] bg-white p-6 shadow-sm shadow-[#0F172A]/[0.03] xl:p-8">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2 text-xs text-[#94A3B8]">
                        <span className="font-mono text-[#0F172A]">#{ticket.number}</span>
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
                    </div>
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
                    helper={ticket.duedate ? (formatRelative(ticket.duedate) ?? undefined) : undefined}
                />
                <Metric
                    label="Updated"
                    value={ticket.updated ? formatRelative(ticket.updated) : null}
                    helper={ticket.updated ? (formatDateTime(ticket.updated) ?? undefined) : undefined}
                />
            </dl>
        </header>
    );
}

function TimelineEntry({ item }: { item: ThreadEntry }) {
    const meta = entryMeta(item.type);
    const text = stripHtml(item.body);

    return (
        <article className="relative rounded-[14px] border border-[#E2E8F0] bg-white p-5 shadow-sm shadow-[#0F172A]/[0.02]">
            <header className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <span className={cn('inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.14em]', meta.tone)}>
                        <HugeiconsIcon icon={meta.icon} size={10} />
                        {meta.label}
                    </span>
                    {item.author && <span className="text-sm font-medium text-[#0F172A]">{item.author}</span>}
                </div>
                {item.created && (
                    <time
                        dateTime={item.created}
                        title={formatDateTime(item.created) ?? undefined}
                        className="inline-flex items-center gap-1 text-xs text-[#94A3B8]"
                    >
                        <HugeiconsIcon icon={Clock01Icon} size={11} />
                        {formatRelative(item.created)}
                    </time>
                )}
            </header>
            {text ? (
                <p className="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-[#0F172A]">{text}</p>
            ) : (
                <p className="mt-4 text-sm italic text-[#94A3B8]">No content.</p>
            )}
        </article>
    );
}

function TimelineEvent({ item }: { item: ThreadEvent }) {
    return (
        <div className="flex items-start gap-3 rounded-md border border-dashed border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
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
    return (
        <section className="rounded-[18px] border border-[#E2E8F0] bg-white p-6 shadow-sm shadow-[#0F172A]/[0.03] xl:p-8">
            <header className="flex items-center justify-between">
                <h2 className="font-display text-lg font-medium text-[#0F172A]">Timeline</h2>
                <span className="text-xs text-[#94A3B8]">{items.length} {items.length === 1 ? 'item' : 'items'}</span>
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
    return (
        <SidebarPanel title="Attachments" count={attachments.length}>
            {attachments.length === 0 ? (
                <PanelEmpty text="No attachments." />
            ) : (
                <ul className="space-y-2">
                    {attachments.map((attachment) => (
                        <li key={attachment.id}>
                            <a
                                href={attachment.download_url}
                                className="flex items-start gap-3 rounded-md border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2.5 text-sm transition-colors hover:bg-white"
                            >
                                <span className="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-white text-[#5B619D] ring-1 ring-[#E2E8F0]">
                                    <HugeiconsIcon icon={File01Icon} size={14} />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate font-medium text-[#0F172A]">
                                        {attachment.name ?? `File ${attachment.file_id}`}
                                    </span>
                                    <span className="mt-0.5 flex items-center gap-2 text-xs text-[#94A3B8]">
                                        {attachment.mime && <span className="truncate">{attachment.mime}</span>}
                                        {formatBytes(attachment.size) && (
                                            <>
                                                <span className="text-[#CBD5E1]">·</span>
                                                <span>{formatBytes(attachment.size)}</span>
                                            </>
                                        )}
                                    </span>
                                </span>
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </SidebarPanel>
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
                            <span className="font-medium text-[#0F172A]">
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

export default function TicketShow({ ticket, customFields, timeline, attachments, collaborators, referrals }: TicketShowProps) {
    return (
        <div className="space-y-6">
            <TicketHeader ticket={ticket} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <Timeline items={timeline} />

                <aside className="space-y-6">
                    <CustomFieldsPanel fields={customFields} />
                    <AttachmentsPanel attachments={attachments} />
                    <CollaboratorsPanel collaborators={collaborators} />
                    <ReferralsPanel referrals={referrals} />
                </aside>
            </div>
        </div>
    );
}

type TicketShowComponent = typeof TicketShow & {
    layout?: (page: ReactElement) => ReactNode;
};

(TicketShow as TicketShowComponent).layout = (page) => (
    <DashboardLayout
        title="Ticket"
        eyebrow="Tickets"
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
