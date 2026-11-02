<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Containers run migrate --seed on every start so only seed an empty database
        if (User::query()->exists()) {
            return;
        }

        $this->call([
            UserSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
