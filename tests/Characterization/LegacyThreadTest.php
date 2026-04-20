<?php

use Illuminate\Support\Facades\DB;

test('captures thread entries for a ticket', function () {
    skipIfLegacyTablesMissing(['thread', 'thread_entry']);

    $thread = DB::connection('legacy')->selectOne("
        SELECT th.object_id AS ticket_id
        FROM ost_thread th
        JOIN ost_thread_entry e ON e.thread_id = th.id
        WHERE th.object_type = 'T'
        GROUP BY th.object_id
        ORDER BY COUNT(e.id) DESC
        LIMIT 1
    ");

    if ($thread === null) {
        $this->markTestSkipped('No ticket threads with entries found in legacy database.');
    }

    $ticketId = $thread->ticket_id;

    $entries = DB::connection('legacy')->select("
        SELECT e.id, e.pid, e.thread_id, e.staff_id, e.user_id,
               e.type, e.poster, e.body, e.format, e.created
        FROM ost_thread_entry e
        JOIN ost_thread th ON th.id = e.thread_id
        WHERE th.object_id = ? AND th.object_type = 'T'
        ORDER BY e.created ASC
    ", [$ticketId]);

    file_put_contents(
        base_path("tests/fixtures/legacy/thread_entries_ticket_{$ticketId}.json"),
        json_encode($entries, JSON_PRETTY_PRINT)
    );

    if ($entries === []) {
        $this->markTestSkipped('No thread entries found for the selected ticket.');
    }

    expect($entries)->not->toBeEmpty();
    expect($entries[0]->type)->toBeIn(['M', 'R', 'N']);
});

test('captures thread metadata for a ticket', function () {
    skipIfLegacyTablesMissing(['thread']);
    skipIfLegacyColumnsMissing('thread', ['extra']);

    $thread = DB::connection('legacy')->selectOne("
        SELECT id, object_id, object_type, extra, created
        FROM ost_thread
        WHERE object_type = 'T'
        ORDER BY id DESC
        LIMIT 1
    ");

    file_put_contents(
        base_path('tests/fixtures/legacy/thread_sample_1.json'),
        json_encode($thread, JSON_PRETTY_PRINT)
    );

    if ($thread === null) {
        $this->markTestSkipped('No ticket threads found in legacy database.');
    }

    expect($thread)->not->toBeNull();
    expect($thread->object_type)->toBe('T');
});
