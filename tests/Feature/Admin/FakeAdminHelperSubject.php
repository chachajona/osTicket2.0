<?php

namespace Tests\Feature\Admin;

use Illuminate\Database\Eloquent\Model;

class FakeAdminHelperSubject extends Model
{
    protected $connection = 'osticket2';

    protected $table = 'fake_admin_helper_subjects';

    public $timestamps = false;

    protected $guarded = [];
}
