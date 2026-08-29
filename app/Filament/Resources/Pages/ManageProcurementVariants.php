<?php
namespace App\Filament\Resources\Pages;
use Filament\Actions\CreateAction; use Filament\Resources\Pages\ManageRecords;
class ManageProcurementVariants extends ManageRecords { protected static string $resource=\App\Filament\Resources\ProcurementVariantResource::class; protected function getHeaderActions():array{return [CreateAction::make()];} }
