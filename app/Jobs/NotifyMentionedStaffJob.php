<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\MentionNotificationMail;
use App\Models\Staff;
use App\Models\ThreadEntry;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

final class NotifyMentionedStaffJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly ThreadEntry $entry,
        public readonly Staff $mentioner,
        public readonly int $mentionedStaffId,
    ) {}

    public function handle(): void
    {
        $mentioned = Staff::where('staff_id', $this->mentionedStaffId)
            ->where('isactive', 1)
            ->first();

        if ($mentioned === null) {
            return;
        }

        Mail::queue(new MentionNotificationMail(
            ticket: $this->ticket,
            entry: $this->entry,
            mentioner: $this->mentioner,
            mentioned: $mentioned,
        ));
    }
}
