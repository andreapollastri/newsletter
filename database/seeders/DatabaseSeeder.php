<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user if it doesn't exist
        if (! User::where('email', 'admin@newsletter.test')->exists()) {
            User::create([
                'name' => 'Admin',
                'email' => 'admin@newsletter.test',
                'password' => Hash::make('password'),
            ]);
        }
    }
}
