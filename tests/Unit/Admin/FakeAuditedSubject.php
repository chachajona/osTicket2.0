<?php

namespace Tests\Unit\Admin;

use Illuminate\Database\Eloquent\Model;

class FakeAuditedSubject extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'fake_audit_subjects';

    public $timestamps = false;

    protected $guarded = [];

    protected static array $auditExcluded = ['password'];
}
