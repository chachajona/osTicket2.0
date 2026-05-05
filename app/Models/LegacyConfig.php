<?php

namespace App\Models;

class LegacyConfig extends LegacyModel
{
    protected $table = 'config';
    protected $primaryKey = 'id';
    public $timestamps = false;
}
