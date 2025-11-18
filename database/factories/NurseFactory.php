<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Nurse>
 */
class NurseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $specializations = [
            'Critical Care', 'Emergency Nursing', 'Pediatric Nursing',
            'Mental Health Nursing', 'Oncology Nursing', 'Operating Room Nursing',
            'Cardiac Care', 'General Nursing', 'Community Health Nursing'
        ];

        return [
            'license_number' => 'NUR-' . fake()->numerify('#####'),
            'med_school' => fake()->randomElement([
                'Nursing School of Excellence',
                'University of Nursing Sciences',
                'College of Nursing and Midwifery',
                'School of Health Sciences',
                'Nigerian College of Nursing'
            ]),
            'specialization' => fake()->randomElement($specializations),
            'grad_year' => fake()->numberBetween(2000, 2023),
            'degree_file' => 'degree_' . fake()->uuid() . '.pdf',
        ];
    }
}
