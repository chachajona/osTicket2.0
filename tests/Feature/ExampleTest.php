<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_root_redirects_guests_to_staff_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/scp/login');
    }
}
