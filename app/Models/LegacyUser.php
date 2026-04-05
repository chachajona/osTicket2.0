<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Legacy osTicket end-user from ost_user.
 *
 * @property int    $id
 * @property int    $org_id
 * @property int    $default_email_id
 * @property string $name
 * @property string $created
 * @property string $updated
 * @property-read UserEmail|null $defaultEmail
 * @property-read \Illuminate\Database\Eloquent\Collection<int, UserEmail> $emails
 * @property-read UserAccount|null $account
 * @property-read UserCdata|null $cdata
 * @property-read Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets
 */
class LegacyUser extends LegacyModel
{
    protected $table = 'user';

    protected $primaryKey = 'id';

    public function defaultEmail(): HasOne
    {
        return $this->hasOne(UserEmail::class, 'id', 'default_email_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(UserEmail::class, 'user_id', 'id');
    }

    public function account(): HasOne
    {
        return $this->hasOne(UserAccount::class, 'user_id', 'id');
    }

    public function cdata(): HasOne
    {
        return $this->hasOne(UserCdata::class, 'user_id', 'id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id', 'id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'user_id', 'id');
    }
}
