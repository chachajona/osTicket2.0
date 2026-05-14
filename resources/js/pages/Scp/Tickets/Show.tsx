import { Link } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState, type ReactElement, type ReactNode } from 'react';
import { Dialog as DialogPrimitive } from '@base-ui/react/dialog';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowLeft01Icon,
    ArrowLeft02Icon,
    ArrowRight02Icon,
    MoreHorizontalIcon,
    Ticket01Icon,
    Cancel01Icon,
    Download01Icon,
    Image01Icon,
    ComputerIcon,
    RefreshIcon,
    FilterIcon,
    Copy01Icon,
    Bookmark01Icon,
    Delete01Icon,
    UserIcon,
    UserGroupIcon,
    Chat01Icon,
    SmartPhone01Icon,
    Mail01Icon,
    PinIcon,
    PinOffIcon,
} from '@hugeicons/core-free-icons';

import { type StatusOption } from '@/components/tickets/StatusPicker';
import { NoteComposer } from '@/components/tickets/NoteComposer';
import { appShellLayout, SetPageHeader } from '@/layouts/AppShell';
import { formatBytes, formatDateTime, formatRelative } from '@/lib/datetime';
import { sanitizeHtml } from '@/lib/sanitizeHtml';
import { cn } from '@/lib/utils';
import {
    SplitButton,
    IconBtn
} from '@/components/tickets/TicketDetailComponents';
import { TicketInfoPanel } from '@/components/tickets/TicketInfoPanel';

interface Ticket {
    id: number;
    number: string;
    status: string | null;
    status_state: string | null;
    priority: string | null;
    department: string | null;
    assignee: string | null;
    team: string | null;
    sla_id: number;
    source: string | null;
    source_extra: string | null;
    ip_address: string | null;
    isoverdue: boolean;
    isanswered: boolean;
    duedate: string | null;
    est_duedate: string | null;
    reopened: string | null;
    created: string | null;
    updated: string | null;
    lastupdate: string | null;
    lastmessage: string | null;
    lastresponse: string | null;
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
    permissions?: {
        canSetStatus?: boolean;
        canAssign?: boolean;
        canPostNote?: boolean;
    };
    availableStatuses?: StatusOption[];
    staffOptions?: { id: number; name: string }[];
    teamOptions?: { id: number; name: string }[];
}

function isImage(mime: string | null | undefined): boolean {
    return typeof mime === 'string' && mime.toLowerCase().startsWith('image/');
}

function isPdf(mime: string | null | undefined): boolean {
    return typeof mime === 'string' && mime.toLowerCase() === 'application/pdf';
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
        let tone: SlaProgress['tone'];
        if (percent >= 75) {
            tone = 'danger';
        } else if (percent >= 50) {
            tone = 'warn';
        } else {
            tone = 'safe';
        }

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

function AttachmentPreview({ attachment, onClose }: { attachment: Attachment | null; onClose: () => void }) {
    const open = attachment !== null;

    return (
        <DialogPrimitive.Root open={open} onOpenChange={(value) => { if (!value) onClose(); }}>
            <DialogPrimitive.Portal>
                <DialogPrimitive.Backdrop className="fixed inset-0 z-40 bg-[#18181B]/70 data-open:animate-in data-open:fade-in-0 data-closed:animate-out data-closed:fade-out-0" />
                <DialogPrimitive.Popup className="fixed inset-0 z-50 grid place-items-center p-4 outline-none">
                    {attachment && (
                        <div className="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-white shadow-2xl">
                            <div className="flex items-center justify-between gap-3 border-b border-[#E2E0D8] bg-white px-4 py-3">
                                <div className="min-w-0 flex-1">
                                    <DialogPrimitive.Title className="truncate text-sm font-medium text-[#18181B]">
                                        {attachment.name ?? `File ${attachment.file_id}`}
                                    </DialogPrimitive.Title>
                                    <p className="text-xs text-[#A1A1AA]">
                                        {attachment.mime} {formatBytes(attachment.size) ? `· ${formatBytes(attachment.size)}` : ''}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <a
                                        href={attachment.download_url}
                                        download
                                        className="inline-flex h-7 items-center gap-1.5 rounded-[3px] border border-[#E2E0D8] bg-white px-3 text-[12px] font-medium uppercase leading-4 tracking-[1.2px] text-[#27272A] transition-colors hover:border-[#18181B] hover:bg-[#FAFAF8] hover:text-[#18181B]"
                                    >
                                        <HugeiconsIcon icon={Download01Icon} size={12} />
                                        Download
                                    </a>
                                    <DialogPrimitive.Close
                                        className="grid h-8 w-8 place-items-center rounded-md text-[#71717A] transition-colors hover:bg-[#F4F2EB] hover:text-[#18181B]"
                                        aria-label="Close preview"
                                    >
                                        <HugeiconsIcon icon={Cancel01Icon} size={16} />
                                    </DialogPrimitive.Close>
                                </div>
                            </div>
                            <div className="flex flex-1 items-center justify-center overflow-auto bg-[#F4F2EB]">
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
                                    <p className="p-12 text-sm text-[#71717A]">No preview available for this file type.</p>
                                )}
                            </div>
                        </div>
                    )}
                </DialogPrimitive.Popup>
            </DialogPrimitive.Portal>
        </DialogPrimitive.Root>
    );
}

