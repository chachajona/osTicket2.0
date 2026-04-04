<?php

namespace App\Models;

/**
 * FileChunk model for the legacy osTicket ost_file_chunk table.
 * Composite primary key: (file_id, chunk_id).
 *
 * @property int $file_id
 * @property int $chunk_id
 * @property string $filedata
 * @property-read File|null $file
 */
class FileChunk extends LegacyModel
{
    protected $table = 'file_chunk';

    public $incrementing = false;

    public function file()
    {
        return $this->belongsTo(File::class, 'file_id', 'id');
    }
}
