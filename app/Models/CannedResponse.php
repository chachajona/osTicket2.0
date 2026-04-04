<?php

namespace App\Models;

/**
 * CannedResponse model for the legacy osTicket ost_canned_response table.
 *
 * @property int $canned_id
 * @property int $dept_id
 * @property int $isenabled
 * @property string $title
 * @property string $response
 * @property string $lang
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Department|null $department
 */
class CannedResponse extends LegacyModel
{
    protected $table = 'canned_response';

    protected $primaryKey = 'canned_id';

    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id', 'id');
    }
}
