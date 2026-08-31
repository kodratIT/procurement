<?php

declare(strict_types=1);

namespace App\Filament\Resources\Distributions\Pages;

use App\Filament\Resources\Distributions\DistributionResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewDistribution extends ViewRecord
{
    protected static string $resource = DistributionResource::class;
}
