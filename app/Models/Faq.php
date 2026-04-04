<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;

/**
 * Faq model for the legacy osTicket ost_faq table.
 *
 * @property int $faq_id
 * @property int $category_id
 * @property int $ispublished
 * @property string $question
 * @property string $answer
 * @property string $keywords
 * @property string $notes
 * @property string $created
 * @property string $updated
 * @property-read FaqCategory|null $category
 * @property-read Collection<int, HelpTopic> $helpTopics
 */
class Faq extends LegacyModel
{
    protected $table = 'faq';

    protected $primaryKey = 'faq_id';

    public function category()
    {
        return $this->belongsTo(FaqCategory::class, 'category_id', 'category_id');
    }

    public function helpTopics()
    {
        return $this->belongsToMany(HelpTopic::class, 'faq_topic', 'faq_id', 'topic_id');
    }
}
