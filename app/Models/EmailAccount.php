<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Crypt;
use Throwable;

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

    /**
     * @var list<string>
     */
    protected $auditExcluded = ['auth_id', 'auth_bk', 'username', 'password'];

    protected function authId(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): string => $this->decryptCredential($value),
            set: fn (mixed $value): ?string => $this->encryptCredential($value),
        );
    }

    protected function authBk(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): string => $this->decryptCredential($value),
            set: fn (mixed $value): ?string => $this->encryptCredential($value),
        );
    }

    public function email()
    {
        return $this->belongsTo(EmailModel::class, 'email_id', 'email_id');
    }

    private function encryptCredential(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        if ($normalized === '') {
            return null;
        }

        return 'enc:'.Crypt::encryptString($normalized);
    }

    private function decryptCredential(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        if (! str_starts_with($value, 'enc:')) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, 4));
        } catch (Throwable) {
            return $value;
        }
    }
}
