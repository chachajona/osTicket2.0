<?php

namespace App\Models;

/**
 * FormEntryValues model for the legacy osTicket ost_form_entry_values table.
 *
 * Composite PK: (entry_id, field_id). Stores EAV answers.
 *
 * @property int $entry_id
 * @property int $field_id
 * @property string $value
 * @property int $value_id
 * @property-read FormEntry $entry
 * @property-read FormField $field
 */
class FormEntryValues extends LegacyModel
{
    protected $table = 'form_entry_values';

    public $incrementing = false;

    public function entry()
    {
        return $this->belongsTo(FormEntry::class, 'entry_id', 'id');
    }

    public function field()
    {
        return $this->belongsTo(FormField::class, 'field_id', 'id');
    }

    public function scopeForEntry($query, int $entryId)
    {
        return $query->where('entry_id', $entryId);
    }
}
