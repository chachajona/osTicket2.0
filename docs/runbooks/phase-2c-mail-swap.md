# Phase 2c Outbound Mail Ownership Swap

## Audience
Operator flipping one event class at a time from legacy to Laravel.

## Pre-flight (per class)
- `php artisan test --compact --filter='OutboundMail'` is green.
- Staging deploy already flipped the same env var and lived green for 24 hours.
- Mailbox monitor confirms staging sent exactly one customer email per staff action in canary spot-check.

## Flip procedure (per class)
1. Set the env var in production:
   - Reply: `MAIL_OWNER_REPLY=laravel`
   - Close-with-notify: `MAIL_OWNER_CLOSE_NOTIFY=laravel`
2. Redeploy.
3. Confirm `php artisan config:show mail.event_class_owner` reflects the new value.
4. Spot-check with a real ticket: action in new SCP sends exactly one customer email; legacy SCP does not log a parallel send.

## Rollback (per class)
1. Set the env var back to `legacy`.
2. Redeploy.
3. Confirm:
   - The matching Inertia button disappears from new SCP for the next page load.
   - `php artisan tinker --execute 'app(\App\Services\Scp\Mail\OutboundMailDispatcher::class)->dispatch("reply", "test@example.com", new \Tests\Support\TestMailable);'` throws `LegacyOwnedEventException`.

## What this runbook does not touch
- `ost_config` rows. Legacy continues operating identically for non-canary use.
- Inbound mail piper. Customer reply threading is unaffected.
- Cron and overdue alerts. Those remain in legacy until Task 13.
