<?php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Headquarters
        $hq = Office::create([
            'name' => 'Bangladesh Railway Headquarters',
            'code' => 'BR-HQ',
            'location' => 'Rail Bhaban, Dhaka',
            'parent_id' => null,
        ]);

        // Zones
        $zones = [
            [
                'name' => 'East Zone',
                'code' => 'BR-EAST',
                'location' => 'Chittagong',
                'divisions' => [
                    [
                        'name' => 'Chittagong Division',
                        'code' => 'CTG-DIV',
                        'location' => 'Chittagong',
                        'stations' => [
                            ['name' => 'Chittagong Station', 'code' => 'CTG-STN', 'location' => 'Chittagong City'],
                            ['name' => 'Feni Station', 'code' => 'FENI-STN', 'location' => 'Feni'],
                            ['name' => 'Comilla Station', 'code' => 'CML-STN', 'location' => 'Comilla'],
                            ['name' => 'Brahmanbaria Station', 'code' => 'BBR-STN', 'location' => 'Brahmanbaria'],
                        ]
                    ],
                    [
                        'name' => 'Sylhet Division',
                        'code' => 'SYL-DIV',
                        'location' => 'Sylhet',
                        'stations' => [
                            ['name' => 'Sylhet Station', 'code' => 'SYL-STN', 'location' => 'Sylhet City'],
                            ['name' => 'Srimangal Station', 'code' => 'SRM-STN', 'location' => 'Srimangal'],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'West Zone',
                'code' => 'BR-WEST',
                'location' => 'Rajshahi',
                'divisions' => [
                    [
                        'name' => 'Dhaka Division',
                        'code' => 'DHK-DIV',
                        'location' => 'Dhaka',
                        'stations' => [
                            ['name' => 'Kamalapur Station', 'code' => 'KML-STN', 'location' => 'Dhaka'],
                            ['name' => 'Airport Station', 'code' => 'APT-STN', 'location' => 'Dhaka'],
                            ['name' => 'Tongi Station', 'code' => 'TNG-STN', 'location' => 'Gazipur'],
                            ['name' => 'Joydebpur Station', 'code' => 'JDP-STN', 'location' => 'Gazipur'],
                            ['name' => 'Mymensingh Station', 'code' => 'MYM-STN', 'location' => 'Mymensingh'],
                        ]
                    ],
                    [
                        'name' => 'Rajshahi Division',
                        'code' => 'RAJ-DIV',
                        'location' => 'Rajshahi',
                        'stations' => [
                            ['name' => 'Rajshahi Station', 'code' => 'RAJ-STN', 'location' => 'Rajshahi City'],
                            ['name' => 'Natore Station', 'code' => 'NAT-STN', 'location' => 'Natore'],
                            ['name' => 'Ishwardi Station', 'code' => 'ISW-STN', 'location' => 'Ishwardi'],
                        ]
                    ],
                    [
                        'name' => 'Khulna Division',
                        'code' => 'KHU-DIV',
                        'location' => 'Khulna',
                        'stations' => [
                            ['name' => 'Khulna Station', 'code' => 'KHU-STN', 'location' => 'Khulna City'],
                            ['name' => 'Jessore Station', 'code' => 'JSR-STN', 'location' => 'Jessore'],
                        ]
                    ]
                ]
            ],
        ];

        foreach ($zones as $zoneData) {
            $zone = Office::create([
                'name' => $zoneData['name'],
                'code' => $zoneData['code'],
                'location' => $zoneData['location'],
                'parent_id' => $hq->id,
            ]);

            foreach ($zoneData['divisions'] as $divisionData) {
                $division = Office::create([
                    'name' => $divisionData['name'],
                    'code' => $divisionData['code'],
                    'location' => $divisionData['location'],
                    'parent_id' => $zone->id,
                ]);

                foreach ($divisionData['stations'] as $stationData) {
                    Office::create([
                        'name' => $stationData['name'],
                        'code' => $stationData['code'],
                        'location' => $stationData['location'],
                        'parent_id' => $division->id,
                    ]);
                }
            }
        }

        $this->command->info('Offices seeded: ' . Office::count() . ' offices created');
    }
}