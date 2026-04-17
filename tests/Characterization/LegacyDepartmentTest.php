<?php

use Illuminate\Support\Facades\DB;

test('captures department with manager from legacy DB', function () {
    skipIfLegacyTablesMissing(['department', 'staff']);

    $dept = DB::connection('legacy')->selectOne("
        SELECT d.id, d.pid, d.name, d.signature,
               d.manager_id, d.sla_id, d.email_id,
               d.ispublic, d.flags, d.created, d.updated,
               s.firstname AS manager_firstname,
               s.lastname AS manager_lastname
        FROM ost_department d
        LEFT JOIN ost_staff s ON s.staff_id = d.manager_id
        ORDER BY d.id ASC
        LIMIT 1
    ");

    file_put_contents(
        base_path('tests/fixtures/legacy/department_sample_1.json'),
        json_encode($dept, JSON_PRETTY_PRINT)
    );

    if ($dept === null) {
        $this->markTestSkipped('No departments found in legacy database.');
    }

    expect($dept)->not->toBeNull();
    expect($dept->id)->toBeInt();
    expect($dept->name)->not->toBeEmpty();
});

test('captures all departments for hierarchy check', function () {
    skipIfLegacyTablesMissing(['department']);

    $departments = DB::connection('legacy')->select("
        SELECT id, pid, name, ispublic
        FROM ost_department
        ORDER BY id ASC
    ");

    file_put_contents(
        base_path('tests/fixtures/legacy/departments_all.json'),
        json_encode($departments, JSON_PRETTY_PRINT)
    );

    if ($departments === []) {
        $this->markTestSkipped('No departments found in legacy database.');
    }

    expect($departments)->not->toBeEmpty();
});
