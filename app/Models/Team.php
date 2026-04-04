<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * Team model for the legacy osTicket ost_team table.
 *
 * @property int $team_id
 * @property int $lead_id
 * @property int $flags
 * @property string $name
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Staff|null $lead
 * @property-read Collection<int, Staff> $members
 */
class Team extends LegacyModel
{
    protected $table = 'team';

    protected $primaryKey = 'team_id';

    public function lead()
    {
        return $this->belongsTo(Staff::class, 'lead_id', 'staff_id');
    }

    public function members()
    {
        return $this->belongsToMany(Staff::class, 'team_member', 'team_id', 'staff_id')
            ->withPivot('flags');
    }
}
