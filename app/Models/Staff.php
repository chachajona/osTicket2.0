<?php

namespace App\Models;

use Illuminate\Support\Facades\Hash;

/**
 * Staff model for the legacy osTicket ost_staff table.
 *
 * @property int    $staff_id
 * @property int    $dept_id
 * @property string $username
 * @property string $firstname
 * @property string $lastname
 * @property string $email
 * @property string $passwd
 * @property string $isactive
 * @property string $isadmin
 * @property string $created
 * @property string $lastlogin
 *
 * @property-read Department|null $department
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $assignedTickets
 */
class Staff extends LegacyModel
{
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Department, $this>
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id', 'id');
    }

    /**
     * Get all tickets assigned to this staff member.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<Ticket, $this>
     */
    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'staff_id', 'staff_id');
    }

    /**
     * Rehash the staff password to bcrypt if it's still using MD5.
     *
     * Called after successful login to transparently upgrade legacy
     * MD5 hashes to bcrypt, mirroring osTicket's check_passwd() behavior.
     *
     * @param  string  $plainPassword
     * @return void
     */
    public function rehashPasswordIfNeeded(string $plainPassword): void
    {
        if (Hash::needsRehash($this->passwd)) {
            $this->passwd = Hash::make($plainPassword);
            $this->save();
        }
    }
}
