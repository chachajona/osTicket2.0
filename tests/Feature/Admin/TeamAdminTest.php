<?php

declare(strict_types=1);

use App\Models\Staff;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

beforeEach(function (): void {
    $schema = Schema::connection('legacy');

    if (! $schema->hasTable('team')) {
        $schema->create('team', function (Blueprint $table): void {
            $table->increments('team_id');
            $table->unsignedInteger('lead_id')->nullable();
            $table->unsignedInteger('flags')->default(1);
            $table->string('name', 64);
            $table->string('notes', 255)->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('updated')->nullable();
        });
    }

    if (! $schema->hasTable('team_member')) {
        $schema->create('team_member', function (Blueprint $table): void {
            $table->unsignedInteger('team_id');
            $table->unsignedInteger('staff_id');
            $table->unsignedInteger('flags')->default(0);

            $table->primary(['team_id', 'staff_id']);
        });
    }

    TeamMember::query()->delete();
    Team::query()->delete();
});

function grantTeamAdminPermissions(Staff $staff, array $permissions): Staff
{
    $staff->givePermissionTo($permissions);

    return $staff->fresh();
}

/**
 * @param  list<int>  $members
 * @return array<string, mixed>
 */
function teamAuditPayload(Team $team, array $members, ?string $name = null, ?int $leadId = null, ?string $notes = null, ?bool $status = null): array
{
    sort($members);

    return [
        'id' => $team->team_id,
        'name' => $name ?? $team->name,
        'lead_id' => $leadId,
        'notes' => $notes,
        'status' => $status ?? ((bool) $team->flags),
        'members' => $members,
    ];
}

