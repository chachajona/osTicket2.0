<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * EmailTemplateGroup model for the legacy osTicket ost_email_template_group table.
 *
 * @property int $tpl_id
 * @property int $isactive
 * @property string $name
 * @property string $lang
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Collection<int, EmailTemplate> $templates
 */
class EmailTemplateGroup extends LegacyModel
{
    protected $table = 'email_template_group';

    protected $primaryKey = 'tpl_id';

    public function templates()
    {
        return $this->hasMany(EmailTemplate::class, 'tpl_id', 'tpl_id');
    }
}
