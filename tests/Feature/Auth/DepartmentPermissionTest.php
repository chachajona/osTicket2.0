<?php

use App\Models\Staff;
use App\Models\StaffDeptAccess;
use App\Services\DepartmentPermissionService;

function makeStaffModel(array $attrs = []): Staff
{
    $staff = new Staff(array_merge([
        'staff_id' => 10,
        'username' => 'staffuser',
        'isadmin' => '0',
        'isactive' => '1',
    ], $attrs));
    $staff->exists = true;

    return $staff;
}

test('admin staff has access to any department', function () {
    $service = app(DepartmentPermissionService::class);
    $admin = makeStaffModel(['isadmin' => '1']);

    expect($service->hasAccessToDepartment($admin, 99))->toBeTrue();
});

test('admin has any role in any department', function () {
    $service = app(DepartmentPermissionService::class);
    $admin = makeStaffModel(['isadmin' => '1']);

    expect($service->hasRoleInDepartment($admin, 1, 1))->toBeTrue();
});

test('admin getRoleIdForDepartment returns null', function () {
    $service = app(DepartmentPermissionService::class);
    $admin = makeStaffModel(['isadmin' => '1']);

    expect($service->getRoleIdForDepartment($admin, 1))->toBeNull();
});

test('admin getAccessibleDepartmentIds returns empty array', function () {
    $service = app(DepartmentPermissionService::class);
    $admin = makeStaffModel(['isadmin' => '1']);

    expect($service->getAccessibleDepartmentIds($admin))->toBe([]);
});

test('non-admin without dept access is denied', function () {
    $service = new DepartmentPermissionService();
    $staff = makeStaffModel(['staff_id' => 999]);

    expect($service->hasAccessToDepartment($staff, 1))->toBeFalse();
});
