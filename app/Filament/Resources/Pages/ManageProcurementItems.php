<?php
namespace App\Filament\Resources\Pages;
use Filament\Actions\CreateAction; use Filament\Resources\Pages\ManageRecords;
class ManageProcurementItems extends ManageRecords { protected static string $resource=\App\Filament\Resources\ProcurementItemResource::class; protected function getHeaderActions():array{return [CreateAction::make()];} }
