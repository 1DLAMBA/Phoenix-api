<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Doctor;
use Faker\Factory as Faker;

class UserAndDoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        // Create users
        $users = User::factory()->count(5)->create([
            'user_type' => 'doctor', // Ensure user_type is set
            'password' => Hash::make('password'), // Set a default password
            'phoneno' => '080' . rand(10000000, 99999999),
            'gender' => 'Female',
            'passport' => 'https://randomuser.me/api/portraits/men/1.jpg'
        ]);

        foreach ($users as $user) {
            // Create a doctor profile for each user
            Doctor::create([
                'user_id' => $user->id,
                'license_number' => 'LIC-' . rand(10000, 99999),
                'med_school' => 'Medical University ' . rand(1, 5),
                'specialization' => ['Cardiology', 'Neurology', 'Pediatrics', 'General Medicine'][rand(0, 3)],
                'grad_year' => rand(2000, 2022),
                'degree_file' => 'degree_' . $user->id . '.pdf',
                'availability' => rand(0, 1),
            ]);
        }
    }
}