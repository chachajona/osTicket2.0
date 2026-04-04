<?php

namespace App\Models;

/**
 * Attachment model for the legacy osTicket ost_attachment table.
 *
 * @property int $id
 * @property int $file_id
 * @property string $object_type
 * @property int $object_id
 * @property string $name
 * @property string $inline
 * @property-read File|null $file
 */
class Attachment extends LegacyModel
{
    protected $table = 'attachment';

    protected $primaryKey = 'id';

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id', 'id');
    }
}
