<?php

use Illuminate\Support\Facades\DB;

test('captures ticket with relations from legacy DB', function () {
    $ticket = DB::connection('legacy')->selectOne("
        SELECT t.ticket_id, t.number, t.dept_id, t.staff_id,
               t.topic_id, t.status_id, t.source, t.isoverdue,
               t.created, t.updated, t.closed,
               c.subject, c.priority
        FROM ost_ticket t
        LEFT JOIN ost_ticket__cdata c ON c.ticket_id = t.ticket_id
        ORDER BY t.ticket_id DESC
        LIMIT 1
    ");

    $fixturePath = base_path('tests/fixtures/legacy/ticket_sample_1.json');
    file_put_contents($fixturePath, json_encode($ticket, JSON_PRETTY_PRINT));

    expect($ticket)->not->toBeNull();
    expect($ticket->ticket_id)->toBeInt();
    expect($ticket->number)->not->toBeEmpty();
});
