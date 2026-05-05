<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Department model for the legacy osTicket ost_department table.
 *
 * @property int $dept_id
 * @property int $tpl_id
 * @property int $sla_id
 * @property int $manager_id
 * @property string $name
 * @property string $signature
 * @property string $ispublic
 */
class Department extends LegacyModel
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'department';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * @return BelongsTo<Sla, $this>
     */
    public function sla(): BelongsTo
    {
        return $this->belongsTo(Sla::class, 'sla_id', 'id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'manager_id', 'staff_id');
    }

    /**
     * @return BelongsTo<EmailModel, $this>
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(EmailModel::class, 'email_id', 'email_id');
    }

    /**
     * @return BelongsTo<EmailTemplateGroup, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplateGroup::class, 'tpl_id', 'tpl_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'dept_id', 'id');
    }
}
