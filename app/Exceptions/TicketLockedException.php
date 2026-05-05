<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class TicketLockedException extends RuntimeException
{
    public function __construct(
        public readonly int $ticketId,
        public readonly int $heldByStaffId,
        public readonly string $expiresAt,
    ) {
        parent::__construct(
            message: "Ticket {$ticketId} is locked by staff {$heldByStaffId} until {$expiresAt}"
        );
    }

    public function render(): JsonResponse
    {
        return response()->json(
            data: [
                'message' => 'Ticket is locked by another staff member.',
                'held_by_staff_id' => $this->heldByStaffId,
                'expires_at' => $this->expiresAt,
            ],
            status: 423,
        );
    }
}
