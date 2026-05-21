<?php

namespace App\Models;

use App\Support\LegacyMysqlText;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ThreadEntry model for the legacy osTicket ost_thread_entry table.
 *
 * Represents individual messages, responses, and notes within a thread.
 *
 * @property int $id
 * @property int $thread_id
 * @property int $staff_id
 * @property string $type
 * @property string $body
 * @property string $format
 * @property string $created
 * @property string $updated
 * @property-read Thread     $thread
 * @property-read Staff|null $staff
 */
class ThreadEntry extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'thread_entry';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    public function setBodyAttribute(?string $value): void
    {
        $this->attributes['body'] = $this->legacyMysqlText($value);
    }

    public function setPosterAttribute(?string $value): void
    {
        $this->attributes['poster'] = $this->legacyMysqlText($value);
    }

    public function setSourceAttribute(?string $value): void
    {
        $this->attributes['source'] = $this->legacyMysqlText($value);
    }

    public function setTitleAttribute(?string $value): void
    {
        $this->attributes['title'] = $this->legacyMysqlText($value);
    }

    /**
     * Get the thread that this entry belongs to.
     *
     * @return BelongsTo<Thread, $this>
     */
    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'id');
    }

    /**
     * Get the staff member who created this entry.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    private function legacyMysqlText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return LegacyMysqlText::stripUnsupportedUtf8mb3Characters($value);
    }
}
