<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * LegacyUser model for the legacy osTicket ost_user table.
 *
 * Distinct from the Laravel App\Models\User (which is the default Laravel auth user).
 * This model maps to osTicket's end-user (ticket submitter) table.
 *
 * @property int $id
 * @property int $default_email_id
 * @property int $org_id
 * @property string $name
 * @property string $created
 * @property string $updated
 * @property-read UserEmail|null  $defaultEmail
 * @property-read Collection<int, UserEmail> $emails
 * @property-read UserAccount|null $account
 * @property-read Organization|null $organization
 * @property-read UserCdata|null $cdata
 * @property-read Collection<int, Ticket> $tickets
 */
class LegacyUser extends LegacyModel
{
    protected $table = 'user';

    protected $primaryKey = 'id';

    public function defaultEmail()
    {
        return $this->belongsTo(UserEmail::class, 'default_email_id', 'id');
    }

    public function emails()
    {
        return $this->hasMany(UserEmail::class, 'user_id', 'id');
    }

    public function account()
    {
        return $this->hasOne(UserAccount::class, 'user_id', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'org_id', 'id');
    }

    public function cdata()
    {
        return $this->hasOne(UserCdata::class, 'user_id', 'id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'user_id', 'id');
    }
}
