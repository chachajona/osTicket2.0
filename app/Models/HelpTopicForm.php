<?php

namespace App\Models;

/**
 * HelpTopicForm model for the legacy osTicket ost_help_topic_form table.
 *
 * @property int $id
 * @property int $topic_id
 * @property int $form_id
 * @property int $sort
 * @property string $extra
 * @property-read HelpTopic  $helpTopic
 * @property-read DynamicForm $form
 */
class HelpTopicForm extends LegacyModel
{
    protected $table = 'help_topic_form';

    protected $primaryKey = 'id';

    public function helpTopic()
    {
        return $this->belongsTo(HelpTopic::class, 'topic_id', 'topic_id');
    }

    public function form()
    {
        return $this->belongsTo(DynamicForm::class, 'form_id', 'id');
    }
}
