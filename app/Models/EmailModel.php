<?php

namespace App\Models;

/**
 * EmailModel for the legacy osTicket ost_email table.
 *
 * Named EmailModel to avoid collision with Laravel's Mail/Email utilities.
 * The table name is still 'email' per the legacy schema.
 *
 * @property int $email_id
 * @property int $noautoresp
 * @property int $priority_id
 * @property int $dept_id
 * @property int $topic_id
 * @property string $email
 * @property string $name
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read Department|null $department
 * @property-read EmailAccount|null $account
 */
class EmailModel extends LegacyModel
{
    protected $table = 'email';

    protected $primaryKey = 'email_id';

    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id', 'id');
    }

    public function account()
    {
        return $this->hasOne(EmailAccount::class, 'email_id', 'email_id');
    }
}
