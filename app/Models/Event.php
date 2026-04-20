<?php

namespace App\Models;

/**
 * Event model for the legacy osTicket ost_event table.
 *
 * @property int $id
 * @property string $name
 * @property string $description
 */
class Event extends LegacyModel
{
    protected $table = 'event';

    protected $primaryKey = 'id';
}
