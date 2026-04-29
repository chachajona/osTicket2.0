<?php

namespace App\Mail;

use Illuminate\Mail\Events\MessageSending;
use RuntimeException;
use Symfony\Component\Mime\Address;

class OutboundMailGuard
{
    public function handle(MessageSending $event): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if ($this->isPasswordReset($event)) {
            return;
        }

        if ($this->allRecipientsExplicitlyAllowed($event)) {
            return;
        }

        throw new RuntimeException('Outbound mail is disabled for this phase of the SCP migration.');
    }

    private function isPasswordReset(MessageSending $event): bool
    {
        return trim((string) $event->message->getSubject()) === 'Reset Your Password';
    }

    private function allRecipientsExplicitlyAllowed(MessageSending $event): bool
    {
        $allowed = collect(config('mail.outbound_guard.allowed_recipients', []))
            ->map(fn (string $email): string => strtolower($email))
            ->all();

        if ($allowed === []) {
            return false;
        }

        $recipients = array_merge(
            $event->message->getTo(),
            $event->message->getCc(),
            $event->message->getBcc(),
        );

        if ($recipients === []) {
            return false;
        }

        return collect($recipients)
            ->map(fn (Address $address): string => strtolower($address->getAddress()))
            ->every(fn (string $email): bool => in_array($email, $allowed, true));
    }
}
