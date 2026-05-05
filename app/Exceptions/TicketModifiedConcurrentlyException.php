<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class TicketModifiedConcurrentlyException extends RuntimeException
{
    public function __construct(
        public readonly int $ticketId,
        public readonly string $currentUpdated,
    ) {
        parent::__construct(
            message: "Ticket {$ticketId} was modified concurrently (now {$currentUpdated})"
        );
    }

    public function render(): JsonResponse
    {
        return response()->json(
            data: [
                'message' => 'Ticket was modified since you opened it. Please reload.',
                'current_updated' => $this->currentUpdated,
            ],
            status: 409,
        );
    }
}
