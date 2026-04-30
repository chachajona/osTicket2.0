<?php

use App\Mail\OutboundMailGuard;
use App\Mail\PasswordResetLinkMail;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Email;

test('outbound mail guard bypasses non-production environments', function () {
    app()->detectEnvironment(fn (): string => 'testing');

    $email = (new Email)
        ->to('customer@example.com')
        ->subject('Customer Reply');

    expect(fn () => app(OutboundMailGuard::class)->handle(new MessageSending($email)))
        ->not->toThrow(RuntimeException::class);
});

test('outbound mail guard allows password reset in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $email = (new Email)
        ->to('staff@example.com')
        ->subject('Reset Your Password');

    expect(fn () => app(OutboundMailGuard::class)->handle(new MessageSending($email, [
        '__laravel_mailable' => PasswordResetLinkMail::class,
    ])))->not->toThrow(RuntimeException::class);
});

test('outbound mail guard does not bypass arbitrary mail by subject', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $email = (new Email)
        ->to('customer@example.com')
        ->subject('Reset Your Password');

    app(OutboundMailGuard::class)->handle(new MessageSending($email));
})->throws(RuntimeException::class, 'Outbound mail is disabled for this phase of the SCP migration.');

test('outbound mail guard blocks customer mail in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $email = (new Email)
        ->to('customer@example.com')
        ->subject('Ticket Updated');

    app(OutboundMailGuard::class)->handle(new MessageSending($email));
})->throws(RuntimeException::class, 'Outbound mail is disabled for this phase of the SCP migration.');
