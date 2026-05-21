<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Staff;
use App\Models\Ticket;
use App\Policies\TicketActionPolicy;
use App\Services\DepartmentPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

final class TicketActionPolicyTest extends TestCase
{
    use RefreshDatabase;

    private TicketActionPolicy $policy;

    private MockInterface $deptService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deptService = $this->mock(DepartmentPermissionService::class);
        $this->policy = new TicketActionPolicy($this->deptService);
    }

    public function test_post_note_allowed_when_has_permission_and_dept_access(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(false);
        $staff->shouldReceive('can')
            ->with('tickets.post-note')
            ->andReturn(true);

        $ticket = Ticket::factory()->create(['dept_id' => 1]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 1)
            ->andReturn(true);

        $this->assertTrue($this->policy->postNote($staff, $ticket));
    }

    public function test_post_note_denied_when_missing_permission(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(false);
        $staff->shouldReceive('can')
            ->with('tickets.post-note')
            ->andReturn(false);

        $ticket = Ticket::factory()->create(['dept_id' => 1]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 1)
            ->andReturn(true);

        $this->assertFalse($this->policy->postNote($staff, $ticket));
    }

    public function test_post_note_denied_when_no_dept_access(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('can')
            ->never();

        $ticket = Ticket::factory()->create(['dept_id' => 1]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 1)
            ->andReturn(false);

        $this->assertFalse($this->policy->postNote($staff, $ticket));
    }

    public function test_assign_allowed_when_has_permission_and_dept_access(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(false);
        $staff->shouldReceive('can')
            ->with('tickets.assign')
            ->andReturn(true);

        $ticket = Ticket::factory()->create(['dept_id' => 2]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 2)
            ->andReturn(true);

        $this->assertTrue($this->policy->assign($staff, $ticket));
    }

    public function test_assign_denied_when_missing_permission(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(false);
        $staff->shouldReceive('can')
            ->with('tickets.assign')
            ->andReturn(false);

        $ticket = Ticket::factory()->create(['dept_id' => 2]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 2)
            ->andReturn(true);

        $this->assertFalse($this->policy->assign($staff, $ticket));
    }

    public function test_assign_denied_when_no_dept_access(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('can')
            ->never();

        $ticket = Ticket::factory()->create(['dept_id' => 2]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 2)
            ->andReturn(false);

        $this->assertFalse($this->policy->assign($staff, $ticket));
    }

    public function test_set_status_allowed_when_has_permission_and_dept_access(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(false);
        $staff->shouldReceive('can')
            ->with('tickets.set-status')
            ->andReturn(true);

        $ticket = Ticket::factory()->create(['dept_id' => 3]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 3)
            ->andReturn(true);

        $this->assertTrue($this->policy->setStatus($staff, $ticket));
    }

    public function test_set_status_denied_when_missing_permission(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(false);
        $staff->shouldReceive('can')
            ->with('tickets.set-status')
            ->andReturn(false);

        $ticket = Ticket::factory()->create(['dept_id' => 3]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 3)
            ->andReturn(true);

        $this->assertFalse($this->policy->setStatus($staff, $ticket));
    }

    public function test_set_status_denied_when_no_dept_access(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('can')
            ->never();

        $ticket = Ticket::factory()->create(['dept_id' => 3]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 3)
            ->andReturn(false);

        $this->assertFalse($this->policy->setStatus($staff, $ticket));
    }

    public function test_admin_can_perform_ticket_actions_without_explicit_permissions(): void
    {
        $staff = \Mockery::mock(Staff::class);
        $staff->shouldReceive('getAttribute')->with('isadmin')->andReturn(true);
        $staff->shouldReceive('can')->never();

        $ticket = Ticket::factory()->create(['dept_id' => 9]);

        $this->deptService->shouldReceive('hasAccessToDepartment')
            ->with($staff, 9)
            ->times(4)
            ->andReturn(true);

        $this->assertTrue($this->policy->postNote($staff, $ticket));
        $this->assertTrue($this->policy->postReply($staff, $ticket));
        $this->assertTrue($this->policy->assign($staff, $ticket));
        $this->assertTrue($this->policy->setStatus($staff, $ticket));
    }
}
