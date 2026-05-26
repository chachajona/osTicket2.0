<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Tickets;

use App\Mail\CloseNotifyMail;
use App\Models\LegacyPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\LegacyMailFixtures;
use Tests\TestCase;

final class CloseNotifyTest extends TestCase
{
    use LegacyMailFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['osticket.ticket_lock' => '0']);
        Mail::fake();
        $this->ensureLegacyMailTables();
        $this->seedMailTemplates();
        DB::connection('legacy')->table('event')->insertOrIgnore([
            ['id' => 7, 'name' => 'created', 'description' => 'Created'],
            ['id' => 200, 'name' => 'status', 'description' => 'Status'],
        ]);
        DB::connection('legacy')->table('ticket_status')->insertOrIgnore([
            ['id' => 1, 'name' => 'Open', 'state' => 'open'],
            ['id' => 2, 'name' => 'Closed', 'state' => 'closed'],
            ['id' => 3, 'name' => 'On Hold', 'state' => 'onhold'],
        ]);
        LegacyPermission::create(['name' => 'tickets.set-status', 'guard_name' => 'staff']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_notify_user_true_with_comments_queues_close_notify_mail(): void
    {
        config(['mail.event_class_owner.close_notify' => 'laravel']);
        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.set-status');

        $this->actingAs($fixture['staff'], 'staff')
            ->post(route('scp.tickets.status.store', $fixture['ticket']), [
                'status_id' => 2,
                'comments' => 'Closing the ticket.',
                'notify_user' => true,
                'expected_updated' => (string) $fixture['ticket']->updated,
            ])
            ->assertRedirect();

        Mail::assertQueued(CloseNotifyMail::class, 1);
    }

    public function test_notify_user_true_with_empty_comments_returns_422(): void
    {
        config(['mail.event_class_owner.close_notify' => 'laravel']);
        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.set-status');

        $this->actingAs($fixture['staff'], 'staff')
            ->postJson(route('scp.tickets.status.store', $fixture['ticket']), [
                'status_id' => 2,
                'comments' => '',
                'notify_user' => true,
                'expected_updated' => (string) $fixture['ticket']->updated,
            ])
            ->assertStatus(422);

        Mail::assertNothingQueued();
    }

    public function test_notify_user_true_with_non_closed_status_returns_422(): void
    {
        config(['mail.event_class_owner.close_notify' => 'laravel']);
        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.set-status');

        $this->actingAs($fixture['staff'], 'staff')
            ->postJson(route('scp.tickets.status.store', $fixture['ticket']), [
                'status_id' => 3,
                'comments' => 'Pausing',
                'notify_user' => true,
                'expected_updated' => (string) $fixture['ticket']->updated,
            ])
            ->assertStatus(422);
    }

    public function test_notify_user_false_does_not_queue_mail(): void
    {
        config(['mail.event_class_owner.close_notify' => 'laravel']);
        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.set-status');

        $this->actingAs($fixture['staff'], 'staff')
            ->post(route('scp.tickets.status.store', $fixture['ticket']), [
                'status_id' => 2,
                'comments' => 'Quietly closing.',
                'notify_user' => false,
                'expected_updated' => (string) $fixture['ticket']->updated,
            ])
            ->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_notify_user_true_with_legacy_ownership_returns_403(): void
    {
        config(['mail.event_class_owner.close_notify' => 'legacy']);
        $fixture = $this->seedMailTicket();
        $fixture['staff']->givePermissionTo('tickets.set-status');

        $this->actingAs($fixture['staff'], 'staff')
            ->post(route('scp.tickets.status.store', $fixture['ticket']), [
                'status_id' => 2,
                'comments' => 'Closing',
                'notify_user' => true,
                'expected_updated' => (string) $fixture['ticket']->updated,
            ])
            ->assertForbidden();
    }
}
