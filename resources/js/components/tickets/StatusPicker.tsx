import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { StatusBadge } from '@/components/scp/StatusBadge';
import { HugeiconsIcon } from '@hugeicons/react';
import { ArrowDown01Icon } from '@hugeicons/core-free-icons';

export interface StatusOption {
    id: number;
    name: string;
    state: string;
}

export interface StatusPickerProps {
    ticketId: number;
    expectedUpdated?: string | null;
    currentStatus: string | null;
    currentStatusState: string | null;
    availableStatuses: StatusOption[];
    onSuccess?: () => void;
}

export function StatusPicker({
    ticketId,
    expectedUpdated,
    currentStatus,
    currentStatusState,
    availableStatuses,
    onSuccess,
}: StatusPickerProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [selectedStatusId, setSelectedStatusId] = useState<number | null>(null);
    const [comment, setComment] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const validTransitions = availableStatuses.filter((s) => {
        if (s.name === currentStatus) return false;
        return s.state.toLowerCase() === 'open' || s.state.toLowerCase() === 'onhold';
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedStatusId) return;

        setIsSubmitting(true);
        setErrors({});

        router.post(`/scp/tickets/${ticketId}/status`, {
            status_id: selectedStatusId,
            comments: comment,
            expected_updated: expectedUpdated || null,
        }, {
            onSuccess: () => {
                onSuccess?.();
                setIsOpen(false);
                setComment('');
                setSelectedStatusId(null);
            },
            onError: (errs) => {
                setErrors(errs);
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger
                className="group flex items-center gap-1.5 rounded-full ring-offset-white transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#6366F1] focus-visible:ring-offset-2"
            >
                <StatusBadge status={currentStatus} state={currentStatusState} />
                <span className="flex h-[22px] items-center gap-1 rounded-full border border-[#E2E0D8] bg-white px-2.5 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#71717A] opacity-0 transition-all group-hover:opacity-100 group-focus-visible:opacity-100">
                    Change
                    <HugeiconsIcon icon={ArrowDown01Icon} size={10} className="text-[#A1A1AA]" />
                </span>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-[320px] rounded-[18px] border-[#E2E0D8] bg-white p-5 shadow-sm shadow-[#18181B]/[0.03]">
                <form onSubmit={handleSubmit} className="flex flex-col gap-5">
                    <div className="space-y-3">
                        <label className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#A1A1AA]">
                            New Status
                        </label>
                        <Select
                            value={selectedStatusId?.toString() ?? ''}
                            onValueChange={(val) => setSelectedStatusId(Number(val))}
                        >
                            <SelectTrigger className="w-full h-10 rounded-md border-[#E2E0D8] bg-[#FAFAF8] px-3">
                                <SelectValue placeholder="Select a status" />
                            </SelectTrigger>
                            <SelectContent>
                                {validTransitions.length === 0 && (
                                    <div className="py-2 px-3 text-xs text-[#71717A]">
                                        No valid status transitions available.
                                    </div>
                                )}
                                {validTransitions.map((status) => (
                                    <SelectItem key={status.id} value={status.id.toString()}>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={status.name} state={status.state} />
                                        </div>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.status_id && <p className="text-[11px] font-medium text-red-500">{errors.status_id}</p>}
                    </div>

                    {selectedStatusId && (
                        <div className="space-y-3 fade-in animate-in">
                            <label className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#A1A1AA]">
                                Optional Comment
                            </label>
                            <Textarea
                                value={comment}
                                onChange={(e) => setComment(e.target.value)}
                                placeholder="Add a note about this status change..."
                                className="min-h-[80px] text-sm bg-[#FAFAF8] border-[#E2E0D8]"
                            />
                            {errors.comments && <p className="text-[11px] font-medium text-red-500">{errors.comments}</p>}
                        </div>
                    )}

                    {errors.message && (
                        <div className="rounded-md border border-red-200 bg-red-50 p-3">
                            <p className="text-xs font-medium text-red-700">{errors.message}</p>
                        </div>
                    )}

                    <div className="flex items-center justify-end gap-3 pt-2">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setIsOpen(false)}
                            disabled={isSubmitting}
                            className="text-[#71717A] hover:text-[#18181B] hover:bg-[#F4F2EB]"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={!selectedStatusId || isSubmitting}
                            className="bg-[#18181B] text-white hover:bg-[#27272A]"
                        >
                            Change Status
                        </Button>
                    </div>
                </form>
            </PopoverContent>
        </Popover>
    );
}
