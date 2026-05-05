<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dept_id' => 1,
            'username' => fake()->unique()->userName(),
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'passwd' => Hash::make('password'),
            'isactive' => 1,
            'isadmin' => 0,
            'created' => now(),
            'lastlogin' => null,
        ];
    }

    public function admin(): self
    {
        return $this->state([
            'isadmin' => 1,
        ]);
    }
}
