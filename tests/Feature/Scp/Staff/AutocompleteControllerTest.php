<?php

declare(strict_types=1);

namespace Tests\Feature\Scp\Staff;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutocompleteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_active_staff_matching_query(): void
    {
        /** @var Staff $current */
        $current = Staff::factory()->create(['isactive' => 1, 'firstname' => 'Current', 'lastname' => 'User', 'username' => 'current']);
        Staff::factory()->create(['isactive' => 1, 'firstname' => 'Ada', 'lastname' => 'Lovelace', 'username' => 'ada']);
        Staff::factory()->create(['isactive' => 1, 'firstname' => 'Alice', 'lastname' => 'Smith', 'username' => 'alice']);
        Staff::factory()->create(['isactive' => 0, 'firstname' => 'Inactive', 'lastname' => 'Person', 'username' => 'inactive']);

        $this->actingAs($current, 'staff')
            ->getJson(route('scp.staff.autocomplete', ['q' => 'ada']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Ada Lovelace', 'username' => 'ada']);
    }

    public function test_excludes_current_staff(): void
    {
        /** @var Staff $current */
        $current = Staff::factory()->create(['isactive' => 1, 'firstname' => 'Current', 'lastname' => 'User', 'username' => 'current']);

        $this->actingAs($current, 'staff')
            ->getJson(route('scp.staff.autocomplete'))
            ->assertOk()
            ->assertJsonMissing(['username' => 'current']);
    }

    public function test_excludes_inactive_staff(): void
    {
        /** @var Staff $current */
        $current = Staff::factory()->create(['isactive' => 1, 'firstname' => 'Active', 'lastname' => 'User', 'username' => 'active']);
        Staff::factory()->create(['isactive' => 0, 'firstname' => 'Inactive', 'lastname' => 'Person', 'username' => 'gone']);

        $this->actingAs($current, 'staff')
            ->getJson(route('scp.staff.autocomplete'))
            ->assertOk()
            ->assertJsonMissing(['username' => 'gone']);
    }

    public function test_returns_at_most_ten_results(): void
    {
        /** @var Staff $current */
        $current = Staff::factory()->create(['isactive' => 1]);
        Staff::factory()->count(15)->create(['isactive' => 1]);

        $response = $this->actingAs($current, 'staff')
            ->getJson(route('scp.staff.autocomplete'))
            ->assertOk();

        $this->assertCount(10, $response->json());
    }

    public function test_returns_401_for_unauthenticated(): void
    {
        $this->getJson(route('scp.staff.autocomplete'))->assertUnauthorized();
    }
}
