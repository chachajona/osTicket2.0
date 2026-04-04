<?php

namespace App\Models;

/**
 * Legacy osTicket end-user from ost_user.
 *
 * @property int    $id
 * @property int    $org_id
 * @property string $default_email_id
 * @property string $name
 * @property string $created
 * @property string $updated
 */
class LegacyUser extends LegacyModel
{
    protected $table = 'user';

    protected $primaryKey = 'id';
}
