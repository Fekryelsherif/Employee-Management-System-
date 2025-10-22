<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BranchManagerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       User::create([
            'fname' => 'Youssef',
            'lname' => 'Ibrahim',
            'email' => 'branch_manager2@example.com',
            'password' => Hash::make('123456'),
           // 'password_confirmation' => Hash::make('123456'),
            'type' => 'branch_manager',
        ]);

        User::create([
            'fname' => 'Salma',
            'lname' => 'Saeed',
            'email' => 'branch_manager3@example.com',
            'password' => Hash::make('123456'),
          //  'password_confirmation' => Hash::make('123456'),
            'type' => 'branch_manager',
        ]);


        User::create([
            'fname' => 'Omar',
            'lname' => 'Youssef',
            'email' => 'branch_manager4@example.com',
            'password' => Hash::make('123456'),
          //  'password_confirmation' => Hash::make('123456'),
            'type' => 'branch_manager',
        ]);
    }
}
