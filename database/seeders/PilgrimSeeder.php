<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pilgrim;
use App\Models\UmrahBatch;
use Illuminate\Database\Seeder;

class PilgrimSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UmrahBatchSeeder::class);

        UmrahBatch::query()
            ->withoutGlobalScopes()
            ->whereIn('code', ['UMR-JKT-2026-01', 'UMR-SBY-2026-01'])
            ->get()
            ->each(function (UmrahBatch $batch): void {
                foreach ([1, 2] as $number) {
                    Pilgrim::updateOrCreate(
                        [
                            'umrah_batch_id' => $batch->getKey(),
                            'passport_no' => "P{$batch->office_id}{$number}2026",
                        ],
                        [
                            'office_id' => $batch->office_id,
                            'name' => "Jamaah {$batch->office_id}-{$number}",
                            'phone' => "0812{$batch->office_id}{$number}2026",
                            'status' => $number === 1
                                ? Pilgrim::STATUS_CONFIRMED
                                : Pilgrim::STATUS_REGISTERED,
                            'is_active' => true,
                            'disabled_at' => null,
                        ],
                    );
                }
            });
    }
}
