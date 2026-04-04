<?php

use App\Models\Staff;

test('Eloquent Staff matches legacy fixture data', function () {
    $fixture = json_decode(
        file_get_contents(base_path('tests/fixtures/legacy/staff_sample_1.json'))
    );

    $staff = Staff::find($fixture->staff_id);

    expect($staff)->not->toBeNull();
    expect($staff->username)->toBe($fixture->username);
    expect($staff->firstname)->toBe($fixture->firstname);
    expect($staff->lastname)->toBe($fixture->lastname);
    expect($staff->dept_id)->toBe($fixture->dept_id);
});

test('Eloquent Staff loads department relation', function () {
    $staff = Staff::with('department')->first();

    expect($staff)->not->toBeNull();
    expect($staff->department)->not->toBeNull();
    expect($staff->department->id)->toBe($staff->dept_id);
});

test('Eloquent Staff loads assigned tickets', function () {
    $staff = Staff::whereHas('assignedTickets')
        ->with('assignedTickets')
        ->first();

    if ($staff === null) {
        $this->markTestSkipped('No staff with assigned tickets found.');
    }

    expect($staff->assignedTickets)->not->toBeEmpty();
    expect($staff->assignedTickets->first()->staff_id)->toBe($staff->staff_id);
});
