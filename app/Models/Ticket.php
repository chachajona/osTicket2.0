<?php

namespace App\Models;

use App\Models\Eloquent\Scopes\TicketAccessibleScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ticket model for the legacy osTicket ost_ticket table.
 *
 * @property int $ticket_id
 * @property string $number
 * @property int $user_id
 * @property int $status_id
 * @property int $dept_id
 * @property int $staff_id
 * @property int $sla_id
 * @property int $email_id
 * @property string $source
 * @property string $ip_address
 * @property int $isoverdue
 * @property int $isanswered
 * @property string|null $duedate
 * @property string|null $closed
 * @property string $lastupdate
 * @property string $lastmessage
 * @property string $lastresponse
 * @property string $created
 * @property string $updated
 * @property-read Staff|null      $staff
 * @property-read Department|null $department
 * @property-read TicketStatus|null $status
 * @property-read Thread|null     $thread
 * @property-read LegacyUser|null $user
 * @property-read TicketCdata|null $cdata
 */
class Ticket extends LegacyModel
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'ticket_id';

    protected static function booted(): void
    {
        static::addGlobalScope(new TicketAccessibleScope);
    }

    /**
     * Get the staff member assigned to the ticket.
     *
     * @return BelongsTo<Staff, $this>
     */
    public function staff()
    {
        return $this->belongsTo(
            Staff::class,
            'staff_id',
            'staff_id'
        );
    }

    /**
     * Get the department the ticket belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department()
    {
        return $this->belongsTo(
            Department::class,
            'dept_id',
            'id'
        );
    }

    /**
     * Get the ticket status.
     *
     * @return BelongsTo<TicketStatus, $this>
     */
    public function status()
    {
        return $this->belongsTo(TicketStatus::class, 'status_id', 'id');
    }

    /**
     * Get the thread associated with the ticket.
     *
     * osTicket uses object_type='T' to distinguish ticket threads from task threads.
     *
     * @return HasOne<Thread, $this>
     */
    public function thread()
    {
        return $this->hasOne(
            Thread::class,
            'object_id',
            'ticket_id'
        )->where('object_type', 'T');
    }

    /**
     * Get the user who created the ticket.
     *
     * @return BelongsTo<LegacyUser, $this>
     */
    public function user()
    {
        return $this->belongsTo(
            LegacyUser::class,
            'user_id',
            'id'
        );
    }

    /**
     * Get the ticket's custom field data from ost_ticket__cdata.
     *
     * @return HasOne<TicketCdata, $this>
     */
    public function cdata()
    {
        return $this->hasOne(TicketCdata::class, 'ticket_id', 'ticket_id');
    }
}
