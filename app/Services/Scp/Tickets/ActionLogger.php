<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Models\Scp\ScpActionLog;
use App\Models\Staff;
use Illuminate\Http\Request;

final class ActionLogger
{
    /**
     * Record an action with full context.
     *
     * @param Staff $staff
     * @param string $action
     * @param string $outcome
     * @param int $httpStatus
     * @param int|null $ticketId
     * @param int|null $threadId
     * @param int|null $queueId
     * @param array|null $beforeState
     * @param array|null $afterState
     * @param string|null $lockId
     * @param Request|null $request
     * @return ScpActionLog
     */
    public function record(
        Staff $staff,
        string $action,
        string $outcome,
        int $httpStatus,
        ?int $ticketId = null,
        ?int $threadId = null,
        ?int $queueId = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?string $lockId = null,
        ?Request $request = null,
    ): ScpActionLog {
        $data = [
            'staff_id' => $staff->staff_id,
            'action' => $action,
            'outcome' => $outcome,
            'http_status' => $httpStatus,
            'ticket_id' => $ticketId,
            'thread_id' => $threadId,
            'queue_id' => $queueId,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'lock_id' => $lockId,
        ];

        if ($request !== null) {
            $data['request_id'] = $request->headers->get('X-Request-Id');
            $data['ip_address'] = $request->ip();
            $data['user_agent'] = $request->userAgent();
        }

        return ScpActionLog::create($data);
    }

    /**
     * Record a failed action with error class.
     *
     * @param Staff $staff
     * @param string $action
     * @param int $httpStatus
     * @param int|null $ticketId
     * @param string $errorClass
     * @param Request|null $request
     * @return ScpActionLog
     */
    public function recordFailure(
        Staff $staff,
        string $action,
        int $httpStatus,
        ?int $ticketId = null,
        string $errorClass = '',
        ?Request $request = null,
    ): ScpActionLog {
        return $this->record(
            staff: $staff,
            action: $action,
            outcome: 'failed',
            httpStatus: $httpStatus,
            ticketId: $ticketId,
            beforeState: ['error_class' => $errorClass],
            request: $request,
        );
    }
}
