<?php

namespace App\Models;

/**
 * FilterAction model for the legacy osTicket ost_filter_action table.
 *
 * @property int $id
 * @property int $filter_id
 * @property int $sort
 * @property string $type
 * @property string $configuration
 * @property string $updated
 * @property-read Filter $filter
 */
class FilterAction extends LegacyModel
{
    protected $table = 'filter_action';

    protected $primaryKey = 'id';

    public function filter()
    {
        return $this->belongsTo(Filter::class, 'filter_id', 'id');
    }
}
