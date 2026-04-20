<?php

namespace App\Models;

/**
 * EmailAccount model for the legacy osTicket ost_email_account table.
 *
 * IMAP/POP/SMTP account settings for an email address.
 *
 * @property int $id
 * @property int $email_id
 * @property string $type
 * @property string $auth_bk
 * @property string $auth_id
 * @property int $active
 * @property string $host
 * @property int $port
 * @property string $folder
 * @property string $protocol
 * @property string $encryption
 * @property int $fetchfreq
 * @property int $fetchmax
 * @property string $postfetch
 * @property string $archivefolder
 * @property int $allow_spoofing
 * @property int $num_errors
 * @property string $last_error_msg
 * @property string $last_error
 * @property string $last_activity
 * @property string $created
 * @property string $updated
 * @property-read EmailModel $email
 */
class EmailAccount extends LegacyModel
{
    protected $table = 'email_account';

    protected $primaryKey = 'id';

    public function email()
    {
        return $this->belongsTo(EmailModel::class, 'email_id', 'email_id');
    }
}
