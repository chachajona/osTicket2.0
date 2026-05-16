<?php

declare(strict_types=1);

namespace App\Exceptions\Scp;

use RuntimeException;

final class LegacyOwnedEventException extends RuntimeException
{
    public function __construct(public readonly string $eventClass)
    {
        parent::__construct(
            sprintf('Event class "%s" is owned by legacy osTicket; Laravel must not dispatch mail for it.', $eventClass),
        );
    }
}
