import { useMemo, useState, useRef, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    Mail01Icon,
    Note01Icon,
    ArrowDown01Icon,
    ArrowRight01Icon,
    MailSend01Icon,
    HourglassIcon,
    PauseCircleIcon,
    CheckmarkCircle01Icon,
    LockKeyIcon,
    MinusSignCircleIcon,
    CheckmarkCircle02Icon,
    CancelCircleIcon,
    FlashIcon,
    SignatureIcon,
    UserIdVerificationIcon,
    Building02Icon,
    TextBoldIcon,
    TextItalicIcon,
    TextUnderlineIcon,
    LeftToRightListBulletIcon,
    LeftToRightListNumberIcon,
    Link01Icon,
    CodeSquareIcon,
    SmileIcon,
    Attachment01Icon,
    Image01Icon,
    MagicWand03Icon,
    AddCircleIcon,
    Chat01Icon,
    ViewOffIcon,
    FloppyDiskIcon,
    InformationCircleIcon,
    Mail01Icon as MailIcon,
    SmartPhone01Icon,
    ComputerIcon,
    Tick02Icon,
    Group01Icon,
} from '@hugeicons/core-free-icons';
import { type StatusOption } from './StatusPicker';
import { RichTextEditor, type RichTextEditorHandle } from './RichTextEditor';
import { cn } from '@/lib/utils';
import { useAutoSave } from '@/hooks/useAutoSave';

type Mode = 'reply' | 'note';
type SignatureChoice = 'none' | 'mine' | 'dept';

interface PageAuth {
    staff?: {
        defaultSignatureType?: SignatureChoice;
        hasSignature?: boolean;
    } | null;
}

function useStaffAuth(): PageAuth['staff'] {
    const { props } = usePage<{ auth?: PageAuth }>();
    return props.auth?.staff ?? null;
}

