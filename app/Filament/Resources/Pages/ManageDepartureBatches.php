<?php
namespace App\Filament\Resources\Pages;
use Filament\Actions\CreateAction; use Filament\Resources\Pages\ManageRecords;
class ManageDepartureBatches extends ManageRecords { protected static string $resource=\App\Filament\Resources\DepartureBatchResource::class; protected function getHeaderActions():array{return [CreateAction::make()];} }
