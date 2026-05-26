<?php

declare(strict_types=1);

namespace App\Mail;

final class RenderedMail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $bodyHtml,
        public readonly string $bodyText,
    ) {}
}
