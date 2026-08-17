<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@ditib-krefeld.de'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin123!'),
                'is_active' => true,
                'language_preference' => 'de',
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole('superadmin');

        $this->command->info("Superadmin erstellt: admin@ditib-krefeld.de");
    }
}
