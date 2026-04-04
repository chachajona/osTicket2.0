<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * DynamicForm model for the legacy osTicket ost_form table.
 *
 * @property int $id
 * @property int $pid
 * @property string $type
 * @property int $flags
 * @property string $title
 * @property string $instructions
 * @property string $name
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Collection<int, FormField> $fields
 * @property-read Collection<int, FormEntry> $entries
 */
class DynamicForm extends LegacyModel
{
    protected $table = 'form';

    protected $primaryKey = 'id';

    public function fields()
    {
        return $this->hasMany(FormField::class, 'form_id', 'id')->orderBy('sort');
    }

    public function entries()
    {
        return $this->hasMany(FormEntry::class, 'form_id', 'id');
    }
}
