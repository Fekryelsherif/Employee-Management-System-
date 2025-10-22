<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\City;
use App\Models\Client;
use App\Models\Region;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class FullSystemSeeder extends Seeder
{
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // حذف كل الجداول (ماعدا migrations)
        $tables = DB::select('SHOW TABLES');
        $databaseName = env('DB_DATABASE');
        $key = "Tables_in_{$databaseName}";

        foreach ($tables as $table) {
            $tableName = $table->$key;
            if ($tableName === 'migrations') continue;
            DB::table($tableName)->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        $this->command->info('✅ All tables cleared successfully!');

        // 1️⃣ المدن الأساسية
        $cities = collect(['Cairo', 'Alexandria', 'Giza'])->map(function ($name) {
            return City::create(['name' => $name]);
        });
        $cityIds = $cities->pluck('id')->toArray();

        // 2️⃣ الخدمات
        $services = collect([
            Service::create(['name' => 'DCL', 'base_price' => 100]),
            Service::create(['name' => 'H4C', 'base_price' => 200]),
            Service::create(['name' => 'بونيت', 'base_price' => 150]),
        ]);

        // 3️⃣ مالك النظام
        $owner = User::create([
            'fname' => 'Owner',
            'lname' => 'System',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'type' => 'owner',
        ]);

        $tokens = [];
        $tokens['owner'] = $owner->createToken('owner-token', ['owner'])->plainTextToken;

        // 4️⃣ إنشاء المناطق ومديريها
        $regions = collect();
        for ($i = 1; $i <= 3; $i++) {
            $regionManager = User::create([
                'fname' => "RegionMgr{$i}",
                'lname' => "Test",
                'email' => "region{$i}@example.com",
                'password' => Hash::make('password'),
                'type' => 'region-manager',
            ]);

            $tokens["region_manager_{$i}"] = $regionManager->createToken("region{$i}-token", ['region_manager'])->plainTextToken;

            $region = Region::create([
                'name' => "Region {$i}",
                'region_manager_id' => $regionManager->id,
            ]);

            $regions->push($region);
        }

        // 5️⃣ إنشاء الفروع ومديريها وموظفيهم
        $branches = collect();
        $employees = collect();

        foreach ($regions as $region) {
            for ($j = 1; $j <= 3; $j++) {
                $branchManager = User::create([
                    'fname' => "BranchMgr{$region->id}_{$j}",
                    'lname' => "Test",
                    'email' => "branchmgr{$region->id}_{$j}@example.com",
                    'password' => Hash::make('password'),
                    'type' => 'branch-manager',
                ]);

                $tokens["branch_manager_{$region->id}_{$j}"] = $branchManager
                    ->createToken("branch{$region->id}_{$j}-token", ['branch_manager'])
                    ->plainTextToken;

                $branch = Branch::create([
                    'name' => "Branch {$region->id}-{$j}",
                    'region_id' => $region->id,
                ]);

                $branches->push($branch);

                // موظفين
                for ($k = 1; $k <= 3; $k++) {
                    $employee = User::create([
                        'fname' => "Employee{$region->id}_{$j}_{$k}",
                        'lname' => "Test",
                        'email' => "emp{$region->id}_{$j}_{$k}@example.com",
                        'password' => Hash::make('password'),
                        'type' => 'employee',
                        'branch_id' => $branch->id,
                        'branch_manager_id' => $branchManager->id,
                    ]);

                    $employees->push($employee);

                    $tokens["employee_{$region->id}_{$j}_{$k}"] = $employee
                        ->createToken("emp{$region->id}_{$j}_{$k}-token", ['employee'])
                        ->plainTextToken;
                }
            }
        }

        // 6️⃣ العملاء (لكل موظف عميلين عشوائيين)
        $clients = collect();
        foreach ($employees as $employee) {
            for ($i = 1; $i <= 2; $i++) {
                $clients->push(Client::create([
                    'name' => "Client_{$employee->id}_{$i}",
                    'phone' => "0100" . rand(100000, 999999),
                    'email' => "client{$employee->id}_{$i}@example.com",
                    'address' => "Address for Client {$i}",
                    'city_id' => $cityIds[array_rand($cityIds)],
                    'created_by' => $employee->id,
                ]));
            }



        }

        // 8️⃣ حفظ التوكنات
        Storage::put('tokens.json', json_encode($tokens, JSON_PRETTY_PRINT));

        $this->command->info("✅ Seeding completed! Tokens saved in storage/app/tokens.json");
    }
}