export default function TicketShow({ ticket, customFields, timeline, attachments, collaborators, referrals, permissions, staffOptions, teamOptions, availableStatuses }: TicketShowProps) {
    const [activeTab, setActiveTab] = useState('conversation');
    const [moreMenuOpen, setMoreMenuOpen] = useState(false);
    const [now, setNow] = useState<Date>(() => new Date());

    useEffect(() => {
        const id = window.setInterval(() => setNow(new Date()), 60_000);
        return () => window.clearInterval(id);
    }, []);

    const sla = computeSlaProgress(ticket, now);

    const tabs = [
        { id: 'conversation', label: 'Conversation' },
        { id: 'task', label: 'Task' },
        { id: 'activity', label: 'Activity Logs' },
        { id: 'notes', label: 'Notes' },
    ];

    const sideIcons = [
        UserIcon,
        UserGroupIcon,
        Chat01Icon,
        SmartPhone01Icon,
        Mail01Icon,
    ];

    // Reconstruct timeline into Thread Messages format
    const threadMessages = useMemo(() => timeline.map(item => {
        if (item.kind === 'event') {
            return {
                id: item.id,
                type: 'system',
                content: item.label ?? 'Event',
                time: item.created ? formatDateTime(item.created) : '',
            };
        } else {
            const isInternal = item.type === 'N';
            const isAgent = item.type === 'M'; // Or R for response
            return {
                id: item.id,
                type: isInternal ? 'internal' : (isAgent ? 'agent' : 'customer'),
                author: item.author ?? 'Unknown',
                avatar: (item.author ?? '?').slice(0, 2).toUpperCase(),
                time: item.created ? formatDateTime(item.created) : '',
                via: 'Portal',
                content: sanitizeHtml(item.body ?? ''),
                isHtml: (item.format ?? '').toLowerCase() === 'html'
            };
        }
    }), [timeline]);

    const [preview, setPreview] = useState<Attachment | null>(null);

    const PANEL_COLLAPSED_KEY = 'ticket.panel.collapsed';
    const [panelCollapsed, setPanelCollapsed] = useState(() => {
        if (typeof window === 'undefined') return false;
        try {
            return window.localStorage.getItem(PANEL_COLLAPSED_KEY) === '1';
        } catch {
            return false;
        }
    });

    const togglePanel = useCallback(() => {
        setPanelCollapsed((prev) => {
            const next = !prev;
            try {
                window.localStorage.setItem(PANEL_COLLAPSED_KEY, next ? '1' : '0');
            } catch {
            }
            return next;
        });
    }, []);

    return (
        <>
            <SetPageHeader>
                <div className="flex w-full items-center justify-between">
                    <div className="flex min-w-0 items-center gap-3">
                        <Link
                            href="/scp/queues"
                            className="flex items-center gap-1.5 whitespace-nowrap px-0 font-sans text-[13px] font-medium text-[#A1A1AA] hover:text-[#18181B]"
                        >
                            <HugeiconsIcon icon={ArrowLeft01Icon} size={16} />
                            Ticket List
                        </Link>

                        <div className="flex gap-0.5">
                            <IconBtn icon={ArrowLeft02Icon} size={28} className="border-none shadow-none" />
                            <IconBtn icon={ArrowRight02Icon} size={28} className="border-none shadow-none" />
                        </div>

                        <div className="flex min-w-0 items-baseline gap-2.5">
                            <span className="shrink-0 font-mono text-[14px] font-semibold text-[#18181B]">#{ticket.number}</span>
                            <span className="truncate text-[15px] font-medium text-[#18181B]">{ticket.subject ?? 'No Subject'}</span>
                        </div>
                    </div>

                    <div className="relative flex shrink-0 items-center gap-2.5">
                        <IconBtn
                            icon={MoreHorizontalIcon}
                            size={34}
                            onClick={() => setMoreMenuOpen(!moreMenuOpen)}
                        />
                        <SplitButton label="Submit as New" />

                        {moreMenuOpen && (
                            <div className="absolute right-14 top-full z-50 mt-1 w-[180px] rounded-md border border-[#E2E0D8] bg-white py-1.5 shadow-[0_8px_24px_rgba(0,0,0,0.1)]">
                                {[
                                    { icon: ComputerIcon, label: 'Remote Assist' },
                                    { icon: RefreshIcon, label: 'Refresh' },
                                    { icon: FilterIcon, label: 'Filters' },
                                    { icon: Copy01Icon, label: 'Merge Ticket' },
                                    { icon: Bookmark01Icon, label: 'Add to Shortcut' },
                                    { icon: Delete01Icon, label: 'Delete', danger: true },
                                ].map((item, i) => (
                                    <button
                                        key={item.label}
                                        className={cn(
                                            'flex w-full items-center gap-2 px-4 py-2 font-sans text-[13px] text-left transition-colors hover:bg-[#F4F2EB]',
                                            item.danger ? 'text-red-600' : 'text-[#18181B]'
                                        )}
                                    >
                                        <HugeiconsIcon icon={item.icon} size={16} className={item.danger ? 'text-red-600' : 'text-[#A1A1AA]'} />
                                        {item.label}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </SetPageHeader>

            <div className="-mx-6 -my-5 flex h-[calc(100vh-80px)] flex-col sm:-mx-8 lg:-mx-10 lg:flex-row">
                {/* Center / Thread Area */}
                <div className="flex min-w-0 flex-1 flex-col border-r border-[#E2E0D8]">
                    {/* Tabs */}
                    <div className="flex shrink-0 justify-center gap-0 border-b border-[#E2E0D8]">
                        {tabs.map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={cn(
                                    'mb-[-1px] border-b-2 px-5 py-2.5 font-sans text-[13px] transition-all',
                                    activeTab === tab.id
                                        ? 'border-[#F97316] font-medium text-[#F97316]'
                                        : 'border-transparent font-normal text-[#A1A1AA] hover:text-[#18181B]'
                                )}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    {/* Messages Area */}
                    <div className="custom-scrollbar flex-1 overflow-y-auto px-8 py-6">
                        {threadMessages.length === 0 ? (
                            <p className="text-sm text-[#A1A1AA]">No thread entries found.</p>
                        ) : (
                            threadMessages.map((msg, idx) => {
                                if (msg.type === 'system') {
                                    return (
                                        <div key={msg.id + '-' + idx} className="mb-6 flex items-center gap-2.5">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-full border border-[#E2E0D8] bg-[#F4F2EB]">
                                                <HugeiconsIcon icon={Ticket01Icon} size={14} className="text-[#A1A1AA]" />
                                            </div>
                                            <span className="text-[13px] text-[#A1A1AA]">
                                                <strong className="font-medium text-[#18181B]">{msg.content}</strong>
                                                {' · ' + msg.time}
                                            </span>
                                        </div>
                                    );
                                }

                                const isAgent = msg.type === 'agent' || msg.type === 'internal';

                                return (
                                    <div key={msg.id + '-' + idx} className="mb-6 flex gap-3">
                                        <div className={cn(
                                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tracking-[0.02em]',
                                            isAgent ? 'bg-gradient-to-br from-[#FB923C] via-[#EC4899] to-[#6366F1] text-white' : 'bg-[#E2E0D8] text-[#71717A]'
                                        )}>
                                            {msg.avatar}
                                        </div>
                                        <div className="flex-1">
                                            <div className="mb-1 flex flex-wrap items-center gap-2">
                                                <span className="text-[13px] font-semibold text-[#18181B]">{msg.author}</span>
                                                <span className="text-[12px] text-[#71717A]">{'· ' + msg.time}</span>
                                                <span className="text-[11px] text-[#71717A]">· Via</span>
                                                <span className="inline-flex items-center gap-[3px] text-[11px] text-[#A1A1AA]">
                                                    <HugeiconsIcon icon={msg.via === 'Email' ? Mail01Icon : Chat01Icon} size={12} className="text-[#71717A]" />
                                                    {msg.via}
                                                </span>
                                            </div>
                                            {msg.isHtml ? (
                                                <div
                                                    className="prose-ticket text-[14px] leading-[22px] text-[#18181B]"
                                                    dangerouslySetInnerHTML={{ __html: msg.content }}
                                                />
                                            ) : (
                                                <p className="whitespace-pre-wrap text-[14px] leading-[22px] text-[#18181B]">
                                                    {msg.content}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    {permissions?.canPostNote && (
                        <NoteComposer ticketId={ticket.id} expectedUpdated={ticket.updated ?? ''} />
                    )}
                </div>

                <div className="flex shrink-0 overflow-hidden border-l border-[#E2E0D8]">
                    <div
                        className="transition-[width] duration-200 ease-in-out"
                        style={{ width: panelCollapsed ? 0 : 300 }}
                    >
                        <TicketInfoPanel
                            ticket={ticket}
                            customFields={customFields}
                            attachments={attachments}
                            collaborators={collaborators}
                            referrals={referrals}
                            onPreviewAttachment={setPreview}
                            onToggleCollapse={togglePanel}
                            isCollapsed={panelCollapsed}
                        />
                    </div>

                    <div className="flex w-10 shrink-0 flex-col items-center gap-1 border-l border-[#E2E0D8] bg-[#FAFAF8] pt-4">
                        <button
                            type="button"
                            onClick={togglePanel}
                            className={cn(
                                'flex h-8 w-8 items-center justify-center rounded-sm transition-colors',
                                panelCollapsed ? 'text-[#F97316]' : 'bg-[#F97316] text-white'
                            )}
                            title={panelCollapsed ? 'Expand panel' : 'Collapse panel'}
                        >
                            <HugeiconsIcon icon={panelCollapsed ? PinIcon : PinOffIcon} size={16} />
                        </button>
                        <div className="my-1 h-px w-6 bg-[#E2E0D8]" />
                        {sideIcons.map((ic, i) => (
                            <button
                                key={i}
                                type="button"
                                className={cn(
                                    'flex h-8 w-8 items-center justify-center rounded-sm transition-colors',
                                    i === 0 ? 'bg-[#F97316] text-white' : 'text-[#71717A] hover:bg-[#E2E0D8] hover:text-[#18181B]'
                                )}
                            >
                                <HugeiconsIcon icon={ic} size={16} />
                            </button>
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

type TicketShowComponent = typeof TicketShow & {
    layout?: (page: ReactElement) => ReactNode;
};

(TicketShow as TicketShowComponent).layout = appShellLayout;
