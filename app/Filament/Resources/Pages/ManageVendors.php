<?php
namespace App\Filament\Resources\Pages;
use Filament\Actions\CreateAction; use Filament\Resources\Pages\ManageRecords;
class ManageVendors extends ManageRecords { protected static string $resource=\App\Filament\Resources\VendorResource::class; protected function getHeaderActions():array{return [CreateAction::make()];} }
