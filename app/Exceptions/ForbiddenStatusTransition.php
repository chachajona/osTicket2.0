<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ForbiddenStatusTransition extends RuntimeException
{
    public function __construct(
        public readonly string $fromState,
        public readonly string $toState,
    ) {
        parent::__construct("Transition from {$fromState} to {$toState} is forbidden.");
    }

    public function render(): JsonResponse
    {
        return response()->json(
            data: [
                'message' => 'Status transition not allowed.',
                'errors' => [
                    'status_id' => ["Transition from {$this->fromState} to {$this->toState} is forbidden."],
                ],
            ],
            status: 422,
        );
    }
}
