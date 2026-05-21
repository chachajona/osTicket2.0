<?php

declare(strict_types=1);

namespace App\Exceptions\Scp;

use RuntimeException;

final class LegacyTemplateNotFoundException extends RuntimeException
{
    public function __construct(public readonly string $codeName, public readonly int $tplId)
    {
        parent::__construct(sprintf(
            'Legacy email template "%s" not found in template group %d or system default.',
            $codeName,
            $tplId,
        ));
    }
}
