<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\BranchManagerEmployeeSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
           // CitySeeder::class,
           // ServiceSeeder::class,
           // OwnerSeedere::class,
          //  AreaManagerSeedere::class,
           // EmployeeSeeder::class,
           // BranchManagerSeeder::class,
            //ClientSeeder::class ,
            FullSystemSeeder::class,
        ]);
    }
}