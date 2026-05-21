<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Mail;

use App\Services\Scp\Mail\EmailInfoPersister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class EmailInfoPersisterTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureLegacyMailTables();
    }

    public function test_records_message_id_for_thread_entry(): void
    {
        $fixture = $this->seedMailTicket();

        app(EmailInfoPersister::class)
            ->record($fixture['entry'], '<test@example.com>', 'Message-ID: <test@example.com>', emailId: 5);

        $this->assertDatabaseHas('thread_entry_email', [
            'thread_entry_id' => $fixture['entry']->id,
            'email_id' => 5,
            'mid' => '<test@example.com>',
        ], 'legacy');
    }

    public function test_email_id_is_nullable(): void
    {
        $fixture = $this->seedMailTicket();

        app(EmailInfoPersister::class)->record($fixture['entry'], '<test@example.com>', 'headers');

        $this->assertNull(DB::connection('legacy')->table('thread_entry_email')->first()->email_id);
    }
}
