<?php

namespace App\Models;

/**
 * ComplaintResolution model for the legacy osTicket ost_complaint_resolution table.
 *
 * Composite PK: (service_id, complaint_id, resolution_id, source)
 *
 * @property int $service_id
 * @property int $complaint_id
 * @property int $resolution_id
 * @property string $source
 */
class ComplaintResolution extends LegacyModel
{
    protected $table = 'complaint_resolution';

    public $incrementing = false;

    public function scopeForService($query, int $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }

    public function scopeForComplaint($query, int $complaintId)
    {
        return $query->where('complaint_id', $complaintId);
    }
}
