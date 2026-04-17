<?php

use App\Models\Ticket;

test('Eloquent Ticket matches legacy fixture data', function () {
    skipIfLegacyTablesMissing(['ticket']);
    skipIfLegacyColumnsMissing('ticket', ['number', 'dept_id', 'staff_id']);

    $fixture = json_decode(
        file_get_contents(base_path('tests/fixtures/legacy/ticket_sample_1.json'))
    );

    $ticket = Ticket::find($fixture->ticket_id);

    if ($ticket === null) {
        $this->markTestSkipped('Fixture ticket row is not present in the legacy database.');
    }

    expect($ticket)->not->toBeNull();
    expect($ticket->number)->toBe($fixture->number);
    expect($ticket->dept_id)->toBe($fixture->dept_id);
    expect($ticket->staff_id)->toBe($fixture->staff_id);
});

test('Eloquent Ticket loads staff relation', function () {
    skipIfLegacyTablesMissing(['ticket', 'staff']);
    skipIfLegacyColumnsMissing('ticket', ['staff_id']);

    $ticket = Ticket::whereNot('staff_id', 0)
        ->with('staff')
        ->first();

    if ($ticket === null) {
        $this->markTestSkipped('No tickets with assigned staff found.');
    }

    expect($ticket->staff)->not->toBeNull();
    expect($ticket->staff->staff_id)->toBe($ticket->staff_id);
});

test('Eloquent Ticket loads department relation', function () {
    skipIfLegacyTablesMissing(['ticket', 'department']);
    skipIfLegacyColumnsMissing('ticket', ['dept_id']);

    $ticket = Ticket::with('department')->first();

    if ($ticket === null) {
        $this->markTestSkipped('No tickets found in legacy database.');
    }

    expect($ticket)->not->toBeNull();
    expect($ticket->department)->not->toBeNull();
    expect($ticket->department->id)->toBe($ticket->dept_id);
});

test('Eloquent Ticket loads thread with entries', function () {
    skipIfLegacyTablesMissing(['ticket', 'thread', 'thread_entry']);
    skipIfLegacyColumnsMissing('thread', ['object_id', 'object_type']);
    skipIfLegacyColumnsMissing('thread_entry', ['thread_id']);

    $ticket = Ticket::with('thread.entries')->first();

    if ($ticket === null) {
        $this->markTestSkipped('No tickets found in legacy database.');
    }

    expect($ticket)->not->toBeNull();
    expect($ticket->thread)->not->toBeNull();
    expect($ticket->thread->object_type)->toBe('T');
    expect($ticket->thread->entries)->not->toBeEmpty();
});
