<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * Organization model for the legacy osTicket ost_organization table.
 *
 * @property int $id
 * @property string $name
 * @property string $manager
 * @property int $status
 * @property string $domain
 * @property string $extra
 * @property string $created
 * @property string $updated
 * @property-read OrganizationCdata|null $cdata
 * @property-read Collection<int, LegacyUser> $users
 */
class Organization extends LegacyModel
{
    protected $table = 'organization';

    protected $primaryKey = 'id';

    public function cdata()
    {
        return $this->hasOne(OrganizationCdata::class, 'org_id', 'id');
    }

    public function users()
    {
        return $this->hasMany(LegacyUser::class, 'org_id', 'id');
    }
}
