import { useState } from 'react';
import { router } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { UserGroupIcon, Cancel01Icon } from '@hugeicons/core-free-icons';

import { cn } from '@/lib/utils';
import {
    Dialog,
    DialogTrigger,
    DialogPortal,
    DialogOverlay,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogClose,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';

export interface StaffOption {
    id: number;
    name: string;
}

export interface TeamOption {
    id: number;
    name: string;
}

export interface AssignmentDialogProps {
    ticketId: number;
    expectedUpdated?: string;
    currentAssignee?: string | null;
    staffOptions: StaffOption[];
    teamOptions: TeamOption[];
    onSuccess?: () => void;
}

export function AssignmentDialog({
    ticketId,
    expectedUpdated,
    currentAssignee,
    staffOptions,
    teamOptions,
    onSuccess,
}: AssignmentDialogProps) {
    const [open, setOpen] = useState(false);
    const [activeTab, setActiveTab] = useState<'staff' | 'team'>('staff');

    const [selectedStaffId, setSelectedStaffId] = useState<string>('');
    const [selectedTeamId, setSelectedTeamId] = useState<string>('');
    const [comment, setComment] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSuccess = () => {
        setOpen(false);
        resetForm();
        onSuccess?.();
    };

    const handleFinish = () => {
        setIsSubmitting(false);
    };

    const resetForm = () => {
        setSelectedStaffId('');
        setSelectedTeamId('');
        setComment('');
    };

    const assignToStaff = () => {
        if (!selectedStaffId) return;
        setIsSubmitting(true);
        router.post(
            `/scp/tickets/${ticketId}/assignment`,
            {
                assignee_type: 'staff',
                assignee_id: parseInt(selectedStaffId, 10),
                comments: comment,
                expected_updated: expectedUpdated,
            },
            {
                onSuccess: handleSuccess,
                onFinish: handleFinish,
            }
        );
    };

    const assignToTeam = () => {
        if (!selectedTeamId) return;
        setIsSubmitting(true);
        router.post(
            `/scp/tickets/${ticketId}/assignment`,
            {
                assignee_type: 'team',
                assignee_id: parseInt(selectedTeamId, 10),
                comments: comment,
                expected_updated: expectedUpdated,
            },
            {
                onSuccess: handleSuccess,
                onFinish: handleFinish,
            }
        );
    };

    const unassign = () => {
        setIsSubmitting(true);
        router.delete(`/scp/tickets/${ticketId}/assignment`, {
            data: { expected_updated: expectedUpdated },
            onSuccess: handleSuccess,
            onFinish: handleFinish,
        });
    };

    return (
        <Dialog open={open} onOpenChange={(val) => {
            setOpen(val);
            if (!val) resetForm();
        }}>
            <DialogTrigger
                render={
                    <button
                        type="button"
                        className="inline-flex items-center gap-1.5 rounded-full border border-[#E2E0D8] bg-white px-2.5 py-1 text-xs font-medium text-[#18181B] transition-colors hover:border-[#18181B] hover:bg-[#FAFAF8]"
                    >
                        {!currentAssignee && <HugeiconsIcon icon={UserGroupIcon} size={12} className="text-[#A1A1AA]" />}
                        {currentAssignee ? currentAssignee : 'Assign'}
                    </button>
                }
            />
            <DialogPortal>
                <DialogOverlay className="bg-[#18181B]/70" />
                <DialogContent className="max-w-md rounded-2xl border-white/10 bg-white p-0 shadow-2xl overflow-hidden">
                    <div className="flex items-center justify-between border-b border-[#E2E0D8] px-5 py-4">
                        <DialogTitle className="font-display text-lg font-medium text-[#18181B]">
                            Assign Ticket
                        </DialogTitle>
                        <DialogClose className="grid h-8 w-8 place-items-center rounded-md text-[#71717A] transition-colors hover:bg-[#F4F2EB] hover:text-[#18181B]">
                            <HugeiconsIcon icon={Cancel01Icon} size={18} />
                            <span className="sr-only">Close</span>
                        </DialogClose>
                    </div>

                    <Tabs value={activeTab} onValueChange={(val: any) => setActiveTab(val)} className="flex flex-col">
                        <div className="px-5 pt-4">
                            <TabsList className="grid w-full grid-cols-2 rounded-lg bg-[#F4F2EB] p-1">
                                <TabsTrigger value="staff" className="rounded-md px-3 py-1.5 text-xs font-medium transition-all data-selected:bg-white data-selected:text-[#18181B] data-selected:shadow-sm text-[#71717A]">
                                    Staff
                                </TabsTrigger>
                                <TabsTrigger value="team" className="rounded-md px-3 py-1.5 text-xs font-medium transition-all data-selected:bg-white data-selected:text-[#18181B] data-selected:shadow-sm text-[#71717A]">
                                    Team
                                </TabsTrigger>
                            </TabsList>
                        </div>

                        <div className="p-5">
                            <TabsContent value="staff" className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#A1A1AA]">Staff Member</Label>
                                    <Select value={selectedStaffId} onValueChange={(val) => val && setSelectedStaffId(val)}>
                                        <SelectTrigger className="w-full rounded-md border border-[#E2E0D8] bg-white h-9">
                                            <SelectValue placeholder="Select staff member" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {staffOptions.map(staff => (
                                                <SelectItem key={staff.id} value={staff.id.toString()}>
                                                    {staff.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#A1A1AA]">Optional Comment</Label>
                                    <Textarea
                                        placeholder="Add an internal note..."
                                        className="min-h-[80px] resize-none rounded-md border border-[#E2E0D8] bg-white text-sm"
                                        value={comment}
                                        onChange={(e) => setComment(e.target.value)}
                                    />
                                </div>
                                <div className="flex items-center justify-between pt-2">
                                    {currentAssignee ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={unassign}
                                            disabled={isSubmitting}
                                            className="uppercase tracking-[1.2px] rounded-[3px] text-xs h-8 text-red-600 hover:bg-red-50 hover:text-red-700 border-red-200"
                                        >
                                            Unassign
                                        </Button>
                                    ) : <div />}
                                    <Button
                                        type="button"
                                        onClick={assignToStaff}
                                        disabled={!selectedStaffId || isSubmitting}
                                        className="uppercase tracking-[1.2px] rounded-[3px] text-xs h-8 bg-[#18181B] text-white hover:bg-[#27272A]"
                                    >
                                        Assign
                                    </Button>
                                </div>
                            </TabsContent>

                            <TabsContent value="team" className="space-y-4">
                                <div className="space-y-1.5">
                                    <Label className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#A1A1AA]">Team</Label>
                                    <Select value={selectedTeamId} onValueChange={(val) => val && setSelectedTeamId(val)}>
                                        <SelectTrigger className="w-full rounded-md border border-[#E2E0D8] bg-white h-9">
                                            <SelectValue placeholder="Select team" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {teamOptions.map(team => (
                                                <SelectItem key={team.id} value={team.id.toString()}>
                                                    {team.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-[10px] font-semibold uppercase tracking-[0.14em] text-[#A1A1AA]">Optional Comment</Label>
                                    <Textarea
                                        placeholder="Add an internal note..."
                                        className="min-h-[80px] resize-none rounded-md border border-[#E2E0D8] bg-white text-sm"
                                        value={comment}
                                        onChange={(e) => setComment(e.target.value)}
                                    />
                                </div>
                                <div className="flex items-center justify-between pt-2">
                                    {currentAssignee ? (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={unassign}
                                            disabled={isSubmitting}
                                            className="uppercase tracking-[1.2px] rounded-[3px] text-xs h-8 text-red-600 hover:bg-red-50 hover:text-red-700 border-red-200"
                                        >
                                            Unassign
                                        </Button>
                                    ) : <div />}
                                    <Button
                                        type="button"
                                        onClick={assignToTeam}
                                        disabled={!selectedTeamId || isSubmitting}
                                        className="uppercase tracking-[1.2px] rounded-[3px] text-xs h-8 bg-[#18181B] text-white hover:bg-[#27272A]"
                                    >
                                        Assign
                                    </Button>
                                </div>
                            </TabsContent>
                        </div>
                    </Tabs>
                </DialogContent>
            </DialogPortal>
        </Dialog>
    );
}
