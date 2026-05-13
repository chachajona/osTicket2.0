import { useState, type ReactNode } from 'react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    ArrowDown01Icon,
    ArrowUp01Icon,
    UserIcon,
    Mail01Icon,
    SmartPhone01Icon,
    Building02Icon,
    Calendar01Icon,
    Clock01Icon,
    Tag01Icon,
    Link01Icon,
    FolderAttachmentIcon,
    UserGroupIcon,
    HashtagIcon,
    GlobeIcon,
    Wifi01Icon,
    Shield01Icon,
    Cancel01Icon,
    Image01Icon,
    Download01Icon,
    File01Icon,
    InformationCircleIcon,
    Alert01Icon,
    CheckmarkCircle01Icon,
    ComputerIcon,
    TelephoneIcon,
    Message01Icon,
    FlashIcon,
    PinIcon,
    PinOffIcon,
    MoreHorizontalIcon,
} from '@hugeicons/core-free-icons';
import { cn } from '@/lib/utils';
import { formatDateTime, formatBytes, formatRelative } from '@/lib/datetime';

export interface Ticket {
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

export interface Attachment {
    id: number;
    file_id: number;
    name: string | null;
    mime: string | null;
    size: number | null;
    inline?: boolean;
    download_url: string;
}

export interface Collaborator {
    id: number;
    name?: string | null;
    email?: string | null;
    role?: string | null;
}

export interface Referral {
    id: number;
    object_type?: string | null;
    object_id?: number | null;
    created?: string | null;
}

export interface Tag {
    id: number;
    name: string;
    color?: string | null;
}

export interface LinkedProblem {
    id: number;
    number: string;
    subject: string;
    status: string;
}

export interface TicketInfoPanelProps {
    ticket: Ticket;
    customFields: Record<string, string | number | boolean | null>;
    attachments: Attachment[];
    collaborators: Collaborator[];
    referrals: Referral[];
    tags?: Tag[];
    linkedProblems?: LinkedProblem[];
    onPreviewAttachment?: (attachment: Attachment) => void;
    onToggleCollapse?: () => void;
    isCollapsed?: boolean;
}

function Section({
    title,
    icon,
    children,
    defaultOpen = true,
    badge,
}: {
    title: string;
    icon: any;
    children: ReactNode;
    defaultOpen?: boolean;
    badge?: string | number;
}) {
    const [isOpen, setIsOpen] = useState(defaultOpen);

    return (
        <div className="border-b border-[#E2E0D8]">
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-[#FAFAF8]"
            >
                <div className="flex items-center gap-2">
                    <HugeiconsIcon icon={icon} size={14} className="text-[#A1A1AA]" />
                    <span className="text-[13px] font-semibold text-[#18181B]">{title}</span>
                    {badge !== undefined && (
                        <span className="rounded-full bg-[#F4F2EB] px-1.5 py-0.5 text-[10px] font-medium text-[#71717A]">
                            {badge}
                        </span>
                    )}
                </div>
                <HugeiconsIcon
                    icon={isOpen ? ArrowUp01Icon : ArrowDown01Icon}
                    size={14}
                    className="text-[#A1A1AA] transition-transform"
                />
            </button>
            {isOpen && <div className="px-4 pb-4">{children}</div>}
        </div>
    );
}

function InfoRow({ label, value, icon }: { label: string; value: ReactNode; icon?: any }) {
    return (
        <div className="flex items-start gap-2 py-1.5">
            {icon && <HugeiconsIcon icon={icon} size={13} className="mt-0.5 shrink-0 text-[#A1A1AA]" />}
            <div className="min-w-0 flex-1">
                <dt className="text-[11px] font-medium uppercase tracking-wide text-[#A1A1AA]">{label}</dt>
                <dd className="mt-0.5 text-[13px] text-[#18181B]">{value}</dd>
            </div>
        </div>
    );
}

function StatusBadge({ status, state }: { status: string | null; state: string | null }) {
    const colors: Record<string, string> = {
        open: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        closed: 'bg-gray-50 text-gray-600 border-gray-200',
        resolved: 'bg-blue-50 text-blue-700 border-blue-200',
        archived: 'bg-amber-50 text-amber-700 border-amber-200',
    };

    const dotColors: Record<string, string> = {
        open: 'bg-emerald-500',
        closed: 'bg-gray-400',
        resolved: 'bg-blue-500',
        archived: 'bg-amber-500',
    };

    const normalized = (state ?? 'open').toLowerCase();
    const colorClass = colors[normalized] || colors.open;
    const dotClass = dotColors[normalized] || dotColors.open;

    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-[3px] border px-2.5 py-1 text-xs font-medium', colorClass)}>
            <span className={cn('h-1.5 w-1.5 rounded-full', dotClass)} />
            {status ?? 'Unknown'}
        </span>
    );
}

function PriorityBadge({ priority }: { priority: string | null }) {
    const colors: Record<string, string> = {
        Low: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        Medium: 'bg-yellow-50 text-yellow-700 border-yellow-200',
        High: 'bg-red-50 text-red-700 border-red-200',
        Urgent: 'bg-red-100 text-red-800 border-red-300',
    };

    const dotColors: Record<string, string> = {
        Low: 'bg-emerald-500',
        Medium: 'bg-yellow-500',
        High: 'bg-red-500',
        Urgent: 'bg-red-600',
    };

    const colorClass = colors[priority ?? ''] || colors.Medium;
    const dotClass = dotColors[priority ?? ''] || dotColors.Medium;

    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-[3px] border px-2.5 py-1 text-xs font-medium', colorClass)}>
            <span className={cn('h-1.5 w-1.5 rounded-full', dotClass)} />
            {priority ?? 'Medium'}
        </span>
    );
}

