<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

/**
 * Seeds the first admin account. There is no public admin self-registration
 * endpoint, so this is the only way to create an admin.
 *
 * Run with: php artisan db:seed --class=AdminSeeder
 * Override credentials via env vars, or edit the defaults below for local dev.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => env('ADMIN_SEED_EMAIL', 'admin@phoenix.local')],
            [
                'name' => env('ADMIN_SEED_NAME', 'Platform Admin'),
                'staff_id' => env('ADMIN_SEED_STAFF_ID', 'ADMIN-001'),
                'phoneno' => env('ADMIN_SEED_PHONE', '0000000000'),
                'password' => env('ADMIN_SEED_PASSWORD', 'password123'),
            ]
        );
    }
}
