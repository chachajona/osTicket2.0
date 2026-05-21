<?php

declare(strict_types=1);

namespace App\Services\Scp\Mail;

use App\Models\ThreadEntry;
use Illuminate\Support\Facades\DB;

final class EmailInfoPersister
{
    public function record(ThreadEntry $entry, string $messageId, string $headers, ?int $emailId = null): void
    {
        DB::connection('legacy')->table('thread_entry_email')->insert([
            'thread_entry_id' => $entry->id,
            'email_id' => $emailId,
            'mid' => $messageId,
            'headers' => $headers,
        ]);
    }
}
