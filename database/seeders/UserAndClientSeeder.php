<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Client;
use Faker\Factory as Faker;

class UserAndClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $faker = Faker::create();
        // Create users
        $users = User::factory()->count(5)->create([
            'user_type' => 'client', // Ensure user_type is set
            'password' => Hash::make('password'), // Set a default password
            'phoneno' => '080' . rand(10000000, 99999999),
            'gender' => 'Male',
            'passport' => 'https://randomuser.me/api/portraits/men/1.jpg'
        ]);

        foreach ($users as $user) {
            // Create a doctor profile for each user
            Client::create([
                'user_id' => $user->id,
                'assigned_doctor_id' => null, // Assign random doctor
                // Random appointment
                'assigned_nurse_id' => null, // Assign random nurse
                'date_of_birth' => $faker->date('Y-m-d', '2005-01-01'),
            ]);
        }
    }
}