function initialsFromName(name: string | null | undefined): string {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function channelFromSource(source: string | null | undefined): { label: string; icon: any; color?: string } {
    const s = (source ?? '').toLowerCase();
    if (s.includes('email')) return { label: 'Email', icon: MailIcon, color: '#A1A1AA' };
    if (s.includes('phone')) return { label: 'Phone', icon: SmartPhone01Icon, color: '#A1A1AA' };
    if (s.includes('web') || s.includes('portal') || s.includes('api')) return { label: 'Portal', icon: ComputerIcon, color: '#A1A1AA' };
    return { label: source || 'Unknown', icon: Chat01Icon, color: '#A1A1AA' };
}

function statusVisuals(state: string | null | undefined): { dot: string; bg: string; ring: string; icon: any } {
    const s = (state ?? '').toLowerCase();
    switch (s) {
        case 'open':
        case 'answered':
            return { dot: 'bg-[#F97316]', bg: 'bg-[#FFF4EC]', ring: 'border-[#FDD2B4]', icon: MailSend01Icon };
        case 'pending':
            return { dot: 'bg-[#CA8A04]', bg: 'bg-[#FFFBEB]', ring: 'border-[#FCD34D]', icon: HourglassIcon };
        case 'onhold':
            return { dot: 'bg-[#EC4899]', bg: 'bg-[#FDF0F7]', ring: 'border-[#F9C4DE]', icon: PauseCircleIcon };
        case 'resolved':
            return { dot: 'bg-[#16A34A]', bg: 'bg-[#F0FDF4]', ring: 'border-[#BBF7D0]', icon: CheckmarkCircle01Icon };
        case 'closed':
        case 'archived':
            return { dot: 'bg-[#A1A1AA]', bg: 'bg-[#FAFAF8]', ring: 'border-[#E2E0D8]', icon: LockKeyIcon };
        default:
            return { dot: 'bg-[#A1A1AA]', bg: 'bg-[#FAFAF8]', ring: 'border-[#E2E0D8]', icon: MailSend01Icon };
    }
}

function sendLabelForStatus(name: string | undefined, state: string | null | undefined): string {
    const s = (state ?? '').toLowerCase();
    switch (s) {
        case 'open':
        case 'answered':
            return 'Send';
        case 'pending':
            return 'Send & Pending';
        case 'onhold':
            return 'Send & Hold';
        case 'resolved':
            return 'Send & Resolve';
        case 'closed':
        case 'archived':
            return 'Send & Close';
        default:
            return `Send & ${name ?? 'Update'}`;
    }
}

function bgColorForStatusState(state: string | null | undefined): string {
    const s = (state ?? '').toLowerCase();
    switch (s) {
        case 'open':
        case 'answered':
            return 'bg-[#F97316]';
        case 'pending':
            return 'bg-[#CA8A04]';
        case 'onhold':
            return 'bg-[#EC4899]';
        case 'resolved':
            return 'bg-[#16A34A]';
        case 'closed':
        case 'archived':
            return 'bg-[#A1A1AA]';
        default:
            return 'bg-[#18181B]';
    }
}

function CIconBtn({
    icon,
    label,
    onClick,
    active,
}: {
    icon: any;
    label: string;
    onClick?: () => void;
    active?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            title={label}
            className={cn(
                'inline-flex h-7 w-7 items-center justify-center rounded-sm transition-all duration-150',
                active
                    ? 'bg-[#FAFAF8] text-[#18181B]'
                    : 'bg-transparent text-[#A1A1AA] hover:bg-[#FAFAF8] hover:text-[#18181B]'
            )}
        >
            <HugeiconsIcon icon={icon} size={15} />
        </button>
    );
}

function Divider() {
    return <span className="mx-1 h-4 w-px bg-[#E2E0D8]" />;
}

function ReplyTabs({ mode, onChange }: { mode: Mode; onChange: (m: Mode) => void }) {
    const tabs = [
        { id: 'reply' as Mode, label: 'Reply', icon: Mail01Icon },
        { id: 'note' as Mode, label: 'Internal note', icon: Note01Icon },
    ];
    return (
        <div className="flex items-center gap-0">
            {tabs.map((t) => {
                const on = mode === t.id;
                return (
                    <button
                        key={t.id}
                        type="button"
                        onClick={() => onChange(t.id)}
                        className={cn(
                            'inline-flex items-center gap-1.5 border-b-2 bg-transparent px-2.5 py-1.5 font-sans text-[10px] font-medium uppercase tracking-[0.1em] transition-all duration-150',
                            on
                                ? 'border-[#18181B] text-[#18181B]'
                                : 'border-transparent text-[#A1A1AA] hover:text-[#18181B]'
                        )}
                    >
                        <HugeiconsIcon icon={t.icon} size={13} />
                        {t.label}
                    </button>
                );
            })}
        </div>
    );
}

function InlineStatusPicker({
    value,
    onChange,
    notify,
    onNotifyChange,
    options,
}: {
    value: string | null;
    onChange: (id: string | null) => void;
    notify: boolean;
    onNotifyChange: (v: boolean) => void;
    options: StatusOption[];
}) {
    const [open, setOpen] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        function onDown(e: MouseEvent) {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
        }
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    const selected = value ? options.find((s) => String(s.id) === value) : null;
    const selectedVisuals = selected ? statusVisuals(selected.state) : null;

    return (
        <div ref={wrapRef} className="relative inline-flex">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className={cn(
                    'inline-flex items-center gap-2 whitespace-nowrap rounded-sm border px-3 py-2 font-sans text-xs font-medium transition-all duration-150',
                    selectedVisuals ? selectedVisuals.bg : 'bg-white',
                    selectedVisuals ? selectedVisuals.ring : 'border-[#E2E0D8]'
                )}
            >
                <span className="inline-flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                    <span
                        className={cn('h-1.5 w-1.5 rounded-full', selectedVisuals ? selectedVisuals.dot : 'bg-[#A1A1AA]')}
                    />
                    Set to
                </span>
                <span className="text-[13px] font-medium">{selected ? selected.name : 'No change'}</span>
                <HugeiconsIcon
                    icon={ArrowDown01Icon}
                    size={12}
                    className={cn('text-[#A1A1AA] transition-transform duration-150', open && 'rotate-180')}
                />
            </button>

            {open && (
                <div
                    className={cn(
                        'absolute bottom-[calc(100%+8px)] right-0 z-20 w-80 rounded-lg border border-[#E2E0D8] bg-white p-1.5 shadow-[0_18px_40px_-12px_rgba(24,24,27,0.18),0_4px_12px_-4px_rgba(24,24,27,0.06)]'
                    )}
                >
                    <div className="flex items-center justify-between px-3 py-2">
                        <span className="text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                            Status on send
                        </span>
                        <span className="font-mono text-[10px] font-medium text-[#A1A1AA]">{options.length}</span>
                    </div>

                    <button
                        type="button"
                        onClick={() => {
                            onChange(null);
                            setOpen(false);
                        }}
                        className={cn(
                            'flex w-full items-start gap-2.5 rounded-md px-2.5 py-2 text-left transition-colors duration-100',
                            value === null ? 'bg-[#FAFAF8]' : 'bg-transparent hover:bg-[#FAFAF8]'
                        )}
                    >
                        <span className="inline-flex h-4 w-5 items-center justify-center pt-0.5">
                            <HugeiconsIcon icon={MinusSignCircleIcon} size={16} className="text-[#A1A1AA]" />
                        </span>
                        <span className="flex-1">
                            <div className="text-[13px] font-medium">No change</div>
                            <div className="mt-px text-[11px] text-[#A1A1AA]">Send the reply, leave status as-is.</div>
                        </span>
                        {value === null && (
                            <HugeiconsIcon icon={CheckmarkCircle02Icon} size={14} className="text-[#F97316]" />
                        )}
                    </button>

                    <div className="mx-1.5 my-1 h-px bg-[#E2E0D8]" />

                    {options.map((s) => {
                        const on = value === String(s.id);
                        const visuals = statusVisuals(s.state);
                        return (
                            <button
                                key={s.id}
                                type="button"
                                onClick={() => {
                                    onChange(String(s.id));
                                    setOpen(false);
                                }}
                                className={cn(
                                    'flex w-full items-start gap-2.5 rounded-md px-2.5 py-2 text-left transition-colors duration-100',
                                    on ? 'bg-[#FAFAF8]' : 'bg-transparent hover:bg-[#FAFAF8]'
                                )}
                            >
                                <span
                                    className={cn(
                                        'inline-flex h-5.5 w-5.5 shrink-0 items-center justify-center rounded-sm border',
                                        visuals.bg,
                                        visuals.ring
                                    )}
                                >
                                    <span className={cn('h-1.5 w-1.5 rounded-full', visuals.dot)} />
                                </span>
                                <span className="flex-1">
                                    <div className="text-[13px] font-medium text-[#18181B]">{s.name}</div>
                                </span>
                                {on && <HugeiconsIcon icon={CheckmarkCircle02Icon} size={14} className={visuals.dot} />}
                            </button>
                        );
                    })}

                    <div className="mx-1 mb-0.5 mt-1.5 flex items-center justify-between gap-3 border-t border-[#E2E0D8] px-2 pt-2.5">
                        <label className="inline-flex cursor-pointer items-center gap-2 text-xs text-[#18181B]">
                            <button
                                type="button"
                                onClick={() => onNotifyChange(!notify)}
                                className={cn(
                                    'relative h-4 w-7 shrink-0 rounded-full transition-colors duration-150',
                                    notify ? 'bg-[#F97316]' : 'bg-[#EDEDED]'
                                )}
                            >
                                <span
                                    className={cn(
                                        'absolute top-0.5 h-3 w-3 rounded-full bg-white shadow transition-all duration-150',
                                        notify ? 'left-3.5' : 'left-0.5'
                                    )}
                                />
                            </button>
                            <span className="text-xs font-medium">Notify customer by email</span>
                        </label>
                    </div>
                </div>
            )}
        </div>
    );
}

function SendButton({
    statusState,
    statusName,
    onSend,
    label,
    disabled,
}: {
    statusState: string | null | undefined;
    statusName: string | undefined;
    onSend: () => void;
    label?: string;
    disabled?: boolean;
}) {
    const btnLabel = label ?? sendLabelForStatus(statusName, statusState);
    const bgColor = bgColorForStatusState(statusState);

    return (
        <div className="inline-flex overflow-hidden rounded-sm shadow-[0_2px_4px_-2px_rgba(24,24,27,0.12)] transition-shadow duration-150 hover:shadow-[0_4px_12px_-2px_rgba(24,24,27,0.18)]">
            <button
                type="button"
                onClick={onSend}
                disabled={disabled}
                className={cn(
                    'inline-flex items-center gap-2 whitespace-nowrap px-4 py-2 font-sans text-xs font-medium uppercase tracking-[1.2px] text-white transition-all duration-150 hover:brightness-95 disabled:opacity-50',
                    bgColor
                )}
            >
                {btnLabel}
                <HugeiconsIcon icon={ArrowRight01Icon} size={14} />
            </button>
            <span className="w-px bg-white/20" />
            <button
                type="button"
                aria-label="More send options"
                className={cn(
                    'inline-flex items-center px-2.5 text-white transition-all duration-150 hover:brightness-95',
                    bgColor
                )}
            >
                <HugeiconsIcon icon={ArrowDown01Icon} size={12} />
            </button>
        </div>
    );
}

function AttachmentChip({ name, size, onRemove }: { name: string; size: string; onRemove: () => void }) {
    const ext = (name.split('.').pop() || '').toUpperCase().slice(0, 3);
    return (
        <span className="inline-flex items-center gap-2 rounded-sm border border-[#E2E0D8] bg-white px-2 py-1 text-xs text-[#18181B]">
            <span className="inline-flex h-5.5 w-5.5 shrink-0 items-center justify-center rounded-[3px] border border-[#E2E0D8] bg-[#FAFAF8] font-mono text-[8px] font-semibold text-[#A1A1AA]">
                {ext}
            </span>
            <span className="font-medium">{name}</span>
            <span className="text-[11px] text-[#A1A1AA]">{size}</span>
            <button
                type="button"
                onClick={onRemove}
                aria-label="Remove attachment"
                className="inline-flex h-5 w-5 items-center justify-center rounded-sm bg-transparent text-[#A1A1AA] transition-colors hover:text-[#18181B]"
            >
                <HugeiconsIcon icon={CancelCircleIcon} size={14} />
            </button>
        </span>
    );
}


function SignatureSelect({
    value,
    onChange,
    hasMySignature,
    hasDeptSignature,
    deptLabel = 'Department',
}: {
    value: SignatureChoice;
    onChange: (v: SignatureChoice) => void;
    hasMySignature: boolean;
    hasDeptSignature: boolean;
    deptLabel?: string;
}) {
    const [open, setOpen] = useState(false);
    const wrapRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;
        const onDown = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [open]);

    const options = [
        { id: 'none' as SignatureChoice, label: 'No signature', desc: "Don't append a signature.", icon: MinusSignCircleIcon, available: true },
        { id: 'mine' as SignatureChoice, label: 'My signature', desc: 'From your staff profile.', icon: UserIdVerificationIcon, available: hasMySignature },
        { id: 'dept' as SignatureChoice, label: `${deptLabel} signature`, desc: 'From the assigned department.', icon: Building02Icon, available: hasDeptSignature },
    ];
    const current = options.find((o) => o.id === value && o.available) || options[0];

    return (
        <div ref={wrapRef} className="relative inline-flex">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                className="inline-flex items-center gap-2 whitespace-nowrap rounded-sm border border-[#E2E0D8] bg-white px-2.5 py-1.5 font-sans text-xs font-medium transition-all duration-150 hover:border-[#A1A1AA]"
            >
                <span className="inline-flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                    <HugeiconsIcon icon={SignatureIcon} size={12} className="text-[#A1A1AA]" />
                    Signature
                </span>
                <span className="text-xs font-medium">{current.label}</span>
                <HugeiconsIcon
                    icon={ArrowDown01Icon}
                    size={11}
                    className={cn('text-[#A1A1AA] transition-transform duration-150', open && 'rotate-180')}
                />
            </button>

            {open && (
                <div className="absolute bottom-[calc(100%+8px)] left-0 z-20 w-72 rounded-lg border border-[#E2E0D8] bg-white p-1.5 shadow-[0_18px_40px_-12px_rgba(24,24,27,0.18),0_4px_12px_-4px_rgba(24,24,27,0.06)]">
                    <div className="px-3 py-2 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                        Append signature
                    </div>

                    {options.map((o) => {
                        const on = current.id === o.id;
                        const disabled = !o.available;
                        return (
                            <button
                                key={o.id}
                                type="button"
                                disabled={disabled}
                                onClick={() => {
                                    if (!disabled) {
                                        onChange(o.id);
                                        setOpen(false);
                                    }
                                }}
                                className={cn(
                                    'flex w-full items-start gap-2.5 rounded-md px-2.5 py-2 text-left transition-colors duration-100',
                                    on ? 'bg-[#FAFAF8]' : 'bg-transparent hover:bg-[#FAFAF8]',
                                    disabled ? 'cursor-not-allowed opacity-45' : 'cursor-pointer'
                                )}
                            >
                                <span className="inline-flex h-4 w-5 items-center justify-center pt-0.5">
                                    <HugeiconsIcon icon={o.icon} size={14} className="text-[#A1A1AA]" />
                                </span>
                                <span className="flex-1">
                                    <div className="flex items-center gap-1.5 text-[13px] font-medium text-[#18181B]">
                                        {o.label}
                                        {disabled && (
                                            <span className="rounded px-1 py-px text-[9px] font-medium uppercase tracking-[0.08em] bg-[#FAFAF8] text-[#A1A1AA]">
                                                Not set
                                            </span>
                                        )}
                                    </div>
                                    <div className="mt-px text-[11px] text-[#A1A1AA]">{o.desc}</div>
                                </span>
                                {on && <HugeiconsIcon icon={CheckmarkCircle02Icon} size={14} className="text-[#F97316]" />}
                            </button>
                        );
                    })}

                    <div className="mx-1 mb-0.5 mt-1 flex items-center gap-1.5 border-t border-[#E2E0D8] px-2 pt-2 text-[11px] text-[#A1A1AA]">
                        <HugeiconsIcon icon={InformationCircleIcon} size={12} />
                        Default from staff prefs ·{' '}
                        <a href="#" className="font-medium text-[#18181B] hover:underline">
                            change
                        </a>
                    </div>
                </div>
            )}
        </div>
    );
}

function resolvePref(
    pref: SignatureChoice | undefined,
    hasMySignature: boolean,
    hasDeptSignature: boolean
): SignatureChoice {
    if (pref === 'mine' && hasMySignature) return 'mine';
    if (pref === 'dept' && hasDeptSignature) return 'dept';
    return 'none';
}

export interface Collaborator {
    id: number;
    name: string;
    email?: string;
    isActive?: boolean;
    isCc?: boolean;
}

export interface ReplyComposerProps {
    ticketId: number;
    expectedUpdated: string;
    statusOptions: StatusOption[];
    requester?: string | null;
    requesterEmail?: string | null;
    source?: string | null;
    sourceExtra?: string | null;
    deptLabel?: string;
    deptSignatureAvailable?: boolean;
    collaborators?: Collaborator[];
    ticketDeptId?: number;
    onSuccess?: () => void;
}

export function ReplyComposer({
    ticketId,
    expectedUpdated,
    statusOptions,
    requester,
    requesterEmail,
    source,
    sourceExtra,
    deptLabel = 'Department',
    deptSignatureAvailable = false,
    collaborators = [],
    ticketDeptId,
    onSuccess,
}: ReplyComposerProps) {
    const staffAuth = useStaffAuth();
    const hasMySignature = staffAuth?.hasSignature ?? false;
    const hasDeptSignature = deptSignatureAvailable;
    const defaultSignatureType = staffAuth?.defaultSignatureType ?? 'none';

    const [mode, setMode] = useState<Mode>('reply');
    const [statusId, setStatusId] = useState<string | null>(null);
    const [notify, setNotify] = useState(true);
    const [body, setBody] = useState('');
    const [attachments, setAttachments] = useState<{ name: string; size: string }[]>([]);
    const [cannedResponses, setCannedResponses] = useState<{ id: number; title: string; response: string }[]>([]);
    const [isFetchingCanned, setIsFetchingCanned] = useState(false);
    const [expanded, setExpanded] = useState(false);
    const [sigPref, setSigPref] = useState<SignatureChoice>(() =>
        resolvePref(defaultSignatureType, hasMySignature, hasDeptSignature)
    );
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [ccOpen, setCcOpen] = useState(false);
    const [selectedCcIds, setSelectedCcIds] = useState<Set<number>>(() => {
        const initial = new Set<number>();
        collaborators.filter((c) => c.isActive).forEach((c) => initial.add(c.id));
        return initial;
    });
    const [macroOpen, setMacroOpen] = useState(false);
    const [emojiOpen, setEmojiOpen] = useState(false);
    const [saveDraftState, setSaveDraftState] = useState<'idle' | 'saving' | 'saved'>('idle');
    const [sendOptionsOpen, setSendOptionsOpen] = useState(false);

    const editorRef = useRef<RichTextEditorHandle>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const imageInputRef = useRef<HTMLInputElement>(null);
    const ccWrapRef = useRef<HTMLDivElement>(null);
    const macroWrapRef = useRef<HTMLDivElement>(null);
    const emojiWrapRef = useRef<HTMLDivElement>(null);
    const sendOptionsRef = useRef<HTMLDivElement>(null);

    const isNote = mode === 'note';

    const { forceSave, deleteDraft, loadDraft } = useAutoSave({
        body,
        ticketId,
        type: isNote ? 'note' : 'reply',
        onStateChange: setSaveDraftState,
    });

    useEffect(() => {
        loadDraft().then((saved) => {
            if (saved && !body) {
                editorRef.current?.getEditor()?.commands.setContent(saved);
                setBody(saved);
                setSaveDraftState('saved');
                setTimeout(() => setSaveDraftState('idle'), 2000);
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const selectedStatus = statusId ? statusOptions.find((s) => String(s.id) === statusId) : null;

    useEffect(() => {
        if (isNote && statusId) setStatusId(null);
    }, [isNote]);

    const channel = useMemo(() => channelFromSource(source), [source]);
    const recipientInitials = useMemo(() => initialsFromName(requester), [requester]);

    const extractMentionIds = (): number[] => {
        const doc = editorRef.current?.getEditor()?.getJSON();
        if (!doc) return [];
        const ids: number[] = [];
        const traverse = (nodes: any[]): void => {
            for (const node of nodes ?? []) {
                if (node.type === 'mention' && node.attrs?.id) {
                    ids.push(Number(node.attrs.id));
                }
                if (node.content) traverse(node.content);
            }
        };
        traverse(doc.content ?? []);
        return [...new Set(ids)];
    };

    const handleSend = () => {
        if (!body.trim()) return;

        setIsSubmitting(true);

        if (isNote) {
            router.post(
                `/scp/tickets/${ticketId}/notes`,
                {
                    body,
                    format: 'html',
                    expected_updated: expectedUpdated,
                    mentioned_staff_ids: extractMentionIds(),
                },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        void deleteDraft();
                        editorRef.current?.clearContent();
                        setBody('');
                        setCannedResponses([]);
                        setAttachments([]);
                        onSuccess?.();
                    },
                    onFinish: () => setIsSubmitting(false),
                }
            );
        } else {
            const ccIds = Array.from(selectedCcIds);
            const payload: Record<string, any> = {
                body,
                format: 'html',
                signature: sigPref,
                reply_status_id: statusId ? Number(statusId) : null,
                expected_updated: expectedUpdated,
                mentioned_staff_ids: extractMentionIds(),
            };
            if (ccIds.length > 0) payload.ccs = ccIds;

            router.post(
                `/scp/tickets/${ticketId}/replies`,
                payload,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        void deleteDraft();
                        editorRef.current?.clearContent();
                        setBody('');
                        setStatusId(null);
                        setCannedResponses([]);
                        setAttachments([]);
                        onSuccess?.();
                    },
                    onFinish: () => setIsSubmitting(false),
                }
            );
        }
    };

    const toggleCc = (id: number) => {
        setSelectedCcIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    const formatText = (action: string) => {
        const editor = editorRef.current?.getEditor();
        if (!editor) return;

        switch (action) {
            case 'bold':
                editor.chain().focus().toggleBold().run();
                break;
            case 'italic':
                editor.chain().focus().toggleItalic().run();
                break;
            case 'underline':
                editor.chain().focus().toggleUnderline().run();
                break;
            case 'bullet':
                editor.chain().focus().toggleBulletList().run();
                break;
            case 'number':
                editor.chain().focus().toggleOrderedList().run();
                break;
            case 'link': {
                const url = window.prompt('Enter URL:');
                if (url) {
                    editor.chain().focus().setLink({ href: url, target: '_blank' }).run();
                }
                break;
            }
            case 'code':
                editor.chain().focus().toggleCode().run();
                break;
        }
    };

    const insertEmoji = (emoji: string) => {
        editorRef.current?.insertContent(emoji);
        setEmojiOpen(false);
    };

    const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = e.target.files;
        if (!files) return;
        const newAttachments = Array.from(files).map((f) => ({
            name: f.name,
            size: f.size < 1024 * 1024 ? `${(f.size / 1024).toFixed(1)} KB` : `${(f.size / (1024 * 1024)).toFixed(1)} MB`,
        }));
        setAttachments((prev) => [...prev, ...newAttachments]);
        e.target.value = '';
    };

    const applyMacro = (item: { id: number; title: string; response: string }) => {
        editorRef.current?.insertContent(item.response);
        setMacroOpen(false);
    };

    useEffect(() => {
        if (!macroOpen) return;
        setIsFetchingCanned(true);
        const params = new URLSearchParams();
        if (ticketDeptId) params.set('dept_id', String(ticketDeptId));
        fetch(`/scp/canned-responses?${params.toString()}`)
            .then((r) => r.json())
            .then((data: { id: number; title: string; response: string }[]) => setCannedResponses(data))
            .catch(() => setCannedResponses([]))
            .finally(() => setIsFetchingCanned(false));
    }, [macroOpen, ticketDeptId]);

    useEffect(() => {
        if (!ccOpen) return;
        const onDown = (e: MouseEvent) => {
            if (ccWrapRef.current && !ccWrapRef.current.contains(e.target as Node)) setCcOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [ccOpen]);

    useEffect(() => {
        if (!macroOpen) return;
        const onDown = (e: MouseEvent) => {
            if (macroWrapRef.current && !macroWrapRef.current.contains(e.target as Node)) setMacroOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [macroOpen]);

    useEffect(() => {
        if (!emojiOpen) return;
        const onDown = (e: MouseEvent) => {
            if (emojiWrapRef.current && !emojiWrapRef.current.contains(e.target as Node)) setEmojiOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [emojiOpen]);

    useEffect(() => {
        if (!sendOptionsOpen) return;
        const onDown = (e: MouseEvent) => {
            if (sendOptionsRef.current && !sendOptionsRef.current.contains(e.target as Node)) setSendOptionsOpen(false);
        };
        document.addEventListener('mousedown', onDown);
        return () => document.removeEventListener('mousedown', onDown);
    }, [sendOptionsOpen]);

    const activeCcCount = selectedCcIds.size;

    return (
        <div
            className={cn(
                'shrink-0 overflow-visible transition-all duration-200',
                isNote ? 'border-t border-[#FCD34D] bg-[rgba(254,252,232,0.5)]' : 'border-t border-[#E2E0D8] bg-white'
            )}
        >
            <div
                className={cn(
                    'mx-8 my-4 rounded-lg border shadow-[0_-2px_8px_rgba(0,0,0,0.03)]',
                    isNote ? 'border-[#FCD34D]' : 'border-[#E2E0D8]'
                )}
            >
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-[#E2E0D8] px-3.5 pt-1.5">
                    <ReplyTabs mode={mode} onChange={setMode} />
                    {!isNote && (
                        <div className="flex flex-wrap items-center gap-2.5 pb-2">
                            <span className="text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">To</span>
                            {requester ? (
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-[#E2E0D8] bg-white px-2 py-0.5 text-xs font-medium text-[#18181B]">
                                    <span className="flex h-4.5 w-4.5 shrink-0 items-center justify-center rounded-full bg-[#0EA5E9] text-[8px] font-semibold text-white">
                                        {recipientInitials}
                                    </span>
                                    {requester}
                                    {requesterEmail && (
                                        <span className="font-normal text-[#A1A1AA]">· {requesterEmail}</span>
                                    )}
                                </span>
                            ) : (
                                <span className="text-xs text-[#A1A1AA]">No requester</span>
                            )}
                            <div ref={ccWrapRef} className="relative inline-flex">
                                <button
                                    type="button"
                                    onClick={() => setCcOpen((o) => !o)}
                                    className={cn(
                                        'inline-flex items-center gap-1 rounded-full border border-dashed border-[#E2E0D8] bg-transparent px-1.5 py-0.5 text-[11px] font-medium transition-colors hover:text-[#18181B]',
                                        activeCcCount > 0 ? 'text-[#18181B]' : 'text-[#A1A1AA]'
                                    )}
                                >
                                    <HugeiconsIcon icon={activeCcCount > 0 ? Group01Icon : AddCircleIcon} size={12} />
                                    {activeCcCount > 0 ? `Cc (${activeCcCount})` : 'Cc'}
                                </button>
                                {ccOpen && (
                                    <div className="absolute bottom-[calc(100%+6px)] left-0 z-20 w-72 rounded-lg border border-[#E2E0D8] bg-white p-1.5 shadow-[0_18px_40px_-12px_rgba(24,24,27,0.18),0_4px_12px_-4px_rgba(24,24,27,0.06)]">
                                        <div className="px-3 py-2 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                                            Collaborators
                                        </div>
                                        {collaborators.length === 0 && (
                                            <div className="px-3 py-4 text-center text-[13px] text-[#A1A1AA]">
                                                No collaborators on this ticket.
                                            </div>
                                        )}
                                        {collaborators.map((c) => {
                                            const on = selectedCcIds.has(c.id);
                                            return (
                                                <button
                                                    key={c.id}
                                                    type="button"
                                                    onClick={() => toggleCc(c.id)}
                                                    className={cn(
                                                        'flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left transition-colors duration-100',
                                                        on ? 'bg-[#FAFAF8]' : 'bg-transparent hover:bg-[#FAFAF8]'
                                                    )}
                                                >
                                                    <span className="flex h-5.5 w-5.5 shrink-0 items-center justify-center rounded-full bg-[#E2E0D8] text-[9px] font-semibold text-white">
                                                        {initialsFromName(c.name)}
                                                    </span>
                                                    <span className="flex-1">
                                                        <div className="text-[13px] font-medium text-[#18181B]">{c.name}</div>
                                                        {c.email && (
                                                            <div className="text-[11px] text-[#A1A1AA]">{c.email}</div>
                                                        )}
                                                    </span>
                                                    {on && (
                                                        <HugeiconsIcon icon={Tick02Icon} size={14} className="text-[#F97316]" />
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                            </div>
                            <span className="text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">Via</span>
                            <span className="inline-flex cursor-default items-center gap-1 rounded-full border border-[#E2E0D8] bg-white px-2 py-0.5 text-xs font-medium text-[#18181B]">
                                <HugeiconsIcon icon={channel.icon} size={11} className={channel.color ? `text-[${channel.color}]` : 'text-[#A1A1AA]'} />
                                {channel.label}
                                {sourceExtra && (
                                    <span className="text-[10px] text-[#A1A1AA]">· {sourceExtra}</span>
                                )}
                            </span>
                        </div>
                    )}
                    {isNote && (
                        <div className="flex items-center gap-1.5 pb-2 text-[11px] font-medium text-[#92400E]">
                            <HugeiconsIcon icon={ViewOffIcon} size={13} />
                            Visible to your team only · customer won&apos;t see this
                        </div>
                    )}
                </div>

                <div className="px-4 pb-2 pt-3.5">
                    <div className="mb-2 flex flex-wrap items-center gap-0.5 border-b border-dashed border-[#E2E0D8] pb-2">
                        <CIconBtn icon={TextBoldIcon} label="Bold" onClick={() => formatText('bold')} />
                        <CIconBtn icon={TextItalicIcon} label="Italic" onClick={() => formatText('italic')} />
                        <CIconBtn icon={TextUnderlineIcon} label="Underline" onClick={() => formatText('underline')} />
                        <Divider />
                        <CIconBtn icon={LeftToRightListBulletIcon} label="Bulleted list" onClick={() => formatText('bullet')} />
                        <CIconBtn icon={LeftToRightListNumberIcon} label="Numbered list" onClick={() => formatText('number')} />
                        <CIconBtn icon={Link01Icon} label="Insert link" onClick={() => formatText('link')} />
                        <CIconBtn icon={CodeSquareIcon} label="Code" onClick={() => formatText('code')} />
                        <Divider />
                        <div ref={emojiWrapRef} className="relative inline-flex">
                            <CIconBtn icon={SmileIcon} label="Emoji" onClick={() => setEmojiOpen((o) => !o)} active={emojiOpen} />
                            {emojiOpen && (
                                <div className="absolute bottom-[calc(100%+6px)] left-0 z-20 grid w-56 grid-cols-8 gap-1 rounded-lg border border-[#E2E0D8] bg-white p-2 shadow-[0_18px_40px_-12px_rgba(24,24,27,0.18),0_4px_12px_-4px_rgba(24,24,27,0.06)]">
                                    {['😀','😂','😍','🤔','😢','😡','👍','👎','🎉','🔥','❤️','👏','🙏','💡','⚠️','✅','❌','📝','📎','🔗','📧','📞','🏷️','⭐','🕐','🚀','🐛','🔒'].map((e) => (
                                        <button
                                            key={e}
                                            type="button"
                                            onClick={() => insertEmoji(e)}
                                            className="flex h-7 w-7 items-center justify-center rounded-sm text-lg hover:bg-[#FAFAF8]"
                                        >
                                            {e}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <CIconBtn icon={Attachment01Icon} label="Attach file" onClick={() => fileInputRef.current?.click()} />
                        <CIconBtn icon={Image01Icon} label="Insert image" onClick={() => imageInputRef.current?.click()} />
                        <input
                            ref={fileInputRef}
                            type="file"
                            multiple
                            className="hidden"
                            onChange={handleFileSelect}
                        />
                        <input
                            ref={imageInputRef}
                            type="file"
                            accept="image/*"
                            multiple
                            className="hidden"
                            onChange={handleFileSelect}
                        />
                        <div className="flex-1" />
                        <div ref={macroWrapRef} className="relative inline-flex">
                            <button
                                type="button"
                                onClick={() => setMacroOpen((o) => !o)}
                                className="inline-flex h-7 items-center gap-1 rounded-sm border-none bg-transparent px-2.5 font-sans text-xs font-medium text-[#18181B] transition-colors hover:bg-[#FAFAF8]"
                            >
                                <HugeiconsIcon icon={FlashIcon} size={13} />
                                Macros
                                <HugeiconsIcon icon={ArrowDown01Icon} size={10} className="text-[#A1A1AA]" />
                            </button>
                            {macroOpen && (
                                <div className="absolute bottom-[calc(100%+6px)] right-0 z-20 w-64 rounded-lg border border-[#E2E0D8] bg-white p-1.5 shadow-[0_18px_40px_-12px_rgba(24,24,27,0.18),0_4px_12px_-4px_rgba(24,24,27,0.06)]">
                                    <div className="px-3 py-2 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                                        Canned responses
                                    </div>
                                    {isFetchingCanned && (
                                        <div className="px-3 py-2 text-[13px] text-[#A1A1AA]">Loading…</div>
                                    )}
                                    {!isFetchingCanned && cannedResponses.length === 0 && (
                                        <div className="px-3 py-2 text-[13px] text-[#A1A1AA]">No canned responses found.</div>
                                    )}
                                    {cannedResponses.map((item) => (
                                        <button
                                            key={item.id}
                                            type="button"
                                            onClick={() => applyMacro(item)}
                                            className="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-[13px] text-[#18181B] hover:bg-[#FAFAF8]"
                                        >
                                            <HugeiconsIcon icon={FlashIcon} size={12} className="text-[#A1A1AA]" />
                                            {item.title}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                const prompt = window.prompt('Describe what you want the AI to draft:');
                                if (prompt) {
                                    editorRef.current?.insertContent(`\n\n[AI Draft: ${prompt}]\n`);
                                }
                            }}
                            className="ml-1 inline-flex h-7 items-center gap-1 rounded-sm border-none bg-transparent px-2.5 font-sans text-xs font-medium text-[#18181B] transition-colors hover:bg-[#FAFAF8]"
                        >
                            <HugeiconsIcon icon={MagicWand03Icon} size={13} className="text-[#EC4899]" />
                            AI Draft
                        </button>
                    </div>

                    {attachments.length > 0 && (
                        <div className="mb-2.5 flex flex-wrap gap-1.5">
                            {attachments.map((a, i) => (
                                <AttachmentChip
                                    key={i}
                                    name={a.name}
                                    size={a.size}
                                    onRemove={() => setAttachments((prev) => prev.filter((_, idx) => idx !== i))}
                                />
                            ))}
                        </div>
                    )}

                    <div className={expanded ? 'min-h-[120px]' : 'min-h-[44px]'}>
                        <RichTextEditor
                            ref={editorRef}
                            value={body}
                            onChange={setBody}
                            placeholder={isNote ? 'Type an internal note…' : 'Type your reply… use "/" for canned responses, "@" to mention.'}
                            onFocus={() => setExpanded(true)}
                            ticketDeptId={ticketDeptId}
                        />
                    </div>

                    {expanded && !isNote && (
                        <div className="mt-2.5 flex flex-wrap items-start gap-3 border-t border-dashed border-[#E2E0D8] pt-2.5">
                            <SignatureSelect
                                value={sigPref}
                                onChange={setSigPref}
                                hasMySignature={hasMySignature}
                                hasDeptSignature={hasDeptSignature}
                                deptLabel={deptLabel}
                            />
                        </div>
                    )}
                </div>

                <div
                    className={cn(
                        'flex flex-wrap items-center justify-between gap-3 rounded-b-lg border-t border-[#E2E0D8] px-3.5 py-2.5',
                        isNote ? 'bg-[rgba(254,243,199,0.45)]' : 'bg-[#FAFAF8]'
                    )}
                >
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="inline-flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-[0.1em] text-[#A1A1AA]">
                            <span
                                className={cn(
                                    'h-1.5 w-1.5 rounded-full',
                                    body.length > 0 ? 'bg-[#16A34A]' : 'bg-[#A1A1AA]'
                                )}
                            />
                            {body.length > 0 ? `Draft · ${body.length} chars` : 'Empty draft'}
                        </span>
                        <button
                            type="button"
                            onClick={() => void forceSave()}
                            disabled={saveDraftState === 'saving' || !body.trim()}
                            className="inline-flex items-center gap-1 bg-transparent p-0 font-sans text-xs font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-[#A1A1AA] hover:text-[#18181B]"
                        >
                            <HugeiconsIcon icon={FloppyDiskIcon} size={12} />
                            {saveDraftState === 'saving' ? 'Saving…' : saveDraftState === 'saved' ? 'Saved' : 'Save as draft'}
                        </button>
                    </div>
                    <div className="inline-flex items-center gap-2.5">
                        {!isNote && (
                            <InlineStatusPicker
                                value={statusId}
                                onChange={setStatusId}
                                notify={notify}
                                onNotifyChange={setNotify}
                                options={statusOptions}
                            />
                        )}
                        <div ref={sendOptionsRef} className="relative inline-flex">
                            <SendButton
                                statusState={selectedStatus?.state}
                                statusName={selectedStatus?.name}
                                onSend={handleSend}
                                disabled={isSubmitting || !body.trim()}
                                label={isSubmitting ? 'Sending…' : undefined}
                            />
                            {sendOptionsOpen && (
                                <div className="absolute bottom-[calc(100%+6px)] right-0 z-20 w-48 rounded-lg border border-[#E2E0D8] bg-white p-1 shadow-[0_18px_40px_-12px_rgba(24,24,27,0.18),0_4px_12px_-4px_rgba(24,24,27,0.06)]">
                                    <button
                                        type="button"
                                        onClick={() => {
                                            handleSend();
                                            setSendOptionsOpen(false);
                                        }}
                                        className="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-[13px] text-[#18181B] hover:bg-[#FAFAF8]"
                                    >
                                        <HugeiconsIcon icon={MailSend01Icon} size={14} className="text-[#A1A1AA]" />
                                        Send now
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            void forceSave();
                                            setSendOptionsOpen(false);
                                        }}
                                        className="flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-left text-[13px] text-[#18181B] hover:bg-[#FAFAF8]"
                                    >
                                        <HugeiconsIcon icon={FloppyDiskIcon} size={14} className="text-[#A1A1AA]" />
                                        Save as draft
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
