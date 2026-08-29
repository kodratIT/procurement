<?php
namespace App\Filament\Resources\Pages;
use Filament\Actions\CreateAction; use Filament\Resources\Pages\ManageRecords;
class ManageProcurementCategories extends ManageRecords { protected static string $resource=\App\Filament\Resources\ProcurementCategoryResource::class; protected function getHeaderActions():array{return [CreateAction::make()];} }
