<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

       User::create([
            'fname' => 'Mohamed ',
            'lname' => 'Ali',
            'email' => 'employe2@example.com',
            'password' => Hash::make('123456'),
           // 'password_confirmation' => Hash::make('123456'),
            'type' => 'employee',
        ]);

        User::create([
            'fname' => 'Ahmed',
            'lname' => 'Hassan',
            'email' => 'employe3@example.com',
            'password' => Hash::make('123456'),
           // 'password_confirmation' => Hash::make('123456'),
            'type' => 'employee',
        ]);

        User::create([
            'fname' => 'Youssef',
            'lname' => 'Ibrahim',
            'email' => 'employe4@example.com',
            'password' => Hash::make('123456'),
           // 'password_confirmation' => Hash::make('123456'),
            'type' => 'employee',
        ]);
    }
}