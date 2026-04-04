<?php

namespace App\Models;

/**
 * Department model for the legacy osTicket ost_department table.
 *
 * @property int    $dept_id
 * @property int    $tpl_id
 * @property int    $sla_id
 * @property int    $manager_id
 * @property string $name
 * @property string $signature
 * @property string $ispublic
 */
class Department extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'department';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
}
