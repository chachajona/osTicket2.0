<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * Filter model for the legacy osTicket ost_filter table.
 *
 * @property int $id
 * @property int $execorder
 * @property int $isactive
 * @property int $flags
 * @property int $status
 * @property int $match_all_rules
 * @property int $stop_onmatch
 * @property string $target
 * @property int $email_id
 * @property string $name
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Collection<int, FilterRule> $rules
 * @property-read Collection<int, FilterAction> $actions
 */
class Filter extends LegacyModel
{
    protected $table = 'filter';

    protected $primaryKey = 'id';

    public function rules()
    {
        return $this->hasMany(FilterRule::class, 'filter_id', 'id');
    }

    public function actions()
    {
        return $this->hasMany(FilterAction::class, 'filter_id', 'id');
    }
}
