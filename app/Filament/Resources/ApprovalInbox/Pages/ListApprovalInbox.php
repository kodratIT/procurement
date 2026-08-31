<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApprovalInbox\Pages;

use App\Filament\Resources\ApprovalInboxResource;
use Filament\Resources\Pages\ListRecords;

final class ListApprovalInbox extends ListRecords
{
    protected static string $resource = ApprovalInboxResource::class;
}
