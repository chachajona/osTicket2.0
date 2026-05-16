<?php

declare(strict_types=1);

use App\Exceptions\Scp\LegacyOwnedEventException;
use App\Mail\EventClassHeader;
use App\Mail\OutboundMailGuard;
use App\Services\Scp\Mail\OutboundMailDispatcher;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\Support\TestMailable;

it('defaults all event classes to legacy ownership', function (): void {
    expect(config('mail.event_class_owner.reply'))->toBe('legacy')
        ->and(config('mail.event_class_owner.close_notify'))->toBe('legacy');
});

it('reads ownership overrides from env', function (): void {
    config(['mail.event_class_owner.reply' => 'laravel']);

    expect(config('mail.event_class_owner.reply'))->toBe('laravel')
        ->and(config('mail.event_class_owner.close_notify'))->toBe('legacy');
});

it('guard allows marker-tagged mail when class is laravel-owned in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['mail.event_class_owner.reply' => 'laravel']);

    $message = (new Email)
        ->from('staff@example.com')
        ->to('customer@example.com')
        ->subject('Re: ticket')
        ->html('<p>hi</p>');
    $message->getHeaders()->addTextHeader(EventClassHeader::NAME, EventClassHeader::REPLY);

    expect(fn () => app(OutboundMailGuard::class)->handle(new MessageSending($message)))
        ->not->toThrow(RuntimeException::class);
});

it('guard throws on marker-tagged mail when class is legacy-owned in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['mail.event_class_owner.reply' => 'legacy']);

    $message = (new Email)
        ->from('staff@example.com')
        ->to('customer@example.com')
        ->subject('Re: ticket')
        ->html('<p>hi</p>');
    $message->getHeaders()->addTextHeader(EventClassHeader::NAME, EventClassHeader::REPLY);

    expect(fn () => app(OutboundMailGuard::class)->handle(new MessageSending($message)))
        ->toThrow(RuntimeException::class);
});

it('guard throws on unmarked mail in production even when a class is laravel-owned', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['mail.event_class_owner.reply' => 'laravel']);

    $message = (new Email)
        ->from('staff@example.com')
        ->to('customer@example.com')
        ->subject('Re: ticket')
        ->html('<p>hi</p>');

    expect(fn () => app(OutboundMailGuard::class)->handle(new MessageSending($message)))
        ->toThrow(RuntimeException::class);
});

it('flipping one event class does not enable another', function (): void {
    Mail::fake();
    config([
        'mail.event_class_owner.reply' => 'laravel',
        'mail.event_class_owner.close_notify' => 'legacy',
    ]);

    app(OutboundMailDispatcher::class)
        ->dispatch(EventClassHeader::REPLY, 'customer@example.com', new TestMailable);

    expect(fn () => app(OutboundMailDispatcher::class)
        ->dispatch(EventClassHeader::CLOSE_NOTIFY, 'customer@example.com', new TestMailable))
        ->toThrow(LegacyOwnedEventException::class);

    Mail::assertSent(TestMailable::class, 1);
});