function SourceBadge({ source, sourceExtra }: { source: string | null; sourceExtra?: string | null }) {
    const sourceIcons: Record<string, any> = {
        Web: GlobeIcon,
        Email: Mail01Icon,
        Phone: TelephoneIcon,
        Chat_FB: Message01Icon,
        Chat_Zalo: Message01Icon,
        Chat_Skype: Message01Icon,
        Internal: Building02Icon,
        API: FlashIcon,
        APP_MERCHANT: SmartPhone01Icon,
        APP_PAYOO: SmartPhone01Icon,
        UNIPORTAL: ComputerIcon,
        MMS: Message01Icon,
        WEB_HOTRO: GlobeIcon,
    };

    const Icon = sourceIcons[source ?? ''] ?? GlobeIcon;
    const display = sourceExtra ?? source ?? 'Unknown';

    return (
        <span className="inline-flex items-center gap-1.5 rounded-[3px] border border-[#E2E0D8] bg-white px-2.5 py-1 text-xs font-medium text-[#18181B]">
            <HugeiconsIcon icon={Icon} size={12} className="text-[#71717A]" />
            {display}
        </span>
    );
}

function DueDateCard({
    label,
    date,
    tone = 'neutral',
}: {
    label: string;
    date: string | null;
    tone?: 'safe' | 'warn' | 'danger' | 'neutral';
}) {
    if (!date) return null;

    const dotColors = {
        safe: 'bg-emerald-500',
        warn: 'bg-amber-400',
        danger: 'bg-red-500',
        neutral: 'bg-[#A1A1AA]',
    };

    const now = new Date();
    const due = new Date(date);
    const isPast = due < now;
    const computedTone = isPast ? 'danger' : tone;

    return (
        <div className="rounded-lg border border-[#E2E0D8] bg-white p-3 shadow-sm">
            <div className="mb-1 flex items-center gap-1.5">
                <span className={cn('h-1.5 w-1.5 rounded-full', dotColors[computedTone])} />
                <span className="text-[11px] font-medium text-[#71717A]">{label}</span>
            </div>
            <div className="text-[13px] text-[#18181B]">{formatDateTime(date)}</div>
            <div className="mt-0.5 text-[11px] text-[#A1A1AA]">{formatRelative(date)}</div>
        </div>
    );
}

