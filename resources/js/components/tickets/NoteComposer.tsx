import { useState } from 'react';
import { router } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import {
    TextBoldIcon,
    SmileIcon,
    Attachment01Icon,
    Mic01Icon as Microphone01Icon,
    ArrowDown01Icon,
    RefreshIcon
} from '@hugeicons/core-free-icons';
import { ChannelPill, FromPill, IconBtn } from './TicketDetailComponents';

interface NoteComposerProps {
    ticketId: number;
    expectedUpdated: string;
    onSuccess?: () => void;
}

export function NoteComposer({ ticketId, expectedUpdated, onSuccess }: NoteComposerProps) {
    const [noteBody, setNoteBody] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    // Hardcoded user initials/name for now as per design template,
    // ideally should come from auth props
    const authorInitials = 'FIK';

    const handleSubmit = () => {
        if (!noteBody.trim()) return;
        setIsSubmitting(true);
        router.post(`/scp/tickets/${ticketId}/notes`, {
            body: noteBody,
            format: 'text', // defaulting to text based on the simple input in design
            expected_updated: expectedUpdated,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNoteBody('');
                onSuccess?.();
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    return (
        <div className="shrink-0 border-t border-[#E2E0D8] bg-white px-8 py-4">
            <div className="rounded-lg border border-[#E2E0D8] bg-white p-4 shadow-[0_-2px_8px_rgba(0,0,0,0.03)]">
                {/* Via / From row */}
                <div className="mb-2.5 flex items-center gap-2.5">
                    <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#FB923C] via-[#EC4899] to-[#6366F1] text-[10px] font-semibold text-white">
                        {authorInitials}
                    </div>
                    <span className="text-xs text-[#71717A]">Via</span>
                    <ChannelPill channel="Portal" />
                    <span className="text-xs text-[#71717A]">From</span>
                    <FromPill from="Internal Note" />
                    <div className="ml-auto flex items-center">
                        <IconBtn icon={RefreshIcon} size={28} className="border-none shadow-none" />
                    </div>
                </div>

                {/* Text input */}
                <input
                    value={noteBody}
                    onChange={e => setNoteBody(e.target.value)}
                    onKeyDown={e => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            handleSubmit();
                        }
                    }}
                    placeholder="Comment or Type '/' For commands (Internal Note)"
                    className="w-full bg-transparent py-2 font-sans text-sm text-[#18181B] outline-none placeholder:text-[#A1A1AA]"
                />

                {/* Toolbar */}
                <div className="mt-2 flex items-center justify-between border-t border-[#E2E0D8] pt-2">
                    <div className="flex items-center gap-0.5">
                        {[TextBoldIcon, SmileIcon, Attachment01Icon, Microphone01Icon].map((ic, i) => (
                            <button
                                key={i}
                                type="button"
                                className="flex h-[30px] w-[30px] items-center justify-center rounded transition-colors text-[#A1A1AA] hover:bg-[#F4F2EB] hover:text-[#18181B]"
                            >
                                <HugeiconsIcon icon={ic} size={16} />
                            </button>
                        ))}
                        <div className="mx-1.5 h-4 w-px bg-[#E2E0D8]" />
                        <button type="button" className="flex items-center gap-1 px-2.5 py-1 font-sans text-xs font-medium text-[#A1A1AA] hover:text-[#18181B]">
                            Macros
                            <HugeiconsIcon icon={ArrowDown01Icon} size={12} />
                        </button>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <button type="button" className="font-sans text-[13px] font-medium text-[#A1A1AA] hover:text-[#18181B]">
                            End Chat
                        </button>
                        <button
                            type="button"
                            onClick={handleSubmit}
                            disabled={isSubmitting || !noteBody.trim()}
                            className="rounded-sm bg-[#71717A] px-6 py-2 font-sans text-[13px] font-medium text-white transition-colors hover:bg-[#52525B] disabled:opacity-50"
                        >
                            {isSubmitting ? 'Sending...' : 'Send'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
