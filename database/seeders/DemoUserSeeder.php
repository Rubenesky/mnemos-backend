<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the public demo account for evaluation/jury use.
 * Safe to run multiple times — uses updateOrCreate to avoid duplicates.
 * is_protected is intentionally false: this account can be freely modified.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@mnemos.app'],
            [
                'name'         => 'Demo Aircury',
                'password'     => Hash::make('Demo1234!'),
                'role'         => 'admin',
                'is_active'    => true,
                'is_protected' => false,
            ]
        );
    }
}
