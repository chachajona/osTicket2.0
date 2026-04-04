<?php

namespace App\Models;

/**
 * StaffDeptAccess model for the legacy osTicket ost_staff_dept_access table.
 *
 * Composite PK: (staff_id, dept_id)
 *
 * @property int $staff_id
 * @property int $dept_id
 * @property int $role_id
 * @property int $flags
 * @property-read Staff      $staff
 * @property-read Department $department
 * @property-read Role|null  $role
 */
class StaffDeptAccess extends LegacyModel
{
    protected $table = 'staff_dept_access';

    public $incrementing = false;

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id', 'id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function scopeForStaff($query, int $staffId)
    {
        return $query->where('staff_id', $staffId);
    }
}
