<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\FeatureRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('feature_flags')->insertOrIgnore(array_map(
            static fn (string $key): array => [
                'key' => $key,
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            app(FeatureRegistry::class)->stateKeys(),
        ));
    }
}
