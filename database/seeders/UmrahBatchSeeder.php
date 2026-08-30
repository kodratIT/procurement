<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Office;
use App\Models\UmrahBatch;
use Illuminate\Database\Seeder;

class UmrahBatchSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            'JKT' => Office::firstOrCreate(
                ['code' => 'JKT'],
                ['name' => 'Kantor Pusat Jakarta', 'is_active' => true, 'disabled_at' => null],
            ),
            'SBY' => Office::firstOrCreate(
                ['code' => 'SBY'],
                ['name' => 'Kantor Regional Surabaya', 'is_active' => true, 'disabled_at' => null],
            ),
        ];

        foreach ($offices as $officeCode => $office) {
            UmrahBatch::updateOrCreate(
                ['office_id' => $office->getKey(), 'code' => "UMR-{$officeCode}-2026-01"],
                [
                    'name' => "Umroh {$office->name} Januari 2026",
                    'departure_date' => '2026-01-15',
                    'return_date' => '2026-01-27',
                    'capacity' => 45,
                    'pilgrim_count' => 2,
                    'status' => UmrahBatch::STATUS_OPEN,
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );

            UmrahBatch::updateOrCreate(
                ['office_id' => $office->getKey(), 'code' => "UMR-{$officeCode}-2026-02"],
                [
                    'name' => "Umroh {$office->name} Februari 2026",
                    'departure_date' => '2026-02-12',
                    'return_date' => '2026-02-24',
                    'capacity' => 50,
                    'pilgrim_count' => 0,
                    'status' => UmrahBatch::STATUS_PLANNED,
                    'is_active' => true,
                    'disabled_at' => null,
                ],
            );
        }
    }
}
