<?php

namespace App\Models;

/**
 * Sequence model for the legacy osTicket ost_sequence table.
 *
 * @property int $id
 * @property string $name
 * @property int $next
 * @property int $increment
 * @property int $padding
 */
class Sequence extends LegacyModel
{
    protected $table = 'sequence';

    protected $primaryKey = 'id';
}
