<?php

namespace App\Models;

/**
 * TeamMember model for the legacy osTicket ost_team_member table.
 *
 * Composite PK: (team_id, staff_id)
 *
 * @property int $team_id
 * @property int $staff_id
 * @property int $flags
 * @property-read Team  $team
 * @property-read Staff $staff
 */
class TeamMember extends LegacyModel
{
    protected $table = 'team_member';

    public $incrementing = false;

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
