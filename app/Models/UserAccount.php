<?php

namespace App\Models;

/**
 * UserAccount model for the legacy osTicket ost_user_account table.
 *
 * Portal login account linked to a legacy user.
 *
 * @property int $id
 * @property int $user_id
 * @property int $status
 * @property string $timezone
 * @property string $lang
 * @property string $username
 * @property string $passwd
 * @property string $backend
 * @property string $extra
 * @property string $registered
 * @property-read LegacyUser $user
 */
class UserAccount extends LegacyModel
{
    protected $table = 'user_account';

    protected $primaryKey = 'id';

    public function user()
    {
        return $this->belongsTo(LegacyUser::class, 'user_id', 'id');
    }
}
