<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * File model for the legacy osTicket ost_file table.
 *
 * @property int $id
 * @property string $type
 * @property int $size
 * @property string $name
 * @property string $key
 * @property string $signature
 * @property string $ft
 * @property string $mime
 * @property string $created
 * @property-read Collection<int, FileChunk> $chunks
 * @property-read Collection<int, Attachment> $attachments
 */
class File extends LegacyModel
{
    protected $table = 'file';

    protected $primaryKey = 'id';

    public function chunks()
    {
        return $this->hasMany(FileChunk::class, 'file_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'file_id', 'id');
    }
}
