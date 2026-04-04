<?php

namespace App\Models;

/**
 * FilterRule model for the legacy osTicket ost_filter_rule table.
 *
 * @property int $id
 * @property int $filter_id
 * @property string $what
 * @property string $how
 * @property string $val
 * @property int $isactive
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Filter $filter
 */
class FilterRule extends LegacyModel
{
    protected $table = 'filter_rule';

    protected $primaryKey = 'id';

    public function filter()
    {
        return $this->belongsTo(Filter::class, 'filter_id', 'id');
    }
}
