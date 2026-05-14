# Task Scheduling Best Practices

## Use `withoutOverlapping()` on Variable-Duration Tasks

Without it, a long-running task spawns a second instance on the next tick, causing double-processing or resource exhaustion.

## Use `onOneServer()` on Multi-Server Deployments

Without it, every server runs the same task simultaneously. Requires a shared cache driver (Redis, database, Memcached).

## Use `runInBackground()` for Concurrent Long Tasks

By default, tasks at the same tick run sequentially. A slow first task delays all subsequent ones. `runInBackground()` runs them as separate processes.

## Use `environments()` to Restrict Tasks

Prevent accidental execution of production-only tasks (billing, reporting) on staging.

```php
Schedule::command('billing:charge')->monthly()->environments(['production']);
```

## Use Overlap Guards and Job Timeouts for Bounded Processing

A task running every 15 minutes that processes an unbounded cursor can overlap
with the next run. Use `withoutOverlapping()` to prevent concurrent runs on one
machine, add `onOneServer()` for multi-server deployments, or move the work to
queued jobs with explicit timeout settings.

## Use Schedule Groups for Shared Configuration

Avoid repeating `->onOneServer()->timezone('America/New_York')` across many tasks.

```php
Schedule::daily()
    ->onOneServer()
    ->timezone('America/New_York')
    ->group(function () {
        Schedule::command('emails:send --force');
        Schedule::command('emails:prune');
    });
```
