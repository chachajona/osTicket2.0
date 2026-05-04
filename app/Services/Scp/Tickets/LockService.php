<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Exceptions\TicketLockedException;
use App\Models\Lock;
use App\Models\Staff;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class LockService
{
    public function __construct(
        private readonly LegacyConfigReader $config,
        private readonly ActionLogger $audit,
    ) {}

    public function acquire(Staff $staff, Ticket $ticket): Lock
    {
        $stolenAudit = null;

        $lock = DB::connection('legacy')->transaction(function () use ($staff, $ticket, &$stolenAudit): Lock {
            $lock = $this->lockQuery($ticket, forUpdate: true)->first();
            $expiresAt = $this->expiresAt();

            if ($lock === null) {
                return Lock::on('legacy')->create([
                    'object_type' => 'T',
                    'object_id' => $ticket->ticket_id,
                    'staff_id' => $staff->staff_id,
                    'expire' => $expiresAt,
                ]);
            }

            if ((int) $lock->staff_id === (int) $staff->staff_id) {
                $lock->forceFill(['expire' => $expiresAt])->save();

                return $lock->refresh();
            }

            if (! $this->isExpired($lock)) {
                $this->throwLocked($ticket, $lock);
            }

            $beforeState = [
                'staff_id' => (int) $lock->staff_id,
                'expire' => Carbon::parse($lock->expire)->toDateTimeString(),
            ];

            $lock->forceFill([
                'staff_id' => $staff->staff_id,
                'expire' => $expiresAt,
            ])->save();

            $lock->refresh();

            $stolenAudit = [
                'before_state' => $beforeState,
                'after_state' => [
                    'staff_id' => (int) $lock->staff_id,
                    'expire' => Carbon::parse($lock->expire)->toDateTimeString(),
                ],
                'lock_id' => (string) $lock->lock_id,
            ];

            return $lock;
        });

        if ($stolenAudit !== null) {
            $this->audit->record(
                staff: $staff,
                action: 'lock.stolen',
                outcome: 'success',
                httpStatus: 200,
                ticketId: $ticket->ticket_id,
                beforeState: $stolenAudit['before_state'],
                afterState: $stolenAudit['after_state'],
                lockId: $stolenAudit['lock_id'],
            );
        }

        return $lock;
    }

    public function renew(Staff $staff, Ticket $ticket): Lock
    {
        return DB::connection('legacy')->transaction(function () use ($staff, $ticket): Lock {
            $lock = $this->lockQuery($ticket, forUpdate: true)->first();

            if ($lock === null || $this->isExpired($lock)) {
                throw new TicketLockedException($ticket->ticket_id, 0, '');
            }

            if ((int) $lock->staff_id !== (int) $staff->staff_id) {
                $this->throwLocked($ticket, $lock);
            }

            $lock->forceFill(['expire' => $this->expiresAt()])->save();

            return $lock->refresh();
        });
    }

    public function release(Staff $staff, Ticket $ticket): void
    {
        $this->lockQuery($ticket)
            ->where('staff_id', $staff->staff_id)
            ->delete();
    }

    public function assertHeldBy(Staff $staff, Ticket $ticket): void
    {
        if ($this->config->ticketLockMode() === 'disabled') {
            return;
        }

        $lock = $this->lockQuery($ticket)->first();

        if ($lock === null || $this->isExpired($lock)) {
            throw new TicketLockedException($ticket->ticket_id, 0, '');
        }

        if ((int) $lock->staff_id !== (int) $staff->staff_id) {
            $this->throwLocked($ticket, $lock);
        }
    }

    private function lockQuery(Ticket $ticket, bool $forUpdate = false)
    {
        $query = Lock::on('legacy')
            ->where('object_type', 'T')
            ->where('object_id', $ticket->ticket_id);

        if ($forUpdate && DB::connection('legacy')->getDriverName() !== 'sqlite') {
            $query->lockForUpdate();
        }

        return $query;
    }

    private function expiresAt(): string
    {
        return Carbon::now()->addSeconds($this->config->lockTime())->toDateTimeString();
    }

    private function isExpired(Lock $lock): bool
    {
        return Carbon::parse($lock->expire)->lessThanOrEqualTo(Carbon::now());
    }

    private function throwLocked(Ticket $ticket, Lock $lock): never
    {
        throw new TicketLockedException(
            $ticket->ticket_id,
            (int) $lock->staff_id,
            Carbon::parse($lock->expire)->toDateTimeString(),
        );
    }
}
