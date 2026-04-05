<?php

use App\Models\Ticket;

test('Eloquent Ticket matches legacy fixture data', function () {
    $fixture = json_decode(
        file_get_contents(base_path('tests/fixtures/legacy/ticket_sample_1.json'))
    );

    $ticket = Ticket::find($fixture->ticket_id);

    expect($ticket)->not->toBeNull();
    expect($ticket->number)->toBe($fixture->number);
    expect($ticket->dept_id)->toBe($fixture->dept_id);
    expect($ticket->staff_id)->toBe($fixture->staff_id);
});

test('Eloquent Ticket loads staff relation', function () {
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
    $ticket = Ticket::with('department')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->department)->not->toBeNull();
    expect($ticket->department->id)->toBe($ticket->dept_id);
});

test('Eloquent Ticket loads thread with entries', function () {
    $ticket = Ticket::with('thread.entries')->first();

    expect($ticket)->not->toBeNull();
    expect($ticket->thread)->not->toBeNull();
    expect($ticket->thread->object_type)->toBe('T');
    expect($ticket->thread->entries)->not->toBeEmpty();
});
