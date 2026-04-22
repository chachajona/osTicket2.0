<?php

use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('login.tester.127.0.0.1');
});

it(
    'flashes throttle metadata when attempts remain',
    function () {
        Staff::factory()->create([
            'username' => 'tester',
            'passwd' => Hash::make('correct-horse'),
            'isactive' => 1,
        ]);

        $response = $this->from('/scp/login')->post('/scp/login', [
            'username' => 'tester',
            'password' => 'wrong',
        ]);

        $response->assertRedirect('/scp/login');
        $response->assertSessionHas('throttle.attemptsRemaining', 4);
        $response->assertSessionHas('throttle.username', 'tester');
        $response->assertSessionMissing('throttle.secondsUntilRetry');
    }
);

it('flashes a countdown after exceeding the limit', function () {
    Staff::factory()->create([
        'username' => 'tester',
        'passwd' => Hash::make('correct-horse'),
        'isactive' => 1,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post('/scp/login', ['username' => 'tester', 'password' => 'wrong']);
    }

    $response = $this->from('/scp/login')->post('/scp/login', [
        'username' => 'tester',
        'password' => 'wrong',
    ]);

    $response->assertSessionHas('throttle.secondsUntilRetry', fn($value) => $value > 0 && $value <= 300);
    $response->assertSessionHas('throttle.username', 'tester');
});
