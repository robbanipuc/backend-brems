<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Office;
use App\Models\Designation;
use App\Models\TransferHistory;
use App\Models\User;
use App\Models\FamilyMember;
use App\Models\Address;
use App\Models\AcademicRecord;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offices = Office::whereNotNull('parent_id')->get(); // Get non-HQ offices
        $designations = Designation::all();
        $superAdmin = User::where('role', 'super_admin')->first();

        if ($offices->isEmpty() || $designations->isEmpty()) {
            $this->command->error('Please run OfficeSeeder and DesignationSeeder first');
            return;
        }

        $sampleEmployees = $this->getSampleEmployees();

        foreach ($sampleEmployees as $index => $empData) {
            $office = $offices->random();
            $designation = $designations->random();
            $joiningDate = Carbon::now()->subYears(rand(1, 15))->subMonths(rand(0, 11));

            // Create employee
            $employee = Employee::create([
                'first_name' => $empData['first_name'],
                'last_name' => $empData['last_name'],
                'name_bn' => $empData['name_bn'],
                'nid_number' => $this->generateNID(),
                'phone' => '01' . rand(3, 9) . rand(10000000, 99999999),
                'designation_id' => $designation->id,
                'current_office_id' => $office->id,
                'gender' => $empData['gender'],
                'dob' => Carbon::now()->subYears(rand(25, 55))->subDays(rand(0, 365)),
                'religion' => $this->getRandomReligion(),
                'blood_group' => $this->getRandomBloodGroup(),
                'marital_status' => rand(0, 1) ? 'Married' : 'Single',
                'place_of_birth' => $this->getRandomDistrict(),
                'height' => rand(150, 185) . ' cm',
                'joining_date' => $joiningDate,
                'is_verified' => rand(0, 1),
                'status' => 'active',
            ]);

            // Create initial transfer history
            TransferHistory::create([
                'employee_id' => $employee->id,
                'from_office_id' => null,
                'to_office_id' => $office->id,
                'transfer_date' => $joiningDate,
                'order_number' => 'INIT-' . str_pad($employee->id, 6, '0', STR_PAD_LEFT),
                'remarks' => 'Initial posting',
                'created_by' => $superAdmin->id,
            ]);

            // Add family members
            $this->addFamilyMembers($employee);

            // Add addresses
            $this->addAddresses($employee);

            // Add academic records
            $this->addAcademicRecords($employee);

            // Create user account for some employees
            if (rand(0, 1)) {
                User::create([
                    'name' => $employee->full_name,
                    'email' => strtolower($empData['first_name']) . '.' . strtolower($empData['last_name']) . '@railway.gov.bd',
                    'password' => Hash::make('password123'),
                    'office_id' => $office->id,
                    'role' => 'verified_user',
                    'employee_id' => $employee->id,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('Employees seeded: ' . Employee::count() . ' employees created');
    }

    private function getSampleEmployees(): array
    {
        return [
            ['first_name' => 'Mohammad', 'last_name' => 'Rahman', 'name_bn' => 'মোহাম্মদ রহমান', 'gender' => 'male'],
            ['first_name' => 'Abdul', 'last_name' => 'Karim', 'name_bn' => 'আব্দুল করিম', 'gender' => 'male'],
            ['first_name' => 'Fatima', 'last_name' => 'Begum', 'name_bn' => 'ফাতিমা বেগম', 'gender' => 'female'],
            ['first_name' => 'Shahidul', 'last_name' => 'Islam', 'name_bn' => 'শহিদুল ইসলাম', 'gender' => 'male'],
            ['first_name' => 'Nasrin', 'last_name' => 'Akter', 'name_bn' => 'নাসরিন আক্তার', 'gender' => 'female'],
            ['first_name' => 'Rafiqul', 'last_name' => 'Haque', 'name_bn' => 'রফিকুল হক', 'gender' => 'male'],
            ['first_name' => 'Salma', 'last_name' => 'Khatun', 'name_bn' => 'সালমা খাতুন', 'gender' => 'female'],
            ['first_name' => 'Mizanur', 'last_name' => 'Rahman', 'name_bn' => 'মিজানুর রহমান', 'gender' => 'male'],
            ['first_name' => 'Hasina', 'last_name' => 'Begum', 'name_bn' => 'হাসিনা বেগম', 'gender' => 'female'],
            ['first_name' => 'Kamal', 'last_name' => 'Hossain', 'name_bn' => 'কামাল হোসেন', 'gender' => 'male'],
            ['first_name' => 'Rina', 'last_name' => 'Parvin', 'name_bn' => 'রিনা পারভীন', 'gender' => 'female'],
            ['first_name' => 'Jahangir', 'last_name' => 'Alam', 'name_bn' => 'জাহাঙ্গীর আলম', 'gender' => 'male'],
            ['first_name' => 'Shirin', 'last_name' => 'Sultana', 'name_bn' => 'শিরিন সুলতানা', 'gender' => 'female'],
            ['first_name' => 'Nurul', 'last_name' => 'Amin', 'name_bn' => 'নুরুল আমিন', 'gender' => 'male'],
            ['first_name' => 'Taslima', 'last_name' => 'Nasrin', 'name_bn' => 'তসলিমা নাসরিন', 'gender' => 'female'],
            ['first_name' => 'Aminul', 'last_name' => 'Islam', 'name_bn' => 'আমিনুল ইসলাম', 'gender' => 'male'],
            ['first_name' => 'Rehana', 'last_name' => 'Akter', 'name_bn' => 'রেহানা আক্তার', 'gender' => 'female'],
            ['first_name' => 'Shamsul', 'last_name' => 'Haque', 'name_bn' => 'শামসুল হক', 'gender' => 'male'],
            ['first_name' => 'Monira', 'last_name' => 'Begum', 'name_bn' => 'মনিরা বেগম', 'gender' => 'female'],
            ['first_name' => 'Delwar', 'last_name' => 'Hossain', 'name_bn' => 'দেলোয়ার হোসেন', 'gender' => 'male'],
        ];
    }

    private function generateNID(): string
    {
        // Generate 10 or 17 digit NID
        $length = rand(0, 1) ? 10 : 17;
        $nid = '';
        for ($i = 0; $i < $length; $i++) {
            $nid .= rand(0, 9);
        }
        return $nid;
    }

    private function getRandomReligion(): string
    {
        $religions = ['Islam', 'Hinduism', 'Buddhism', 'Christianity'];
        $weights = [88, 8, 2, 2]; // Approximate distribution in Bangladesh

        $rand = rand(1, 100);
        $cumulative = 0;

        foreach ($religions as $index => $religion) {
            $cumulative += $weights[$index];
            if ($rand <= $cumulative) {
                return $religion;
            }
        }

        return 'Islam';
    }

    private function getRandomBloodGroup(): string
    {
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        return $bloodGroups[array_rand($bloodGroups)];
    }

    private function getRandomDistrict(): string
    {
        $districts = [
            'Dhaka', 'Chittagong', 'Rajshahi', 'Khulna', 'Sylhet',
            'Rangpur', 'Barisal', 'Comilla', 'Gazipur', 'Narayanganj',
            'Mymensingh', 'Jessore', 'Bogra', 'Dinajpur', 'Brahmanbaria'
        ];
        return $districts[array_rand($districts)];
    }

    private function addFamilyMembers(Employee $employee): void
    {
        // Father
        FamilyMember::create([
            'employee_id' => $employee->id,
            'relation' => 'father',
            'name' => 'Late ' . $this->getRandomMaleName(),
            'name_bn' => 'মরহুম ' . $this->getRandomBengaliMaleName(),
            'occupation' => $this->getRandomOccupation(),
            'is_alive' => rand(0, 1),
        ]);

        // Mother
        FamilyMember::create([
            'employee_id' => $employee->id,
            'relation' => 'mother',
            'name' => $this->getRandomFemaleName(),
            'name_bn' => $this->getRandomBengaliFemaleName(),
            'is_alive' => true,
        ]);

        // Spouse (if married)
        if ($employee->marital_status === 'Married') {
            $spouseGender = $employee->gender === 'male' ? 'female' : 'male';
            $spouseName = $spouseGender === 'male' ? $this->getRandomMaleName() : $this->getRandomFemaleName();
            $spouseNameBn = $spouseGender === 'male' ? $this->getRandomBengaliMaleName() : $this->getRandomBengaliFemaleName();

            FamilyMember::create([
                'employee_id' => $employee->id,
                'relation' => 'spouse',
                'name' => $spouseName,
                'name_bn' => $spouseNameBn,
                'gender' => $spouseGender,
                'nid' => $this->generateNID(),
                'occupation' => $this->getRandomOccupation(),
                'is_active_marriage' => true,
                'is_alive' => true,
            ]);

            // Add children
            $numChildren = rand(0, 3);
            for ($i = 0; $i < $numChildren; $i++) {
                $childGender = rand(0, 1) ? 'male' : 'female';
                $childName = $childGender === 'male' ? $this->getRandomMaleName() : $this->getRandomFemaleName();
                $childNameBn = $childGender === 'male' ? $this->getRandomBengaliMaleName() : $this->getRandomBengaliFemaleName();

                FamilyMember::create([
                    'employee_id' => $employee->id,
                    'relation' => 'child',
                    'name' => $childName,
                    'name_bn' => $childNameBn,
                    'gender' => $childGender,
                    'dob' => Carbon::now()->subYears(rand(1, 20)),
                    'is_alive' => true,
                ]);
            }
        }
    }

    private function addAddresses(Employee $employee): void
    {
        $divisions = ['Dhaka', 'Chittagong', 'Rajshahi', 'Khulna', 'Sylhet', 'Rangpur', 'Barisal', 'Mymensingh'];

        // Present Address
        Address::create([
            'employee_id' => $employee->id,
            'type' => 'present',
            'division' => $divisions[array_rand($divisions)],
            'district' => $this->getRandomDistrict(),
            'upazila' => 'Sadar',
            'post_office' => 'Central Post Office',
            'house_no' => rand(1, 500) . '/' . chr(rand(65, 70)),
            'village_road' => 'Road No. ' . rand(1, 20) . ', Block ' . chr(rand(65, 70)),
        ]);

        // Permanent Address
        Address::create([
            'employee_id' => $employee->id,
            'type' => 'permanent',
            'division' => $divisions[array_rand($divisions)],
            'district' => $this->getRandomDistrict(),
            'upazila' => 'Sadar',
            'post_office' => 'Village Post Office',
            'house_no' => rand(1, 200),
            'village_road' => 'Village: ' . $this->getRandomVillage(),
        ]);
    }

    private function addAcademicRecords(Employee $employee): void
    {
        $examNames = ['SSC / Dakhil', 'HSC / Alim', 'Bachelor (Honors)', 'Masters', 'Diploma'];

        // Everyone has SSC
        AcademicRecord::create([
            'employee_id' => $employee->id,
            'exam_name' => 'SSC / Dakhil',
            'institute' => $this->getRandomSchool(),
            'passing_year' => (string)(Carbon::parse($employee->dob)->year + 16),
            'result' => $this->getRandomResult(),
        ]);

        // Most have HSC
        if (rand(0, 10) > 2) {
            AcademicRecord::create([
                'employee_id' => $employee->id,
                'exam_name' => 'HSC / Alim',
                'institute' => $this->getRandomCollege(),
                'passing_year' => (string)(Carbon::parse($employee->dob)->year + 18),
                'result' => $this->getRandomResult(),
            ]);
        }

        // Some have Bachelor
        if (rand(0, 10) > 5) {
            AcademicRecord::create([
                'employee_id' => $employee->id,
                'exam_name' => 'Bachelor (Honors)',
                'institute' => $this->getRandomUniversity(),
                'passing_year' => (string)(Carbon::parse($employee->dob)->year + 22),
                'result' => $this->getRandomResult(),
            ]);
        }

        // Few have Masters
        if (rand(0, 10) > 8) {
            AcademicRecord::create([
                'employee_id' => $employee->id,
                'exam_name' => 'Masters',
                'institute' => $this->getRandomUniversity(),
                'passing_year' => (string)(Carbon::parse($employee->dob)->year + 24),
                'result' => $this->getRandomResult(),
            ]);
        }
    }

    private function getRandomMaleName(): string
    {
        $names = ['Mohammad Karim', 'Abdul Rahman', 'Shahidul Haque', 'Rafiqul Islam', 'Nurul Amin', 'Jahangir Alam', 'Kamal Uddin'];
        return $names[array_rand($names)];
    }

    private function getRandomFemaleName(): string
    {
        $names = ['Fatima Begum', 'Nasrin Akter', 'Salma Khatun', 'Hasina Begum', 'Rina Parvin', 'Shirin Sultana'];
        return $names[array_rand($names)];
    }

    private function getRandomBengaliMaleName(): string
    {
        $names = ['মোহাম্মদ করিম', 'আব্দুল রহমান', 'শহিদুল হক', 'রফিকুল ইসলাম', 'নুরুল আমিন', 'জাহাঙ্গীর আলম'];
        return $names[array_rand($names)];
    }

    private function getRandomBengaliFemaleName(): string
    {
        $names = ['ফাতিমা বেগম', 'নাসরিন আক্তার', 'সালমা খাতুন', 'হাসিনা বেগম', 'রিনা পারভীন', 'শিরিন সুলতানা'];
        return $names[array_rand($names)];
    }

    private function getRandomOccupation(): string
    {
        $occupations = ['Farmer', 'Teacher', 'Business', 'Housewife', 'Government Service', 'Private Service', 'Retired'];
        return $occupations[array_rand($occupations)];
    }

    private function getRandomVillage(): string
    {
        $villages = ['Char Para', 'Uttar Para', 'Dakkhin Para', 'Paschim Para', 'Purba Para', 'Madhya Para'];
        return $villages[array_rand($villages)];
    }

    private function getRandomSchool(): string
    {
        $schools = ['Government High School', 'Model High School', 'Pilot High School', 'Central High School', 'National High School'];
        return $this->getRandomDistrict() . ' ' . $schools[array_rand($schools)];
    }

    private function getRandomCollege(): string
    {
        $colleges = ['Government College', 'City College', 'Model College', 'Central College', 'National College'];
        return $this->getRandomDistrict() . ' ' . $colleges[array_rand($colleges)];
    }

    private function getRandomUniversity(): string
    {
        $universities = [
            'University of Dhaka',
            'Bangladesh University of Engineering and Technology',
            'University of Chittagong',
            'Rajshahi University',
            'Jahangirnagar University',
            'National University',
            'Islamic University, Bangladesh',
        ];
        return $universities[array_rand($universities)];
    }

    private function getRandomResult(): string
    {
        $results = ['5.00', '4.80', '4.50', '4.00', '3.75', '3.50', '3.00', 'First Class', 'Second Class'];
        return $results[array_rand($results)];
    }
}