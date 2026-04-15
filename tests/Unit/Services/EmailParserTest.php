<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EmailParser;
use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Message;

final class EmailParserTest extends TestCase
{
    public function test_detect_bounce_does_not_treat_common_outlook_suppression_values_as_bounce(): void
    {
        $message = Message::fromString(
            "From: Sender <sender@example.test>\r\n"
            ."X-Auto-Response-Suppress: OOF, DR, RN, NRN, AutoReply\r\n\r\n"
            .'Body'
        );

        self::assertFalse((new EmailParser)->detectBounce($message));
    }

    public function test_detect_bounce_still_treats_suppress_all_as_bounce_indicator(): void
    {
        $message = Message::fromString(
            "From: Microsoft Exchange <system@example.test>\r\n"
            ."X-Auto-Response-Suppress: All\r\n\r\n"
            .'Body'
        );

        self::assertTrue((new EmailParser)->detectBounce($message));
    }
}
