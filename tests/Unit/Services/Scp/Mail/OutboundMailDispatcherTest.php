<?php

declare(strict_types=1);

use App\Exceptions\Scp\LegacyOwnedEventException;
use App\Mail\EventClassHeader;
use App\Services\Scp\Mail\OutboundMailDispatcher;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\Support\TestMailable;

beforeEach(function (): void {
    Mail::fake();
});

it('throws when the event class is legacy-owned', function (): void {
    config(['mail.event_class_owner.reply' => 'legacy']);

    expect(fn () => app(OutboundMailDispatcher::class)
        ->dispatch(EventClassHeader::REPLY, 'customer@example.com', new TestMailable))
        ->toThrow(LegacyOwnedEventException::class);

    Mail::assertNothingSent();
});

it('sends the mail with marker header when laravel-owned', function (): void {
    config(['mail.event_class_owner.reply' => 'laravel']);

    app(OutboundMailDispatcher::class)
        ->dispatch(EventClassHeader::REPLY, 'customer@example.com', new TestMailable);

    Mail::assertSent(TestMailable::class, function (TestMailable $mail): bool {
        $message = new Email;

        foreach ($mail->callbacks as $callback) {
            $callback($message);
        }

        return in_array('customer@example.com', collect($mail->to)->pluck('address')->all(), true)
            && $message->getHeaders()->get(EventClassHeader::NAME)?->getBodyAsString() === EventClassHeader::REPLY;
    });
});

it('throws on unknown event class even when laravel-owned', function (): void {
    config(['mail.event_class_owner.bogus' => 'laravel']);

    expect(fn () => app(OutboundMailDispatcher::class)
        ->dispatch('bogus', 'customer@example.com', new TestMailable))
        ->toThrow(InvalidArgumentException::class);
});
