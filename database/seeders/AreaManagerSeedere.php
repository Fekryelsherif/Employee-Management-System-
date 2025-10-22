<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AreaManagerSeedere extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        User::create([
            'fname' => 'Mohamed ',
            'lname' => 'Gamal',
            'email' => 'area_manager2@example.com',
            'password' => Hash::make('123456'),
            //'password_confirmation' => Hash::make('123456'),
            'type' => 'area_manager',
        ]);

        User::create([
            'fname' => 'Ahmed',
            'lname' => 'Saeed',
            'email' => 'area_manager3@example.com',
            'password' => Hash::make('123456'),
          //  'password_confirmation' => Hash::make('123456'),
            'type' => 'area_manager',
        ]);

        User::create([
            'fname' => 'Youssef',
            'lname' => 'Ibrahim',
            'email' => 'area_manager4@example.com',
            'password' => Hash::make('123456'),
           // 'password_confirmation' => Hash::make('123456'),
            'type' => 'area_manager',
        ]);
    }
}
