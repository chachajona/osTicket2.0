import { useState } from 'react';
import { router } from '@inertiajs/react';
import { HugeiconsIcon } from '@hugeicons/react';
import { Note01Icon } from '@hugeicons/core-free-icons';

import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Field, FieldError } from '@/components/ui/field';

interface NoteComposerProps {
    ticketId: number;
    expectedUpdated: string;
    onSuccess?: () => void;
}

export function NoteComposer({ ticketId, expectedUpdated, onSuccess }: NoteComposerProps) {
    const [noteBody, setNoteBody] = useState('');
    const [selectedFormat, setSelectedFormat] = useState('html');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = () => {
        setIsSubmitting(true);
        router.post(`/scp/tickets/${ticketId}/notes`, {
            body: noteBody,
            format: selectedFormat,
            expected_updated: expectedUpdated,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNoteBody('');
                setErrors({});
                onSuccess?.();
            },
            onError: (newErrors) => {
                setErrors(newErrors);
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    return (
        <div className="rounded-[18px] border border-[#E2E0D8] bg-white p-6 shadow-sm shadow-[#18181B]/[0.03]">
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <HugeiconsIcon icon={Note01Icon} size={16} className="text-[#A1A1AA]" />
                    <h3 className="font-display text-base font-medium text-[#18181B]">Post Internal Note</h3>
                </div>

                <Select value={selectedFormat} onValueChange={(val) => val && setSelectedFormat(val)}>
                    <SelectTrigger className="h-8 w-[120px] bg-white text-xs">
                        <SelectValue placeholder="Format" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="html">HTML</SelectItem>
                        <SelectItem value="text">Plain Text</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div className="space-y-4">
                <Field>
                    <Textarea
                        placeholder="Write an internal note..."
                        value={noteBody}
                        onChange={(e) => setNoteBody(e.target.value)}
                        className="min-h-[120px] resize-y bg-white"
                    />
                    {errors.body && <FieldError>{errors.body}</FieldError>}
                </Field>

                <div className="flex justify-end">
                    <Button
                        onClick={handleSubmit}
                        disabled={isSubmitting || !noteBody.trim()}
                        className="h-8 rounded-[3px] uppercase tracking-[1.2px]"
                    >
                        {isSubmitting ? 'Posting...' : 'Post Note'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
