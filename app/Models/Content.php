<?php

namespace App\Models;

/**
 * Content model for the legacy osTicket ost_content table.
 *
 * @property int $id
 * @property int $isactive
 * @property string $type
 * @property string $name
 * @property string $body
 * @property string $notes
 * @property string $created
 * @property string $updated
 */
class Content extends LegacyModel
{
    protected $table = 'content';

    protected $primaryKey = 'id';
}
