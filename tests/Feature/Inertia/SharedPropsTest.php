<?php

namespace Tests\Feature\Inertia;

use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_admin_staff_has_true_is_admin(): void
    {
        $staff = Staff::factory()->create(['isadmin' => 1]);

        $response = $this->actingAs($staff, 'staff')->get('/scp');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.staff.isAdmin', true)
        );
    }

    public function test_non_admin_staff_has_false_is_admin(): void
    {
        $staff = Staff::factory()->create(['isadmin' => 0]);

        $response = $this->actingAs($staff, 'staff')->get('/scp');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.staff.isAdmin', false)
        );
    }

    public function test_unauthenticated_user_redirects_to_login(): void
    {
        $response = $this->get('/scp');

        $response->assertRedirect('/scp/login');
    }
}
