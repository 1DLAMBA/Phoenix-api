<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Admin>
 */
class AdminFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'staff_id' => 'ADM-' . fake()->numerify('#####'),
            'email' => fake()->unique()->safeEmail(),
            'phoneno' => '080' . fake()->numerify('########'),
            'password' => Hash::make('password'),
        ];
    }
}
