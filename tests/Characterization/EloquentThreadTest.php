<?php

use App\Models\Thread;
use App\Models\ThreadEntry;

test('Eloquent Thread matches legacy fixture data', function () {
    $fixture = json_decode(
        file_get_contents(base_path('tests/fixtures/legacy/thread_sample_1.json'))
    );

    $thread = Thread::find($fixture->id);

    expect($thread)->not->toBeNull();
    expect($thread->object_id)->toBe($fixture->object_id);
    expect($thread->object_type)->toBe($fixture->object_type);
});

test('Eloquent Thread loads entries in chronological order', function () {
    $thread = Thread::where('object_type', 'T')
        ->has('entries')
        ->with('entries')
        ->first();

    expect($thread)->not->toBeNull();
    expect($thread->entries)->not->toBeEmpty();

    $dates = $thread->entries->pluck('created')->toArray();
    $sorted = $dates;
    sort($sorted);
    expect($dates)->toBe($sorted);
});

test('Eloquent ThreadEntry loads staff relation', function () {
    $entry = ThreadEntry::whereNot('staff_id', 0)
        ->with('staff')
        ->first();

    if ($entry === null) {
        $this->markTestSkipped('No thread entries with staff found.');
    }

    expect($entry->staff)->not->toBeNull();
    expect($entry->staff->staff_id)->toBe($entry->staff_id);
});
