<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assigned_doctor_id' => null, // Will be set in seeder
            'assigned_nurse_id' => null, // Will be set in seeder
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'), // At least 18 years old
        ];
    }
}
