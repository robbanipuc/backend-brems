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
            ['title' => 'Director General', 'title_bn' => 'মহাপরিচালক', 'grade' => 'Grade-1', 'basic_salary' => 78000],
            ['title' => 'Additional Director General', 'title_bn' => 'অতিরিক্ত মহাপরিচালক', 'grade' => 'Grade-2', 'basic_salary' => 66000],
            ['title' => 'Joint Director General', 'title_bn' => 'যুগ্ম মহাপরিচালক', 'grade' => 'Grade-3', 'basic_salary' => 56500],
            ['title' => 'Deputy Director General', 'title_bn' => 'উপ-মহাপরিচালক', 'grade' => 'Grade-4', 'basic_salary' => 50000],

            // Grade 5-6 (Senior Management)
            ['title' => 'Chief Mechanical Engineer', 'title_bn' => 'প্রধান যন্ত্র প্রকৌশলী', 'grade' => 'Grade-5', 'basic_salary' => 43000],
            ['title' => 'Chief Signal Engineer', 'title_bn' => 'প্রধান সংকেত প্রকৌশলী', 'grade' => 'Grade-5', 'basic_salary' => 43000],
            ['title' => 'Divisional Railway Manager', 'title_bn' => 'বিভাগীয় রেলওয়ে ব্যবস্থাপক', 'grade' => 'Grade-5', 'basic_salary' => 43000],
            ['title' => 'Deputy Chief Engineer', 'title_bn' => 'উপ-প্রধান প্রকৌশলী', 'grade' => 'Grade-6', 'basic_salary' => 35500],

            // Grade 7-9 (Middle Management)
            ['title' => 'Station Superintendent', 'title_bn' => 'স্টেশন সুপারিনটেনডেন্ট', 'grade' => 'Grade-7', 'basic_salary' => 29000],
            ['title' => 'Assistant Mechanical Engineer', 'title_bn' => 'সহকারী যন্ত্র প্রকৌশলী', 'grade' => 'Grade-7', 'basic_salary' => 29000],
            ['title' => 'Station Master', 'title_bn' => 'স্টেশন মাস্টার', 'grade' => 'Grade-9', 'basic_salary' => 22000],
            ['title' => 'Assistant Station Master', 'title_bn' => 'সহকারী স্টেশন মাস্টার', 'grade' => 'Grade-10', 'basic_salary' => 16000],

            // Grade 10-13 (Junior Officers)
            ['title' => 'Ticket Collector', 'title_bn' => 'টিকেট কালেক্টর', 'grade' => 'Grade-11', 'basic_salary' => 12500],
            ['title' => 'Train Examiner', 'title_bn' => 'ট্রেন পরীক্ষক', 'grade' => 'Grade-11', 'basic_salary' => 12500],
            ['title' => 'Points Man', 'title_bn' => 'পয়েন্টস ম্যান', 'grade' => 'Grade-12', 'basic_salary' => 11300],
            ['title' => 'Gateman', 'title_bn' => 'গেটম্যান', 'grade' => 'Grade-13', 'basic_salary' => 11000],

            // Grade 14-16 (Support Staff)
            ['title' => 'Khalasi', 'title_bn' => 'খালাসী', 'grade' => 'Grade-14', 'basic_salary' => 10200],
            ['title' => 'Helper', 'title_bn' => 'হেলপার', 'grade' => 'Grade-15', 'basic_salary' => 9700],
            ['title' => 'Cleaner', 'title_bn' => 'ক্লিনার', 'grade' => 'Grade-16', 'basic_salary' => 9300],
            ['title' => 'Peon', 'title_bn' => 'পিয়ন', 'grade' => 'Grade-16', 'basic_salary' => 9300],

            // Technical Staff
            ['title' => 'Loco Pilot', 'title_bn' => 'লোকো পাইলট', 'grade' => 'Grade-10', 'basic_salary' => 16000],
            ['title' => 'Assistant Loco Pilot', 'title_bn' => 'সহকারী লোকো পাইলট', 'grade' => 'Grade-12', 'basic_salary' => 11300],
            ['title' => 'Guard', 'title_bn' => 'গার্ড', 'grade' => 'Grade-11', 'basic_salary' => 12500],
            ['title' => 'Senior Guard', 'title_bn' => 'সিনিয়র গার্ড', 'grade' => 'Grade-10', 'basic_salary' => 16000],

            // Administrative Staff
            ['title' => 'Office Assistant', 'title_bn' => 'অফিস সহকারী', 'grade' => 'Grade-13', 'basic_salary' => 11000],
            ['title' => 'Computer Operator', 'title_bn' => 'কম্পিউটার অপারেটর', 'grade' => 'Grade-12', 'basic_salary' => 11300],
            ['title' => 'Accountant', 'title_bn' => 'হিসাবরক্ষক', 'grade' => 'Grade-10', 'basic_salary' => 16000],
            ['title' => 'Senior Accountant', 'title_bn' => 'সিনিয়র হিসাবরক্ষক', 'grade' => 'Grade-9', 'basic_salary' => 22000],
        ];

        foreach ($designations as $designation) {
            Designation::create($designation);
        }

        $this->command->info('Designations seeded: ' . Designation::count() . ' designations created');
    }
}