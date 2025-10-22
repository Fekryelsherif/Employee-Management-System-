<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
       $client2= Client::create([
        "name"=> "Mhamedo Samir",
        "email"=> "client2@gmail.com",
        "phone"=> "01238654789",
        "national_id"=> "12345665554234",
        "address"=> "khalafat street, Cairo",
        "location"=> "Floor 2",
        "notes"=> "new client",
        "city_id"=> 3,
        //"services"=> [1],
   ]);
        $client3= Client::create([
            "name"=> "Mohamed Ali",
            "email"=> "client3@gmail.com",
            "phone"=> "012386547789",
            "national_id"=> "9895665554234",
            "address"=> "mohamed street, Cairo",
            "location"=> "near el azhar",
            "notes"=> "new client",
            "city_id"=> 3,
          //  "services"=> [1,2],
        ]);
        $client4= Client::create([
            "name"=> "Ahmed Hassan",
            "email"=> " client4@gmail.com",
            "phone"=> "012386900789",
            "national_id"=> "99753680954234",
            "address"=> "orab street, Cairo",
            "location"=> "walking street",
            "notes"=> "frequent client",
            "city_id"=> 6,
            //"services"=> [1,2,4,5],


        ]);
        $client2->services()->attach([1]);
        $client3->services()->attach([1, 2]);
        $client4->services()->attach([1, 2, 4, 5]);


}


}