function FlagBadge({ active, label, activeColor = 'bg-red-50 text-red-700 border-red-200' }: { active: boolean; label: string; activeColor?: string }) {
    if (!active) return null;
    return (
        <span className={cn('inline-flex items-center gap-1 rounded-[3px] border px-2 py-0.5 text-[11px] font-medium', activeColor)}>
            <span className="h-1 w-1 rounded-full bg-current" />
            {label}
        </span>
    );
}

function AttachmentItem({ attachment, onPreview }: { attachment: Attachment; onPreview?: () => void }) {
    const isImage = attachment.mime?.toLowerCase().startsWith('image/');
    const Icon = isImage ? Image01Icon : File01Icon;

    return (
        <div className="group flex items-center gap-2 rounded border border-[#E2E0D8] bg-white p-2 transition-colors hover:border-[#A1A1AA]">
            <HugeiconsIcon icon={Icon} size={16} className="shrink-0 text-[#A1A1AA]" />
            <div className="min-w-0 flex-1">
                <p className="truncate text-[12px] font-medium text-[#18181B]">{attachment.name ?? `File ${attachment.file_id}`}</p>
                <p className="text-[10px] text-[#A1A1AA]">
                    {attachment.mime ?? 'unknown'} {attachment.size ? `· ${formatBytes(attachment.size)}` : ''}
                </p>
            </div>
            <div className="flex shrink-0 items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                {onPreview && (
                    <button
                        type="button"
                        onClick={onPreview}
                        className="flex h-6 w-6 items-center justify-center rounded text-[#A1A1AA] hover:bg-[#F4F2EB] hover:text-[#18181B]"
                    >
                        <HugeiconsIcon icon={Image01Icon} size={13} />
                    </button>
                )}
                <a
                    href={attachment.download_url}
                    download
                    className="flex h-6 w-6 items-center justify-center rounded text-[#A1A1AA] hover:bg-[#F4F2EB] hover:text-[#18181B]"
                >
                    <HugeiconsIcon icon={Download01Icon} size={13} />
                </a>
            </div>
        </div>
    );
}

function TagChip({ tag, onRemove }: { tag: Tag; onRemove?: () => void }) {
    return (
        <div className="flex items-center gap-1 rounded border border-[#E2E0D8] bg-white px-2 py-1 text-xs text-[#18181B] shadow-sm">
            {tag.color && <span className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: tag.color }} />}
            {tag.name}
            {onRemove && (
                <button type="button" onClick={onRemove} className="ml-0.5 text-[#A1A1AA] hover:text-[#18181B]">
                    <HugeiconsIcon icon={Cancel01Icon} size={10} />
                </button>
            )}
        </div>
    );
}

function PriorityToggle({ priority, onChange }: { priority: string | null; onChange?: (p: string) => void }) {
    const options = [
        { value: 'Low', label: 'Low', dot: 'bg-emerald-500' },
        { value: 'Medium', label: 'Medium', dot: 'bg-amber-400' },
        { value: 'High', label: 'High', dot: 'bg-red-500' },
    ];

    return (
        <div className="flex items-center gap-1.5">
            {options.map((opt) => {
                const isActive = (priority ?? 'Medium') === opt.value;
                return (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => onChange?.(opt.value)}
                        className={cn(
                            'flex flex-1 items-center justify-center gap-1.5 rounded border px-2 py-1.5 text-xs font-medium transition-colors',
                            isActive
                                ? 'border-[#F97316] bg-orange-50 text-[#F97316]'
                                : 'border-[#E2E0D8] text-[#71717A] hover:bg-[#FAFAF8]'
                        )}
                    >
                        <span className={cn('h-1.5 w-1.5 rounded-full', opt.dot)} />
                        {opt.label}
                    </button>
                );
            })}
        </div>
    );
}

