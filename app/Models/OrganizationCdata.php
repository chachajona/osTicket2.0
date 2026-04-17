<?php

namespace App\Models;

/**
 * OrganizationCdata model for the legacy osTicket ost_organization__cdata table.
 *
 * Materialized view flattening EAV custom fields for organizations.
 *
 * @property int $org_id
 * @property string $name
 * @property string $address
 * @property string $phone
 * @property string $website
 * @property string $notes
 * @property-read Organization $organization
 */
class OrganizationCdata extends LegacyModel
{
    protected $table = 'organization__cdata';

    protected $primaryKey = 'org_id';

    public $incrementing = false;

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'org_id', 'id');
    }
}
