<?php

namespace App\Models;

/**
 * ListItem model for the legacy osTicket ost_list_items table.
 *
 * @property int $id
 * @property int $list_id
 * @property int $status
 * @property string $value
 * @property string $extra
 * @property int $sort
 * @property string $properties
 * @property-read DynamicList $list
 */
class ListItem extends LegacyModel
{
    protected $table = 'list_items';

    protected $primaryKey = 'id';

    public function list()
    {
        return $this->belongsTo(DynamicList::class, 'list_id', 'id');
    }
}
