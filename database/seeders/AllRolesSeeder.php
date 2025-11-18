<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use App\Models\User;
use App\Models\Doctor;
use App\Models\Nurse;
use App\Models\Client;
use App\Models\Admin;
use Faker\Factory as Faker;

class AllRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Safety check for production
        if (app()->environment('production') && !$this->command->confirm('⚠️  You are in PRODUCTION environment. Are you sure you want to seed data?', false)) {
            $this->command->warn('Seeding cancelled.');
            return;
        }

        $faker = Faker::create();
        
        // Get photo path from environment or use default (supports both Windows and Linux)
        $sourcePhotosPath = env('SEEDER_PHOTOS_PATH', base_path('storage/seeder-photos'));
        
        // Try multiple possible paths for development
        if (!File::exists($sourcePhotosPath) && app()->environment('local')) {
            $possiblePaths = [
                'C:/Users/Daniel/Documents/AA people',
                base_path('storage/seeder-photos'),
                storage_path('seeder-photos'),
            ];
            
            foreach ($possiblePaths as $path) {
                if (File::exists($path)) {
                    $sourcePhotosPath = $path;
                    break;
                }
            }
        }

        $photos = [
            'download (1).jpeg',
            'download (2).jpeg',
            'download (3).jpeg',
            'download (4).jpeg',
            'download.jpeg',
            'download21.jpeg',
            'whitewoma.jpeg'
        ];

        // Ensure storage directory exists
        $storagePath = storage_path('app/public/files');
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        // Copy photos to storage and create array of filenames
        $photoFilenames = [];
        if (File::exists($sourcePhotosPath)) {
            foreach ($photos as $photo) {
                $sourcePath = $sourcePhotosPath . DIRECTORY_SEPARATOR . $photo;
                if (File::exists($sourcePath)) {
                    // Generate a unique filename
                    $extension = File::extension($photo);
                    $filename = uniqid('passport_', true) . '.' . $extension;
                    
                    // Copy the file using Storage facade
                    $fileContents = File::get($sourcePath);
                    Storage::disk('local')->put('public/files/' . $filename, $fileContents);
                    $photoFilenames[] = $filename;
                }
            }
            
            if (empty($photoFilenames)) {
                $this->command->warn('⚠️  No photos found in: ' . $sourcePhotosPath);
                $this->command->info('Continuing without passport photos...');
            }
        } else {
            $this->command->warn('⚠️  Photo directory not found: ' . $sourcePhotosPath);
            $this->command->info('Continuing without passport photos...');
            $this->command->info('💡 Set SEEDER_PHOTOS_PATH in .env to specify photo location');
        }

        // If we have fewer photos than needed, we'll reuse them
        $photoIndex = 0;
        $getPhoto = function() use (&$photoIndex, $photoFilenames) {
            if (empty($photoFilenames)) {
                return null;
            }
            $photo = $photoFilenames[$photoIndex % count($photoFilenames)];
            $photoIndex++;
            return $photo;
        };

        // Get password from env or use default (only for development)
        $defaultPassword = app()->environment('production') 
            ? env('SEEDER_DEFAULT_PASSWORD', null)
            : 'password';
        
        if (app()->environment('production') && !$defaultPassword) {
            $this->command->error('❌ SEEDER_DEFAULT_PASSWORD must be set in production .env');
            return;
        }

        // Create 5+ Doctors
        $this->command->info('Creating Doctors...');
        $doctors = [];
        for ($i = 0; $i < 5; $i++) {
            $email = 'doctor' . ($i + 1) . '@hospital.com';
            
            // Check if user already exists
            if (User::where('email', $email)->exists()) {
                $this->command->warn("⚠️  User {$email} already exists, skipping...");
                $existingUser = User::where('email', $email)->first();
                $existingDoctor = Doctor::where('user_id', $existingUser->id)->first();
                if ($existingDoctor) {
                    $doctors[] = $existingDoctor;
                }
                continue;
            }

            $user = User::create([
                'name' => $faker->name(),
                'email' => $email,
                'phoneno' => '080' . $faker->numerify('########'),
                'gender' => $faker->randomElement(['Male', 'Female']),
                'user_type' => 'doctor',
                'passport' => $getPhoto(),
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]);

            $doctor = Doctor::create([
                'user_id' => $user->id,
                'license_number' => 'LIC-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'med_school' => $faker->randomElement([
                    'Harvard Medical School',
                    'Johns Hopkins University School of Medicine',
                    'Mayo Clinic Alix School of Medicine',
                    'University of California San Francisco School of Medicine',
                    'Medical University of Nigeria',
                    'Lagos University Teaching Hospital Medical School'
                ]),
                'specialization' => $faker->randomElement([
                    'Cardiology', 'Neurology', 'Pediatrics', 'General Medicine',
                    'Orthopedics', 'Dermatology', 'Psychiatry', 'Oncology',
                    'Emergency Medicine', 'Internal Medicine', 'Surgery', 'Radiology'
                ]),
                'grad_year' => $faker->numberBetween(2000, 2023),
                'degree_file' => 'degree_' . $user->id . '.pdf',
                'availability' => $faker->boolean(80),
            ]);

            $doctors[] = $doctor;
        }

        // Create 5+ Nurses
        $this->command->info('Creating Nurses...');
        $nurses = [];
        for ($i = 0; $i < 5; $i++) {
            $email = 'nurse' . ($i + 1) . '@hospital.com';
            
            // Check if user already exists
            if (User::where('email', $email)->exists()) {
                $this->command->warn("⚠️  User {$email} already exists, skipping...");
                $existingUser = User::where('email', $email)->first();
                $existingNurse = Nurse::where('user_id', $existingUser->id)->first();
                if ($existingNurse) {
                    $nurses[] = $existingNurse;
                }
                continue;
            }

            $user = User::create([
                'name' => $faker->name(),
                'email' => $email,
                'phoneno' => '080' . $faker->numerify('########'),
                'gender' => $faker->randomElement(['Male', 'Female']),
                'user_type' => 'nurse',
                'passport' => $getPhoto(),
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]);

            $nurse = Nurse::create([
                'user_id' => $user->id,
                'license_number' => 'NUR-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT),
                'med_school' => $faker->randomElement([
                    'Nursing School of Excellence',
                    'University of Nursing Sciences',
                    'College of Nursing and Midwifery',
                    'School of Health Sciences',
                    'Nigerian College of Nursing'
                ]),
                'specialization' => $faker->randomElement([
                    'Critical Care', 'Emergency Nursing', 'Pediatric Nursing',
                    'Mental Health Nursing', 'Oncology Nursing', 'Operating Room Nursing',
                    'Cardiac Care', 'General Nursing', 'Community Health Nursing'
                ]),
                'grad_year' => $faker->numberBetween(2000, 2023),
                'degree_file' => 'degree_' . $user->id . '.pdf',
            ]);

            $nurses[] = $nurse;
        }

        // Create 5+ Clients
        $this->command->info('Creating Clients...');
        $clients = [];
        for ($i = 0; $i < 5; $i++) {
            $email = 'client' . ($i + 1) . '@hospital.com';
            
            // Check if user already exists
            if (User::where('email', $email)->exists()) {
                $this->command->warn("⚠️  User {$email} already exists, skipping...");
                $existingUser = User::where('email', $email)->first();
                $existingClient = Client::where('user_id', $existingUser->id)->first();
                if ($existingClient) {
                    $clients[] = $existingClient;
                }
                continue;
            }

            $user = User::create([
                'name' => $faker->name(),
                'email' => $email,
                'phoneno' => '080' . $faker->numerify('########'),
                'gender' => $faker->randomElement(['Male', 'Female']),
                'user_type' => 'client',
                'passport' => $getPhoto(),
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
            ]);

            // Assign random doctor and nurse to some clients
            $assignedDoctor = !empty($doctors) && $faker->boolean(70) 
                ? $doctors[$faker->numberBetween(0, count($doctors) - 1)]->id 
                : null;
            $assignedNurse = !empty($nurses) && $faker->boolean(60) 
                ? $nurses[$faker->numberBetween(0, count($nurses) - 1)]->id 
                : null;

            $client = Client::create([
                'user_id' => $user->id,
                'assigned_doctor_id' => $assignedDoctor,
                'assigned_nurse_id' => $assignedNurse,
                'date_of_birth' => $faker->date('Y-m-d', '-18 years'),
            ]);

            $clients[] = $client;
        }

        // Create 5+ Admins
        $this->command->info('Creating Admins...');
        $adminCount = 0;
        for ($i = 0; $i < 5; $i++) {
            $email = 'admin' . ($i + 1) . '@hospital.com';
            $staffId = 'ADM-' . str_pad($i + 1, 5, '0', STR_PAD_LEFT);
            
            // Check if admin already exists
            if (Admin::where('email', $email)->orWhere('staff_id', $staffId)->exists()) {
                $this->command->warn("⚠️  Admin {$email} or {$staffId} already exists, skipping...");
                continue;
            }

            Admin::create([
                'name' => $faker->name(),
                'staff_id' => $staffId,
                'email' => $email,
                'phoneno' => '080' . $faker->numerify('########'),
                'password' => Hash::make($defaultPassword),
            ]);
            $adminCount++;
        }

        $this->command->info('✅ Successfully seeded:');
        $this->command->info('- ' . count($doctors) . ' Doctors');
        $this->command->info('- ' . count($nurses) . ' Nurses');
        $this->command->info('- ' . count($clients) . ' Clients');
        $this->command->info('- ' . $adminCount . ' Admins');
        
        if (app()->environment('production')) {
            $this->command->warn('⚠️  All users have password from SEEDER_DEFAULT_PASSWORD');
        } else {
            $this->command->info('All users have password: "password"');
        }
    }
}

