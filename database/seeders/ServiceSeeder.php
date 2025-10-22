<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //add some services such as DCL,H4C,بونيت و اورنج كاش و اجهزة
        $services = ['DCL', 'H4C', 'بونيت', 'اورنج كاش', 'اجهزة'];
        foreach ($services as $service) {
            Service::create(['name' => $service]);
        }
    }

}