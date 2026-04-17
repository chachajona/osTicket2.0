<?php

use App\Models\Thread;
use App\Models\ThreadEntry;

test('Eloquent Thread matches legacy fixture data', function () {
    skipIfLegacyTablesMissing(['thread']);
    skipIfLegacyColumnsMissing('thread', ['object_id', 'object_type']);

    $fixture = json_decode(
        file_get_contents(base_path('tests/fixtures/legacy/thread_sample_1.json'))
    );

    $thread = Thread::find($fixture->id);

    if ($thread === null) {
        $this->markTestSkipped('Fixture thread row is not present in the legacy database.');
    }

    expect($thread)->not->toBeNull();
    expect($thread->object_id)->toBe($fixture->object_id);
    expect($thread->object_type)->toBe($fixture->object_type);
});

test('Eloquent Thread loads entries in chronological order', function () {
    skipIfLegacyTablesMissing(['thread', 'thread_entry']);
    skipIfLegacyColumnsMissing('thread', ['object_type']);
    skipIfLegacyColumnsMissing('thread_entry', ['thread_id', 'created']);

    $thread = Thread::where('object_type', 'T')
        ->has('entries')
        ->with('entries')
        ->first();

    if ($thread === null) {
        $this->markTestSkipped('No ticket threads with entries found.');
    }

    expect($thread)->not->toBeNull();
    expect($thread->entries)->not->toBeEmpty();

    $dates = $thread->entries->pluck('created')->toArray();
    $sorted = $dates;
    sort($sorted);
    expect($dates)->toBe($sorted);
});

test('Eloquent ThreadEntry loads staff relation', function () {
    skipIfLegacyTablesMissing(['thread_entry', 'staff']);
    skipIfLegacyColumnsMissing('thread_entry', ['staff_id']);

    $entry = ThreadEntry::whereNot('staff_id', 0)
        ->with('staff')
        ->first();

    if ($entry === null) {
        $this->markTestSkipped('No thread entries with staff found.');
    }

    expect($entry->staff)->not->toBeNull();
    expect($entry->staff->staff_id)->toBe($entry->staff_id);
});
