<?php

// RJC

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the initial admin account for production.
 * Safe to run multiple times — uses updateOrCreate to avoid duplicates.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@mnemos.app'],
            [
                'name' => 'Admin',
                'password' => Hash::make('Admin1234!'),
                'role' => 'admin',
                'is_active' => true,
                'is_protected' => true,
            ]
        );
    }
}
