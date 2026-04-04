<?php

namespace App\Models;

/**
 * Translation model for the legacy osTicket ost_translation table.
 *
 * @property int $id
 * @property string $lang
 * @property string $msgid
 * @property string $msgid_plural
 * @property string $msgstr
 * @property string $flags
 * @property string $created
 * @property string $updated
 */
class Translation extends LegacyModel
{
    protected $table = 'translation';

    protected $primaryKey = 'id';
}
