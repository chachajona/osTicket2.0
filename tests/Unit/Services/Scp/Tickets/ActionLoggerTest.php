<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scp\Tickets;

use App\Models\Scp\ScpActionLog;
use App\Models\Staff;
use App\Services\Scp\Tickets\ActionLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

final class ActionLoggerTest extends TestCase
{
    use RefreshDatabase;

    private ActionLogger $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = new ActionLogger();
    }

    public function test_logs_success_action_with_full_context(): void
    {
        $staff = Staff::factory()->create();
        $request = $this->createRequest('192.168.1.1', 'Mozilla/5.0', 'req-123');

        $log = $this->logger->record(
            staff: $staff,
            action: 'ticket_update',
            outcome: 'success',
            httpStatus: 200,
            ticketId: 42,
            threadId: 10,
            queueId: 5,
            beforeState: ['status' => 'open'],
            afterState: ['status' => 'closed'],
            lockId: 'lock-abc',
            request: $request
        );

        $this->assertInstanceOf(ScpActionLog::class, $log);
        $this->assertDatabaseHas('scp_action_log', [
            'staff_id' => $staff->staff_id,
            'ticket_id' => 42,
            'thread_id' => 10,
            'queue_id' => 5,
            'action' => 'ticket_update',
            'outcome' => 'success',
            'http_status' => 200,
            'lock_id' => 'lock-abc',
            'request_id' => 'req-123',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $this->assertEquals(['status' => 'open'], $log->before_state);
        $this->assertEquals(['status' => 'closed'], $log->after_state);
    }

    public function test_logs_failure_with_error_class(): void
    {
        $staff = Staff::factory()->create();
        $request = $this->createRequest('10.0.0.1', 'Chrome/90', 'req-456');

        $log = $this->logger->recordFailure(
            staff: $staff,
            action: 'ticket_lock_check',
            httpStatus: 423,
            ticketId: 99,
            errorClass: 'TicketLockedException',
            request: $request
        );

        $this->assertInstanceOf(ScpActionLog::class, $log);
        $this->assertDatabaseHas('scp_action_log', [
            'staff_id' => $staff->staff_id,
            'ticket_id' => 99,
            'action' => 'ticket_lock_check',
            'outcome' => 'failed',
            'http_status' => 423,
            'request_id' => 'req-456',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Chrome/90',
        ]);

        $this->assertEquals(['error_class' => 'TicketLockedException'], $log->before_state);
    }

    private function createRequest(string $ip, string $userAgent, string $requestId): Request
    {
        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
        ]);
        $request->headers->set('X-Request-Id', $requestId);

        return $request;
    }
}
