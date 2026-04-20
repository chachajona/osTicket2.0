<?php

namespace App\Models;

/**
 * Search model for the legacy osTicket ost__search table.
 * Composite primary key: (object_type, object_id).
 *
 * @property string $object_type
 * @property int $object_id
 * @property string $title
 * @property string $content
 */
class Search extends LegacyModel
{
    protected $table = '_search';

    public $incrementing = false;
}
