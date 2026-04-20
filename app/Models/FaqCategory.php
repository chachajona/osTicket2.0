<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * FaqCategory model for the legacy osTicket ost_faq_category table.
 *
 * @property int $category_id
 * @property int $category_pid
 * @property int $ispublic
 * @property string $name
 * @property string $description
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read FaqCategory|null $parent
 * @property-read Collection<int, FaqCategory> $children
 * @property-read Collection<int, Faq> $faqs
 */
class FaqCategory extends LegacyModel
{
    protected $table = 'faq_category';

    protected $primaryKey = 'category_id';

    public function parent()
    {
        return $this->belongsTo(FaqCategory::class, 'category_pid', 'category_id');
    }

    public function children()
    {
        return $this->hasMany(FaqCategory::class, 'category_pid', 'category_id');
    }

    public function faqs()
    {
        return $this->hasMany(Faq::class, 'category_id', 'category_id');
    }
}
