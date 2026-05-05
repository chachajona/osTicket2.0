<?php

namespace Database\Factories;

use App\Models\Thread;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ThreadFactory extends Factory
{
    protected $model = Thread::class;

    public function definition(): array
    {
        return [
            'object_id' => $this->faker->numberBetween(1, 1000),
            'object_type' => 'T',
            'created' => now(),
        ];
    }
}
