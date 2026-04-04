<?php

namespace App\Models;

/**
 * Syslog model for the legacy osTicket ost_syslog table.
 *
 * @property int $log_id
 * @property string $log_type
 * @property string $title
 * @property string $log
 * @property string $logger
 * @property string $ip_addr
 * @property string $created
 * @property string $updated
 */
class Syslog extends LegacyModel
{
    protected $table = 'syslog';

    protected $primaryKey = 'log_id';
}
