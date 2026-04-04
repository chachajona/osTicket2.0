<?php

namespace App\Models;

/**
 * UserCdata model for the legacy osTicket ost_user__cdata table.
 *
 * Materialized view flattening EAV custom fields for users.
 *
 * @property int $user_id
 * @property string $email
 * @property string $name
 * @property string $phone
 * @property string $ewallet
 * @property string $notes
 * @property-read LegacyUser $user
 */
class UserCdata extends LegacyModel
{
    protected $table = 'user__cdata';

    protected $primaryKey = 'user_id';

    public function user()
    {
        return $this->belongsTo(LegacyUser::class, 'user_id', 'id');
    }
}
