<?php

namespace App\Models;

/**
 * FormField model for the legacy osTicket ost_form_field table.
 *
 * @property int $id
 * @property int $form_id
 * @property int $flags
 * @property string $type
 * @property string $label
 * @property string $name
 * @property string $configuration
 * @property int $sort
 * @property string $hint
 * @property string $created
 * @property string $updated
 * @property-read DynamicForm $form
 */
class FormField extends LegacyModel
{
    protected $table = 'form_field';

    protected $primaryKey = 'id';

    public function form()
    {
        return $this->belongsTo(DynamicForm::class, 'form_id', 'id');
    }
}
