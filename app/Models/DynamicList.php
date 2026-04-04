<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * DynamicList model for the legacy osTicket ost_list table.
 *
 * Named DynamicList to avoid collision with PHP's reserved class/function names.
 *
 * @property int $id
 * @property string $name
 * @property string $name_plural
 * @property string $sort_mode
 * @property int $masks
 * @property string $type
 * @property string $configuration
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Collection<int, ListItem> $items
 */
class DynamicList extends LegacyModel
{
    protected $table = 'list';

    protected $primaryKey = 'id';

    public function items()
    {
        return $this->hasMany(ListItem::class, 'list_id', 'id');
    }
}
