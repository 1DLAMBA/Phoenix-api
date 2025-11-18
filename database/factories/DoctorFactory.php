<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $specializations = [
            'Cardiology', 'Neurology', 'Pediatrics', 'General Medicine',
            'Orthopedics', 'Dermatology', 'Psychiatry', 'Oncology',
            'Emergency Medicine', 'Internal Medicine', 'Surgery', 'Radiology'
        ];

        return [
            'license_number' => 'LIC-' . fake()->numerify('#####'),
            'med_school' => fake()->randomElement([
                'Harvard Medical School',
                'Johns Hopkins University School of Medicine',
                'Mayo Clinic Alix School of Medicine',
                'University of California San Francisco School of Medicine',
                'Medical University of Nigeria',
                'Lagos University Teaching Hospital Medical School'
            ]),
            'specialization' => fake()->randomElement($specializations),
            'grad_year' => fake()->numberBetween(2000, 2023),
            'degree_file' => 'degree_' . fake()->uuid() . '.pdf',
            'availability' => fake()->boolean(80), // 80% chance of being available
        ];
    }
}
