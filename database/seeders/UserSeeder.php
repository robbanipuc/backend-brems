<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Office;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get offices
        $hq = Office::where('code', 'BR-HQ')->first();
        $eastZone = Office::where('code', 'BR-EAST')->first();
        $westZone = Office::where('code', 'BR-WEST')->first();
        $ctgDiv = Office::where('code', 'CTG-DIV')->first();
        $dhkDiv = Office::where('code', 'DHK-DIV')->first();
        $kmlStation = Office::where('code', 'KML-STN')->first();
        $ctgStation = Office::where('code', 'CTG-STN')->first();

        // Super Admin (No employee link)
        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@railway.gov.bd',
            'password' => Hash::make('admin123'),
            'office_id' => $hq->id,
            'role' => 'super_admin',
            'is_active' => true,
            'employee_id' => null,
        ]);

        // Additional Super Admin
        User::create([
            'name' => 'DG Office Admin',
            'email' => 'dg@railway.gov.bd',
            'password' => Hash::make('admin123'),
            'office_id' => $hq->id,
            'role' => 'super_admin',
            'is_active' => true,
            'employee_id' => null,
        ]);

        // Zone Admins
        if ($eastZone) {
            User::create([
                'name' => 'East Zone Admin',
                'email' => 'east.admin@railway.gov.bd',
                'password' => Hash::make('admin123'),
                'office_id' => $eastZone->id,
                'role' => 'office_admin',
                'is_active' => true,
                'employee_id' => null,
            ]);
        }

        if ($westZone) {
            User::create([
                'name' => 'West Zone Admin',
                'email' => 'west.admin@railway.gov.bd',
                'password' => Hash::make('admin123'),
                'office_id' => $westZone->id,
                'role' => 'office_admin',
                'is_active' => true,
                'employee_id' => null,
            ]);
        }

        // Division Admins
        if ($ctgDiv) {
            User::create([
                'name' => 'Chittagong Division Admin',
                'email' => 'ctg.div.admin@railway.gov.bd',
                'password' => Hash::make('admin123'),
                'office_id' => $ctgDiv->id,
                'role' => 'office_admin',
                'is_active' => true,
                'employee_id' => null,
            ]);
        }

        if ($dhkDiv) {
            User::create([
                'name' => 'Dhaka Division Admin',
                'email' => 'dhk.div.admin@railway.gov.bd',
                'password' => Hash::make('admin123'),
                'office_id' => $dhkDiv->id,
                'role' => 'office_admin',
                'is_active' => true,
                'employee_id' => null,
            ]);
        }

        // Station Admins
        if ($kmlStation) {
            User::create([
                'name' => 'Kamalapur Station Admin',
                'email' => 'kamalapur.admin@railway.gov.bd',
                'password' => Hash::make('admin123'),
                'office_id' => $kmlStation->id,
                'role' => 'office_admin',
                'is_active' => true,
                'employee_id' => null,
            ]);
        }

        if ($ctgStation) {
            User::create([
                'name' => 'Chittagong Station Admin',
                'email' => 'ctg.station.admin@railway.gov.bd',
                'password' => Hash::make('admin123'),
                'office_id' => $ctgStation->id,
                'role' => 'office_admin',
                'is_active' => true,
                'employee_id' => null,
            ]);
        }

        $this->command->info('Users seeded: ' . User::count() . ' users created');
        $this->command->info('Login credentials: email: admin@railway.gov.bd, password: admin123');
    }
}