<?php

namespace App\Models;

/**
 * UserEmail model for the legacy osTicket ost_user_email table.
 *
 * @property int $id
 * @property int $user_id
 * @property int $flags
 * @property string $address
 * @property-read LegacyUser $user
 */
class UserEmail extends LegacyModel
{
    protected $table = 'user_email';

    protected $primaryKey = 'id';

    public function user()
    {
        return $this->belongsTo(LegacyUser::class, 'user_id', 'id');
    }
}
