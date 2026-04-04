<?php

namespace App\Models;

/**
 * Ticket model for the legacy osTicket ost_ticket table.
 *
 * @property int         $ticket_id
 * @property string      $number
 * @property int         $user_id
 * @property int         $dept_id
 * @property int         $staff_id
 * @property int         $sla_id
 * @property string      $status
 * @property string      $source
 * @property string      $isoverdue
 * @property string      $isanswered
 * @property string      $duedate
 * @property string      $created
 * @property string      $updated
 * @property string      $lastmessage
 * @property string      $lastresponse
 *
 * @property-read Staff|null      $staff
 * @property-read Department|null $department
 * @property-read Thread|null     $thread
 * @property-read LegacyUser|null $user
 * @property-read TicketCdata|null $cdata
 */
class Ticket extends LegacyModel
{
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

    /**
     * Get the staff member assigned to the ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Staff, $this>
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Department, $this>
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
     * Get the thread associated with the ticket.
     *
     * osTicket uses object_type='T' to distinguish ticket threads from task threads.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<Thread, $this>
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<LegacyUser, $this>
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
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<TicketCdata, $this>
     */
    public function cdata()
    {
        return $this->hasOne(TicketCdata::class, 'ticket_id', 'ticket_id');
    }
}
