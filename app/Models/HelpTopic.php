<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * HelpTopic model for the legacy osTicket ost_help_topic table.
 *
 * @property int $topic_id
 * @property int $topic_pid
 * @property int $ispublic
 * @property int $noautoresp
 * @property int $flags
 * @property int $status_id
 * @property int $priority_id
 * @property int $dept_id
 * @property int $staff_id
 * @property int $team_id
 * @property int $sla_id
 * @property int $page_id
 * @property int $sequence_id
 * @property int $sort
 * @property string $topic
 * @property string $number_format
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read HelpTopic|null   $parent
 * @property-read Department|null  $department
 * @property-read Staff|null       $staff
 * @property-read Team|null        $team
 * @property-read Sla|null         $sla
 * @property-read Collection<int, Faq> $faqs
 */
class HelpTopic extends LegacyModel
{
    protected $table = 'help_topic';

    protected $primaryKey = 'topic_id';

    public function parent()
    {
        return $this->belongsTo(HelpTopic::class, 'topic_pid', 'topic_id');
    }

    public function children()
    {
        return $this->hasMany(HelpTopic::class, 'topic_pid', 'topic_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'dept_id', 'id');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'staff_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function sla()
    {
        return $this->belongsTo(Sla::class, 'sla_id', 'id');
    }

    public function faqs()
    {
        return $this->belongsToMany(Faq::class, 'faq_topic', 'topic_id', 'faq_id');
    }
}
