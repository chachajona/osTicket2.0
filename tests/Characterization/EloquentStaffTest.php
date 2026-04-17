<?php

use App\Models\Staff;

test('Eloquent Staff matches legacy fixture data', function () {
    skipIfLegacyTablesMissing(['staff']);

    $fixture = json_decode(
        file_get_contents(base_path('tests/fixtures/legacy/staff_sample_1.json'))
    );

    $staff = Staff::find($fixture->staff_id);

    if ($staff === null) {
        $this->markTestSkipped('Fixture staff row is not present in the legacy database.');
    }

    expect($staff)->not->toBeNull();
    expect($staff->username)->toBe($fixture->username);
    expect($staff->firstname)->toBe($fixture->firstname);
    expect($staff->lastname)->toBe($fixture->lastname);
    expect($staff->dept_id)->toBe($fixture->dept_id);
});

test('Eloquent Staff loads department relation', function () {
    skipIfLegacyTablesMissing(['staff', 'department']);

    $staff = Staff::with('department')->first();

    if ($staff === null) {
        $this->markTestSkipped('No staff found in legacy database.');
    }

    expect($staff)->not->toBeNull();
    expect($staff->department)->not->toBeNull();
    expect($staff->department->id)->toBe($staff->dept_id);
});

test('Eloquent Staff loads assigned tickets', function () {
    skipIfLegacyTablesMissing(['staff', 'ticket']);
    skipIfLegacyColumnsMissing('ticket', ['staff_id']);

    $staff = Staff::whereHas('assignedTickets')
        ->with('assignedTickets')
        ->first();

    if ($staff === null) {
        $this->markTestSkipped('No staff with assigned tickets found.');
    }

    expect($staff->assignedTickets)->not->toBeEmpty();
    expect($staff->assignedTickets->first()->staff_id)->toBe($staff->staff_id);
});
