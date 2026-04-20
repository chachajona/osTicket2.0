<?php

namespace App\Models;

use App\Auth\StaffTwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Traits\HasRoles;

/**
 * Staff model for the legacy osTicket ost_staff table.
 *
 * Implements Authenticatable so Laravel's auth guards can work with
 * legacy staff records. Maps staff_id as the auth identifier and
 * passwd as the auth password column.
 *
 * @property int $staff_id
 * @property int $dept_id
 * @property string $username
 * @property string $firstname
 * @property string $lastname
 * @property string $email
 * @property string $passwd
 * @property string $isactive
 * @property string $isadmin
 * @property string $created
 * @property string $lastlogin
 * @property-read Department|null $department
 * @property-read Collection<int, Ticket> $assignedTickets
 */
class Staff extends LegacyModel implements Authenticatable
{
    use HasRoles;
    use StaffTwoFactorAuthenticatable;

    private ?string $rememberToken = null;

    /**
     * The guard name for spatie/laravel-permission.
     */
    protected string $guard_name = 'staff';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'staff';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'staff_id';

    /**
     * Get the department this staff member belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id', 'id');
    }

    /**
     * Get all tickets assigned to this staff member.
     *
     * @return HasMany<Ticket, $this>
     */
    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'staff_id', 'staff_id');
    }

    /**
     * @return HasOne<StaffAuthMigration, $this>
     */
    public function authMigration(): HasOne
    {
        return $this->hasOne(StaffAuthMigration::class, 'staff_id', 'staff_id');
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthIdentifierName(): string
    {
        return 'staff_id';
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->staff_id;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthPassword(): string
    {
        return $this->passwd;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthPasswordName(): string
    {
        return 'passwd';
    }

    /**
     * {@inheritdoc}
     */
    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    /**
     * {@inheritdoc}
     */
    public function setRememberToken($value): void
    {
        $this->rememberToken = is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Rehash the staff password to bcrypt if it's still using MD5.
     *
     * Called after successful login to transparently upgrade legacy
     * MD5 hashes to bcrypt, mirroring osTicket's check_passwd() behavior.
     */
    public function rehashPasswordIfNeeded(string $plainPassword): void
    {
        if (Hash::needsRehash($this->passwd)) {
            $this->passwd = Hash::make($plainPassword);
            $this->save();
        }
    }

    public function hasTotpEnabled(): bool
    {
        return $this->hasEnabledTwoFactorAuthentication();
    }

    public function isMigrated(): bool
    {
        return ! is_null($this->loadMissing('authMigration')->authMigration?->migrated_at);
    }
}
