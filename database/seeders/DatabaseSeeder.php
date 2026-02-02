<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            OfficeSeeder::class,
            DesignationSeeder::class,
            UserSeeder::class,
            EmployeeSeeder::class,
        ]);
    }
}