function TicketTypeSelector({ type, onChange }: { type: string | null; onChange?: (t: string) => void }) {
    const options = ['Incident', 'Request'];
    return (
        <div className="relative">
            <select
                value={type ?? 'Incident'}
                onChange={(e) => onChange?.(e.target.value)}
                className="w-full appearance-none rounded-md border border-[#E2E0D8] bg-white px-3 py-2 pr-8 text-sm text-[#18181B] outline-none transition-colors focus:border-[#F97316]"
            >
                {options.map((opt) => (
                    <option key={opt} value={opt}>{opt}</option>
                ))}
            </select>
            <HugeiconsIcon icon={ArrowDown01Icon} size={14} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#A1A1AA]" />
        </div>
    );
}

export function TicketInfoPanel({
    ticket,
    customFields,
    attachments,
    collaborators,
    referrals,
    tags = [],
    linkedProblems = [],
    onPreviewAttachment,
    onToggleCollapse,
    isCollapsed,
}: TicketInfoPanelProps) {
    const hasCustomFields = Object.keys(customFields).length > 0;
    const hasReferrals = referrals.length > 0;
    const hasTags = tags.length > 0;
    const hasLinkedProblems = linkedProblems.length > 0;

    return (
        <div className="flex h-full flex-col bg-white">
            <div className="border-b border-[#E2E0D8] bg-[#FAFAF8] px-4 py-4">
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <span className="font-mono text-[12px] font-semibold text-[#A1A1AA]">#{ticket.number}</span>
                        <StatusBadge status={ticket.status} state={ticket.status_state} />
                    </div>
                    {onToggleCollapse && (
                        <button
                            type="button"
                            onClick={onToggleCollapse}
                            className="flex h-7 w-7 items-center justify-center rounded text-[#A1A1AA] transition-colors hover:bg-[#E2E0D8] hover:text-[#18181B]"
                            title={isCollapsed ? 'Expand panel' : 'Collapse panel'}
                        >
                            <HugeiconsIcon icon={isCollapsed ? PinIcon : PinOffIcon} size={14} />
                        </button>
                    )}
                </div>
                <h2 className="mb-3 text-[15px] font-semibold leading-snug text-[#18181B]">
                    {ticket.subject ?? 'No Subject'}
                </h2>
                <div className="flex flex-wrap items-center gap-1.5">
                    <PriorityBadge priority={ticket.priority} />
                    {ticket.isoverdue && <FlagBadge active={ticket.isoverdue} label="Overdue" activeColor="bg-red-50 text-red-700 border-red-200" />}
                    {ticket.isanswered && <FlagBadge active={ticket.isanswered} label="Answered" activeColor="bg-emerald-50 text-emerald-700 border-emerald-200" />}
                </div>
            </div>

            <div className="custom-scrollbar flex-1 overflow-y-auto">
                <Section title="Requester" icon={UserIcon}>
                    <div className="rounded-md border border-[#E2E0D8] bg-white p-3">
                        <div className="flex items-center gap-2.5">
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#6366F1] text-[11px] font-semibold text-white">
                                {(ticket.requester ?? '?').slice(0, 2).toUpperCase()}
                            </div>
                            <div className="min-w-0">
                                <p className="truncate text-[13px] font-semibold text-[#18181B]">
                                    {ticket.requester ?? 'Unknown'}
                                </p>
                                {ticket.requester_email && (
                                    <p className="flex items-center gap-1 text-[11px] text-[#71717A]">
                                        <HugeiconsIcon icon={Mail01Icon} size={10} />
                                        {ticket.requester_email}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </Section>

                <Section title="Properties" icon={HashtagIcon}>
                    <dl className="space-y-3">
                        <div>
                            <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wide text-[#A1A1AA]">Ticket Type</label>
                            <TicketTypeSelector type="Incident" />
                        </div>
                        <div>
                            <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wide text-[#A1A1AA]">Priority</label>
                            <PriorityToggle priority={ticket.priority} />
                        </div>
                        {hasLinkedProblems && (
                            <div>
                                <label className="mb-1.5 block text-[11px] font-medium uppercase tracking-wide text-[#A1A1AA]">Linked Problem</label>
                                <div className="relative">
                                    <select className="w-full appearance-none rounded-md border border-[#E2E0D8] bg-white px-3 py-2 pr-8 text-sm text-[#71717A] outline-none transition-colors focus:border-[#F97316]">
                                        <option value="">Select problem</option>
                                        {linkedProblems.map((p) => (
                                            <option key={p.id} value={p.id}>{p.subject}</option>
                                        ))}
                                    </select>
                                    <HugeiconsIcon icon={ArrowDown01Icon} size={14} className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-[#A1A1AA]" />
                                </div>
                            </div>
                        )}
                    </dl>
                </Section>

                <Section title="Tags" icon={Tag01Icon} badge={hasTags ? tags.length : undefined} defaultOpen={hasTags}>
                    <div className="min-h-[80px] rounded-lg border border-[#E2E0D8] bg-[#FAFAF8]/50 p-2">
                        {hasTags ? (
                            <div className="flex flex-wrap gap-1.5">
                                {tags.map((tag) => (
                                    <TagChip key={tag.id} tag={tag} />
                                ))}
                            </div>
                        ) : (
                            <p className="py-4 text-center text-[12px] text-[#A1A1AA]">No tags assigned</p>
                        )}
                    </div>
                </Section>

                <Section title="Assignment" icon={Shield01Icon}>
                    <dl className="space-y-1">
                        <InfoRow
                            label="Assigned To"
                            value={ticket.assignee ?? 'Unassigned'}
                            icon={UserIcon}
                        />
                        <InfoRow
                            label="Department"
                            value={ticket.department ?? '—'}
                            icon={Building02Icon}
                        />
                        <InfoRow
                            label="Team"
                            value={ticket.team ?? '—'}
                            icon={UserGroupIcon}
                        />
                        <InfoRow
                            label="SLA Plan"
                            value={ticket.sla_id > 0 ? `SLA #${ticket.sla_id}` : '—'}
                            icon={Shield01Icon}
                        />
                    </dl>
                </Section>

                <Section title="Dates & Timeline" icon={Calendar01Icon}>
                    <div className="space-y-3">
                        <dl className="space-y-1">
                            <InfoRow
                                label="Created"
                                value={ticket.created ? formatDateTime(ticket.created) : '—'}
                                icon={Calendar01Icon}
                            />
                            <InfoRow
                                label="Last Updated"
                                value={ticket.lastupdate ? formatDateTime(ticket.lastupdate) : '—'}
                                icon={Clock01Icon}
                            />
                            {ticket.lastmessage && (
                                <InfoRow
                                    label="Last Message"
                                    value={formatDateTime(ticket.lastmessage)}
                                    icon={Message01Icon}
                                />
                            )}
                            {ticket.lastresponse && (
                                <InfoRow
                                    label="Last Response"
                                    value={formatDateTime(ticket.lastresponse)}
                                    icon={Mail01Icon}
                                />
                            )}
                            {ticket.reopened && (
                                <InfoRow
                                    label="Reopened"
                                    value={formatDateTime(ticket.reopened)}
                                    icon={FlashIcon}
                                />
                            )}
                            {ticket.closed && (
                                <InfoRow
                                    label="Closed"
                                    value={formatDateTime(ticket.closed)}
                                    icon={Cancel01Icon}
                                />
                            )}
                        </dl>

                        {(ticket.duedate || ticket.est_duedate) && (
                            <div className="space-y-2 pt-1">
                                {ticket.duedate && (
                                    <DueDateCard label="Resolution Due" date={ticket.duedate} tone="warn" />
                                )}
                                {ticket.est_duedate && (
                                    <DueDateCard label="Estimated Due" date={ticket.est_duedate} tone="neutral" />
                                )}
                            </div>
                        )}
                    </div>
                </Section>

                <Section title="Ticket Details" icon={InformationCircleIcon} defaultOpen={false}>
                    <dl className="space-y-1">
                        <InfoRow
                            label="Ticket ID"
                            value={`#${ticket.number}`}
                            icon={HashtagIcon}
                        />
                        <InfoRow
                            label="Source"
                            value={<SourceBadge source={ticket.source} sourceExtra={ticket.source_extra} />}
                            icon={GlobeIcon}
                        />
                        <InfoRow
                            label="IP Address"
                            value={ticket.ip_address || '—'}
                            icon={Wifi01Icon}
                        />
                        <InfoRow
                            label="Overdue"
                            value={ticket.isoverdue ? 'Yes' : 'No'}
                            icon={Alert01Icon}
                        />
                        <InfoRow
                            label="Answered"
                            value={ticket.isanswered ? 'Yes' : 'No'}
                            icon={CheckmarkCircle01Icon}
                        />
                    </dl>
                </Section>

                {hasCustomFields && (
                    <Section title="Custom Fields" icon={Tag01Icon} badge={Object.keys(customFields).length}>
                        <dl className="space-y-2">
                            {Object.entries(customFields).map(([key, val]) => (
                                <div key={key} className="rounded-md border border-[#E2E0D8] bg-white p-2.5">
                                    <dt className="text-[11px] font-medium uppercase tracking-wide text-[#A1A1AA]">
                                        {key}
                                    </dt>
                                    <dd className="mt-1 text-[13px] text-[#18181B]">
                                        {val === null || val === '' ? '—' : String(val)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </Section>
                )}

                {attachments.length > 0 && (
                    <Section title="Attachments" icon={FolderAttachmentIcon} badge={attachments.length}>
                        <div className="space-y-2">
                            {attachments.map((att) => (
                                <AttachmentItem
                                    key={att.id}
                                    attachment={att}
                                    onPreview={onPreviewAttachment ? () => onPreviewAttachment(att) : undefined}
                                />
                            ))}
                        </div>
                    </Section>
                )}

                {collaborators.length > 0 && (
                    <Section title="Collaborators" icon={UserGroupIcon} badge={collaborators.length}>
                        <div className="space-y-2">
                            {collaborators.map((collab) => (
                                <div key={collab.id} className="flex items-center gap-2 rounded-md border border-[#E2E0D8] bg-white p-2.5">
                                    <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EC4899] text-[10px] font-semibold text-white">
                                        {(collab.name ?? '?').slice(0, 2).toUpperCase()}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-[13px] font-medium text-[#18181B]">
                                            {collab.name ?? 'Unknown'}
                                        </p>
                                        {collab.email && (
                                            <p className="text-[11px] text-[#71717A]">{collab.email}</p>
                                        )}
                                    </div>
                                    {collab.role && (
                                        <span className="shrink-0 rounded-[3px] bg-[#F4F2EB] px-1.5 py-0.5 text-[10px] font-medium uppercase text-[#71717A]">
                                            {collab.role}
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>
                    </Section>
                )}

                {hasReferrals && (
                    <Section title="Referrals" icon={Link01Icon} badge={referrals.length}>
                        <div className="space-y-2">
                            {referrals.map((ref) => (
                                <div key={ref.id} className="flex items-center gap-2 rounded-md border border-[#E2E0D8] bg-white p-2.5">
                                    <HugeiconsIcon icon={Link01Icon} size={14} className="text-[#A1A1AA]" />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-[13px] text-[#18181B]">
                                            {ref.object_type} #{ref.object_id}
                                        </p>
                                        {ref.created && (
                                            <p className="text-[11px] text-[#71717A]">
                                                {formatDateTime(ref.created)}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Section>
                )}
            </div>
        </div>
    );
}

export default TicketInfoPanel;
