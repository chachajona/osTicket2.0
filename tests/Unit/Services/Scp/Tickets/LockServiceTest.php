<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Exceptions\TicketLockedException;
use App\Models\Lock;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\Scp\ScpActionLog;
use App\Services\Scp\Tickets\ActionLogger;
use App\Services\Scp\Tickets\LegacyConfigReader;
use App\Services\Scp\Tickets\LockService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class LockServiceTest extends TestCase
{
    use RefreshDatabase;

    private LockService $service;

    private string $legacyDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacyDatabasePath = sys_get_temp_dir().'/lock-service-legacy.sqlite';
        touch($this->legacyDatabasePath);

        config()->set('database.connections.legacy.database', $this->legacyDatabasePath);
        config()->set('database.connections.osticket2.database', $this->legacyDatabasePath);
        DB::purge('legacy');
        DB::purge('osticket2');

        $this->ensureLegacyTables();
        $this->resetTables();

        $this->service = new LockService(new LegacyConfigReader(), new ActionLogger());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        DB::purge('legacy');
        DB::purge('osticket2');

        if (isset($this->legacyDatabasePath) && file_exists($this->legacyDatabasePath)) {
            unlink($this->legacyDatabasePath);
        }

        parent::tearDown();
    }

    public function test_acquire_creates_row_when_none_exists(): void
    {
        Carbon::setTestNow('2026-05-03 10:00:00');

        $staff = $this->createStaff();
        $ticket = $this->createTicket();
        $this->setLockConfig(mode: '1', minutes: 5);

        $lock = $this->service->acquire($staff, $ticket);

        $this->assertInstanceOf(Lock::class, $lock);
        $this->assertSame('T', $lock->object_type);
        $this->assertSame($ticket->ticket_id, $lock->object_id);
        $this->assertSame($staff->staff_id, $lock->staff_id);
        $this->assertSame('2026-05-03 10:05:00', Carbon::parse($lock->expire)->toDateTimeString());
        $this->assertDatabaseHas('lock', [
            'lock_id' => $lock->lock_id,
            'object_type' => 'T',
            'object_id' => $ticket->ticket_id,
            'staff_id' => $staff->staff_id,
            'expire' => '2026-05-03 10:05:00',
        ], 'legacy');
    }

    public function test_acquire_returns_existing_lock_when_held_by_caller(): void
    {
        Carbon::setTestNow('2026-05-03 10:00:00');

        $staff = $this->createStaff();
        $ticket = $this->createTicket();
        $this->setLockConfig(mode: '1', minutes: 5);
        $existing = $this->createLock($ticket, $staff, '2026-05-03 10:01:00');

        Carbon::setTestNow('2026-05-03 10:02:00');

        $lock = $this->service->acquire($staff, $ticket);

        $this->assertSame($existing->lock_id, $lock->lock_id);
        $this->assertSame('2026-05-03 10:07:00', Carbon::parse($lock->expire)->toDateTimeString());
        $this->assertDatabaseHas('lock', [
            'lock_id' => $existing->lock_id,
            'staff_id' => $staff->staff_id,
            'expire' => '2026-05-03 10:07:00',
        ], 'legacy');
    }

    public function test_acquire_throws_when_held_by_other_and_not_expired(): void
    {
        Carbon::setTestNow('2026-05-03 10:00:00');

        $caller = $this->createStaff();
        $owner = $this->createStaff();
        $ticket = $this->createTicket();
        $this->setLockConfig(mode: '1', minutes: 5);
        $this->createLock($ticket, $owner, '2026-05-03 10:05:00');

        $this->expectException(TicketLockedException::class);

        try {
            $this->service->acquire($caller, $ticket);
        } catch (TicketLockedException $exception) {
            $this->assertSame($ticket->ticket_id, $exception->ticketId);
            $this->assertSame($owner->staff_id, $exception->heldByStaffId);
            $this->assertSame('2026-05-03 10:05:00', $exception->expiresAt);

            throw $exception;
        }
    }

    public function test_acquire_steals_when_existing_lock_expired(): void
    {
        Carbon::setTestNow('2026-05-03 10:10:00');

        $caller = $this->createStaff();
        $owner = $this->createStaff();
        $ticket = $this->createTicket();
        $this->setLockConfig(mode: '1', minutes: 5);
        $existing = $this->createLock($ticket, $owner, '2026-05-03 10:09:00');

        $lock = $this->service->acquire($caller, $ticket);

        $this->assertSame($existing->lock_id, $lock->lock_id);
        $this->assertSame($caller->staff_id, $lock->staff_id);
        $this->assertSame('2026-05-03 10:15:00', Carbon::parse($lock->expire)->toDateTimeString());
        $this->assertDatabaseHas('lock', [
            'lock_id' => $existing->lock_id,
            'staff_id' => $caller->staff_id,
            'expire' => '2026-05-03 10:15:00',
        ], 'legacy');
        $this->assertDatabaseHas('scp_action_log', [
            'staff_id' => $caller->staff_id,
            'ticket_id' => $ticket->ticket_id,
            'action' => 'lock.stolen',
            'outcome' => 'success',
            'http_status' => 200,
            'lock_id' => (string) $existing->lock_id,
        ]);

        $audit = ScpActionLog::query()->where('action', 'lock.stolen')->firstOrFail();
        $this->assertSame(['staff_id' => $owner->staff_id, 'expire' => '2026-05-03 10:09:00'], $audit->before_state);
        $this->assertSame(['staff_id' => $caller->staff_id, 'expire' => '2026-05-03 10:15:00'], $audit->after_state);
    }

    public function test_renew_extends_expiry_for_owner(): void
    {
        Carbon::setTestNow('2026-05-03 10:00:00');

        $staff = $this->createStaff();
        $ticket = $this->createTicket();
        $this->setLockConfig(mode: '1', minutes: 3);
        $lock = $this->createLock($ticket, $staff, '2026-05-03 10:04:00');

        Carbon::setTestNow('2026-05-03 10:02:00');

        $renewed = $this->service->renew($staff, $ticket);

        $this->assertSame($lock->lock_id, $renewed->lock_id);
        $this->assertSame('2026-05-03 10:05:00', Carbon::parse($renewed->expire)->toDateTimeString());
    }

    public function test_renew_throws_for_non_owner(): void
    {
        Carbon::setTestNow('2026-05-03 10:00:00');

        $caller = $this->createStaff();
        $owner = $this->createStaff();
        $ticket = $this->createTicket();
        $this->setLockConfig(mode: '1', minutes: 5);
        $this->createLock($ticket, $owner, '2026-05-03 10:05:00');

        $this->expectException(TicketLockedException::class);

        $this->service->renew($caller, $ticket);
    }

    public function test_release_deletes_owner_lock(): void
    {
        $staff = $this->createStaff();
        $other = $this->createStaff();
        $ticket = $this->createTicket();

        $ownerLock = $this->createLock($ticket, $staff, '2026-05-03 10:05:00');
        $otherLock = Lock::on('legacy')->create([
            'object_type' => 'T',
            'object_id' => $ticket->ticket_id + 1,
            'staff_id' => $other->staff_id,
            'expire' => '2026-05-03 10:05:00',
        ]);

        $this->service->release($staff, $ticket);

        $this->assertDatabaseMissing('lock', ['lock_id' => $ownerLock->lock_id], 'legacy');
        $this->assertDatabaseHas('lock', ['lock_id' => $otherLock->lock_id], 'legacy');
    }

    public function test_assert_held_by_passes_in_disabled_mode(): void
    {
        $staff = $this->createStaff();
        $ticket = $this->createTicket();
        $this->setLockConfig(mode: '0', minutes: 5);

        $this->service->assertHeldBy($staff, $ticket);

        $this->assertTrue(true);
    }

    public function test_assert_held_by_throws_on_view_mode_without_lock(): void
    {
        $staff = $this->createStaff();
        $ticket = $this->createTicket();
        $this->setLockConfig(mode: '1', minutes: 5);

        $this->expectException(TicketLockedException::class);

        try {
            $this->service->assertHeldBy($staff, $ticket);
        } catch (TicketLockedException $exception) {
            $this->assertSame($ticket->ticket_id, $exception->ticketId);
            $this->assertSame(0, $exception->heldByStaffId);
            $this->assertSame('', $exception->expiresAt);

            throw $exception;
        }
    }

    private function ensureLegacyTables(): void
    {
        $schema = Schema::connection('legacy');

        if (! $schema->hasTable('config')) {
            $schema->create('config', function (Blueprint $table): void {
                $table->id();
                $table->string('namespace');
                $table->string('key');
                $table->text('value');
                $table->timestamp('updated')->nullable();
            });
        }

        if (! $schema->hasTable('ticket')) {
            $schema->create('ticket', function (Blueprint $table): void {
                $table->unsignedInteger('ticket_id')->autoIncrement();
                $table->string('number')->default('T-1');
                $table->unsignedInteger('user_id')->default(1);
                $table->unsignedInteger('status_id')->default(1);
                $table->unsignedInteger('dept_id')->default(1);
                $table->unsignedInteger('staff_id')->default(0);
                $table->unsignedInteger('sla_id')->default(0);
                $table->unsignedInteger('email_id')->default(0);
                $table->string('source')->default('Web');
                $table->string('ip_address')->default('127.0.0.1');
                $table->tinyInteger('isoverdue')->default(0);
                $table->tinyInteger('isanswered')->default(0);
                $table->timestamp('duedate')->nullable();
                $table->timestamp('closed')->nullable();
                $table->timestamp('lastupdate')->useCurrent();
                $table->timestamp('lastmessage')->useCurrent();
                $table->timestamp('lastresponse')->useCurrent();
                $table->timestamp('created')->useCurrent();
                $table->timestamp('updated')->useCurrent();
            });
        }

        if (! $schema->hasTable('staff')) {
            $schema->create('staff', function (Blueprint $table): void {
                $table->unsignedInteger('staff_id')->autoIncrement();
                $table->unsignedInteger('dept_id')->default(1);
                $table->string('username', 32)->unique();
                $table->string('firstname', 64)->default('');
                $table->string('lastname', 64)->default('');
                $table->string('email', 128)->default('');
                $table->string('passwd', 128)->default('');
                $table->tinyInteger('isactive')->default(1);
                $table->tinyInteger('isadmin')->default(0);
                $table->timestamp('created')->nullable();
                $table->timestamp('lastlogin')->nullable();
            });
        }

        if (! $schema->hasTable('lock')) {
            $schema->create('lock', function (Blueprint $table): void {
                $table->unsignedInteger('lock_id')->autoIncrement();
                $table->char('object_type', 1);
                $table->unsignedInteger('object_id');
                $table->unsignedInteger('staff_id');
                $table->timestamp('expire');
            });
        }
    }

    private function resetTables(): void
    {
        DB::connection('legacy')->table('lock')->delete();
        DB::connection('legacy')->table('ticket')->delete();
        DB::connection('legacy')->table('staff')->delete();
        DB::connection('legacy')->table('config')->where('namespace', 'core')->delete();
        ScpActionLog::query()->delete();
    }

    private function setLockConfig(string $mode, int $minutes): void
    {
        DB::connection('legacy')->table('config')->insert([
            ['namespace' => 'core', 'key' => 'ticket_lock', 'value' => $mode, 'updated' => now()],
            ['namespace' => 'core', 'key' => 'lock_time', 'value' => (string) $minutes, 'updated' => now()],
        ]);
    }

    private function createTicket(): Ticket
    {
        return Ticket::on('legacy')->create([
            'number' => 'T-'.fake()->unique()->numerify('#####'),
            'user_id' => 1,
            'status_id' => 1,
            'dept_id' => 1,
            'staff_id' => 0,
            'sla_id' => 0,
            'email_id' => 0,
            'source' => 'Web',
            'ip_address' => '127.0.0.1',
            'isoverdue' => 0,
            'isanswered' => 0,
            'lastupdate' => now(),
            'lastmessage' => now(),
            'lastresponse' => now(),
            'created' => now(),
            'updated' => now(),
        ]);
    }

    private function createStaff(): Staff
    {
        return Staff::on('legacy')->create([
            'dept_id' => 1,
            'username' => fake()->unique()->userName(),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'passwd' => 'password',
            'isactive' => 1,
            'isadmin' => 0,
            'created' => now(),
            'lastlogin' => null,
        ]);
    }

    private function createLock(Ticket $ticket, Staff $staff, string $expire): Lock
    {
        return Lock::on('legacy')->create([
            'object_type' => 'T',
            'object_id' => $ticket->ticket_id,
            'staff_id' => $staff->staff_id,
            'expire' => $expire,
        ]);
    }
}
