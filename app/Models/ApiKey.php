<?php

namespace App\Models;

/**
 * ApiKey model for the legacy osTicket ost_api_key table.
 *
 * @property int $id
 * @property int $isactive
 * @property int $can_create_tickets
 * @property int $can_exec_cron
 * @property string $ipaddr
 * @property string $apikey
 * @property string $note
 * @property string $created
 * @property string $updated
 */
class ApiKey extends LegacyModel
{
    protected $table = 'api_key';

    protected $primaryKey = 'id';
}
