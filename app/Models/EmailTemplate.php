<?php

namespace App\Models;

/**
 * EmailTemplate model for the legacy osTicket ost_email_template table.
 *
 * @property int $id
 * @property int $tpl_id
 * @property string $code_name
 * @property string $subject
 * @property string $body
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read EmailTemplateGroup|null $group
 */
class EmailTemplate extends LegacyModel
{
    protected $table = 'email_template';

    protected $primaryKey = 'id';

    public function group()
    {
        return $this->belongsTo(EmailTemplateGroup::class, 'tpl_id', 'tpl_id');
    }
}
