<?php

namespace App\Models;

/**
 * FaqTopic model for the legacy osTicket ost_faq_topic table.
 *
 * Composite PK: (faq_id, topic_id) — pivot between FAQs and HelpTopics.
 *
 * @property int $faq_id
 * @property int $topic_id
 */
class FaqTopic extends LegacyModel
{
    protected $table = 'faq_topic';

    public $incrementing = false;

    public function faq()
    {
        return $this->belongsTo(Faq::class, 'faq_id', 'faq_id');
    }

    public function helpTopic()
    {
        return $this->belongsTo(HelpTopic::class, 'topic_id', 'topic_id');
    }
}
