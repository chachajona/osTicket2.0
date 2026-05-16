<?php

declare(strict_types=1);

namespace App\Mail;

final class EventClassHeader
{
    public const NAME = 'X-Ost-Event-Class';

    public const REPLY = 'reply';

    public const CLOSE_NOTIFY = 'close_notify';

    /**
     * @return list<string>
     */
    public static function known(): array
    {
        return [self::REPLY, self::CLOSE_NOTIFY];
    }
}
