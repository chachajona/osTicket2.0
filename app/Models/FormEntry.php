<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * FormEntry model for the legacy osTicket ost_form_entry table.
 *
 * Links a form to an object (ticket, user, etc.) via polymorphic object_type.
 * object_type='T' for ticket, 'U' for user, 'O' for organization.
 *
 * @property int $id
 * @property int $form_id
 * @property int $object_id
 * @property string $object_type
 * @property int $sort
 * @property string $extra
 * @property string $created
 * @property string $updated
 * @property-read DynamicForm $form
 * @property-read Collection<int, FormEntryValues> $values
 */
class FormEntry extends LegacyModel
{
    protected $table = 'form_entry';

    protected $primaryKey = 'id';

    public function form()
    {
        return $this->belongsTo(DynamicForm::class, 'form_id', 'id');
    }

    public function values()
    {
        return $this->hasMany(FormEntryValues::class, 'entry_id', 'id');
    }
}
