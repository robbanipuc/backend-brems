<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $designations = [
            // Grade 1-4 (Senior Officers)
            ['title' => 'Director General', 'title_bn' => 'মহাপরিচালক', 'grade' => 'Grade-1'],
            ['title' => 'Additional Director General', 'title_bn' => 'অতিরিক্ত মহাপরিচালক', 'grade' => 'Grade-2'],
            ['title' => 'Joint Director General', 'title_bn' => 'যুগ্ম মহাপরিচালক', 'grade' => 'Grade-3'],
            ['title' => 'Deputy Director General', 'title_bn' => 'উপ-মহাপরিচালক', 'grade' => 'Grade-4'],

                   ];

        foreach ($designations as $designation) {
            Designation::create($designation);
        }

        $this->command->info('Designations seeded: ' . Designation::count() . ' designations created');
    }
}