it('renders the team index for authorized admins with lead names and member counts', function (): void {
    $lead = Staff::factory()->create();
    $memberOne = Staff::factory()->create();
    $memberTwo = Staff::factory()->create();

    $team = Team::query()->create([
        'lead_id' => $lead->staff_id,
        'flags' => 1,
        'name' => 'Escalations',
        'notes' => 'Handles escalations',
        'created' => now(),
        'updated' => now(),
    ]);

    TeamMember::query()->create(['team_id' => $team->team_id, 'staff_id' => $memberOne->staff_id, 'flags' => 0]);
    TeamMember::query()->create(['team_id' => $team->team_id, 'staff_id' => $memberTwo->staff_id, 'flags' => 0]);

    Team::query()->create([
        'lead_id' => null,
        'flags' => 0,
        'name' => 'Overflow',
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    $staff = grantTeamAdminPermissions(actingAsAdmin(), ['admin.team.update']);

    actingAs($staff, 'staff');

    get(route('admin.teams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Teams/Index')
            ->has('teams.data', 2)
            ->where('teams.data.0.name', 'Escalations')
            ->where('teams.data.0.lead_name', $lead->displayName())
            ->where('teams.data.0.member_count', 2)
            ->where('teams.data.0.status', true)
            ->where('teams.data.1.name', 'Overflow')
            ->where('teams.data.1.lead_name', null)
            ->where('teams.data.1.member_count', 0)
            ->where('teams.data.1.status', false)
        );
});

it('forbids the team index for unauthorized staff', function (): void {
    actingAsAgent();

    get(route('admin.teams.index'))->assertForbidden();
});

it('renders create and edit pages with staff options and selected members', function (): void {
    $lead = Staff::factory()->create();
    $member = Staff::factory()->create();
    $other = Staff::factory()->create();

    $team = Team::query()->create([
        'lead_id' => $lead->staff_id,
        'flags' => 1,
        'name' => 'Managers',
        'notes' => 'Leadership team',
        'created' => now(),
        'updated' => now(),
    ]);

    TeamMember::query()->create(['team_id' => $team->team_id, 'staff_id' => $member->staff_id, 'flags' => 0]);

    $staff = grantTeamAdminPermissions(actingAsAdmin(), ['admin.team.create', 'admin.team.update']);

    actingAs($staff, 'staff');

    get(route('admin.teams.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Teams/Edit')
            ->where('team', null)
            ->has('staffOptions', 4)
        );

    actingAs($staff, 'staff');

    get(route('admin.teams.edit', $team))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Teams/Edit')
            ->where('team.name', 'Managers')
            ->where('team.lead_id', $lead->staff_id)
            ->where('team.member_ids', [$member->staff_id])
            ->has('staffOptions', 4)
        );

    expect(Staff::query()->whereKey($other->staff_id)->exists())->toBeTrue();
});

it('creates a team, syncs members, and writes an audit log', function (): void {
    $lead = Staff::factory()->create();
    $memberOne = Staff::factory()->create();
    $memberTwo = Staff::factory()->create();
    $staff = grantTeamAdminPermissions(actingAsAdmin(), ['admin.team.create']);

    actingAs($staff, 'staff');

    post(route('admin.teams.store'), [
        'name' => 'Escalations',
        'lead_id' => $lead->staff_id,
        'notes' => 'Priority routing',
        'status' => true,
        'members' => [$memberTwo->staff_id, $memberOne->staff_id],
    ])->assertRedirect();

    $team = Team::query()->where('name', 'Escalations')->firstOrFail();

    assertDatabaseHas('team', [
        'team_id' => $team->team_id,
        'lead_id' => $lead->staff_id,
        'flags' => 1,
        'notes' => 'Priority routing',
    ], 'legacy');

    assertDatabaseHas('team_member', ['team_id' => $team->team_id, 'staff_id' => $memberOne->staff_id], 'legacy');
    assertDatabaseHas('team_member', ['team_id' => $team->team_id, 'staff_id' => $memberTwo->staff_id], 'legacy');

    assertAuditLogged(
        'team.create',
        $team,
        null,
        teamAuditPayload($team, [$memberOne->staff_id, $memberTwo->staff_id], 'Escalations', $lead->staff_id, 'Priority routing', true),
    );
});

it('rejects invalid team creation payloads', function (): void {
    $staff = grantTeamAdminPermissions(actingAsAdmin(), ['admin.team.create']);

    actingAs($staff, 'staff');

    from(route('admin.teams.create'))
        ->post(route('admin.teams.store'), [
            'name' => '',
            'lead_id' => 999999,
            'notes' => str_repeat('a', 256),
            'status' => 'invalid',
            'members' => ['not-a-staff-id'],
        ])
        ->assertSessionHasErrors(['name', 'lead_id', 'notes', 'status', 'members.0']);

    expect(Team::query()->count())->toBe(0);
});

it('forbids unauthorized team creation', function (): void {
    actingAsAgent();

    post(route('admin.teams.store'), [
        'name' => 'Escalations',
        'status' => true,
    ])->assertForbidden();
});

it('updates a team, syncs members, changes the lead, and writes an audit log diff', function (): void {
    $oldLead = Staff::factory()->create();
    $newLead = Staff::factory()->create();
    $removedMember = Staff::factory()->create();
    $keptMember = Staff::factory()->create();
    $newMember = Staff::factory()->create();

    $team = Team::query()->create([
        'lead_id' => $oldLead->staff_id,
        'flags' => 1,
        'name' => 'Managers',
        'notes' => 'Old notes',
        'created' => now(),
        'updated' => now(),
    ]);

    TeamMember::query()->create(['team_id' => $team->team_id, 'staff_id' => $removedMember->staff_id, 'flags' => 0]);
    TeamMember::query()->create(['team_id' => $team->team_id, 'staff_id' => $keptMember->staff_id, 'flags' => 0]);

    $staff = grantTeamAdminPermissions(actingAsAdmin(), ['admin.team.update']);

    actingAs($staff, 'staff');

    put(route('admin.teams.update', $team), [
        'name' => 'Supervisors',
        'lead_id' => $newLead->staff_id,
        'notes' => 'Updated notes',
        'status' => false,
        'members' => [$keptMember->staff_id, $newMember->staff_id],
    ])->assertRedirect(route('admin.teams.edit', $team));

    $team->refresh();

    expect($team->name)->toBe('Supervisors')
        ->and((int) $team->lead_id)->toBe($newLead->staff_id)
        ->and($team->notes)->toBe('Updated notes')
        ->and((int) $team->flags)->toBe(0)
        ->and(TeamMember::query()->forTeam($team->team_id)->orderBy('staff_id')->pluck('staff_id')->all())
        ->toBe([$keptMember->staff_id, $newMember->staff_id]);

    assertDatabaseMissing('team_member', ['team_id' => $team->team_id, 'staff_id' => $removedMember->staff_id], 'legacy');

    assertAuditLogged(
        'team.update',
        $team,
        teamAuditPayload($team, [$removedMember->staff_id, $keptMember->staff_id], 'Managers', $oldLead->staff_id, 'Old notes', true),
        teamAuditPayload($team, [$keptMember->staff_id, $newMember->staff_id], 'Supervisors', $newLead->staff_id, 'Updated notes', false),
    );
});

it('deletes a team, removes team members, and writes an audit log entry', function (): void {
    $lead = Staff::factory()->create();
    $member = Staff::factory()->create();

    $team = Team::query()->create([
        'lead_id' => $lead->staff_id,
        'flags' => 1,
        'name' => 'Managers',
        'notes' => 'Delete me',
        'created' => now(),
        'updated' => now(),
    ]);

    TeamMember::query()->create(['team_id' => $team->team_id, 'staff_id' => $member->staff_id, 'flags' => 0]);

    $staff = grantTeamAdminPermissions(actingAsAdmin(), ['admin.team.delete']);

    actingAs($staff, 'staff');

    delete(route('admin.teams.destroy', $team))
        ->assertRedirect(route('admin.teams.index'));

    assertDatabaseMissing('team', ['team_id' => $team->team_id], 'legacy');
    assertDatabaseMissing('team_member', ['team_id' => $team->team_id, 'staff_id' => $member->staff_id], 'legacy');

    assertAuditLogged(
        'team.delete',
        $team,
        teamAuditPayload($team, [$member->staff_id], 'Managers', $lead->staff_id, 'Delete me', true),
        null,
    );
});

it('forbids unauthorized team updates and deletion', function (): void {
    $team = Team::query()->create([
        'lead_id' => null,
        'flags' => 1,
        'name' => 'Managers',
        'notes' => '',
        'created' => now(),
        'updated' => now(),
    ]);

    actingAsAgent();

    put(route('admin.teams.update', $team), [
        'name' => 'Supervisors',
        'status' => true,
    ])->assertForbidden();

    delete(route('admin.teams.destroy', $team))->assertForbidden();
});
