<?php

use Illuminate\Support\Facades\DB;

test('captures staff with department from legacy DB', function () {
    $staff = DB::connection('legacy')->selectOne("
        SELECT s.staff_id, s.dept_id, s.role_id, s.username,
               s.firstname, s.lastname, s.email, s.isactive, s.isadmin,
               s.created, s.lastlogin,
               d.name AS dept_name
        FROM ost_staff s
        LEFT JOIN ost_department d ON d.id = s.dept_id
        ORDER BY s.staff_id ASC
        LIMIT 1
    ");

    file_put_contents(
        base_path('tests/fixtures/legacy/staff_sample_1.json'),
        json_encode($staff, JSON_PRETTY_PRINT)
    );

    expect($staff)->not->toBeNull();
    expect($staff->staff_id)->toBeInt();
    expect($staff->username)->not->toBeEmpty();
});

test('captures staff department access from legacy DB', function () {
    $staffId = DB::connection('legacy')->selectOne("SELECT staff_id FROM ost_staff LIMIT 1")->staff_id;

    $access = DB::connection('legacy')->select("
        SELECT a.staff_id, a.dept_id, a.role_id,
               d.name AS dept_name
        FROM ost_staff_dept_access a
        LEFT JOIN ost_department d ON d.id = a.dept_id
        WHERE a.staff_id = ?
    ", [$staffId]);

    file_put_contents(
        base_path("tests/fixtures/legacy/staff_dept_access_{$staffId}.json"),
        json_encode($access, JSON_PRETTY_PRINT)
    );

    expect($access)->toBeArray();
});
