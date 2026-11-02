<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * demo accounts, all with the password "password".
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
        User::factory()->planner()->create(['name' => 'Mahsa Ahmadi', 'email' => 'planner@example.com']);
        User::factory()->installer()->create(['name' => 'Ali Karimi', 'email' => 'installer1@example.com']);
        User::factory()->installer()->create(['name' => 'Reza Moradi', 'email' => 'installer2@example.com']);
    }
}
