<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Staff;
use App\Models\Ticket;
use App\Policies\TicketActionPolicy;
use App\Services\DepartmentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TicketActionPolicyPostReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_reply_allowed_when_has_permission_and_dept_access(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(false);
        $staff->shouldReceive('can')->with('tickets.post-reply')->andReturn(true);

        $ticket = Ticket::factory()->create(['dept_id' => 5]);
        $deptService = \Mockery::mock(DepartmentPermissionService::class);
        $deptService->shouldReceive('hasAccessToDepartment')->with($staff, 5)->andReturn(true);

        $this->assertTrue((new TicketActionPolicy($deptService))->postReply($staff, $ticket));
    }

    public function test_post_reply_denied_when_missing_permission(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(false);
        $staff->shouldReceive('can')->with('tickets.post-reply')->andReturn(false);

        $ticket = Ticket::factory()->create(['dept_id' => 5]);
        $deptService = \Mockery::mock(DepartmentPermissionService::class);
        $deptService->shouldReceive('hasAccessToDepartment')->with($staff, 5)->andReturn(true);

        $this->assertFalse((new TicketActionPolicy($deptService))->postReply($staff, $ticket));
    }

    public function test_post_reply_denied_when_no_dept_access(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('can')->never();

        $ticket = Ticket::factory()->create(['dept_id' => 5]);
        $deptService = \Mockery::mock(DepartmentPermissionService::class);
        $deptService->shouldReceive('hasAccessToDepartment')->with($staff, 5)->andReturn(false);

        $this->assertFalse((new TicketActionPolicy($deptService))->postReply($staff, $ticket));
    }
}
