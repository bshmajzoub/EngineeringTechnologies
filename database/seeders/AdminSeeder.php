<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the factory admin account.
     * Uses updateOrCreate so it is safe to run multiple times without duplicating.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'Ihsanwardeh@gmail.com'],
            [
                'name' => 'Factory Manager',
                'password' => Hash::make('admin123456'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        $this->command->info('Admin account seeded: Ihsanwardeh@gmail.com / admin123456');
    }
}
