<?php

declare(strict_types=1);

namespace App\Services\Scp\Tickets;

use App\Models\Event;
use App\Models\Staff;
use App\Models\Thread;
use App\Models\ThreadEvent;
use InvalidArgumentException;

final class ThreadEventWriter
{
    private const KNOWN_EVENTS = [
        'assigned' => 'Ticket assigned',
        'created' => 'Thread entry created',
        'released' => 'Ticket assignment released',
        'status' => 'Ticket status changed',
    ];

    private array $eventCache = [];

    public function record(
        Thread $thread,
        string $eventName,
        ?int $entryId, // Reserved for future use (e.g., linking to thread entry)
        Staff $staff,
        array $data = []
    ): ThreadEvent {
        $eventId = $this->resolveEventId($eventName);

        return ThreadEvent::on('legacy')->create([
            'thread_id' => $thread->id,
            'thread_type' => $thread->object_type,
            'event_id' => $eventId,
            'staff_id' => $staff->staff_id,
            'team_id' => 0,
            'dept_id' => 0,
            'topic_id' => 0,
            'data' => json_encode($data),
            'username' => $staff->displayName(),
            'uid' => $staff->staff_id,
            'uid_type' => 'S',
            'annulled' => 0,
            'timestamp' => now(),
        ]);
    }

    private function resolveEventId(string $eventName): int
    {
        if (isset($this->eventCache[$eventName])) {
            return $this->eventCache[$eventName];
        }

        $event = Event::on('legacy')->firstOrNew(['name' => $eventName]);

        if (! $event->exists && ! array_key_exists($eventName, self::KNOWN_EVENTS)) {
            throw new InvalidArgumentException("Unknown event name: {$eventName}");
        }

        if (! $event->exists) {
            $event->description = self::KNOWN_EVENTS[$eventName];
            $event->save();
        }

        $this->eventCache[$eventName] = (int) $event->id;

        return (int) $event->id;
    }
}
