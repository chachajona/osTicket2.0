<?php

namespace Database\Factories;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('######'),
            'user_id' => 1,
            'status_id' => 1,
            'dept_id' => 1,
            'staff_id' => 0,
            'sla_id' => 0,
            'email_id' => 0,
            'source' => 'web',
            'ip_address' => fake()->ipv4(),
            'isoverdue' => 0,
            'isanswered' => 0,
            'duedate' => null,
            'closed' => null,
            'lastupdate' => now(),
            'lastmessage' => now(),
            'lastresponse' => now(),
            'created' => now(),
            'updated' => now(),
        ];
    }
}
