<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::where('email', 'admin@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => '123456789',
            ]);
        }

        if (User::where('email', 'usertest@example.com')->doesntExist()) {
            User::factory()->create([
                'name' => 'User Test',
                'email' => 'usertest@example.com',
                'password' => '123456789',
            ]);
        }
    }
}
