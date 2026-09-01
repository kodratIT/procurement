<?php

namespace App\Filament\Resources\ApproverMappings;

use App\Filament\Resources\ApproverMappings\Pages\CreateApproverMapping;
use App\Filament\Resources\ApproverMappings\Pages\EditApproverMapping;
use App\Filament\Resources\ApproverMappings\Pages\ListApproverMappings;
use App\Filament\Resources\ApproverMappings\Schemas\ApproverMappingForm;
use App\Filament\Resources\ApproverMappings\Tables\ApproverMappingsTable;
use App\Models\ApproverMapping;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApproverMappingResource extends Resource
{
    protected static ?string $model = ApproverMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Approver Mappings';

    protected static ?string $modelLabel = 'approver mapping';

    protected static ?string $pluralModelLabel = 'approver mappings';

    public static function form(Schema $schema): Schema
    {
        return ApproverMappingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApproverMappingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApproverMappings::route('/'),
            'create' => CreateApproverMapping::route('/create'),
            'edit' => EditApproverMapping::route('/{record}/edit'),
        ];
    }
}
