<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerSeedere extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        User::create([
            'fname' => 'Gaber',
            'lname' => 'Hussien',
            'email' => 'owner2@example.com',
            'password' => Hash::make('123456'),
           // 'password_confirmation' => Hash::make('123456'),
            'type' => 'owner',
        ]);
    }
}
