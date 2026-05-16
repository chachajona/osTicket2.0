<?php

declare(strict_types=1);

namespace App\Services\Scp\Mail;

use App\Exceptions\Scp\LegacyOwnedEventException;
use App\Mail\EventClassHeader;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Symfony\Component\Mime\Email;

class OutboundMailDispatcher
{
    public function dispatch(string $eventClass, string $recipient, Mailable $mailable): void
    {
        if (! in_array($eventClass, EventClassHeader::known(), true)) {
            throw new InvalidArgumentException(sprintf('Unknown event class "%s".', $eventClass));
        }

        $owner = (string) config("mail.event_class_owner.{$eventClass}", 'legacy');

        if ($owner !== 'laravel') {
            throw new LegacyOwnedEventException($eventClass);
        }

        $mailable->withSymfonyMessage(function (Email $message) use ($eventClass): void {
            $message->getHeaders()->addTextHeader(EventClassHeader::NAME, $eventClass);
        });

        Mail::to($recipient)->send($mailable);
    }
}
