<?php

namespace App\Models;

/**
 * FormMapping model for the legacy osTicket ost_form_mapping table.
 *
 * Composite PK: (service_id, complaint_id, reason_id, source).
 *
 * @property int $service_id
 * @property int $complaint_id
 * @property int $reason_id
 * @property string $source
 */
class FormMapping extends LegacyModel
{
    protected $table = 'form_mapping';

    public $incrementing = false;

    public function scopeForService($query, int $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }
}
