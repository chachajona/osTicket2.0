<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Queue;
use Illuminate\Database\Eloquent\Factories\Factory;

final class QueueFactory extends Factory
{
    protected $model = Queue::class;

    public function definition(): array
    {
        return [
            'parent_id' => 0,
            'staff_id' => 0,
            'flags' => '',
            'title' => $this->faker->words(3, true),
            'config' => '{}',
            'created' => now(),
            'updated' => now(),
        ];
    }
}
